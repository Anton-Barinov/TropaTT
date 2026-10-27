<?php
declare(strict_types=1);

namespace Module\Crm\WhatsAppBusiness\Service;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Service managing WhatsApp Business Cloud API webhooks, conversations inbox, and outbound message dispatch.
 */
final class WhatsAppBusinessService
{
    private PDO $db;

    /** @var null|callable(string,array<string,string>,string):array{status:int,body:string} */
    private $httpTransport;

    public function __construct(PDO $db, ?callable $httpTransport = null)
    {
        $this->db = $db;
        $this->httpTransport = $httpTransport;
    }

    public function saveConfig(
        int $organizationId,
        string $phoneNumberId,
        string $wabaId,
        string $apiToken,
        string $verifyToken,
        string $appSecret,
        int $configuredByUserId = 0
    ): string {
        if ($organizationId <= 0) {
            throw new RuntimeException('An active workspace is required.');
        }
        $phoneNumberId = trim($phoneNumberId);
        $wabaId = trim($wabaId);
        if ($phoneNumberId === '' || strlen($phoneNumberId) > 64 || !preg_match('/^[A-Za-z0-9_-]+$/D', $phoneNumberId)
            || $wabaId === '' || strlen($wabaId) > 64 || !preg_match('/^[A-Za-z0-9_-]+$/D', $wabaId)) {
            throw new RuntimeException('WhatsApp phone number ID or business account ID is invalid.');
        }
        if ($apiToken === '' || strlen($apiToken) > 4096 || $verifyToken === '' || strlen($verifyToken) > 48
            || $appSecret === '' || strlen($appSecret) > 4096) {
            throw new RuntimeException('WhatsApp credentials are missing or exceed the supported size.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $encToken = $this->encryptCredential($apiToken);
        $encVerifyToken = $this->encryptCredential($verifyToken);
        $encSecret = $this->encryptCredential($appSecret);

        $stmt = $this->db->prepare(
            "SELECT public_id FROM crm_whatsapp_configs WHERE organization_id = :org_id LIMIT 1"
        );
        $stmt->execute([':org_id' => $organizationId]);
        $existing = $stmt->fetchColumn();

        if ($existing) {
            $upd = $this->db->prepare(
                "UPDATE crm_whatsapp_configs
                 SET phone_number_id = :pn, waba_id = :waba, api_token_encrypted = :tok, webhook_verify_token = :vtok, app_secret_encrypted = :sec, configured_by_user_id = :user_id, is_active = 1, updated_at = :now
                 WHERE public_id = :pub_id"
            );
            $upd->execute([
                ':pn' => $phoneNumberId,
                ':waba' => $wabaId,
                ':tok' => $encToken,
                ':vtok' => $encVerifyToken,
                ':sec' => $encSecret,
                ':user_id' => $configuredByUserId,
                ':now' => $now,
                ':pub_id' => $existing,
            ]);
            return (string)$existing;
        }

        $pubId = 'wac_' . bin2hex(random_bytes(10));
        $ins = $this->db->prepare(
            "INSERT INTO crm_whatsapp_configs
             (public_id, organization_id, phone_number_id, waba_id, api_token_encrypted, webhook_verify_token, app_secret_encrypted, configured_by_user_id, is_active, created_at, updated_at)
             VALUES (:pub_id, :org_id, :pn, :waba, :tok, :vtok, :sec, :user_id, 1, :now, :now)"
        );
        $ins->execute([
            ':pub_id' => $pubId,
            ':org_id' => $organizationId,
            ':pn' => $phoneNumberId,
            ':waba' => $wabaId,
            ':tok' => $encToken,
            ':vtok' => $encVerifyToken,
            ':sec' => $encSecret,
            ':user_id' => $configuredByUserId,
            ':now' => $now,
        ]);

        return $pubId;
    }

    /** Return only non-secret setup state; encrypted credentials never leave the server. */
    public function getStatus(int $organizationId, string $webhookUrl = ''): array
    {
        if ($organizationId <= 0) {
            throw new RuntimeException('An active workspace is required.');
        }
        $stmt = $this->db->prepare(
            'SELECT phone_number_id, waba_id, is_active, api_token_encrypted, webhook_verify_token, app_secret_encrypted, updated_at'
            . ' FROM crm_whatsapp_configs WHERE organization_id = :org_id LIMIT 1'
        );
        $stmt->execute([':org_id' => $organizationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $configured = is_array($row)
            && trim((string)($row['phone_number_id'] ?? '')) !== ''
            && trim((string)($row['waba_id'] ?? '')) !== ''
            && str_starts_with((string)($row['api_token_encrypted'] ?? ''), 'v1:')
            && str_starts_with((string)($row['webhook_verify_token'] ?? ''), 'v1:')
            && str_starts_with((string)($row['app_secret_encrypted'] ?? ''), 'v1:');
        return [
            'configured' => $configured,
            'is_active' => $configured && !empty($row['is_active']),
            'phone_number_id' => $configured ? (string)$row['phone_number_id'] : '',
            'waba_id' => $configured ? (string)$row['waba_id'] : '',
            'webhook_url' => $webhookUrl,
            'updated_at' => $configured ? (string)($row['updated_at'] ?? '') : null,
            'inbound_webhook_ready' => $configured,
            'inbound_webhook_note' => $configured ? 'Webhook verification and signed inbound messages are supported.' : 'Configure Meta credentials to activate the webhook.',
        ];
    }

    /** Verify Meta's challenge token without exposing it to the admin UI. */
    public function verifyWebhookChallenge(int $organizationId, string $verifyToken): bool
    {
        if ($organizationId <= 0 || $verifyToken === '' || strlen($verifyToken) > 256) {
            return false;
        }
        $stmt = $this->db->prepare(
            'SELECT webhook_verify_token FROM crm_whatsapp_configs WHERE organization_id = :org_id AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([':org_id' => $organizationId]);
        $encrypted = trim((string)($stmt->fetchColumn() ?: ''));
        if ($encrypted === '' || !str_starts_with($encrypted, 'v1:')) {
            return false;
        }
        $configured = $this->decryptCredential($encrypted);
        return hash_equals($configured, $verifyToken);
    }

    /**
     * Process incoming webhook callback from Meta Cloud API.
     * Validates HMAC-SHA256 signature if app_secret is set, and deduplicates message_id.
     *
     * @param array<string, mixed> $payload
     * @return array{ok: bool, processed: int, duplicates: int, conversation_ids: array<int,string>}
     */
    public function handleWebhook(int $organizationId, array $payload, ?string $rawBody = null, ?string $signatureHeader = null): array
    {
        $stmt = $this->db->prepare('SELECT app_secret_encrypted, waba_id, phone_number_id FROM crm_whatsapp_configs WHERE organization_id = :org_id AND is_active = 1 LIMIT 1');
        $stmt->execute([':org_id' => $organizationId]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        $encryptedSecret = (string)($config['app_secret_encrypted'] ?? '');
        if ($organizationId <= 0 || $encryptedSecret === '' || $rawBody === null || $rawBody === ''
            || $signatureHeader === null || !preg_match('/^sha256=[a-f0-9]{64}$/i', $signatureHeader)) {
            throw new \DomainException('Webhook authentication failed.');
        }
        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $this->decryptCredential($encryptedSecret));
        if (!hash_equals($expected, strtolower($signatureHeader))) {
            throw new \DomainException('Webhook authentication failed.');
        }

        $processed = 0;
        $duplicates = 0;
        $conversationIds = [];
        foreach ((array)($payload['entry'] ?? []) as $entry) {
            if (!is_array($entry) || !hash_equals((string)($config['waba_id'] ?? ''), (string)($entry['id'] ?? ''))) {
                throw new \DomainException('Webhook account does not match workspace configuration.');
            }
            foreach ((array)($entry['changes'] ?? []) as $change) {
                $value = is_array($change['value'] ?? null) ? $change['value'] : [];
                $metadataPhoneId = (string)($value['metadata']['phone_number_id'] ?? '');
                if ($metadataPhoneId !== '' && !hash_equals((string)($config['phone_number_id'] ?? ''), $metadataPhoneId)) {
                    throw new \DomainException('Webhook phone does not match workspace configuration.');
                }
                $contacts = [];
                foreach ((array)($value['contacts'] ?? []) as $contact) {
                    $waId = (string)($contact['wa_id'] ?? '');
                    if ($waId !== '') {
                        $contacts[$waId] = mb_substr(trim((string)($contact['profile']['name'] ?? 'WhatsApp Contact')), 0, 128);
                    }
                }
                foreach ((array)($value['messages'] ?? []) as $message) {
                    if (++$processed > 100) {
                        throw new \DomainException('Webhook contains too many messages.');
                    }
                    if (!is_array($message)) continue;
                    $waMsgId = (string)($message['id'] ?? '');
                    $from = (string)($message['from'] ?? '');
                    if (!preg_match('/^[A-Za-z0-9._-]{1,128}$/D', $waMsgId) || !preg_match('/^[0-9]{5,32}$/D', $from)) {
                        throw new \DomainException('Malformed WhatsApp message.');
                    }
                    $type = (string)($message['type'] ?? '');
                    $text = $type === 'text'
                        ? (string)($message['text']['body'] ?? '')
                        : '[WhatsApp message: ' . preg_replace('/[^a-z0-9_-]/i', '', $type ?: 'unsupported') . ']';
                    if (isset($message[$type]['caption']) && is_string($message[$type]['caption'])) {
                        $text .= "\n" . $message[$type]['caption'];
                    }
                    if (mb_strlen($text) > 4096) $text = mb_substr($text, 0, 4096);
                    $name = $contacts[$from] ?? 'WhatsApp Contact';
                    $now = gmdate('Y-m-d H:i:s');
                    $this->db->beginTransaction();
                    try {
                        $dup = $this->db->prepare('SELECT c.public_id FROM crm_whatsapp_messages m JOIN crm_whatsapp_conversations c ON c.id = m.conversation_id AND c.organization_id = m.organization_id WHERE m.organization_id = :org AND m.wa_message_id = :mid LIMIT 1');
                        $dup->execute([':org' => $organizationId, ':mid' => $waMsgId]);
                        $existingConversation = $dup->fetchColumn();
                        if ($existingConversation) {
                            $this->db->commit();
                            $duplicates++;
                            $conversationIds[] = (string)$existingConversation;
                            continue;
                        }
                        $find = $this->db->prepare('SELECT id, public_id FROM crm_whatsapp_conversations WHERE organization_id = :org AND wa_chat_id = :chat LIMIT 1');
                        $find->execute([':org' => $organizationId, ':chat' => $from]);
                        $conversation = $find->fetch(PDO::FETCH_ASSOC);
                        if ($conversation) {
                            $conversationId = (int)$conversation['id'];
                            $conversationPublicId = (string)$conversation['public_id'];
                            $update = $this->db->prepare("UPDATE crm_whatsapp_conversations SET contact_name = :name, last_inbound_at = :now, last_message_at = :now, status = 'open', updated_at = :now WHERE id = :id AND organization_id = :org");
                            $update->execute([':name' => $name, ':now' => $now, ':id' => $conversationId, ':org' => $organizationId]);
                        } else {
                            $conversationPublicId = 'wcn_' . bin2hex(random_bytes(10));
                            $insert = $this->db->prepare("INSERT INTO crm_whatsapp_conversations (public_id, organization_id, wa_chat_id, contact_phone, contact_name, intake_public_id, last_message_at, last_inbound_at, status, created_at, updated_at) VALUES (:pub, :org, :chat, :phone, :name, NULL, :now, :now, 'open', :now, :now)");
                            $insert->execute([':pub' => $conversationPublicId, ':org' => $organizationId, ':chat' => $from, ':phone' => $from, ':name' => $name, ':now' => $now]);
                            $conversationId = (int)$this->db->lastInsertId();
                        }
                        $insertMessage = $this->db->prepare("INSERT INTO crm_whatsapp_messages (public_id, organization_id, conversation_id, wa_message_id, direction, message_text, delivery_status, created_at) VALUES (:pub, :org, :cid, :mid, 'inbound', :txt, 'received', :now)");
                        $insertMessage->execute([':pub' => 'wms_' . bin2hex(random_bytes(10)), ':org' => $organizationId, ':cid' => $conversationId, ':mid' => $waMsgId, ':txt' => $text, ':now' => $now]);
                        $this->db->commit();
                        $conversationIds[] = $conversationPublicId;
                    } catch (Throwable $e) {
                        if ($this->db->inTransaction()) $this->db->rollBack();
                        throw $e;
                    }
                }
            }
        }
        return ['ok' => true, 'processed' => $processed - $duplicates, 'duplicates' => $duplicates, 'conversation_ids' => array_values(array_unique($conversationIds))];
    }

    /** Store text in Intake once per conversation; the caller supplies an authorized workspace member actor. */
    public function linkIntake(int $organizationId, string $conversationPublicId, callable $createIntake, array $actor): ?array
    {
        $query = $this->db->prepare('SELECT id, public_id, contact_phone, contact_name, intake_public_id FROM crm_whatsapp_conversations WHERE organization_id = :org AND public_id = :pub LIMIT 1');
        $query->execute([':org' => $organizationId, ':pub' => $conversationPublicId]);
        $conv = $query->fetch(PDO::FETCH_ASSOC);
        if (!$conv) return null;
        if (!empty($conv['intake_public_id'])) return ['public_id' => (string)$conv['intake_public_id']];
        $externalId = 'conversation:' . $conversationPublicId;
        $existing = $this->db->prepare('SELECT public_id FROM intake_items WHERE organization_id = :org AND external_source = :source AND external_id = :external AND deleted_at IS NULL LIMIT 1');
        $existing->execute([':org' => $organizationId, ':source' => 'whatsapp', ':external' => $externalId]);
        $intakePublicId = (string)($existing->fetchColumn() ?: '');
        if ($intakePublicId === '') {
            $messages = $this->db->prepare("SELECT message_text, created_at FROM crm_whatsapp_messages WHERE organization_id = :org AND conversation_id = :cid AND direction = 'inbound' ORDER BY id ASC LIMIT 20");
            $messages->execute([':org' => $organizationId, ':cid' => (int)$conv['id']]);
            $parts = [];
            foreach ($messages->fetchAll(PDO::FETCH_ASSOC) as $message) $parts[] = '[' . (string)$message['created_at'] . '] ' . (string)$message['message_text'];
            $result = $createIntake([
                'title' => mb_substr('WhatsApp: ' . (string)($conv['contact_name'] ?: $conv['contact_phone']), 0, 255),
                'description' => mb_substr(implode("\n", $parts), 0, 60000),
                'source_type' => 'webhook',
                'external_source' => 'whatsapp',
                'external_id' => $externalId,
            ], $actor);
            if (!is_array($result) || empty($result['public_id'])) return null;
            $intakePublicId = (string)$result['public_id'];
        }
        $update = $this->db->prepare('UPDATE crm_whatsapp_conversations SET intake_public_id = :intake, updated_at = :now WHERE organization_id = :org AND public_id = :pub AND intake_public_id IS NULL');
        $update->execute([':intake' => $intakePublicId, ':now' => gmdate('Y-m-d H:i:s'), ':org' => $organizationId, ':pub' => $conversationPublicId]);
        return ['public_id' => $intakePublicId];
    }

    public function sendReply(int $organizationId, string $conversationPublicId, int $userId, string $text, string $idempotencyKey): array
    {
        $text = trim($text);
        if ($organizationId <= 0 || $userId <= 0 || $text === '' || mb_strlen($text) > 4096
            || !preg_match('/^[A-Za-z0-9._:-]{16,128}$/D', $idempotencyKey)) {
            throw new RuntimeException('Reply request is invalid.');
        }
        $payloadHash = hash('sha256', $conversationPublicId . "\0" . $text);
        $keyHash = hash('sha256', $idempotencyKey);
        $this->db->beginTransaction();
        try {
            $existing = $this->db->prepare('SELECT payload_hash, status, wa_message_id FROM crm_whatsapp_send_requests WHERE organization_id = :org AND request_key_hash = :key LIMIT 1');
            $existing->execute([':org' => $organizationId, ':key' => $keyHash]);
            $row = $existing->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                if (!hash_equals((string)$row['payload_hash'], $payloadHash)) throw new RuntimeException('Idempotency key was already used for different content.');
                if ((string)$row['status'] === 'sent') { $this->db->commit(); return ['sent' => true, 'replayed' => true, 'message_id' => (string)$row['wa_message_id']]; }
                throw new RuntimeException('Previous send outcome is uncertain. Check the conversation before retrying.');
            }
            $cfgQuery = $this->db->prepare('SELECT phone_number_id, api_token_encrypted FROM crm_whatsapp_configs WHERE organization_id = :org AND is_active = 1 LIMIT 1');
            $cfgQuery->execute([':org' => $organizationId]);
            $cfg = $cfgQuery->fetch(PDO::FETCH_ASSOC);
            $convQuery = $this->db->prepare('SELECT id, wa_chat_id, last_inbound_at FROM crm_whatsapp_conversations WHERE organization_id = :org AND public_id = :pub LIMIT 1');
            $convQuery->execute([':org' => $organizationId, ':pub' => $conversationPublicId]);
            $conv = $convQuery->fetch(PDO::FETCH_ASSOC);
            $lastInboundAt = !empty($conv['last_inbound_at']) ? strtotime((string)$conv['last_inbound_at'] . ' UTC') : false;
            if (!$cfg || !$conv || $lastInboundAt === false || $lastInboundAt <= 0 || $lastInboundAt > time() + 300 || $lastInboundAt < time() - 86400) {
                throw new RuntimeException('Conversation is unavailable or outside WhatsApp 24-hour service window.');
            }
            $insert = $this->db->prepare("INSERT INTO crm_whatsapp_send_requests (organization_id, request_key_hash, payload_hash, status, created_at, updated_at) VALUES (:org, :key, :hash, 'sending', :now, :now)");
            $insert->execute([':org' => $organizationId, ':key' => $keyHash, ':hash' => $payloadHash, ':now' => gmdate('Y-m-d H:i:s')]);
            $this->db->commit();
            $body = json_encode(['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => (string)$conv['wa_chat_id'], 'type' => 'text', 'text' => ['preview_url' => false, 'body' => $text]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($body)) throw new RuntimeException('Reply payload encoding failed.');
            $url = 'https://graph.facebook.com/v26.0/' . rawurlencode((string)$cfg['phone_number_id']) . '/messages';
            $headers = ['Authorization' => 'Bearer ' . $this->decryptCredential((string)$cfg['api_token_encrypted']), 'Content-Type' => 'application/json', 'Accept' => 'application/json'];
            try {
                $response = $this->httpTransport !== null ? ($this->httpTransport)($url, $headers, $body) : $this->postGraphApi($url, $headers, $body);
            } catch (Throwable $transportError) {
                // A timeout or connection reset does not prove whether Meta accepted
                // the message. Persist that uncertainty so it cannot remain stuck
                // as "sending" or be blindly re-sent with the same key.
                $this->markSendRequest($organizationId, $keyHash, 'uncertain', '');
                throw new RuntimeException('WhatsApp send outcome is uncertain. Check the conversation before sending a new reply.', 0, $transportError);
            }
            $decoded = json_decode((string)($response['body'] ?? ''), true);
            if ((int)($response['status'] ?? 0) < 200 || (int)($response['status'] ?? 0) >= 300 || !is_array($decoded)) {
                $this->markSendRequest($organizationId, $keyHash, 'failed', '');
                throw new RuntimeException('WhatsApp provider rejected the reply.');
            }
            $waId = (string)($decoded['messages'][0]['id'] ?? '');
            if (!preg_match('/^[A-Za-z0-9._-]{1,128}$/D', $waId)) {
                $this->markSendRequest($organizationId, $keyHash, 'uncertain', '');
                throw new RuntimeException('WhatsApp send outcome is uncertain.');
            }
            $now = gmdate('Y-m-d H:i:s');
            $this->db->beginTransaction();
            $msg = $this->db->prepare("INSERT INTO crm_whatsapp_messages (public_id, organization_id, conversation_id, wa_message_id, direction, message_text, delivery_status, created_at) VALUES (:pub, :org, :cid, :mid, 'outbound', :txt, 'sent', :now)");
            $msg->execute([':pub' => 'wms_' . bin2hex(random_bytes(10)), ':org' => $organizationId, ':cid' => (int)$conv['id'], ':mid' => $waId, ':txt' => $text, ':now' => $now]);
            $this->db->prepare('UPDATE crm_whatsapp_conversations SET last_message_at = :now, updated_at = :now WHERE organization_id = :org AND id = :id')->execute([':now' => $now, ':org' => $organizationId, ':id' => (int)$conv['id']]);
            $this->db->prepare("UPDATE crm_whatsapp_send_requests SET status = 'sent', wa_message_id = :mid, updated_at = :now WHERE organization_id = :org AND request_key_hash = :key")->execute([':mid' => $waId, ':now' => $now, ':org' => $organizationId, ':key' => $keyHash]);
            $this->db->commit();
            return ['sent' => true, 'replayed' => false, 'message_id' => $waId];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    private function markSendRequest(int $organizationId, string $keyHash, string $status, string $messageId): void
    {
        $this->db->prepare('UPDATE crm_whatsapp_send_requests SET status = :status, wa_message_id = :mid, updated_at = :now WHERE organization_id = :org AND request_key_hash = :key')
            ->execute([':status' => $status, ':mid' => $messageId, ':now' => gmdate('Y-m-d H:i:s'), ':org' => $organizationId, ':key' => $keyHash]);
    }

    /** @param array<string,string> $headers @return array{status:int,body:string} */
    private function postGraphApi(string $url, array $headers, string $body): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('Secure HTTP transport is unavailable.');
        $handle = curl_init($url);
        if ($handle === false) throw new RuntimeException('Secure HTTP transport is unavailable.');
        $headerLines = [];
        foreach ($headers as $name => $value) $headerLines[] = $name . ': ' . $value;
        $responseBody = '';
        curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headerLines, CURLOPT_RETURNTRANSFER => false, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_USERAGENT => 'TropaTT-CRM-WhatsApp/1.0', CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$responseBody): int { if (strlen($responseBody) + strlen($chunk) > 65536) return 0; $responseBody .= $chunk; return strlen($chunk); }]);
        $result = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_errno($handle);
        curl_close($handle);
        if ($error !== 0 || $result !== true || strlen($responseBody) > 65536) throw new RuntimeException('WhatsApp send outcome is uncertain.');
        return ['status' => $status, 'body' => $responseBody];
    }

    /**
     * List open conversations for Inbox view.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listConversations(int $organizationId): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.public_id, c.contact_phone, c.contact_name, c.intake_public_id, c.assigned_user_public_id, c.last_message_at, c.status, (SELECT m.message_text FROM crm_whatsapp_messages m WHERE m.organization_id = c.organization_id AND m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_message
             FROM crm_whatsapp_conversations c
             WHERE c.organization_id = :org_id
             ORDER BY last_message_at DESC"
        );
        $stmt->execute([':org_id' => $organizationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listMessages(int $organizationId, string $conversationPublicId): array
    {
        $stmt = $this->db->prepare("SELECT m.public_id, m.direction, m.message_text, m.delivery_status, m.created_at FROM crm_whatsapp_messages m JOIN crm_whatsapp_conversations c ON c.id = m.conversation_id AND c.organization_id = m.organization_id WHERE m.organization_id = :org AND c.public_id = :pub ORDER BY m.id DESC LIMIT 200");
        $stmt->execute([':org' => $organizationId, ':pub' => $conversationPublicId]);
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
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
