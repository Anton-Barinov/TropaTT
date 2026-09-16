<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Task\TaskKeyCounterRepository;
use Api\Model\Project\ProjectRepository;
use Api\System\Library\Support\AppLog;
use PDOException;

final class TaskKeyService
{
    private const RESERVED_PREFIXES = ['TASK', 'SYS', 'API'];
    private const GLOBAL_PREFIX = 'TASK';

    public function __construct(
        private readonly TaskKeyCounterRepository $counters,
        private readonly ProjectRepository $projects,
    )
    {
    }

    /**
     * Normalize a raw prefix to uppercase, trimmed, valid format.
     */
    public function normalizePrefix(string $raw): string
    {
        $prefix = trim($raw);
        $prefix = strtoupper($prefix);
        $prefix = preg_replace('/[^A-Z0-9]/', '', $prefix) ?? '';

        return $prefix;
    }

    /**
     * Check if the prefix matches the valid format: 2-10 chars, starts with letter.
     */
    public function isValidPrefix(string $prefix): bool
    {
        if ($prefix === '') {
            return false;
        }

        return preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $prefix) === 1;
    }

    /**
     * Generate a project prefix from the project title.
     */
    public function generateProjectPrefix(string $projectTitle): string
    {
        $title = trim($projectTitle);
        if ($title === '') {
            return 'PRJ';
        }

        $cleaned = preg_replace('/[^A-Z0-9]/', '', strtoupper($title));

        if ($cleaned === '' || strlen($cleaned) < 2 || !preg_match('/^[A-Z]/', $cleaned)) {
            return 'PRJ';
        }

        return substr($cleaned, 0, 10);
    }

    /**
     * Ensure a unique project prefix, adding numeric suffix if needed.
     */
    public function ensureUniqueProjectPrefix(string $prefix, ?string $exceptProjectPublicId = null): string
    {
        if ($this->projects->taskKeyPrefixExists($prefix, $exceptProjectPublicId)) {
            $base = $prefix;
            $suffix = 2;

            while ($suffix <= 999) {
                // The whole prefix has to fit the VARCHAR(10) that holds it: a
                // three-digit suffix on a ten-character base used to overflow it.
                $suffixText = (string)$suffix;
                $candidate = substr($base, 0, 10 - strlen($suffixText)) . $suffixText;
                if (!in_array($candidate, self::RESERVED_PREFIXES, true) && !$this->projects->taskKeyPrefixExists($candidate, $exceptProjectPublicId)) {
                    return $candidate;
                }
                $suffix++;
            }
        }

        return $prefix;
    }

    /**
     * Check if a prefix is reserved.
     */
    public function isReservedPrefix(string $prefix): bool
    {
        return in_array(strtoupper($prefix), self::RESERVED_PREFIXES, true);
    }

    /**
     * Assign the next task key for a project or global scope.
     *
     * A project without a stored prefix no longer falls back to the shared 'PRJ'
     * string. The counter is kept per project, so a second prefix-less project would
     * start again at PRJ-1 and hit the unique index `uq_tasks_task_key` while creating
     * its very first task - the 500 this method used to cause. The prefix is now
     * derived from the project title, made unique and *stored on the project*, so
     * every later task, key and card in that project agrees on it.
     *
     * @return array{task_key: string, task_key_prefix: string, task_sequence_number: int}|null
     */
    public function assignNextTaskKey(?int $projectId, ?string $projectPrefix = null): ?array
    {
        if ($projectId !== null && $projectId > 0) {
            $prefix = $projectPrefix ?? $this->projectPrefix($projectId);
            $this->counters->ensureProjectCounter($projectId, $prefix);
            return $this->counters->nextForProject($projectId, $prefix);
        }

        // Global scope
        $prefix = $projectPrefix ?? self::GLOBAL_PREFIX;
        $this->counters->ensureGlobalCounter($prefix);
        return $this->counters->nextGlobal($prefix);
    }

    /**
     * The prefix a project's tasks must be keyed with, generating and persisting one
     * when the project has none yet.
     *
     * Called on the task-creation path, so it has to be safe under concurrency: the
     * prefix is checked for uniqueness, then written, and the unique index on
     * `projects.task_key_prefix` decides the race. A losing writer adopts whatever the
     * winner stored (so both requests key their tasks identically) or suffixes its own
     * candidate and tries again.
     */
    private function projectPrefix(int $projectId): string
    {
        $stored = trim((string)($this->projects->taskKeyPrefixById($projectId) ?? ''));
        if ($stored !== '') {
            return $stored;
        }

        $project = $this->projects->findById($projectId);
        if ($project === null) {
            // No project row at all (nothing to persist onto): keep the historical
            // fallback rather than failing the insert outright.
            return 'PRJ';
        }

        $exceptPublicId = (string)($project['public_id'] ?? '');
        $base = $this->generateProjectPrefix((string)($project['title'] ?? ''));

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = $this->ensureUniqueProjectPrefix($base, $exceptPublicId);

            try {
                $this->projects->setTaskKeyPrefixById($projectId, $candidate);

                return $candidate;
            } catch (PDOException $e) {
                if (!$this->isTaskKeyPrefixDuplicate($e)) {
                    throw $e;
                }

                // Another request took the prefix between the check and the write.
                // If this project won the race, adopt the stored value; otherwise
                // suffix the base and try again.
                $nowStored = trim((string)($this->projects->taskKeyPrefixById($projectId) ?? ''));
                if ($nowStored !== '') {
                    return $nowStored;
                }

                AppLog::warning('[TaskKeyService::projectPrefix] prefix taken concurrently, retrying: ' . $candidate);
                $base = $candidate;
            }
        }

        return $this->ensureUniqueProjectPrefix($base, $exceptPublicId);
    }

    private function isTaskKeyPrefixDuplicate(PDOException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return (string)$exception->getCode() === '23000'
            && (str_contains($message, 'task_key_prefix') || str_contains($message, 'uq_projects_task_key_prefix'));
    }

    /**
     * Parse a task key string into prefix and sequence number.
     *
     * @return array{prefix: string, number: int}|null
     */
    public function parseTaskKey(string $raw): ?array
    {
        $normalized = strtoupper(trim($raw));

        if (preg_match('/^([A-Z][A-Z0-9]{1,9})-([1-9][0-9]*)$/', $normalized, $matches)) {
            return [
                'prefix' => (string)$matches[1],
                'number' => (int)$matches[2],
            ];
        }

        return null;
    }

    /**
     * Normalize a raw task key string.
     */
    public function normalizeTaskKey(string $raw): string
    {
        return strtoupper(trim($raw));
    }
}
