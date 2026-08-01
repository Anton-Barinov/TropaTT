<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/**
 * @return list<string>
 */
function collectForbiddenAiStoragePaths(string $storageBase): array
{
    $found = [];
    foreach ([
        'ai',
        'llm',
        'embeddings',
        'vector',
        'vectors',
        'exports/ai',
        'cache/ai',
    ] as $relativePath) {
        $absolutePath = rtrim($storageBase, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (file_exists($absolutePath)) {
            $found[] = $absolutePath;
        }
    }

    return $found;
}

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    // Ensure migration is applied in environments where DB was created before AI stage.
    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $permissions = request('GET', '/api/v1/permissions', [], $rootHeaders);
    assertTrue($permissions['status'] === 200, 'Permissions endpoint must be available');
    $permissionCodes = array_map(
        static fn(array $item): string => (string)($item['code'] ?? ''),
        (array)($permissions['payload']['data']['items'] ?? [])
    );
    foreach (['ai.use', 'ai.admin', 'ai.use_sensitive_context', 'ai.manage_prompts', 'ai.view_audit', 'ai.view_cron_results', 'ai.manage_cron_jobs'] as $requiredPermission) {
        assertTrue(in_array($requiredPermission, $permissionCodes, true), 'Missing permission in registry: ' . $requiredPermission);
    }

    $featureFlags = request('GET', '/api/v1/feature-flags', [], $rootHeaders);
    assertTrue($featureFlags['status'] === 200, 'Feature flags list status must be 200');
    $flags = (array)($featureFlags['payload']['data']['items'] ?? []);
    $flagsByCode = [];
    foreach ($flags as $row) {
        $flagsByCode[(string)($row['code'] ?? '')] = (string)($row['public_id'] ?? '');
    }
    foreach ([
        'ai.enabled',
        'ai.task',
        'ai.project',
        'ai.calendar',
        'ai.client',
        'ai.analytics',
        'ai.admin',
        'ai.search',
        'ai.workflow',
        'ai.import',
        'ai.cron.enabled',
        'ai.cron.daily_work_plan',
    ] as $requiredFlag) {
        assertTrue(isset($flagsByCode[$requiredFlag]), 'Missing feature flag: ' . $requiredFlag);
    }

    $roleAdminCreate = request('POST', '/api/v1/roles', [
        'code' => 'ai_admin_' . randomSuffix(),
        'title' => 'AI Admin Role',
    ], $rootHeaders);
    assertTrue($roleAdminCreate['status'] === 201, 'AI admin role create status must be 201');
    $roleAdminPublicId = (string)($roleAdminCreate['payload']['data']['role']['public_id'] ?? '');

    $roleUserCreate = request('POST', '/api/v1/roles', [
        'code' => 'ai_user_' . randomSuffix(),
        'title' => 'AI User Role',
    ], $rootHeaders);
    assertTrue($roleUserCreate['status'] === 201, 'AI user role create status must be 201');
    $roleUserPublicId = (string)($roleUserCreate['payload']['data']['role']['public_id'] ?? '');

    $setAdminPermissions = request('PUT', '/api/v1/roles/' . $roleAdminPublicId . '/permissions', [
        'permission_codes' => ['ai.admin', 'ai.use', 'ai.manage_cron_jobs'],
    ], $rootHeaders);
    assertTrue($setAdminPermissions['status'] === 200, 'AI admin permissions set must be 200');

    $setUserPermissions = request('PUT', '/api/v1/roles/' . $roleUserPublicId . '/permissions', [
        'permission_codes' => ['ai.use', 'task.manage'],
    ], $rootHeaders);
    assertTrue($setUserPermissions['status'] === 200, 'AI user permissions set must be 200');

    $adminLogin = 'ai.admin.' . randomSuffix();
    $adminPassword = 'AiAdminPass#2026!';
    $adminToken = 'ai-admin-token-' . randomSuffix();
    $adminCreate = request('POST', '/api/v1/users', [
        'login' => $adminLogin,
        'password' => $adminPassword,
        'token' => $adminToken,
        'email' => $adminLogin . '@crm.local',
        'full_name' => 'AI Admin',
        'role_public_ids' => [$roleAdminPublicId],
    ], $rootHeaders);
    assertTrue($adminCreate['status'] === 201, 'AI admin user create status must be 201');
    $adminPublicId = (string)($adminCreate['payload']['data']['user']['public_id'] ?? '');

    $userLogin = 'ai.user.' . randomSuffix();
    $userPassword = 'AiUserPass#2026!';
    $userToken = 'ai-user-token-' . randomSuffix();
    $userCreate = request('POST', '/api/v1/users', [
        'login' => $userLogin,
        'password' => $userPassword,
        'token' => $userToken,
        'email' => $userLogin . '@crm.local',
        'full_name' => 'AI User',
        'role_public_ids' => [$roleUserPublicId],
    ], $rootHeaders);
    assertTrue($userCreate['status'] === 201, 'AI user create status must be 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');

    // Non-root without ai.admin must be forbidden on provider endpoints.
    $userAuth = request('POST', '/api/v1/auth/login', ['login' => $userLogin, 'password' => $userPassword, 'token' => $userToken]);
    assertTrue($userAuth['status'] === 200, 'AI user login status must be 200');
    $userHeaders = authHeaders((string)($userAuth['payload']['data']['access_token'] ?? ''));
    $forbiddenProviders = request('GET', '/api/v1/ai/providers', [], $userHeaders);
    assertTrue($forbiddenProviders['status'] === 403, 'AI user without ai.admin must get 403 on provider list');

    // ai.enabled off -> safe error for ai action endpoint.
    $aiEnabledPublicId = (string)$flagsByCode['ai.enabled'];
    $aiTaskPublicId = (string)$flagsByCode['ai.task'];
    $aiDailyPlanPublicId = (string)$flagsByCode['ai.cron.daily_work_plan'];
    $disableAi = request('PATCH', '/api/v1/feature-flags/' . $aiEnabledPublicId, [
        'is_enabled' => 0,
    ], $rootHeaders);
    assertTrue($disableAi['status'] === 200, 'Disable ai.enabled must be 200');

    $disabledAction = request('POST', '/api/v1/ai/actions/task_summary', [
        'scope_type' => 'task',
        'scope_public_id' => 'tsk_demo',
    ], $userHeaders);
    assertTrue($disabledAction['status'] === 409, 'AI action with ai.enabled=0 must return 409');
    assertTrue((string)($disabledAction['payload']['code'] ?? '') === 'AI_DISABLED', 'AI disabled code mismatch');
    $availabilityWhenAiDisabled = request('GET', '/api/v1/ai/availability', ['intents' => 'task_summary,admin_log_review'], $userHeaders);
    assertTrue($availabilityWhenAiDisabled['status'] === 200, 'AI availability endpoint must be available for ai.use user');
    assertTrue((bool)($availabilityWhenAiDisabled['payload']['data']['ai']['enabled'] ?? true) === false, 'Availability must reflect ai.enabled=false');
    assertTrue((bool)($availabilityWhenAiDisabled['payload']['data']['intents']['task_summary']['enabled'] ?? true) === false, 'task_summary must be disabled when ai.enabled=false');
    assertTrue((string)($availabilityWhenAiDisabled['payload']['data']['intents']['task_summary']['reason'] ?? '') === 'ai_disabled', 'task_summary reason must be ai_disabled when ai.enabled=false');
    assertTrue((bool)($availabilityWhenAiDisabled['payload']['data']['intents']['admin_log_review']['enabled'] ?? true) === false, 'admin_log_review must be disabled for ai.use user');
    assertTrue((string)($availabilityWhenAiDisabled['payload']['data']['intents']['admin_log_review']['reason'] ?? '') === 'permission_required', 'admin_log_review reason must be permission_required for ai.use user');

    // ai.admin user can open provider settings.
    $adminAuth = request('POST', '/api/v1/auth/login', ['login' => $adminLogin, 'password' => $adminPassword, 'token' => $adminToken]);
    assertTrue($adminAuth['status'] === 200, 'AI admin login status must be 200');
    $adminHeaders = authHeaders((string)($adminAuth['payload']['data']['access_token'] ?? ''));
    $adminProvidersOpen = request('GET', '/api/v1/ai/providers', [], $adminHeaders);
    assertTrue($adminProvidersOpen['status'] === 200, 'User with ai.admin must access provider list');

    $settingsForbidden = request('GET', '/api/v1/ai/settings', [], $userHeaders);
    assertTrue($settingsForbidden['status'] === 403, 'AI user without ai.admin must get 403 on ai settings');
    $settingsGet = request('GET', '/api/v1/ai/settings', [], $adminHeaders);
    assertTrue($settingsGet['status'] === 200, 'AI admin must access ai settings');
    $settingsPatch = request('PATCH', '/api/v1/ai/settings', [
        'default_model' => 'mock-gpt-4.1-mini',
        'strict_json_mode' => 1,
    ], $adminHeaders);
    assertTrue($settingsPatch['status'] === 200, 'AI admin must update ai settings');
    $settingsNoChanges = request('PATCH', '/api/v1/ai/settings', [], $adminHeaders);
    assertTrue($settingsNoChanges['status'] === 422, 'AI settings patch without changes must be rejected');

    // SSRF guard for localhost/private network in production mode.
    $blockedProvider = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'blocked_local',
        'title' => 'Blocked Local Provider',
        'base_url' => 'http://127.0.0.1:1234',
        'default_model' => 'local-model',
    ], $rootHeaders);
    assertTrue($blockedProvider['status'] === 422, 'Localhost/private provider URL must be rejected in production mode');

    $blockedHeadersProvider = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'blocked_headers',
        'title' => 'Blocked Headers Provider',
        'base_url' => 'https://example.com',
        'extra_headers' => ['Authorization' => 'Bearer do-not-store'],
    ], $rootHeaders);
    assertTrue($blockedHeadersProvider['status'] === 422, 'Forbidden extra_headers must be rejected');

    // Root can create provider and set masked secret.
    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'Primary AI Provider',
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-gpt-4.1-mini',
        'provider_payload' => [
            'mock_models' => ['mock-gpt-4.1-mini', 'mock-fast'],
        ],
        'is_default' => 1,
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($providerCreate['status'] === 201, 'Root provider create status must be 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Created provider public_id is required');

    $rawSecret = 'super-secret-ai-token-' . randomSuffix();
    $secretLast4 = strlen($rawSecret) > 4 ? substr($rawSecret, -4) : $rawSecret;
    $secretSet = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => $rawSecret,
    ], $rootHeaders);
    assertTrue($secretSet['status'] === 200, 'Secret update status must be 200');
    assertTrue((string)($secretSet['payload']['data']['credential']['masked_value'] ?? '') === '***', 'Secret response must be masked');
    assertTrue((string)($secretSet['payload']['data']['credential']['credential_last4'] ?? '') === $secretLast4, 'Secret response must include last4 indicator');

    $providerGet = request('GET', '/api/v1/ai/providers/' . $providerPublicId, [], $rootHeaders);
    assertTrue($providerGet['status'] === 200, 'Provider get status must be 200');
    $providerPayload = (array)($providerGet['payload']['data']['provider'] ?? []);
    assertTrue(!isset($providerPayload['secret']) && !isset($providerPayload['token']), 'Provider payload must not expose raw secret/token');
    assertTrue((bool)($providerPayload['credential_is_configured'] ?? false) === true, 'Provider must indicate secret presence');
    assertTrue((string)($providerPayload['credential_last4'] ?? '') === $secretLast4, 'Provider get must include secret_last4 indicator');
    $providerListAfterSecret = request('GET', '/api/v1/ai/providers', [], $rootHeaders);
    assertTrue($providerListAfterSecret['status'] === 200, 'Provider list after secret set must be 200');
    $providerListItems = (array)($providerListAfterSecret['payload']['data']['items'] ?? []);
    $providerInList = null;
    foreach ($providerListItems as $providerItem) {
        if ((string)($providerItem['public_id'] ?? '') === $providerPublicId) {
            $providerInList = (array)$providerItem;
            break;
        }
    }
    assertTrue(is_array($providerInList), 'Provider must be present in providers list after secret set');
    assertTrue((bool)($providerInList['credential_is_configured'] ?? false) === true, 'Provider list must indicate secret presence');
    assertTrue((string)($providerInList['credential_last4'] ?? '') === $secretLast4, 'Provider list must include secret_last4 indicator');

    // Models/test endpoints are admin-only; ai.use user must get 403.
    $modelsForbidden = request('GET', '/api/v1/ai/models', [], $userHeaders);
    assertTrue($modelsForbidden['status'] === 403, 'AI user without ai.admin must get 403 on models');
    $testForbidden = request('POST', '/api/v1/ai/providers/' . $providerPublicId . '/test', [], $userHeaders);
    assertTrue($testForbidden['status'] === 403, 'AI user without ai.admin must get 403 on provider test');

    // Mock provider connectivity is deterministic and does not require external network.
    $providerTest = request('POST', '/api/v1/ai/providers/' . $providerPublicId . '/test', [], $rootHeaders);
    assertTrue($providerTest['status'] === 200, 'Mock provider test status must be 200');

    $modelsList = request('GET', '/api/v1/ai/models', ['provider_public_id' => $providerPublicId], $rootHeaders);
    assertTrue($modelsList['status'] === 200, 'Mock models list status must be 200');
    assertTrue((array)($modelsList['payload']['data']['items'] ?? []) !== [], 'Mock models list must contain items');

    $modelsSync = request('POST', '/api/v1/ai/models/sync', ['provider_public_id' => $providerPublicId], $rootHeaders);
    assertTrue($modelsSync['status'] === 200, 'Mock models sync status must be 200');

    $providerSimulateTimeout = request('PATCH', '/api/v1/ai/providers/' . $providerPublicId, [
        'provider_payload' => [
            'mock_models' => ['mock-gpt-4.1-mini', 'mock-fast'],
            'simulate_test_error' => 'timeout',
        ],
    ], $rootHeaders);
    assertTrue($providerSimulateTimeout['status'] === 200, 'Provider payload patch for timeout simulation must be 200');
    $providerTestTimeout = request('POST', '/api/v1/ai/providers/' . $providerPublicId . '/test', [], $rootHeaders);
    assertTrue($providerTestTimeout['status'] === 504, 'Provider test timeout simulation must return 504');
    assertTrue((string)($providerTestTimeout['payload']['code'] ?? '') === 'AI_PROVIDER_TIMEOUT', 'Provider test timeout code mismatch');
    assertTrue((string)($providerTestTimeout['payload']['meta']['provider_error']['category'] ?? '') === 'timeout', 'Provider timeout meta category mismatch');
    assertTrue((bool)($providerTestTimeout['payload']['meta']['provider_error']['retryable'] ?? false) === true, 'Provider timeout must be retryable');

    $providerSimulateUnavailable = request('PATCH', '/api/v1/ai/providers/' . $providerPublicId, [
        'provider_payload' => [
            'mock_models' => ['mock-gpt-4.1-mini', 'mock-fast'],
            'simulate_test_error' => 'connection-refused',
        ],
    ], $rootHeaders);
    assertTrue($providerSimulateUnavailable['status'] === 200, 'Provider payload patch for unavailable simulation must be 200');
    $providerTestUnavailable = request('POST', '/api/v1/ai/providers/' . $providerPublicId . '/test', [], $rootHeaders);
    assertTrue($providerTestUnavailable['status'] === 502, 'Provider test unavailable simulation must return 502');
    assertTrue((string)($providerTestUnavailable['payload']['code'] ?? '') === 'AI_PROVIDER_UNAVAILABLE', 'Provider unavailable code mismatch');
    assertTrue((string)($providerTestUnavailable['payload']['meta']['provider_error']['category'] ?? '') === 'unavailable', 'Provider unavailable meta category mismatch');
    assertTrue((bool)($providerTestUnavailable['payload']['meta']['provider_error']['retryable'] ?? false) === true, 'Provider unavailable must be retryable');

    $providerSimulateModelsAuth = request('PATCH', '/api/v1/ai/providers/' . $providerPublicId, [
        'provider_payload' => [
            'mock_models' => ['mock-gpt-4.1-mini', 'mock-fast'],
            'simulate_models_error' => 'auth',
            'simulate_test_error' => '',
        ],
    ], $rootHeaders);
    assertTrue($providerSimulateModelsAuth['status'] === 200, 'Provider payload patch for models auth simulation must be 200');
    $modelsAuthFailed = request('GET', '/api/v1/ai/models', ['provider_public_id' => $providerPublicId], $rootHeaders);
    assertTrue($modelsAuthFailed['status'] === 502, 'Models auth simulation must return 502');
    assertTrue((string)($modelsAuthFailed['payload']['code'] ?? '') === 'AI_PROVIDER_AUTH_FAILED', 'Models auth simulation code mismatch');
    assertTrue((string)($modelsAuthFailed['payload']['meta']['provider_error']['category'] ?? '') === 'auth', 'Models auth meta category mismatch');
    assertTrue((bool)($modelsAuthFailed['payload']['meta']['provider_error']['retryable'] ?? true) === false, 'Models auth must not be retryable');

    $modelsSyncAuthFailed = request('POST', '/api/v1/ai/models/sync', ['provider_public_id' => $providerPublicId], $rootHeaders);
    assertTrue($modelsSyncAuthFailed['status'] === 502, 'Models sync auth simulation must return 502');
    assertTrue((string)($modelsSyncAuthFailed['payload']['code'] ?? '') === 'AI_PROVIDER_AUTH_FAILED', 'Models sync auth simulation code mismatch');

    $providerRestorePayload = request('PATCH', '/api/v1/ai/providers/' . $providerPublicId, [
        'provider_payload' => [
            'mock_models' => ['mock-gpt-4.1-mini', 'mock-fast'],
        ],
    ], $rootHeaders);
    assertTrue($providerRestorePayload['status'] === 200, 'Provider payload restore after simulation must be 200');

    // Intent settings are admin-only.
    $intentSettingsForbidden = request('GET', '/api/v1/ai/intent-settings', [], $userHeaders);
    assertTrue($intentSettingsForbidden['status'] === 403, 'AI user without ai.admin must get 403 on intent settings list');

    $intentSettingsList = request('GET', '/api/v1/ai/intent-settings', [], $adminHeaders);
    assertTrue($intentSettingsList['status'] === 200, 'AI admin must access intent settings list');
    $intentItems = (array)($intentSettingsList['payload']['data']['items'] ?? []);
    assertTrue($intentItems !== [], 'Intent settings list must not be empty');
    $taskSummaryIntentPresent = false;
    foreach ($intentItems as $intentItem) {
        if ((string)($intentItem['intent_code'] ?? '') === 'task_summary') {
            $taskSummaryIntentPresent = true;
            break;
        }
    }
    assertTrue($taskSummaryIntentPresent, 'task_summary intent must be present in intent settings');

    $intentUpdate = request('PATCH', '/api/v1/ai/intent-settings/task_summary', [
        'provider_public_id' => $providerPublicId,
        'max_tokens' => 1500,
        'is_enabled' => 1,
        'feature_flag' => 'ai.task',
    ], $adminHeaders);
    assertTrue($intentUpdate['status'] === 200, 'Intent update status must be 200');
    assertTrue((string)($intentUpdate['payload']['data']['item']['intent_code'] ?? '') === 'task_summary', 'Intent update must return task_summary');

    $intentUpdateInvalid = request('PATCH', '/api/v1/ai/intent-settings/not_allowed_intent', [
        'is_enabled' => 1,
    ], $adminHeaders);
    assertTrue($intentUpdateInvalid['status'] === 422, 'Unknown intent update must be rejected');
    assertTrue((string)($intentUpdateInvalid['payload']['code'] ?? '') === 'AI_INTENT_NOT_ALLOWED', 'Unknown intent update code mismatch');

    // Prompt templates / schemas are admin-only.
    $promptForbidden = request('GET', '/api/v1/ai/prompt-templates', [], $userHeaders);
    assertTrue($promptForbidden['status'] === 403, 'AI user without ai.admin must get 403 on prompt templates');
    $schemaForbidden = request('GET', '/api/v1/ai/json-schemas', [], $userHeaders);
    assertTrue($schemaForbidden['status'] === 403, 'AI user without ai.admin must get 403 on json schemas');

    $promptCreate = request('POST', '/api/v1/ai/prompt-templates', [
        'intent_code' => 'task_summary',
        'locale' => 'ru-ru',
        'version' => 1,
        'template_text' => 'Сформируй краткую сводку задачи в JSON по schema.',
        'is_active' => 1,
    ], $adminHeaders);
    assertTrue($promptCreate['status'] === 201, 'Prompt template create status must be 201');
    $promptPublicId = (string)($promptCreate['payload']['data']['prompt']['public_id'] ?? '');
    assertTrue($promptPublicId !== '', 'Prompt template public_id is required');

    $promptList = request('GET', '/api/v1/ai/prompt-templates', ['intent_code' => 'task_summary'], $adminHeaders);
    assertTrue($promptList['status'] === 200, 'Prompt templates list status must be 200');
    assertTrue((array)($promptList['payload']['data']['items'] ?? []) !== [], 'Prompt templates list must contain created template');

    $schemaInvalid = request('POST', '/api/v1/ai/json-schemas', [
        'intent_code' => 'task_summary',
        'schema_version' => 'v1',
        'schema_json' => '{"type":"array"}',
    ], $adminHeaders);
    assertTrue($schemaInvalid['status'] === 422, 'Invalid schema definition must be rejected');
    assertTrue((string)($schemaInvalid['payload']['code'] ?? '') === 'AI_SCHEMA_VALIDATION_FAILED', 'Invalid schema code mismatch');

    $schemaCreate = request('POST', '/api/v1/ai/json-schemas', [
        'intent_code' => 'task_summary',
        'schema_version' => 'v1',
        'schema_json' => [
            'type' => 'object',
            'required' => ['summary', 'risks', 'suggested_tasks', 'checklist_items', 'calendar_slots', 'questions'],
            'properties' => [
                'summary' => ['type' => 'string'],
                'risks' => ['type' => 'array'],
                'suggested_tasks' => ['type' => 'array'],
                'checklist_items' => ['type' => 'array'],
                'calendar_slots' => ['type' => 'array'],
                'questions' => ['type' => 'array'],
            ],
        ],
        'is_active' => 1,
    ], $adminHeaders);
    assertTrue($schemaCreate['status'] === 201, 'JSON schema create status must be 201');
    $schemaPublicId = (string)($schemaCreate['payload']['data']['schema']['public_id'] ?? '');
    assertTrue($schemaPublicId !== '', 'JSON schema public_id is required');

    $schemaList = request('GET', '/api/v1/ai/json-schemas', ['intent_code' => 'task_summary'], $adminHeaders);
    assertTrue($schemaList['status'] === 200, 'JSON schema list status must be 200');
    assertTrue((array)($schemaList['payload']['data']['items'] ?? []) !== [], 'JSON schema list must contain created schema');

    // invalid action type must fail.
    $enableAi = request('PATCH', '/api/v1/feature-flags/' . $aiEnabledPublicId, [
        'is_enabled' => 1,
    ], $rootHeaders);
    assertTrue($enableAi['status'] === 200, 'Enable ai.enabled must be 200');
    $availabilityWhenAiEnabled = request('GET', '/api/v1/ai/availability', ['intents' => 'task_summary,admin_log_review'], $adminHeaders);
    assertTrue($availabilityWhenAiEnabled['status'] === 200, 'AI availability endpoint must be available for ai.admin user');
    assertTrue((bool)($availabilityWhenAiEnabled['payload']['data']['ai']['enabled'] ?? false) === true, 'Availability must reflect ai.enabled=true');
    assertTrue((bool)($availabilityWhenAiEnabled['payload']['data']['intents']['task_summary']['enabled'] ?? false) === true, 'task_summary must be enabled for ai.admin+ai.use user');
    assertTrue((bool)($availabilityWhenAiEnabled['payload']['data']['intents']['admin_log_review']['enabled'] ?? false) === true, 'admin_log_review must be enabled for ai.admin user');

    $disableAiTask = request('PATCH', '/api/v1/feature-flags/' . $aiTaskPublicId, [
        'is_enabled' => 0,
    ], $rootHeaders);
    assertTrue($disableAiTask['status'] === 200, 'Disable ai.task must be 200');

    // ai.task off -> task summary suggestion endpoint is safely disabled.
    $featureDisabledSummary = request('POST', '/api/v1/ai/tasks/tsk_demo/summary', [
        'prompt' => 'Summarize this task',
    ], $userHeaders);
    assertTrue($featureDisabledSummary['status'] === 409, 'Task summary with ai.task=0 must return 409');
    assertTrue((string)($featureDisabledSummary['payload']['code'] ?? '') === 'AI_FEATURE_DISABLED', 'ai.task disabled code mismatch');

    $enableAiTask = request('PATCH', '/api/v1/feature-flags/' . $aiTaskPublicId, [
        'is_enabled' => 1,
    ], $rootHeaders);
    assertTrue($enableAiTask['status'] === 200, 'Enable ai.task must be 200');

    $enableAiProject = request('PATCH', '/api/v1/feature-flags/' . (string)$flagsByCode['ai.project'], [
        'is_enabled' => 1,
    ], $rootHeaders);
    assertTrue($enableAiProject['status'] === 200, 'Enable ai.project must be 200');
    $enableAiClient = request('PATCH', '/api/v1/feature-flags/' . (string)$flagsByCode['ai.client'], [
        'is_enabled' => 1,
    ], $rootHeaders);
    assertTrue($enableAiClient['status'] === 200, 'Enable ai.client must be 200');
    $enableAiCalendar = request('PATCH', '/api/v1/feature-flags/' . (string)$flagsByCode['ai.calendar'], [
        'is_enabled' => 1,
    ], $rootHeaders);
    assertTrue($enableAiCalendar['status'] === 200, 'Enable ai.calendar must be 200');
    $enableAiAnalytics = request('PATCH', '/api/v1/feature-flags/' . (string)$flagsByCode['ai.analytics'], [
        'is_enabled' => 1,
    ], $rootHeaders);
    assertTrue($enableAiAnalytics['status'] === 200, 'Enable ai.analytics must be 200');

    $enableAiDailyPlan = request('PATCH', '/api/v1/feature-flags/' . $aiDailyPlanPublicId, [
        'is_enabled' => 1,
    ], $rootHeaders);
    assertTrue($enableAiDailyPlan['status'] === 200, 'Enable ai.cron.daily_work_plan must be 200');

    $invalidAction = request('POST', '/api/v1/ai/actions/not_allowed_action', [], $userHeaders);
    assertTrue($invalidAction['status'] === 422, 'Invalid action type must be rejected');
    assertTrue((string)($invalidAction['payload']['code'] ?? '') === 'AI_ACTION_TYPE_NOT_ALLOWED', 'Invalid action code mismatch');

    // ai.use user can execute allowed action via configured provider.
    $allowedAction = request('POST', '/api/v1/ai/actions/task_summary', [
        'scope_type' => 'task',
        'scope_public_id' => 'tsk_demo',
        'input_text' => 'Summarize task context',
    ], $userHeaders);
    assertTrue($allowedAction['status'] === 200, 'Allowed AI action must return 200');

    $aiPreferencesGet = request('GET', '/api/v1/ai/preferences', [], $userHeaders);
    assertTrue($aiPreferencesGet['status'] === 200, 'AI preferences get must return 200');
    $initialAiPreferences = (array)($aiPreferencesGet['payload']['data']['preferences'] ?? []);
    assertTrue(isset($initialAiPreferences['daily_plan_enabled']), 'AI preferences response must include defaults');

    $aiPreferencesPatchNoChanges = request('PATCH', '/api/v1/ai/preferences', [], $userHeaders);
    assertTrue($aiPreferencesPatchNoChanges['status'] === 422, 'AI preferences patch without changes must be rejected');

    $aiPreferencesPatch = request('PATCH', '/api/v1/ai/preferences', [
        'preferences' => [
            'daily_plan_enabled' => 0,
            'preferred_response_length' => 'medium',
            'focus_block_minutes' => 120,
        ],
    ], $userHeaders);
    assertTrue($aiPreferencesPatch['status'] === 200, 'AI preferences patch must return 200');
    $patchedAiPreferences = (array)($aiPreferencesPatch['payload']['data']['preferences'] ?? []);
    assertTrue((bool)($patchedAiPreferences['daily_plan_enabled'] ?? true) === false, 'AI preferences daily_plan_enabled must be updated');
    assertTrue((string)($patchedAiPreferences['preferred_response_length'] ?? '') === 'medium', 'AI preferences preferred_response_length must be updated');
    assertTrue((int)($patchedAiPreferences['focus_block_minutes'] ?? 0) === 120, 'AI preferences focus_block_minutes must be updated');

    $usageForbidden = request('GET', '/api/v1/ai/usage', [], $userHeaders);
    assertTrue($usageForbidden['status'] === 403, 'AI user without ai.admin/ai.view_audit must get 403 on ai usage');
    $auditForbidden = request('GET', '/api/v1/ai/audit', [], $userHeaders);
    assertTrue($auditForbidden['status'] === 403, 'AI user without ai.admin/ai.view_audit must get 403 on ai audit');

    $usageAdmin = request('GET', '/api/v1/ai/usage', ['limit' => 10], $adminHeaders);
    assertTrue($usageAdmin['status'] === 200, 'AI admin must access ai usage');
    $auditAdmin = request('GET', '/api/v1/ai/audit', ['limit' => 10], $adminHeaders);
    assertTrue($auditAdmin['status'] === 200, 'AI admin must access ai audit');
    $jobsForbidden = request('GET', '/api/v1/ai/jobs', [], $userHeaders);
    assertTrue($jobsForbidden['status'] === 403, 'AI user without ai.admin/ai.view_cron_results must get 403 on ai jobs');
    $jobsAdmin = request('GET', '/api/v1/ai/jobs', ['limit' => 10], $adminHeaders);
    assertTrue($jobsAdmin['status'] === 200, 'AI admin must access ai jobs list');
    $jobDryRunForbidden = request('POST', '/api/v1/ai/jobs/ai:user-daily-work-plan/dry-run', [], $userHeaders);
    assertTrue($jobDryRunForbidden['status'] === 403, 'AI user without ai.manage_cron_jobs must get 403 on ai jobs dry-run');
    $jobRunOnceForbidden = request('POST', '/api/v1/ai/jobs/ai:user-daily-work-plan/run-once', [], $userHeaders);
    assertTrue($jobRunOnceForbidden['status'] === 403, 'AI user without ai.manage_cron_jobs must get 403 on ai jobs run-once');
    $jobRetryForbidden = request('POST', '/api/v1/ai/jobs/aij_unknown/retry', [], $userHeaders);
    assertTrue($jobRetryForbidden['status'] === 403, 'AI user without ai.manage_cron_jobs must get 403 on ai jobs retry');

    $jobDryRunInvalidCode = request('POST', '/api/v1/ai/jobs/ai:unknown-job/dry-run', [], $adminHeaders);
    assertTrue($jobDryRunInvalidCode['status'] === 422, 'Unknown ai job code dry-run must return 422');
    assertTrue((string)($jobDryRunInvalidCode['payload']['code'] ?? '') === 'AI_JOB_CODE_NOT_ALLOWED', 'Unknown ai job code dry-run code mismatch');

    $myDayPlan = request('POST', '/api/v1/ai/my-day/plan', [], $userHeaders);
    assertTrue($myDayPlan['status'] === 409, 'My day AI plan must be blocked when user disabled daily_plan_enabled');
    assertTrue((string)($myDayPlan['payload']['code'] ?? '') === 'AI_PREFERENCES_DAILY_PLAN_DISABLED', 'My day plan opt-out code mismatch');

    $myWeekPlan = request('POST', '/api/v1/ai/my-week/plan', [], $userHeaders);
    assertTrue($myWeekPlan['status'] === 201, 'My week AI plan create must return 201');
    assertTrue((string)($myWeekPlan['payload']['data']['suggestion']['intent_code'] ?? '') === 'my_week_plan', 'My week plan suggestion must have my_week_plan intent');
    assertTrue(is_array($myWeekPlan['payload']['data']['suggestion']['payload']['tasks_by_day'] ?? null), 'My week plan must provide tasks_by_day payload');
    assertTrue(is_array($myWeekPlan['payload']['data']['suggestion']['payload']['suggested_events'] ?? null), 'My week plan must provide suggested_events payload');

    $priorityParentTaskCreate = request('POST', '/api/v1/tasks', [
        'title' => 'AI Priority Parent Task ' . randomSuffix(),
        'description' => 'Parent task for AI priority smoke',
        'priority' => 'high',
    ], $userHeaders);
    assertTrue($priorityParentTaskCreate['status'] === 201, 'AI priority parent task create status must be 201');
    $priorityParentTaskPublicId = (string)($priorityParentTaskCreate['payload']['data']['task']['public_id'] ?? '');
    assertTrue($priorityParentTaskPublicId !== '', 'AI priority parent task public_id is required');

    $priorityChildTaskCreate = request('POST', '/api/v1/tasks/' . $priorityParentTaskPublicId . '/subtasks', [
        'title' => 'AI Priority Child Task ' . randomSuffix(),
        'description' => 'Child task for AI priority smoke',
        'priority' => 'normal',
    ], $userHeaders);
    assertTrue($priorityChildTaskCreate['status'] === 201, 'AI priority child task create status must be 201');
    $priorityChildTaskPublicId = (string)($priorityChildTaskCreate['payload']['data']['subtask']['public_id'] ?? '');
    assertTrue($priorityChildTaskPublicId !== '', 'AI priority child task public_id is required');

    $taskListPriority = request('POST', '/api/v1/ai/tasks/priority', [
        'task_public_ids' => [$priorityChildTaskPublicId, $priorityParentTaskPublicId],
        'view_mode' => 'tree',
        'filters' => [
            'search' => '',
            'status' => '',
            'priority' => '',
            'sort' => 'priority_code',
            'order' => 'DESC',
        ],
    ], $userHeaders);
    assertTrue($taskListPriority['status'] === 201, 'Task list AI priority create must return 201');
    assertTrue((string)($taskListPriority['payload']['data']['suggestion']['intent_code'] ?? '') === 'task_list_priority', 'Task list AI priority suggestion must have task_list_priority intent');
    $taskListPriorityPayload = (array)($taskListPriority['payload']['data']['suggestion']['payload'] ?? []);
    assertTrue(is_array($taskListPriorityPayload['ordered_task_ids'] ?? null), 'Task list AI priority must provide ordered_task_ids payload');
    $orderedTaskIds = (array)($taskListPriorityPayload['ordered_task_ids'] ?? []);
    $parentIndex = array_search($priorityParentTaskPublicId, $orderedTaskIds, true);
    $childIndex = array_search($priorityChildTaskPublicId, $orderedTaskIds, true);
    assertTrue($parentIndex !== false && $childIndex !== false && $parentIndex < $childIndex, 'Task list AI priority must keep parent task before subtask');

    $jobDryRun = request('POST', '/api/v1/ai/jobs/ai:user-daily-work-plan/dry-run', [
        'scope_public_id' => $userPublicId,
    ], $adminHeaders);
    assertTrue($jobDryRun['status'] === 200, 'AI admin must access ai jobs dry-run');
    assertTrue((string)($jobDryRun['payload']['code'] ?? '') === 'AI_JOB_DRY_RUN', 'AI jobs dry-run code mismatch');
    assertTrue((bool)($jobDryRun['payload']['data']['dry_run']['can_run'] ?? true) === false, 'AI jobs dry-run must be blocked when user daily plan preference is disabled');
    $jobDryRunChecks = (array)($jobDryRun['payload']['data']['dry_run']['checks'] ?? []);
    $dailyPlanEnabledCheck = null;
    foreach ($jobDryRunChecks as $check) {
        if (!is_array($check)) {
            continue;
        }
        if ((string)($check['name'] ?? '') === 'daily_plan_enabled') {
            $dailyPlanEnabledCheck = $check;
            break;
        }
    }
    assertTrue(is_array($dailyPlanEnabledCheck), 'AI jobs dry-run checks must include daily_plan_enabled');
    assertTrue((bool)($dailyPlanEnabledCheck['ok'] ?? true) === false, 'AI jobs dry-run daily_plan_enabled check must fail for opted-out user');

    $runOnceWindowStart = date('Y-m-d 00:00:00');
    $runOnceWindowEnd = date('Y-m-d 23:59:59', time() + 86400);
    $eventsBeforeCronRunOnce = request(
        'GET',
        '/api/v1/calendar/events?from=' . rawurlencode($runOnceWindowStart) . '&to=' . rawurlencode($runOnceWindowEnd),
        [],
        $rootHeaders
    );
    assertTrue($eventsBeforeCronRunOnce['status'] === 200, 'Calendar events list before cron run-once must be 200');
    $calendarItemsBeforeCronRunOnce = (array)($eventsBeforeCronRunOnce['payload']['data']['items'] ?? []);

    $reEnableDailyPlanPreference = request('PATCH', '/api/v1/ai/preferences', [
        'preferences' => [
            'daily_plan_enabled' => 1,
        ],
    ], $userHeaders);
    assertTrue($reEnableDailyPlanPreference['status'] === 200, 'AI preferences daily_plan_enabled re-enable before run-once must return 200');

    $myDayPlanEnabled = request('POST', '/api/v1/ai/my-day/plan', [], $userHeaders);
    assertTrue($myDayPlanEnabled['status'] === 201, 'My day AI plan create must return 201 after re-enable');
    assertTrue((string)($myDayPlanEnabled['payload']['data']['suggestion']['intent_code'] ?? '') === 'my_day_plan', 'My day plan suggestion must have my_day_plan intent');
    assertTrue((string)($myDayPlanEnabled['payload']['data']['suggestion']['payload']['meta']['marker_version'] ?? '') === 'my_day_source_v1', 'My day plan marker_version must be stable');
    assertTrue((string)($myDayPlanEnabled['payload']['data']['suggestion']['payload']['meta']['source_marker'] ?? '') === 'manual', 'My day plan source marker must default to manual for ai.use flow');
    assertTrue((string)($myDayPlanEnabled['payload']['data']['suggestion']['payload']['meta']['execution_mode'] ?? '') === 'manual', 'My day plan execution mode must default to manual for ai.use flow');

    $myDayJobPublicId = (string)($myDayPlanEnabled['payload']['data']['job_public_id'] ?? '');
    if ($myDayJobPublicId !== '') {
        $jobDetailAdmin = request('GET', '/api/v1/ai/jobs/' . $myDayJobPublicId, [], $adminHeaders);
        assertTrue($jobDetailAdmin['status'] === 200, 'AI admin must access ai job detail');
        assertTrue((string)($jobDetailAdmin['payload']['data']['job']['public_id'] ?? '') === $myDayJobPublicId, 'AI job detail must return requested public_id');

        $jobRetry = request('POST', '/api/v1/ai/jobs/' . $myDayJobPublicId . '/retry', [], $adminHeaders);
        assertTrue($jobRetry['status'] === 201, 'AI admin must be able to retry AI job');
        $retryJobPublicId = (string)($jobRetry['payload']['data']['job']['public_id'] ?? '');
        assertTrue($retryJobPublicId !== '' && $retryJobPublicId !== $myDayJobPublicId, 'Retry must create new job public_id');
        assertTrue((string)($jobRetry['payload']['data']['job']['status'] ?? '') === 'queued', 'Retry job status must be queued');
    }

    $jobRunOnce = request('POST', '/api/v1/ai/jobs/ai:user-daily-work-plan/run-once', [
        'scope_public_id' => $userPublicId,
    ], $adminHeaders);
    assertTrue($jobRunOnce['status'] === 201, 'AI admin must access ai jobs run-once');
    assertTrue((string)($jobRunOnce['payload']['code'] ?? '') === 'AI_JOB_RUN_ONCE_SCHEDULED', 'AI jobs run-once code mismatch');
    $runOnceJobPublicId = (string)($jobRunOnce['payload']['data']['job']['public_id'] ?? '');
    assertTrue($runOnceJobPublicId !== '', 'AI jobs run-once must return job public_id');
    if ($runOnceJobPublicId !== '') {
        $runOnceJobDetail = request('GET', '/api/v1/ai/jobs/' . $runOnceJobPublicId, [], $adminHeaders);
        assertTrue($runOnceJobDetail['status'] === 200, 'Run-once created job must be available in jobs detail');
        assertTrue((string)($runOnceJobDetail['payload']['data']['job']['status'] ?? '') === 'queued', 'Run-once created job status must be queued');
    }

    $eventsAfterCronRunOnce = request(
        'GET',
        '/api/v1/calendar/events?from=' . rawurlencode($runOnceWindowStart) . '&to=' . rawurlencode($runOnceWindowEnd),
        [],
        $rootHeaders
    );
    assertTrue($eventsAfterCronRunOnce['status'] === 200, 'Calendar events list after cron run-once must be 200');
    $calendarItemsAfterCronRunOnce = (array)($eventsAfterCronRunOnce['payload']['data']['items'] ?? []);
    assertTrue(
        count($calendarItemsBeforeCronRunOnce) === count($calendarItemsAfterCronRunOnce),
        'Cron run-once must not auto-create calendar business entities without explicit apply confirmation'
    );

    // First interactive suggestion flow: task summary draft/suggestion lifecycle.
    $taskCreate = request('POST', '/api/v1/tasks', [
        'title' => 'AI Summary Smoke Task ' . randomSuffix(),
        'description' => 'Client email smoke@example.com should be masked in AI context.',
    ], $userHeaders);
    assertTrue($taskCreate['status'] === 201, 'AI smoke task create status must be 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'AI smoke task public_id is required');

    $taskSummary = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/summary', [
        'prompt' => 'Build concise summary',
    ], $userHeaders);
    assertTrue($taskSummary['status'] === 201, 'Task summary suggestion create must return 201');
    $suggestionPublicId = (string)($taskSummary['payload']['data']['suggestion']['public_id'] ?? '');
    assertTrue($suggestionPublicId !== '', 'Task summary suggestion public_id is required');

    $taskDecompose = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/decompose', [], $userHeaders);
    assertTrue($taskDecompose['status'] === 201, 'Task decompose suggestion create must return 201');
    $taskDecomposeSuggestionPublicId = (string)($taskDecompose['payload']['data']['suggestion']['public_id'] ?? '');
    assertTrue($taskDecomposeSuggestionPublicId !== '', 'Task decompose suggestion public_id is required');
    $taskChecklist = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/checklist', [], $userHeaders);
    assertTrue($taskChecklist['status'] === 201, 'Task checklist suggestion create must return 201');
    $taskChecklistSuggestionPublicId = (string)($taskChecklist['payload']['data']['suggestion']['public_id'] ?? '');
    assertTrue($taskChecklistSuggestionPublicId !== '', 'Task checklist suggestion public_id is required');
    $taskQuality = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/quality', [], $userHeaders);
    assertTrue($taskQuality['status'] === 201, 'Task quality suggestion create must return 201');
    $taskNextAction = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/next-action', [], $userHeaders);
    assertTrue($taskNextAction['status'] === 201, 'Task next-action suggestion create must return 201');
    $taskNextActionSuggestionPublicId = (string)($taskNextAction['payload']['data']['suggestion']['public_id'] ?? '');
    assertTrue($taskNextActionSuggestionPublicId !== '', 'Task next-action suggestion public_id is required');
    $taskCommentDraft = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/comment-draft', [], $userHeaders);
    assertTrue($taskCommentDraft['status'] === 201, 'Task comment-draft suggestion create must return 201');
    $taskCommentDraftSuggestionPublicId = (string)($taskCommentDraft['payload']['data']['suggestion']['public_id'] ?? '');
    assertTrue($taskCommentDraftSuggestionPublicId !== '', 'Task comment-draft suggestion public_id is required');

    $largeContextTask = request('POST', '/api/v1/tasks', [
        'title' => 'AI Large Context Smoke Task ' . randomSuffix(),
        'description' => str_repeat('Large context line for storage policy checks. ', 1200),
    ], $userHeaders);
    assertTrue($largeContextTask['status'] === 201, 'Large-context task create status must be 201');
    $largeContextTaskPublicId = (string)($largeContextTask['payload']['data']['task']['public_id'] ?? '');
    assertTrue($largeContextTaskPublicId !== '', 'Large-context task public_id is required');
    $largeContextSummary = request('POST', '/api/v1/ai/tasks/' . $largeContextTaskPublicId . '/summary', [
        'prompt' => 'Summarize only key points in 3 bullets.',
    ], $userHeaders);
    assertTrue($largeContextSummary['status'] === 201, 'Large-context task summary create must return 201');

    $projectCreate = request('POST', '/api/v1/projects', [
        'title' => 'AI Project Smoke ' . randomSuffix(),
        'description' => 'Project context for AI endpoints',
    ], $rootHeaders);
    assertTrue($projectCreate['status'] === 201, 'Project create for AI smoke must be 201');
    $projectPublicId = (string)($projectCreate['payload']['data']['project']['public_id'] ?? '');
    assertTrue($projectPublicId !== '', 'Project public_id for AI smoke is required');

    $clientCreate = request('POST', '/api/v1/clients', [
        'title' => 'AI Client Smoke ' . randomSuffix(),
        'client_type' => 'legal_entity',
        'legal_name' => 'AI Client Smoke LLC',
        'tax_inn' => '1234567890',
        'tax_kpp' => '123456789',
        'tax_ogrn' => '1234567890123',
        'status' => 'active',
    ], $rootHeaders);
    assertTrue($clientCreate['status'] === 201, 'Client create for AI smoke must be 201');
    $clientPublicId = (string)($clientCreate['payload']['data']['client']['public_id'] ?? '');
    assertTrue($clientPublicId !== '', 'Client public_id for AI smoke is required');

    $eventCreate = request('POST', '/api/v1/calendar/events', [
        'title' => 'AI Agenda Smoke Event',
        'description' => 'Agenda context',
        'starts_at' => date('Y-m-d H:i:s', time() + 3600),
        'ends_at' => date('Y-m-d H:i:s', time() + 5400),
    ], $rootHeaders);
    assertTrue($eventCreate['status'] === 201, 'Calendar event create for AI smoke must be 201');
    $eventPublicId = (string)($eventCreate['payload']['data']['event']['public_id'] ?? '');
    assertTrue($eventPublicId !== '', 'Calendar event public_id for AI smoke is required');

    $projectSummary = request('POST', '/api/v1/ai/projects/' . $projectPublicId . '/summary', [], $rootHeaders);
    assertTrue($projectSummary['status'] === 201, 'Project summary suggestion create must return 201');
    $projectRisks = request('POST', '/api/v1/ai/projects/' . $projectPublicId . '/risks', [], $rootHeaders);
    assertTrue($projectRisks['status'] === 201, 'Project risks suggestion create must return 201');
    $projectClientReport = request('POST', '/api/v1/ai/projects/' . $projectPublicId . '/client-report', [], $rootHeaders);
    assertTrue($projectClientReport['status'] === 201, 'Project client-report suggestion create must return 201');
    $clientSummary = request('POST', '/api/v1/ai/clients/' . $clientPublicId . '/summary', [], $rootHeaders);
    assertTrue($clientSummary['status'] === 201, 'Client summary suggestion create must return 201');
    $clientMeetingPrep = request('POST', '/api/v1/ai/clients/' . $clientPublicId . '/meeting-prep', [], $rootHeaders);
    assertTrue($clientMeetingPrep['status'] === 201, 'Client meeting-prep suggestion create must return 201');
    $clientDataQuality = request('POST', '/api/v1/ai/clients/' . $clientPublicId . '/data-quality', [], $rootHeaders);
    assertTrue($clientDataQuality['status'] === 201, 'Client data-quality suggestion create must return 201');
    $clientSafeReport = request('POST', '/api/v1/ai/clients/' . $clientPublicId . '/client-safe-report', [], $rootHeaders);
    assertTrue($clientSafeReport['status'] === 201, 'Client client-safe-report suggestion create must return 201');
    $calendarAgenda = request('POST', '/api/v1/ai/calendar/events/' . $eventPublicId . '/agenda', [], $rootHeaders);
    assertTrue($calendarAgenda['status'] === 201, 'Calendar agenda suggestion create must return 201');
    $dashboardDigest = request('POST', '/api/v1/ai/dashboard/digest', [], $rootHeaders);
    assertTrue($dashboardDigest['status'] === 201, 'Dashboard digest suggestion create must return 201');
    $analyticsKpi = request('POST', '/api/v1/ai/analytics/kpi-explanation', [], $rootHeaders);
    assertTrue($analyticsKpi['status'] === 201, 'Analytics KPI explanation suggestion create must return 201');
    $analyticsRisks = request('POST', '/api/v1/ai/analytics/risks-explanation', [], $rootHeaders);
    assertTrue($analyticsRisks['status'] === 201, 'Analytics risks explanation suggestion create must return 201');
    $analyticsWorkload = request('POST', '/api/v1/ai/analytics/team-workload-summary', [], $rootHeaders);
    assertTrue($analyticsWorkload['status'] === 201, 'Analytics team workload summary suggestion create must return 201');
    $adminLogReview = request('POST', '/api/v1/ai/admin/log-review', [], $rootHeaders);
    assertTrue($adminLogReview['status'] === 201, 'Admin log review suggestion create must return 201');
    $webhookHealth = request('POST', '/api/v1/ai/admin/webhook-health', [], $rootHeaders);
    assertTrue($webhookHealth['status'] === 201, 'Webhook health review suggestion create must return 201');
    $workflowAudit = request('POST', '/api/v1/ai/admin/workflow-audit', [], $rootHeaders);
    assertTrue($workflowAudit['status'] === 201, 'Workflow rule audit suggestion create must return 201');

    $suggestionsList = request('GET', '/api/v1/ai/suggestions', [
        'entity_type' => 'task',
        'entity_public_id' => $taskPublicId,
    ], $userHeaders);
    assertTrue($suggestionsList['status'] === 200, 'Suggestions list status must be 200');
    $listItems = (array)($suggestionsList['payload']['data']['items'] ?? []);
    assertTrue($listItems !== [], 'Suggestions list must contain created suggestion');

    $suggestionGet = request('GET', '/api/v1/ai/suggestions/' . $suggestionPublicId, [], $userHeaders);
    assertTrue($suggestionGet['status'] === 200, 'Suggestion detail status must be 200');
    $suggestionSummaryText = (string)($suggestionGet['payload']['data']['suggestion']['summary'] ?? '');
    assertTrue($suggestionSummaryText !== '', 'Suggestion summary text must be present');

    $suggestionPreview = request('POST', '/api/v1/ai/suggestions/' . $suggestionPublicId . '/preview-apply', [], $userHeaders);
    assertTrue($suggestionPreview['status'] === 200, 'Suggestion preview status must be 200');
    $previewRequiresConfirmation = (bool)($suggestionPreview['payload']['data']['preview']['requires_confirmation'] ?? false);
    assertTrue($previewRequiresConfirmation === true, 'Suggestion preview must require explicit confirmation');
    $taskSummaryPreviewChanges = (array)($suggestionPreview['payload']['data']['preview']['changes'] ?? []);
    assertTrue($taskSummaryPreviewChanges === [], 'Default task summary preview must stay read-only without apply changes');

    $decomposePreview = request('POST', '/api/v1/ai/suggestions/' . $taskDecomposeSuggestionPublicId . '/preview-apply', [], $userHeaders);
    assertTrue($decomposePreview['status'] === 200, 'Task decompose preview status must be 200');
    $decomposeChanges = (array)($decomposePreview['payload']['data']['preview']['changes'] ?? []);
    assertTrue(count($decomposeChanges) > 0, 'Task decompose preview must contain selectable subtask actions');

    $checklistPreview = request('POST', '/api/v1/ai/suggestions/' . $taskChecklistSuggestionPublicId . '/preview-apply', [], $userHeaders);
    assertTrue($checklistPreview['status'] === 200, 'Task checklist preview status must be 200');
    $checklistChanges = (array)($checklistPreview['payload']['data']['preview']['changes'] ?? []);
    assertTrue(count($checklistChanges) > 0, 'Task checklist preview must contain selectable checklist actions');

    $nextActionPreview = request('POST', '/api/v1/ai/suggestions/' . $taskNextActionSuggestionPublicId . '/preview-apply', [], $userHeaders);
    assertTrue($nextActionPreview['status'] === 200, 'Task next-action preview status must be 200');
    $nextActionChanges = (array)($nextActionPreview['payload']['data']['preview']['changes'] ?? []);
    assertTrue(count($nextActionChanges) > 0, 'Task next-action preview must contain selectable actions');

    $commentDraftPreview = request('POST', '/api/v1/ai/suggestions/' . $taskCommentDraftSuggestionPublicId . '/preview-apply', [], $userHeaders);
    assertTrue($commentDraftPreview['status'] === 200, 'Task comment-draft preview status must be 200');
    $commentDraftChanges = (array)($commentDraftPreview['payload']['data']['preview']['changes'] ?? []);
    assertTrue(count($commentDraftChanges) > 0, 'Task comment-draft preview must contain comment-draft action');

    $suggestionApplyPreview = request('POST', '/api/v1/ai/suggestions/' . $suggestionPublicId . '/apply-preview', [], $userHeaders);
    assertTrue($suggestionApplyPreview['status'] === 200, 'Suggestion apply-preview status must be 200');
    $applyPreviewRequiresConfirmation = (bool)($suggestionApplyPreview['payload']['data']['preview']['requires_confirmation'] ?? false);
    assertTrue($applyPreviewRequiresConfirmation === true, 'Suggestion apply-preview must require explicit confirmation');

    $suggestionConfirmConflict = request('POST', '/api/v1/ai/suggestions/' . $suggestionPublicId . '/confirm', [
        'decision' => 'applied',
        'row_version' => 999999,
    ], $userHeaders);
    assertTrue($suggestionConfirmConflict['status'] === 409, 'Suggestion confirm with wrong row_version must return 409');
    assertTrue((string)($suggestionConfirmConflict['payload']['code'] ?? '') === 'AI_ROW_VERSION_CONFLICT', 'Suggestion confirm row_version conflict code mismatch');
    assertTrue((int)($suggestionConfirmConflict['payload']['meta']['row_version'] ?? 0) > 0, 'Suggestion confirm row_version conflict must include current row_version');

    // Apply through existing endpoint and confirm suggestion.
    $applyComment = request('POST', '/api/v1/tasks/' . $taskPublicId . '/comments', [
        'body' => '[AI summary] smoke apply',
    ], $userHeaders);
    assertTrue($applyComment['status'] === 201, 'Applying suggestion via existing comments endpoint must be 201');

    $suggestionConfirm = request('POST', '/api/v1/ai/suggestions/' . $suggestionPublicId . '/confirm', [
        'decision' => 'applied',
        'apply_target' => '/api/v1/tasks/{public_id}/comments',
        'apply_target_public_id' => $taskPublicId,
    ], $userHeaders);
    assertTrue($suggestionConfirm['status'] === 200, 'Suggestion confirm status must be 200');
    assertTrue((string)($suggestionConfirm['payload']['data']['suggestion']['status'] ?? '') === 'applied', 'Suggestion status after confirm must be applied');

    $suggestionDismiss = request('POST', '/api/v1/ai/suggestions/' . $suggestionPublicId . '/dismiss', [], $userHeaders);
    assertTrue($suggestionDismiss['status'] === 200, 'Suggestion dismiss status must be 200');
    assertTrue((string)($suggestionDismiss['payload']['data']['suggestion']['status'] ?? '') === 'applied', 'Applied suggestion must not be downgraded to dismissed');

    // Cookie-auth write must enforce CSRF policy.
    $cookieToken = (string)($userAuth['payload']['data']['access_token'] ?? '');
    $csrfToken = (string)($userAuth['payload']['data']['csrf_token'] ?? '');
    $cookieNoCsrf = request(
        'POST',
        '/api/v1/ai/actions/task_summary',
        ['scope_type' => 'task', 'scope_public_id' => 'tsk_demo'],
        [],
        [],
        ['crm_api_session' => $cookieToken]
    );
    assertTrue($cookieNoCsrf['status'] === 403, 'Cookie AI write without CSRF must return 403');
    assertTrue((string)($cookieNoCsrf['payload']['code'] ?? '') === 'CSRF_TOKEN_INVALID', 'Cookie AI write without CSRF must return CSRF_TOKEN_INVALID');

    $cookieWithCsrf = request(
        'POST',
        '/api/v1/ai/actions/task_summary',
        ['scope_type' => 'task', 'scope_public_id' => 'tsk_demo'],
        ['X-CSRF-Token' => $csrfToken],
        [],
        ['crm_api_session' => $cookieToken]
    );
    assertTrue($cookieWithCsrf['status'] === 200, 'Cookie AI write with valid CSRF must pass');

    $limitSet = request('PATCH', '/api/v1/settings/max_requests_per_minute', [
        'scope' => 'ai_limits',
        'value' => 1,
    ], $rootHeaders);
    assertTrue($limitSet['status'] === 200, 'Set ai_limits.max_requests_per_minute must return 200');

    $rateLimitedAction = request('POST', '/api/v1/ai/actions/task_summary', [
        'scope_type' => 'task',
        'scope_public_id' => 'tsk_demo',
    ], $userHeaders);
    assertTrue($rateLimitedAction['status'] === 429, 'AI rate limited action must return 429');
    assertTrue((string)($rateLimitedAction['payload']['code'] ?? '') === 'AI_RATE_LIMITED', 'AI rate limited code mismatch');
    assertTrue((int)($rateLimitedAction['payload']['meta']['retry_after'] ?? 0) > 0, 'AI rate limited response must include retry_after');

    $limitReset = request('PATCH', '/api/v1/settings/max_requests_per_minute', [
        'scope' => 'ai_limits',
        'value' => 60,
    ], $rootHeaders);
    assertTrue($limitReset['status'] === 200, 'Reset ai_limits.max_requests_per_minute must return 200');

    // Request logs should not contain raw provider token.
    $requestLogs = request('GET', '/api/v1/logs/request', ['limit' => 200], $rootHeaders);
    assertTrue($requestLogs['status'] === 200, 'Request logs read must return 200');
    $serializedLogs = json_encode($requestLogs['payload']['data']['items'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    assertTrue(is_string($serializedLogs), 'Request logs serialization must succeed');
    assertTrue(!str_contains($serializedLogs, $rawSecret), 'Request logs must not expose raw provider token');

    $securityLogs = request('GET', '/api/v1/logs/security', ['limit' => 200], $rootHeaders);
    assertTrue($securityLogs['status'] === 200, 'Security logs read must return 200');
    $serializedSecurityLogs = json_encode($securityLogs['payload']['data']['items'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    assertTrue(is_string($serializedSecurityLogs), 'Security logs serialization must succeed');
    assertTrue(!str_contains($serializedSecurityLogs, $rawSecret), 'Security logs must not expose raw provider token');

    // Idempotency baseline should stay intact for existing write endpoints.
    $idemKey = 'ai-foundation-task-' . randomSuffix();
    $idemTaskPayload = [
        'title' => 'AI idempotency baseline ' . randomSuffix(),
        'description' => 'baseline check',
    ];
    $firstIdemTask = request('POST', '/api/v1/tasks', $idemTaskPayload, array_merge($userHeaders, ['X-Idempotency-Key' => $idemKey]));
    assertTrue($firstIdemTask['status'] === 201, 'First idempotent task create must return 201');
    $secondIdemTask = request('POST', '/api/v1/tasks', $idemTaskPayload, array_merge($userHeaders, ['X-Idempotency-Key' => $idemKey]));
    assertTrue(in_array($secondIdemTask['status'], [200, 201], true), 'Second idempotent task create must return 200/201');
    assertTrue((bool)($secondIdemTask['payload']['meta']['idempotency_replayed'] ?? false) === true, 'Second idempotent task create must be marked as replayed');

    // AI runtime artifacts must not be written into api/storage.
    $forbiddenStoragePaths = collectForbiddenAiStoragePaths(dirname(__DIR__, 2) . '/storage');
    assertTrue(
        $forbiddenStoragePaths === [],
        'AI runtime artifacts/cache/debug payloads must not be written into api/storage: ' . implode(', ', $forbiddenStoragePaths)
    );

    request('DELETE', '/api/v1/users/' . $adminPublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/users/' . $userPublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/roles/' . $roleAdminPublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/roles/' . $roleUserPublicId, [], $rootHeaders);

    echo "AI foundation smoke: OK\n";
    echo "provider_public_id={$providerPublicId}\n";
} catch (Throwable $e) {
    fwrite(STDERR, "AI foundation smoke FAILED: " . $e->getMessage() . "\n");
    exit(1);
}
