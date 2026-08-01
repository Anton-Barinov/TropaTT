<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/support/Autoloader.php';

$autoloader = new Api\System\Library\Support\Autoloader(dirname(__DIR__, 2));
$autoloader->register();

use Api\System\Library\Support\EnvLoader;

function envLoaderAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$tmpDir = sys_get_temp_dir() . '/crm_env_loader_' . bin2hex(random_bytes(4));
if (!mkdir($tmpDir, 0777, true) && !is_dir($tmpDir)) {
    throw new RuntimeException('Failed to create temp dir');
}

try {
    $baseFile = $tmpDir . '/.env';
    $localFile = $tmpDir . '/.env.local';

    file_put_contents($baseFile, implode("\n", [
        '# comment',
        'CRM_ENV_UNIT_ONE=base',
        'CRM_ENV_UNIT_TWO="line\\nvalue"',
        'CRM_ENV_UNIT_THREE=plain # inline comment',
        'export CRM_ENV_UNIT_EXPORT=exported',
        'INVALID-KEY=ignored',
    ]));
    file_put_contents($localFile, "CRM_ENV_UNIT_ONE=local\nCRM_ENV_UNIT_LOCAL='quoted local'\n");

    putenv('CRM_ENV_UNIT_EXTERNAL=external');
    $_ENV['CRM_ENV_UNIT_EXTERNAL'] = 'external';
    $_SERVER['CRM_ENV_UNIT_EXTERNAL'] = 'external';
    file_put_contents($baseFile, file_get_contents($baseFile) . "\nCRM_ENV_UNIT_EXTERNAL=file\n");

    EnvLoader::loadFiles([$baseFile, $localFile]);

    envLoaderAssert(getenv('CRM_ENV_UNIT_ONE') === 'local', '.env.local must override value loaded from .env');
    envLoaderAssert(getenv('CRM_ENV_UNIT_TWO') === "line\nvalue", 'Double-quoted escape sequences must be parsed');
    envLoaderAssert(getenv('CRM_ENV_UNIT_THREE') === 'plain', 'Inline comments must be stripped for plain values');
    envLoaderAssert(getenv('CRM_ENV_UNIT_EXPORT') === 'exported', 'export prefix must be supported');
    envLoaderAssert(getenv('CRM_ENV_UNIT_LOCAL') === 'quoted local', 'Single quoted values must be unwrapped');
    envLoaderAssert(getenv('CRM_ENV_UNIT_EXTERNAL') === 'external', 'Existing process env must not be overridden');
    envLoaderAssert(getenv('INVALID-KEY') === false, 'Invalid env keys must be ignored');

    echo "[OK] env_loader_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] env_loader_unit: ' . $e->getMessage() . "\n");
    exit(1);
} finally {
    foreach (glob($tmpDir . '/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($tmpDir);
}
