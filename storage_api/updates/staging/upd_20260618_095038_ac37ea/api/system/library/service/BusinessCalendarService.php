<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Calendar\BusinessCalendarRepository;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Support\Ulid;

final class BusinessCalendarService
{
    public function __construct(
        private readonly BusinessCalendarRepository $repo,
        private readonly JsonLogger $logger
    ) {
    }

    public function listCalendars(array $filters): array
    {
        [$items, $total, $page, $limit] = $this->repo->listCalendars($filters);

        return [
            'items' => $items,
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int)ceil($total / max(1, $limit)),
                ],
            ],
        ];
    }

    public function createCalendar(array $input, array $actor): array
    {
        $publicId = Ulid::generate('bcl');
        $now = gmdate('Y-m-d H:i:s');

        $this->repo->createCalendar([
            'public_id' => $publicId,
            'title' => trim((string)$input['title']),
            'timezone' => trim((string)($input['timezone'] ?? 'UTC')),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->logger->audit([
            'action' => 'business_calendar_created',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'business_calendar',
            'entity_public_id' => $publicId,
        ]);

        return (array)$this->repo->findCalendarByPublicId($publicId);
    }

    public function getCalendar(string $publicId): ?array
    {
        return $this->repo->findCalendarByPublicId($publicId);
    }

    public function updateCalendar(string $publicId, array $input, array $actor): ?array
    {
        $existing = $this->repo->findCalendarByPublicId($publicId);
        if (!$existing) {
            return null;
        }

        $set = [];
        if (array_key_exists('title', $input)) {
            $set['title'] = trim((string)$input['title']);
        }
        if (array_key_exists('timezone', $input)) {
            $set['timezone'] = trim((string)$input['timezone']);
        }
        if ($set !== []) {
            $set['updated_at'] = gmdate('Y-m-d H:i:s');
            $this->repo->updateCalendarByPublicId($publicId, $set);
        }

        $this->logger->audit([
            'action' => 'business_calendar_updated',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'business_calendar',
            'entity_public_id' => $publicId,
            'changes' => $set,
        ]);

        return $this->repo->findCalendarByPublicId($publicId);
    }

    public function deleteCalendar(string $publicId, array $actor): bool
    {
        $ok = $this->repo->deleteCalendarByPublicId($publicId);
        if ($ok) {
            $this->logger->audit([
                'action' => 'business_calendar_deleted',
                'actor_public_id' => $actor['public_id'] ?? null,
                'entity_type' => 'business_calendar',
                'entity_public_id' => $publicId,
            ]);
        }

        return $ok;
    }

    public function listHolidays(string $calendarPublicId, array $filters): array
    {
        [$items, $total, $page, $limit] = $this->repo->listHolidays($calendarPublicId, $filters);
        if ($items === null) {
            return ['ok' => false, 'code' => 'CALENDAR_NOT_FOUND'];
        }

        return [
            'ok' => true,
            'items' => $items,
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int)ceil($total / max(1, $limit)),
                ],
            ],
        ];
    }

    public function createHoliday(array $input, array $actor): array
    {
        $calendar = $this->repo->findCalendarByPublicId((string)$input['calendar_public_id']);
        if (!$calendar) {
            return ['ok' => false, 'code' => 'CALENDAR_NOT_FOUND'];
        }

        $publicId = Ulid::generate('hol');
        $this->repo->createHoliday([
            'public_id' => $publicId,
            'calendar_id' => (int)$calendar['id'],
            'holiday_date' => (string)$input['holiday_date'],
            'title' => trim((string)$input['title']),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->logger->audit([
            'action' => 'calendar_holiday_created',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'holiday',
            'entity_public_id' => $publicId,
            'calendar_public_id' => $calendar['public_id'] ?? null,
        ]);

        return ['ok' => true, 'holiday' => $this->repo->findHolidayByPublicId($publicId)];
    }

    public function getHoliday(string $publicId): ?array
    {
        return $this->repo->findHolidayByPublicId($publicId);
    }

    public function updateHoliday(string $publicId, array $input, array $actor): ?array
    {
        $existing = $this->repo->findHolidayByPublicId($publicId);
        if (!$existing) {
            return null;
        }

        $set = [];
        if (array_key_exists('holiday_date', $input)) {
            $set['holiday_date'] = (string)$input['holiday_date'];
        }
        if (array_key_exists('title', $input)) {
            $set['title'] = trim((string)$input['title']);
        }
        if ($set !== []) {
            $this->repo->updateHolidayByPublicId($publicId, $set);
        }

        $this->logger->audit([
            'action' => 'calendar_holiday_updated',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'holiday',
            'entity_public_id' => $publicId,
            'changes' => $set,
        ]);

        return $this->repo->findHolidayByPublicId($publicId);
    }

    public function deleteHoliday(string $publicId, array $actor): bool
    {
        $ok = $this->repo->deleteHolidayByPublicId($publicId);
        if ($ok) {
            $this->logger->audit([
                'action' => 'calendar_holiday_deleted',
                'actor_public_id' => $actor['public_id'] ?? null,
                'entity_type' => 'holiday',
                'entity_public_id' => $publicId,
            ]);
        }

        return $ok;
    }

    public function listWorkingHours(string $calendarPublicId, array $filters): array
    {
        [$items, $total, $page, $limit] = $this->repo->listWorkingHours($calendarPublicId, $filters);
        if ($items === null) {
            return ['ok' => false, 'code' => 'CALENDAR_NOT_FOUND'];
        }

        return [
            'ok' => true,
            'items' => $items,
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int)ceil($total / max(1, $limit)),
                ],
            ],
        ];
    }

    public function createWorkingHours(array $input, array $actor): array
    {
        $calendar = $this->repo->findCalendarByPublicId((string)$input['calendar_public_id']);
        if (!$calendar) {
            return ['ok' => false, 'code' => 'CALENDAR_NOT_FOUND'];
        }

        $publicId = Ulid::generate('wrk');
        $now = gmdate('Y-m-d H:i:s');
        $this->repo->createWorkingHours([
            'public_id' => $publicId,
            'calendar_id' => (int)$calendar['id'],
            'weekday' => (int)$input['weekday'],
            'start_time' => (string)$input['start_time'],
            'end_time' => (string)$input['end_time'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->logger->audit([
            'action' => 'calendar_working_hours_created',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'working_hours',
            'entity_public_id' => $publicId,
            'calendar_public_id' => $calendar['public_id'] ?? null,
        ]);

        return ['ok' => true, 'working_hours' => $this->repo->findWorkingHoursByPublicId($publicId)];
    }

    public function getWorkingHours(string $publicId): ?array
    {
        return $this->repo->findWorkingHoursByPublicId($publicId);
    }

    public function updateWorkingHours(string $publicId, array $input, array $actor): ?array
    {
        $existing = $this->repo->findWorkingHoursByPublicId($publicId);
        if (!$existing) {
            return null;
        }

        $set = [];
        if (array_key_exists('weekday', $input)) {
            $set['weekday'] = (int)$input['weekday'];
        }
        if (array_key_exists('start_time', $input)) {
            $set['start_time'] = (string)$input['start_time'];
        }
        if (array_key_exists('end_time', $input)) {
            $set['end_time'] = (string)$input['end_time'];
        }
        if ($set !== []) {
            $set['updated_at'] = gmdate('Y-m-d H:i:s');
            $this->repo->updateWorkingHoursByPublicId($publicId, $set);
        }

        $this->logger->audit([
            'action' => 'calendar_working_hours_updated',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'working_hours',
            'entity_public_id' => $publicId,
            'changes' => $set,
        ]);

        return $this->repo->findWorkingHoursByPublicId($publicId);
    }

    public function deleteWorkingHours(string $publicId, array $actor): bool
    {
        $ok = $this->repo->deleteWorkingHoursByPublicId($publicId);
        if ($ok) {
            $this->logger->audit([
                'action' => 'calendar_working_hours_deleted',
                'actor_public_id' => $actor['public_id'] ?? null,
                'entity_type' => 'working_hours',
                'entity_public_id' => $publicId,
            ]);
        }

        return $ok;
    }
}
