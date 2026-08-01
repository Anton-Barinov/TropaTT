<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$bindingsPath = $root . '/web/assets/js/page-api-bindings.js';
$br1Path = $root . '/web/assets/js/br1.js';

function failXss(string $message): void
{
    fwrite(STDERR, "[FAIL] web_xss_rendering_contract_smoke: {$message}\n");
    exit(1);
}

function readFileStrict(string $path): string
{
    if (!is_file($path)) {
        failXss("File not found: {$path}");
    }
    $content = file_get_contents($path);
    if ($content === false) {
        failXss("Unable to read file: {$path}");
    }
    return (string)$content;
}

function assertContainsText(string $haystack, string $needle, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        failXss($message . " (needle: {$needle})");
    }
}

$bindings = readFileStrict($bindingsPath);
$br1 = readFileStrict($br1Path);

// Helper contract: safeText must be bound to HTML escaping helper.
assertContainsText($bindings, 'return window.CRM.br1 ? window.CRM.br1.escapeHtml(value) : String(value || \'\');', 'safeText must proxy escapeHtml');

// Notifications: title/body/preview must pass through safeText.
assertContainsText($bindings, 'safeText(item.title || \'Уведомление\')', 'Notification card title must be escaped');
assertContainsText($bindings, 'safeText(notificationSecondaryLine(item))', 'Notification secondary line must be escaped');
assertContainsText($bindings, 'safeText(preview)', 'Notification preview must be escaped');
assertContainsText($bindings, 'data-popover-notification-action="mark-read"', 'Notification popover action hook must exist');

// Tasks list rendering must escape title/project/status values.
assertContainsText($bindings, 'safeText(item.title || \'Без названия\')', 'Task row title must be escaped');
assertContainsText($bindings, 'safeText(item.project_title || \'—\')', 'Task row project title must be escaped');
assertContainsText($bindings, 'safeText(statusLabel(item.status_code))', 'Task row status label must be escaped');

// Comments/mentions rendering in br1 must escape user-provided content.
assertContainsText($br1, "escapeHtml(item.body || '')", 'Comment body must be escaped');
assertContainsText($br1, "escapeHtml(item.author_name || item.author_login || 'Пользователь')", 'Comment author label must be escaped');

// AI preview rendering must escape values in preview list.
assertContainsText($br1, "return '<li><strong>' + escapeHtml(label) + '</strong>: ' + escapeHtml(value) + '</li>';", 'AI preview values must be escaped');

fwrite(STDOUT, "[OK] web_xss_rendering_contract_smoke\n");

