<?php
declare(strict_types=1);
namespace Api\System\Library\Service;
use PDO;
use Api\System\Library\Support\Ulid;

/** Reserves spend before I/O. No provider call runs inside a database transaction. */
final class AiChatBudgetService
{
    public function __construct(private readonly PDO $pdo, private readonly SettingService $settings) {}

    /** Budgets follow the human user across chats and workspaces, including root. */
    public function acquire(array $actor, string $runId, int $maxOutputTokens, int $inputTokens = 0): array
    {
        $userId = (int)($actor['id'] ?? 0);
        if ($userId < 1 || $runId === '' || strlen($runId) > 64 || $maxOutputTokens < 1 || $maxOutputTokens > 16384 || $inputTokens < 0 || $inputTokens > 131072) {
            return ['ok' => false, 'code' => 'AI_BUDGET_INVALID_REQUEST'];
        }
        // Do not take ownership of an enclosing transaction or silently bypass missing schema.
        if ($this->pdo->inTransaction()) return ['ok' => false, 'code' => 'AI_BUDGET_UNAVAILABLE', 'retry_after' => 5];
        try {
            $minuteLimit = $this->integer('max_requests_per_minute', 60, 1, 120);
            $dayLimit = $this->integer('max_requests_per_day', 2000, 1, 10000);
            $concurrency = $this->integer('max_concurrent_interactive_requests', 2, 1, 4);
            $tokenLimit = $this->integer('max_tokens_per_day', 100000, 100, 2000000);
            $usdLimit = $this->number('max_cost_per_day_usd', 20.0, 0.01, 1000.0);
            $price = $this->number('cost_per_1k_tokens_usd', 0.02, 0.0001, 1000.0);
            $tokenLimit = min($tokenLimit, (int)floor($usdLimit * 1000 / $price));
            $tokens = $inputTokens + $maxOutputTokens;
            $now = gmdate('Y-m-d H:i:s');
            $this->pdo->beginTransaction();
            $mysql = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
            // Global row serializes reservation creation; actor row also serializes reconciliation.
            foreach ([0, $userId] as $lockId) {
                $insert = $this->pdo->prepare(($mysql ? 'INSERT IGNORE' : 'INSERT OR IGNORE') . ' INTO ai_chat_budget_locks (user_id) VALUES (:uid)');
                $insert->execute(['uid' => $lockId]);
                $lock = $this->pdo->prepare('SELECT user_id FROM ai_chat_budget_locks WHERE user_id = :uid' . ($mysql ? ' FOR UPDATE' : ''));
                $lock->execute(['uid' => $lockId]);
            }
            $query = $this->pdo->prepare("SELECT COUNT(*) AS requests, COALESCE(SUM(charged_tokens),0) AS tokens,
                COALESCE(SUM(CASE WHEN created_at >= :minute THEN 1 ELSE 0 END),0) AS minute_requests,
                COALESCE(SUM(CASE WHEN status = 'active' AND expires_at > :now THEN 1 ELSE 0 END),0) AS active
                FROM ai_chat_budget_reservations WHERE user_id = :uid AND created_at >= :day");
            $query->execute(['minute' => gmdate('Y-m-d H:i:s', time()-60), 'now' => $now, 'uid' => $userId, 'day' => gmdate('Y-m-d H:i:s', time()-86400)]);
            $usage = $query->fetch(PDO::FETCH_ASSOC);
            $code = null; $retry = 60;
            if ((int)$usage['minute_requests'] >= $minuteLimit || (int)$usage['requests'] >= $dayLimit) $code = 'AI_RATE_LIMITED';
            elseif ((int)$usage['tokens'] + $tokens > $tokenLimit) { $code = 'AI_COST_LIMIT_EXCEEDED'; $retry = 3600; }
            elseif ((int)$usage['active'] >= $concurrency) { $code = 'AI_BUSY'; $retry = 5; }
            $site = $this->pdo->prepare("SELECT COUNT(*) FROM ai_chat_budget_reservations WHERE status = 'active' AND expires_at > :now");
            $site->execute(['now' => $now]);
            if ($code === null && (int)$site->fetchColumn() >= 16) { $code = 'AI_BUSY'; $retry = 5; }
            if ($code !== null) { $this->pdo->rollBack(); return ['ok' => false, 'code' => $code, 'retry_after' => $retry]; }
            $publicId = Ulid::generate('aibr');
            $insert = $this->pdo->prepare("INSERT INTO ai_chat_budget_reservations (public_id,user_id,run_public_id,reserved_tokens,charged_tokens,status,created_at,expires_at) VALUES (:pid,:uid,:run,:reserved,:charged,'active',:now,:expires)");
            $insert->execute(['pid'=>$publicId,'uid'=>$userId,'run'=>$runId,'reserved'=>$tokens,'charged'=>$tokens,'now'=>$now,'expires'=>gmdate('Y-m-d H:i:s',time()+900)]);
            $this->pdo->commit();
            return ['ok' => true, 'reservation_id' => $publicId];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return ['ok' => false, 'code' => 'AI_BUDGET_UNAVAILABLE', 'retry_after' => 5];
        }
    }

    /** Each attempt reconciles once. Unknown/error usage never refunds the reservation. */
    public function finish(string $reservationId, ?array $completion = null): void
    {
        $tokens = max(0, (int)($completion['total_tokens'] ?? 0), (int)($completion['request_tokens'] ?? 0) + (int)($completion['response_tokens'] ?? 0));
        $known = !empty($completion['ok']) && array_key_exists('request_tokens', $completion ?? [])
            && array_key_exists('response_tokens', $completion ?? []) && (int)$completion['request_tokens'] > 0
            && (int)$completion['response_tokens'] >= 0;
        // One atomic UPDATE reconciles the active attempt. Later calls may increase,
        // but never lower, a finalized charge; simultaneous acquire sees either value.
        $stmt = $this->pdo->prepare("UPDATE ai_chat_budget_reservations SET charged_tokens = CASE
            WHEN status = 'active' AND :known = 'yes' THEN :actual
            WHEN charged_tokens > :t1 THEN charged_tokens ELSE :t2 END,
            status = 'finished', finished_at = :now WHERE public_id = :pid");
        $stmt->execute(['known'=>$known ? 'yes' : 'no','actual'=>$tokens,'t1'=>$tokens,'t2'=>$tokens,'now'=>gmdate('Y-m-d H:i:s'),'pid'=>$reservationId]);
    }

    /** Standard cron can invoke this; expiration also stops stale concurrency immediately. */
    public function cleanupStale(int $limit = 100): int
    {
        $limit = max(1,min(500,$limit));
        $stmt = $this->pdo->prepare("SELECT public_id FROM ai_chat_budget_reservations WHERE status = 'active' AND expires_at <= :now ORDER BY expires_at LIMIT {$limit}");
        $stmt->execute(['now'=>gmdate('Y-m-d H:i:s')]);
        $count = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) { $this->finish((string)$id); ++$count; }
        // Keep enough history for rolling 24-hour budgets; delete only a bounded old batch.
        $old = $this->pdo->prepare("SELECT public_id FROM ai_chat_budget_reservations WHERE status = 'finished' AND created_at < :before ORDER BY created_at LIMIT {$limit}");
        $old->execute(['before'=>gmdate('Y-m-d H:i:s',time()-172800)]);
        $delete = $this->pdo->prepare('DELETE FROM ai_chat_budget_reservations WHERE public_id = :pid');
        foreach ($old->fetchAll(PDO::FETCH_COLUMN) as $id) $delete->execute(['pid'=>$id]);
        return $count;
    }
    private function integer(string $name,int $default,int $min,int $max): int { return (int)$this->number($name,$default,$min,$max); }
    private function number(string $name,float $default,float $min,float $max): float
    {
        $value = $this->settings->get('ai_limits',$name)['value'] ?? $default;
        return max($min,min($max,is_numeric($value) && is_finite((float)$value) ? (float)$value : $default));
    }
}
