<?php
/**
 * Save real OpenAI API key to the AI provider secrets.
 * Usage: SK=sk-your-real-key php tests/integration/save_ai_key.php
 * 
 * This encrypts the key with the current ai.key secret and saves to DB.
 */
$argv ??= $_SERVER['argv'] ?? [];
$argc ??= count($argv);

if (!getenv('SK') && $argc < 2) {
    die("Usage: SK=sk-your-openai-key php save_ai_key.php\n");
}
$rawKey = getenv('SK') ?: $argv[1];
if (strlen($rawKey) < 10) die("Key too short\n");

require_once __DIR__ . '/../../system/library/support/Autoloader.php';
(new \Api\System\Library\Support\Autoloader(__DIR__ . '/../..'))->register();

$cfg = new \Api\System\Library\Config();
$cfg->load(__DIR__ . '/../../config/database.php', 'database');
if (is_file(__DIR__ . '/../../config/database.local.php')) {
    $cfg->load(__DIR__ . '/../../config/database.local.php', 'database');
}
$pdo = (new \Api\System\Library\Database\ConnectionManager($cfg))->connect();

$svc = new \Api\System\Library\Service\AiProviderService(
    providers: new \Api\Model\Ai\AiProviderRepository($pdo),
    settings: new \Api\System\Library\Service\SettingService($pdo),
    logger: new \Api\System\Library\Logger\JsonLogger(channels: []),
    config: $cfg,
    providerClientFactory: new \Api\System\Library\Service\AiProviderClientFactory(
        new \Api\System\Library\Service\OpenAiCompatibleProviderClient(),
        new \Api\System\Library\Service\MockAiProviderClient(),
        new \Api\System\Library\Service\CustomHttpProviderClient()
    ),
    request: new \Api\System\Library\Http\Request(
        method: 'GET',
        uri: '/',
        path: '/',
        query: [],
        post: [],
        cookies: [],
        files: [],
        server: [],
        headers: [],
        rawBody: '',
        requestId: 'save-ai-key',
        correlationId: 'save-ai-key-correlation',
        locale: 'en-gb',
    )
);

    // Find the deepseek provider (or first active)
    $provider = $pdo->query("SELECT public_id FROM ai_providers WHERE provider_code='deepseek' AND is_active=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC)
        ?: $pdo->query("SELECT public_id FROM ai_providers WHERE is_active=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$provider) die("No active provider found\n");

$result = $svc->upsertSecret($provider['public_id'], $rawKey, ['user_id' => 1, 'login' => 'root']);
echo $result['ok'] ? "Key saved successfully.\n" : "Failed: " . ($result['message'] ?? '') . "\n";

// Verify
try {
    $secret = (new ReflectionMethod($svc, 'decryptedSecretByProvider'))
        ->invoke($svc, (int)$pdo->query("SELECT id FROM ai_providers WHERE public_id='{$provider['public_id']}'")->fetchColumn());
    echo "Decryption check: " . ($secret ? "OK (length=" . strlen($secret) . ")" : "FAILED") . "\n";
} catch (\Throwable $e) {
    echo "Decryption check: FAILED - " . $e->getMessage() . "\n";
}
