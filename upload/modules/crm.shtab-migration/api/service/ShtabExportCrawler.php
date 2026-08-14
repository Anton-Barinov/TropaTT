<?php
declare(strict_types=1);

namespace Module\Crm\ShtabMigration\Service;

use Module\Crm\ShtabMigration\Repository\ShtabMigrationRepository;
use RuntimeException;

final class ShtabExportCrawler
{
    public function __construct(private readonly ShtabExportParser $parser, private readonly ShtabMigrationRepository $repo) {}

    /** @return array<string,mixed> */
    public function crawl(array $job, ?callable $heartbeat = null): array
    {
        $scope = (array)($job['source_scope'] ?? []);
        $type = $this->normalizeType((string)($scope['entity_type'] ?? 'auto'));
        $rows = $this->parser->parse((string)$job['source_file_path'], (string)$job['source_file_name'], 100000);
        $stats = ['rows' => 0, 'items' => 0, 'users' => 0, 'tags' => 0, 'collisions' => 0, 'warnings' => [], 'entity_types' => []];
        $rows = $this->orderRows($rows, $type);
        $seen = [];

        foreach ($rows as $row) {
            if ($heartbeat !== null && !$heartbeat()) {
                throw new RuntimeException('SHTAB_JOB_LEASE_LOST');
            }
            $entity = $type === 'auto' ? $this->normalizeType((string)($row['entity_type'] ?? $row['type'] ?? '')) : $type;
            if ($entity === 'auto') {
                $stats['warnings'][] = 'Row ' . ($row['_row_number'] ?? '?') . ' has no entity_type and was skipped.';
                continue;
            }

            $source = $this->sourceId($entity, $row);
            $checksum = $this->rowChecksum($row);
            $stats['rows']++;
            $stats['items']++;
            $stats['entity_types'][$entity] = ($stats['entity_types'][$entity] ?? 0) + 1;

            if (isset($seen[$entity][$source]) && $seen[$entity][$source] !== $checksum) {
                // A source without a stable ID may represent two distinct rows
                // with the same identity fields. Do not let upsertItem() silently
                // replace one with the other; preserve the row as an explicit
                // failed item that an operator can fix in the source export.
                $collisionSource = $this->boundedSourceId($source . ':collision-' . substr($checksum, 0, 16));
                $this->item(
                    $job,
                    $entity,
                    $collisionSource,
                    $this->first($row, ['parent_id', 'parent_task_id', 'parent', 'project_id', 'project']),
                    $row,
                    'failed',
                    'SOURCE_ID_COLLISION',
                    'Two export rows resolve to the same source identity but contain different data.'
                );
                $stats['collisions']++;
                $stats['warnings'][] = 'Row ' . ($row['_row_number'] ?? '?') . ' was not imported because its source identity collides with another row.';
                continue;
            }
            $seen[$entity][$source] = $checksum;

            $parent = $this->first($row, ['parent_id', 'parent_task_id', 'parent', 'project_id', 'project']);
            $this->item($job, $entity, $source, $parent, $row);
            if ($entity === 'user') {
                $this->repo->upsertUserMapping((int)$job['connection_id'], $row);
                $stats['users']++;
            }
            if (in_array($entity, ['task', 'subtask'], true)) {
                foreach ($this->split($this->first($row, ['tags', 'labels'])) as $tag) {
                    $tagId = hash('sha256', 'tag:' . mb_strtolower($tag));
                    $this->item($job, 'tag', $tagId, $source, ['id' => $tagId, 'name' => $tag, '_task_id' => $source]);
                    $stats['tags']++;
                }
            }
        }
        if ($stats['rows'] === 0) {
            $stats['warnings'][] = 'No importable rows were found in the export.';
        }
        return $stats;
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private function orderRows(array $rows, string $forcedType = 'auto'): array
    {
        $taskRows = [];
        $otherRows = [];
        foreach ($rows as $row) {
            $type = $forcedType !== 'auto' ? $forcedType : $this->normalizeType((string)($row['entity_type'] ?? $row['type'] ?? ''));
            if ($type === 'task') {
                $taskRows[] = $row;
            } else {
                $otherRows[] = $row;
            }
        }
        $bySource = [];
        foreach ($taskRows as $index => $row) {
            $bySource[$this->sourceId('task', $row)][] = $index;
        }
        $visited = [];
        $ordered = [];
        $visit = function (int $index) use (&$visit, &$visited, &$ordered, &$taskRows, $bySource): void {
            if (isset($visited[$index])) {
                return;
            }
            $visited[$index] = true;
            $parent = $this->first($taskRows[$index], ['parent_id', 'parent_task_id', 'parent']);
            if ($parent !== null) {
                $parent = $this->taskReference($parent);
                foreach ($bySource[$parent] ?? [] as $parentIndex) {
                    $visit($parentIndex);
                }
            }
            $ordered[] = $taskRows[$index];
        };
        foreach (array_keys($taskRows) as $index) {
            $visit((int)$index);
        }
        return array_merge($otherRows, $ordered);
    }

    private function taskReference(string $value): string
    {
        $value = trim($value);
        if (str_starts_with($value, 'task:')) return $value;
        if (str_starts_with($value, 'subtask:')) return 'task:' . substr($value, 8);
        return 'task:' . $value;
    }

    private function item(array $job, string $type, string $source, ?string $parent, array $payload, string $status = 'pending', ?string $errorCode = null, ?string $errorMessage = null): void
    {
        $payload = $this->redact($payload);
        $this->repo->upsertItem((int)$job['id'], $type, $source, [
            'source_parent_id' => $parent,
            'status' => $status,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'checksum' => $this->rowChecksum($payload),
            'payload_json' => $payload,
        ]);
    }

    private function rowChecksum(array $row): string
    {
        unset($row['_row_number']);
        ksort($row);
        return hash('sha256', (string)json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function sourceId(string $type, array $row): string
    {
        foreach (['id', 'external_id', 'gid', 'uuid', 'key'] as $key) {
            $value = trim((string)($row[$key] ?? ''));
            if ($value !== '') return $this->boundedSourceId($type . ':' . $value);
        }
        $identity = [];
        foreach (['name', 'title', 'subject', 'project_id', 'project', 'parent_id', 'parent_task_id', 'parent', 'email', 'user_id', 'task_id', 'url', 'link'] as $key) {
            $value = trim((string)($row[$key] ?? ''));
            if ($value !== '') $identity[$key] = $value;
        }
        if ($type === 'comment' && trim((string)($row['text'] ?? $row['comment'] ?? $row['body'] ?? '')) !== '') {
            $identity['text'] = trim((string)($row['text'] ?? $row['comment'] ?? $row['body']));
        }
        if ($type === 'file' && trim((string)($row['name'] ?? $row['filename'] ?? '')) !== '') {
            $identity['filename'] = trim((string)($row['name'] ?? $row['filename']));
        }
        if ($identity === []) {
            foreach ($row as $key => $value) {
                if (str_starts_with((string)$key, '_') || in_array($key, ['description', 'desc', 'text', 'status', 'priority', 'due_at', 'due_date', 'updated_at', 'created_at', 'tags', 'labels'], true) || is_array($value)) continue;
                if (trim((string)$value) !== '') $identity[$key] = trim((string)$value);
            }
        }
        return $this->boundedSourceId($type . ':row-' . hash('sha256', (string)json_encode($identity, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
    }

    private function boundedSourceId(string $source): string
    {
        return mb_strlen($source) <= 191 ? $source : substr($source, 0, 32) . '-hash-' . hash('sha256', $source);
    }

    private function redact(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (preg_match('/pass(word)?|api[_-]?key|access[_-]?token|refresh[_-]?token|authorization|cookie|secret/i', (string)$key) === 1) {
                $payload[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $payload[$key] = $this->redact($value);
            }
        }
        return $payload;
    }

    private function normalizeType(string $type): string
    {
        $type = mb_strtolower(trim($type));
        $normalized = match ($type) {
            'workspace', 'workspaces', 'пространство' => 'workspace',
            'organization', 'organisation', 'организация' => 'organization',
            'team', 'команда' => 'team',
            'project', 'projects', 'проект' => 'project',
            'task', 'tasks', 'задача', 'subtask', 'subtasks', 'подзадача' => 'task',
            'tag', 'tags', 'label', 'метка', 'тег' => 'tag',
            'user', 'users', 'member', 'пользователь' => 'user',
            'comment', 'comments', 'discussion', 'комментарий' => 'comment',
            'contact', 'contacts', 'контакт' => 'contact',
            'deal', 'deals', 'сделка' => 'deal',
            'event', 'events', 'meeting', 'событие', 'встреча' => 'event',
            'file', 'files', 'attachment', 'вложение' => 'file',
            '' => 'auto',
            default => $type,
        };
        return mb_substr($normalized, 0, 64);
    }

    private function first(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = trim((string)($row[$key] ?? ''));
            if ($value !== '') return $value;
        }
        return null;
    }

    /** @return array<int,string> */
    private function split(?string $value): array
    {
        if ($value === null || trim($value) === '') return [];
        return array_values(array_filter(array_map('trim', preg_split('/[,;|\n]+/', $value) ?: []), static fn(string $v): bool => $v !== ''));
    }
}
