<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/service/AiProviderClientInterface.php';
require_once __DIR__ . '/../../system/library/service/OpenAiCompatibleProviderClient.php';
require_once __DIR__ . '/../../system/library/service/MockAiProviderClient.php';
require_once __DIR__ . '/../../system/library/service/CustomHttpProviderClient.php';
require_once __DIR__ . '/../../system/library/service/AiProviderClientFactory.php';

use Api\System\Library\Service\AiProviderClientFactory;
use Api\System\Library\Service\OpenAiCompatibleProviderClient;
use Api\System\Library\Service\MockAiProviderClient;
use Api\System\Library\Service\CustomHttpProviderClient;

function unitAssert3(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $factory = new AiProviderClientFactory(
        new OpenAiCompatibleProviderClient(),
        new MockAiProviderClient(),
        new CustomHttpProviderClient()
    );

    $mock = $factory->forProvider(['provider_code' => 'mock']);
    unitAssert3($mock instanceof MockAiProviderClient, 'mock provider must use MockAiProviderClient');

    $custom = $factory->forProvider(['provider_code' => 'custom_http']);
    unitAssert3($custom instanceof CustomHttpProviderClient, 'custom_http must use CustomHttpProviderClient');

    $openai = $factory->forProvider(['provider_code' => 'openai_compatible']);
    unitAssert3($openai instanceof OpenAiCompatibleProviderClient, 'openai_compatible must use OpenAiCompatibleProviderClient');

    $fallback = $factory->forProvider(['provider_code' => 'unknown_provider']);
    unitAssert3($fallback instanceof OpenAiCompatibleProviderClient, 'unknown provider must fallback to OpenAiCompatibleProviderClient');

    echo "[OK] ai_provider_factory_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_provider_factory_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
