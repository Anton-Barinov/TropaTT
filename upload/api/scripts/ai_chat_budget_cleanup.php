<?php
declare(strict_types=1);
// Shared-hosting cron: php api/scripts/ai_chat_budget_cleanup.php (bounded batch).
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$basePath = dirname(__DIR__);
require_once $basePath . '/system/library/support/Autoloader.php';
require_once $basePath . '/system/library/support/EnvLoader.php';
Api\System\Library\Support\EnvLoader::loadFiles([dirname($basePath).'/.env',$basePath.'/.env',dirname($basePath).'/.env.local',$basePath.'/.env.local']);
(new Api\System\Library\Support\Autoloader($basePath))->register();
try {
    $config = new Api\System\Library\Config();
    $config->load($basePath.'/config/database.php','database');
    $pdo = (new Api\System\Library\Database\ConnectionManager($config))->connect();
    $settings = new Api\System\Library\Service\SettingService(new Api\Model\Setting\SettingRepository($pdo));
    $count = (new Api\System\Library\Service\AiChatBudgetService($pdo,$settings))->cleanupStale(100);
    $runs = (new Api\System\Library\Service\AiChatRunCleanupService($pdo,$settings))->cleanupExpired(100);
    echo 'AI budget stale reservations settled: '.$count.'; expired chat runs deleted: '.$runs.PHP_EOL;
} catch (Throwable $e) {
    // DB credentials and provider details must not be emitted by a CLI failure.
    fwrite(STDERR,"AI budget cleanup failed; verify database availability and migrations.\n"); exit(1);
}
