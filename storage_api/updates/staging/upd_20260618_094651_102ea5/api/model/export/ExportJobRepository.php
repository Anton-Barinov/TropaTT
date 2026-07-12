<?php
declare(strict_types=1);

namespace Api\Model\Export;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class ExportJobRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(array $filters, ?int $actorUserId = null, bool $actorIsRoot = false): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->buildListQuery($filters, $actorUserId, $actorIsRoot)->count();
        $items = $this->buildListQuery($filters, $actorUserId, $actorIsRoot)
            ->select([
                'ej.public_id',
                'ej.type',
                'ej.status',
                'ej.attempts',
                'ej.next_run_at',
                'ej.locked_at',
                'ej.started_at',
                'ej.finished_at',
                'ej.last_error',
                'ej.dead_letter',
                'ej.payload',
                'ej.result',
                'ej.created_at',
                'ej.updated_at',
                'u.public_id AS user_public_id',
                'u.login AS user_login',
                'u.full_name AS user_full_name',
            ])
            ->orderBy('ej.created_at', 'DESC')
            ->orderBy('ej.public_id', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildListQuery(array $filters, ?int $actorUserId, bool $actorIsRoot): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('export_jobs ej')
            ->leftJoin('users u', 'u.id', '=', 'ej.user_id');

        if (!$actorIsRoot && $actorUserId !== null && $actorUserId > 0) {
            $query->where('ej.user_id', '=', $actorUserId);
        }

        if (!empty($filters['type'])) {
            $query->where('ej.type', '=', (string)$filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where('ej.status', '=', (string)$filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . (string)$filters['search'] . '%';
            $query->whereRaw('(ej.public_id LIKE ? OR ej.type LIKE ?)', [$search, $search]);
        }

        return $query;
    }

    public function findByPublicId(string $publicId): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('export_jobs ej')
            ->leftJoin('users u', 'u.id', '=', 'ej.user_id')
            ->select([
                'ej.id',
                'ej.user_id',
                'ej.public_id',
                'ej.type',
                'ej.status',
                'ej.attempts',
                'ej.next_run_at',
                'ej.locked_at',
                'ej.started_at',
                'ej.finished_at',
                'ej.last_error',
                'ej.dead_letter',
                'ej.payload',
                'ej.result',
                'ej.created_at',
                'ej.updated_at',
                'u.public_id AS user_public_id',
                'u.login AS user_login',
                'u.full_name AS user_full_name',
            ])
            ->where('ej.public_id', '=', $publicId)
            ->first();

        return $row ?: null;
    }

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('export_jobs')
            ->insert($payload);
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('export_jobs')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function claimNextRunnable(string $now): ?array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, public_id FROM export_jobs
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

            $id = (int)$row['id'];
            $lock = $this->pdo->prepare('UPDATE export_jobs SET locked_at = :locked_at, status = :status, started_at = COALESCE(started_at, :started_at), updated_at = :updated_at WHERE id = :id AND locked_at IS NULL');
            $lock->execute([
                'locked_at' => $now,
                'status' => 'processing',
                'started_at' => $now,
                'updated_at' => $now,
                'id' => $id,
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
}
