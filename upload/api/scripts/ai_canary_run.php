<?php
declare(strict_types=1);
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }
$argv ??= $_SERVER['argv'] ?? [];

/**
 * Daily canary run for the Ideas AI-analysis pipeline (TROPATTCRM-623).
 *
 * Creates a service idea titled "[QA-TEST] canary ...", walks the full
 * pipeline a real user walks (interview -> auto-answers -> clarifications ->
 * understanding -> gap -> refined -> potential -> risks -> pitfalls -> plan ->
 * final -> tasks) through the very same REST endpoints the web UI calls, and
 * verifies that every block came back as a real AI result — not a safe-mode
 * stub (_demo_mode) and not an error. The test idea is always deleted, even on
 * failure, so no test data survives the run. Administrators get one
 * notification per run with the outcome.
 *
 * Self-calls go through the in-process App (the same simulated-request
 * mechanism ai_cron.php uses), so no network exposure or extra API key is
 * needed. Authentication reuses the ai_cron token resolution
 * (CRM_AI_CRON_BEARER_TOKEN or CRM_AI_CRON_LOGIN/PASSWORD/TOTP).
 *
 * Schedule (daily, idempotent — a state file prevents a second run within 24h):
 *   0 7 * * * cd /path/to/site && php api/scripts/ai_canary_run.php --summary >> storage_api/logs/ai-canary.log 2>&1
 *
 * Options:
 *   --force     run even if the last run was < 24h ago
 *   --keep      do not delete the test idea (for debugging a failed run)
 *   --summary   print a step-by-step table
 *   --json      machine-readable output
 *   --dry-run   resolve auth and print the plan without creating anything
 */

use Api\Model\Common\UserRepository;
use Api\Model\Notification\NotificationRepository;
use Api\Model\Setting\SettingRepository;
use Api\System\Library\App;
use Api\System\Library\Config;
use Api\System\Library\Database\ConnectionManager;
use Api\System\Library\Language\LanguageManager;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Service\NotificationService;
use Api\System\Library\Service\SettingService;
use Api\System\Library\Support\AppLog;

require_once __DIR__ . '/../system/library/support/Autoloader.php';
// ai_diag_log() and friends are loaded by index.php for web requests; the
// in-process App below bypasses index.php, so include them explicitly —
// otherwise every IdeaController AI step dies with
// "Call to undefined function ai_diag_log()".
require_once __DIR__ . '/../system/library/ai_diag.php';

$basePath = dirname(__DIR__);
$projectRoot = dirname($basePath);

$autoloader = new Api\System\Library\Support\Autoloader($basePath);
$autoloader->register();

if (class_exists(Api\System\Library\Support\EnvLoader::class)) {
    Api\System\Library\Support\EnvLoader::loadFiles([
        $projectRoot . '/.env',
        $basePath . '/.env',
        // Developer checkout: the repository root .env sits one level above
        // upload/ (on an installed copy upload/ IS the document root, so the
        // two paths above already cover it).
        dirname($projectRoot) . '/.env',
        $projectRoot . '/.env.local',
        $basePath . '/.env.local',
    ]);
}

$asJson = in_array('--json', $argv, true);
$force = in_array('--force', $argv, true);
$keep = in_array('--keep', $argv, true);
$withSummary = in_array('--summary', $argv, true);
$dryRun = in_array('--dry-run', $argv, true);

$stateFile = rtrim((string)(getenv('CRM_STORAGE_BASE') ?: dirname($basePath) . '/storage_api'), '/') . '/logs/ai_canary.last';

$steps = [];
$failedSteps = 0;
$ideaPublicId = null;
$exitCode = 0;

/**
 * Simulated in-process request against this very installation — the same
 * mechanism ai_cron.php uses: back up the superglobals, present a minimal
 * request environment, run the App, restore everything afterwards.
 *
 * @param array<string,mixed> $payload
 * @param array<string,string> $headers
 * @return array{status:int,payload:array<string,mixed>}
 */
