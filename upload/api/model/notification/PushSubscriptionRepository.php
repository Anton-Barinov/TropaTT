<?php
declare(strict_types=1);

namespace Api\Model\Notification;

use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Database\IndexHelper;
use PDO;

final class PushSubscriptionRepository
{
    private bool $schemaEnsured = false;

    public function __construct(private readonly PDO $pdo)
    {
    }

    private function driver(): string
    {
        return (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /** @return array{0:array<int,array<string,mixed>>,1:int,2:int,3:int} */
    public function listByUser(int $userId, array $filters): array
    {
        $this->ensureSchema();
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(200, max(1, (int)($filters['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $base = (new QueryBuilder($this->pdo))
            ->from('notification_push_subscriptions')
            ->where('user_id', '=', $userId);

        $total = $base->count();
        $items = (new QueryBuilder($this->pdo))
            ->from('notification_push_subscriptions')
            ->select([
                'public_id',
                'endpoint',
                'p256dh',
                'auth',
                'user_agent',
                'device_label',
                'is_active',
                'last_error',
                'last_seen_at',
                'created_at',
                'updated_at',
            ])
            ->where('user_id', '=', $userId)
            ->orderBy('updated_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    public function findByPublicIdForUser(string $publicId, int $userId): ?array
    {
        $this->ensureSchema();
        return (new QueryBuilder($this->pdo))
            ->from('notification_push_subscriptions')
            ->select([
                'id',
                'public_id',
                'user_id',
                'endpoint',
                'p256dh',
                'auth',
                'user_agent',
                'device_label',
                'is_active',
                'last_error',
                'last_seen_at',
                'created_at',
                'updated_at',
            ])
            ->where('public_id', '=', $publicId)
            ->where('user_id', '=', $userId)
            ->first();
    }

    public function findByEndpointForUser(string $endpoint, int $userId): ?array
    {
        $this->ensureSchema();
        return (new QueryBuilder($this->pdo))
            ->from('notification_push_subscriptions')
            ->select(['id', 'public_id', 'user_id'])
            ->where('endpoint', '=', $endpoint)
            ->where('user_id', '=', $userId)
            ->first();
    }

    public function create(array $payload): void
    {
        $this->ensureSchema();
        (new QueryBuilder($this->pdo))
            ->from('notification_push_subscriptions')
            ->insert($payload);
    }

    public function updateByPublicIdForUser(string $publicId, int $userId, array $set): bool
    {
        $this->ensureSchema();
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('notification_push_subscriptions')
            ->where('public_id', '=', $publicId)
            ->where('user_id', '=', $userId)
            ->update($set) > 0;
    }

    public function deleteByPublicIdForUser(string $publicId, int $userId): bool
    {
        $this->ensureSchema();
        return (new QueryBuilder($this->pdo))
            ->from('notification_push_subscriptions')
            ->where('public_id', '=', $publicId)
            ->where('user_id', '=', $userId)
            ->delete() > 0;
    }

    public function markInactiveByPublicIdForUser(string $publicId, int $userId, ?string $lastError, string $updatedAt): bool
    {
        $this->ensureSchema();
        return (new QueryBuilder($this->pdo))
            ->from('notification_push_subscriptions')
            ->where('public_id', '=', $publicId)
            ->where('user_id', '=', $userId)
            ->update([
                'is_active' => 0,
                'last_error' => $lastError,
                'updated_at' => $updatedAt,
            ]) > 0;
    }

    public function touchDeliverySuccessByPublicIdForUser(string $publicId, int $userId, string $updatedAt): bool
    {
        $this->ensureSchema();
        return (new QueryBuilder($this->pdo))
            ->from('notification_push_subscriptions')
            ->where('public_id', '=', $publicId)
            ->where('user_id', '=', $userId)
            ->update([
                'is_active' => 1,
                'last_error' => null,
                'last_seen_at' => $updatedAt,
                'updated_at' => $updatedAt,
            ]) > 0;
    }

    /** @return array<int,array<string,mixed>> */
    public function activeByUser(int $userId): array
    {
        $this->ensureSchema();
        return (new QueryBuilder($this->pdo))
            ->from('notification_push_subscriptions')
            ->select([
                'public_id',
                'endpoint',
                'p256dh',
                'auth',
                'user_agent',
                'device_label',
                'is_active',
                'last_error',
            ])
            ->where('user_id', '=', $userId)
            ->where('is_active', '=', 1)
            ->orderBy('updated_at', 'DESC')
            ->get();
    }

    private function ensureSchema(): void
    {
        if ($this->schemaEnsured) {
            return;
        }
        $this->schemaEnsured = true;

        try {
            // AUTOINCREMENT is SQLite-only; MySQL/MariaDB needs AUTO_INCREMENT.
            // Mirror SchemaManager's driver-aware id column instead of duplicating
            // the whole DDL per driver (they would drift apart). The old code
            // hard-coded the SQLite syntax, so every hourly notification sweep
            // logged a schema error on MySQL/MariaDB.
            $id = $this->driver() === 'mysql'
                ? 'INT AUTO_INCREMENT PRIMARY KEY'
                : 'INTEGER PRIMARY KEY AUTOINCREMENT';

            $this->pdo->exec(
                "CREATE TABLE IF NOT EXISTS notification_push_subscriptions (
                    id {$id},
                    public_id VARCHAR(64) UNIQUE,
                    user_id INTEGER,
                    endpoint TEXT,
                    p256dh VARCHAR(1024),
                    auth VARCHAR(1024),
                    user_agent TEXT NULL,
                    device_label VARCHAR(255) NULL,
                    is_active INTEGER DEFAULT 1,
                    last_error TEXT NULL,
                    last_seen_at DATETIME NULL,
                    created_at DATETIME,
                    updated_at DATETIME
                )"
            );
            IndexHelper::createIndexIfNotExists($this->pdo, 'notification_push_subscriptions', 'idx_notif_push_subscriptions_user_active', 'user_id, is_active, updated_at');
            if (!$this->hasColumn('last_error')) {
                $this->pdo->exec('ALTER TABLE notification_push_subscriptions ADD COLUMN last_error TEXT NULL');
            }
        } catch (\Throwable $e) {
            error_log('[PushSubscriptionRepository::ensureSchema] DB exec: ' . $e->getMessage());
            // Keep fail-safe behavior for already managed schema or restricted DB modes.
        }
    }

    private function hasColumn(string $name): bool
    {
        try {
            if ($this->driver() === 'mysql') {
                // SHOW COLUMNS ... LIKE ? is a SHOW command: MySQL/MariaDB do
                // not accept prepared-statement parameters in SHOW statements,
                // so with PDO::ATTR_EMULATE_PREPARES=false the server chokes on
                // the placeholder (SQLSTATE 1064 near '?'). Use the standard
                // information_schema introspection instead (same as IndexHelper).
                $stmt = $this->pdo->prepare(
                    'SELECT 1 FROM information_schema.columns
                     WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column LIMIT 1'
                );
                $stmt->execute(['table' => 'notification_push_subscriptions', 'column' => $name]);
                return (bool)$stmt->fetchColumn();
            }

            $columns = $this->pdo->query('PRAGMA table_info(notification_push_subscriptions)');
            if ($columns === false) {
                return true; // Unknown introspection: avoid a destructive ALTER guess.
            }
            foreach ($columns->fetchAll(PDO::FETCH_ASSOC) as $column) {
                if ((string)($column['name'] ?? '') === $name) {
                    return true;
                }
            }
            return false;
        } catch (\Throwable $e) {
            error_log('[PushSubscriptionRepository::hasColumn] ' . $e->getMessage());
            return true; // Fail-safe: assume the column exists rather than guessing DDL.
        }
    }
}
