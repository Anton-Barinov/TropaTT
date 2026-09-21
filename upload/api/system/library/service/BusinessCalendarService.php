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

    private function organizationId(array $actor): ?int
    {
        $id = (int)($actor['organization_id'] ?? 0);
        return $id > 0 ? $id : null;
    }

    public function listCalendars(array $filters, array $actor = []): array
    {
        $orgId = $this->organizationId($actor);
        if ($orgId !== null) {
            $filters['organization_id'] = $orgId;
        }
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
            'organization_id' => $this->organizationId($actor),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->logger->audit([
            'action' => 'business_calendar_created',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'business_calendar',
            'entity_public_id' => $publicId,
        ]);

        return (array)$this->repo->findCalendarByPublicId($publicId, $this->organizationId($actor));
    }

    public function getCalendar(string $publicId, array $actor = []): ?array
    {
        return $this->repo->findCalendarByPublicId($publicId, $this->organizationId($actor));
    }

    public function updateCalendar(string $publicId, array $input, array $actor): ?array
    {
        $existing = $this->repo->findCalendarByPublicId($publicId, $this->organizationId($actor));
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
            $this->repo->updateCalendarByPublicId($publicId, $set, $this->organizationId($actor));
        }

        $this->logger->audit([
            'action' => 'business_calendar_updated',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'business_calendar',
            'entity_public_id' => $publicId,
            'changes' => $set,
        ]);

        return $this->repo->findCalendarByPublicId($publicId, $this->organizationId($actor));
    }

    public function deleteCalendar(string $publicId, array $actor): bool
    {
        $ok = $this->repo->deleteCalendarByPublicId($publicId, $this->organizationId($actor));
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

    public function listHolidays(string $calendarPublicId, array $filters, array $actor = []): array
    {
        [$items, $total, $page, $limit] = $this->repo->listHolidays($calendarPublicId, $filters, $this->organizationId($actor));
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
        $calendar = $this->repo->findCalendarByPublicId((string)$input['calendar_public_id'], $this->organizationId($actor));
        if (!$calendar) {
            return ['ok' => false, 'code' => 'CALENDAR_NOT_FOUND'];
        }

        $publicId = Ulid::generate('hol');
        $this->repo->createHoliday([
            'public_id' => $publicId,
            'calendar_id' => (int)$calendar['id'],
            'holiday_date' => (string)$input['holiday_date'],
            'title' => trim((string)$input['title']),
            'organization_id' => $calendar['organization_id'] ?? $this->organizationId($actor),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->logger->audit([
            'action' => 'calendar_holiday_created',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'holiday',
            'entity_public_id' => $publicId,
            'calendar_public_id' => $calendar['public_id'] ?? null,
        ]);

        return ['ok' => true, 'holiday' => $this->repo->findHolidayByPublicId($publicId, $this->organizationId($actor))];
    }

    public function getHoliday(string $publicId, array $actor = []): ?array
    {
        return $this->repo->findHolidayByPublicId($publicId, $this->organizationId($actor));
    }

    public function updateHoliday(string $publicId, array $input, array $actor): ?array
    {
        $existing = $this->repo->findHolidayByPublicId($publicId, $this->organizationId($actor));
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
            $this->repo->updateHolidayByPublicId($publicId, $set, $this->organizationId($actor));
        }

        $this->logger->audit([
            'action' => 'calendar_holiday_updated',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'holiday',
            'entity_public_id' => $publicId,
            'changes' => $set,
        ]);

        return $this->repo->findHolidayByPublicId($publicId, $this->organizationId($actor));
    }

    public function deleteHoliday(string $publicId, array $actor): bool
    {
        $ok = $this->repo->deleteHolidayByPublicId($publicId, $this->organizationId($actor));
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

    public function listWorkingHours(string $calendarPublicId, array $filters, array $actor = []): array
    {
        [$items, $total, $page, $limit] = $this->repo->listWorkingHours($calendarPublicId, $filters, $this->organizationId($actor));
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
        $calendar = $this->repo->findCalendarByPublicId((string)$input['calendar_public_id'], $this->organizationId($actor));
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
            'organization_id' => $calendar['organization_id'] ?? $this->organizationId($actor),
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

        return ['ok' => true, 'working_hours' => $this->repo->findWorkingHoursByPublicId($publicId, $this->organizationId($actor))];
    }

    public function getWorkingHours(string $publicId, array $actor = []): ?array
    {
        return $this->repo->findWorkingHoursByPublicId($publicId, $this->organizationId($actor));
    }

    public function updateWorkingHours(string $publicId, array $input, array $actor): ?array
    {
        $existing = $this->repo->findWorkingHoursByPublicId($publicId, $this->organizationId($actor));
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
            $this->repo->updateWorkingHoursByPublicId($publicId, $set, $this->organizationId($actor));
        }

        $this->logger->audit([
            'action' => 'calendar_working_hours_updated',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'working_hours',
            'entity_public_id' => $publicId,
            'changes' => $set,
        ]);

        return $this->repo->findWorkingHoursByPublicId($publicId, $this->organizationId($actor));
    }

    public function deleteWorkingHours(string $publicId, array $actor): bool
    {
        $ok = $this->repo->deleteWorkingHoursByPublicId($publicId, $this->organizationId($actor));
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

    public function addWorkingMinutes(\DateTimeImmutable $from, int $minutes, ?int $calendarId = null, ?int $organizationId = null): \DateTimeImmutable
    {
        if ($minutes <= 0) {
            return $from;
        }

        $calendar = null;
        if ($calendarId !== null && $calendarId > 0) {
            $calendar = $this->repo->findCalendarById($calendarId);
        }
        if (!$calendar) {
            $calendar = $this->repo->getDefaultCalendarForOrganization($organizationId);
        }

        // If no calendar exists, or no working hours exist:
        // fall back to astronomical minutes (24/7)
        if (!$calendar) {
            return $from->modify("+{$minutes} minutes");
        }

        $calId = (int)$calendar['id'];
        $rawWorkingHours = $this->repo->getAllWorkingHoursForCalendar($calId);
        if ($rawWorkingHours === []) {
            return $from->modify("+{$minutes} minutes");
        }

        // Parse calendar timezone
        $tzStr = trim((string)($calendar['timezone'] ?? 'UTC'));
        try {
            $tz = new \DateTimeZone($tzStr !== '' ? $tzStr : 'UTC');
        } catch (\Throwable) {
            $tz = new \DateTimeZone('UTC');
        }

        // Group working hours by weekday 1..7 (1=Mon, 7=Sun)
        $schedule = [];
        $is24x7 = true;
        for ($i = 1; $i <= 7; $i++) {
            $schedule[$i] = [];
        }

        foreach ($rawWorkingHours as $wh) {
            $dow = (int)$wh['weekday'];
            if ($dow < 1 || $dow > 7) {
                continue;
            }
            $startParts = explode(':', (string)$wh['start_time']);
            $endParts = explode(':', (string)$wh['end_time']);
            $startSec = (int)($startParts[0] ?? 0) * 3600 + (int)($startParts[1] ?? 0) * 60 + (int)($startParts[2] ?? 0);
            $endSec = (int)($endParts[0] ?? 0) * 3600 + (int)($endParts[1] ?? 0) * 60 + (int)($endParts[2] ?? 0);
            if ($endSec > $startSec) {
                $schedule[$dow][] = ['start' => $startSec, 'end' => $endSec];
            }
        }

        // Check if every day has schedule and if all 7 days cover 00:00 to 24:00 (or >= 86340 sec)
        for ($i = 1; $i <= 7; $i++) {
            if (empty($schedule[$i])) {
                $is24x7 = false;
                break;
            }
            usort($schedule[$i], static fn($a, $b) => $a['start'] <=> $b['start']);
            $totalDaySec = 0;
            foreach ($schedule[$i] as $intv) {
                $totalDaySec += ($intv['end'] - $intv['start']);
            }
            if ($totalDaySec < 86340) { // 23h 59m
                $is24x7 = false;
            }
        }

        // Fetch holidays for the next year
        $startDateStr = $from->setTimezone($tz)->format('Y-m-d');
        $endDateStr = $from->setTimezone($tz)->modify('+366 days')->format('Y-m-d');
        $holidayRows = $this->repo->getHolidaysInRange($calId, $startDateStr, $endDateStr);
        $holidays = [];
        foreach ($holidayRows as $h) {
            $holidays[$h['holiday_date']] = true;
        }

        if ($is24x7 && empty($holidays)) {
            return $from->modify("+{$minutes} minutes");
        }

        $current = $from->setTimezone($tz);
        $remaining = $minutes;
        $maxDays = 366;
        $daysCount = 0;

        while ($remaining > 0 && $daysCount < $maxDays) {
            $dayStr = $current->format('Y-m-d');
            $dow = (int)$current->format('N');

            if (isset($holidays[$dayStr]) || empty($schedule[$dow])) {
                $current = $current->modify('+1 day')->setTime(0, 0, 0);
                $daysCount++;
                continue;
            }

            $currentSec = (int)$current->format('H') * 3600 + (int)$current->format('i') * 60 + (int)$current->format('s');
            $intervals = $schedule[$dow];

            foreach ($intervals as $interval) {
                $startSec = $interval['start'];
                $endSec = $interval['end'];

                if ($currentSec >= $endSec) {
                    continue;
                }

                if ($currentSec < $startSec) {
                    $current = $current->setTime((int)intdiv($startSec, 3600), (int)intdiv($startSec % 3600, 60), $startSec % 60);
                    $currentSec = $startSec;
                }

                $availableMinutes = (int)floor(($endSec - $currentSec) / 60);
                if ($availableMinutes <= 0) {
                    continue;
                }

                if ($remaining <= $availableMinutes) {
                    $current = $current->modify("+{$remaining} minutes");
                    $remaining = 0;
                    break;
                }

                $remaining -= $availableMinutes;
                $current = $current->setTime((int)intdiv($endSec, 3600), (int)intdiv($endSec % 3600, 60), $endSec % 60);
                $currentSec = $endSec;
            }

            if ($remaining > 0) {
                $current = $current->modify('+1 day')->setTime(0, 0, 0);
                $daysCount++;
            }
        }

        return $current->setTimezone($from->getTimezone());
    }
}
