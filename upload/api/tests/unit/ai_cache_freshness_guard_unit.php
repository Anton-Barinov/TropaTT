<?php
declare(strict_types=1);

function unitAssertCacheFreshness(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $servicePath = __DIR__ . '/../../system/library/service/AiSuggestionService.php';
    $serviceSource = (string)file_get_contents($servicePath);
    unitAssertCacheFreshness($serviceSource !== '', 'AiSuggestionService must be readable');
    unitAssertCacheFreshness(str_contains($serviceSource, 'isForceRefreshRequested('), 'force_refresh parser must exist');
    unitAssertCacheFreshness(str_contains($serviceSource, 'resolveCacheDateBucket('), 'date bucket helper must exist');
    unitAssertCacheFreshness(str_contains($serviceSource, 'buildCacheKey('), 'cache key helper must exist');
    unitAssertCacheFreshness(str_contains($serviceSource, 'buildDependencyFingerprint('), 'dependency fingerprint helper must exist');
    unitAssertCacheFreshness(str_contains($serviceSource, 'resolveCachedSuggestionResponse('), 'cache hit/stale resolver must exist');
    unitAssertCacheFreshness(str_contains($serviceSource, "'cache_key' =>"), 'suggestion persistence must include cache_key');
    unitAssertCacheFreshness(str_contains($serviceSource, "'dependency_fingerprint' =>"), 'suggestion persistence must include dependency_fingerprint');

    $runtimeRepoPath = __DIR__ . '/../../model/ai/AiRuntimeRepository.php';
    $runtimeSource = (string)file_get_contents($runtimeRepoPath);
    unitAssertCacheFreshness($runtimeSource !== '', 'AiRuntimeRepository must be readable');
    unitAssertCacheFreshness(str_contains($runtimeSource, 'findLatestSuggestionByCacheKey('), 'runtime repo must support lookup by cache key');
    unitAssertCacheFreshness(str_contains($runtimeSource, 'markSuggestionUsed('), 'runtime repo must update usage counters');

    $providerServicePath = __DIR__ . '/../../system/library/service/AiProviderService.php';
    $providerSource = (string)file_get_contents($providerServicePath);
    unitAssertCacheFreshness($providerSource !== '', 'AiProviderService must be readable');
    unitAssertCacheFreshness(str_contains($providerSource, 'persistProviderHealthSnapshot('), 'provider health snapshot writer must exist');
    unitAssertCacheFreshness(str_contains($providerSource, "'provider_health' =>"), 'provider response must expose health summary');

    $migrationManagerPath = __DIR__ . '/../../system/library/database/migration/MigrationManager.php';
    $managerSource = (string)file_get_contents($migrationManagerPath);
    unitAssertCacheFreshness(str_contains($managerSource, 'AiSuggestionsCacheFreshnessMigration'), 'migration manager must register cache freshness migration');

    echo "[OK] ai_cache_freshness_guard_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_cache_freshness_guard_unit: ' . $e->getMessage() . "\n");
    exit(1);
}

