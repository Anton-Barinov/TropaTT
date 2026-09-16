<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

/**
 * Give every project a task-key prefix.
 *
 * `TaskHumanReadableKeysMigration` backfilled the projects that existed when it ran,
 * but a project can still be prefix-less afterwards: a legacy row whose backfill was
 * lost (that migration is not transactional), or a row written by any path that did
 * not carry a prefix. That state is not cosmetic. `TaskKeyService::assignNextTaskKey()`
 * used to fall back to the shared `PRJ`, and because the key counter is kept *per
 * project*, the second prefix-less project started again at `PRJ-1` and every task
 * creation in it failed: the insert hit the unique index `uq_tasks_task_key` and the
 * caller got a 500 ("Controller invocation failed" over MCP, "Внутренняя ошибка
 * сервера" over REST). The live reproduction was the project «Разработка контента».
 *
 * Only `projects.task_key_prefix` is written. Keys of tasks that already exist are
 * never touched: `tasks.task_key` is what people quote, search and link to, so a
 * backfill must not rewrite history. A project that already has a prefix is left
 * alone, which makes the migration idempotent and safe to run after the application
 * has already filled one in.
 */
final class ProjectTaskKeyPrefixBackfillMigration implements MigrationInterface
{
    private const RESERVED_PREFIXES = ['TASK', 'SYS', 'API'];
    private const MAX_PREFIX_LENGTH = 10;

    public function key(): string
    {
        return '20260916_000001_project_task_key_prefix_backfill';
    }

    public function description(): string
    {
        return 'Assign a unique task-key prefix to every project that has none';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $projects = $pdo->query(
            "SELECT id, title FROM projects
              WHERE task_key_prefix IS NULL OR TRIM(task_key_prefix) = ''
              ORDER BY id ASC"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($projects === []) {
            return;
        }

        $update = $pdo->prepare('UPDATE projects SET task_key_prefix = :prefix, updated_at = :updated_at WHERE id = :id');
        $now = gmdate('Y-m-d H:i:s');

        foreach ($projects as $project) {
            $prefix = $this->uniquePrefix($pdo, $this->prefixFromTitle((string)($project['title'] ?? '')), (int)$project['id']);
            $update->execute([
                'prefix' => $prefix,
                'updated_at' => $now,
                'id' => (int)$project['id'],
            ]);
        }
    }

    /**
     * The prefix a project title suggests: its first letters and digits, 2-10
     * characters, starting with a letter.
     *
     * Deliberately a copy of `TaskKeyService::generateProjectPrefix()` rather than a
     * call into it: a migration has to keep working against the schema and the code of
     * the release it ships with, without the service container, the repositories or
     * the default settings of a later version.
     */
    private function prefixFromTitle(string $title): string
    {
        $cleaned = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($title))) ?? '';

        if (strlen($cleaned) < 2 || preg_match('/^[A-Z]/', $cleaned) !== 1) {
            return 'PRJ';
        }

        return substr($cleaned, 0, self::MAX_PREFIX_LENGTH);
    }

    /**
     * A prefix no other project holds, suffixing the suggestion until one is free.
     *
     * Excluded by numeric id, never by public_id: a legacy row with an empty
     * public_id would never match the comparison, so two projects could be handed the
     * same prefix and then collide on `uq_projects_task_key_prefix`.
     */
    private function uniquePrefix(PDO $pdo, string $prefix, int $exceptProjectId): string
    {
        if ($this->isFree($pdo, $prefix, $exceptProjectId)) {
            return $prefix;
        }

        for ($suffix = 2; $suffix <= 999; $suffix++) {
            $suffixText = (string)$suffix;
            $candidate = substr($prefix, 0, self::MAX_PREFIX_LENGTH - strlen($suffixText)) . $suffixText;
            if ($this->isFree($pdo, $candidate, $exceptProjectId)) {
                return $candidate;
            }
        }

        // A thousand projects resolving to the same suggestion. Keep the column's
        // width instead of giving up on uniqueness: the leading letter makes the
        // format valid even when the title suggested none.
        for ($attempt = 0; $attempt < 200; $attempt++) {
            $candidate = 'P' . strtoupper(substr(bin2hex(random_bytes(5)), 0, self::MAX_PREFIX_LENGTH - 1));
            if ($this->isFree($pdo, $candidate, $exceptProjectId)) {
                return $candidate;
            }
        }

        // Unreachable in practice. The application repairs a prefix the very first time
        // a task is created in that project, so this cannot become a permanent blank.
        return substr($prefix, 0, self::MAX_PREFIX_LENGTH);
    }

    private function isFree(PDO $pdo, string $prefix, int $exceptProjectId): bool
    {
        if (in_array($prefix, self::RESERVED_PREFIXES, true)) {
            return false;
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM projects WHERE task_key_prefix = :prefix AND id != :id');
        $stmt->execute(['prefix' => $prefix, 'id' => $exceptProjectId]);

        return (int)$stmt->fetchColumn() === 0;
    }
}
