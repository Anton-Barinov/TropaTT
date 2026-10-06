<?php
declare(strict_types=1);

namespace Module\Crm\WhatsAppBusiness\Service;

use Api\System\Library\Security\KeyGuard;
use PDO;
use RuntimeException;

/**
 * Service managing WhatsApp Business Cloud API webhooks, conversations inbox, and outbound message dispatch.
 */
final class WhatsAppBusinessService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function saveConfig(
        int $organizationId,
        string $phoneNumberId,
        string $wabaId,
        string $apiToken,
        string $verifyToken,
        string $appSecret
    ): string {
        $now = date('Y-m-d H:i:s');
        $encToken = class_exists(KeyGuard::class) ? KeyGuard::encrypt($apiToken) : base64_encode($apiToken);
        $encSecret = class_exists(KeyGuard::class) ? KeyGuard::encrypt($appSecret) : base64_encode($appSecret);

        $stmt = $this->db->prepare(
            "SELECT public_id FROM crm_whatsapp_configs WHERE organization_id = :org_id LIMIT 1"
        );
        $stmt->execute([':org_id' => $organizationId]);
        $existing = $stmt->fetchColumn();

        if ($existing) {
            $upd = $this->db->prepare(
                "UPDATE crm_whatsapp_configs
                 SET phone_number_id = :pn, waba_id = :waba, api_token_encrypted = :tok, webhook_verify_token = :vtok, app_secret_encrypted = :sec, is_active = 1, updated_at = :now
                 WHERE public_id = :pub_id"
            );
            $upd->execute([
                ':pn' => $phoneNumberId,
                ':waba' => $wabaId,
                ':tok' => $encToken,
                ':vtok' => $verifyToken,
                ':sec' => $encSecret,
                ':now' => $now,
                ':pub_id' => $existing,
            ]);
            return (string)$existing;
        }

        $pubId = 'wac_' . bin2hex(random_bytes(10));
        $ins = $this->db->prepare(
            "INSERT INTO crm_whatsapp_configs
             (public_id, organization_id, phone_number_id, waba_id, api_token_encrypted, webhook_verify_token, app_secret_encrypted, is_active, created_at, updated_at)
             VALUES (:pub_id, :org_id, :pn, :waba, :tok, :vtok, :sec, 1, :now, :now)"
        );
        $ins->execute([
            ':pub_id' => $pubId,
            ':org_id' => $organizationId,
            ':pn' => $phoneNumberId,
            ':waba' => $wabaId,
            ':tok' => $encToken,
            ':vtok' => $verifyToken,
            ':sec' => $encSecret,
            ':now' => $now,
        ]);

        return $pubId;
    }

    /**
     * Process incoming webhook callback from Meta Cloud API.
     * Validates HMAC-SHA256 signature if app_secret is set, and deduplicates message_id.
     *
     * @param array<string, mixed> $payload
     * @return array{ok: bool, duplicate: bool, conversation_id: ?string}
     */
    public function handleWebhook(int $organizationId, array $payload, ?string $rawBody = null, ?string $signatureHeader = null): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM crm_whatsapp_configs WHERE organization_id = :org_id AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([':org_id' => $organizationId]);
        $cfg = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cfg) {
            throw new RuntimeException("WhatsApp config not found or inactive.");
        }

        // Validate Meta signature if provided
        if ($signatureHeader !== null && $rawBody !== null && !empty($cfg['app_secret_encrypted'])) {
            $rawSecret = (string)$cfg['app_secret_encrypted'];
            $appSecret = class_exists(KeyGuard::class) ? KeyGuard::decrypt($rawSecret) : base64_decode($rawSecret);
            $expected = 'sha256=' . hash_hmac('sha256', $rawBody, (string)$appSecret);
            if (!hash_equals($expected, $signatureHeader)) {
                throw new RuntimeException("Invalid WhatsApp signature.");
            }
        }

        // Extract message from Meta Cloud API structure
        $entry = $payload['entry'][0]['changes'][0]['value'] ?? [];
        $messages = $entry['messages'] ?? [];
        if (empty($messages)) {
            return ['ok' => true, 'duplicate' => false, 'conversation_id' => null];
        }

        $msg = $messages[0];
        $waMsgId = (string)($msg['id'] ?? '');
        $fromPhone = (string)($msg['from'] ?? '');
        $text = (string)($msg['text']['body'] ?? '');
        $contactName = (string)($entry['contacts'][0]['profile']['name'] ?? 'WhatsApp Contact');

        if ($waMsgId === '' || $fromPhone === '') {
            throw new RuntimeException("Malformed WhatsApp message payload.");
        }

        // Deduplication
        $dup = $this->db->prepare(
            "SELECT id FROM crm_whatsapp_messages WHERE organization_id = :org_id AND wa_message_id = :mid LIMIT 1"
        );
        $dup->execute([':org_id' => $organizationId, ':mid' => $waMsgId]);
        if ($dup->fetchColumn()) {
            return ['ok' => true, 'duplicate' => true, 'conversation_id' => null];
        }

        $now = date('Y-m-d H:i:s');

        // Find or create conversation
        $convStmt = $this->db->prepare(
            "SELECT id, public_id FROM crm_whatsapp_conversations WHERE organization_id = :org_id AND wa_chat_id = :chat LIMIT 1"
        );
        $convStmt->execute([':org_id' => $organizationId, ':chat' => $fromPhone]);
        $conv = $convStmt->fetch(PDO::FETCH_ASSOC);

        if ($conv) {
            $convId = (int)$conv['id'];
            $convPubId = (string)$conv['public_id'];
            $this->db->prepare("UPDATE crm_whatsapp_conversations SET last_message_at = :now, status = 'open' WHERE id = :id")
                ->execute([':now' => $now, ':id' => $convId]);
        } else {
            $convPubId = 'wcn_' . bin2hex(random_bytes(10));
            $intakePubId = 'itk_' . bin2hex(random_bytes(10)); // Link to Intake
            $insConv = $this->db->prepare(
                "INSERT INTO crm_whatsapp_conversations
                 (public_id, organization_id, wa_chat_id, contact_phone, contact_name, intake_public_id, last_message_at, status, created_at, updated_at)
                 VALUES (:pub_id, :org_id, :chat, :phone, :name, :intake, :now, 'open', :now, :now)"
            );
            $insConv->execute([
                ':pub_id' => $convPubId,
                ':org_id' => $organizationId,
                ':chat' => $fromPhone,
                ':phone' => $fromPhone,
                ':name' => $contactName,
                ':intake' => $intakePubId,
                ':now' => $now,
            ]);
            $convId = (int)$this->db->lastInsertId();
        }

        // Insert message
        $msgPubId = 'wms_' . bin2hex(random_bytes(10));
        $insMsg = $this->db->prepare(
            "INSERT INTO crm_whatsapp_messages
             (public_id, organization_id, conversation_id, wa_message_id, direction, message_text, delivery_status, created_at)
             VALUES (:pub_id, :org_id, :cid, :mid, 'inbound', :txt, 'delivered', :now)"
        );
        $insMsg->execute([
            ':pub_id' => $msgPubId,
            ':org_id' => $organizationId,
            ':cid' => $convId,
            ':mid' => $waMsgId,
            ':txt' => $text,
            ':now' => $now,
        ]);

        return [
            'ok' => true,
            'duplicate' => false,
            'conversation_id' => $convPubId,
        ];
    }

    /**
     * List open conversations for Inbox view.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listConversations(int $organizationId): array
    {
        $stmt = $this->db->prepare(
            "SELECT public_id, contact_phone, contact_name, intake_public_id, assigned_user_public_id, last_message_at, status
             FROM crm_whatsapp_conversations
             WHERE organization_id = :org_id
             ORDER BY last_message_at DESC"
        );
        $stmt->execute([':org_id' => $organizationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
