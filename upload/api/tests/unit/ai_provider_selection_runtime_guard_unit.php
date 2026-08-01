<?php
declare(strict_types=1);

$source = (string)file_get_contents(__DIR__ . '/../../system/library/service/AiSuggestionService.php');

$assert = static function (bool $ok, string $message): void {
    if (!$ok) {
        fwrite(STDERR, '[FAIL] ai_provider_selection_runtime_guard_unit: ' . $message . "\n");
        exit(1);
    }
};

$assert(str_contains($source, 'private function isMockRuntimeAllowed(): bool'), 'AiSuggestionService must define explicit mock runtime policy gate');
$assert(str_contains($source, "getenv('CRM_AI_ALLOW_MOCK_RUNTIME')"), 'Mock runtime gate must require explicit environment flag support');
$assert(str_contains($source, "if (\$this->isMockProvider(\$provider) && !\$this->isMockRuntimeAllowed())"), 'Mock providers must be rejected in non-dev runtime selection');
$assert(!str_contains($source, 'resolveMockFallbackProvider('), 'Hidden provider fallback to mock/fake must not exist');
$assert(!str_contains($source, 'AI_PROVIDER_SECRET_NOT_CONFIGURED") {\n            $fallbackProvider = $this->resolveMockFallbackProvider'), 'Preflight provider secret errors must not fallback to mock/fake');

fwrite(STDOUT, "[OK] ai_provider_selection_runtime_guard_unit\n");
