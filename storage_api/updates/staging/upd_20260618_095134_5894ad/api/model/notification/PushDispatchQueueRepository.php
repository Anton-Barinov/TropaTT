<?php
declare(strict_types=1);

namespace Api\Model\Notification;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class PushDispatchQueueRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))->from('notification_push_queue')->insert($payload);
    }

    public function claimNextRunnable(string $now): ?array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, public_id FROM notification_push_queue
                 WHERE dead_letter = 0
                   AND status IN ('queued','retry')
                   AND (next_run_at IS NULL OR next_run_at <= :now)
                   AND locked_at IS NULL
                 ORDER BY created_at ASC, id ASC
                 LIMIT 1"
            );
            $stmt->execute(['now' => $now]);
            $row = $stmt->fetch();
            if (!is_array($row) || (int)($row['id'] ?? 0) <= 0) {
                $this->pdo->commit();
                return null;
            }

            $lock = $this->pdo->prepare('UPDATE notification_push_queue SET locked_at = :locked_at, status = :status, updated_at = :updated_at WHERE id = :id AND locked_at IS NULL');
            $lock->execute([
                'locked_at' => $now,
                'status' => 'processing',
                'updated_at' => $now,
                'id' => (int)$row['id'],
            ]);
            if ((int)$lock->rowCount() <= 0) {
                $this->pdo->rollBack();
                return null;
            }

            $this->pdo->commit();
            return $this->findByPublicId((string)$row['public_id']);
        } catch (\Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return null;
        }
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('notification_push_queue')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function findByPublicId(string $publicId): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('notification_push_queue')
            ->select(['*'])
            ->where('public_id', '=', $publicId)
            ->first();

        return is_array($row) ? $row : null;
    }
}
