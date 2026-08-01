<?php
declare(strict_types=1);

function failCalendarUx(string $message): void
{
    fwrite(STDERR, "[FAIL] web_calendar_ux_security_contract_smoke: {$message}\n");
    exit(1);
}

function readCalendarUxFile(string $path): string
{
    $content = file_get_contents($path);
    if (!is_string($content) || $content === '') {
        failCalendarUx('Cannot read file: ' . $path);
    }
    return $content;
}

function assertCalendarContains(string $haystack, string $needle, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        failCalendarUx($message . ': ' . $needle);
    }
}

$root = dirname(__DIR__, 3);
$calendarTemplate = readCalendarUxFile($root . '/web/view/template/page/calendar.php');
$modals = readCalendarUxFile($root . '/web/assets/js/modals.js');
$br1 = readCalendarUxFile($root . '/web/assets/js/br1.js');
$bindings = readCalendarUxFile($root . '/web/assets/js/page-api-bindings.js');

foreach ([
    'data-calendar-view-toggle',
    'data-calendar-ai-day-plan',
    'data-calendar-ai-plan-generate-btn',
    'data-calendar-ai-plan-apply-btn',
] as $needle) {
    assertCalendarContains($calendarTemplate, $needle, 'Calendar template missing UX contract');
}

foreach ([
    '<form id="calendarEventForm" novalidate>',
] as $needle) {
    assertCalendarContains($modals, $needle, 'Calendar modal missing form contract');
}

foreach ([
    'window.CRM.calendarOpenEventEditor',
    'setCalendarFormMode',
    'prepareCalendarCreate',
    'showCalendarFormErrors',
    "method: editId ? 'PATCH' : 'POST'",
    'calendarEditId',
] as $needle) {
    assertCalendarContains($br1, $needle, 'Calendar form handler missing create/edit contract');
}

foreach ([
    'function confirmCalendarAction(options)',
    "modal.style.zIndex = '1095'",
    "latestBackdrop.style.zIndex = '1090'",
    'data-calendar-event-edit-btn',
    'data-calendar-event-delete-btn',
    'Удалить событие?',
    'Создать события из AI-слотов?',
    "btn.setAttribute('aria-pressed'",
    'data-calendar-date=',
] as $needle) {
    assertCalendarContains($bindings, $needle, 'Calendar bindings missing UX/security contract');
}

$start = strpos($bindings, 'async function renderCalendarPage()');
$end = strpos($bindings, 'function notificationCategoryLabel', $start ?: 0);
if ($start === false || $end === false || $end <= $start) {
    failCalendarUx('Cannot isolate renderCalendarPage');
}
$calendarSource = substr($bindings, $start, $end - $start);
foreach (['window.confirm', 'window.prompt'] as $forbidden) {
    if (str_contains($calendarSource, $forbidden)) {
        failCalendarUx('Calendar page still contains forbidden native dialog: ' . $forbidden);
    }
}

fwrite(STDOUT, "[OK] web_calendar_ux_security_contract_smoke\n");
