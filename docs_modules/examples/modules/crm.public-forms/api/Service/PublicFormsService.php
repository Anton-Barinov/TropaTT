<?php
declare(strict_types=1);

namespace Module\Crm\PublicForms\Service;

use PDO;
use RuntimeException;

/**
 * Service managing public lead intake forms and appointment booking slots.
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

    public function __construct(PDO $db)
    {
        $this->db = $db;
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
        int $bufferMinutes = 15
    ): string {
        $now = date('Y-m-d H:i:s');
        $pubId = 'pfn_' . bin2hex(random_bytes(10));
        $fieldsJson = json_encode($fieldsSchema, JSON_UNESCAPED_UNICODE);

        $stmt = $this->db->prepare(
            "INSERT INTO crm_public_forms
             (public_id, organization_id, slug, title, form_type, fields_schema_json, consent_text, consent_version, target_project_public_id, target_assignee_user_public_id, calendar_user_public_id, slot_duration_minutes, buffer_minutes, is_published, created_at, updated_at)
             VALUES (:pub_id, :org_id, :slug, :title, :type, :fields, :consent, 1, :proj, :assignee, :cal_usr, :slot_dur, :buf, 1, :now, :now)"
        );
        $stmt->execute([
            ':pub_id' => $pubId,
            ':org_id' => $organizationId,
            ':slug' => $slug,
            ':title' => $title,
            ':type' => $formType,
            ':fields' => $fieldsJson,
            ':consent' => $consentText,
            ':proj' => $targetProject,
            ':assignee' => $targetAssignee,
            ':cal_usr' => $calendarUser,
            ':slot_dur' => $slotDuration,
            ':buf' => $bufferMinutes,
            ':now' => $now,
        ]);

        return $pubId;
    }

    /**
     * Get published form by slug for public rendering.
     *
     * @return array<string, mixed>|null
     */
    public function getPublishedFormBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT public_id, slug, title, description, form_type, fields_schema_json, consent_text, consent_version, slot_duration_minutes, buffer_minutes, is_published
             FROM crm_public_forms
             WHERE slug = :slug AND is_published = 1"
        );
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['fields_schema'] = json_decode((string)$row['fields_schema_json'], true) ?: [];
        unset($row['fields_schema_json']);

        return $row;
    }

    /**
     * Process submission from public visitor with anti-spam check, consent recording and routing.
     *
     * @param array<string, mixed> $data
     * @return array{ok: bool, submission_id: string, message: string}
     */
    public function submit(string $slug, array $data, string $clientIp): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, organization_id, fields_schema_json, consent_version
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
        unset($data['_hp_website'], $data['_t_token'], $data['_idempotency_key'], $data['consent_agreed']);
        if ($key === '' || strlen($key) > 128 || preg_match('/^[A-Za-z0-9._:-]{16,128}$/D', $key) !== 1) {
            throw new PublicFormSubmissionException('IDEMPOTENCY_KEY_REQUIRED', 'A valid submission key is required. Reload the form and try again.', 400);
        }

        $keyHash = hash('sha256', $key);
        $ip = filter_var($clientIp, FILTER_VALIDATE_IP) !== false ? $clientIp : '0.0.0.0';
        $formId = (int)$form['id'];

        try {
            $this->db->beginTransaction();
            $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            $lockSql = 'SELECT id, organization_id, fields_schema_json, consent_version FROM crm_public_forms WHERE id = :id AND is_published = 1';
            if ($driver === 'mysql') {
                $lockSql .= ' FOR UPDATE';
            }
            $lock = $this->db->prepare($lockSql);
            $lock->execute([':id' => $formId]);
            $lockedForm = $lock->fetch(PDO::FETCH_ASSOC);
            if (!is_array($lockedForm)) {
                throw new PublicFormSubmissionException('FORM_NOT_FOUND', 'Form not found or unpublished.', 404);
            }

            $payload = $this->validateAndFilterFields((string)$lockedForm['fields_schema_json'], $data);
            $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($payloadJson)) {
                throw new PublicFormSubmissionException('INVALID_SUBMISSION', 'Submission data is invalid.', 422);
            }
            $payloadHash = hash('sha256', $payloadJson);

            $existingStmt = $this->db->prepare(
                'SELECT public_id, idempotency_payload_hash FROM crm_public_form_submissions
                 WHERE form_id = :form_id AND idempotency_key_hash = :key_hash LIMIT 1'
            );
            $existingStmt->execute([':form_id' => $formId, ':key_hash' => $keyHash]);
            $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($existing)) {
                if (!hash_equals((string)$existing['idempotency_payload_hash'], $payloadHash)) {
                    throw new PublicFormSubmissionException('IDEMPOTENCY_CONFLICT', 'This submission key was already used for different data.', 409);
                }
                $this->db->commit();
                return [
                    'ok' => true,
                    'submission_id' => (string)$existing['public_id'],
                    'message' => 'Submission received successfully.',
                    'duplicate' => true,
                ];
            }

            $windowStart = date('Y-m-d H:i:s', time() - self::RATE_WINDOW_SECONDS);
            $rateStmt = $this->db->prepare(
                'SELECT COUNT(*) FROM crm_public_form_submissions
                 WHERE form_id = :form_id AND submitter_ip = :ip AND created_at >= :window_start'
            );
            $rateStmt->execute([':form_id' => $formId, ':ip' => $ip, ':window_start' => $windowStart]);
            if ((int)$rateStmt->fetchColumn() >= self::RATE_MAX_SUBMISSIONS) {
                throw new PublicFormSubmissionException('RATE_LIMITED', 'Too many submissions. Please try again later.', 429);
            }

            $now = date('Y-m-d H:i:s');
            $subPubId = 'sub_' . bin2hex(random_bytes(10));
            $ins = $this->db->prepare(
                'INSERT INTO crm_public_form_submissions
                 (public_id, organization_id, form_id, submitter_ip, submitter_data_json, consent_agreed, consent_version, idempotency_key_hash, idempotency_payload_hash, created_at)
                 VALUES (:pub_id, :org_id, :fid, :ip, :payload, 1, :c_ver, :key_hash, :payload_hash, :now)'
            );
            $ins->execute([
                ':pub_id' => $subPubId,
                ':org_id' => $lockedForm['organization_id'],
                ':fid' => $formId,
                ':ip' => $ip,
                ':payload' => $payloadJson,
                ':c_ver' => $lockedForm['consent_version'],
                ':key_hash' => $keyHash,
                ':payload_hash' => $payloadHash,
                ':now' => $now,
            ]);
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
            throw new RuntimeException('Submission could not be saved safely.', 0, $e);
        }

        return [
            'ok' => true,
            'submission_id' => $subPubId,
            'message' => 'Submission received successfully.',
            'duplicate' => false,
        ];
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
