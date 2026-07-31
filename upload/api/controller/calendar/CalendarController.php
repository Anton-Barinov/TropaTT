<?php
declare(strict_types=1);

namespace Api\Controller\Calendar;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\BusinessCalendarService;
use Api\System\Library\Service\CalendarService;
use Api\System\Library\Validation\Validator;

final class CalendarController extends BaseController
{
    public function events(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $cache = $this->cacheApi();
        if ($cache !== null) {
            $input = $this->request()->allInput();
            ksort($input);
            $cacheKey = 'events:' . $this->cacheUserId() . ':' . hash('sha256', json_encode($input));
            $result = $cache->remember('calendar', $cacheKey, 60, function () use ($input, $authUser) {
                /** @var CalendarService $service */
                $service = $this->container->get('service.calendar');
                return $service->listEvents($input, $authUser['user']);
            });
        } else {
            /** @var CalendarService $service */
            $service = $this->container->get('service.calendar');
            $result = $service->listEvents($this->request()->allInput(), $authUser['user']);
        }

        return $this->success('CALENDAR_EVENT_LIST', $this->t('calendar/messages.event_list'), ['items' => $result['items']], meta: $result['meta']);
    }

    public function createEvent(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'title', $this->t('common/messages.field_required'))
            ->require($input, 'starts_at', $this->t('common/messages.field_required'))
            ->maxLen($input, 'title', 255, $this->t('calendar/messages.max_255'))
            ->date($input, 'starts_at', $this->t('common/messages.invalid_date'))
            ->date($input, 'ends_at', $this->t('common/messages.invalid_date'));
        $this->validateEventDates($v, $input, forbidPast: true);

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var CalendarService $service */
        $service = $this->container->get('service.calendar');
        $item = $service->createEvent($input, $authUser['user']);
        if ($item === 'PROJECT_NOT_FOUND') {
            return $this->error('PROJECT_NOT_FOUND', $this->t('calendar/messages.project_not_found'), 404, [
                'project_public_id' => [$this->t('calendar/messages.project_not_found')],
            ]);
        }
        if ($item === 'TASK_NOT_FOUND') {
            return $this->error('TASK_NOT_FOUND', $this->t('common/messages.task_not_found'), 404, [
                'task_public_id' => [$this->t('common/messages.task_not_found')],
            ]);
        }

        $this->invalidateCache('calendar');

