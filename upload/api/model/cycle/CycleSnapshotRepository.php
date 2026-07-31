<?php
declare(strict_types=1);

namespace Api\Model\Cycle;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class CycleSnapshotRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function createOrUpdateDailySnapshot(int $cycleId, string $date, array $summary): array
    {
        $existing = (new QueryBuilder($this->pdo))
            ->from('cycle_snapshots')
            ->where('cycle_id', '=', $cycleId)
            ->where('snapshot_date', '=', $date)
            ->first();

        if ($existing) {
            (new QueryBuilder($this->pdo))
                ->from('cycle_snapshots')
                ->where('id', '=', (int)$existing['id'])
                ->update([
                    'total_tasks' => (int)($summary['total_tasks'] ?? 0),
                    'completed_tasks' => (int)($summary['completed_tasks'] ?? 0),
                    'open_tasks' => (int)($summary['open_tasks'] ?? 0),
                    'overdue_tasks' => (int)($summary['overdue_tasks'] ?? 0),
                    'unassigned_tasks' => (int)($summary['unassigned_tasks'] ?? 0),
                    'payload_json' => isset($summary['payload']) ? json_encode($summary['payload']) : null,
                ]);

            return $existing;
        }

        $publicId = \Api\System\Library\Support\Ulid::generate('csn');
        $payload = [
            'public_id' => $publicId,
            'cycle_id' => $cycleId,
            'snapshot_date' => $date,
            'total_tasks' => (int)($summary['total_tasks'] ?? 0),
            'completed_tasks' => (int)($summary['completed_tasks'] ?? 0),
            'open_tasks' => (int)($summary['open_tasks'] ?? 0),
            'overdue_tasks' => (int)($summary['overdue_tasks'] ?? 0),
            'unassigned_tasks' => (int)($summary['unassigned_tasks'] ?? 0),
            'payload_json' => isset($summary['payload']) ? json_encode($summary['payload']) : null,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ];

        (new QueryBuilder($this->pdo))
            ->from('cycle_snapshots')
            ->insert($payload);

        return $payload;
    }

    public function listByCycleId(int $cycleId): array
    {
        return (new QueryBuilder($this->pdo))
            ->from('cycle_snapshots')
            ->where('cycle_id', '=', $cycleId)
            ->orderBy('snapshot_date', 'ASC')
            ->get();
    }
}
