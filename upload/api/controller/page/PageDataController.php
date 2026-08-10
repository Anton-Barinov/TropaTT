<?php
declare(strict_types=1);

namespace Api\Controller\Page;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\AiSuggestionService;
use Api\System\Library\Service\CalendarService;
use Api\System\Library\Service\StatusService;
use Api\System\Library\Service\TaskService;

final class PageDataController extends BaseController
{
    public function myDay(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $date = $this->normalizeDate((string)($input['date'] ?? ''));
        $yesterday = (new \DateTimeImmutable($date . ' 00:00:00'))->modify('-1 day')->format('Y-m-d');
        $actor = $auth['user'];

        $cache = $this->cacheApi();
        if ($cache !== null) {
            $cacheKey = 'myDay:' . $this->cacheUserId() . ':' . hash('sha256', json_encode(['date' => $date], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $payload = $cache->remember('page', $cacheKey, 45, function () use ($actor, $date, $yesterday) {
                return $this->buildMyDayPayload($actor, $date, $yesterday);
            });
        } else {
            $payload = $this->buildMyDayPayload($actor, $date, $yesterday);
        }

        return $this->success('PAGE_MY_DAY', $this->t('page/messages.my_day_data'), $payload);
    }

    public function myWeek(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $date = $this->normalizeDate((string)($input['date'] ?? ''));
        $base = new \DateTimeImmutable($date . ' 00:00:00');
        $weekStart = $base->modify('monday this week')->format('Y-m-d');
        $weekEnd = $base->modify('monday this week')->modify('+6 day')->format('Y-m-d');
        $yesterday = (new \DateTimeImmutable($date . ' 00:00:00'))->modify('-1 day')->format('Y-m-d');
        $actor = $auth['user'];

        $cache = $this->cacheApi();
        if ($cache !== null) {
            $cacheKey = 'myWeek:' . $this->cacheUserId() . ':' . hash('sha256', json_encode(['date' => $date], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $payload = $cache->remember('page', $cacheKey, 45, function () use ($actor, $date, $weekStart, $weekEnd, $yesterday) {
                return $this->buildMyWeekPayload($actor, $date, $weekStart, $weekEnd, $yesterday);
            });
        } else {
            $payload = $this->buildMyWeekPayload($actor, $date, $weekStart, $weekEnd, $yesterday);
        }

        return $this->success('PAGE_MY_WEEK', $this->t('page/messages.my_week_data'), $payload);
    }

    public function kanban(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $actor = $auth['user'];
        $cache = $this->cacheApi();
        if ($cache !== null) {
            $cacheKey = 'kanban:' . $this->cacheUserId();
            $payload = $cache->remember('page', $cacheKey, 30, function () use ($actor) {
                return $this->buildKanbanPayload($actor);
            });
        } else {
            $payload = $this->buildKanbanPayload($actor);
        }

        return $this->success('PAGE_KANBAN', $this->t('page/messages.kanban_data'), $payload);
    }

    /** @param array<string,mixed> $actor */
    private function buildMyDayPayload(array $actor, string $date, string $yesterday): array
    {
        /** @var TaskService $taskService */
        $taskService = $this->container->get('service.task');
        /** @var CalendarService $calendarService */
        $calendarService = $this->container->get('service.calendar');

        $overdue = $taskService->list([
            'due_at_to' => $yesterday,
            'limit' => 30,
            'sort' => 'due_at',
            'order' => 'ASC',
        ], $actor);
        $today = $taskService->list([
            'due_at' => $date,
            'limit' => 40,
            'sort' => 'priority_code',
            'order' => 'DESC',
        ], $actor);
        $calendar = $calendarService->myDay($actor, $date);

        return [
            'date' => $date,
            'overdue_tasks' => [
                'items' => (array)($overdue['items'] ?? []),
                'meta' => (array)($overdue['meta'] ?? []),
            ],
            'today_tasks' => [
                'items' => (array)($today['items'] ?? []),
                'meta' => (array)($today['meta'] ?? []),
            ],
            'calendar' => $calendar,
            'ai_suggestion' => $this->latestMyDaySuggestion($actor),
        ];
    }

    /** @param array<string,mixed> $actor */
    private function buildMyWeekPayload(array $actor, string $date, string $weekStart, string $weekEnd, string $yesterday): array
    {
        /** @var TaskService $taskService */
        $taskService = $this->container->get('service.task');
        /** @var CalendarService $calendarService */
        $calendarService = $this->container->get('service.calendar');

        $weekTasks = $taskService->list([
            'due_at_from' => $weekStart,
            'due_at_to' => $weekEnd,
            'limit' => 50,
        ], $actor);
        $overdue = $taskService->list([
            'due_at_to' => $yesterday,
            'limit' => 30,
        ], $actor);
        $calendar = $calendarService->myWeek($actor, $date);

        return [
            'date' => $date,
            'week_from' => $weekStart,
            'week_to' => $weekEnd,
            'week_tasks' => [
                'items' => (array)($weekTasks['items'] ?? []),
                'meta' => (array)($weekTasks['meta'] ?? []),
            ],
            'overdue_tasks' => [
                'items' => (array)($overdue['items'] ?? []),
                'meta' => (array)($overdue['meta'] ?? []),
            ],
            'calendar' => $calendar,
            'ai_suggestion' => $this->latestMyWeekSuggestion($actor),
        ];
    }

    /** @param array<string,mixed> $actor */
    private function buildKanbanPayload(array $actor): array
    {
        /** @var TaskService $taskService */
        $taskService = $this->container->get('service.task');
        /** @var StatusService $statusService */
        $statusService = $this->container->get('service.status');

        // Global setting kanban_max_cards (scope 'system'): 0 (default) = load every task
        // the actor can see at once; > 0 = chunked pages with automatic client-side load-more.
        $kanbanLimit = 0;
        if ($this->container->has('service.setting')) {
            $kanbanSetting = $this->container->get('service.setting')->get('system', 'kanban_max_cards');
            if ($kanbanSetting !== null) {
                $kanbanLimit = max(0, (int)($kanbanSetting['value'] ?? 0));
            }
        }

        $tasks = $taskService->list([
            'limit' => $kanbanLimit,
            'with_status_counts' => '1',
        ], $actor);
        $statuses = $statusService->list([
            'limit' => 200,
        ]);

        return [
            'tasks' => [
                'items' => (array)($tasks['items'] ?? []),
                'meta' => (array)($tasks['meta'] ?? []),
            ],
            // Full per-status counts for the whole visible set — the board can
            // show real column counters immediately while cards load in chunks.
            'status_counts' => (array)($tasks['meta']['status_counts'] ?? []),
            'statuses' => [
                'items' => (array)($statuses['items'] ?? []),
                'meta' => (array)($statuses['meta'] ?? []),
            ],
        ];
    }

    /** @param array<string,mixed> $actor */
    private function latestMyDaySuggestion(array $actor): ?array
    {
        if (!$this->actorCanUseAi($actor) || !$this->container->has('service.ai_suggestion')) {
            return null;
        }

        try {
            /** @var AiSuggestionService $service */
            $service = $this->container->get('service.ai_suggestion');
            $result = $service->list([
                'intent_code' => 'my_day_plan',
                'entity_type' => 'user',
                'limit' => 20,
            ], $actor);
            $items = (array)($result['items'] ?? []);
            usort($items, static function (array $a, array $b): int {
                $aTime = strtotime((string)($a['updated_at'] ?? $a['created_at'] ?? '')) ?: 0;
                $bTime = strtotime((string)($b['updated_at'] ?? $b['created_at'] ?? '')) ?: 0;
                return $bTime <=> $aTime;
            });

            $selected = $this->selectBestMyDaySuggestion($items);
            $publicId = trim((string)($selected['public_id'] ?? ''));
            return $publicId !== '' ? $service->get($publicId, $actor) : null;
        } catch (\Throwable $e) {
            error_log('[PageDataController::latestMyDaySuggestion] ' . $e->getMessage());
            return null;
        }
    }

    /** @param array<string,mixed> $actor */
    private function latestMyWeekSuggestion(array $actor): ?array
    {
        if (!$this->actorCanUseAi($actor) || !$this->container->has('service.ai_suggestion')) {
            return null;
        }

        try {
            /** @var AiSuggestionService $service */
            $service = $this->container->get('service.ai_suggestion');
            $result = $service->list([
                'intent_code' => 'my_week_plan',
                'entity_type' => 'user',
                'limit' => 20,
            ], $actor);
            $items = (array)($result['items'] ?? []);
            usort($items, static function (array $a, array $b): int {
                $aTime = strtotime((string)($a['updated_at'] ?? $a['created_at'] ?? '')) ?: 0;
                $bTime = strtotime((string)($b['updated_at'] ?? $b['created_at'] ?? '')) ?: 0;
                return $bTime <=> $aTime;
            });

            $selected = $this->selectBestMyWeekSuggestion($items);
            $publicId = trim((string)($selected['public_id'] ?? ''));
            return $publicId !== '' ? $service->get($publicId, $actor) : null;
        } catch (\Throwable $e) {
            error_log('[PageDataController::latestMyWeekSuggestion] ' . $e->getMessage());
            return null;
        }
    }

    /** @param array<int,array<string,mixed>> $items */
    private function selectBestMyDaySuggestion(array $items): ?array
    {
        $withSlots = null;
        $concrete = null;
        foreach ($items as $item) {
            $payload = is_array($item['payload'] ?? null) ? (array)$item['payload'] : [];
            $workItems = is_array($payload['work_items'] ?? null) ? (array)$payload['work_items'] : [];
            $slots = is_array($payload['calendar_slots'] ?? null) ? (array)$payload['calendar_slots'] : [];
            $hasItems = $this->hasSuggestionWorkItems($workItems);
            $hasSlots = $this->hasSuggestionSlots($slots);
            if ($withSlots === null && $hasItems && $hasSlots) {
                $withSlots = $item;
            }
            if ($concrete === null && $hasItems) {
                $concrete = $item;
            }
        }

        return $withSlots ?? $concrete ?? ($items[0] ?? null);
    }

    /** @param array<int,array<string,mixed>> $items */
    private function selectBestMyWeekSuggestion(array $items): ?array
    {
        foreach ($items as $item) {
            $payload = is_array($item['payload'] ?? null) ? (array)$item['payload'] : [];
            if (is_array($payload['tasks_by_day'] ?? null) && (array)$payload['tasks_by_day'] !== []) {
                return $item;
            }
        }
        return $items[0] ?? null;
    }

    /** @param array<int,mixed> $workItems */
    private function hasSuggestionWorkItems(array $workItems): bool
    {
        foreach ($workItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (trim((string)($item['task_public_id'] ?? $item['title'] ?? '')) !== '') {
                return true;
            }
        }
        return false;
    }

    /** @param array<int,mixed> $slots */
    private function hasSuggestionSlots(array $slots): bool
    {
        foreach ($slots as $slot) {
            if (!is_array($slot)) {
                continue;
            }
            if (trim((string)($slot['start_at'] ?? $slot['start'] ?? '')) !== ''
                && trim((string)($slot['end_at'] ?? $slot['end'] ?? '')) !== '') {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,mixed> $actor */
    private function actorCanUseAi(array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }
        $permissions = is_array($actor['permission_codes'] ?? null) ? array_map('strval', (array)$actor['permission_codes']) : [];
        return in_array('*', $permissions, true) || in_array('ai.use', $permissions, true);
    }

    private function normalizeDate(string $date): string
    {
        $raw = trim($date);
        if ($raw === '' || strtotime($raw) === false) {
            return (new \DateTimeImmutable('today'))->format('Y-m-d');
        }
        return (new \DateTimeImmutable($raw))->format('Y-m-d');
    }
}
