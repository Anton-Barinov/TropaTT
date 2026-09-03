<?php
declare(strict_types=1);

namespace Api\Model\Admin;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class OperationalWidgetRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function countsSummary(): array
    {
        return [
            'users_total' => (new QueryBuilder($this->pdo))
                ->from('users')
                ->whereNull('deleted_at')
                ->count(),
            'users_active' => (new QueryBuilder($this->pdo))
                ->from('users')
                ->whereNull('deleted_at')
                ->where('is_active', '=', 1)
                ->count(),
            'projects_active' => (new QueryBuilder($this->pdo))
                ->from('projects')
                ->whereNull('archived_at')
                ->count(),
            'projects_archived' => (new QueryBuilder($this->pdo))
                ->from('projects')
                ->whereNotNull('archived_at')
                ->count(),
            'tasks_active' => $this->buildVisibleTasksQuery()
                ->whereRaw('status_code NOT IN (?, ?)', ['done', 'archived'])
                ->count(),
            'tasks_overdue' => $this->buildVisibleTasksQuery()
                ->whereRaw('status_code NOT IN (?, ?)', ['done', 'archived'])
                ->whereNotNull('due_at')
                ->where('due_at', '<', gmdate('Y-m-d H:i:s'))
                ->count(),
            'tasks_blocked' => $this->buildVisibleTasksQuery()
                ->where('status_code', '=', 'blocked')
                ->count(),
            'notifications_unread_global' => (new QueryBuilder($this->pdo))
                ->from('notifications')
                ->where('is_read', '=', 0)
                ->count(),
            'api_clients_total' => (new QueryBuilder($this->pdo))
                ->from('api_clients')
                ->count(),
            'api_clients_active' => (new QueryBuilder($this->pdo))
                ->from('api_clients')
                ->where('is_active', '=', 1)
                ->count(),
            'api_keys_active' => (new QueryBuilder($this->pdo))
                ->from('api_keys')
                ->whereNull('revoked_at')
                ->count(),
        ];
    }

    public function logsSummary(): array
    {
        $since24h = gmdate('Y-m-d H:i:s', time() - 86400);

        return [
            'request_total_24h' => (new QueryBuilder($this->pdo))
                ->from('request_logs')
                ->where('created_at', '>=', $since24h)
                ->count(),
            'request_5xx_24h' => (new QueryBuilder($this->pdo))
                ->from('request_logs')
                ->where('created_at', '>=', $since24h)
                ->where('status_code', '>=', 500)
                ->count(),
            'auth_failed_24h' => (new QueryBuilder($this->pdo))
                ->from('security_logs')
                ->where('created_at', '>=', $since24h)
                ->whereIn('event_type', ['auth_login_failed', 'auth_rate_limited'])
                ->count(),
            'audit_total_24h' => (new QueryBuilder($this->pdo))
                ->from('audit_logs')
                ->where('created_at', '>=', $since24h)
                ->count(),
        ];
    }

    public function requestMetrics24h(): array
    {
        if (!$this->tableExists('request_logs')) {
            return ['table_exists' => false, 'total' => 0, 'status_5xx' => 0, 'p95_duration_ms' => 0];
        }

        $since24h = gmdate('Y-m-d H:i:s', time() - 86400);
        $rows = (new QueryBuilder($this->pdo))
            ->from('request_logs')
            ->select(['status_code', 'duration_ms'])
            ->where('created_at', '>=', $since24h)
            ->limit(10000)
            ->get();

        $durations = [];
        $status5xx = 0;
        foreach ($rows as $row) {
            if ((int)($row['status_code'] ?? 0) >= 500) {
                $status5xx++;
            }
            $duration = (int)($row['duration_ms'] ?? 0);
            if ($duration >= 0) {
                $durations[] = $duration;
            }
        }
        sort($durations);
        $p95 = 0;
        if ($durations !== []) {
            $index = (int)ceil(count($durations) * 0.95) - 1;
            $p95 = $durations[max(0, min(count($durations) - 1, $index))];
        }

        return [
            'table_exists' => true,
            'total' => count($rows),
            'status_5xx' => $status5xx,
            'p95_duration_ms' => $p95,
        ];
    }

    public function aiMetrics24h(): array
    {
        if (!$this->tableExists('ai_usage_logs')) {
            return ['table_exists' => false, 'requests' => 0, 'errors' => 0, 'timeouts' => 0, 'rate_limited' => 0, 'tokens_total' => 0];
        }

        $since24h = gmdate('Y-m-d H:i:s', time() - 86400);
        $rows = (new QueryBuilder($this->pdo))
            ->from('ai_usage_logs')
            ->select(['status', 'error_code', 'total_tokens'])
            ->where('created_at', '>=', $since24h)
            ->limit(10000)
            ->get();

        $errors = 0;
        $timeouts = 0;
        $rateLimited = 0;
        $tokens = 0;
        foreach ($rows as $row) {
            $status = strtolower((string)($row['status'] ?? ''));
            $errorCode = strtoupper((string)($row['error_code'] ?? ''));
            if ($status !== '' && $status !== 'ok' && $status !== 'success') {
                $errors++;
            }
            if (str_contains($errorCode, 'TIMEOUT')) {
                $timeouts++;
            }
            if (str_contains($errorCode, 'RATE_LIMIT')) {
                $rateLimited++;
            }
            $tokens += (int)($row['total_tokens'] ?? 0);
        }

        return [
            'table_exists' => true,
            'requests' => count($rows),
            'errors' => $errors,
            'timeouts' => $timeouts,
            'rate_limited' => $rateLimited,
            'tokens_total' => $tokens,
        ];
    }

    public function migrationStatus(): array
    {
        $exists = $this->tableExists('migrations');
        if (!$exists) {
            return ['table_exists' => false, 'applied_count' => 0, 'last_migration' => null];
        }

        $count = (new QueryBuilder($this->pdo))
            ->from('migrations')
            ->count();
        $last = $this->lastMigrationKey();

        return [
            'table_exists' => true,
            'applied_count' => $count,
            'last_migration' => $last,
        ];
    }

    public function queueSummary(): array
    {
        return [
            'import' => $this->queueCounters('import_jobs'),
            'export' => $this->queueCounters('export_jobs'),
            'push' => $this->queueCounters('notification_push_queue'),
            'webhook_deliveries_failed_24h' => $this->webhookFailures24h(),
        ];
    }

    public function dbPing(): bool
    {
        try {
            (new QueryBuilder($this->pdo))
                ->from('users')
                ->count();
            return true;
        } catch (\Throwable $e) {
            error_log('[OperationalWidgetRepository::dbPing] ' . $e->getMessage());
            return false;
        }
    }

    private function buildVisibleTasksQuery(): QueryBuilder
    {
        return (new QueryBuilder($this->pdo))
            ->from('tasks')
            ->whereNull('deleted_at')
            ->whereNull('archived_at');
    }

    private function tableExists(string $table): bool
    {
        try {
            (new QueryBuilder($this->pdo))
                ->from($table)
                ->count();
            return true;
        } catch (\Throwable $e) {
            error_log('[OperationalWidgetRepository::tableExists] ' . $e->getMessage());
            return false;
        }
    }

    private function lastMigrationKey(): ?string
    {
        // New schema stores migration identifier in `migration_key`.
        $queries = [
            'migration_key',
            'migration',
        ];

        foreach ($queries as $column) {
            try {
                $value = (new QueryBuilder($this->pdo))
                    ->from('migrations')
                    ->orderBy('id', 'DESC')
                    ->value($column);
                if ($value !== false && $value !== null && $value !== '') {
                    return (string)$value;
                }
            } catch (\Throwable $e) {
                error_log('[OperationalWidgetRepository::lastMigrationKey] ' . $e->getMessage());
                // Try next known variant.
            }
        }

        return null;
    }

    private function queueCounters(string $table): array
    {
        if (!$this->tableExists($table)) {
            return [
                'table_exists' => false,
                'queued' => 0,
                'processing' => 0,
                'retry' => 0,
                'dead_letter' => 0,
                'completed' => 0,
            ];
        }

        $base = (new QueryBuilder($this->pdo))->from($table);
        return [
            'table_exists' => true,
            'queued' => (clone $base)->where('status', '=', 'queued')->count(),
            'processing' => (clone $base)->where('status', '=', 'processing')->count(),
            'retry' => (clone $base)->where('status', '=', 'retry')->count(),
            'dead_letter' => (clone $base)->whereRaw('status = ? OR dead_letter = ?', ['dead_letter', 1])->count(),
            'completed' => (clone $base)->where('status', '=', 'completed')->count(),
        ];
    }

    private function webhookFailures24h(): int
    {
        if (!$this->tableExists('webhook_deliveries')) {
            return 0;
        }
        $since24h = gmdate('Y-m-d H:i:s', time() - 86400);
        return (new QueryBuilder($this->pdo))
            ->from('webhook_deliveries')
            ->where('created_at', '>=', $since24h)
            ->whereRaw('status IN (?, ?)', ['failed', 'error'])
            ->count();
    }
}
