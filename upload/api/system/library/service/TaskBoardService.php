<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Status\StatusRepository;
use Api\Model\Task\TaskRepository;
use Api\System\Library\Language\LanguageManager;
use Api\System\Library\Language\TranslatableTrait;
use Api\System\Library\Security\HtmlSanitizer;

final class TaskBoardService
{
    use TranslatableTrait;

    public function __construct(
        private readonly TaskRepository $tasks,
        private readonly StatusRepository $statuses,
        private readonly TaskService $taskService,
        LanguageManager $lang,
        private readonly ?HtmlSanitizer $htmlSanitizer = null
    ) {
        $this->lang = $lang;
    }

    public function board(array $filters, array $actor): array
    {
        $limit = min(1000, max(1, (int)($filters['limit'] ?? 500)));
        $items = $this->tasks->boardItems(
            $filters,
            (int)($actor['id'] ?? 0),
            (bool)($actor['is_root'] ?? false),
            $limit
        );

        $columns = $this->loadColumns();
        $swimlaneBy = $this->normalizeSwimlane((string)($filters['swimlane_by'] ?? 'none'));

        $board = $this->buildBoard($columns, $items, $swimlaneBy);

        return [
            'board' => $board,
            'meta' => [
                'filters' => [
                    'project_public_id' => (string)($filters['project_public_id'] ?? ''),
                    'search' => (string)($filters['search'] ?? ''),
                    'status' => (string)($filters['status'] ?? ''),
                    'priority' => (string)($filters['priority'] ?? ''),
                    'swimlane_by' => $swimlaneBy,
                ],
                'limit' => $limit,
                'total' => count($items),
            ],
        ];
    }

    public function move(string $taskPublicId, array $input, array $actor): array|string|null
    {
        $task = $this->taskService->get($taskPublicId, $actor);
        if (!$task) {
            return null;
        }

        $currentRowVersion = (int)($task['row_version'] ?? 0);
        if (isset($input['row_version']) && (int)$input['row_version'] !== $currentRowVersion) {
            return 'ROW_VERSION_CONFLICT';
        }

        $statusCode = trim((string)($input['to_status'] ?? ''));
        if ($statusCode === '' && !empty($input['to_status_public_id'])) {
            $status = $this->statuses->findByPublicId((string)$input['to_status_public_id']);
            if ($status && (string)($status['scope'] ?? '') === 'task') {
                $statusCode = (string)$status['code'];
            }
        }

        if ($statusCode === '') {
            return 'STATUS_REQUIRED';
        }

        if (!$this->isAllowedStatus($statusCode)) {
            return 'INVALID_STATUS';
        }

        if ($this->isWipLimitExceeded($statusCode, $task)) {
            return 'WIP_LIMIT_EXCEEDED';
        }

        $updated = $this->taskService->update(
            $taskPublicId,
            ['status' => $statusCode],
            (int)($actor['id'] ?? 0),
            $actor
        );

        if (!$updated) {
            return null;
        }

        return $updated;
    }

