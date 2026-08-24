<?php
declare(strict_types=1);

namespace Api\Model\Calendar;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;
use Api\System\Library\Support\LikeEscaper;

final class BusinessCalendarRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listCalendars(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->buildCalendarsListQuery($filters)->count();
        $items = $this->buildCalendarsListQuery($filters)
            ->select(['public_id', 'title', 'timezone', 'created_at', 'updated_at'])
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildCalendarsListQuery(array $filters): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('business_calendars');

        if (!empty($filters['search'])) {
            $search = '%' . LikeEscaper::escape(trim((string)$filters['search'])) . '%';
            $query->whereRaw('(title LIKE ? OR timezone LIKE ?)', [$search, $search]);
        }

        return $query;
    }

    public function findCalendarByPublicId(string $publicId): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('business_calendars')
            ->select(['*'])
            ->where('public_id', '=', $publicId)
            ->first();

        return $row ?: null;
    }

    public function createCalendar(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('business_calendars')
            ->insert($payload);
    }

    public function updateCalendarByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('business_calendars')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function deleteCalendarByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('business_calendars')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }

    public function listHolidays(string $calendarPublicId, array $filters): array
    {
        $calendar = $this->findCalendarByPublicId($calendarPublicId);
        if (!$calendar) {
            return [null, 0, 1, 20];
        }

        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $calendarId = (int)$calendar['id'];
        $total = $this->buildHolidaysListQuery($calendarId, $filters)->count();
        $items = $this->buildHolidaysListQuery($calendarId, $filters)
            ->select(['h.public_id', 'h.holiday_date', 'h.title', 'h.created_at'])
            ->orderBy('h.holiday_date', 'ASC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildHolidaysListQuery(int $calendarId, array $filters): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('holidays h')
            ->where('h.calendar_id', '=', $calendarId);

        if (!empty($filters['from'])) {
            $query->where('h.holiday_date', '>=', (string)$filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->where('h.holiday_date', '<=', (string)$filters['to']);
        }

        return $query;
    }

    public function findHolidayByPublicId(string $publicId): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('holidays h')
            ->join('business_calendars bc', 'bc.id', '=', 'h.calendar_id')
            ->select(['h.*', 'bc.public_id AS calendar_public_id'])
            ->where('h.public_id', '=', $publicId)
            ->first();

        return $row ?: null;
    }

    public function createHoliday(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('holidays')
            ->insert($payload);
    }

    public function updateHolidayByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('holidays')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function deleteHolidayByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('holidays')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }

    public function listWorkingHours(string $calendarPublicId, array $filters): array
    {
        $calendar = $this->findCalendarByPublicId($calendarPublicId);
        if (!$calendar) {
            return [null, 0, 1, 20];
        }

        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $calendarId = (int)$calendar['id'];
        $total = $this->buildWorkingHoursListQuery($calendarId, $filters)->count();
        $items = $this->buildWorkingHoursListQuery($calendarId, $filters)
            ->select(['w.public_id', 'w.weekday', 'w.start_time', 'w.end_time', 'w.created_at', 'w.updated_at'])
            ->orderBy('w.weekday', 'ASC')
            ->orderBy('w.start_time', 'ASC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildWorkingHoursListQuery(int $calendarId, array $filters): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('working_hours w')
            ->where('w.calendar_id', '=', $calendarId);

        if (array_key_exists('weekday', $filters) && $filters['weekday'] !== '') {
            $query->where('w.weekday', '=', (int)$filters['weekday']);
        }

        return $query;
    }

    public function findWorkingHoursByPublicId(string $publicId): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('working_hours w')
            ->join('business_calendars bc', 'bc.id', '=', 'w.calendar_id')
            ->select(['w.*', 'bc.public_id AS calendar_public_id'])
            ->where('w.public_id', '=', $publicId)
            ->first();

        return $row ?: null;
    }

    public function createWorkingHours(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('working_hours')
            ->insert($payload);
    }

    public function updateWorkingHoursByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('working_hours')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function deleteWorkingHoursByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('working_hours')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }
}