        return $this->success('CALENDAR_EVENT_CREATED', $this->t('calendar/messages.event_created'), ['event' => $item], 201);
    }

    public function getEvent(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var CalendarService $service */
        $service = $this->container->get('service.calendar');
        $item = $service->getEvent((string)$params['public_id'], $authUser['user']);
        if (!$item) {
            return $this->error('CALENDAR_EVENT_NOT_FOUND', $this->t('calendar/messages.event_not_found'), 404, [
                'event' => [$this->t('calendar/messages.event_not_found')],
            ]);
        }

        return $this->success('CALENDAR_EVENT_DETAIL', $this->t('calendar/messages.event_detail'), ['event' => $item]);
    }

    public function updateEvent(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->maxLen($input, 'title', 255, $this->t('calendar/messages.max_255'))
            ->date($input, 'starts_at', $this->t('common/messages.invalid_date'))
            ->date($input, 'ends_at', $this->t('common/messages.invalid_date'));
        $this->validateEventDates($v, $input, forbidPast: array_key_exists('starts_at', $input));
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var CalendarService $service */
        $service = $this->container->get('service.calendar');
        $item = $service->updateEvent((string)$params['public_id'], $input, $authUser['user']);
        if ($item === null) {
            return $this->error('CALENDAR_EVENT_NOT_FOUND', $this->t('calendar/messages.event_not_found'), 404, [
                'event' => [$this->t('calendar/messages.event_not_found')],
            ]);
        }
        if ($item === 'PROJECT_NOT_FOUND') {
            return $this->error('PROJECT_NOT_FOUND', $this->t('calendar/messages.project_not_found'), 404, [
                'project_public_id' => [$this->t('calendar/messages.project_not_found')],
            ]);
        }
        if ($item === 'TASK_NOT_FOUND') {
            return $this->error('TASK_NOT_FOUND', $this->t('common/messages.task_not_found'), 404, [
                'task_public_id' => [$this->t('common/messages.task_not_found')],
            ]);
        }

        $this->invalidateCache('calendar');

        return $this->success('CALENDAR_EVENT_UPDATED', $this->t('calendar/messages.event_updated'), ['event' => $item]);
    }

    public function deleteEvent(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var CalendarService $service */
        $service = $this->container->get('service.calendar');
        $ok = $service->deleteEvent((string)$params['public_id'], $authUser['user']);
        if (!$ok) {
            return $this->error('CALENDAR_EVENT_NOT_FOUND', $this->t('calendar/messages.event_not_found'), 404, [
                'event' => [$this->t('calendar/messages.event_not_found')],
            ]);
        }

        $this->invalidateCache('calendar');

        return $this->success('CALENDAR_EVENT_DELETED', $this->t('calendar/messages.event_deleted'));
    }

    private function validateEventDates(Validator $validator, array $input, bool $forbidPast): void
    {
        $startsAtRaw = trim((string)($input['starts_at'] ?? ''));
        $endsAtRaw = trim((string)($input['ends_at'] ?? ''));
        $startsAtTs = $startsAtRaw !== '' ? strtotime($startsAtRaw) : false;
        $endsAtTs = $endsAtRaw !== '' ? strtotime($endsAtRaw) : false;

        if ($forbidPast && $startsAtTs !== false) {
            $today = new \DateTimeImmutable('today');
            $startsDay = (new \DateTimeImmutable('@' . $startsAtTs))->setTimezone(new \DateTimeZone(date_default_timezone_get()))->setTime(0, 0);
            if ($startsDay < $today) {
                $validator->addError('starts_at', $this->t('calendar/messages.event_past_forbidden'));
            }
        }

        if ($startsAtTs !== false && $endsAtTs !== false && $endsAtTs < $startsAtTs) {
            $validator->addError('ends_at', $this->t('calendar/messages.event_end_before_start'));
        }
    }

    public function myDay(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $cache = $this->cacheApi();
        if ($cache !== null) {
            $input = $this->request()->allInput();
            ksort($input);
            $cacheKey = 'myDay:' . $this->cacheUserId() . ':' . hash('sha256', json_encode($input));
            $payload = $cache->remember('calendar', $cacheKey, 60, function () use ($input, $authUser) {
                /** @var CalendarService $service */
                $service = $this->container->get('service.calendar');
                return $service->myDay($authUser['user'], (string)($input['date'] ?? ''));
            });
        } else {
            /** @var CalendarService $service */
            $service = $this->container->get('service.calendar');
            $payload = $service->myDay($authUser['user'], (string)($this->request()->allInput()['date'] ?? ''));
        }

        return $this->success('CALENDAR_MY_DAY', $this->t('calendar/messages.my_day'), $payload);
    }

    public function myWeek(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $cache = $this->cacheApi();
        if ($cache !== null) {
            $input = $this->request()->allInput();
            ksort($input);
            $cacheKey = 'myWeek:' . $this->cacheUserId() . ':' . hash('sha256', json_encode($input));
            $payload = $cache->remember('calendar', $cacheKey, 60, function () use ($input, $authUser) {
                /** @var CalendarService $service */
                $service = $this->container->get('service.calendar');
                return $service->myWeek($authUser['user'], (string)($input['date'] ?? ''));
            });
        } else {
            /** @var CalendarService $service */
            $service = $this->container->get('service.calendar');
            $payload = $service->myWeek($authUser['user'], (string)($this->request()->allInput()['date'] ?? ''));
        }

        return $this->success('CALENDAR_MY_WEEK', $this->t('calendar/messages.my_week'), $payload);
    }

    public function myMonth(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $cache = $this->cacheApi();
        if ($cache !== null) {
            $input = $this->request()->allInput();
            ksort($input);
            $cacheKey = 'myMonth:' . $this->cacheUserId() . ':' . hash('sha256', json_encode($input));
            $payload = $cache->remember('calendar', $cacheKey, 60, function () use ($input, $authUser) {
                /** @var CalendarService $service */
                $service = $this->container->get('service.calendar');
                return $service->myMonth($authUser['user'], (string)($input['date'] ?? ''));
            });
        } else {
            /** @var CalendarService $service */
            $service = $this->container->get('service.calendar');
            $payload = $service->myMonth($authUser['user'], (string)($this->request()->allInput()['date'] ?? ''));
        }

        return $this->success('CALENDAR_MY_MONTH', $this->t('calendar/messages.my_month'), $payload);
    }

    public function businessList(): \Api\System\Library\Http\JsonResponse
    {
        /** @var BusinessCalendarService $service */
        $service = $this->container->get('service.business_calendar');
        $result = $service->listCalendars($this->request()->allInput());

        return $this->success('BUSINESS_CALENDAR_LIST', $this->t('calendar/messages.business_list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function businessCreate(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'title', $this->t('common/messages.field_required'))
            ->maxLen($input, 'title', 255, $this->t('calendar/messages.max_255'))
            ->maxLen($input, 'timezone', 64, $this->t('calendar/messages.max_64'));
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var BusinessCalendarService $service */
        $service = $this->container->get('service.business_calendar');
        $item = $service->createCalendar($input, $authUser['user']);

        return $this->success('BUSINESS_CALENDAR_CREATED', $this->t('calendar/messages.business_created'), [
            'calendar' => $item,
        ], 201);
    }

    public function businessGet(array $params): \Api\System\Library\Http\JsonResponse
    {
        /** @var BusinessCalendarService $service */
        $service = $this->container->get('service.business_calendar');
        $item = $service->getCalendar((string)$params['public_id']);
        if (!$item) {
            return $this->error('BUSINESS_CALENDAR_NOT_FOUND', $this->t('calendar/messages.business_not_found'), 404, [
                'calendar' => [$this->t('calendar/messages.business_not_found')],
            ]);
        }

        return $this->success('BUSINESS_CALENDAR_DETAIL', $this->t('calendar/messages.business_detail'), [
            'calendar' => $item,
        ]);
    }

    public function businessUpdate(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->maxLen($input, 'title', 255, $this->t('calendar/messages.max_255'))
            ->maxLen($input, 'timezone', 64, $this->t('calendar/messages.max_64'));
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var BusinessCalendarService $service */
        $service = $this->container->get('service.business_calendar');
        $item = $service->updateCalendar((string)$params['public_id'], $input, $authUser['user']);
        if (!$item) {
            return $this->error('BUSINESS_CALENDAR_NOT_FOUND', $this->t('calendar/messages.business_not_found'), 404, [
                'calendar' => [$this->t('calendar/messages.business_not_found')],
            ]);
        }

        return $this->success('BUSINESS_CALENDAR_UPDATED', $this->t('calendar/messages.business_updated'), [
            'calendar' => $item,
        ]);
    }

    public function businessDelete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var BusinessCalendarService $service */
        $service = $this->container->get('service.business_calendar');
        $ok = $service->deleteCalendar((string)$params['public_id'], $authUser['user']);
        if (!$ok) {
            return $this->error('BUSINESS_CALENDAR_NOT_FOUND', $this->t('calendar/messages.business_not_found'), 404, [
                'calendar' => [$this->t('calendar/messages.business_not_found')],
            ]);
        }

        return $this->success('BUSINESS_CALENDAR_DELETED', $this->t('calendar/messages.business_deleted'));
    }

    public function holidaysList(): \Api\System\Library\Http\JsonResponse
    {
        $input = $this->request()->allInput();
        $calendarPublicId = trim((string)($input['calendar_public_id'] ?? ''));
        if ($calendarPublicId === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'calendar_public_id' => [$this->t('common/messages.field_required')],
            ]);
        }

        /** @var BusinessCalendarService $service */
        $service = $this->container->get('service.business_calendar');
        $result = $service->listHolidays($calendarPublicId, $input);
        if (!(bool)($result['ok'] ?? false)) {
            return $this->error('CALENDAR_NOT_FOUND', $this->t('calendar/messages.business_not_found'), 404, [
                'calendar_public_id' => [$this->t('calendar/messages.business_not_found')],
            ]);
        }

        return $this->success('CALENDAR_HOLIDAY_LIST', $this->t('calendar/messages.holiday_list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function holidaysCreate(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'calendar_public_id', $this->t('common/messages.field_required'))
            ->require($input, 'holiday_date', $this->t('common/messages.field_required'))
            ->require($input, 'title', $this->t('common/messages.field_required'))
            ->maxLen($input, 'calendar_public_id', 64, $this->t('calendar/messages.max_64'))
            ->maxLen($input, 'title', 255, $this->t('calendar/messages.max_255'));
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$input['holiday_date']) !== 1) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'holiday_date' => [$this->t('common/messages.invalid_date')],
            ]);
        }

        /** @var BusinessCalendarService $service */
        $service = $this->container->get('service.business_calendar');
        $result = $service->createHoliday($input, $authUser['user']);
        if (!(bool)($result['ok'] ?? false)) {
            return $this->error('CALENDAR_NOT_FOUND', $this->t('calendar/messages.business_not_found'), 404, [
                'calendar_public_id' => [$this->t('calendar/messages.business_not_found')],
            ]);
        }

        return $this->success('CALENDAR_HOLIDAY_CREATED', $this->t('calendar/messages.holiday_created'), [
            'holiday' => $result['holiday'],
        ], 201);
    }

    public function holidaysGet(array $params): \Api\System\Library\Http\JsonResponse
    {
        /** @var BusinessCalendarService $service */
        $service = $this->container->get('service.business_calendar');
        $item = $service->getHoliday((string)$params['public_id']);
        if (!$item) {
            return $this->error('CALENDAR_HOLIDAY_NOT_FOUND', $this->t('calendar/messages.holiday_not_found'), 404, [
                'holiday' => [$this->t('calendar/messages.holiday_not_found')],
            ]);
        }

        return $this->success('CALENDAR_HOLIDAY_DETAIL', $this->t('calendar/messages.holiday_detail'), [
            'holiday' => $item,
        ]);
    }

    public function holidaysUpdate(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        if (array_key_exists('holiday_date', $input) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$input['holiday_date']) !== 1) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'holiday_date' => [$this->t('common/messages.invalid_date')],
            ]);
        }

        /** @var BusinessCalendarService $service */
        $service = $this->container->get('service.business_calendar');
        $item = $service->updateHoliday((string)$params['public_id'], $input, $authUser['user']);
        if (!$item) {
            return $this->error('CALENDAR_HOLIDAY_NOT_FOUND', $this->t('calendar/messages.holiday_not_found'), 404, [
                'holiday' => [$this->t('calendar/messages.holiday_not_found')],
            ]);
        }

        return $this->success('CALENDAR_HOLIDAY_UPDATED', $this->t('calendar/messages.holiday_updated'), [
            'holiday' => $item,
        ]);
    }

    public function holidaysDelete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var BusinessCalendarService $service */
        $service = $this->container->get('service.business_calendar');
        $ok = $service->deleteHoliday((string)$params['public_id'], $authUser['user']);
        if (!$ok) {
            return $this->error('CALENDAR_HOLIDAY_NOT_FOUND', $this->t('calendar/messages.holiday_not_found'), 404, [
                'holiday' => [$this->t('calendar/messages.holiday_not_found')],
            ]);
        }

        return $this->success('CALENDAR_HOLIDAY_DELETED', $this->t('calendar/messages.holiday_deleted'));
    }

    public function workingHoursList(): \Api\System\Library\Http\JsonResponse
    {
        $input = $this->request()->allInput();
        $calendarPublicId = trim((string)($input['calendar_public_id'] ?? ''));
        if ($calendarPublicId === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'calendar_public_id' => [$this->t('common/messages.field_required')],
            ]);
        }

        /** @var BusinessCalendarService $service */
        $service = $this->container->get('service.business_calendar');
        $result = $service->listWorkingHours($calendarPublicId, $input);
        if (!(bool)($result['ok'] ?? false)) {
            return $this->error('CALENDAR_NOT_FOUND', $this->t('calendar/messages.business_not_found'), 404, [
                'calendar_public_id' => [$this->t('calendar/messages.business_not_found')],
            ]);
        }

        return $this->success('CALENDAR_WORKING_HOURS_LIST', $this->t('calendar/messages.working_hours_list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function workingHoursCreate(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'calendar_public_id', $this->t('common/messages.field_required'))
            ->require($input, 'weekday', $this->t('common/messages.field_required'))
            ->require($input, 'start_time', $this->t('common/messages.field_required'))
            ->require($input, 'end_time', $this->t('common/messages.field_required'));
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        $weekday = (int)$input['weekday'];
        if ($weekday < 1 || $weekday > 7) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'weekday' => [$this->t('calendar/messages.weekday_range')],
            ]);
        }
        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string)$input['start_time']) !== 1) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'start_time' => [$this->t('calendar/messages.invalid_time')],
            ]);
        }
        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string)$input['end_time']) !== 1) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'end_time' => [$this->t('calendar/messages.invalid_time')],
            ]);
        }

        /** @var BusinessCalendarService $service */
        $service = $this->container->get('service.business_calendar');
        $result = $service->createWorkingHours($input, $authUser['user']);
        if (!(bool)($result['ok'] ?? false)) {
            return $this->error('CALENDAR_NOT_FOUND', $this->t('calendar/messages.business_not_found'), 404, [
                'calendar_public_id' => [$this->t('calendar/messages.business_not_found')],
            ]);
        }

        return $this->success('CALENDAR_WORKING_HOURS_CREATED', $this->t('calendar/messages.working_hours_created'), [
            'working_hours' => $result['working_hours'],
        ], 201);
    }

    public function workingHoursGet(array $params): \Api\System\Library\Http\JsonResponse
    {
        /** @var BusinessCalendarService $service */
        $service = $this->container->get('service.business_calendar');
        $item = $service->getWorkingHours((string)$params['public_id']);
        if (!$item) {
            return $this->error('CALENDAR_WORKING_HOURS_NOT_FOUND', $this->t('calendar/messages.working_hours_not_found'), 404, [
                'working_hours' => [$this->t('calendar/messages.working_hours_not_found')],
            ]);
        }

        return $this->success('CALENDAR_WORKING_HOURS_DETAIL', $this->t('calendar/messages.working_hours_detail'), [
            'working_hours' => $item,
        ]);
    }

    public function workingHoursUpdate(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        if (array_key_exists('weekday', $input)) {
            $weekday = (int)$input['weekday'];
            if ($weekday < 1 || $weekday > 7) {
                return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                    'weekday' => [$this->t('calendar/messages.weekday_range')],
                ]);
            }
        }
        if (array_key_exists('start_time', $input) && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string)$input['start_time']) !== 1) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'start_time' => [$this->t('calendar/messages.invalid_time')],
            ]);
        }
        if (array_key_exists('end_time', $input) && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string)$input['end_time']) !== 1) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'end_time' => [$this->t('calendar/messages.invalid_time')],
            ]);
        }

        /** @var BusinessCalendarService $service */
        $service = $this->container->get('service.business_calendar');
        $item = $service->updateWorkingHours((string)$params['public_id'], $input, $authUser['user']);
        if (!$item) {
            return $this->error('CALENDAR_WORKING_HOURS_NOT_FOUND', $this->t('calendar/messages.working_hours_not_found'), 404, [
                'working_hours' => [$this->t('calendar/messages.working_hours_not_found')],
            ]);
        }

        return $this->success('CALENDAR_WORKING_HOURS_UPDATED', $this->t('calendar/messages.working_hours_updated'), [
            'working_hours' => $item,
        ]);
    }

    public function workingHoursDelete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var BusinessCalendarService $service */
        $service = $this->container->get('service.business_calendar');
        $ok = $service->deleteWorkingHours((string)$params['public_id'], $authUser['user']);
        if (!$ok) {
            return $this->error('CALENDAR_WORKING_HOURS_NOT_FOUND', $this->t('calendar/messages.working_hours_not_found'), 404, [
                'working_hours' => [$this->t('calendar/messages.working_hours_not_found')],
            ]);
        }

        return $this->success('CALENDAR_WORKING_HOURS_DELETED', $this->t('calendar/messages.working_hours_deleted'));
    }
}
