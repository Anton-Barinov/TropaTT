<?php
declare(strict_types=1);

namespace Module\Crm\WhatsAppBusiness\Service;

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
        if ($apiToken === '' || $verifyToken === '' || $appSecret === '') {
            throw new RuntimeException('WhatsApp API token, verification token and app secret are required.');
        }

        $now = date('Y-m-d H:i:s');
        $encToken = $this->encryptCredential($apiToken);
        $encSecret = $this->encryptCredential($appSecret);

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

        $encryptedSecret = (string)($cfg['app_secret_encrypted'] ?? '');
        if ($encryptedSecret === '') {
            throw new RuntimeException('WhatsApp webhook signature validation is not configured.');
        }
        if ($rawBody === null || $rawBody === '') {
            throw new RuntimeException('WhatsApp webhook raw body is required for signature validation.');
        }
        if ($signatureHeader === null || !preg_match('/^sha256=[a-f0-9]{64}$/i', $signatureHeader)) {
            throw new RuntimeException('WhatsApp webhook signature is missing or malformed.');
        }

        $appSecret = $this->decryptCredential($encryptedSecret);
        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);
        if (!hash_equals($expected, strtolower($signatureHeader))) {
            throw new RuntimeException('Invalid WhatsApp signature.');
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

    private function encryptCredential(string $plaintext): string
    {
        $key = $this->credentialEncryptionKey();
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if (!is_string($ciphertext) || $ciphertext === '' || strlen($tag) !== 16) {
            throw new RuntimeException('Failed to encrypt WhatsApp credentials.');
        }

        return 'v1:' . base64_encode($iv . $tag . $ciphertext);
    }

    private function decryptCredential(string $encrypted): string
    {
        if (!str_starts_with($encrypted, 'v1:')) {
            throw new RuntimeException('Stored WhatsApp credential is not encrypted with the supported format; save the credentials again.');
        }

        $payload = base64_decode(substr($encrypted, 3), true);
        if (!is_string($payload) || strlen($payload) < 29) {
            throw new RuntimeException('Stored WhatsApp credential is invalid.');
        }

        $plaintext = openssl_decrypt(
            substr($payload, 28),
            'aes-256-gcm',
            $this->credentialEncryptionKey(),
            OPENSSL_RAW_DATA,
            substr($payload, 0, 12),
            substr($payload, 12, 16),
            ''
        );
        if (!is_string($plaintext) || $plaintext === '') {
            throw new RuntimeException('Stored WhatsApp credential cannot be decrypted. Save the credentials again.');
        }

        return $plaintext;
    }

    private function credentialEncryptionKey(): string
    {
        $configuredKey = trim((string)(getenv('APP_KEY') ?: ($_SERVER['APP_KEY'] ?? $_ENV['APP_KEY'] ?? '')));
        if (!preg_match('/^[a-f0-9]{64}$/i', $configuredKey)) {
            throw new RuntimeException('APP_KEY must be configured as a 32-byte hexadecimal key to use WhatsApp credentials.');
        }

        $masterKey = hex2bin($configuredKey);
        if (!is_string($masterKey) || strlen($masterKey) !== 32) {
            throw new RuntimeException('APP_KEY is invalid.');
        }

        return hash_hkdf('sha256', $masterKey, 32, 'crm.module.crm.whatsapp-business.credentials', 'TropaTT CRM');
    }
}
