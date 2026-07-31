<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use PDO;

final class ModuleNotificationDispatcher
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Send a notification to users from a module.
     * @param int|array<int> $userIds
     */
    public function notify(string $moduleName, int|array $userIds, string $title, string $body, string $type = 'info', ?string $actionUrl = null): void
    {
        $userIds = is_array($userIds) ? $userIds : [$userIds];
        $now = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare("INSERT INTO notifications (public_id, user_id, category, title, body, entity_type, action_code, link, payload_json, is_read, created_at) VALUES (:public_id, :user_id, :category, :title, :body, :entity, :action, :link, :payload, 0, :now)");

        foreach ($userIds as $userId) {
            $publicId = 'n_' . bin2hex(random_bytes(12));
            $payload = json_encode([
                'module' => $moduleName,
                'title' => $title,
                'body' => $body,
                'type' => $type,
                'action_url' => $actionUrl,
            ], JSON_UNESCAPED_UNICODE);

            $stmt->execute([
                'public_id' => $publicId,
                'user_id' => (int)$userId,
                'category' => 'module.' . $moduleName,
                'title' => $title,
                'body' => $body,
                'entity' => 'module',
                'action' => 'notify',
                'link' => $actionUrl,
                'payload' => $payload,
                'now' => $now,
            ]);
        }

        error_log(sprintf(
            '[ModuleNotification] %s -> %d user(s): %s',
            $moduleName,
            count($userIds),
            $title
        ));
    }
}
