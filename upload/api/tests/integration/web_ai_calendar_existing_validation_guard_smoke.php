<?php declare(strict_types=1);

$root = dirname(__DIR__, 3);
$bindingsPath = $root . '/web/assets/js/page-api-bindings.js';
$calendarControllerPath = $root . '/api/controller/calendar/CalendarController.php';

function failCalendarGuard(string $message): void
{
    fwrite(STDERR, "[FAIL] web_ai_calendar_existing_validation_guard_smoke: {$message}\n");
    exit(1);
}

function readCalendarGuard(string $path): string
{
    if (!is_file($path)) {
        failCalendarGuard('file not found: ' . $path);
    }
    $content = file_get_contents($path);
    if ($content === false) {
        failCalendarGuard('unable to read file: ' . $path);
    }
    return $content;
}

function assertContainsCalendarGuard(string $haystack, string $needle, string $message): void
{
    if (strpos($haystack, $needle) === false) {
        failCalendarGuard($message . ' (needle: ' . $needle . ')');
    }
}

$bindings = readCalendarGuard($bindingsPath);
$calendarController = readCalendarGuard($calendarControllerPath);

assertContainsCalendarGuard($calendarController, '$this->validateEventDates($v, $input, forbidPast: true);', 'Calendar create controller must enforce shared date validation');
assertContainsCalendarGuard($calendarController, "event_past_forbidden", 'Calendar controller must use canonical past-event validation message');
assertContainsCalendarGuard($bindings, "await request('api/v1/calendar/events'", 'AI event apply must use canonical calendar create endpoint');
assertContainsCalendarGuard($bindings, "slotStartMs < Date.now()", 'Calendar day AI apply must guard past slots before POST');
assertContainsCalendarGuard($bindings, "eventDraft.is_past", 'My-week AI apply must guard past suggested events before POST');
assertContainsCalendarGuard($bindings, "Событие в прошлом: создание заблокировано.", 'My-week AI UI must mark past events as blocked');
assertContainsCalendarGuard($bindings, "Пропущено событие в прошлом:", 'My-week AI apply must warn when skipping past events');

fwrite(STDOUT, "[OK] web_ai_calendar_existing_validation_guard_smoke\n");
