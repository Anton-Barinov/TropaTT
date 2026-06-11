<?php
declare(strict_types=1);

namespace Api\Controller\Page;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\AiSuggestionService;
use Api\System\Library\Service\CalendarService;
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
            $cacheKey = 'myDay:' . $this->cacheUserId() . ':' . md5(json_encode(['date' => $date], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $payload = $cache->remember('page', $cacheKey, 45, function () use ($actor, $date, $yesterday) {
                return $this->buildMyDayPayload($actor, $date, $yesterday);
            });
        } else {
            $payload = $this->buildMyDayPayload($actor, $date, $yesterday);
        }

        return $this->success('PAGE_MY_DAY', 'Данные страницы «Мой день»', $payload);
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
        } catch (\Throwable) {
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
