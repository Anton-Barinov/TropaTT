<?php
declare(strict_types=1);

/**
 * Updater rescue page - last-resort recovery while maintenance mode holds.
 *
 * Two authentication methods:
 * 1. Recovery key (generated at install, rotatable from admin-updates).
 * 2. APP_KEY from .env (always available to the server admin via SSH/file manager).
 *
 * The recovery key is NEVER generated here: a lazy on-visit generation would
 * hand a full recovery key to any anonymous visitor. If the key file is
 * missing, the page explains how to authenticate with APP_KEY instead.
 */

$basePath = dirname(__DIR__);
$storage = $basePath . '/storage_api/updates';
$hashFile = $storage . '/recovery_key.hash';
$provided = (string)($_GET['key'] ?? $_POST['key'] ?? '');

$maintenanceOn = is_file($basePath . '/storage_api/maintenance.flag');

/**
 * Load APP_KEY from .env files (same locations the application checks).
 */
function loadAppKey(string $basePath): string
{
    $candidates = [
        $basePath . '/.env',
        $basePath . '/api/.env',
        dirname($basePath) . '/.env',
    ];
    foreach ($candidates as $envFile) {
        if (!is_file($envFile)) {
            continue;
        }
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            continue;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (preg_match('/^APP_KEY\s*=\s*(.+)$/i', $line, $m)) {
                return trim($m[1], " \t\"'");
            }
        }
    }
    return '';
}

/**
 * Verify the provided secret against either the recovery key hash or APP_KEY.
 */
function verifySecret(string $provided, string $hashFile, string $appKey): bool
{
    if ($provided === '') {
        return false;
    }
    // Method 1: recovery key hash
    if (is_file($hashFile)) {
        $hash = trim((string)file_get_contents($hashFile));
        if (password_verify($provided, $hash)) {
            return true;
        }
    }
    // Method 2: APP_KEY (constant-time comparison)
    if ($appKey !== '' && hash_equals($appKey, $provided)) {
        return true;
    }
    return false;
}

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
        . '.info{border-left:4px solid #0b6bcb;background:#eff6ff;padding:10px 14px;border-radius:0 8px 8px 0;margin:14px 0}'
        . '</style></head><body><div class="card">'
        . '<span class="pill ' . ($GLOBALS['__rescueMaintenance'] ? 'on' : 'off') . '">'
        . ($GLOBALS['__rescueMaintenance'] ? 'Maintenance is ON' : 'Maintenance is OFF') . '</span>'
        . '<h1>' . $escape($title) . '</h1>' . $bodyHtml
        . '</div></body></html>';
}

$GLOBALS['__rescueMaintenance'] = $maintenanceOn;

$appKey = loadAppKey($basePath);

// --- Authentication ---
if (!verifySecret($provided, $hashFile, $appKey)) {
    $methods = '<h2>Authentication</h2>'
        . '<p>You can authenticate with either:</p>'
        . '<ul>'
        . '<li><strong>Recovery key</strong> &mdash; generated at installation, '
        . 'shown once on the completion page and re-issuable from '
        . '<code>index.php?route=admin-updates</code> &rarr; <strong>Emergency recovery</strong>.</li>'
        . '<li><strong>APP_KEY</strong> &mdash; found in the <code>.env</code> file '
        . 'on the server (e.g. <code>cat .env | grep APP_KEY</code> via SSH).</li>'
        . '</ul>';

    if (!is_file($hashFile) && $appKey === '') {
        rescuePage('No credentials available', '<p>'
            . 'Neither the recovery key file nor the APP_KEY have been configured. '
            . 'Please set up at least one to use this recovery page.</p>'
            . '<div class="warn"><strong>How to proceed:</strong> '
            . 'Delete the <code>storage_api/updates/recovery_key.hash</code> file '
            . '(if it exists), then log in to the CRM and generate a new recovery key '
            . 'from the updates page. Alternatively, set <code>APP_KEY</code> in your '
            . '<code>.env</code> file.</div>', 403);
        exit;
    }

    rescuePage('Recovery access denied', $methods
        . '<form method="post">'
        . '<input name="key" type="password" placeholder="Recovery key or APP_KEY" autocomplete="off">'
        . '<button type="submit">Open recovery</button>'
        . '</form>', 403);
    exit;
}

// --- Authenticated: show recovery tools ---

// Regenerate the recovery key in the session for the redirect after actions
$_SESSION['rescue_key'] = $provided;

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

if (($_POST['action'] ?? '') === 'delete_lock') {
    @unlink($storage . '/locks/update.lock');
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
// Show lock removal button if a lock file exists
$lockFile = $storage . '/locks/update.lock';
if (is_file($lockFile)) {
    $body .= '<form method="post" style="margin-top:10px"><input type="hidden" name="key" value="' . htmlspecialchars($provided, ENT_QUOTES, 'UTF-8') . '">'
        . '<button name="action" value="delete_lock">Force remove update lock</button></form>';
}
$body .= '<h2>Latest job</h2><pre>' . htmlspecialchars($state, ENT_QUOTES, 'UTF-8') . '</pre>'
    . '<h2>Backups</h2><pre>' . htmlspecialchars(implode("\n", $backups), ENT_QUOTES, 'UTF-8') . '</pre>'
    . '<h2>Latest log</h2><pre>' . htmlspecialchars($log, ENT_QUOTES, 'UTF-8') . '</pre>';
rescuePage('TropaTT Recovery', $body);
