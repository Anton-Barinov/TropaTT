<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$runtimeRepositoryPath = $root . '/api/model/ai/AiRuntimeRepository.php';
$suggestionServicePath = $root . '/api/system/library/service/AiSuggestionService.php';
$jobServicePath = $root . '/api/system/library/service/AiJobService.php';
$unitPath = $root . '/api/tests/unit/ai_runtime_retention_json_limits_unit.php';

function failRetentionCleanupGuard(string $message): void
{
    fwrite(STDERR, "[FAIL] ai_retention_cleanup_runtime_guard_smoke: {$message}\n");
    exit(1);
}

function readRetentionCleanupGuard(string $path): string
{
    if (!is_file($path)) {
        failRetentionCleanupGuard('file not found: ' . $path);
    }
    $content = file_get_contents($path);
    if ($content === false) {
        failRetentionCleanupGuard('unable to read file: ' . $path);
    }
    return $content;
}

function assertContainsRetentionCleanup(string $haystack, string $needle, string $message): void
{
    if (strpos($haystack, $needle) === false) {
        failRetentionCleanupGuard($message . ' (needle: ' . $needle . ')');
    }
}

$runtimeRepository = readRetentionCleanupGuard($runtimeRepositoryPath);
$suggestionService = readRetentionCleanupGuard($suggestionServicePath);
$jobService = readRetentionCleanupGuard($jobServicePath);
$unit = readRetentionCleanupGuard($unitPath);

assertContainsRetentionCleanup($runtimeRepository, 'public function cleanupByRetention(array $policies): array', 'AiRuntimeRepository cleanupByRetention must exist');
assertContainsRetentionCleanup($runtimeRepository, "->from('ai_suggestions')", 'Retention cleanup must delete expired suggestions');
assertContainsRetentionCleanup($runtimeRepository, "->from('ai_jobs')", 'Retention cleanup must process ai_jobs');
assertContainsRetentionCleanup($runtimeRepository, "->whereRaw('(status IS NULL OR status NOT IN (?, ?))', ['queued', 'running'])", 'Retention cleanup must preserve queued/running jobs');
assertContainsRetentionCleanup($runtimeRepository, "->from('ai_usage_logs')", 'Retention cleanup must delete expired usage logs');

assertContainsRetentionCleanup($suggestionService, 'private function applyRetentionCleanup(): void', 'Suggestion service must expose retention cleanup hook');
assertContainsRetentionCleanup($suggestionService, '$this->runtime->cleanupByRetention($this->retention->getPolicies());', 'Suggestion service must invoke retention cleanup after runtime writes');
assertContainsRetentionCleanup($jobService, '$this->runtime->cleanupByRetention($this->retention->getPolicies());', 'Cron/job service must invoke retention cleanup for diagnostics/runtime flow');

assertContainsRetentionCleanup($unit, "'suggestions_ttl_days' => 1,", 'Unit coverage must exercise suggestion retention TTL');
assertContainsRetentionCleanup($unit, "'jobs_ttl_days' => 1,", 'Unit coverage must exercise jobs retention TTL');
assertContainsRetentionCleanup($unit, "'usage_logs_ttl_days' => 1,", 'Unit coverage must exercise usage retention TTL');
assertContainsRetentionCleanup($unit, 'cleanup must delete expired completed jobs', 'Unit coverage must verify completed jobs are deleted');
assertContainsRetentionCleanup($unit, 'cleanup must not delete queued jobs', 'Unit coverage must verify queued jobs are preserved');

fwrite(STDOUT, "[OK] ai_retention_cleanup_runtime_guard_smoke\n");