function canaryApiRequest(string $method, string $uri, array $payload = [], array $headers = []): array
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
            'HTTP_USER_AGENT' => 'ai-canary-cli/1.0',
        ];
        foreach ($headers as $name => $value) {
            $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$name);
            if ($safeName === '') {
                continue;
            }
            $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $safeName))] = (string)$value;
        }

        $app = new App(dirname(__DIR__));
        $response = $app->run();
        $out = $response->payload();
        if (!is_array($out)) {
            $out = ['success' => false, 'code' => 'INTERNAL_ERROR', 'message' => 'Invalid API response'];
        }
        return ['status' => $response->status(), 'payload' => $out];
    } finally {
        $_GET = $savedGet;
        $_POST = $savedPost;
        $_FILES = $savedFiles;
        $_COOKIE = $savedCookie;
        $_SERVER = $savedServer;
    }
}

/** Bearer token resolution — mirrors ai_cron.php (env first, then login flow). */
function canaryResolveAuthToken(): string
{
    $direct = trim((string)getenv('CRM_AI_CRON_BEARER_TOKEN'));
    if ($direct !== '') {
        return $direct;
    }

    $login = trim((string)getenv('CRM_AI_CRON_LOGIN'));
    if ($login === '') {
        $login = trim((string)getenv('CRM_TEST_ROOT_LOGIN')) ?: 'root';
    }

    $passwords = array_values(array_unique(array_filter([
        trim((string)getenv('CRM_AI_CRON_PASSWORD')),
        trim((string)getenv('CRM_TEST_ROOT_PASSWORD')),
    ], static fn(string $v): bool => $v !== '')));
    $tokens = array_values(array_unique(array_filter([
        '',
        trim((string)getenv('CRM_AI_CRON_TOTP')),
        trim((string)getenv('CRM_TEST_ROOT_TOKEN')),
        'RootToken#2026!',
    ], static fn(string $v): bool => $v !== '')));
    // Password-only login first, then TOTP candidates (mirrors ai_cron.php).
    array_unshift($tokens, '');
    $tokens = array_values(array_unique($tokens));

    foreach ($passwords as $password) {
        foreach ($tokens as $token) {
            $payload = ['login' => $login, 'password' => $password, 'token' => $token];
            $response = canaryApiRequest('POST', '/api/v1/auth/login', $payload, [
                'X-Correlation-ID' => 'ai-canary-login-' . bin2hex(random_bytes(4)),
            ]);
            if (($response['status'] ?? 0) === 200 && (bool)($response['payload']['success'] ?? false)) {
                $tokenOut = (string)($response['payload']['data']['access_token'] ?? $response['payload']['data']['token'] ?? $response['payload']['token'] ?? '');
                if ($tokenOut !== '') {
                    return $tokenOut;
                }
            }
        }
    }

    return '';
}

/**
 * One pipeline step: POST the endpoint, verify success + non-stub payload.
 *
 * @param array{label:string,uri:string,payload?:array<string,mixed>,require_data?:bool} $step
 * @return array{label:string,ok:bool,code:string,message:string,data_keys:int}
 */
function canaryRunStep(array $step, string $token): array
{
    $response = canaryApiRequest(
        'POST',
        $step['uri'],
        $step['payload'] ?? [],
        ['Authorization' => 'Bearer ' . $token, 'X-Correlation-ID' => 'ai-canary-' . bin2hex(random_bytes(4))]
    );

    $payload = $response['payload'];
    $ok = ($response['status'] >= 200 && $response['status'] < 300) && (bool)($payload['success'] ?? false);
    $code = (string)($payload['error']['code'] ?? $payload['code'] ?? '');
    $message = (string)($payload['error']['message'] ?? $payload['message'] ?? '');
    $data = $payload['data'] ?? null;
    $dataKeys = is_array($data) ? count($data) : ($data === null ? 0 : 1);

    // A *_FALLBACK result code means the block fell back to a canned stub
    // after an AI error (TROPATTCRM-621: those rows answer 200/success but
    // are not an AI result). The canary must report them, not pass them.
    if ($ok && str_ends_with($code, '_FALLBACK')) {
        $ok = false;
        $message = 'block fell back to a stub instead of an AI result';
    }

    // A safe-mode/stub block is a failure for the canary: the point of the run
    // is that REAL AI answers come back (TROPATTCRM-621: stubs must never be
    // mistaken for a result).
    if ($ok && is_array($data) && !empty($data['_demo_mode'])) {
        $ok = false;
        $code = 'STUB_RESULT';
        $message = 'safe-mode stub returned instead of a real AI result';
    }
    if ($ok && ($step['require_data'] ?? false) && $dataKeys === 0) {
        $ok = false;
        $code = 'EMPTY_RESULT';
        $message = 'empty data payload returned';
    }

    return [
        'label' => $step['label'],
        'ok' => $ok,
        'code' => $code,
        'message' => $message,
        'data_keys' => $dataKeys,
    ];
}

