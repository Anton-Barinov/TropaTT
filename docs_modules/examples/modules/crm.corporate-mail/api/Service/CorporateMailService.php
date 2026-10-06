<?php
declare(strict_types=1);

namespace Module\Crm\CorporateMail\Service;

use Api\System\Library\Security\KeyGuard;
use PDO;
use RuntimeException;

/**
 * Service managing corporate mailboxes, thread linking, and batch email sync.
 */
final class CorporateMailService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function saveMailbox(
        int $organizationId,
        string $email,
        string $displayName,
        string $adapter,
        array $authConfig
    ): string {
        $now = date('Y-m-d H:i:s');
        $rawConfig = json_encode($authConfig, JSON_UNESCAPED_UNICODE);
        $encrypted = class_exists(KeyGuard::class) ? KeyGuard::encrypt($rawConfig) : base64_encode($rawConfig);

        $pubId = 'mbx_' . bin2hex(random_bytes(10));
        $ins = $this->db->prepare(
            "INSERT INTO crm_mail_mailboxes
             (public_id, organization_id, email_address, display_name, protocol_adapter, auth_config_encrypted, is_active, created_at, updated_at)
             VALUES (:pub_id, :org_id, :email, :name, :adapter, :auth, 1, :now, :now)"
        );
        $ins->execute([
            ':pub_id' => $pubId,
            ':org_id' => $organizationId,
            ':email' => $email,
            ':name' => $displayName,
            ':adapter' => $adapter,
            ':auth' => $encrypted,
            ':now' => $now,
        ]);

        return $pubId;
    }

    /**
     * Ingest an email message with Message-ID header deduplication and thread continuity.
     *
     * @param array<string, mixed> $msg
     * @return array{ok: bool, duplicate: bool, message_public_id: ?string}
     */
    public function ingestMessage(int $organizationId, string $mailboxPublicId, array $msg): array
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM crm_mail_mailboxes WHERE public_id = :pub_id AND organization_id = :org_id AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([':pub_id' => $mailboxPublicId, ':org_id' => $organizationId]);
        $mbxId = $stmt->fetchColumn();

        if (!$mbxId) {
            throw new RuntimeException("Mailbox not found or inactive.");
        }

        $headerId = trim((string)($msg['message_id_header'] ?? ''));
        if ($headerId === '') {
            throw new RuntimeException("Missing Message-ID header.");
        }

        // Deduplication
        $dup = $this->db->prepare(
            "SELECT id FROM crm_mail_messages WHERE organization_id = :org_id AND mailbox_id = :mbx_id AND message_id_header = :hdr LIMIT 1"
        );
        $dup->execute([':org_id' => $organizationId, ':mbx_id' => $mbxId, ':hdr' => $headerId]);
        if ($dup->fetchColumn()) {
            return ['ok' => true, 'duplicate' => true, 'message_public_id' => null];
        }

        // Thread resolution
        $threadId = $headerId;
        if (!empty($msg['in_reply_to'])) {
            $inReplyTo = (string)$msg['in_reply_to'];
            $parentStmt = $this->db->prepare(
                "SELECT thread_id FROM crm_mail_messages WHERE organization_id = :org_id AND mailbox_id = :mbx_id AND message_id_header = :parent_hdr LIMIT 1"
            );
            $parentStmt->execute([':org_id' => $organizationId, ':mbx_id' => $mbxId, ':parent_hdr' => $inReplyTo]);
            $parentThread = $parentStmt->fetchColumn();
            $threadId = $parentThread ?: $inReplyTo;
        }

        $now = date('Y-m-d H:i:s');
        $pubId = 'msg_' . bin2hex(random_bytes(10));
        $intakePubId = 'itk_' . bin2hex(random_bytes(10)); // Create Intake linkage

        $ins = $this->db->prepare(
            "INSERT INTO crm_mail_messages
             (public_id, organization_id, mailbox_id, message_id_header, thread_id, in_reply_to, sender_email, sender_name, recipient_email, subject, body_text, intake_public_id, direction, sent_at, created_at)
             VALUES (:pub_id, :org_id, :mbx_id, :hdr, :thread, :in_reply, :sender_email, :sender_name, :recipient_email, :subj, :body, :intake, :dir, :sent, :now)"
        );
        $ins->execute([
            ':pub_id' => $pubId,
            ':org_id' => $organizationId,
            ':mbx_id' => $mbxId,
            ':hdr' => $headerId,
            ':thread' => $threadId,
            ':in_reply' => $msg['in_reply_to'] ?? null,
            ':sender_email' => (string)($msg['sender_email'] ?? ''),
            ':sender_name' => $msg['sender_name'] ?? null,
            ':recipient_email' => (string)($msg['recipient_email'] ?? ''),
            ':subj' => (string)($msg['subject'] ?? '(No subject)'),
            ':body' => (string)($msg['body_text'] ?? ''),
            ':intake' => $intakePubId,
            ':dir' => (string)($msg['direction'] ?? 'inbound'),
            ':sent' => (string)($msg['sent_at'] ?? $now),
            ':now' => $now,
        ]);

        return [
            'ok' => true,
            'duplicate' => false,
            'message_public_id' => $pubId,
        ];
    }

    /**
     * List mailboxes for organization.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listMailboxes(int $organizationId): array
    {
        $stmt = $this->db->prepare(
            "SELECT public_id, email_address, display_name, protocol_adapter, sync_status, last_synced_at, is_active
             FROM crm_mail_mailboxes
             WHERE organization_id = :org_id
             ORDER BY id DESC"
        );
        $stmt->execute([':org_id' => $organizationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
