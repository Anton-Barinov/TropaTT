<?php
declare(strict_types=1);
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }

/**
 * Idea analysis worker — processes pending live-pipeline steps.
 *
 * Usage:
 *   php idea_analysis_worker.php                     # process one step for all pending ideas
 *   php idea_analysis_worker.php --idea=IV_XXXXX     # process one step for a specific idea
 *   php idea_analysis_worker.php --all               # process all pending steps (loop until done)
 *   php idea_analysis_worker.php --limit=5           # process up to 5 steps
 *   php idea_analysis_worker.php --token=TOKEN       # explicit bearer token
 *   php idea_analysis_worker.php --json              # machine-readable output
 *   php idea_analysis_worker.php --help
 */

require_once __DIR__ . '/../system/library/support/Autoloader.php';

$autoloader = new Api\System\Library\Support\Autoloader(dirname(__DIR__));
$autoloader->register();

$argv = $_SERVER['argv'] ?? [];

main($argv);

function main(array $argv): void
{
    $options = parseCliArgs($argv);
    if ($options['help']) { printUsage(); exit(0); }

    $authToken = resolveAuthToken($options);
    if ($authToken === '') {
        fwrite(STDERR, "Cannot authenticate. Provide --token or set CRM_AI_CRON_BEARER_TOKEN / CRM_TEST_ROOT_PASSWORD.\n");
        exit(3);
    }

    $limit = max(1, min(50, $options['limit']));
    $runAll = $options['all'];
    $targetIdea = $options['idea'] ?? '';
    $results = [];
    $stepsDone = 0;
    $cleanStop = false;

    // Serialize overlapping runs: a cron tick that outlives its interval must
    // not start a second pass alongside the first one (double AI calls).
    // Advisory lock, released automatically when the process exits.
    $lockHandle = @fopen(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'crm_idea_analysis_worker.lock', 'c');
    if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
        fwrite(STDERR, "Another idea worker run is already in progress — exiting.\n");
        exit(0);
    }

    do {
        $payload = ['limit' => $limit];
        if ($targetIdea !== '') {
            $payload['public_id'] = $targetIdea;
        }

        $response = apiRequest('POST', '/api/v1/ideas/queue/run-worker', $payload, [
            'Authorization' => 'Bearer ' . $authToken,
        ]);

        $ok = ($response['status'] >= 200 && $response['status'] < 300)
            && (bool)($response['payload']['success'] ?? false);

        $results[] = [
            'ok' => $ok,
            'response' => $response,
        ];

        $code = (string)($response['payload']['code'] ?? '');
        if ($ok && in_array($code, ['NO_PENDING_STEP', 'AWAITING_HUMAN_INPUT', 'STEP_BUSY', 'NOT_FOUND'], true)) {
            // Nothing executable right now: no work left, or the idea waits for
            // the human / another run. Retrying in a tight loop would only spin.
            $cleanStop = true;
            break;
        }

        if ($ok) {
            $stepsDone++;
            $allDone = (bool)($response['payload']['data']['all_done'] ?? false);
            if ($allDone && $targetIdea !== '') {
                break; // specific idea completed
            }
        }

        if (!$ok) {
            $msg = (string)($response['payload']['message'] ?? '');
            fwrite(STDERR, "Step failed: [{$code}] {$msg}\n");
            if ($code === 'NO_PENDING_STEP' || $code === 'NOT_FOUND') {
                $cleanStop = true;
                break; // nothing left to do
            }
            if (!$runAll) {
                break;
            }
        }
    } while ($runAll && $stepsDone < $limit * 20); // safety cap

    if ($options['json']) {
        echo json_encode([
            'ok' => $stepsDone > 0 || $cleanStop,
            'steps_processed' => $stepsDone,
            'results' => $results,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "Steps processed: {$stepsDone}\n";
        foreach ($results as $r) {
            $status = $r['ok'] ? 'OK' : 'FAIL';
            $step = $r['response']['payload']['data']['step'] ?? '-';
            $code = (string)($r['response']['payload']['code'] ?? '');
            echo "  [{$status}] step={$step}" . ($code !== '' ? " ({$code})" : '') . "\n";
        }
    }

    exit($stepsDone > 0 || $cleanStop ? 0 : 1);
}

function parseCliArgs(array $argv): array
{
    $options = [
        'help' => false,
        'json' => false,
        'all' => false,
        'limit' => 1,
        'token' => '',
        'idea' => '',
    ];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help' || $arg === '-h') { $options['help'] = true; continue; }
        if ($arg === '--json') { $options['json'] = true; continue; }
        if ($arg === '--all') { $options['all'] = true; continue; }
        if (str_starts_with($arg, '--limit=')) { $options['limit'] = (int)substr($arg, 8); continue; }
        if (str_starts_with($arg, '--token=')) { $options['token'] = trim(substr($arg, 8)); continue; }
        if (str_starts_with($arg, '--idea=')) { $options['idea'] = trim(substr($arg, 7)); continue; }
    }
    return $options;
}

