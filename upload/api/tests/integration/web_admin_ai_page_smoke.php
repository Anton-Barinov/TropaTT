<?php declare(strict_types=1);

$root = dirname(__DIR__, 3);
$webRoot = $root . '/web';
$templatePath = $webRoot . '/view/template/page/admin_ai.php';
$bindingsPath = $webRoot . '/assets/js/page-api-bindings.js';

function failSmoke(string $message): void
{
    fwrite(STDERR, "[FAIL] web_admin_ai_page_smoke: {$message}\n");
    exit(1);
}

function readFileSafe(string $path): string
{
    if (!is_file($path)) {
        failSmoke("file not found: {$path}");
    }
    $content = file_get_contents($path);
    if ($content === false) {
        failSmoke("unable to read file: {$path}");
    }
    return $content;
}

function assertContains(string $haystack, string $needle, string $label): void
{
    if (strpos($haystack, $needle) === false) {
        failSmoke($label . ' missing: ' . $needle);
    }
}

function assertNotContains(string $haystack, string $needle, string $label): void
{
    if (strpos($haystack, $needle) !== false) {
        failSmoke($label . ' must not contain: ' . $needle);
    }
}

function extractSection(string $content, string $anchor, int $length = 22000): string
{
    $position = strpos($content, $anchor);
    if ($position === false) {
        failSmoke('section anchor not found: ' . $anchor);
    }
    return substr($content, $position, $length);
}

$template = readFileSafe($templatePath);
$bindings = readFileSafe($bindingsPath);

foreach ([
    'adminAiKpiProviders',
    'adminAiKpiEnabledIntents',
    'adminAiKpiJobsToday',
    'adminAiKpiErrorsToday',
    'adminAiProvidersBody',
    'adminAiIntentsBody',
    'adminAiUsageBody',
    'adminAiAuditBody',
    'adminAiFailedJobsBody',
    'adminAiCreateForm',
    'adminAiEditForm',
    'adminAiSecretForm',
    'adminAiReviewCard',
    'adminAiAuditDetailPre',
] as $requiredTemplateId) {
    assertContains($template, 'id="' . $requiredTemplateId . '"', 'admin-ai template id');
}

assertContains($template, 'id="adminAiUsageRange"', 'admin-ai usage range control');
assertContains($template, 'value="1"', 'admin-ai usage range option (today)');
assertContains($template, 'value="7"', 'admin-ai usage range option (7 days)');
assertContains($template, 'value="30"', 'admin-ai usage range option (30 days)');

foreach ([
    'api/v1/ai/providers',
    'api/v1/ai/intent-settings',
    'api/v1/ai/usage',
    'api/v1/ai/audit',
    'api/v1/ai/jobs',
    'api/v1/ai/action-types',
    'api/v1/ai/retention-policies',
    'api/v1/ai/prompt-templates',
    'api/v1/ai/json-schemas',
] as $requiredEndpoint) {
    assertContains($bindings, $requiredEndpoint, 'admin-ai bindings endpoint');
}

$adminAiBindingsSection = extractSection($bindings, 'window.CRM.__adminAiState');

foreach ([
    'health/deep',
    'api/v1/admin/widgets/system',
    'api/v1/ops/system',
] as $forbiddenEndpoint) {
    assertNotContains($adminAiBindingsSection, $forbiddenEndpoint, 'admin-ai bindings section');
}

assertContains($bindings, 'usageRangeDays !== 1 && usageRangeDays !== 7 && usageRangeDays !== 30', 'usage range guard');
assertContains($bindings, 'api/v1/ai/jobs', 'failed jobs endpoint in bindings');

fwrite(STDOUT, "[OK] web_admin_ai_page_smoke\n");
