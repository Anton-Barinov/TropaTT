<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/support/Autoloader.php';
$autoloader = new Api\System\Library\Support\Autoloader(dirname(__DIR__, 2));
$autoloader->register();

use Api\System\Library\Config;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Service\AiSemanticIndexService;

$assert = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException($message);
    }
    echo "[OK] {$message}\n";
};

$tmpBase = sys_get_temp_dir() . '/crm_ai_org_scope_' . bin2hex(random_bytes(4));
@mkdir($tmpBase, 0775, true);

try {
    $config = new Config();
    $config->merge('default', ['storage' => ['base' => $tmpBase]]);
    $service = new AiSemanticIndexService($config, new JsonLogger([]));

    $service->indexDocument('doc_a', 'alpha tenant text', [], 'org_alpha');
    $service->indexDocument('doc_b', 'beta tenant text', [], 'org_beta');
    $service->indexDocument('doc_legacy', 'legacy tenant text');

    $alpha = $service->search('tenant', 10, 'org_alpha');
    $beta = $service->search('tenant', 10, 'org_beta');
    $legacy = $service->search('tenant', 10);
    $ids = static fn(array $result): array => array_map(static fn(array $item): string => (string)$item['document_public_id'], $result['items'] ?? []);

    $assert($ids($alpha) === ['doc_a'], 'organization alpha search reads only alpha index');
    $assert($ids($beta) === ['doc_b'], 'organization beta search reads only beta index');
    $assert($ids($legacy) === ['doc_legacy'], 'legacy search keeps the original unscoped index');

    $service->removeDocument('doc_a', 'org_alpha');
    $assert($ids($service->search('tenant', 10, 'org_alpha')) === [], 'removeDocument only removes from the selected organization index');
    $assert($ids($service->search('tenant', 10, 'org_beta')) === ['doc_b'], 'removing alpha does not affect beta index');
    $assert(is_file($tmpBase . '/ai/cache/semantic-index-org-' . hash('sha256', 'org_alpha') . '.json'), 'organization index is physically separated by hashed public id');
} finally {
    $files = glob($tmpBase . '/ai/cache/*') ?: [];
    foreach ($files as $file) @unlink($file);
    @rmdir($tmpBase . '/ai/cache');
    @rmdir($tmpBase . '/ai');
    @rmdir($tmpBase);
}

echo "AI semantic organization scope unit passed.\n";
