<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Standalone public CLI entry point: no private test bootstrap, no implicit
// migrations, no default credentials, and no HTTP request to our own hostname.
$argv = $_SERVER['argv'] ?? [];
$limit = 20;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        echo "Usage: php api/scripts/jobs_worker_run.php [--limit=1..100] [--json]\n";
        echo "Set CRM_JOBS_CRON_BEARER_TOKEN in the hosting user's private environment.\n";
        echo "Optional: CRM_JOBS_CRON_ORGANIZATION_PUBLIC_ID scopes the authenticated API request.\n";
        exit(0);
    }
    if ($arg === '--json') continue;
    if (preg_match('/\A--limit=([0-9]{1,3})\z/D', $arg, $match) && (int)$match[1] >= 1 && (int)$match[1] <= 100) {
        $limit = (int)$match[1];
        continue;
    }
    fwrite(STDERR, "Invalid option; use --help.\n");
    exit(2);
}

$basePath = dirname(__DIR__);
$projectRoot = dirname($basePath);
require_once $basePath . '/system/library/support/Autoloader.php';
(new \Api\System\Library\Support\Autoloader($basePath))->register();
$backgroundWriteGuard = \Api\System\Library\Support\BackgroundWriteGuard::enterOrExit($projectRoot, $argv);
\Api\System\Library\Support\EnvLoader::loadFiles([
    $projectRoot . '/.env', $basePath . '/.env', $projectRoot . '/.env.local', $basePath . '/.env.local',
]);
$token = trim((string)getenv('CRM_JOBS_CRON_BEARER_TOKEN'));
if ($token === '' || preg_match('/[\r\n]/', $token)) {
    fwrite(STDERR, "Explicit CRM_JOBS_CRON_BEARER_TOKEN is required.\n");
    exit(3);
}

try {
    $_GET = []; $_FILES = []; $_COOKIE = [];
    $_POST = ['limit' => $limit];
    $_SERVER = [
        'REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api/v1/ops/jobs/run',
        'REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'crm-jobs-cli/1.0',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
    ];
    $workspace = trim((string)getenv('CRM_JOBS_CRON_ORGANIZATION_PUBLIC_ID'));
    if ($workspace !== '') {
        if (!preg_match('/\Aorg_[A-Za-z0-9]{1,80}\z/D', $workspace)) {
            fwrite(STDERR, "Invalid CRM_JOBS_CRON_ORGANIZATION_PUBLIC_ID.\n");
            exit(2);
        }
        $_SERVER['HTTP_X_ORGANIZATION_PUBLIC_ID'] = $workspace;
    }
    // Normal REST authentication, permission and workspace checks still apply.
    $response = (new \Api\System\Library\App($basePath))->run();
    $payload = $response->payload();
    if ($response->status() !== 200 || !is_array($payload) || ($payload['success'] ?? false) !== true
        || ($payload['code'] ?? '') !== 'OPS_JOBS_RUN') {
        fwrite(STDERR, "[FAIL] Jobs API rejected or did not confirm execution.\n");
        exit(1);
    }
    $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
    echo json_encode([
        'ok' => true, 'limit' => $limit,
        'import' => (array)($data['import'] ?? []), 'export' => (array)($data['export'] ?? []),
        'push' => (array)($data['push'] ?? []), 'webhook' => (array)($data['webhook'] ?? []),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (\Throwable $e) {
    fwrite(STDERR, "[FAIL] Jobs execution failed; inspect the private application log.\n");
    exit(1);
}
