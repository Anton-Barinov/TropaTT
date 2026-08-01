<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/support/Autoloader.php';

$autoloader = new Api\System\Library\Support\Autoloader(dirname(__DIR__, 2));
$autoloader->register();

use Api\Model\Ai\AiRuntimeRepository;

function unitAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec('CREATE TABLE ai_suggestions (
        public_id TEXT PRIMARY KEY,
        intent_code TEXT NULL,
        entity_type TEXT NULL,
        entity_public_id TEXT NULL,
        summary TEXT NULL,
        suggestion_json TEXT NULL,
        status TEXT NULL,
        created_by_user_id INTEGER NULL,
        confirmed_by_user_id INTEGER NULL,
        created_at TEXT NULL,
        updated_at TEXT NULL,
        expires_at TEXT NULL
    )');

    $pdo->exec('CREATE TABLE ai_jobs (
        public_id TEXT PRIMARY KEY,
        job_type TEXT NULL,
        action_type TEXT NULL,
        intent_code TEXT NULL,
        status TEXT NULL,
        requested_by_user_id INTEGER NULL,
        scope_type TEXT NULL,
        scope_public_id TEXT NULL,
        idempotency_key_hash TEXT NULL,
        payload_json TEXT NULL,
        result_json TEXT NULL,
        error_code TEXT NULL,
        error_message TEXT NULL,
        created_at TEXT NULL,
        started_at TEXT NULL,
        finished_at TEXT NULL,
        updated_at TEXT NULL
    )');

    $pdo->exec('CREATE TABLE ai_usage_logs (
        public_id TEXT PRIMARY KEY,
        user_id INTEGER NULL,
        provider_public_id TEXT NULL,
        action_type TEXT NULL,
        intent_code TEXT NULL,
        status TEXT NULL,
        error_code TEXT NULL,
        request_tokens INTEGER NULL,
        response_tokens INTEGER NULL,
        total_tokens INTEGER NULL,
        latency_ms INTEGER NULL,
        is_sensitive_context INTEGER NULL,
        request_meta TEXT NULL,
        created_at TEXT NULL
    )');

    $runtime = new AiRuntimeRepository($pdo);

    $veryLarge = str_repeat('A', 300000);
    $runtime->createSuggestion([
        'intent_code' => 'task_summary',
        'entity_type' => 'task',
        'entity_public_id' => 'tsk_unit',
        'summary' => 'unit',
        'suggestion_json' => json_encode(['blob' => $veryLarge], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'status' => 'draft',
        'created_by_user_id' => 1,
        'confirmed_by_user_id' => null,
        'created_at' => gmdate('Y-m-d H:i:s'),
        'updated_at' => gmdate('Y-m-d H:i:s'),
        'expires_at' => null,
    ]);

    $runtime->createJob([
        'job_type' => 'interactive',
        'action_type' => 'task_summary',
        'intent_code' => 'task_summary',
        'status' => 'completed',
        'requested_by_user_id' => 1,
        'scope_type' => 'task',
        'scope_public_id' => 'tsk_unit',
        'idempotency_key_hash' => null,
        'payload_json' => json_encode(['blob' => $veryLarge], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'result_json' => json_encode(['blob' => $veryLarge], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'error_code' => null,
        'error_message' => null,
        'created_at' => gmdate('Y-m-d H:i:s'),
        'started_at' => gmdate('Y-m-d H:i:s'),
        'finished_at' => gmdate('Y-m-d H:i:s'),
        'updated_at' => gmdate('Y-m-d H:i:s'),
    ]);

    $runtime->createUsageLog([
        'user_id' => 1,
        'provider_public_id' => 'aip_unit',
        'action_type' => 'task_summary',
        'intent_code' => 'task_summary',
        'status' => 'completed',
        'error_code' => null,
        'request_tokens' => 0,
        'response_tokens' => 0,
        'total_tokens' => 0,
        'latency_ms' => 0,
        'is_sensitive_context' => 0,
        'request_meta' => json_encode(['blob' => $veryLarge], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'created_at' => gmdate('Y-m-d H:i:s'),
    ]);

    $suggestionRow = (array)$pdo->query('SELECT suggestion_json FROM ai_suggestions ORDER BY rowid DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $jobRow = (array)$pdo->query('SELECT payload_json, result_json FROM ai_jobs ORDER BY rowid DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $usageRow = (array)$pdo->query('SELECT request_meta FROM ai_usage_logs ORDER BY rowid DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);

    $suggestionStored = (string)($suggestionRow['suggestion_json'] ?? '');
    $payloadStored = (string)($jobRow['payload_json'] ?? '');
    $resultStored = (string)($jobRow['result_json'] ?? '');
    $metaStored = (string)($usageRow['request_meta'] ?? '');

    unitAssert(strlen($suggestionStored) < strlen($veryLarge), 'suggestion_json must be bounded');
    unitAssert(strlen($payloadStored) < strlen($veryLarge), 'payload_json must be bounded');
    unitAssert(strlen($resultStored) < strlen($veryLarge), 'result_json must be bounded');
    unitAssert(strlen($metaStored) < strlen($veryLarge), 'request_meta must be bounded');

    $suggestionDecoded = json_decode($suggestionStored, true);
    $payloadDecoded = json_decode($payloadStored, true);
    $resultDecoded = json_decode($resultStored, true);
    $metaDecoded = json_decode($metaStored, true);

    unitAssert(is_array($suggestionDecoded) && (bool)($suggestionDecoded['_truncated'] ?? false), 'suggestion_json must store truncation marker');
    unitAssert(is_array($payloadDecoded) && (bool)($payloadDecoded['_truncated'] ?? false), 'payload_json must store truncation marker');
    unitAssert(is_array($resultDecoded) && (bool)($resultDecoded['_truncated'] ?? false), 'result_json must store truncation marker');
    unitAssert(is_array($metaDecoded) && (bool)($metaDecoded['_truncated'] ?? false), 'request_meta must store truncation marker');

    $oldDate = gmdate('Y-m-d H:i:s', time() - 4 * 86400);
    $newDate = gmdate('Y-m-d H:i:s');

    $runtime->createSuggestion([
        'intent_code' => 'task_summary',
        'entity_type' => 'task',
        'entity_public_id' => 'tsk_old',
        'summary' => 'old suggestion',
        'suggestion_json' => '{}',
        'status' => 'draft',
        'created_by_user_id' => 1,
        'confirmed_by_user_id' => null,
        'created_at' => $oldDate,
        'updated_at' => $oldDate,
        'expires_at' => null,
    ]);

    $runtime->createJob([
        'job_type' => 'cron',
        'action_type' => 'suggestion_cleanup',
        'intent_code' => 'suggestion_cleanup',
        'status' => 'completed',
        'requested_by_user_id' => null,
        'scope_type' => 'system',
        'scope_public_id' => 'global',
        'idempotency_key_hash' => null,
        'payload_json' => '{}',
        'result_json' => '{}',
        'error_code' => null,
        'error_message' => null,
        'created_at' => $oldDate,
        'started_at' => $oldDate,
        'finished_at' => $oldDate,
        'updated_at' => $oldDate,
    ]);

    $runtime->createJob([
        'job_type' => 'cron',
        'action_type' => 'suggestion_cleanup',
        'intent_code' => 'suggestion_cleanup',
        'status' => 'queued',
        'requested_by_user_id' => null,
        'scope_type' => 'system',
        'scope_public_id' => 'global',
        'idempotency_key_hash' => null,
        'payload_json' => '{}',
        'result_json' => null,
        'error_code' => null,
        'error_message' => null,
        'created_at' => $oldDate,
        'started_at' => null,
        'finished_at' => null,
        'updated_at' => $newDate,
    ]);

    $runtime->createUsageLog([
        'user_id' => null,
        'provider_public_id' => null,
        'action_type' => 'cron_job',
        'intent_code' => 'suggestion_cleanup',
        'status' => 'ok',
        'error_code' => null,
        'request_tokens' => null,
        'response_tokens' => null,
        'total_tokens' => null,
        'latency_ms' => null,
        'is_sensitive_context' => 0,
        'request_meta' => '{}',
        'created_at' => $oldDate,
    ]);

    $cleanup = $runtime->cleanupByRetention([
        'suggestions_ttl_days' => 1,
        'jobs_ttl_days' => 1,
        'usage_logs_ttl_days' => 1,
    ]);

    unitAssert((int)($cleanup['suggestions_deleted'] ?? 0) >= 1, 'cleanup must delete expired suggestions');
    unitAssert((int)($cleanup['jobs_deleted'] ?? 0) >= 1, 'cleanup must delete expired completed jobs');
    unitAssert((int)($cleanup['usage_logs_deleted'] ?? 0) >= 1, 'cleanup must delete expired usage logs');

    $queuedLeft = (int)$pdo->query("SELECT COUNT(*) FROM ai_jobs WHERE status = 'queued'")->fetchColumn();
    unitAssert($queuedLeft >= 1, 'cleanup must not delete queued jobs');

    echo "[OK] ai_runtime_retention_json_limits_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_runtime_retention_json_limits_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
