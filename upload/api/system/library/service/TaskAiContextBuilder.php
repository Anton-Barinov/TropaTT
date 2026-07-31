<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

final class TaskAiContextBuilder
{
    public function __construct(
        private readonly AiMaskingService $masking,
        private readonly ProjectService $projects,
        private readonly ClientService $clients,
        private readonly CommentService $comments,
        private readonly SubtaskService $subtasks,
        private readonly ChecklistService $checklists,
        private readonly TaskService $tasks
    ) {
    }

    /**
     * @param array<string,mixed> $task
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public function buildSummaryContext(array $task, array $input, array $actor): array
    {
        $description = trim((string)($task['description'] ?? ''));
        $inputPrompt = trim((string)($input['prompt'] ?? $input['input_text'] ?? ''));
        $projectPublicId = trim((string)($task['project_public_id'] ?? ''));
        $projectSummary = $this->buildProjectSummary($projectPublicId, $actor);
        $clientSummary = $this->buildClientSummary(
            $projectSummary !== null ? trim((string)($projectSummary['client_public_id'] ?? '')) : '',
            $actor
        );

        $context = [
            'task_public_id' => (string)($task['public_id'] ?? ''),
            'title' => trim((string)($task['title'] ?? '')),
            'description' => $this->masking->maskSensitiveText($description),
            'status' => trim((string)($task['status_code'] ?? '')),
            'priority' => trim((string)($task['priority_code'] ?? '')),
            'due_at' => (string)($task['due_at'] ?? ''),
            'project_public_id' => $projectPublicId,
            'project_title' => trim((string)($task['project_title'] ?? '')),
            'prompt' => $this->masking->maskSensitiveText($inputPrompt),
        ];

        if ($projectSummary !== null) {
            $context['project_summary'] = $projectSummary;
        }
        if ($clientSummary !== null) {
            $context['client_summary'] = $clientSummary;
        }

        return $context;
    }

    /**
     * @param array<string,mixed> $actor
     * @return array<string,mixed>|null
     */
    private function buildProjectSummary(string $projectPublicId, array $actor): ?array
    {
        if ($projectPublicId === '') {
            return null;
        }

        $project = $this->projects->get($projectPublicId, $actor);
        if (!$project) {
            return null;
        }

        return [
            'project_public_id' => (string)($project['public_id'] ?? ''),
            'title' => trim((string)($project['title'] ?? '')),
            'status' => trim((string)($project['status_code'] ?? '')),
            'priority' => trim((string)($project['priority_code'] ?? '')),
            'client_public_id' => trim((string)($project['client_public_id'] ?? '')),
            'description' => $this->masking->maskSensitiveText(trim((string)($project['description'] ?? ''))),
        ];
    }

    /**
     * @param array<string,mixed> $actor
     * @return array<string,mixed>|null
     */
    private function buildClientSummary(string $clientPublicId, array $actor): ?array
    {
        if ($clientPublicId === '') {
            return null;
        }

        $client = $this->clients->get($clientPublicId, $actor);
        if (!$client) {
            return null;
        }

        $clientType = trim((string)($client['client_type'] ?? ''));

        return [
            'client_public_id' => (string)($client['public_id'] ?? ''),
            'title' => $this->maskClientTitleByPolicy(trim((string)($client['title'] ?? '')), $clientType),
            'status' => trim((string)($client['status'] ?? '')),
            'client_type' => $clientType,
            'notes' => $this->masking->maskSensitiveText(trim((string)($client['notes'] ?? ''))),
        ];
    }

    private function maskClientTitleByPolicy(string $title, string $clientType): string
    {
        $normalizedType = strtolower(trim($clientType));
        if ($title === '') {
            return '';
        }

        if ($normalizedType === 'individual' || $normalizedType === 'sole_proprietor') {
            return '[masked]';
        }

        return $title;
    }

