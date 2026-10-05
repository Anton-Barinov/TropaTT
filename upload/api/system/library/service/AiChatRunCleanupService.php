<?php
declare(strict_types=1);
namespace Api\System\Library\Service;
use PDO;

/** Bounded cleanup, usable by web traffic and ordinary shared-hosting cron. */
final class AiChatRunCleanupService
{
    public function __construct(private readonly PDO $pdo, private readonly ?SettingService $settings = null) {}

    public function cleanupExpired(int $limit = 100, ?array $policies = null): int
    {
        $limit = max(1, min(500, $limit));
        $days = max(1, min(3650, (int)($policies['chat_runs_ttl_days'] ?? $this->settings?->get('ai_retention','chat_runs_ttl_days')['value'] ?? 30)));
        // Worker lease timestamps use PHP UTC; created/updated_at use SQL NOW().
        // These clocks can differ on shared hosts whose MySQL session is not UTC.
        $dbClock = time();
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $wallTime = (string)$this->pdo->query('SELECT NOW()')->fetchColumn();
            $parsed = strtotime($wallTime . ' UTC');
            if ($parsed === false) throw new \RuntimeException('Database clock unavailable');
            $dbClock = $parsed;
        }
        $params = ['cutoff' => gmdate('Y-m-d H:i:s', $dbClock-$days*86400),
            'lease' => gmdate('Y-m-d H:i:s',time()-900),
            'lease_without_timestamp' => gmdate('Y-m-d H:i:s',$dbClock-900)];
        // Expiration never invalidates a fresh worker's fencing token. Orphaned
        // workers get the same lease grace as normal workers before deletion.
        $predicate = "(ai_chat_runs.updated_at < :cutoff
            OR NOT EXISTS (SELECT 1 FROM chats WHERE chats.id = ai_chat_runs.chat_id)
            OR NOT EXISTS (SELECT 1 FROM users WHERE users.id = ai_chat_runs.actor_user_id))
            AND (ai_chat_runs.status NOT IN ('running','cancelling')
                OR (ai_chat_runs.locked_at IS NOT NULL AND ai_chat_runs.locked_at < :lease)
                OR (ai_chat_runs.locked_at IS NULL AND ai_chat_runs.updated_at < :lease_without_timestamp))";
        $stmt = $this->pdo->prepare("SELECT id FROM ai_chat_runs WHERE {$predicate} ORDER BY id LIMIT {$limit}");
        $stmt->execute($params);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $delete = $this->pdo->prepare("DELETE FROM ai_chat_runs WHERE id = :id AND {$predicate}");
        $count = 0;
        foreach ($ids as $id) {
            // Recheck after selection: the row may have obtained a live lease.
            $delete->execute(['id' => $id] + $params);
            $count += $delete->rowCount();
        }
        return $count;
    }
}