/**
 * Answer every open question with a canned auto-answer so the pipeline can
 * proceed exactly like a user walking through the interview.
 *
 * @return array{answered:int}
 */
function canaryAutoAnswer(string $token, string $ideaPublicId): array
{
    $answered = 0;

    // 1. Interview questions.
    $questions = canaryApiRequest('GET', '/api/v1/ideas/' . rawurlencode($ideaPublicId) . '/questions', [], [
        'Authorization' => 'Bearer ' . $token,
    ]);
    $rows = (array)($questions['payload']['data']['questions'] ?? $questions['payload']['data']['items'] ?? []);
    $answers = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $qPid = (string)($row['public_id'] ?? '');
        if ($qPid === '') {
            continue;
        }
        $answers[] = [
            'question_public_id' => $qPid,
            'answer_text' => 'QA canary auto-answer: pilot launch in one region with a small budget.',
        ];
    }

    // 2. Clarification / gap questions live in coverage_json but are answered
    //    through the same endpoint (they are rows in idea_questions too).
    foreach (['additional-questions', 'gap-questions'] as $uriStep) {
        $loaded = canaryApiRequest('GET', '/api/v1/ideas/' . rawurlencode($ideaPublicId) . '/' . $uriStep, [], [
            'Authorization' => 'Bearer ' . $token,
        ]);
        foreach ((array)($loaded['payload']['data']['questions'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $qPid = (string)($row['public_id'] ?? '');
            if ($qPid === '') {
                continue;
            }
            $answers[] = [
                'question_public_id' => $qPid,
                'answer_text' => 'QA canary auto-answer.',
            ];
        }
    }

    if ($answers === []) {
        return ['answered' => 0];
    }

    $save = canaryApiRequest(
        'POST',
        '/api/v1/ideas/' . rawurlencode($ideaPublicId) . '/interview-answers',
        ['answers' => $answers],
        ['Authorization' => 'Bearer ' . $token]
    );
    if (($save['payload']['success'] ?? false) === true) {
        $answered = count($answers);
    }

    return ['answered' => $answered];
}

try {
    $config = new Config();
    $config->load($basePath . '/config/default.php', 'default');
    $config->load($basePath . '/config/database.php', 'database');
    $config->load($basePath . '/config/feature_flags.php', 'feature_flags');
    AppLog::bootFromConfig($config, $basePath . '/config/logging.php');
    $locale = (string)$config->get('default.locale.default', 'en-gb');
    $lang = new LanguageManager($basePath . '/language', (string)$config->get('default.locale.fallback', 'en-gb'));
    $lang->setLocale($locale);

    // Daily idempotency: cron may fire more often than the run should happen.
    if (!$force && !$dryRun && is_file($stateFile)) {
        $lastRun = (int)@filemtime($stateFile);
        if ($lastRun > 0 && (time() - $lastRun) < 86400) {
            $output = ['ok' => true, 'skipped' => 'already_ran_within_24h', 'last_run_at' => gmdate('c', $lastRun)];
            fwrite(STDOUT, $asJson
                ? json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL
                : 'AI canary: skipped (last run ' . gmdate('c', $lastRun) . "; use --force to rerun)\n");
            exit(0);
        }
    }

    $token = canaryResolveAuthToken();
    if ($token === '') {
        fwrite(STDERR, "Cannot authenticate for the AI canary run.\n");
        fwrite(STDERR, "Provide CRM_AI_CRON_BEARER_TOKEN or CRM_AI_CRON_LOGIN/CRM_AI_CRON_PASSWORD (optionally CRM_AI_CRON_TOTP).\n");
        exit(3);
    }

    if ($dryRun) {
        $output = ['ok' => true, 'dry_run' => true, 'authenticated' => true, 'state_file' => $stateFile];
        fwrite(STDOUT, $asJson
            ? json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL
            : "AI canary dry run: authenticated, ready to create the test idea.\n");
        exit(0);
    }

    $authHeader = ['Authorization' => 'Bearer ' . $token];

    // ---------------------------------------------------------------- create
    $created = canaryApiRequest('POST', '/api/v1/ideas', [
        'title' => '[QA-TEST] canary ' . gmdate('Ymd-His'),
        'description' => 'Autonomous daily canary for the AI-analysis pipeline (TROPATTCRM-623). '
            . 'A small internal logistics idea: a mobile app for route planning of company couriers. '
            . 'This idea is created and deleted automatically by ai_canary_run.php — do not edit it.',
        'category' => 'internal',
        'region' => '',
        'visibility' => 'private',
    ], $authHeader);

    if (($created['payload']['success'] ?? false) !== true) {
        $steps[] = ['label' => 'create idea', 'ok' => false, 'code' => (string)($created['payload']['error']['code'] ?? ''), 'message' => (string)($created['payload']['error']['message'] ?? ''), 'data_keys' => 0];
        $failedSteps++;
        throw new RuntimeException('cannot create the canary idea: ' . (string)($created['payload']['error']['message'] ?? ''));
    }
    $ideaPublicId = (string)($created['payload']['data']['public_id'] ?? '');
    if ($ideaPublicId === '') {
        throw new RuntimeException('canary idea created without a public_id');
    }
    $steps[] = ['label' => 'create idea', 'ok' => true, 'code' => 'IDEA_CREATED', 'message' => $ideaPublicId, 'data_keys' => 1];

    // ------------------------------------------------------------ interview
    $steps[] = canaryRunStep([
        'label' => 'interview (generate questions)',
        'uri' => '/api/v1/ideas/' . rawurlencode($ideaPublicId) . '/interview',
        'require_data' => false,
    ], $token);
    $failedSteps += $steps[count($steps) - 1]['ok'] ? 0 : 1;

    $answerRound1 = canaryAutoAnswer($token, $ideaPublicId);

    // ------------------------------------------------------- clarifications
    $steps[] = canaryRunStep([
        'label' => 'clarifications (additional questions)',
        'uri' => '/api/v1/ideas/' . rawurlencode($ideaPublicId) . '/additional-questions',
        'require_data' => true,
    ], $token);
    $failedSteps += $steps[count($steps) - 1]['ok'] ? 0 : 1;

    $answerRound2 = canaryAutoAnswer($token, $ideaPublicId);

    // ---------------------------------------------------------- understanding
    $steps[] = canaryRunStep([
        'label' => 'understanding card',
        'uri' => '/api/v1/ideas/' . rawurlencode($ideaPublicId) . '/understanding-card',
        'require_data' => true,
    ], $token);
    $failedSteps += $steps[count($steps) - 1]['ok'] ? 0 : 1;

    // ----------------------------------------------------------- gap questions
    $steps[] = canaryRunStep([
        'label' => 'gap questions',
        'uri' => '/api/v1/ideas/' . rawurlencode($ideaPublicId) . '/gap-questions',
        'require_data' => true,
    ], $token);
    $failedSteps += $steps[count($steps) - 1]['ok'] ? 0 : 1;
    canaryAutoAnswer($token, $ideaPublicId);

    // ------------------------------------------------------------- 8 blocks
    $blocks = [
        ['refined card', '/refined-card'],
        ['potential score', '/potential'],
        ['risk report', '/risk-report'],
        ['pitfalls report', '/pitfalls'],
        ['implementation plan', '/implementation-plan'],
        ['final recommendation', '/final-recommendation'],
        ['suggested tasks', '/suggested-tasks'],
    ];
    foreach ($blocks as [$label, $path]) {
        $steps[] = canaryRunStep([
            'label' => $label,
            'uri' => '/api/v1/ideas/' . rawurlencode($ideaPublicId) . $path,
            'require_data' => true,
        ], $token);
        $failedSteps += $steps[count($steps) - 1]['ok'] ? 0 : 1;
    }

    $ok = $failedSteps === 0;
    $exitCode = $ok ? 0 : 4;

    // ---------------------------------------------------------- notify admins
    try {
        $logger = new JsonLogger(
            (array)$config->get('logging.channels', []),
            (array)$config->get('logging.mask_keys', [])
        );
        $pdo = (new ConnectionManager($config))->connect();
        $settings = new SettingService(new SettingRepository($pdo));
        $notifications = new NotificationService(
            new NotificationRepository($pdo),
            new UserRepository($pdo),
            $logger,
            null,
            null,
            $lang,
            $settings
        );
        $users = new UserRepository($pdo);
        $adminIds = [];
        foreach ($users->findAdmins() as $admin) {
            $adminId = (int)($admin['id'] ?? 0);
            if ($adminId > 0) {
                $adminIds[] = $adminId;
            }
        }
        if ($adminIds !== []) {
            $failedLabels = array_values(array_filter(array_map(
                static fn(array $s): string => (string)$s['label'],
                array_filter($steps, static fn(array $s): bool => !$s['ok'])
            )));
            $notifications->notifyUsers($adminIds, [
                'category' => 'ideas',
                'title' => $ok ? 'AI canary: OK' : 'AI canary: FAILED',
                'body' => $ok
                    ? 'Daily AI-analysis canary passed: all ' . count($steps) . ' pipeline steps returned real AI results.'
                    : 'Daily AI-analysis canary FAILED at: ' . implode(', ', $failedLabels) . '. See storage_api/logs/ai-canary.log.',
                'entity_type' => 'idea',
                'entity_public_id' => (string)$ideaPublicId,
                'action_code' => 'ai_canary_result',
                'link' => 'index.php?route=admin-ai',
            ], null);
        }
    } catch (\Throwable $e) {
        // Notification trouble must not change the verdict of the run itself.
        fwrite(STDERR, '[WARN] ai_canary_run notification failed: ' . $e->getMessage() . PHP_EOL);
    }

    // --------------------------------------------------------------- cleanup
    if ($ideaPublicId !== null && !$keep) {
        $deleted = canaryApiRequest('DELETE', '/api/v1/ideas/' . rawurlencode($ideaPublicId), [], $authHeader);
        if (($deleted['payload']['success'] ?? false) !== true) {
            fwrite(STDERR, '[WARN] ai_canary_run: test idea ' . $ideaPublicId . " could not be deleted — remove it manually.\n");
            $exitCode = $exitCode === 0 ? 5 : $exitCode;
        }
    }

    // ----------------------------------------------------------- state file
    @mkdir(dirname($stateFile), 0775, true);
    @file_put_contents($stateFile, gmdate('c') . ' ' . ($ok ? 'ok' : 'failed') . ' failed_steps=' . $failedSteps . "\n");

    // --------------------------------------------------------------- output
    if ($asJson) {
        fwrite(STDOUT, json_encode([
            'ok' => $ok,
            'idea_public_id' => $ideaPublicId,
            'failed_steps' => $failedSteps,
            'answers' => ['round1' => $answerRound1['answered'], 'round2' => $answerRound2['answered']],
            'steps' => $steps,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL);
    } else {
        fwrite(STDOUT, sprintf(
            "AI canary: %s (%d/%d steps ok, idea %s %s)\n",
            $ok ? 'PASSED' : 'FAILED',
            count($steps) - $failedSteps,
            count($steps),
            (string)$ideaPublicId,
            $keep ? 'kept' : 'deleted'
        ));
        if ($withSummary) {
            foreach ($steps as $step) {
                fwrite(STDOUT, sprintf(
                    "  %s %s%s\n",
                    $step['ok'] ? '[OK]  ' : '[FAIL]',
                    $step['label'],
                    $step['ok'] ? '' : ' — ' . $step['code'] . ' ' . $step['message']
                ));
            }
        }
    }

    exit($exitCode);
} catch (Throwable $e) {
    // Best-effort cleanup: a failed run must not leave test data behind.
    if ($ideaPublicId !== null && !$keep) {
        try {
            $token = canaryResolveAuthToken();
            if ($token !== '') {
                canaryApiRequest('DELETE', '/api/v1/ideas/' . rawurlencode($ideaPublicId), [], ['Authorization' => 'Bearer ' . $token]);
            }
        } catch (\Throwable $cleanupError) {
            fwrite(STDERR, '[WARN] ai_canary_run cleanup failed: ' . $cleanupError->getMessage() . PHP_EOL);
        }
    }

    fwrite(STDERR, '[FAIL] ai_canary_run: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
