<?php
declare(strict_types=1);

/**
 * Updater rescue page - last-resort recovery while maintenance mode holds.
 *
 * The recovery key is a one-time secret generated at installation (and
 * rotatable from the admin-updates page). It is NEVER generated here: a
 * lazy on-visit generation would hand a full recovery key to any anonymous
 * visitor and silently lock real admins out of recovery. If the key file is
 * missing, this page explains how to obtain a key instead.
 */

$basePath = dirname(__DIR__);
$storage = $basePath . '/storage_api/updates';
$hashFile = $storage . '/recovery_key.hash';
$provided = (string)($_GET['key'] ?? $_POST['key'] ?? '');

$maintenanceOn = is_file($basePath . '/storage_api/maintenance.flag');

function rescuePage(string $title, string $bodyHtml, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    $escape = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . $escape($title) . '</title>'
        . '<style>'
        . 'body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f6f7f9;color:#1f2933;margin:0;padding:48px 16px}'
        . '.card{max-width:720px;margin:0 auto;background:#fff;border:1px solid #e1e5ea;border-radius:12px;padding:28px 32px;box-shadow:0 2px 10px rgba(15,23,42,.04)}'
        . 'h1{font-size:1.4rem;margin:0 0 10px}h2{font-size:1.05rem;margin:24px 0 8px}'
        . 'p{line-height:1.55;color:#52606d}code{background:#f1f3f5;padding:2px 6px;border-radius:6px;font-size:.9em}'
        . 'pre{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px;overflow:auto;font-size:.82rem;max-height:300px}'
        . 'input[type=password]{width:100%;padding:10px 12px;border:1px solid #cbd2d9;border-radius:8px;font-size:1rem;margin:8px 0 12px}'
        . 'button{background:#0b6bcb;color:#fff;border:0;padding:10px 18px;border-radius:8px;font-size:.95rem;cursor:pointer}'
        . 'button:hover{background:#0a5bb0}'
        . '.muted{color:#7b8794;font-size:.85rem}'
        . '.pill{display:inline-block;padding:3px 10px;border-radius:999px;font-size:.78rem;font-weight:600;margin-bottom:14px}'
        . '.pill.on{background:#fee2e2;color:#b91c1c}.pill.off{background:#dcfce7;color:#15803d}'
        . '.warn{border-left:4px solid #f59e0b;background:#fffbeb;padding:10px 14px;border-radius:0 8px 8px 0;margin:14px 0}'
        . '</style></head><body><div class="card">'
        . '<span class="pill ' . ($GLOBALS['__rescueMaintenance'] ? 'on' : 'off') . '">'
        . ($GLOBALS['__rescueMaintenance'] ? 'Maintenance is ON' : 'Maintenance is OFF') . '</span>'
        . '<h1>' . $escape($title) . '</h1>' . $bodyHtml
        . '</div></body></html>';
}

$GLOBALS['__rescueMaintenance'] = $maintenanceOn;

if (!is_file($hashFile)) {
    rescuePage('Recovery key is not configured', '<p>'
        . 'The updater recovery key has not been generated for this installation. '
        . 'It is created automatically during installation or can be generated at any time '
        . 'from the updates page in the admin panel.</p>'
        . '<div class="warn"><strong>How to get the key:</strong> log in to the CRM and open '
        . '<code>index.php?route=admin-updates</code> &rarr; section '
        . '<strong>&laquo;Emergency recovery&raquo;</strong> &rarr; '
        . 'click <em>&laquo;Show recovery key&raquo;</em>. The updates page stays reachable '
        . 'even while maintenance mode is on, so this always works.</div>'
        . '<p class="muted">If you do not have the key and cannot reach the admin panel, '
        . 'delete the <code>storage_api/updates/recovery_key.hash</code> file on the server '
        . 'only if you are sure no attacker can reach this URL, then return here to re-run this page '
        . '(a fresh key will be issued through the admin panel afterwards).</p>', 403);
    exit;
}

if ($provided === '' || !password_verify($provided, trim((string)file_get_contents($hashFile)))) {
    rescuePage('Recovery access denied', '<p>'
        . 'This recovery area is protected by the updater recovery key. '
        . 'The key is shown once at installation and can be re-issued at any time from '
        . '<code>index.php?route=admin-updates</code> &rarr; <strong>Emergency recovery</strong>.</p>'
        . '<form method="post"><input name="key" type="password" placeholder="Recovery key" autocomplete="off">'
        . '<button type="submit">Open recovery</button></form>', 403);
    exit;
}

$jobs = glob($storage . '/jobs/*/state.json') ?: [];
usort($jobs, static fn (string $a, string $b): int => @filemtime($b) <=> @filemtime($a));
$backups = glob($storage . '/backups/*') ?: [];
usort($backups, static fn (string $a, string $b): int => @filemtime($b) <=> @filemtime($a));
$latestJobDir = $jobs ? dirname($jobs[0]) : null;
$log = $latestJobDir && is_file($latestJobDir . '/log.jsonl') ? (string)file_get_contents($latestJobDir . '/log.jsonl') : '';

if (($_POST['action'] ?? '') === 'disable_maintenance') {
    @unlink($basePath . '/storage_api/maintenance.flag');
    header('Location: /updater/rescue.php?key=' . rawurlencode($provided));
    exit;
}

$state = $latestJobDir && is_file($jobs[0]) ? (string)file_get_contents($jobs[0]) : 'No jobs';
$body = '';
if ($maintenanceOn) {
    $body .= '<form method="post"><input type="hidden" name="key" value="' . htmlspecialchars($provided, ENT_QUOTES, 'UTF-8') . '">'
        . '<button name="action" value="disable_maintenance">Disable maintenance mode</button></form>'
        . '<p class="muted">After disabling maintenance, return to the CRM and finish or roll back '
        . 'the update from the updates page.</p>';
} else {
    $body .= '<p>Maintenance mode is currently off &mdash; the CRM should be reachable normally. '
        . 'If you still see the maintenance screen, clear the file '
        . '<code>storage_api/maintenance.flag</code> manually or use the button below.</p>'
        . '<form method="post"><input type="hidden" name="key" value="' . htmlspecialchars($provided, ENT_QUOTES, 'UTF-8') . '">'
        . '<button name="action" value="disable_maintenance">Remove maintenance flag anyway</button></form>';
}
$body .= '<h2>Latest job</h2><pre>' . htmlspecialchars($state, ENT_QUOTES, 'UTF-8') . '</pre>'
    . '<h2>Backups</h2><pre>' . htmlspecialchars(implode("\n", $backups), ENT_QUOTES, 'UTF-8') . '</pre>'
    . '<h2>Latest log</h2><pre>' . htmlspecialchars($log, ENT_QUOTES, 'UTF-8') . '</pre>';
rescuePage('TropaTT Recovery', $body);
