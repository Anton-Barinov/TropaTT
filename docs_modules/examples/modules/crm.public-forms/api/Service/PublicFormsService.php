<?php
declare(strict_types=1);

namespace Module\Crm\PublicForms\Service;

use PDO;
use RuntimeException;

/**
 * Service managing public lead intake forms and their submissions.
 */
final class PublicFormsService
{
    private const MAX_REQUEST_BYTES = 32768;
    private const MAX_FIELDS = 40;
    private const MAX_FIELD_VALUE_BYTES = 4096;
    private const MAX_TOTAL_VALUE_BYTES = 16384;
    private const RATE_WINDOW_SECONDS = 600;
    private const RATE_MAX_SUBMISSIONS = 5;

    private PDO $db;
    private ?\Closure $intakeCreator;
    private ?\Closure $calendarEventCreator;
    private ?\Closure $calendarEventAfterCommit;

    /**
     * @param null|callable(array<string,mixed>,array<string,mixed>):(array<string,mixed>|string) $intakeCreator
     */
    public function __construct(PDO $db, ?callable $intakeCreator = null, ?callable $calendarEventCreator = null, ?callable $calendarEventAfterCommit = null)
    {
        $this->db = $db;
        $this->intakeCreator = $intakeCreator !== null ? \Closure::fromCallable($intakeCreator) : null;
        $this->calendarEventCreator = $calendarEventCreator !== null ? \Closure::fromCallable($calendarEventCreator) : null;
        $this->calendarEventAfterCommit = $calendarEventAfterCommit !== null ? \Closure::fromCallable($calendarEventAfterCommit) : null;
    }

