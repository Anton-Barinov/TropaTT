<?php
declare(strict_types=1);

$basePath = dirname(__DIR__);
$storage = $basePath . '/storage_api/updates';
$hashFile = $storage . '/recovery_key.hash';
$provided = (string)($_GET['key'] ?? $_POST['key'] ?? '');

if (!is_file($hashFile)) {
    if (!is_dir($storage)) {
        mkdir($storage, 0775, true);
    }
    $key = bin2hex(random_bytes(16));
    file_put_contents($hashFile, password_hash($key, PASSWORD_DEFAULT));
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>TropaTT Recovery Key Created</h1><p>Save this key now. It will not be shown again.</p><pre>' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '</pre>';
    exit;
}

if ($provided === '' || !password_verify($provided, trim((string)file_get_contents($hashFile)))) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Recovery access denied</h1><form method="post"><input name="key" type="password" placeholder="Recovery key"><button>Open recovery</button></form>';
    exit;
}

$jobs = glob($storage . '/jobs/*/state.json') ?: [];
rsort($jobs);
$backups = glob($storage . '/backups/*') ?: [];
rsort($backups);
$latestJobDir = $jobs ? dirname($jobs[0]) : null;
$log = $latestJobDir && is_file($latestJobDir . '/log.jsonl') ? (string)file_get_contents($latestJobDir . '/log.jsonl') : '';

if (($_POST['action'] ?? '') === 'disable_maintenance') {
    @unlink($basePath . '/storage_api/maintenance.flag');
    header('Location: /updater/rescue.php?key=' . rawurlencode($provided));
    exit;
}

header('Content-Type: text/html; charset=utf-8');
echo '<h1>TropaTT Recovery</h1>';
echo '<form method="post"><input type="hidden" name="key" value="' . htmlspecialchars($provided, ENT_QUOTES, 'UTF-8') . '"><button name="action" value="disable_maintenance">Disable maintenance</button></form>';
echo '<h2>Latest job</h2><pre>' . htmlspecialchars($latestJobDir && is_file($jobs[0]) ? (string)file_get_contents($jobs[0]) : 'No jobs', ENT_QUOTES, 'UTF-8') . '</pre>';
echo '<h2>Backups</h2><pre>' . htmlspecialchars(implode("\n", $backups), ENT_QUOTES, 'UTF-8') . '</pre>';
echo '<h2>Latest log</h2><pre>' . htmlspecialchars($log, ENT_QUOTES, 'UTF-8') . '</pre>';
