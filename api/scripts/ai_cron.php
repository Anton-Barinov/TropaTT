#!/usr/bin/env php
<?php
declare(strict_types=1);
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }


use Api\System\Library\App;

require_once __DIR__ . '/../system/library/support/Autoloader.php';

$autoloader = new Api\System\Library\Support\Autoloader(dirname(__DIR__));
$autoloader->register();

/** @var list<string> $argv */
$argv = $_SERVER['argv'] ?? [];

const SUPPORTED_JOB_CODES = [
    'ai:user-daily-work-plan',
    'ai:user-daily-digest',
    'ai:user-weekly-plan',
    'ai:manager-weekly-digest',
    'ai:task-risk-scan',
    'ai:task-quality-scan',
    'ai:task-decomposition-scan',
    'ai:meeting-agenda',
    'ai:project-daily-summary',
    'ai:client-weekly-report',
    'ai:team-workload-scan',
    'ai:sla-approval-scan',
    'ai:data-quality-scan',
    'ai:import-review',
    'ai:security-log-review',
    'ai:webhook-health-review',
    'ai:workflow-audit',
    'ai:semantic-index-refresh',
    'ai:suggestion-cleanup',
];

main($argv);

/**
 * @param list<string> $argv
 */
function main(array $argv): void
{
    [$jobCode, $options] = parseCliArgs($argv);

    if (($options['help'] ?? false) === true || ($jobCode === '' && ($options['all'] ?? false) !== true)) {
        printUsage();
        exit(($jobCode === '' && ($options['all'] ?? false) !== true) ? 1 : 0);
    }

    if ($jobCode === 'list') {
        echo "Supported job codes:\n";
        foreach (SUPPORTED_JOB_CODES as $code) {
            echo ' - ' . $code . "\n";
        }
        exit(0);
    }

    if (($options['all'] ?? false) !== true && !in_array($jobCode, SUPPORTED_JOB_CODES, true)) {
        fwrite(STDERR, "Unsupported job code: {$jobCode}\n");
        fwrite(STDERR, "Use 'list' to show supported job codes.\n");
        exit(2);
    }

    $endpointAction = ($options['run_once'] ?? false) ? 'run-once' : 'dry-run';

    $authToken = resolveAuthToken($options);
    if ($authToken === '') {
        fwrite(STDERR, "Cannot authenticate for AI cron endpoint.\n");
        fwrite(STDERR, "Provide CRM_AI_CRON_BEARER_TOKEN or CRM_AI_CRON_PASSWORD (optionally CRM_AI_CRON_LOGIN, CRM_AI_CRON_TOTP).\n");
        exit(3);
    }

    $payload = [];
    if (($options['with_provider'] ?? false) === true) {
        $payload['with_provider'] = true;
    }
    if (($options['scope_public_id'] ?? '') !== '') {
        $payload['scope_public_id'] = (string)$options['scope_public_id'];
    }
    if (($options['timezone'] ?? '') !== '') {
        $payload['timezone'] = (string)$options['timezone'];
    }
    if (($options['date'] ?? '') !== '') {
        $payload['date'] = (string)$options['date'];
    }
    if (($options['service_actor'] ?? false) === true) {
        $payload['run_as_service'] = true;
    }

    $codesToRun = ($options['all'] ?? false) === true ? SUPPORTED_JOB_CODES : [$jobCode];
    $failed = 0;
    $results = [];
    foreach ($codesToRun as $codeToRun) {
        $uri = '/api/v1/ai/jobs/' . $codeToRun . '/' . $endpointAction;
        $response = apiRequest('POST', $uri, $payload, [
            'Authorization' => 'Bearer ' . $authToken,
            'X-Correlation-ID' => 'ai-cron-cli-' . bin2hex(random_bytes(4)),
        ]);

        $ok = ($response['status'] >= 200 && $response['status'] < 300)
            && (bool)($response['payload']['success'] ?? false);
        if (!$ok) {
            $failed++;
        }

        $results[] = [
            'job_code' => $codeToRun,
            'ok' => $ok,
            'response' => $response,
        ];

        if (($options['json'] ?? false) === true) {
            continue;
        }
        printHumanOutput($codeToRun, $endpointAction, $response);
    }

    if (($options['json'] ?? false) === true) {
        $normalized = [];
        foreach ($results as $result) {
            $normalized[] = [
                'job_code' => (string)($result['job_code'] ?? ''),
                'ok' => (bool)($result['ok'] ?? false),
                'response' => (array)($result['response']['payload'] ?? []),
                'http_status' => (int)($result['response']['status'] ?? 0),
            ];
        }
        echo json_encode(['items' => $normalized, 'failed' => $failed, 'total' => count($normalized)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }

    exit($failed === 0 ? 0 : 4);
}

/**
 * @param list<string> $argv
 * @return array{0:string,1:array<string,mixed>}
 */
function parseCliArgs(array $argv): array
{
    $jobCode = '';
    $options = [
        'run_once' => false,
        'with_provider' => false,
        'all' => false,
        'service_actor' => false,
        'json' => false,
        'help' => false,
        'scope_public_id' => '',
        'timezone' => '',
        'date' => '',
        'token' => '',
        'login' => '',
        'password' => '',
        'totp' => '',
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--run-once') {
            $options['run_once'] = true;
            continue;
        }
        if ($arg === '--with-provider') {
            $options['with_provider'] = true;
            continue;
        }
        if ($arg === '--all') {
            $options['all'] = true;
            continue;
        }
        if ($arg === '--service-actor') {
            $options['service_actor'] = true;
            continue;
        }
        if ($arg === '--json') {
            $options['json'] = true;
            continue;
        }
        if ($arg === '--help' || $arg === '-h') {
            $options['help'] = true;
            continue;
        }
        if (str_starts_with($arg, '--scope-public-id=')) {
            $options['scope_public_id'] = trim(substr($arg, strlen('--scope-public-id=')));
            continue;
        }
        if (str_starts_with($arg, '--timezone=')) {
            $options['timezone'] = trim(substr($arg, strlen('--timezone=')));
            continue;
        }
        if (str_starts_with($arg, '--date=')) {
            $options['date'] = trim(substr($arg, strlen('--date=')));
            continue;
        }
        if (str_starts_with($arg, '--token=')) {
            $options['token'] = trim(substr($arg, strlen('--token=')));
            continue;
        }
        if (str_starts_with($arg, '--login=')) {
            $options['login'] = trim(substr($arg, strlen('--login=')));
            continue;
        }
        if (str_starts_with($arg, '--password=')) {
            $options['password'] = trim(substr($arg, strlen('--password=')));
            continue;
        }
        if (str_starts_with($arg, '--totp=')) {
            $options['totp'] = trim(substr($arg, strlen('--totp=')));
            continue;
        }

        if ($jobCode === '') {
            $jobCode = trim($arg);
        }
    }

    return [$jobCode, $options];
}

/** @param array<string,mixed> $options */
function resolveAuthToken(array $options): string
{
    $directToken = trim((string)($options['token'] ?? ''));
    if ($directToken === '') {
        $directToken = trim((string)getenv('CRM_AI_CRON_BEARER_TOKEN'));
    }
    if ($directToken !== '') {
        return $directToken;
    }

    $login = trim((string)($options['login'] ?? ''));
    if ($login === '') {
        $login = trim((string)getenv('CRM_AI_CRON_LOGIN'));
    }
    if ($login === '') {
        $login = trim((string)getenv('CRM_TEST_ROOT_LOGIN'));
    }
    if ($login === '') {
        $login = 'root';
    }

    $passwordCandidates = [];
    $passwordFromOption = trim((string)($options['password'] ?? ''));
    if ($passwordFromOption !== '') {
        $passwordCandidates[] = $passwordFromOption;
    }
    $passwordFromEnv = trim((string)getenv('CRM_AI_CRON_PASSWORD'));
    if ($passwordFromEnv !== '') {
        $passwordCandidates[] = $passwordFromEnv;
    }
    $passwordFromTestEnv = trim((string)getenv('CRM_TEST_ROOT_PASSWORD'));
    if ($passwordFromTestEnv !== '') {
        $passwordCandidates[] = $passwordFromTestEnv;
    }
    $passwordCandidates = array_values(array_unique(array_filter($passwordCandidates, static fn(string $v): bool => $v !== '')));

    $tokenCandidates = [];
    $tokenFromOption = trim((string)($options['totp'] ?? ''));
    if ($tokenFromOption !== '') {
        $tokenCandidates[] = $tokenFromOption;
    }
    $tokenFromEnv = trim((string)getenv('CRM_AI_CRON_TOTP'));
    if ($tokenFromEnv !== '') {
        $tokenCandidates[] = $tokenFromEnv;
    }
    $tokenFromTestEnv = trim((string)getenv('CRM_TEST_ROOT_TOKEN'));
    if ($tokenFromTestEnv !== '') {
        $tokenCandidates[] = $tokenFromTestEnv;
    }
    $tokenCandidates[] = '';
    $tokenCandidates[] = 'RootToken#2026!';
    $tokenCandidates = array_values(array_unique($tokenCandidates));

    foreach ($passwordCandidates as $password) {
        foreach ($tokenCandidates as $token) {
            $loginPayload = [
                'login' => $login,
                'password' => $password,
            ];
            if ($token !== '') {
                $loginPayload['token'] = $token;
            }

            $loginResponse = apiRequest('POST', '/api/v1/auth/login', $loginPayload, [
                'X-Correlation-ID' => 'ai-cron-cli-login-' . bin2hex(random_bytes(4)),
            ]);
            if (($loginResponse['status'] ?? 0) !== 200 || !(bool)($loginResponse['payload']['success'] ?? false)) {
                continue;
            }

            $accessToken = trim((string)($loginResponse['payload']['data']['access_token'] ?? ''));
            if ($accessToken !== '') {
                return $accessToken;
            }
        }
    }

    return '';
}

/**
 * @param array<string,mixed> $payload
 * @param array<string,string> $headers
 * @return array{status:int,payload:array<string,mixed>}
 */
function apiRequest(string $method, string $uri, array $payload = [], array $headers = []): array
{
    $_GET = [];
    $_POST = $payload;
    $_FILES = [];
    $_COOKIE = [];

    if (str_contains($uri, '?')) {
        [, $query] = explode('?', $uri, 2);
        parse_str($query, $_GET);
    }

    $_SERVER = [
        'REQUEST_METHOD' => strtoupper($method),
        'REQUEST_URI' => $uri,
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_USER_AGENT' => 'crm-ai-cron-cli/1.0',
    ];

    foreach ($headers as $name => $value) {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $_SERVER[$serverKey] = $value;
    }

    $app = new App(dirname(__DIR__));
    $response = $app->run();

    $payloadOut = $response->payload();
    if (!is_array($payloadOut)) {
        $payloadOut = ['success' => false, 'code' => 'INTERNAL_ERROR', 'message' => 'Invalid API response'];
    }

    return [
        'status' => $response->status(),
        'payload' => $payloadOut,
    ];
}

/**
 * @param array{status:int,payload:array<string,mixed>} $response
 */
function printHumanOutput(string $jobCode, string $action, array $response): void
{
    $payload = $response['payload'];
    $status = (int)($response['status'] ?? 0);
    $success = (bool)($payload['success'] ?? false);
    $code = (string)($payload['code'] ?? 'UNKNOWN');
    $message = trim((string)($payload['message'] ?? ''));

    echo sprintf("[%s] %s (%s)\n", $success ? 'OK' : 'ERROR', $jobCode, $action);
    echo sprintf("HTTP: %d | CODE: %s\n", $status, $code);
    if ($message !== '') {
        echo "Message: {$message}\n";
    }

    if ($success && $action === 'dry-run') {
        $dryRun = is_array($payload['data']['dry_run'] ?? null) ? $payload['data']['dry_run'] : [];
        $canRun = (bool)($dryRun['can_run'] ?? false);
        echo 'Can run: ' . ($canRun ? 'yes' : 'no') . "\n";

        $checks = is_array($dryRun['checks'] ?? null) ? $dryRun['checks'] : [];
        foreach ($checks as $check) {
            if (!is_array($check)) {
                continue;
            }
            $name = (string)($check['name'] ?? 'check');
            $ok = (bool)($check['ok'] ?? false);
            echo sprintf(" - %s: %s\n", $name, $ok ? 'OK' : 'FAIL');
        }

        $warnings = is_array($dryRun['warnings'] ?? null) ? $dryRun['warnings'] : [];
        foreach ($warnings as $warning) {
            $text = trim((string)$warning);
            if ($text !== '') {
                echo ' ! warning: ' . $text . "\n";
            }
        }
    }

    if ($success && $action === 'run-once') {
        $job = is_array($payload['data']['job'] ?? null) ? $payload['data']['job'] : [];
        $publicId = (string)($job['public_id'] ?? '');
        $jobStatus = (string)($job['status'] ?? '');
        if ($publicId !== '') {
            echo 'Queued job public_id: ' . $publicId . "\n";
        }
        if ($jobStatus !== '') {
            echo 'Queued job status: ' . $jobStatus . "\n";
        }
    }
}

function printUsage(): void
{
    $usage = <<<TXT
Usage:
  php api/scripts/ai_cron.php <job_code> [options]

Job code:
  list
  ai:user-daily-work-plan
  ai:user-daily-digest
  ai:user-weekly-plan
  ai:manager-weekly-digest
  ai:task-risk-scan
  ai:task-quality-scan
  ai:task-decomposition-scan
  ai:meeting-agenda
  ai:project-daily-summary
  ai:client-weekly-report
  ai:team-workload-scan
  ai:sla-approval-scan
  ai:data-quality-scan
  ai:import-review
  ai:security-log-review
  ai:webhook-health-review
  ai:workflow-audit
  ai:semantic-index-refresh
  ai:suggestion-cleanup

Default mode:
  dry-run (calls POST /api/v1/ai/jobs/{job_code}/dry-run)

Options:
  --run-once            Queue one run (POST /api/v1/ai/jobs/{job_code}/run-once)
  --all                 Run selected mode for all supported job codes (batch continues on errors)
  --service-actor       Queue run-once as service actor (owner keeps visibility via user scope)
  --with-provider       Include provider-config check marker in dry-run input
  --scope-public-id=ID  Optional scope override for system jobs
  --timezone=TZ         Optional timezone in input payload
  --date=YYYY-MM-DD     Optional date in input payload
  --token=TOKEN         Bearer token override
  --login=LOGIN         Login for auth/login fallback (default: env or root)
  --password=PASSWORD   Password for auth/login fallback
  --totp=CODE           Optional TOTP code for auth/login fallback
  --json                Print raw JSON envelope
  --help, -h            Show help

Environment fallback:
  CRM_AI_CRON_BEARER_TOKEN
  CRM_AI_CRON_LOGIN
  CRM_AI_CRON_PASSWORD
  CRM_AI_CRON_TOTP

Examples:
  php api/scripts/ai_cron.php ai:user-daily-work-plan
  php api/scripts/ai_cron.php ai:user-daily-digest --run-once
  php api/scripts/ai_cron.php list
  php api/scripts/ai_cron.php ai:user-daily-work-plan --all
  php api/scripts/ai_cron.php ai:task-risk-scan --json
TXT;

    echo $usage . "\n";
}