    /** @return list<array<string, mixed>> */
    public function listForms(int $organizationId): array
    {
        if ($organizationId < 1) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT f.public_id, f.slug, f.title, f.form_type, f.is_published, f.created_at, f.updated_at,'
            . ' (SELECT COUNT(*) FROM crm_public_form_submissions s WHERE s.form_id = f.id AND s.organization_id = f.organization_id) AS submission_count'
            . ' FROM crm_public_forms f WHERE f.organization_id = :org_id ORDER BY f.id DESC'
        );
        $stmt->execute([':org_id' => $organizationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function getFormForWorkspace(int $organizationId, string $publicId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT public_id, slug, title, description, form_type, fields_schema_json, consent_text, consent_version,'
            . ' target_project_public_id, target_assignee_user_public_id, calendar_user_public_id,'
            . ' slot_duration_minutes, buffer_minutes, booking_timezone, booking_schedule_json, is_published'
            . ' FROM crm_public_forms WHERE organization_id = :org_id AND public_id = :public_id LIMIT 1'
        );
        $stmt->execute([':org_id' => $organizationId, ':public_id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $schema = json_decode((string)$row['fields_schema_json'], true);
        if (!is_array($schema)) {
            throw new RuntimeException('Form schema is invalid.');
        }
        $row['fields_schema'] = $schema;
        $row['booking_schedule'] = json_decode((string)($row['booking_schedule_json'] ?? '[]'), true) ?: [];
        unset($row['booking_schedule_json']);
        unset($row['fields_schema_json']);
        return $row;
    }

    /** @return list<array{public_id:string,full_name:string}> */
    public function listCalendarUsers(int $organizationId): array
    {
        if ($organizationId < 1) return [];
        $stmt = $this->db->prepare('SELECT u.public_id, u.full_name FROM users u INNER JOIN organization_memberships om ON om.user_id = u.id WHERE om.organization_id = :org_id AND u.is_active = 1 AND u.deleted_at IS NULL ORDER BY u.full_name ASC LIMIT 500');
        $stmt->execute([':org_id' => $organizationId]);
        return array_map(static fn(array $row): array => ['public_id' => (string)$row['public_id'], 'full_name' => (string)$row['full_name']], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** @return array{projects:list<array{public_id:string,title:string}>,assignees:list<array{public_id:string,full_name:string}>} */
    public function listRoutingOptions(int $organizationId): array
    {
        if ($organizationId < 1) return ['projects' => [], 'assignees' => []];
        $projects = $this->db->prepare('SELECT public_id, title FROM projects WHERE organization_id = :org_id AND archived_at IS NULL AND deleted_at IS NULL ORDER BY title ASC LIMIT 500');
        $projects->execute([':org_id' => $organizationId]);
        $assignees = $this->db->prepare('SELECT u.public_id, u.full_name FROM users u INNER JOIN organization_memberships om ON om.user_id = u.id WHERE om.organization_id = :org_id AND u.is_active = 1 AND u.deleted_at IS NULL ORDER BY u.full_name ASC LIMIT 500');
        $assignees->execute([':org_id' => $organizationId]);
        return [
            'projects' => array_map(static fn(array $row): array => ['public_id' => (string)$row['public_id'], 'title' => (string)$row['title']], $projects->fetchAll(PDO::FETCH_ASSOC) ?: []),
            'assignees' => array_map(static fn(array $row): array => ['public_id' => (string)$row['public_id'], 'full_name' => (string)$row['full_name']], $assignees->fetchAll(PDO::FETCH_ASSOC) ?: []),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listSubmissions(int $organizationId, int $limit = 50): array
    {
        if ($organizationId < 1) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $stmt = $this->db->prepare(
            'SELECT s.public_id, s.intake_public_id, s.consent_agreed, s.consent_version,'
            . ' f.public_id AS form_public_id, f.title AS form_title, f.fields_schema_json, s.submitter_data_json, s.created_at'
            . ' FROM crm_public_form_submissions s'
            . ' INNER JOIN crm_public_forms f ON f.id = s.form_id AND f.organization_id = s.organization_id'
            . ' WHERE s.organization_id = :org_id ORDER BY s.id DESC LIMIT ' . $limit
        );
        $stmt->execute([':org_id' => $organizationId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($items as &$item) {
            $decoded = json_decode((string)$item['submitter_data_json'], true);
            $item['data'] = is_array($decoded) ? $decoded : [];
            $snapshot = is_array($item['data']['__public_form_snapshot'] ?? null)
                ? $item['data']['__public_form_snapshot']
                : [];
            unset($item['data']['__public_form_snapshot']);
            if (is_string($snapshot['form_title'] ?? null) && $snapshot['form_title'] !== '') {
                $item['form_title'] = $snapshot['form_title'];
            }
            $schema = is_array($snapshot['fields_schema'] ?? null)
                ? $snapshot['fields_schema']
                : json_decode((string)$item['fields_schema_json'], true);
            $labels = [];
            foreach (is_array($schema) ? $schema : [] as $field) {
                if (is_array($field) && is_string($field['name'] ?? null) && is_string($field['label'] ?? null)) {
                    $labels[$field['name']] = $field['label'];
                }
            }
            $item['field_labels'] = $labels;
            $item['consent_evidence'] = is_array($snapshot['consent'] ?? null)
                ? $snapshot['consent']
                : [
                    'agreed' => (bool)$item['consent_agreed'],
                    'version' => (int)$item['consent_version'],
                    'text' => null,
                    'recorded_at' => null,
                ];
            if (isset($item['data']['_booking_start_utc']) && is_string($item['data']['_booking_start_utc'])) {
                $item['booking_starts_at'] = $item['data']['_booking_start_utc'];
                unset($item['data']['_booking_start_utc']);
            }
            unset($item['submitter_data_json'], $item['fields_schema_json'], $item['consent_agreed'], $item['consent_version']);
        }
        unset($item);
        return $items;
    }

    /**
     * Save/Create a public form configuration.
     */
    public function saveForm(
        int $organizationId,
        string $slug,
        string $title,
        string $formType,
        array $fieldsSchema,
        string $consentText,
        ?string $targetProject = null,
        ?string $targetAssignee = null,
        ?string $calendarUser = null,
        int $slotDuration = 30,
        int $bufferMinutes = 15,
        string $bookingTimezone = 'UTC',
        array $bookingSchedule = [],
        string $description = ''
    ): string {
        if ($organizationId < 1) {
            throw new RuntimeException('A valid workspace is required.');
        }
        $this->validateFormConfig($slug, $title, $formType, $fieldsSchema, $consentText, $targetProject, $targetAssignee, $slotDuration, $bufferMinutes, $calendarUser, $bookingTimezone, $bookingSchedule, $organizationId, $description);
        $now = date('Y-m-d H:i:s');
        $pubId = 'pfn_' . bin2hex(random_bytes(10));
        $fieldsJson = json_encode($fieldsSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $stmt = $this->db->prepare(
            "INSERT INTO crm_public_forms
             (public_id, organization_id, slug, title, description, form_type, fields_schema_json, consent_text, consent_version, target_project_public_id, target_assignee_user_public_id, calendar_user_public_id, slot_duration_minutes, buffer_minutes, booking_timezone, booking_schedule_json, is_published, created_at, updated_at)
             VALUES (:pub_id, :org_id, :slug, :title, :description, :type, :fields, :consent, 1, :proj, :assignee, :cal_usr, :slot_dur, :buf, :timezone, :schedule, 0, :now, :now)"
        );
        $stmt->execute([
            ':pub_id' => $pubId,
            ':org_id' => $organizationId,
            ':slug' => $slug,
            ':title' => $title,
            ':description' => trim($description),
            ':type' => $formType,
            ':fields' => $fieldsJson,
            ':consent' => $consentText,
            ':proj' => $targetProject,
            ':assignee' => $targetAssignee,
            ':cal_usr' => $calendarUser,
            ':slot_dur' => $slotDuration,
            ':buf' => $bufferMinutes,
            ':timezone' => $bookingTimezone,
            ':schedule' => json_encode($bookingSchedule, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ':now' => $now,
        ]);

        return $pubId;
    }

    public function updateForm(
        int $organizationId,
        string $publicId,
        string $slug,
        string $title,
        string $formType,
        array $fieldsSchema,
        string $consentText,
        ?string $targetProject = null,
        ?string $targetAssignee = null,
        ?string $calendarUser = null,
        int $slotDuration = 30,
        int $bufferMinutes = 15,
        string $bookingTimezone = 'UTC',
        array $bookingSchedule = [],
        string $description = ''
    ): bool {
        if ($organizationId < 1 || !preg_match('/^pfn_[a-f0-9]{20}$/D', $publicId)) {
            return false;
        }
        $this->validateFormConfig($slug, $title, $formType, $fieldsSchema, $consentText, $targetProject, $targetAssignee, $slotDuration, $bufferMinutes, $calendarUser, $bookingTimezone, $bookingSchedule, $organizationId, $description);
        $stmt = $this->db->prepare(
            'UPDATE crm_public_forms SET slug = :slug, title = :title, description = :description, form_type = :type, fields_schema_json = :fields,'
            . ' consent_version = consent_version + CASE WHEN consent_text <> :consent_compare THEN 1 ELSE 0 END, consent_text = :consent,'
            . ' target_project_public_id = :project, target_assignee_user_public_id = :assignee, calendar_user_public_id = :calendar_user,'
            . ' slot_duration_minutes = :slot_duration, buffer_minutes = :buffer, booking_timezone = :timezone, booking_schedule_json = :schedule, updated_at = :updated_at'
            . ' WHERE organization_id = :org_id AND public_id = :public_id'
        );
        $exists = $this->db->prepare('SELECT 1 FROM crm_public_forms WHERE organization_id = :org_id AND public_id = :public_id LIMIT 1');
        $exists->execute([':org_id' => $organizationId, ':public_id' => $publicId]);
        if ($exists->fetchColumn() === false) {
            return false;
        }
        $stmt->execute([
            ':slug' => $slug, ':title' => $title, ':description' => trim($description), ':type' => $formType,
            ':fields' => json_encode($fieldsSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ':consent_compare' => $consentText, ':consent' => $consentText,
            ':project' => $targetProject, ':assignee' => $targetAssignee, ':calendar_user' => $calendarUser,
            ':slot_duration' => $slotDuration, ':buffer' => $bufferMinutes, ':timezone' => $bookingTimezone,
            ':schedule' => json_encode($bookingSchedule, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ':updated_at' => date('Y-m-d H:i:s'),
            ':org_id' => $organizationId, ':public_id' => $publicId,
        ]);
        return true;
    }

    public function setFormPublished(int $organizationId, string $publicId, bool $published): bool
    {
        if ($organizationId < 1 || !preg_match('/^pfn_[a-f0-9]{20}$/D', $publicId)) {
            return false;
        }
        $exists = $this->db->prepare('SELECT 1 FROM crm_public_forms WHERE organization_id = :org_id AND public_id = :public_id LIMIT 1');
        $exists->execute([':org_id' => $organizationId, ':public_id' => $publicId]);
        if ($exists->fetchColumn() === false) {
            return false;
        }
        $stmt = $this->db->prepare(
            'UPDATE crm_public_forms SET is_published = :published, updated_at = :updated_at'
            . ' WHERE organization_id = :org_id AND public_id = :public_id'
        );
        $stmt->execute([
            ':published' => $published ? 1 : 0,
            ':updated_at' => date('Y-m-d H:i:s'),
            ':org_id' => $organizationId,
            ':public_id' => $publicId,
        ]);
        return true;
    }

    /** @param list<array<string, mixed>> $fieldsSchema */
    private function validateFormConfig(string $slug, string $title, string $formType, array $fieldsSchema, string $consentText, ?string $targetProject, ?string $targetAssignee, int $slotDuration, int $bufferMinutes, ?string $calendarUser, string $bookingTimezone, array $bookingSchedule, int $organizationId, string $description = ''): void
    {
        $titleLength = function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : preg_match_all('/./us', $title);
        $descriptionLength = function_exists('mb_strlen') ? mb_strlen($description, 'UTF-8') : preg_match_all('/./us', $description);
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{1,62}[a-z0-9])?$/D', $slug)
            || !is_int($titleLength) || $titleLength < 1 || $titleLength > 255
            || preg_match('/[\x00-\x1F]/', $title)
            || !in_array($formType, ['intake', 'booking'], true)
            || count($fieldsSchema) < 1 || count($fieldsSchema) > self::MAX_FIELDS
            || strlen($consentText) < 3 || strlen($consentText) > 2000
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $consentText)
            || !is_int($descriptionLength) || $descriptionLength > 2000
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $description)
            || $slotDuration < 5 || $slotDuration > 480 || $bufferMinutes < 0 || $bufferMinutes > 240) {
            throw new \InvalidArgumentException('Form configuration is invalid.');
        }
        if ($formType === 'booking') {
            if ($targetProject !== null || $targetAssignee !== null) {
                throw new \InvalidArgumentException('Intake routing is available only for intake forms.');
            }
            $this->assertBookingUser($calendarUser, $organizationId);
            $this->validateBookingSchedule($bookingTimezone, $bookingSchedule);
        } else {
            if ($targetProject !== null) $this->assertRoutingProject($targetProject, $organizationId);
            if ($targetAssignee !== null) $this->assertRoutingAssignee($targetAssignee, $organizationId);
        }
        $names = [];
        $allowedTypes = ['text', 'email', 'tel', 'number', 'date', 'url', 'textarea'];
        foreach ($fieldsSchema as $field) {
            if (!is_array($field)) {
                throw new \InvalidArgumentException('Each form field must be an object.');
            }
            $name = trim((string)($field['name'] ?? ''));
            $label = trim((string)($field['label'] ?? ''));
            $type = (string)($field['type'] ?? 'text');
            $labelLength = function_exists('mb_strlen') ? mb_strlen($label, 'UTF-8') : preg_match_all('/./us', $label);
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/D', $name)
                || in_array($name, ['consent_agreed', '_hp_website', '_t_token', '_idempotency_key'], true)
                || isset($names[$name]) || !is_int($labelLength) || $labelLength < 1 || $labelLength > 120
                || preg_match('/[\x00-\x1F]/', $label)
                || !in_array($type, $allowedTypes, true)
                || (array_key_exists('required', $field) && !is_bool($field['required']))
                || array_diff(array_keys($field), ['name', 'label', 'type', 'required']) !== []) {
                throw new \InvalidArgumentException('Form field configuration is invalid.');
            }
            $names[$name] = true;
        }
    }

    private function assertRoutingProject(string $publicId, int $organizationId): void
    {
        if (!preg_match('/^prj_[A-Za-z0-9_-]{1,60}$/D', $publicId)) {
            throw new \InvalidArgumentException('A valid workspace project is required.');
        }
        $stmt = $this->db->prepare('SELECT 1 FROM projects WHERE public_id = :public_id AND organization_id = :org_id AND archived_at IS NULL AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':public_id' => $publicId, ':org_id' => $organizationId]);
        if ($stmt->fetchColumn() === false) throw new \InvalidArgumentException('The target project must be active and belong to this workspace.');
    }

    private function assertRoutingAssignee(string $publicId, int $organizationId): int
    {
        if (!preg_match('/^usr_[A-Za-z0-9_-]{1,60}$/D', $publicId)) {
            throw new \InvalidArgumentException('A valid workspace assignee is required.');
        }
        $stmt = $this->db->prepare('SELECT u.id FROM users u INNER JOIN organization_memberships om ON om.user_id = u.id WHERE u.public_id = :public_id AND om.organization_id = :org_id AND u.is_active = 1 AND u.deleted_at IS NULL LIMIT 1');
        $stmt->execute([':public_id' => $publicId, ':org_id' => $organizationId]);
        $id = $stmt->fetchColumn();
        if (!is_numeric($id) || (int)$id < 1) throw new \InvalidArgumentException('The target assignee must be active and belong to this workspace.');
        return (int)$id;
    }

    private function assertBookingUser(?string $publicId, int $organizationId, bool $forUpdate = false): int
    {
        if (!is_string($publicId) || !preg_match('/^usr_[A-Za-z0-9_-]{1,60}$/D', $publicId)) {
            throw new \InvalidArgumentException('A valid workspace calendar user is required.');
        }
        $sql = 'SELECT u.id FROM users u INNER JOIN organization_memberships om ON om.user_id = u.id WHERE u.public_id = :public_id AND om.organization_id = :org_id AND u.is_active = 1 AND u.deleted_at IS NULL LIMIT 1';
        if ($forUpdate) $sql .= ' FOR UPDATE';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':public_id' => $publicId, ':org_id' => $organizationId]);
        $id = $stmt->fetchColumn();
        if (!is_numeric($id) || (int)$id < 1) {
            throw new \InvalidArgumentException('The calendar user must be active and belong to this workspace.');
        }
        return (int)$id;
    }

    private function validateBookingSchedule(string $timezone, array $schedule): void
    {
        if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true) || count($schedule) < 1 || count($schedule) > 7) {
            throw new \InvalidArgumentException('Booking timezone or weekly schedule is invalid.');
        }
        foreach ($schedule as $weekday => $intervals) {
            if (!is_string((string)$weekday) && !is_int($weekday)) throw new \InvalidArgumentException('Booking schedule weekday is invalid.');
            $weekday = (int)$weekday;
            if ($weekday < 1 || $weekday > 7 || !is_array($intervals) || !array_is_list($intervals) || count($intervals) > 8) {
                throw new \InvalidArgumentException('Booking schedule weekday is invalid.');
            }
            $previousEnd = '';
            foreach ($intervals as $interval) {
                if (!is_array($interval) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', (string)($interval['start'] ?? ''))
                    || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', (string)($interval['end'] ?? ''))
                    || (string)$interval['start'] >= (string)$interval['end']
                    || ($previousEnd !== '' && (string)$interval['start'] < $previousEnd)) {
                    throw new \InvalidArgumentException('Booking schedule intervals must be valid and non-overlapping.');
                }
                $previousEnd = (string)$interval['end'];
            }
        }
    }

    /** @return list<array{starts_at:string,ends_at:string}> */
    public function getBookingSlots(string $slug, string $fromDate, string $toDate, string $clientIp): array
    {
        $stmt = $this->db->prepare("SELECT id, public_id, organization_id, form_type, calendar_user_public_id, slot_duration_minutes, buffer_minutes, booking_timezone, booking_schedule_json FROM crm_public_forms WHERE slug = :slug AND is_published = 1 AND form_type = 'booking' LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        $form = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($form)) throw new PublicFormSubmissionException('FORM_NOT_FOUND', 'Form not found or unpublished.', 404);
        $from = $this->parseBookingDate($fromDate);
        $to = $this->parseBookingDate($toDate);
        if ($to < $from || $from < (new \DateTimeImmutable('today', new \DateTimeZone((string)$form['booking_timezone'])))->format('Y-m-d')
            || (new \DateTimeImmutable($to, new \DateTimeZone('UTC')))->diff(new \DateTimeImmutable($from, new \DateTimeZone('UTC')))->days > 30) {
            throw new PublicFormSubmissionException('BOOKING_RANGE_INVALID', 'Choose a date range within the next 31 days.', 422);
        }
        try { $userId = $this->assertBookingUser((string)$form['calendar_user_public_id'], (int)$form['organization_id']); }
        catch (\InvalidArgumentException) { throw new PublicFormSubmissionException('BOOKING_UNAVAILABLE', 'Appointment booking is temporarily unavailable.', 503); }
        $this->recordSlotQuery((int)$form['id'], $clientIp);
        $slots = [];
        $date = new \DateTimeImmutable($from, new \DateTimeZone('UTC'));
        $endDate = new \DateTimeImmutable($to, new \DateTimeZone('UTC'));
        while ($date <= $endDate) {
            foreach ($this->configuredSlotsForDate($form, $date->format('Y-m-d')) as $slot) {
                if ($slot['start_utc'] <= gmdate('Y-m-d H:i:s')) continue;
                if (!$this->hasCalendarOverlap((int)$form['organization_id'], $userId, $slot['start_utc'], (int)$form['slot_duration_minutes'], (int)$form['buffer_minutes'])) {
                    $slots[] = ['starts_at' => $slot['starts_at'], 'ends_at' => $slot['ends_at']];
                }
            }
            $date = $date->modify('+1 day');
        }
        return $slots;
    }

    private function recordSlotQuery(int $formId, string $clientIp): void
    {
        $ip = filter_var($clientIp, FILTER_VALIDATE_IP) !== false ? $clientIp : '0.0.0.0';
        $ipHash = hash('sha256', $ip);
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        try {
            if ($driver === 'sqlite') $this->db->exec('BEGIN IMMEDIATE'); else $this->db->beginTransaction();
            $lockSql = 'SELECT id FROM crm_public_forms WHERE id = :id AND is_published = 1';
            if ($driver === 'mysql') $lockSql .= ' FOR UPDATE';
            $lock = $this->db->prepare($lockSql); $lock->execute([':id' => $formId]);
            if ($lock->fetchColumn() === false) throw new PublicFormSubmissionException('FORM_NOT_FOUND', 'Form not found or unpublished.', 404);
            $delete = $this->db->prepare('DELETE FROM crm_public_form_slot_requests WHERE form_id = :form AND created_at < :cutoff');
            $delete->execute([':form' => $formId, ':cutoff' => gmdate('Y-m-d H:i:s', time() - 600)]);
            $count = $this->db->prepare('SELECT COUNT(*) FROM crm_public_form_slot_requests WHERE form_id = :form AND request_ip_hash = :ip AND created_at >= :cutoff');
            $count->execute([':form' => $formId, ':ip' => $ipHash, ':cutoff' => gmdate('Y-m-d H:i:s', time() - 600)]);
            if ((int)$count->fetchColumn() >= 30) throw new PublicFormSubmissionException('RATE_LIMITED', 'Too many availability requests. Please try again later.', 429);
            $insert = $this->db->prepare('INSERT INTO crm_public_form_slot_requests (form_id, request_ip_hash, created_at) VALUES (:form, :ip, :created)');
            $insert->execute([':form' => $formId, ':ip' => $ipHash, ':created' => gmdate('Y-m-d H:i:s')]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    private function beginSqliteBookingTransaction(): void
    {
        $deadline = microtime(true) + 5.0;
        do {
            try {
                $this->db->exec('BEGIN IMMEDIATE');
                return;
            } catch (\PDOException $e) {
                if (!str_contains(strtolower($e->getMessage()), 'locked') || microtime(true) >= $deadline) throw $e;
                usleep(25000);
            }
        } while (microtime(true) < $deadline);
        throw new RuntimeException('Booking database is busy.');
    }

    private function parseBookingDate(string $value): string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) throw new PublicFormSubmissionException('BOOKING_RANGE_INVALID', 'Choose valid dates.', 422);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d') !== $value) throw new PublicFormSubmissionException('BOOKING_RANGE_INVALID', 'Choose valid dates.', 422);
        return $value;
    }

    private function parseBookingSlot(array $form, string $value): string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $value)) {
            throw new PublicFormSubmissionException('BOOKING_SLOT_INVALID', 'Choose an available appointment time.', 422);
        }
        try { $slot = new \DateTimeImmutable($value); } catch (\Throwable) {
            throw new PublicFormSubmissionException('BOOKING_SLOT_INVALID', 'Choose an available appointment time.', 422);
        }
        return $slot->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function isConfiguredBookingSlot(array $form, string $startUtc): bool
    {
        $timezone = (string)($form['booking_timezone'] ?? '');
        try { $local = (new \DateTimeImmutable($startUtc, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone($timezone)); }
        catch (\Throwable) { return false; }
        foreach ($this->configuredSlotsForDate($form, $local->format('Y-m-d')) as $slot) {
            if (hash_equals($slot['start_utc'], $startUtc)) return true;
        }
        return false;
    }

    /** @return list<array{starts_at:string,ends_at:string,start_utc:string}> */
    private function configuredSlotsForDate(array $form, string $date): array
    {
        $timezoneName = (string)($form['booking_timezone'] ?? '');
        $schedule = json_decode((string)($form['booking_schedule_json'] ?? ''), true);
        if (!in_array($timezoneName, \DateTimeZone::listIdentifiers(), true) || !is_array($schedule)) return [];
        try { $timezone = new \DateTimeZone($timezoneName); } catch (\Throwable) { return []; }
        $localDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        if (!$localDate || $localDate->format('Y-m-d') !== $date) return [];
        $periods = $schedule[(string)$localDate->format('N')] ?? [];
        if (!is_array($periods)) return [];
        $duration = (int)($form['slot_duration_minutes'] ?? 0);
        $buffer = (int)($form['buffer_minutes'] ?? 0);
        if ($duration < 5 || $duration > 480 || $buffer < 0 || $buffer > 240) return [];
        $result = [];
        foreach ($periods as $period) {
            if (!is_array($period)) continue;
            $startTime = (string)($period['start'] ?? ''); $endTime = (string)($period['end'] ?? '');
            if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $startTime) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $endTime)) continue;
            $cursor = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $startTime, $timezone);
            $endLocal = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $endTime, $timezone);
            if (!$cursor || !$endLocal || $cursor->format('Y-m-d H:i') !== $date . ' ' . $startTime || $endLocal->format('Y-m-d H:i') !== $date . ' ' . $endTime) continue;
            $utcEndBoundary = $endLocal->setTimezone(new \DateTimeZone('UTC'));
            $step = $duration + $buffer;
            for ($i = 0; $i < 100 && $cursor->modify('+' . $duration . ' minutes')->setTimezone(new \DateTimeZone('UTC')) <= $utcEndBoundary; $i++) {
                $start = $cursor->setTimezone(new \DateTimeZone('UTC'));
                $end = $start->modify('+' . $duration . ' minutes');
                $result[] = [
                    'starts_at' => $cursor->format('Y-m-d\TH:i:sP'),
                    'ends_at' => $end->setTimezone($timezone)->format('Y-m-d\TH:i:sP'),
                    'start_utc' => $start->format('Y-m-d H:i:s'),
                ];
                $cursor = $cursor->modify('+' . $step . ' minutes');
            }
        }
        return $result;
    }

    private function hasCalendarOverlap(int $organizationId, int $userId, string $startUtc, int $duration, int $buffer): bool
    {
        $start = (new \DateTimeImmutable($startUtc, new \DateTimeZone('UTC')))->modify('-' . $buffer . ' minutes')->format('Y-m-d H:i:s');
        $end = (new \DateTimeImmutable($startUtc, new \DateTimeZone('UTC')))->modify('+' . ($duration + $buffer) . ' minutes')->format('Y-m-d H:i:s');
        $stmt = $this->db->prepare('SELECT 1 FROM calendar_events e WHERE e.organization_id = :org_id AND e.starts_at < :end_at AND e.ends_at > :start_at AND (e.owner_user_id = :user_id OR EXISTS (SELECT 1 FROM calendar_event_attendees a WHERE a.event_id = e.id AND a.user_id = :attendee_id)) LIMIT 1');
        $stmt->execute([':org_id' => $organizationId, ':end_at' => $end, ':start_at' => $start, ':user_id' => $userId, ':attendee_id' => $userId]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Get published form by slug for public rendering.
     *
     * @return array<string, mixed>|null
     */
    public function getPublishedFormBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT public_id, slug, title, description, form_type, fields_schema_json, consent_text, consent_version, slot_duration_minutes, buffer_minutes, booking_timezone, booking_schedule_json, is_published
             FROM crm_public_forms
             WHERE slug = :slug AND is_published = 1"
        );
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['fields_schema'] = json_decode((string)$row['fields_schema_json'], true) ?: [];
        $row['booking_schedule'] = json_decode((string)($row['booking_schedule_json'] ?? '[]'), true) ?: [];
        unset($row['fields_schema_json'], $row['booking_schedule_json'], $row['calendar_user_public_id']);

        return $row;
    }

    /**
     * Process submission from public visitor with anti-spam check, consent recording and routing.
     *
     * @param array<string, mixed> $data
     * @return array{ok: bool, submission_id: string, intake_public_id?: string, message: string, duplicate?: bool}
     */
    public function submit(string $slug, array $data, string $clientIp): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, public_id, organization_id, title, form_type, fields_schema_json, consent_text, consent_version, calendar_user_public_id, slot_duration_minutes, buffer_minutes, booking_timezone, booking_schedule_json
             FROM crm_public_forms WHERE slug = :slug AND is_published = 1"
        );
        $stmt->execute([':slug' => $slug]);
        $form = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$form) {
            throw new PublicFormSubmissionException('FORM_NOT_FOUND', 'Form not found or unpublished.', 404);
        }

        $this->assertPayloadSize($data);

        // Anti-spam 1: Honeypot check
        if (!empty($data['_hp_website'])) {
            return [
                'ok' => true,
                'submission_id' => 'spm_dropped',
                'message' => 'Thank you for your submission.',
            ];
        }

        // Anti-spam 2: Consent must be checked
        if (!in_array($data['consent_agreed'] ?? null, ['1', 1, true, 'on'], true)) {
            throw new PublicFormSubmissionException('CONSENT_REQUIRED', 'Consent to personal data processing is required.', 422);
        }

        $key = trim((string)($data['_idempotency_key'] ?? ''));
        $bookingSlotInput = $data['_booking_slot'] ?? null;
        unset($data['_hp_website'], $data['_t_token'], $data['_idempotency_key'], $data['consent_agreed'], $data['_booking_slot']);
        if ($key === '' || strlen($key) > 128 || preg_match('/^[A-Za-z0-9._:-]{16,128}$/D', $key) !== 1) {
            throw new PublicFormSubmissionException('IDEMPOTENCY_KEY_REQUIRED', 'A valid submission key is required. Reload the form and try again.', 400);
        }

        $keyHash = hash('sha256', $key);
        $ip = filter_var($clientIp, FILTER_VALIDATE_IP) !== false ? $clientIp : '0.0.0.0';
        $formId = (int)$form['id'];
        $isBooking = (string)($form['form_type'] ?? 'intake') === 'booking';
        if ($isBooking && !is_string($bookingSlotInput)) {
            throw new PublicFormSubmissionException('BOOKING_SLOT_REQUIRED', 'Choose an available appointment time.', 422);
        }
        $bookingStartsUtc = $isBooking ? $this->parseBookingSlot((array)$form, (string)$bookingSlotInput) : null;
        $duplicate = false;
        $submissionPublicId = '';
        $lockedForm = $form;

        try {
            $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($isBooking && $driver === 'sqlite') {
                $this->beginSqliteBookingTransaction();
            } else {
                $this->db->beginTransaction();
            }
            $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            $lockSql = 'SELECT id, public_id, organization_id, title, form_type, fields_schema_json, consent_text, consent_version, target_project_public_id, target_assignee_user_public_id, calendar_user_public_id, slot_duration_minutes, buffer_minutes, booking_timezone, booking_schedule_json FROM crm_public_forms WHERE id = :id AND is_published = 1';
            if ($driver === 'mysql') {
                $lockSql .= ' FOR UPDATE';
            }
            $lock = $this->db->prepare($lockSql);
            $lock->execute([':id' => $formId]);
            $lockedForm = $lock->fetch(PDO::FETCH_ASSOC);
            if (!is_array($lockedForm)) {
                throw new PublicFormSubmissionException('FORM_NOT_FOUND', 'Form not found or unpublished.', 404);
            }

            $existingStmt = $this->db->prepare(
                'SELECT public_id, organization_id, intake_public_id, intake_claimed_at, intake_claim_token,'
                . ' consent_agreed, consent_version, submitter_data_json, idempotency_payload_hash, event_public_id'
                . ' FROM crm_public_form_submissions
                 WHERE form_id = :form_id AND idempotency_key_hash = :key_hash LIMIT 1'
            );
            $existingStmt->execute([':form_id' => $formId, ':key_hash' => $keyHash]);
            $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
            $snapshotSchema = null;
            if (is_array($existing)) {
                $existingData = json_decode((string)($existing['submitter_data_json'] ?? ''), true);
                if (is_array($existingData) && is_array($existingData['__public_form_snapshot']['fields_schema'] ?? null)) {
                    $snapshotSchema = $existingData['__public_form_snapshot']['fields_schema'];
                }
            }
            $schemaForReplay = is_array($snapshotSchema)
                ? json_encode($snapshotSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : (string)$lockedForm['fields_schema_json'];
            $payload = $this->validateAndFilterFields((string)$schemaForReplay, $data);
            if ($isBooking) {
                $payload['_booking_start_utc'] = $bookingStartsUtc;
            }
            $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($payloadJson)) {
                throw new PublicFormSubmissionException('INVALID_SUBMISSION', 'Submission data is invalid.', 422);
            }
            $payloadHash = hash('sha256', $payloadJson);
            if (is_array($existing)) {
                if (!hash_equals((string)$existing['idempotency_payload_hash'], $payloadHash)) {
                    throw new PublicFormSubmissionException('IDEMPOTENCY_CONFLICT', 'This submission key was already used for different data.', 409);
                }
                $duplicate = true;
                $submissionPublicId = (string)$existing['public_id'];
                if ($isBooking && trim((string)($existing['event_public_id'] ?? '')) === '') {
                    throw new PublicFormSubmissionException('BOOKING_RESULT_INCONSISTENT', 'This booking result needs administrator review.', 503);
                }
            } else {
                $calendarUserId = null;
                if ($isBooking) {
                    try { $calendarUserId = $this->assertBookingUser((string)$lockedForm['calendar_user_public_id'], (int)$lockedForm['organization_id'], $driver === 'mysql'); }
                    catch (\InvalidArgumentException) { throw new PublicFormSubmissionException('BOOKING_UNAVAILABLE', 'Appointment booking is temporarily unavailable.', 503); }
                    // Serialize this availability read against every core event writer.
                    // The lock is acquired before overlap checking and held until the
                    // submission, calendar event, and outbox transaction commits.
                    (new \Api\Model\Calendar\CalendarEventRepository($this->db))->lockConflictUsers([(int)$calendarUserId]);
                    if (!$this->isConfiguredBookingSlot($lockedForm, $bookingStartsUtc)) {
                        throw new PublicFormSubmissionException('BOOKING_SLOT_UNAVAILABLE', 'That appointment time is no longer available. Choose another time.', 409);
                    }
                    if ($this->hasCalendarOverlap((int)$lockedForm['organization_id'], $calendarUserId, $bookingStartsUtc, (int)$lockedForm['slot_duration_minutes'], (int)$lockedForm['buffer_minutes'])) {
                        throw new PublicFormSubmissionException('BOOKING_SLOT_UNAVAILABLE', 'That appointment time is no longer available. Choose another time.', 409);
                    }
                }
                $windowStart = gmdate('Y-m-d H:i:s', time() - self::RATE_WINDOW_SECONDS);
                $rateStmt = $this->db->prepare(
                    'SELECT COUNT(*) FROM crm_public_form_submissions
                     WHERE form_id = :form_id AND submitter_ip = :ip AND created_at >= :window_start'
                );
                $rateStmt->execute([':form_id' => $formId, ':ip' => $ip, ':window_start' => $windowStart]);
                if ((int)$rateStmt->fetchColumn() >= self::RATE_MAX_SUBMISSIONS) {
                    throw new PublicFormSubmissionException('RATE_LIMITED', 'Too many submissions. Please try again later.', 429);
                }

                $now = gmdate('Y-m-d H:i:s');
                $submissionPublicId = 'sub_' . bin2hex(random_bytes(10));
                $fieldsSchema = json_decode((string)$lockedForm['fields_schema_json'], true);
                $submissionData = $payload;
                $submissionData['__public_form_snapshot'] = [
                    'form_public_id' => (string)$lockedForm['public_id'],
                    'form_title' => (string)$lockedForm['title'],
                    'fields_schema' => is_array($fieldsSchema) ? $fieldsSchema : [],
                    'target_project_public_id' => $lockedForm['target_project_public_id'] ?? null,
                    'target_assignee_user_public_id' => $lockedForm['target_assignee_user_public_id'] ?? null,
                    'consent' => [
                        'agreed' => true,
                        'version' => (int)$lockedForm['consent_version'],
                        'text' => (string)$lockedForm['consent_text'],
                        'recorded_at' => $now,
                    ],
                ];
                $submissionJson = json_encode($submissionData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (!is_string($submissionJson) || strlen($submissionJson) > self::MAX_REQUEST_BYTES + 4096) {
                    throw new PublicFormSubmissionException('SUBMISSION_TOO_LARGE', 'Submission data is too large to store safely.', 422);
                }
                $ins = $this->db->prepare(
                    'INSERT INTO crm_public_form_submissions
                     (public_id, organization_id, form_id, submitter_ip, submitter_data_json, consent_agreed, consent_version, booking_buffer_minutes, idempotency_key_hash, idempotency_payload_hash, created_at)
                     VALUES (:pub_id, :org_id, :fid, :ip, :payload, 1, :c_ver, :booking_buffer, :key_hash, :payload_hash, :now)'
                );
                $ins->execute([
                    ':pub_id' => $submissionPublicId,
                    ':org_id' => $lockedForm['organization_id'],
                    ':fid' => $formId,
                    ':ip' => $ip,
                    ':payload' => $submissionJson,
                    ':c_ver' => $lockedForm['consent_version'],
                    ':booking_buffer' => $isBooking ? (int)$lockedForm['buffer_minutes'] : 0,
                    ':key_hash' => $keyHash,
                    ':payload_hash' => $payloadHash,
                    ':now' => $now,
                ]);
                if ($isBooking) {
                    $startsAt = $bookingStartsUtc;
                    $endsAt = (new \DateTimeImmutable($startsAt, new \DateTimeZone('UTC')))->modify('+' . (int)$lockedForm['slot_duration_minutes'] . ' minutes')->format('Y-m-d H:i:s');
                    if ($this->calendarEventCreator === null) {
                        throw new PublicFormSubmissionException('BOOKING_UNAVAILABLE', 'Appointment booking is temporarily unavailable.', 503);
                    }
                    $calendarActor = $this->getBookingActor((int)$calendarUserId, (int)$lockedForm['organization_id']);
                    if ($calendarActor === null) {
                        throw new PublicFormSubmissionException('BOOKING_UNAVAILABLE', 'Appointment booking is temporarily unavailable.', 503);
                    }
                    $event = ($this->calendarEventCreator)([
                        'title' => trim((string)$lockedForm['title']),
                        'description' => 'Appointment booked through public form.',
                        'starts_at' => $startsAt, 'ends_at' => $endsAt,
                    ], $calendarActor, [
                        'source_type' => 'public_form_booking',
                        'source_owner_user_id' => (int)$calendarUserId,
                        'source_external_id' => $submissionPublicId,
                    ]);
                    if ($event === 'BOOKING_CONFLICT') {
                        throw new PublicFormSubmissionException('BOOKING_SLOT_UNAVAILABLE', 'That appointment time is no longer available. Choose another time.', 409);
                    }
                    if (!is_array($event) || !is_string($event['public_id'] ?? null)) {
                        throw new PublicFormSubmissionException('BOOKING_UNAVAILABLE', 'Appointment booking is temporarily unavailable.', 503);
                    }
                    $eventPublicId = (string)$event['public_id'];
                    $linkEvent = $this->db->prepare('UPDATE crm_public_form_submissions SET event_public_id = :event_id WHERE form_id = :form_id AND public_id = :submission_id');
                    $linkEvent->execute([':event_id' => $eventPublicId, ':form_id' => $formId, ':submission_id' => $submissionPublicId]);
                }
            }
            $this->db->commit();
            if ($isBooking && !$duplicate && isset($event) && is_array($event) && $this->calendarEventAfterCommit !== null) {
                try { ($this->calendarEventAfterCommit)($event, $calendarActor); }
                catch (\Throwable) { /* The booking is committed; a notification failure must not report it as failed. */ }
            }
        } catch (PublicFormSubmissionException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($isBooking && (str_contains(strtolower($e->getMessage()), 'locked') || str_contains(strtolower($e->getMessage()), 'deadlock') || str_contains(strtolower($e->getMessage()), 'lock wait timeout') || str_contains(strtolower($e->getMessage()), 'database is busy'))) {
                throw new PublicFormSubmissionException('BOOKING_BUSY', 'Another booking is being confirmed. Please retry with the same submission key.', 503);
            }
            throw new RuntimeException('Submission could not be saved safely.', 0, $e);
        }

        if ($isBooking) {
            $eventIdQuery = $this->db->prepare('SELECT event_public_id FROM crm_public_form_submissions WHERE form_id = :form_id AND public_id = :submission_id AND organization_id = :org_id LIMIT 1');
            $eventIdQuery->execute([':form_id' => $formId, ':submission_id' => $submissionPublicId, ':org_id' => (int)$lockedForm['organization_id']]);
            $eventId = trim((string)($eventIdQuery->fetchColumn() ?: ''));
            return ['ok' => true, 'submission_id' => $submissionPublicId, 'event_public_id' => $eventId, 'starts_at' => $bookingStartsUtc, 'message' => 'Appointment booked successfully.', 'duplicate' => $duplicate];
        }
        $intakePublicId = $this->ensureIntakeLinked($formId, $submissionPublicId, $lockedForm);
        return [
            'ok' => true,
            'submission_id' => $submissionPublicId,
            'intake_public_id' => $intakePublicId,
            'message' => 'Submission received successfully.',
            'duplicate' => $duplicate,
        ];
    }

    /** @return array<string,mixed>|null */
    private function getBookingActor(int $userId, int $organizationId): ?array
    {
        $stmt = $this->db->prepare('SELECT u.id, u.public_id, u.full_name FROM users u INNER JOIN organization_memberships om ON om.user_id = u.id WHERE u.id = :user_id AND om.organization_id = :org_id AND u.is_active = 1 AND u.deleted_at IS NULL LIMIT 1');
        $stmt->execute([':user_id' => $userId, ':org_id' => $organizationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) return null;
        return ['id' => (int)$row['id'], 'public_id' => (string)$row['public_id'], 'full_name' => (string)$row['full_name'], 'organization_id' => $organizationId, 'is_root' => false];
    }

    /**
     * Claim and finish the Intake-link step separately from response storage.
     * Core Intake dispatches module/webhook events synchronously after its
     * mutation, so it must not run inside the response transaction. A database
     * claim serializes replays; the stable external_id lookup repairs an
     * interrupted attempt after the claim expires without creating a duplicate.
     *
     * @param array<string,mixed> $formFallback
     */
    private function ensureIntakeLinked(int $formId, string $submissionPublicId, array $formFallback): string
    {
        if ($this->intakeCreator === null) {
            throw new RuntimeException('Core Intake service is unavailable.');
        }
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $claimToken = bin2hex(random_bytes(16));
        $claimTime = gmdate('Y-m-d H:i:s');
        try {
            $this->db->beginTransaction();
            $claimSql = 'SELECT public_id, organization_id, intake_public_id, intake_claimed_at, intake_claim_token,'
                . ' consent_agreed, consent_version, submitter_data_json FROM crm_public_form_submissions'
                . ' WHERE form_id = :form_id AND public_id = :submission_public_id LIMIT 1';
            if ($driver === 'mysql') {
                $claimSql .= ' FOR UPDATE';
            }
            $claimStmt = $this->db->prepare($claimSql);
            $claimStmt->execute([':form_id' => $formId, ':submission_public_id' => $submissionPublicId]);
            $row = $claimStmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                throw new RuntimeException('Public form response could not be found for Intake processing.');
            }
            $linkedId = trim((string)($row['intake_public_id'] ?? ''));
            if ($linkedId !== '') {
                $this->db->commit();
                return $linkedId;
            }
            $claimedAt = trim((string)($row['intake_claimed_at'] ?? ''));
            $claimEpoch = $claimedAt !== '' ? strtotime($claimedAt . ' UTC') : false;
            if ($claimEpoch !== false && $claimEpoch >= time() - 600) {
                $this->db->commit();
                throw new PublicFormSubmissionException(
                    'INTAKE_CREATION_IN_PROGRESS',
                    'Your response is saved and is being added to the team inbox. Retry shortly if it is not confirmed.',
                    503
                );
            }
            $claimUpdate = $this->db->prepare(
                'UPDATE crm_public_form_submissions SET intake_claimed_at = :claimed_at, intake_claim_token = :claim_token'
                . ' WHERE form_id = :form_id AND public_id = :submission_public_id AND intake_public_id IS NULL'
            );
            $claimUpdate->execute([
                ':claimed_at' => $claimTime,
                ':claim_token' => $claimToken,
                ':form_id' => $formId,
                ':submission_public_id' => $submissionPublicId,
            ]);
            if ($claimUpdate->rowCount() !== 1) {
                throw new RuntimeException('Public form response could not be claimed for Intake processing.');
            }
            $this->db->commit();
        } catch (PublicFormSubmissionException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        $organizationId = (int)($row['organization_id'] ?? 0);
        if ($organizationId < 1) {
            $this->releaseIntakeClaim($formId, $submissionPublicId, $claimToken);
            throw new RuntimeException('A workspace is required to create an intake record.');
        }
        $existingIntake = $this->db->prepare(
            'SELECT public_id FROM intake_items'
            . ' WHERE organization_id = :organization_id AND external_source = :external_source AND external_id = :external_id'
            . ' AND deleted_at IS NULL ORDER BY id ASC LIMIT 1'
        );
        $existingIntake->execute([
            ':organization_id' => $organizationId,
            ':external_source' => 'public_form',
            ':external_id' => $submissionPublicId,
        ]);
        $intakePublicId = trim((string)($existingIntake->fetchColumn() ?: ''));
        if ($intakePublicId === '') {
            try {
                $intakePublicId = $this->createIntakeForSubmission($formFallback, $row, $submissionPublicId);
            } catch (\Throwable $e) {
                $this->releaseIntakeClaim($formId, $submissionPublicId, $claimToken);
                throw new PublicFormSubmissionException(
                    'INTAKE_CREATION_FAILED',
                    'Your response was saved, but the team inbox could not be updated yet. Retry with the same submission key shortly.',
                    503
                );
            }
        }

        try {
            $this->db->beginTransaction();
            $link = $this->db->prepare(
                'UPDATE crm_public_form_submissions SET intake_public_id = :intake_public_id,'
                . ' intake_claimed_at = NULL, intake_claim_token = NULL'
                . ' WHERE form_id = :form_id AND public_id = :submission_public_id'
                . ' AND intake_public_id IS NULL AND intake_claim_token = :claim_token'
            );
            $link->execute([
                ':intake_public_id' => $intakePublicId,
                ':form_id' => $formId,
                ':submission_public_id' => $submissionPublicId,
                ':claim_token' => $claimToken,
            ]);
            if ($link->rowCount() !== 1) {
                $read = $this->db->prepare(
                    'SELECT intake_public_id FROM crm_public_form_submissions WHERE form_id = :form_id AND public_id = :submission_public_id LIMIT 1'
                );
                $read->execute([':form_id' => $formId, ':submission_public_id' => $submissionPublicId]);
                $linkedId = trim((string)($read->fetchColumn() ?: ''));
                if ($linkedId === '' || !hash_equals($linkedId, $intakePublicId)) {
                    throw new RuntimeException('Public form response could not be linked to its Intake item.');
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            // Leave the claim for stale recovery when the result is uncertain.
            // A retry first checks core Intake using the stable external key.
            throw new RuntimeException('Public form response could not be linked safely.', 0, $e);
        }
        return $intakePublicId;
    }

    private function releaseIntakeClaim(int $formId, string $submissionPublicId, string $claimToken): void
    {
        $stmt = $this->db->prepare(
            'UPDATE crm_public_form_submissions SET intake_claimed_at = NULL, intake_claim_token = NULL'
            . ' WHERE form_id = :form_id AND public_id = :submission_public_id'
            . ' AND intake_public_id IS NULL AND intake_claim_token = :claim_token'
        );
        $stmt->execute([
            ':form_id' => $formId,
            ':submission_public_id' => $submissionPublicId,
            ':claim_token' => $claimToken,
        ]);
    }

    /**
     * Build the core item from the stored response snapshot so later form edits
     * cannot change historical labels or consent evidence.
     *
     * @param array<string,mixed> $formFallback
     * @param array<string,mixed> $submission
     */
    private function createIntakeForSubmission(array $formFallback, array $submission, string $submissionPublicId): string
    {
        if ($this->intakeCreator === null) {
            throw new RuntimeException('Core Intake service is unavailable.');
        }
        $storedData = json_decode((string)($submission['submitter_data_json'] ?? ''), true);
        $storedData = is_array($storedData) ? $storedData : [];
        $snapshot = is_array($storedData['__public_form_snapshot'] ?? null) ? $storedData['__public_form_snapshot'] : [];
        unset($storedData['__public_form_snapshot']);
        $formPublicId = trim((string)($snapshot['form_public_id'] ?? $formFallback['public_id'] ?? ''));
        $formTitle = trim((string)($snapshot['form_title'] ?? $formFallback['title'] ?? ''));
        $schema = is_array($snapshot['fields_schema'] ?? null)
            ? $snapshot['fields_schema']
            : (json_decode((string)($formFallback['fields_schema_json'] ?? ''), true) ?: []);
        $consent = is_array($snapshot['consent'] ?? null) ? $snapshot['consent'] : [
            'agreed' => (bool)($submission['consent_agreed'] ?? false),
            'version' => (int)($submission['consent_version'] ?? 1),
            'text' => ((int)($submission['consent_version'] ?? 1) === (int)($formFallback['consent_version'] ?? 1))
                ? (string)($formFallback['consent_text'] ?? '')
                : null,
            'recorded_at' => null,
        ];
        $organizationId = (int)($submission['organization_id'] ?? $formFallback['organization_id'] ?? 0);
        if ($organizationId < 1 || $formPublicId === '') {
            throw new RuntimeException('A valid workspace and public form are required for Intake creation.');
        }
        $labels = [];
        foreach (is_array($schema) ? $schema : [] as $field) {
            if (is_array($field) && is_string($field['name'] ?? null) && is_string($field['label'] ?? null)) {
                $labels[$field['name']] = $field['label'];
            }
        }
        $titleValue = '';
        foreach (['subject', 'title', 'name', 'full_name', 'company', 'company_name'] as $candidate) {
            if (isset($storedData[$candidate]) && is_scalar($storedData[$candidate]) && trim((string)$storedData[$candidate]) !== '') {
                $titleValue = trim((string)$storedData[$candidate]);
                break;
            }
        }
        $title = $formTitle;
        if ($titleValue !== '') {
            $title .= ' — ' . $titleValue;
        }
        if ($title === '') {
            $title = 'Public form submission';
        }
        $title = function_exists('mb_substr') ? mb_substr($title, 0, 255, 'UTF-8') : substr($title, 0, 255);
        $descriptionLines = [];
        foreach ($storedData as $name => $value) {
            if (!is_string($name) || !is_scalar($value)) {
                continue;
            }
            $descriptionLines[] = trim((string)($labels[$name] ?? $name)) . ': ' . (string)$value;
        }
        $description = implode("\n", $descriptionLines);
        $consentVersion = (int)($consent['version'] ?? $submission['consent_version'] ?? 1);
        $extra = [
            'public_form' => [
                'form_public_id' => $formPublicId,
                'submission_public_id' => $submissionPublicId,
                'consent_agreed' => (bool)($consent['agreed'] ?? $submission['consent_agreed'] ?? false),
                'consent_version' => $consentVersion,
                'consent_text' => is_string($consent['text'] ?? null) ? $consent['text'] : null,
                'consented_at' => $consent['recorded_at'] ?? null,
            ],
        ];
        $targetProject = $snapshot['target_project_public_id'] ?? $formFallback['target_project_public_id'] ?? null;
        $targetAssignee = $snapshot['target_assignee_user_public_id'] ?? $formFallback['target_assignee_user_public_id'] ?? null;
        $intakeInput = [
            'title' => $title,
            'description' => $description,
            'source_type' => 'api',
            'source_ref' => 'public_form:' . $formPublicId,
            'external_source' => 'public_form',
            'external_id' => $submissionPublicId,
            'extra' => $extra,
        ];
        if (is_string($targetProject) && $targetProject !== '') {
            $this->assertRoutingProject($targetProject, $organizationId);
            $intakeInput['project_public_id'] = $targetProject;
        }
        if (is_string($targetAssignee) && $targetAssignee !== '') {
            $intakeInput['assignee_user_id'] = $this->assertRoutingAssignee($targetAssignee, $organizationId);
        }
        $result = ($this->intakeCreator)($intakeInput, [
            'id' => 0,
            'organization_id' => $organizationId,
            'full_name' => 'Public form',
            'login' => 'public-form',
        ]);
        if (!is_array($result) || trim((string)($result['public_id'] ?? '')) === '') {
            throw new RuntimeException('Core Intake service did not create an intake record.');
        }
        return (string)$result['public_id'];
    }

    /** @param array<string, mixed> $data */
    private function assertPayloadSize(array $data): void
    {
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || strlen($encoded) > self::MAX_REQUEST_BYTES) {
            throw new PublicFormSubmissionException('PAYLOAD_TOO_LARGE', 'Submission exceeds the 32 KB limit.', 413);
        }
    }

    /** @param array<string, mixed> $data
     *  @return array<string, string>
     */
    private function validateAndFilterFields(string $schemaJson, array $data): array
    {
        $schema = json_decode($schemaJson, true);
        if (!is_array($schema) || count($schema) > self::MAX_FIELDS) {
            throw new PublicFormSubmissionException('FORM_SCHEMA_INVALID', 'This form is not configured safely.', 503);
        }

        $allowed = [];
        foreach ($schema as $field) {
            if (!is_array($field)) {
                throw new PublicFormSubmissionException('FORM_SCHEMA_INVALID', 'This form is not configured safely.', 503);
            }
            $name = trim((string)($field['name'] ?? ''));
            if ($name === '' || strlen($name) > 64 || preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/D', $name) !== 1
                || in_array($name, ['consent_agreed', '_hp_website', '_t_token', '_idempotency_key'], true)
                || isset($allowed[$name])) {
                throw new PublicFormSubmissionException('FORM_SCHEMA_INVALID', 'This form is not configured safely.', 503);
            }
            $allowed[$name] = !empty($field['required']);
        }

        if (count($data) > self::MAX_FIELDS) {
            throw new PublicFormSubmissionException('TOO_MANY_FIELDS', 'Too many fields were submitted.', 422);
        }
        foreach ($data as $name => $_value) {
            if (!is_string($name) || !array_key_exists($name, $allowed)) {
                throw new PublicFormSubmissionException('UNEXPECTED_FIELD', 'The submission contains an unknown field.', 422);
            }
        }

        $clean = [];
        $totalBytes = 0;
        foreach ($allowed as $name => $required) {
            if (!array_key_exists($name, $data)) {
                if ($required) {
                    throw new PublicFormSubmissionException('REQUIRED_FIELD_MISSING', 'A required field is missing.', 422);
                }
                continue;
            }
            $value = $data[$name];
            if (!is_scalar($value)) {
                throw new PublicFormSubmissionException('INVALID_FIELD_VALUE', 'Field values must be plain text.', 422);
            }
            $value = trim((string)$value);
            $bytes = strlen($value);
            if ($bytes > self::MAX_FIELD_VALUE_BYTES) {
                throw new PublicFormSubmissionException('FIELD_TOO_LONG', 'A field exceeds the 4 KB limit.', 422);
            }
            $totalBytes += $bytes;
            if ($totalBytes > self::MAX_TOTAL_VALUE_BYTES) {
                throw new PublicFormSubmissionException('SUBMISSION_TOO_LARGE', 'Combined field values exceed the 16 KB limit.', 422);
            }
            if ($required && $value === '') {
                throw new PublicFormSubmissionException('REQUIRED_FIELD_MISSING', 'A required field is empty.', 422);
            }
            $clean[$name] = $value;
        }

        return $clean;
    }
}
