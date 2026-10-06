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
            "SELECT * FROM crm_public_forms WHERE slug = :slug AND is_published = 1"
        );
        $stmt->execute([':slug' => $slug]);
        $form = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$form) {
            throw new RuntimeException("Form not found or unpublished.");
        }

        // Anti-spam 1: Honeypot check
        if (!empty($data['_hp_website'])) {
            return [
                'ok' => true,
                'submission_id' => 'spm_dropped',
                'message' => 'Thank you for your submission.',
            ];
        }

        // Anti-spam 2: Consent must be checked
        if (empty($data['consent_agreed'])) {
            throw new RuntimeException("Consent to personal data processing is required.");
        }

        $now = date('Y-m-d H:i:s');
        $subPubId = 'sub_' . bin2hex(random_bytes(10));

        // Clean internal helper fields from payload
        unset($data['_hp_website'], $data['_t_token'], $data['consent_agreed']);
        $payloadJson = json_encode($data, JSON_UNESCAPED_UNICODE);

        $ins = $this->db->prepare(
            "INSERT INTO crm_public_form_submissions
             (public_id, organization_id, form_id, submitter_ip, submitter_data_json, consent_agreed, consent_version, created_at)
             VALUES (:pub_id, :org_id, :fid, :ip, :payload, 1, :c_ver, :now)"
        );
        $ins->execute([
            ':pub_id' => $subPubId,
            ':org_id' => $form['organization_id'],
            ':fid' => $form['id'],
            ':ip' => $clientIp,
            ':payload' => $payloadJson,
            ':c_ver' => $form['consent_version'],
            ':now' => $now,
        ]);

        return [
            'ok' => true,
            'submission_id' => $subPubId,
            'message' => 'Submission received successfully.',
        ];
    }
}
