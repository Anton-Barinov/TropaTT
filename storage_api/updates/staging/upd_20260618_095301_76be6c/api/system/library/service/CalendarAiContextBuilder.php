<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

final class CalendarAiContextBuilder
{
    public function __construct(
        private readonly CalendarService $calendar,
        private readonly TaskService $tasks,
        private readonly AiMaskingService $masking
    ) {
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array{agenda:array<string,mixed>,candidate_tasks:array<int,array<string,mixed>>,date:string}
     */
    public function buildMyDayContext(array $input, array $actor): array
    {
        $date = trim((string)($input['date'] ?? ''));
        $agenda = $this->calendar->myDay($actor, $date !== '' ? $date : null);
        $candidateTasks = [];
        $seen = [];
        $taskQueries = [
            ['limit' => 60, 'sort' => 'due_at', 'order' => 'ASC'],
            ['limit' => 40, 'sort' => 'priority_code', 'order' => 'DESC'],
            ['limit' => 40, 'status' => 'in_progress', 'sort' => 'updated_at', 'order' => 'DESC'],
        ];
        foreach ($taskQueries as $query) {
            $tasksList = $this->tasks->list($query, $actor);
            $items = is_array($tasksList['items'] ?? null) ? (array)$tasksList['items'] : [];
            foreach ($items as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $publicId = trim((string)($row['public_id'] ?? ''));
                if ($publicId === '' || isset($seen[$publicId])) {
                    continue;
                }
                $seen[$publicId] = true;
                $candidateTasks[] = $row;
            }
        }
        $candidateTasks = array_map(function (array $row): array {
            return [
                'public_id' => (string)($row['public_id'] ?? ''),
                'title' => $this->masking->maskSensitiveText(trim((string)($row['title'] ?? ''))),
                'status_code' => trim((string)($row['status_code'] ?? '')),
                'priority_code' => trim((string)($row['priority_code'] ?? '')),
                'due_at' => (string)($row['due_at'] ?? ''),
                'estimated_minutes' => (int)($row['estimated_minutes'] ?? 0),
                'project_public_id' => (string)($row['project_public_id'] ?? ''),
                'project_title' => $this->masking->maskSensitiveText(trim((string)($row['project_title'] ?? ''))),
                'parent_task_public_id' => (string)($row['parent_task_public_id'] ?? ''),
            ];
        }, $candidateTasks);

        return [
            'agenda' => $agenda,
            'candidate_tasks' => $candidateTasks,
            'date' => $date,
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array{agenda:array<string,mixed>,candidate_tasks:array<int,array<string,mixed>>,date:string}
     */
    public function buildMyWeekContext(array $input, array $actor): array
    {
        $date = trim((string)($input['date'] ?? ''));
        $agenda = $this->calendar->myWeek($actor, $date !== '' ? $date : null);
        $candidateTasks = [];
        $seen = [];
        $taskQueries = [
            ['limit' => 100, 'sort' => 'due_at', 'order' => 'ASC'],
            ['limit' => 60, 'sort' => 'priority_code', 'order' => 'DESC'],
            ['limit' => 60, 'status' => 'in_progress', 'sort' => 'updated_at', 'order' => 'DESC'],
        ];
        foreach ($taskQueries as $query) {
            $tasksList = $this->tasks->list($query, $actor);
            $items = is_array($tasksList['items'] ?? null) ? (array)$tasksList['items'] : [];
            foreach ($items as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $publicId = trim((string)($row['public_id'] ?? ''));
                if ($publicId === '' || isset($seen[$publicId])) {
                    continue;
                }
                $seen[$publicId] = true;
                $candidateTasks[] = $row;
            }
        }
        $candidateTasks = array_map(function (array $row): array {
            return [
                'public_id' => (string)($row['public_id'] ?? ''),
                'title' => $this->masking->maskSensitiveText(trim((string)($row['title'] ?? ''))),
                'status_code' => trim((string)($row['status_code'] ?? '')),
                'priority_code' => trim((string)($row['priority_code'] ?? '')),
                'due_at' => (string)($row['due_at'] ?? ''),
                'estimated_minutes' => (int)($row['estimated_minutes'] ?? 0),
                'project_public_id' => (string)($row['project_public_id'] ?? ''),
                'project_title' => $this->masking->maskSensitiveText(trim((string)($row['project_title'] ?? ''))),
                'parent_task_public_id' => (string)($row['parent_task_public_id'] ?? ''),
            ];
        }, $candidateTasks);

        return [
            'agenda' => $agenda,
            'candidate_tasks' => $candidateTasks,
            'date' => $date,
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>|null
     */
    public function buildEventAgendaContext(string $eventPublicId, array $input, array $actor): ?array
    {
        $event = $this->calendar->getEvent($eventPublicId, $actor);
        if (!$event) {
            return null;
        }

        $inputPrompt = trim((string)($input['prompt'] ?? $input['input_text'] ?? ''));
        return [
            'event_public_id' => (string)($event['public_id'] ?? ''),
            'title' => $this->masking->maskSensitiveText(trim((string)($event['title'] ?? ''))),
            'description' => $this->masking->maskSensitiveText(trim((string)($event['description'] ?? ''))),
            'starts_at' => (string)($event['starts_at'] ?? ''),
            'ends_at' => (string)($event['ends_at'] ?? ''),
            'task_public_id' => (string)($event['task_public_id'] ?? ''),
            'project_public_id' => (string)($event['project_public_id'] ?? ''),
            'prompt' => $this->masking->maskSensitiveText($inputPrompt),
        ];
    }
}
