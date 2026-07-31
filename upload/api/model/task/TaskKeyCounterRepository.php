<?php
declare(strict_types=1);

namespace Api\Model\Task;

use PDO;

final class TaskKeyCounterRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Get next sequence number for a project scope (transaction-safe with FOR UPDATE).
     * @return array{task_key: string, task_key_prefix: string, task_sequence_number: int}
     */
    public function nextForProject(int $projectId, string $prefix): array
    {
        $scopeKey = 'project:' . $projectId;

        return $this->nextInTransaction($scopeKey, 'project', $projectId, $prefix);
    }

    /**
     * Get next global sequence number.
     * @return array{task_key: string, task_key_prefix: string, task_sequence_number: int}
     */
    public function nextGlobal(string $prefix = 'TASK'): array
    {
        return $this->nextInTransaction('global', 'global', null, $prefix);
    }

    /**
     * Ensure a project counter exists.
     */
    public function ensureProjectCounter(int $projectId, string $prefix): void
    {
        $scopeKey = 'project:' . $projectId;
        $now = gmdate('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO task_key_counters (scope_key, scope_type, project_id, prefix, current_value, created_at, updated_at)
             VALUES (:scope_key, :scope_type, :project_id, :prefix, 0, :created_at, :updated_at)'
        );
        $stmt->execute([
            'scope_key' => $scopeKey,
            'scope_type' => 'project',
            'project_id' => $projectId,
            'prefix' => $prefix,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Ensure the global counter exists.
     */
    public function ensureGlobalCounter(string $prefix = 'TASK'): void
    {
        $now = gmdate('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO task_key_counters (scope_key, scope_type, project_id, prefix, current_value, created_at, updated_at)
             VALUES (\'global\', \'global\', NULL, :prefix, 0, :created_at, :updated_at)'
        );
        $stmt->execute([
            'prefix' => $prefix,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Rebuild counters from actual max sequence numbers in tasks table.
     */
    public function rebuildCounters(): void
    {
        // Update project counters
        $projectMaxSeq = $this->pdo->query("
            SELECT t.project_id, p.task_key_prefix, MAX(t.task_sequence_number) AS max_seq
            FROM tasks t
            INNER JOIN projects p ON p.id = t.project_id
            WHERE t.project_id IS NOT NULL AND t.task_sequence_number IS NOT NULL
            GROUP BY t.project_id, p.task_key_prefix
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $now = gmdate('Y-m-d H:i:s');

        foreach ($projectMaxSeq as $row) {
            $projectId = (int)$row['project_id'];
            $prefix = (string)($row['task_key_prefix'] ?? 'PRJ');
            $maxSeq = (int)($row['max_seq'] ?? 0);

            $this->upsertCounter('project:' . $projectId, 'project', $projectId, $prefix, $maxSeq, $now);
        }

        // Update global counter
        $globalMax = $this->pdo->query("
            SELECT MAX(task_sequence_number) FROM tasks WHERE task_key_prefix = 'TASK' AND task_sequence_number IS NOT NULL
        ")->fetchColumn();

        $this->upsertCounter('global', 'global', null, 'TASK', (int)$globalMax, $now);
    }

    /**
     * @return array{task_key: string, task_key_prefix: string, task_sequence_number: int}
     */
    private function nextInTransaction(string $scopeKey, string $scopeType, ?int $projectId, string $prefix): array
    {
        $now = gmdate('Y-m-d H:i:s');

        $this->pdo->beginTransaction();
        try {
            // Ensure counter row exists
            $stmt = $this->pdo->prepare(
                'INSERT IGNORE INTO task_key_counters (scope_key, scope_type, project_id, prefix, current_value, created_at, updated_at)
                 VALUES (:scope_key, :scope_type, :project_id, :prefix, 0, :created_at, :updated_at)'
            );
            $stmt->execute([
                'scope_key' => $scopeKey,
                'scope_type' => $scopeType,
                'project_id' => $projectId,
                'prefix' => $prefix,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // SELECT ... FOR UPDATE to lock the row
            $stmt = $this->pdo->prepare(
                'SELECT id, current_value FROM task_key_counters WHERE scope_key = :scope_key FOR UPDATE'
            );
            $stmt->execute(['scope_key' => $scopeKey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $currentValue = $row !== false ? (int)$row['current_value'] : 0;
            $nextValue = $currentValue + 1;
            $counterId = $row !== false ? (int)$row['id'] : null;

            // Update counter
            if ($counterId !== null) {
                $stmt = $this->pdo->prepare(
                    'UPDATE task_key_counters SET current_value = :next_value, prefix = :prefix, updated_at = :updated_at WHERE id = :id'
                );
                $stmt->execute([
                    'next_value' => $nextValue,
                    'prefix' => $prefix,
                    'updated_at' => $now,
                    'id' => $counterId,
                ]);
            }

            $this->pdo->commit();

            $taskKey = $prefix . '-' . $nextValue;

            return [
                'task_key' => $taskKey,
                'task_key_prefix' => $prefix,
                'task_sequence_number' => $nextValue,
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function upsertCounter(string $scopeKey, string $scopeType, ?int $projectId, string $prefix, int $currentValue, string $now): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO task_key_counters (scope_key, scope_type, project_id, prefix, current_value, created_at, updated_at)
             VALUES (:scope_key, :scope_type, :project_id, :prefix, :current_value, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE current_value = GREATEST(current_value, :current_value2), prefix = :prefix2, updated_at = :updated_at2'
        );
        $stmt->execute([
            'scope_key' => $scopeKey,
            'scope_type' => $scopeType,
            'project_id' => $projectId,
            'prefix' => $prefix,
            'current_value' => $currentValue,
            'created_at' => $now,
            'updated_at' => $now,
            'current_value2' => $currentValue,
            'prefix2' => $prefix,
            'updated_at2' => $now,
        ]);
    }
}
