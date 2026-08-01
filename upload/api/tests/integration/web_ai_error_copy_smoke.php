<?php declare(strict_types=1);

$root = dirname(__DIR__, 3);
$aiJsPath = $root . '/web/assets/js/ai.js';
$pageBindingsPath = $root . '/web/assets/js/page-api-bindings.js';

function failSmoke(string $message): void
{
    fwrite(STDERR, "[FAIL] web_ai_error_copy_smoke: {$message}\n");
    exit(1);
}

function readFileSafe(string $path): string
{
    if (!is_file($path)) {
        failSmoke('file not found: ' . $path);
    }
    $content = file_get_contents($path);
    if ($content === false) {
        failSmoke('unable to read file: ' . $path);
    }
    return $content;
}

function assertContains(string $haystack, string $needle, string $message): void
{
    if (strpos($haystack, $needle) === false) {
        failSmoke($message . ' (needle: ' . $needle . ')');
    }
}

$aiJs = readFileSafe($aiJsPath);
$pageBindingsJs = readFileSafe($pageBindingsPath);

// Core user-facing copy must remain safe and non-technical.
$expectedMessages = [
    'AI временно отключен администратором.',
    'AI пока не настроен администратором.',
    'Провайдер AI не ответил вовремя. Попробуйте еще раз.',
    'Ошибка доступа к AI-провайдеру. Обратитесь к администратору.',
    'AI-провайдер временно недоступен. Попробуйте позже.',
    'Лимит AI-запросов временно исчерпан. Попробуйте позже.',
    'Не удалось разобрать ответ AI. Можно повторить запрос.',
    'Данные изменились после подготовки предложения. Обновите предложение.'
];

foreach ($expectedMessages as $message) {
    assertContains($aiJs, $message, 'missing expected safe UI message in ai.js');
}

// Error code -> UI state mapping must include standard AI states.
$expectedStateMappings = [
    "if (code === 'AI_PROVIDER_NOT_CONFIGURED') return 'provider_missing';",
    "if (code === 'AI_RATE_LIMITED') return 'rate_limited';",
    "if (code === 'AI_DISABLED' || code === 'AI_INTENT_DISABLED' || code === 'AI_FEATURE_DISABLED') return 'disabled';",
    "if (code === 'AI_ROW_VERSION_CONFLICT') return 'conflict';"
];

foreach ($expectedStateMappings as $mapping) {
    assertContains($aiJs, $mapping, 'missing expected error->state mapping in ai.js');
}

// Admin hint must be route-based and only for provider-missing/disabled states in drawer.
assertContains($aiJs, "index.php?route=admin-ai", 'admin-ai recovery link missing in ai.js');
assertContains($aiJs, "if ((state === 'provider_missing' || state === 'disabled') && canOpenAdminAi())", 'safe admin-ai link gating missing in ai.js');

// Shared state helpers must be used by page bindings for soft error rendering.
assertContains($pageBindingsJs, 'resolveAiUiState', 'page-api-bindings must use resolveAiUiState helper');
assertContains($pageBindingsJs, 'setAiUiState', 'page-api-bindings must use setAiUiState helper');

// Basic guard: safe UI messages should not mention secrets/tokens/endpoints in visible strings.
$forbiddenUiTerms = [
    'provider error body',
    'api key',
    'token:',
    'authorization:'
];

$messageSection = '';
if (preg_match('/function normalizeError\(.*?return \{\s*code:/s', $aiJs, $matches) === 1) {
    $messageSection = $matches[0];
}
if ($messageSection === '') {
    failSmoke('unable to isolate normalizeError message section');
}

foreach ($forbiddenUiTerms as $term) {
    if (stripos($messageSection, $term) !== false) {
        failSmoke('forbidden technical term detected in normalizeError UI message section: ' . $term);
    }
}

fwrite(STDOUT, "[OK] web_ai_error_copy_smoke\n");
