<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Task\TaskKeyCounterRepository;
use Api\Model\Project\ProjectRepository;

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
                $candidate = substr($base, 0, 8) . (string)$suffix;
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
     * @return array{task_key: string, task_key_prefix: string, task_sequence_number: int}|null
     */
    public function assignNextTaskKey(?int $projectId, ?string $projectPrefix = null): ?array
    {
        if ($projectId !== null && $projectId > 0) {
            $prefix = $projectPrefix ?? 'PRJ';
            $this->counters->ensureProjectCounter($projectId, $prefix);
            return $this->counters->nextForProject($projectId, $prefix);
        }

        // Global scope
        $prefix = $projectPrefix ?? self::GLOBAL_PREFIX;
        $this->counters->ensureGlobalCounter($prefix);
        return $this->counters->nextGlobal($prefix);
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