    /** @return array<int,array{code:string,title:string,color:string,sort_order:int}> */
    private function loadColumns(): array
    {
        [$items] = $this->statuses->list([
            'scope' => 'task',
            'is_active' => 1,
            'page' => 1,
            'limit' => 200,
        ]);

        if ($items === []) {
            return [
                ['code' => 'new', 'title' => $this->t('task/messages.status_new'), 'color' => '#64748b', 'sort_order' => 10],
                ['code' => 'in_progress', 'title' => $this->t('task/messages.status_in_progress'), 'color' => '#0ea5e9', 'sort_order' => 20],
                ['code' => 'blocked', 'title' => $this->t('task/messages.status_blocked'), 'color' => '#ef4444', 'sort_order' => 30],
                ['code' => 'done', 'title' => $this->t('task/messages.status_done'), 'color' => '#22c55e', 'sort_order' => 40],
            ];
        }

        $columns = [];
        foreach ($items as $item) {
            $columns[] = [
                'code' => (string)$item['code'],
                'title' => (string)$item['title'],
                'color' => (string)($item['color'] ?? '#64748b'),
                'sort_order' => (int)($item['sort_order'] ?? 100),
            ];
        }

        usort($columns, static fn(array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);

        return $columns;
    }

    private function normalizeSwimlane(string $raw): string
    {
        $value = strtolower(trim($raw));
        return in_array($value, ['none', 'assignee', 'priority', 'project'], true) ? $value : 'none';
    }

    /** @param array<int,array<string,mixed>> $columns */
    /** @param array<int,array<string,mixed>> $items */
    private function buildBoard(array $columns, array $items, string $swimlaneBy): array
    {
        if ($swimlaneBy === 'none') {
            return [
                'mode' => 'columns',
                'columns' => $this->buildColumnsWithTasks($columns, $items),
                'total_tasks' => count($items),
            ];
        }

        $lanes = [];
        foreach ($items as $item) {
            [$laneKey, $laneTitle] = $this->laneFromTask($item, $swimlaneBy);
            if (!isset($lanes[$laneKey])) {
                $lanes[$laneKey] = [
                    'key' => $laneKey,
                    'title' => $laneTitle,
                    'columns' => $this->buildColumnsWithTasks($columns, []),
                ];
            }

            $statusCode = (string)($item['status_code'] ?? 'new');
            if (!isset($lanes[$laneKey]['columns'][$statusCode])) {
                $lanes[$laneKey]['columns'][$statusCode] = [
                    'status_code' => $statusCode,
                    'title' => $statusCode,
                    'color' => '#64748b',
                    'sort_order' => 999,
                    'total' => 0,
                    'tasks' => [],
                ];
            }

            $lanes[$laneKey]['columns'][$statusCode]['tasks'][] = $this->taskCard($item);
            $lanes[$laneKey]['columns'][$statusCode]['total']++;
        }

        $resultLanes = [];
        foreach ($lanes as $lane) {
            $columnsList = array_values($lane['columns']);
            usort($columnsList, static fn(array $a, array $b): int => (int)$a['sort_order'] <=> (int)$b['sort_order']);
            $lane['columns'] = $columnsList;
            $lane['total'] = array_sum(array_map(static fn(array $c): int => (int)$c['total'], $columnsList));
            $resultLanes[] = $lane;
        }

        usort($resultLanes, static fn(array $a, array $b): int => strcmp((string)$a['title'], (string)$b['title']));

        return [
            'mode' => 'columns+swimlanes',
            'swimlane_by' => $swimlaneBy,
            'swimlanes' => $resultLanes,
            'total_tasks' => count($items),
        ];
    }

    /** @param array<int,array<string,mixed>> $columns */
    /** @param array<int,array<string,mixed>> $items */
    private function buildColumnsWithTasks(array $columns, array $items): array
    {
        $mapped = [];
        foreach ($columns as $column) {
            $code = (string)$column['code'];
            $mapped[$code] = [
                'status_code' => $code,
                'title' => (string)$column['title'],
                'color' => (string)$column['color'],
                'sort_order' => (int)$column['sort_order'],
                'total' => 0,
                'tasks' => [],
            ];
        }

        foreach ($items as $item) {
            $statusCode = (string)($item['status_code'] ?? 'new');
            if (!isset($mapped[$statusCode])) {
                $mapped[$statusCode] = [
                    'status_code' => $statusCode,
                    'title' => $statusCode,
                    'color' => '#64748b',
                    'sort_order' => 999,
                    'total' => 0,
                    'tasks' => [],
                ];
            }

            $mapped[$statusCode]['tasks'][] = $this->taskCard($item);
            $mapped[$statusCode]['total']++;
        }

        return $mapped;
    }

    /** @param array<string,mixed> $item */
    private function taskCard(array $item): array
    {
        return [
            'public_id' => (string)$item['public_id'],
            'title' => (string)$item['title'],
            'description' => ($this->htmlSanitizer ?? new HtmlSanitizer())->sanitize((string)($item['description'] ?? '')),
            'status_code' => (string)($item['status_code'] ?? 'new'),
            'priority_code' => (string)($item['priority_code'] ?? 'normal'),
            'due_at' => $item['due_at'] ?? null,
            'updated_at' => $item['updated_at'] ?? null,
            'row_version' => (int)($item['row_version'] ?? 1),
            'project_public_id' => $item['project_public_id'] ?? null,
            'project_title' => $item['project_title'] ?? null,
            'assignee_user_public_id' => $item['assignee_user_public_id'] ?? null,
            'assignee_name' => $item['assignee_name'] ?? null,
        ];
    }

    /** @param array<string,mixed> $item */
    private function laneFromTask(array $item, string $swimlaneBy): array
    {
        return match ($swimlaneBy) {
            'assignee' => [
                (string)($item['assignee_user_public_id'] ?? 'unassigned'),
                (string)($item['assignee_name'] ?? $this->t('task/messages.unassigned')),
            ],
            'priority' => [
                (string)($item['priority_code'] ?? 'normal'),
                (string)($item['priority_code'] ?? 'normal'),
            ],
            'project' => [
                (string)($item['project_public_id'] ?? 'no_project'),
                (string)($item['project_title'] ?? $this->t('task/messages.no_project')),
            ],
            default => ['default', $this->t('task/messages.all_tasks')],
        };
    }

    private function isAllowedStatus(string $code): bool
    {
        if (in_array($code, ['new', 'in_progress', 'blocked', 'done'], true)) {
            return true;
        }

        $status = $this->statuses->findByScopeAndCode('task', $code);
        return $status !== null && (int)($status['is_active'] ?? 1) === 1;
    }

    private function isWipLimitExceeded(string $newStatusCode, array $task): bool
    {
        $status = $this->statuses->findByScopeAndCode('task', $newStatusCode);
        if (!$status) return false;

        $wipLimit = (int)($status['wip_limit'] ?? 0);
        if ($wipLimit <= 0) return false;

        $currentStatus = (string)($task['status_code'] ?? '');
        if ($currentStatus === $newStatusCode) return false;

        $columnCount = $this->statuses->countActiveTasksInStatus($newStatusCode);
        return $columnCount >= $wipLimit;
    }
}