    /**
     * Builds full task context including comments, subtasks, parent task, and checklists.
     *
     * @param array<string,mixed> $task
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public function buildFullTaskContext(array $task, array $input, array $actor): array
    {
        $baseContext = $this->buildSummaryContext($task, $input, $actor);
        $taskPublicId = (string)($task['public_id'] ?? '');

        $context = $baseContext;

        $parentTask = $this->buildParentTaskContext($task, $actor);
        if ($parentTask !== null) {
            $context['parent_task'] = $parentTask;
        }

        $subtasks = $this->buildSubtasksContext($taskPublicId, $actor);
        if ($subtasks !== []) {
            $context['subtasks'] = $subtasks;
        }

        $comments = $this->buildCommentsContext($taskPublicId, $actor);
        if ($comments !== []) {
            $context['comments'] = $comments;
        }

        $checklists = $this->buildChecklistsContext($taskPublicId, $actor);
        if ($checklists !== []) {
            $context['checklists'] = $checklists;
        }

        return $context;
    }

    /**
     * @param array<string,mixed> $task
     * @param array<string,mixed> $actor
     * @return array<string,mixed>|null
     */
    private function buildParentTaskContext(array $task, array $actor): ?array
    {
        $parentTaskPublicId = trim((string)($task['parent_task_public_id'] ?? ''));
        if ($parentTaskPublicId === '') {
            return null;
        }

        $parentTask = $this->tasks->get($parentTaskPublicId, $actor);
        if (!$parentTask) {
            return null;
        }

        $description = trim((string)($parentTask['description'] ?? ''));

        return [
            'task_public_id' => (string)($parentTask['public_id'] ?? ''),
            'title' => trim((string)($parentTask['title'] ?? '')),
            'description' => $this->masking->maskSensitiveText($description),
            'status' => trim((string)($parentTask['status_code'] ?? '')),
            'priority' => trim((string)($parentTask['priority_code'] ?? '')),
            'due_at' => (string)($parentTask['due_at'] ?? ''),
        ];
    }

    /**
     * @param array<string,mixed> $actor
     * @return list<array<string,mixed>>
     */
    private function buildSubtasksContext(string $taskPublicId, array $actor): array
    {
        $subtasksData = $this->subtasks->listByTask($taskPublicId, $actor);
        if ($subtasksData === null || $subtasksData === []) {
            return [];
        }

        $result = [];
        foreach ($subtasksData as $subtask) {
            $description = trim((string)($subtask['description'] ?? ''));
            $result[] = [
                'public_id' => (string)($subtask['public_id'] ?? ''),
                'title' => trim((string)($subtask['title'] ?? '')),
                'description' => $this->masking->maskSensitiveText($description),
                'status' => trim((string)($subtask['status_code'] ?? '')),
                'priority' => trim((string)($subtask['priority_code'] ?? '')),
                'due_at' => (string)($subtask['due_at'] ?? ''),
                'assignee_name' => trim((string)($subtask['assignee_name'] ?? '')),
            ];
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $actor
     * @return list<array<string,mixed>>
     */
    private function buildCommentsContext(string $taskPublicId, array $actor): array
    {
        $commentsData = $this->comments->listByTask($taskPublicId, ['limit' => 50, 'page' => 1]);
        $items = $commentsData['items'] ?? [];
        if ($items === []) {
            return [];
        }

        $result = [];
        foreach ($items as $comment) {
            $body = trim((string)($comment['body'] ?? ''));
            if ($body === '') {
                continue;
            }
            $result[] = [
                'public_id' => (string)($comment['public_id'] ?? ''),
                'body' => $this->masking->maskSensitiveText($body),
                'visibility' => trim((string)($comment['visibility'] ?? '')),
                'author_name' => trim((string)($comment['author_name'] ?? '')),
                'created_at' => (string)($comment['created_at'] ?? ''),
            ];
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $actor
     * @return list<array<string,mixed>>
     */
    private function buildChecklistsContext(string $taskPublicId, array $actor): array
    {
        $checklistsData = $this->checklists->listByTask($taskPublicId, $actor);
        if ($checklistsData === null || $checklistsData === []) {
            return [];
        }

        $result = [];
        foreach ($checklistsData as $checklist) {
            $itemsData = $this->checklists->listItems((string)($checklist['public_id'] ?? ''), $actor);
            $items = [];
            if ($itemsData !== null) {
                foreach ($itemsData as $item) {
                    $items[] = [
                        'public_id' => (string)($item['public_id'] ?? ''),
                        'title' => trim((string)($item['title'] ?? '')),
                        'is_done' => (bool)($item['is_done'] ?? false),
                        'sort_order' => (int)($item['sort_order'] ?? 0),
                    ];
                }
            }

            $result[] = [
                'public_id' => (string)($checklist['public_id'] ?? ''),
                'title' => trim((string)($checklist['title'] ?? '')),
                'items' => $items,
            ];
        }

        return $result;
    }
}
