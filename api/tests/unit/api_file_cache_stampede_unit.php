<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/Config.php';
require_once __DIR__ . '/../../system/library/cache/ApiFileCache.php';

use Api\System\Library\Cache\ApiFileCache;
use Api\System\Library\Config;

if (!function_exists('pcntl_fork')) {
    echo "[SKIP] pcntl extension is unavailable\n";
    exit(0);
}

$cacheDir = sys_get_temp_dir() . '/crm-cache-stampede-' . bin2hex(random_bytes(6));
$counterFile = $cacheDir . '/callback-count';
mkdir($cacheDir, 0770, true);

$createCache = static function () use ($cacheDir): ApiFileCache {
    $config = new Config();
    $config->merge('default', [
        'storage' => ['cache' => $cacheDir],
        'api_file_cache' => ['enabled' => true, 'default_ttl' => 60, 'gc_enabled' => false],
    ]);

    return new ApiFileCache($config);
};

$children = [];
for ($i = 0; $i < 6; $i++) {
    $pid = pcntl_fork();
    if ($pid === 0) {
        usleep(100_000);
        $value = $createCache()->remember('task', 'same-request', 60, static function () use ($counterFile): array {
            $counter = fopen($counterFile, 'c+');
            flock($counter, LOCK_EX);
            $current = (int)stream_get_contents($counter);
            rewind($counter);
            ftruncate($counter, 0);
            fwrite($counter, (string)($current + 1));
            fflush($counter);
            flock($counter, LOCK_UN);
            fclose($counter);
            usleep(250_000);

            return ['source' => 'callback'];
        });
        exit($value === ['source' => 'callback'] ? 0 : 1);
    }
    if ($pid < 0) {
        throw new RuntimeException('Unable to fork cache test worker');
    }
    $children[] = $pid;
}

foreach ($children as $pid) {
    pcntl_waitpid($pid, $status);
    if (pcntl_wexitstatus($status) !== 0) {
        throw new RuntimeException('Cache test worker failed');
    }
}

$callbacks = (int)file_get_contents($counterFile);
foreach (glob($cacheDir . '/*') ?: [] as $file) {
    if (is_dir($file)) {
        foreach (glob($file . '/*') ?: [] as $nestedFile) {
            unlink($nestedFile);
        }
        rmdir($file);
        continue;
    }
    unlink($file);
}
rmdir($cacheDir);

if ($callbacks !== 1) {
    throw new RuntimeException("Expected one cache rebuild, got {$callbacks}");
}

echo "[OK] api_file_cache_stampede_unit\n";
