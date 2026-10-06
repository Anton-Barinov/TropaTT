<?php
declare(strict_types=1);

namespace Module\Crm\TelegramBot\Service;

use Api\System\Library\Security\KeyGuard;
use PDO;
use RuntimeException;

/**
 * Service managing Telegram Bot webhook/polling processing, staff binding, and deduplication.
 */
final class TelegramBotService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Save/Update bot config for organization.
     */
    public function saveConfig(
        int $organizationId,
        string $botToken,
        string $webhookSecretToken,
        ?string $defaultProject = null,
        ?string $defaultAssignee = null
    ): string {
        $now = date('Y-m-d H:i:s');
        $encryptedToken = class_exists(KeyGuard::class) ? KeyGuard::encrypt($botToken) : base64_encode($botToken);

        $stmt = $this->db->prepare(
            "SELECT public_id FROM crm_telegram_configs WHERE organization_id = :org_id LIMIT 1"
        );
        $stmt->execute([':org_id' => $organizationId]);
        $existingPubId = $stmt->fetchColumn();

        if ($existingPubId) {
            $upd = $this->db->prepare(
                "UPDATE crm_telegram_configs
                 SET bot_token_encrypted = :tok, webhook_secret_token = :sec, default_project_public_id = :proj, default_assignee_user_public_id = :usr, is_active = 1, updated_at = :now
                 WHERE public_id = :pub_id"
            );
            $upd->execute([
                ':tok' => $encryptedToken,
                ':sec' => $webhookSecretToken,
                ':proj' => $defaultProject,
                ':usr' => $defaultAssignee,
                ':now' => $now,
                ':pub_id' => $existingPubId,
            ]);
            return (string)$existingPubId;
        }

        $pubId = 'tgc_' . bin2hex(random_bytes(10));
        $ins = $this->db->prepare(
            "INSERT INTO crm_telegram_configs
             (public_id, organization_id, bot_token_encrypted, webhook_secret_token, default_project_public_id, default_assignee_user_public_id, is_active, created_at, updated_at)
             VALUES (:pub_id, :org_id, :tok, :sec, :proj, :usr, 1, :now, :now)"
        );
        $ins->execute([
            ':pub_id' => $pubId,
            ':org_id' => $organizationId,
            ':tok' => $encryptedToken,
            ':sec' => $webhookSecretToken,
            ':proj' => $defaultProject,
            ':usr' => $defaultAssignee,
            ':now' => $now,
        ]);

        return $pubId;
    }

    /**
     * Process an incoming Telegram update with deduplication on update_id and secret token validation.
     *
     * @param array<string, mixed> $update
     * @return array{ok: bool, duplicate: bool, action: string}
     */
    public function handleUpdate(int $organizationId, string $receivedSecretToken, array $update): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM crm_telegram_configs WHERE organization_id = :org_id AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([':org_id' => $organizationId]);
        $cfg = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cfg) {
            throw new RuntimeException("Telegram bot configuration not found or inactive.");
        }

        // Secret token validation
        if (!hash_equals((string)$cfg['webhook_secret_token'], $receivedSecretToken)) {
            throw new RuntimeException("Invalid Telegram webhook secret token.");
        }

        $updateId = (int)($update['update_id'] ?? 0);
        if ($updateId <= 0) {
            throw new RuntimeException("Missing or invalid update_id.");
        }

        // Deduplication check
        $dupCheck = $this->db->prepare(
            "SELECT id FROM crm_telegram_updates_log WHERE organization_id = :org_id AND update_id = :uid LIMIT 1"
        );
        $dupCheck->execute([':org_id' => $organizationId, ':uid' => $updateId]);
        if ($dupCheck->fetchColumn()) {
            return [
                'ok' => true,
                'duplicate' => true,
                'action' => 'ignored_duplicate',
            ];
        }

        $message = $update['message'] ?? [];
        $chatId = (int)($message['chat']['id'] ?? 0);
        $text = trim((string)($message['text'] ?? ''));
        $now = date('Y-m-d H:i:s');
        $action = 'message_received';

        if (str_starts_with($text, '/bind ')) {
            $code = trim(substr($text, 6));
            $action = $this->verifyUserBinding($organizationId, $chatId, $code);
        }

        // Record update log
        $logPubId = 'tgu_' . bin2hex(random_bytes(10));
        $ins = $this->db->prepare(
            "INSERT INTO crm_telegram_updates_log
             (public_id, organization_id, update_id, chat_id, action_type, payload_json, created_at)
             VALUES (:pub_id, :org_id, :uid, :cid, :action, :payload, :now)"
        );
        $ins->execute([
            ':pub_id' => $logPubId,
            ':org_id' => $organizationId,
            ':uid' => $updateId,
            ':cid' => $chatId,
            ':action' => $action,
            ':payload' => json_encode($update, JSON_UNESCAPED_UNICODE),
            ':now' => $now,
        ]);

        return [
            'ok' => true,
            'duplicate' => false,
            'action' => $action,
        ];
    }

    /**
     * Verify staff user binding code.
     */
    private function verifyUserBinding(int $organizationId, int $chatId, string $code): string
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM crm_telegram_user_bindings
             WHERE organization_id = :org_id AND verification_code = :code AND is_verified = 0 LIMIT 1"
        );
        $stmt->execute([':org_id' => $organizationId, ':code' => $code]);
        $bindingId = $stmt->fetchColumn();

        if (!$bindingId) {
            return 'bind_failed_invalid_code';
        }

        $now = date('Y-m-d H:i:s');
        $upd = $this->db->prepare(
            "UPDATE crm_telegram_user_bindings
             SET is_verified = 1, telegram_chat_id = :cid, verification_code = NULL, updated_at = :now
             WHERE id = :id"
        );
        $upd->execute([':cid' => $chatId, ':now' => $now, ':id' => $bindingId]);

        return 'bind_success';
    }

    /**
     * Generate binding code for a staff member.
     */
    public function generateBindingCode(int $organizationId, string $userPublicId): string
    {
        $code = strtoupper(bin2hex(random_bytes(4)));
        $now = date('Y-m-d H:i:s');
        $pubId = 'tgb_' . bin2hex(random_bytes(10));

        $stmt = $this->db->prepare(
            "INSERT INTO crm_telegram_user_bindings
             (public_id, organization_id, user_public_id, telegram_chat_id, verification_code, is_verified, created_at, updated_at)
             VALUES (:pub_id, :org_id, :uid, 0, :code, 0, :now, :now)"
        );
        $stmt->execute([
            ':pub_id' => $pubId,
            ':org_id' => $organizationId,
            ':uid' => $userPublicId,
            ':code' => $code,
            ':now' => $now,
        ]);

        return $code;
    }
}