function resolveAuthToken(array $options): string
{
    $token = trim((string)($options['token'] ?? ''));
    if ($token !== '') return $token;

    $token = trim((string)getenv('CRM_AI_CRON_BEARER_TOKEN'));
    if ($token !== '') return $token;

    $password = trim((string)getenv('CRM_TEST_ROOT_PASSWORD'));
    if ($password === '') $password = 'adminadmin';
    $login = trim((string)getenv('CRM_TEST_ROOT_LOGIN'));
    if ($login === '') $login = 'admin';

    $loginResponse = apiRequest('POST', '/api/v1/auth/login', [
        'login' => $login,
        'password' => $password,
    ]);
    if (($loginResponse['status'] ?? 0) === 200 && (bool)($loginResponse['payload']['success'] ?? false)) {
        return trim((string)($loginResponse['payload']['data']['access_token'] ?? ''));
    }
    return '';
}

function apiRequest(string $method, string $uri, array $payload = [], array $headers = []): array
{
    $savedGet = $_GET;
    $savedPost = $_POST;
    $savedFiles = $_FILES;
    $savedCookie = $_COOKIE;
    $savedServer = $_SERVER;

    try {
        $_GET = [];
        $_FILES = [];
        $_COOKIE = [];
        $_POST = [];
        foreach ($payload as $key => $value) {
            if (is_string($key) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
                $_POST[$key] = $value;
            }
        }
        if (str_contains($uri, '?')) {
            [, $query] = explode('?', $uri, 2);
            $parsed = [];
            parse_str($query, $parsed);
            foreach ($parsed as $key => $value) {
                if (is_string($key) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
                    $_GET[$key] = $value;
                }
            }
        }
        $_SERVER = [
            'REQUEST_METHOD' => strtoupper($method),
            'REQUEST_URI' => $uri,
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'crm-idea-worker/1.0',
        ];
        foreach ($headers as $name => $value) {
            $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$name);
            if ($safeName === '') continue;
            $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $safeName))] = (string)$value;
        }

        $app = new \Api\System\Library\App(dirname(__DIR__));
        $response = $app->run();
        $payloadOut = $response->payload();
        if (!is_array($payloadOut)) {
            $payloadOut = ['success' => false, 'code' => 'INTERNAL_ERROR', 'message' => 'Invalid API response'];
        }
        return ['status' => $response->status(), 'payload' => $payloadOut];
    } finally {
        $_GET = $savedGet;
        $_POST = $savedPost;
        $_FILES = $savedFiles;
        $_COOKIE = $savedCookie;
        $_SERVER = $savedServer;
    }
}

function printUsage(): void
{
    echo <<<'TXT'
Idea analysis worker — processes pending live-pipeline analysis steps.

Usage:
  php idea_analysis_worker.php [options]

Options:
  --idea=IV_XXXXX    Process steps for a specific idea only
  --all              Loop until no more pending steps
  --limit=N          Max steps to process (default: 1)
  --token=TOKEN      Bearer token override
  --json             Machine-readable output
  --help             Show this help

Environment:
  CRM_AI_CRON_BEARER_TOKEN   Bearer token
  CRM_TEST_ROOT_LOGIN        Login for auth (default: admin)
  CRM_TEST_ROOT_PASSWORD     Password for auth (default: adminadmin)

Examples:
  php idea_analysis_worker.php
  php idea_analysis_worker.php --idea=IV_abc123 --all
  php idea_analysis_worker.php --all --limit=10 --json
TXT;
}
