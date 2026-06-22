<?php
declare(strict_types=1);

namespace Api\System\Library;

use Api\System\Library\Database\ConnectionManager;
use Api\System\Library\Database\Migration\MigrationManager;
use Api\System\Library\Database\SchemaManager;
use Api\System\Library\Hook\HookManager;
use Api\System\Library\Module\ModuleAutoloader;
use Api\System\Library\Module\ModuleCache;
use Api\System\Library\Module\ModuleConfig;
use Api\System\Library\Module\ModuleErrorHandler;
use Api\System\Library\Module\ModuleMigrationRunner;
use Api\System\Library\Module\PluginManager;
use Api\System\Library\Module\ServiceProviderRegistry;
use Api\System\Library\Module\EventDispatcher;
use Api\System\Library\Module\ModuleAuditLogger;
use Api\System\Library\Module\ModuleDeprecation;
use Api\System\Library\Module\ModuleCodeValidator;
use Api\System\Library\Module\ModuleProfiler;
use Api\System\Library\Module\ModuleResourceLimits;
use Api\System\Library\Module\ModuleTableValidator;
use Api\System\Library\Module\ModuleCronScheduler;
use Api\System\Library\Module\ModuleJobDispatcher;
use Api\System\Library\Module\ModuleWebhookDispatcher;
use Api\System\Library\Module\ModuleNotificationDispatcher;
use Api\System\Library\Module\ModuleFeatureFlags;
use Api\System\Library\Module\ModuleCircuitBreaker;
use Api\System\Library\Module\ModuleBulkhead;
use Api\System\Library\Module\ModuleRateLimiter;
use Api\System\Library\Module\ModuleApiVersionManager;
use Api\System\Library\Http\JsonResponse;
use Api\System\Library\Http\RawJsonResponse;
use Api\System\Library\Http\Request;
use Api\System\Library\Language\LanguageManager;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Router\Router;
use Api\System\Library\Security\PasswordHasher;
use Api\System\Library\Security\TokenManager;
use Api\System\Library\Service\AuthService;
use Api\System\Library\Service\CommentService;
use Api\System\Library\Service\CommentDraftService;
use Api\System\Library\Service\ChecklistService;
use Api\System\Library\Service\ChatService;
use Api\System\Library\Service\CustomHttpProviderClient;
use Api\System\Library\Service\BusinessCalendarService;
use Api\System\Library\Service\CalendarService;
use Api\System\Library\Service\CalendarAiContextBuilder;
use Api\System\Library\Service\ClientCabinetService;
use Api\System\Library\Service\ClientAiContextBuilder;
use Api\System\Library\Service\ActivityService;
use Api\System\Library\Service\AiActionService;
use Api\System\Library\Service\AiActionTypeService;
use Api\System\Library\Service\AiAvailabilityService;
use Api\System\Library\Service\AiContextBuilder;
use Api\System\Library\Service\AiCronSchedulerService;
use Api\System\Library\Service\AiJsonSchemaService;
use Api\System\Library\Service\AiMaskingService;
use Api\System\Library\Service\AiPreferenceService;
use Api\System\Library\Service\AiProviderClientFactory;
use Api\System\Library\Service\AiPromptTemplateService;
use Api\System\Library\Service\AiPromptBuilderService;
use Api\System\Library\Service\AiProviderService;
use Api\System\Library\Service\AiRateLimitService;
use Api\System\Library\Service\AiRetentionPolicyService;
use Api\System\Library\Service\AiSemanticIndexService;
use Api\System\Library\Service\AiSettingsService;
use Api\System\Library\Service\AiIntentSettingService;
use Api\System\Library\Service\AiPromptSchemaService;
use Api\System\Library\Service\AiSuggestionService;
use Api\System\Library\Service\AiTokenBudgetService;
use Api\System\Library\Service\AiUserPreferenceService;
use Api\System\Library\Service\AiUsageService;
use Api\System\Library\Service\AiJobService;
use Api\System\Library\Service\AiCostLimitService;
use Api\System\Library\Service\ApiClientService;
use Api\System\Library\Service\AdminWidgetService;
use Api\System\Library\Service\AdminRoleMatrixService;
use Api\System\Library\Service\AdminAiContextBuilder;
use Api\System\Library\Service\AnalyticsService;
use Api\System\Library\Service\ApprovalService;
use Api\System\Library\Service\DashboardService;
use Api\System\Library\Service\ExportService;
use Api\System\Library\Service\FeatureFlagService;
use Api\System\Library\Service\FavoriteService;
use Api\System\Library\Service\FileService;
use Api\System\Library\Service\GanttService;
use Api\System\Library\Service\ImportService;
use Api\System\Library\Service\ImportAiContextBuilder;
use Api\System\Library\Service\ImpersonationService;
use Api\System\Library\Service\InvitationService;
use Api\System\Library\Service\InstallService;
use Api\System\Library\Service\IntakeItemService;
use Api\System\Library\Service\LogsService;
use Api\System\Library\Service\MigrationService;
use Api\System\Library\Service\MilestoneService;
use Api\System\Library\Service\MockAiProviderClient;
use Api\System\Library\Service\NotificationService;
use Api\System\Library\Service\OrganizationService;
use Api\System\Library\Service\OpenAiCompatibleProviderClient;
use Api\System\Library\Service\PasswordResetService;
use Api\System\Library\Service\PermissionService;
use Api\System\Library\Service\PriorityService;
use Api\System\Library\Service\ProjectAiContextBuilder;
use Api\System\Library\Service\ProjectService;
use Api\System\Library\Service\ProjectSummaryService;
use Api\System\Library\Service\RecycleBinService;
use Api\System\Library\Service\DependencyService;
use Api\System\Library\Service\ReminderService;
use Api\System\Library\Service\WebhookService;
use Api\System\Library\Service\EntityAccessService;
use Api\System\Library\Service\MentionService;
use Api\System\Library\Service\OpsService;
use Api\System\Library\Service\ReactionService;
use Api\System\Library\Service\RetentionService;
use Api\System\Library\Service\SearchService;
use Api\System\Library\Service\SecurityAiContextBuilder;
use Api\System\Library\Service\RoleService;
use Api\System\Library\Service\SlaService;
use Api\System\Library\Service\SettingService;
use Api\System\Library\Service\SavedViewService;
use Api\System\Library\Service\SubscriptionService;
use Api\System\Library\Service\StatusService;
use Api\System\Library\Service\SubtaskService;
use Api\System\Library\Service\TagService;
use Api\System\Library\Service\TaskBulkService;
use Api\System\Library\Service\TaskBoardService;
use Api\System\Library\Service\TaskAiContextBuilder;
use Api\System\Library\Service\TaskActivityService;
use Api\System\Library\Service\WorkCycleService;
use Api\System\Library\Service\ProjectModuleService;
use Api\System\Library\Service\KnowledgePageVersionService;
use Api\System\Library\Service\StickyNoteService;
use Api\System\Library\Service\TaskEstimateService;
use Api\System\Library\Service\KnowledgePageService;
use Api\System\Library\Service\TaskService;
use Api\System\Library\Service\TaskKeyService;
use Api\System\Library\Service\TwoFactorService;
use Api\System\Library\Service\UserProfileService;
use Api\System\Library\Service\AuthzService;
use Api\System\Library\Service\UserService;
use Api\System\Library\Service\WorklogService;
use Api\System\Library\Service\WorkflowService;
use Api\System\Library\Service\DashboardAiContextBuilder;
use Api\System\Library\Cache\ApiFileCache;
use RuntimeException;
use Throwable;

final class App
{
    private Config $config;
    private Container $container;

    public function __construct(private readonly string $basePath)
    {
        $this->config = new Config();
        $this->container = new Container();
    }

    public function run(): JsonResponse|RawJsonResponse
    {
        $this->bootstrapConfig();
        $this->bootstrapRuntime();

        /** @var Request $request */
        $request = $this->container->get('request');
        /** @var JsonLogger $logger */
        $logger = $this->container->get('logger');

        $start = microtime(true);
        $routePath = $request->path;
        $statusCode = 200;
        $resultCode = 'OK';

        try {
            if ($request->method === 'OPTIONS') {
                /** @var LanguageManager $lang */
                $lang = $this->container->get('lang');
                return JsonResponse::success('CORS_PREFLIGHT', $lang->get('common/messages.ok', 'OK'), requestId: $request->requestId, correlationId: $request->correlationId);
            }

            /** @var Router $router */
            $router = $this->container->get('router');
            $matched = $router->match($request);

            if (!$matched) {
                /** @var LanguageManager $lang */
                $lang = $this->container->get('lang');
                $response = JsonResponse::error(
                    code: 'ROUTE_NOT_FOUND',
                    message: $lang->get('common/messages.route_not_found', 'Route not found'),
                    status: 404,
                    requestId: $request->requestId,
                    correlationId: $request->correlationId
                );
                $statusCode = 404;
                $resultCode = 'ROUTE_NOT_FOUND';
                return $response;
            }

            $routePath = (string)$matched['route_path'];

            /** @var InstallService $install */
            $install = $this->container->get('service.install');
            $installRoute = str_starts_with($routePath, '/install/');
            if ($installRoute && $install->isInstalled()) {
                /** @var LanguageManager $lang */
                $lang = $this->container->get('lang');
                $response = JsonResponse::error(
                    code: 'INSTALL_DISABLED',
                    message: $lang->get('install/messages.already_installed', 'Install endpoints are disabled after setup'),
                    status: 404,
                    requestId: $request->requestId,
                    correlationId: $request->correlationId
                );
                $statusCode = 404;
                $resultCode = 'INSTALL_DISABLED';
                return $response;
            }
            if (!$installRoute && !$install->isInstalled()) {
                /** @var LanguageManager $lang */
                $lang = $this->container->get('lang');
                $response = JsonResponse::error(
                    code: 'INSTALL_REQUIRED',
                    message: $lang->get('install/messages.install_required_setup_hint', 'System is not installed. Run /install/setup'),
                    status: 423,
                    errors: ['install' => [$lang->get('install/messages.install_required', 'System is not installed')]],
                    requestId: $request->requestId,
                    correlationId: $request->correlationId
                );
                $statusCode = 423;
                $resultCode = 'INSTALL_REQUIRED';
                return $response;
            }

            if ($this->isAiRoute($routePath) && $this->aiRequestPayloadTooLarge($request)) {
                /** @var LanguageManager $lang */
                $lang = $this->container->get('lang');
                $response = JsonResponse::error(
                    code: 'AI_REQUEST_PAYLOAD_TOO_LARGE',
                    message: $lang->get('common/messages.validation_error', 'Validation error'),
                    status: 413,
                    errors: ['payload' => ['AI request payload exceeds configured max_input_chars']],
                    requestId: $request->requestId,
                    correlationId: $request->correlationId
                );
                $statusCode = 413;
                $resultCode = 'AI_REQUEST_PAYLOAD_TOO_LARGE';
                return $response;
            }

            if (($matched['auth'] ?? true) === true) {
                $auth = $this->authenticate();
                if (!$auth) {
                    /** @var LanguageManager $lang */
                    $lang = $this->container->get('lang');
                    if ($routePath === '/api/v1/mcp') {
                        $response = new RawJsonResponse([
                            'jsonrpc' => '2.0',
                            'id' => null,
                            'error' => [
                                'code' => -32001,
                                'message' => $lang->get('common/messages.unauthorized', 'Unauthorized'),
                                'data' => [
                                    'auth' => $lang->get('auth/messages.bearer_required', 'Provide Bearer token'),
                                ],
                            ],
                        ], 401, [
                            'MCP-Protocol-Version' => '2025-06-18',
                        ]);
                        $statusCode = 401;
                        $resultCode = 'MCP_UNAUTHORIZED';
                        return $response;
                    }
                    $response = JsonResponse::error(
                        code: 'UNAUTHORIZED',
                        message: $lang->get('common/messages.unauthorized', 'Unauthorized'),
                        status: 401,
                        errors: ['auth' => [$lang->get('auth/messages.bearer_required', 'Provide Bearer token')]],
                        requestId: $request->requestId,
                        correlationId: $request->correlationId
                    );
                    $statusCode = 401;
                    $resultCode = 'UNAUTHORIZED';
                    return $response;
                }

                $this->container->set('auth_user', $auth);
                if (!$this->passesCookieCsrfPolicy($request, $auth)) {
                    /** @var LanguageManager $lang */
                    $lang = $this->container->get('lang');
                    $response = JsonResponse::error(
                        code: 'CSRF_TOKEN_INVALID',
                        message: $lang->get('common/messages.forbidden', 'Forbidden'),
                        status: 403,
                        errors: ['csrf' => [$lang->get('common/messages.permission_denied_action', 'Invalid CSRF token')]],
                        requestId: $request->requestId,
                        correlationId: $request->correlationId
                    );
                    $statusCode = 403;
                    $resultCode = 'CSRF_TOKEN_INVALID';
                    return $response;
                }

                /** @var LanguageManager $lang */
                $lang = $this->container->get('lang');
                $authLocale = (string)($auth['user']['locale'] ?? '');
                if ($authLocale !== '') {
                    $lang->setLocale($authLocale);
                    header('X-Response-Locale: ' . $authLocale);
                }
            }

            if ($this->shouldApplyGlobalRouteRateLimit($request, $routePath)) {
                    /** @var RateLimiterInterface $routeRateLimiter */
                $routeRateLimiter = $this->container->get('security.route_rate_limiter');
                $auth = $this->container->has('auth_user') ? $this->container->get('auth_user') : null;
                $rateKey = $this->globalRouteRateLimitKey($request, $routePath, is_array($auth) ? $auth : null);
                $rateState = $routeRateLimiter->check($rateKey);
                if (($rateState['blocked'] ?? false) === true) {
                    $retryAfter = max(1, (int)($rateState['retry_after'] ?? 1));
                    header('Retry-After: ' . (string)$retryAfter);
                    $response = JsonResponse::error(
                        code: 'RATE_LIMITED',
                        message: 'Too many requests',
                        status: 429,
                        errors: ['rate_limit' => ['Too many requests']],
                        meta: ['retry_after' => $retryAfter],
                        requestId: $request->requestId,
                        correlationId: $request->correlationId
                    );
                    $statusCode = 429;
                    $resultCode = 'RATE_LIMITED';
                    return $response;
                }
                $routeRateLimiter->hit($rateKey);
            }

            $requiredPermissions = $matched['required_permissions'] ?? [];
            if (is_string($requiredPermissions) && $requiredPermissions !== '') {
                $requiredPermissions = [$requiredPermissions];
            }
            if (!is_array($requiredPermissions)) {
                $requiredPermissions = [];
            }

            if ($requiredPermissions !== []) {
                $auth = $this->container->has('auth_user') ? $this->container->get('auth_user') : null;
                if (!$auth) {
                    /** @var LanguageManager $lang */
                    $lang = $this->container->get('lang');
                    $response = JsonResponse::error(
                        code: 'UNAUTHORIZED',
                        message: $lang->get('common/messages.unauthorized', 'Unauthorized'),
                        status: 401,
                        errors: ['auth' => [$lang->get('auth/messages.bearer_required', 'Provide Bearer token')],
                        ],
                        requestId: $request->requestId,
                        correlationId: $request->correlationId
                    );
                    $statusCode = 401;
                    $resultCode = 'UNAUTHORIZED';
                    return $response;
                }

                /** @var AuthzService $authz */
                $authz = $this->container->get('service.authz');
                if (!$authz->hasPermissions($auth['user'], $requiredPermissions)) {
                    /** @var LanguageManager $lang */
                    $lang = $this->container->get('lang');
                    $response = JsonResponse::error(
                        code: 'FORBIDDEN',
                        message: $lang->get('common/messages.forbidden', 'Forbidden'),
                        status: 403,
                        errors: ['permission' => [$lang->get('common/messages.permission_denied_action', 'Insufficient permissions for this action')],
                        ],
                        requestId: $request->requestId,
                        correlationId: $request->correlationId
                    );
                    $statusCode = 403;
                    $resultCode = 'FORBIDDEN';
                    return $response;
                }
            }

            $controllerClass = (string)$matched['controller'];
            $action = (string)$matched['action'];
            $params = (array)($matched['params'] ?? []);

            $controller = new $controllerClass($this->container);
            if (!method_exists($controller, $action)) {
                /** @var LanguageManager $lang */
                $lang = $this->container->get('lang');
                $response = JsonResponse::error(
                    code: 'ACTION_NOT_FOUND',
                    message: $lang->get('common/messages.action_not_found', 'Controller action not found'),
                    status: 500,
                    requestId: $request->requestId,
                    correlationId: $request->correlationId
                );
                $statusCode = 500;
                $resultCode = 'ACTION_NOT_FOUND';
                return $response;
            }

            $result = $params !== [] ? $controller->{$action}($params) : $controller->{$action}();

            if (($matched['binary'] ?? false) === true) {
                $this->emitBinary($result, $request);
                exit;
            }

            if (($matched['sse'] ?? false) === true) {
                $this->emitSse($result, $request);
                exit;
            }

            if ($result instanceof RawJsonResponse) {
                $statusCode = $result->status();
                $rawPayload = $result->payload();
                $resultCode = is_array($rawPayload)
                    ? (string)($rawPayload['error']['code'] ?? $rawPayload['result']['code'] ?? 'MCP_JSON_RPC')
                    : 'MCP_JSON_RPC';

                return $result;
            }

            if (!$result instanceof JsonResponse) {
                $result = JsonResponse::success(
                    code: 'OK',
                    message: $this->container->get('lang')->get('common/messages.ok', 'OK'),
                    data: is_array($result) ? $result : ['result' => $result],
                    requestId: $request->requestId,
                    correlationId: $request->correlationId
                );
            }

            $statusCode = $result->status();
            $payload = $result->payload();
            $resultCode = (string)($payload['code'] ?? 'OK');

            return $result;
        } catch (Throwable $e) {
            $logger->error([
                'request_id' => $request->requestId,
                'route' => $routePath,
                'method' => $request->method,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $statusCode = 500;
            $resultCode = 'INTERNAL_ERROR';

            $exceptionDetail = $this->config->get('default.app.debug', false) ? $e->getMessage() : null;
            $errors = $exceptionDetail !== null ? ['exception' => [$exceptionDetail]] : [];
            return JsonResponse::error(
                code: 'INTERNAL_ERROR',
                message: $this->container->get('lang')->get('common/messages.internal_error', 'Internal server error'),
                status: 500,
                errors: $errors,
                requestId: $request->requestId,
                correlationId: $request->correlationId
            );
        } finally {
            $duration = (int)round((microtime(true) - $start) * 1000);
            $auth = $this->container->has('auth_user') ? $this->container->get('auth_user') : null;
            $logger->request([
                'request_id' => $request->requestId,
                'correlation_id' => $request->correlationId,
                'timestamp' => gmdate('c'),
                'user_id' => $auth['user']['id'] ?? null,
                'user_public_id' => $auth['user']['public_id'] ?? null,
                'login' => $auth['user']['login'] ?? null,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'route' => $routePath,
                'method' => $request->method,
                'payload' => $this->buildSafeRequestPayload($request),
                'response_status' => $statusCode,
                'result_code' => $resultCode,
                'execution_time_ms' => $duration,
                'success' => $statusCode < 400,
            ]);
        }
    }

    private function bootstrapConfig(): void
    {
        $files = [
            'default',
            'database',
            'security',
            'install',
            'api',
            'ai',
            'feature_flags',
            'logging',
            'update',
        ];

        foreach ($files as $name) {
            $this->config->load($this->basePath . '/config/' . $name . '.php', $name);
        }

        date_default_timezone_set((string)$this->config->get('default.app.timezone', 'UTC'));
        if (!defined('APP_ENV')) {
            define('APP_ENV', (string)$this->config->get('default.app.env', 'prod'));
        }

        $this->loadLocalConfig($this->basePath . '/config/database.local.php', 'database');
        $this->loadLocalConfig($this->basePath . '/config/logging.local.php', 'logging');

        $this->validateSecurityConfig();
        $this->validateProductionRuntimeConfig();
    }

    private function loadLocalConfig(string $file, string $namespace): void
    {
        if (!is_file($file)) {
            return;
        }

        $config = require $file;
        if (!is_array($config)) {
            return;
        }

        if ($this->isProductionEnvironment() && $this->configContainsTestRuntimePath($config)) {
            return;
        }

        $this->config->merge($namespace, $config);
    }

    private function validateSecurityConfig(): void
    {
        if (!$this->isProductionEnvironment()) {
            return;
        }

        $corsOrigin = trim((string)$this->config->get('security.cors.allow_origin', ''));
        if ($corsOrigin === '*') {
            throw new RuntimeException('CONFIG_SECURITY_CORS_WILDCARD_PRODUCTION');
        }

        $csrfSecret = trim((string)$this->config->get('security.auth.csrf.secret_key', ''));
        if ($csrfSecret === '') {
            throw new RuntimeException('CONFIG_SECURITY_CSRF_SECRET_REQUIRED');
        }

        $webhookSecret = trim((string)$this->config->get('security.webhook.secret_key', ''));
        if ($webhookSecret === '') {
            throw new RuntimeException('CONFIG_SECURITY_WEBHOOK_SECRET_REQUIRED_PRODUCTION');
        }

        $appKeyPresent = (bool)$this->config->get('security.bootstrap.required_env.app_key_present', true);
        if (!$appKeyPresent) {
            throw new RuntimeException('CONFIG_SECURITY_APP_KEY_REQUIRED');
        }

        $csrfEnvPresent = (bool)$this->config->get('security.bootstrap.required_env.csrf_secret_present', true);
        if (!$csrfEnvPresent) {
            throw new RuntimeException('CONFIG_SECURITY_CSRF_SECRET_ENV_REQUIRED');
        }

        $webhookEnvPresent = (bool)$this->config->get('security.bootstrap.required_env.webhook_secret_present', true);
        if (!$webhookEnvPresent) {
            throw new RuntimeException('CONFIG_SECURITY_WEBHOOK_SECRET_ENV_REQUIRED');
        }

        $aiEnvPresent = (bool)$this->config->get('security.bootstrap.required_env.ai_encryption_key_present', true);
        if (!$aiEnvPresent) {
            throw new RuntimeException('CONFIG_SECURITY_AI_ENCRYPTION_KEY_REQUIRED');
        }
    }

    private function isProductionEnvironment(): bool
    {
        $env = strtolower(trim((string)$this->config->get('default.app.env', 'prod')));
        return in_array($env, ['prod', 'production'], true);
    }

    private function validateProductionRuntimeConfig(): void
    {
        if (!$this->isProductionEnvironment()) {
            return;
        }

        $runtimeConfig = [
            'database' => $this->config->get('database', []),
            'logging' => $this->config->get('logging', []),
        ];

        if ($this->configContainsTestRuntimePath($runtimeConfig)) {
            throw new RuntimeException('CONFIG_LOCAL_TEST_RUNTIME_PRODUCTION');
        }
    }

    /**
     * @param mixed $value
     */
    private function configContainsTestRuntimePath(mixed $value): bool
    {
        foreach ($this->flattenConfigValues($value) as $item) {
            $normalized = str_replace('\\', '/', (string)$item);
            if (str_contains($normalized, '/storage_test_runtime/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $value
     * @return array<int,scalar>
     */
    private function flattenConfigValues(mixed $value): array
    {
        if (!is_array($value)) {
            return is_scalar($value) ? [$value] : [];
        }

        $result = [];
        foreach ($value as $item) {
            foreach ($this->flattenConfigValues($item) as $flattened) {
                $result[] = $flattened;
            }
        }

        return $result;
    }

    private function bootstrapRuntime(): void
    {
        $request = Request::capture((string)$this->config->get('default.locale.default', 'en-gb'));

        $db = new ConnectionManager($this->config);
        $schema = new SchemaManager();
        $migrations = new MigrationManager($schema);

        $logChannels = (array)$this->config->get('logging.channels', []);
        $maskKeys = (array)$this->config->get('logging.mask_keys', []);
        $logWriter = function (string $channel, array $context) use ($db): void {
            $pdo = $db->connect();
            $repo = new \Api\Model\Logs\LogsRepository($pdo);

            if ($channel === 'request') {
                $repo->insertRequest($context);
                return;
            }

            if ($channel === 'audit') {
                $repo->insertAudit($context);
                return;
            }

            if ($channel === 'security') {
                $repo->insertSecurity($context);
            }
        };
        $logger = new JsonLogger($logChannels, $maskKeys, $logWriter);

        $corsMethods = (string)$this->config->get('security.cors.allow_methods', 'GET, POST, PATCH, PUT, DELETE, OPTIONS');
        $corsHeaders = (string)$this->config->get('security.cors.allow_headers', 'Content-Type, Authorization');
        $resolvedCorsOrigin = $this->resolveCorsOrigin($request->header('Origin'));
        if ($resolvedCorsOrigin !== '') {
            header('Access-Control-Allow-Origin: ' . $resolvedCorsOrigin);
            header('Vary: Origin');
        }
        header('Access-Control-Allow-Methods: ' . $corsMethods);
        header('Access-Control-Allow-Headers: ' . $corsHeaders);
        header('X-Request-Id: ' . $request->requestId);
        header('X-Correlation-Id: ' . $request->correlationId);
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Powered-By: TropaTT');

        $lang = new LanguageManager($this->basePath . '/language', (string)$this->config->get('default.locale.fallback', 'en-gb'));
        $lang->setLocale($request->locale);

        $this->container->set('config', $this->config);
        $this->container->set('request', $request);
        $this->container->set('logger', $logger);
        $this->container->set('lang', $lang);
        $this->container->set('db.connection_manager', $db);
        $this->container->set('db.schema', $schema);
        $this->container->set('db.migrations', $migrations);
        $this->container->set('hook.manager', new HookManager());

        $pluginManager = new PluginManager(dirname($this->basePath));
        $moduleAutoloader = new ModuleAutoloader(dirname($this->basePath));
        $this->container->set('plugin.manager', $pluginManager);
        $this->container->set('module.autoloader', $moduleAutoloader);
        $moduleAutoloader->register();

        $this->container->factory('db.pdo', fn() => $db->connect());
        $this->container->factory('repository.user', fn(Container $c) => new \Api\Model\Common\UserRepository($c->get('db.pdo')));
        $this->container->factory('repository.idempotency', fn(Container $c) => new \Api\Model\Common\IdempotencyRepository($c->get('db.pdo')));
        $this->container->factory('repository.auth', fn(Container $c) => new \Api\Model\Auth\AuthRepository($c->get('db.pdo')));
        $this->container->factory('repository.project', fn(Container $c) => new \Api\Model\Project\ProjectRepository($c->get('db.pdo')));
        $this->container->factory('repository.task', fn(Container $c) => new \Api\Model\Task\TaskRepository($c->get('db.pdo')));
        $this->container->factory('repository.task_key_counter', fn(Container $c) => new \Api\Model\Task\TaskKeyCounterRepository($c->get('db.pdo')));
        $this->container->factory('repository.task_relation', fn(Container $c) => new \Api\Model\Task\TaskRelationRepository($c->get('db.pdo')));
        $this->container->factory('repository.comment', fn(Container $c) => new \Api\Model\Comment\CommentRepository($c->get('db.pdo')));
        $this->container->factory('repository.comment_draft', fn(Container $c) => new \Api\Model\Comment\CommentDraftRepository($c->get('db.pdo')));
        $this->container->factory('repository.mention', fn(Container $c) => new \Api\Model\Comment\MentionRepository($c->get('db.pdo')));
        $this->container->factory('repository.subtask', fn(Container $c) => new \Api\Model\Subtask\SubtaskRepository($c->get('db.pdo')));
        $this->container->factory('repository.checklist', fn(Container $c) => new \Api\Model\Checklist\ChecklistRepository($c->get('db.pdo')));
        $this->container->factory('repository.reaction', fn(Container $c) => new \Api\Model\Reaction\ReactionRepository($c->get('db.pdo')));
        $this->container->factory('repository.subscription', fn(Container $c) => new \Api\Model\Subscription\SubscriptionRepository($c->get('db.pdo')));
        $this->container->factory('repository.favorite', fn(Container $c) => new \Api\Model\Favorite\FavoriteRepository($c->get('db.pdo')));
        $this->container->factory('repository.saved_view', fn(Container $c) => new \Api\Model\View\SavedViewRepository($c->get('db.pdo')));
        $this->container->factory('repository.task_activity', fn(Container $c) => new \Api\Model\Task\TaskActivityRepository($c->get('db.pdo')));
        $this->container->factory('repository.work_cycle', fn(Container $c) => new \Api\Model\Cycle\WorkCycleRepository($c->get('db.pdo')));
        $this->container->factory('repository.cycle_task', fn(Container $c) => new \Api\Model\Cycle\CycleTaskRepository($c->get('db.pdo')));
        $this->container->factory('repository.cycle_snapshot', fn(Container $c) => new \Api\Model\Cycle\CycleSnapshotRepository($c->get('db.pdo')));
        $this->container->factory('repository.status', fn(Container $c) => new \Api\Model\Status\StatusRepository($c->get('db.pdo')));
        $this->container->factory('repository.priority', fn(Container $c) => new \Api\Model\Priority\PriorityRepository($c->get('db.pdo')));
        $this->container->factory('repository.tag', fn(Container $c) => new \Api\Model\Tag\TagRepository($c->get('db.pdo')));
        $this->container->factory('repository.file', fn(Container $c) => new \Api\Model\File\FileRepository($c->get('db.pdo')));
        $this->container->factory('repository.knowledge', fn(Container $c) => new \Api\Model\Knowledge\KnowledgeRepository($c->get('db.pdo')));
        $this->container->factory('repository.user_management', fn(Container $c) => new \Api\Model\User\UserManagementRepository($c->get('db.pdo')));
        $this->container->factory('repository.role', fn(Container $c) => new \Api\Model\Role\RoleRepository($c->get('db.pdo')));
        $this->container->factory('repository.permission', fn(Container $c) => new \Api\Model\Permission\PermissionRepository($c->get('db.pdo')));
        $this->container->factory('repository.role_permission', fn(Container $c) => new \Api\Model\Permission\RolePermissionRepository($c->get('db.pdo')));
        $this->container->factory('repository.logs', fn(Container $c) => new \Api\Model\Logs\LogsRepository($c->get('db.pdo')));
        $this->container->factory('repository.team', fn(Container $c) => new \Api\Model\Team\TeamRepository($c->get('db.pdo')));
        $this->container->factory('repository.department', fn(Container $c) => new \Api\Model\Department\DepartmentRepository($c->get('db.pdo')));
        $this->container->factory('repository.company', fn(Container $c) => new \Api\Model\Company\CompanyRepository($c->get('db.pdo')));
        $this->container->factory('repository.client', fn(Container $c) => new \Api\Model\Client\ClientRepository($c->get('db.pdo')));
        $this->container->factory('repository.counterparty', fn(Container $c) => new \Api\Model\Counterparty\CounterpartyRepository($c->get('db.pdo')));
        $this->container->factory('repository.client_cabinet', fn(Container $c) => new \Api\Model\Client\ClientCabinetRepository($c->get('db.pdo')));
        $this->container->factory('repository.contact', fn(Container $c) => new \Api\Model\Contact\ContactRepository($c->get('db.pdo')));
        $this->container->factory('repository.setting', fn(Container $c) => new \Api\Model\Setting\SettingRepository($c->get('db.pdo')));
        $this->container->factory('repository.session', fn(Container $c) => new \Api\Model\Security\SessionRepository($c->get('db.pdo')));
        $this->container->factory('repository.notification', fn(Container $c) => new \Api\Model\Notification\NotificationRepository($c->get('db.pdo')));
        $this->container->factory('repository.notification_push_subscription', fn(Container $c) => new \Api\Model\Notification\PushSubscriptionRepository($c->get('db.pdo')));
        $this->container->factory('repository.notification_push_queue', fn(Container $c) => new \Api\Model\Notification\PushDispatchQueueRepository($c->get('db.pdo')));
        $this->container->factory('repository.reminder', fn(Container $c) => new \Api\Model\Reminder\ReminderRepository($c->get('db.pdo')));
        $this->container->factory('repository.calendar_event', fn(Container $c) => new \Api\Model\Calendar\CalendarEventRepository($c->get('db.pdo')));
        $this->container->factory('repository.business_calendar', fn(Container $c) => new \Api\Model\Calendar\BusinessCalendarRepository($c->get('db.pdo')));
        $this->container->factory('repository.analytics', fn(Container $c) => new \Api\Model\Analytics\AnalyticsRepository($c->get('db.pdo')));
        $this->container->factory('repository.activity', fn(Container $c) => new \Api\Model\Activity\ActivityRepository($c->get('db.pdo')));
        $this->container->factory('repository.admin_widget', fn(Container $c) => new \Api\Model\Admin\OperationalWidgetRepository($c->get('db.pdo')));
        $this->container->factory('repository.api_client', fn(Container $c) => new \Api\Model\ApiClient\ApiClientRepository($c->get('db.pdo')));
        $this->container->factory('repository.webhook', fn(Container $c) => new \Api\Model\Webhook\WebhookRepository($c->get('db.pdo')));
        $this->container->factory('repository.invitation', fn(Container $c) => new \Api\Model\Security\InvitationRepository($c->get('db.pdo')));
        $this->container->factory('repository.password_reset', fn(Container $c) => new \Api\Model\Security\PasswordResetRepository($c->get('db.pdo')));
        $this->container->factory('repository.two_factor', fn(Container $c) => new \Api\Model\Security\TwoFactorRepository($c->get('db.pdo')));
        $this->container->factory('repository.impersonation', fn(Container $c) => new \Api\Model\Security\ImpersonationRepository($c->get('db.pdo')));
        $this->container->factory('repository.search', fn(Container $c) => new \Api\Model\Search\SearchRepository($c->get('db.pdo')));
        $this->container->factory('repository.worklog', fn(Container $c) => new \Api\Model\Worklog\WorklogRepository($c->get('db.pdo')));
        $this->container->factory('repository.dashboard', fn(Container $c) => new \Api\Model\Dashboard\DashboardRepository($c->get('db.pdo')));
        $this->container->factory('repository.project_summary', fn(Container $c) => new \Api\Model\Project\ProjectSummaryRepository($c->get('db.pdo')));
        $this->container->factory('repository.milestone', fn(Container $c) => new \Api\Model\Milestone\MilestoneRepository($c->get('db.pdo')));
        $this->container->factory('repository.dependency', fn(Container $c) => new \Api\Model\Dependency\DependencyRepository($c->get('db.pdo')));
        $this->container->factory('repository.template', fn(Container $c) => new \Api\Model\Template\TemplateRepository($c->get('db.pdo')));
        $this->container->factory('repository.recurring', fn(Container $c) => new \Api\Model\Recurring\RecurringRepository($c->get('db.pdo'), $c->get('lang')));
        $this->container->factory('repository.custom_field', fn(Container $c) => new \Api\Model\Custom_field\CustomFieldRepository($c->get('db.pdo')));
        $this->container->factory('repository.workflow', fn(Container $c) => new \Api\Model\Workflow\WorkflowRepository($c->get('db.pdo')));
        $this->container->factory('repository.intake_item', fn(Container $c) => new \Api\Model\Intake\IntakeItemRepository($c->get('db.pdo')));
        $this->container->factory('repository.intake_item_activity', fn(Container $c) => new \Api\Model\Intake\IntakeItemActivityRepository($c->get('db.pdo')));
        $this->container->factory('repository.sla', fn(Container $c) => new \Api\Model\Sla\SlaRepository($c->get('db.pdo')));
        $this->container->factory('repository.approval', fn(Container $c) => new \Api\Model\Approval\ApprovalRepository($c->get('db.pdo')));
        $this->container->factory('repository.recycle_bin', fn(Container $c) => new \Api\Model\Recycle_bin\RecycleBinRepository($c->get('db.pdo')));
        $this->container->factory('repository.import', fn(Container $c) => new \Api\Model\Import\ImportJobRepository($c->get('db.pdo')));
        $this->container->factory('repository.export', fn(Container $c) => new \Api\Model\Export\ExportJobRepository($c->get('db.pdo')));
        $this->container->factory('repository.feature_flag', fn(Container $c) => new \Api\Model\Feature_flag\FeatureFlagRepository($c->get('db.pdo')));
        $this->container->factory('repository.organization', fn(Container $c) => new \Api\Model\Organization\OrganizationRepository($c->get('db.pdo')));
        $this->container->factory('repository.ai_provider', fn(Container $c) => new \Api\Model\Ai\AiProviderRepository($c->get('db.pdo')));
        $this->container->factory('repository.ai_runtime', fn(Container $c) => new \Api\Model\Ai\AiRuntimeRepository($c->get('db.pdo')));
        $this->container->factory('repository.ai_intent_settings', fn(Container $c) => new \Api\Model\Ai\AiIntentSettingRepository($c->get('db.pdo')));
        $this->container->factory('repository.ai_prompt_templates', fn(Container $c) => new \Api\Model\Ai\AiPromptTemplateRepository($c->get('db.pdo')));
        $this->container->factory('repository.ai_json_schemas', fn(Container $c) => new \Api\Model\Ai\AiJsonSchemaRepository($c->get('db.pdo')));

        $this->container->factory('security.hasher', fn() => new PasswordHasher((string)$this->config->get('security.auth.password_algo', PASSWORD_ARGON2ID)));
        $this->container->factory('security.token', fn() => new TokenManager());
        $this->container->factory('security.login_rate_limiter', function (Container $c): DatabaseRateLimiter {
            $rateLimitConfig = (array)$this->config->get('security.rate_limit.auth_login', []);
            $authConfig = (array)$this->config->get('security.auth', []);

            $configuredMax = (int)($rateLimitConfig['max'] ?? 15);
            $lockThreshold = (int)($authConfig['lock_threshold'] ?? 5);
            $maxAttempts = max(1, min($configuredMax > 0 ? $configuredMax : 15, $lockThreshold > 0 ? $lockThreshold : 5));

            return new DatabaseRateLimiter(
                $c->get('db.pdo'),
                max(1, (int)($rateLimitConfig['window_sec'] ?? 60)),
                $maxAttempts,
                max(1, (int)($authConfig['lock_seconds'] ?? 300))
            );
        });
        $this->container->factory('security.password_reset_rate_limiter', function (Container $c): DatabaseRateLimiter {
            $cfg = (array)$this->config->get('security.rate_limit.password_reset', []);

            return new DatabaseRateLimiter(
                $c->get('db.pdo'),
                max(1, (int)($cfg['window_sec'] ?? 300)),
                max(1, (int)($cfg['max'] ?? 5)),
                max(1, (int)($cfg['lock_seconds'] ?? 900))
            );
        });
        $this->container->factory('security.route_rate_limiter', function (Container $c): DatabaseRateLimiter {
            $cfg = (array)$this->config->get('security.rate_limit.route_global', []);

            return new DatabaseRateLimiter(
                $c->get('db.pdo'),
                max(1, (int)($cfg['window_sec'] ?? 60)),
                max(1, (int)($cfg['max'] ?? 120)),
                max(1, (int)($cfg['lock_seconds'] ?? 60))
            );
        });
        $this->container->factory('policy.hierarchy', fn(Container $c) => new \Api\System\Library\Policy\HierarchyPolicy($c->get('repository.user_management')));

        $this->container->factory('service.install', fn(Container $c) => new InstallService(
            $this->config,
            $c->get('db.connection_manager'),
            $c->get('db.schema'),
            $c->get('db.migrations'),
            $c->get('security.hasher'),
            $c->get('logger')
        ));

        $this->container->factory('service.auth', fn(Container $c) => new AuthService(
            $c->get('repository.user'),
            $c->get('repository.auth'),
            $c->get('security.hasher'),
            $c->get('security.token'),
            $c->get('logger'),
            (int)$this->config->get('security.auth.access_token_ttl', 43200),
            $c->get('security.login_rate_limiter')
        ));

        $this->container->factory('service.project', fn(Container $c) => new ProjectService(
            $c->get('repository.project'),
            $c->get('repository.user'),
            $c->get('repository.team'),
            $c->get('service.notification'),
            $c->get('service.ai_semantic_index'),
            $c->get('service.chat'),
            $c->get('service.task_key'),
            $c->get('repository.task_key_counter')
        ));
        $this->container->factory('service.project_summary', fn(Container $c) => new ProjectSummaryService(
            $c->get('repository.project_summary'),
            $c->get('service.project')
        ));
        $this->container->factory('service.gantt', fn(Container $c) => new GanttService(
            $c->get('service.project'),
            $c->get('repository.task'),
            $c->get('repository.milestone'),
            $c->get('repository.dependency')
        ));
        $this->container->factory('service.task_key', fn(Container $c) => new TaskKeyService(
            $c->get('repository.task_key_counter'),
            $c->get('repository.project')
        ));
        $this->container->factory('service.task', fn(Container $c) => new TaskService(
            $c->get('repository.task'),
            $c->get('service.project'),
            $c->get('repository.team'),
            $c->get('service.notification'),
            $c->get('service.ai_semantic_index'),
            $c->get('service.task_activity'),
            $c->get('service.task_key'),
            $c->get('repository.task_key_counter'),
            $c->get('repository.project')
        ));
        $this->container->factory('service.task_bulk', fn(Container $c) => new TaskBulkService(
            $c->get('service.task'),
            $c->get('repository.tag'),
            $c->get('repository.user')
        ));
        $this->container->factory('service.task_board', fn(Container $c) => new TaskBoardService(
            $c->get('repository.task'),
            $c->get('repository.status'),
            $c->get('service.task'),
            $c->get('lang')
        ));
        $this->container->factory('service.comment', fn(Container $c) => new CommentService(
            $c->get('repository.comment'),
            $c->get('repository.task'),
            $c->get('service.notification'),
            $c->get('service.ai_semantic_index'),
            $c->get('service.task_activity')
        ));
        $this->container->factory('service.comment_draft', fn(Container $c) => new CommentDraftService(
            $c->get('repository.comment_draft'),
            $c->get('service.task')
        ));
        $this->container->factory('service.entity_access', fn(Container $c) => new EntityAccessService(
            $c->get('service.task'),
            $c->get('service.project'),
            $c->get('repository.comment')
        ));
        $this->container->factory('service.mention', fn(Container $c) => new MentionService(
            $c->get('repository.mention'),
            $c->get('repository.user'),
            $c->get('service.entity_access'),
            $c->get('service.notification')
        ));
        $this->container->factory('service.reaction', fn(Container $c) => new ReactionService(
            $c->get('repository.reaction'),
            $c->get('service.entity_access')
        ));
        $this->container->factory('service.subscription', fn(Container $c) => new SubscriptionService(
            $c->get('repository.subscription'),
            $c->get('service.entity_access')
        ));
        $this->container->factory('service.favorite', fn(Container $c) => new FavoriteService(
            $c->get('repository.favorite'),
            $c->get('service.entity_access')
        ));
        $this->container->factory('service.saved_view', fn(Container $c) => new SavedViewService(
            $c->get('repository.saved_view')
        ));
        $this->container->factory('service.subtask', fn(Container $c) => new SubtaskService(
            $c->get('repository.subtask'),
            $c->get('service.task')
        ));
        $this->container->factory('service.checklist', fn(Container $c) => new ChecklistService(
            $c->get('repository.checklist'),
            $c->get('service.task'),
            $c->get('service.task_activity')
        ));
        $this->container->factory('service.status', fn(Container $c) => new StatusService($c->get('repository.status')));
        $this->container->factory('service.priority', fn(Container $c) => new PriorityService($c->get('repository.priority')));
        $this->container->factory('service.milestone', fn(Container $c) => new MilestoneService(
            $c->get('repository.milestone'),
            $c->get('service.project')
        ));
        $this->container->factory('service.dependency', fn(Container $c) => new DependencyService(
            $c->get('repository.dependency'),
            $c->get('service.task'),
            $c->get('service.task_activity'),
            $c->get('service.workflow')
        ));
        $this->container->factory('service.task_relation', fn(Container $c) => new \Api\System\Library\Service\TaskRelationService(
            $c->get('repository.task_relation'),
            $c->get('service.task'),
            $c->get('service.task_activity')
        ));
        $this->container->factory('service.template', fn(Container $c) => new \Api\System\Library\Service\TemplateService(
            $c->get('repository.template'),
            $c->get('repository.user_management'),
            $c->get('policy.hierarchy')
        ));
        $this->container->factory('service.recurring', fn(Container $c) => new \Api\System\Library\Service\RecurringService(
            $c->get('repository.recurring'),
            $c->get('lang')
        ));
        $this->container->factory('service.custom_field', fn(Container $c) => new \Api\System\Library\Service\CustomFieldService(
            $c->get('repository.custom_field')
        ));
        $this->container->factory('service.workflow', fn(Container $c) => new WorkflowService(
            $c->get('repository.workflow'),
            $c->get('repository.user_management'),
            $c->get('policy.hierarchy'),
            $c->get('lang')
        ));
        $this->container->factory('service.sla', fn(Container $c) => new SlaService(
            $c->get('repository.sla')
        ));
        $this->container->factory('service.approval', fn(Container $c) => new ApprovalService(
            $c->get('repository.approval'),
            $c->get('repository.user'),
            $c->get('logger'),
            $c->get('service.notification')
        ));
        $this->container->factory('service.recycle_bin', fn(Container $c) => new RecycleBinService(
            $c->get('repository.recycle_bin'),
            $c->get('repository.file'),
            $c->get('logger')
        ));
        $this->container->factory('service.import', fn(Container $c) => new ImportService(
            $c->get('repository.import'),
            $c->get('service.project'),
            $c->get('service.task'),
            $c->get('logger'),
            $c->get('lang')
        ));
        $this->container->factory('service.export', fn(Container $c) => new ExportService(
            $c->get('repository.export'),
            $c->get('service.project'),
            $c->get('service.task'),
            $c->get('logger'),
            $this->basePath,
            (string)$c->get('config')->get('default.storage.base', $this->basePath . '/../storage_api')
        ));
        $this->container->factory('service.feature_flag', fn(Container $c) => new FeatureFlagService(
            $c->get('repository.feature_flag'),
            $c->get('logger'),
            (array)$this->config->get('feature_flags.feature_flags', [])
        ));
        $this->container->factory('service.organization', fn(Container $c) => new OrganizationService(
            $c->get('repository.organization'),
            $c->get('repository.user'),
            $c->get('logger'),
            $c->get('lang')
        ));
        $this->container->factory('service.tag', fn(Container $c) => new TagService(
            $c->get('repository.tag'),
            $c->get('service.task')
        ));
        $this->container->factory('service.file', fn(Container $c) => new FileService(
            $c->get('repository.file'),
            (string)$this->config->get('default.storage.uploads', $this->basePath . '/../storage_api/uploads'),
            (string)$this->config->get('default.storage.quarantine', $this->basePath . '/../storage_api/quarantine'),
            (int)$this->config->get('security.uploads.max_size_bytes', 20971520),
            (array)$this->config->get('security.uploads.quarantine_extensions', []),
            (array)$this->config->get('security.uploads.quarantine_mime_prefixes', []),
            $c->get('repository.task'),
            $c->get('repository.project'),
            $c->get('repository.knowledge'),
            $c->get('repository.recycle_bin'),
            $c->get('logger'),
            $c->get('service.ai_semantic_index'),
            $c->get('service.task_activity')
        ));
        $this->container->factory('service.user', fn(Container $c) => new UserService(
            $c->get('repository.user_management'),
            $c->get('repository.role'),
            $c->get('security.hasher'),
            $c->get('policy.hierarchy'),
            $c->get('logger'),
            $c->get('service.logs')
        ));
        $this->container->factory('service.user_profile', fn(Container $c) => new UserProfileService(
            $c->get('repository.user_management'),
            $c->get('repository.session'),
            $c->get('service.setting'),
            $c->get('security.hasher'),
            $c->get('logger')
        ));
        $this->container->factory('service.invitation', fn(Container $c) => new InvitationService(
            $c->get('repository.invitation'),
            $c->get('repository.user'),
            $c->get('repository.user_management'),
            $c->get('repository.role'),
            $c->get('security.hasher'),
            $c->get('security.token'),
            $c->get('logger')
        ));
        $this->container->factory('service.password_reset', fn(Container $c) => new PasswordResetService(
            $c->get('repository.password_reset'),
            $c->get('repository.user'),
            $c->get('repository.session'),
            $c->get('repository.user_management'),
            $c->get('security.hasher'),
            $c->get('security.token'),
            $c->get('logger'),
            $c->get('security.password_reset_rate_limiter')
        ));
        $this->container->factory('service.two_factor', fn(Container $c) => new TwoFactorService(
            $c->get('repository.two_factor'),
            $c->get('repository.user'),
            $c->get('security.hasher'),
            $c->get('security.token'),
            $c->get('logger')
        ));
        $this->container->factory('service.impersonation', fn(Container $c) => new ImpersonationService(
            $c->get('repository.impersonation'),
            $c->get('repository.user_management'),
            $c->get('repository.session'),
            $c->get('repository.auth'),
            $c->get('policy.hierarchy'),
            $c->get('security.token'),
            $c->get('logger'),
            (int)$this->config->get('security.auth.access_token_ttl', 43200)
        ));
        $this->container->factory('service.role', fn(Container $c) => new RoleService($c->get('repository.role'), $c->get('logger')));
        $this->container->factory('service.permission', fn(Container $c) => new PermissionService(
            $c->get('repository.permission'),
            $c->get('repository.role_permission'),
            $c->get('repository.role'),
            $c->get('logger'),
            $c->get('lang')
        ));
        $this->container->factory('service.admin_role_matrix', fn(Container $c) => new AdminRoleMatrixService(
            $c->get('service.permission')
        ));
        $this->container->factory('service.authz', fn(Container $c) => new AuthzService($c->get('repository.role_permission')));
        $this->container->factory('service.logs', fn(Container $c) => new LogsService($c->get('repository.logs')));
        $this->container->factory('service.migration', fn(Container $c) => new MigrationService(
            $c->get('db.connection_manager'),
            $c->get('db.migrations')
        ));
        $this->container->factory('service.team', fn(Container $c) => new \Api\System\Library\Service\TeamService(
            $c->get('repository.team'),
            $c->get('service.notification'),
            $c->get('service.chat')
        ));
        $this->container->factory('service.department', fn(Container $c) => new \Api\System\Library\Service\DepartmentService(
            $c->get('repository.team'),
            $c->get('service.notification')
        ));
        $this->container->factory('service.company', fn(Container $c) => new \Api\System\Library\Service\CompanyService(
            $c->get('repository.counterparty'),
            $c->get('repository.user_management'),
            $c->get('policy.hierarchy'),
            $c->get('service.ai_semantic_index')
        ));
        $this->container->factory('service.client', fn(Container $c) => new \Api\System\Library\Service\ClientService(
            $c->get('repository.counterparty'),
            $c->get('repository.user_management'),
            $c->get('policy.hierarchy'),
            $c->get('service.ai_semantic_index')
        ));
        $this->container->factory('service.counterparty', fn(Container $c) => new \Api\System\Library\Service\CounterpartyService(
            $c->get('repository.counterparty'),
            $c->get('repository.user_management'),
            $c->get('policy.hierarchy'),
            $c->get('service.ai_semantic_index')
        ));
        $this->container->factory('service.client_cabinet', fn(Container $c) => new ClientCabinetService(
            $c->get('repository.client_cabinet')
        ));
        $this->container->factory('service.contact', fn(Container $c) => new \Api\System\Library\Service\ContactService(
            $c->get('repository.contact'),
            $c->get('repository.counterparty'),
            $c->get('repository.user_management'),
            $c->get('policy.hierarchy'),
            $c->get('service.ai_semantic_index')
        ));
        $this->container->factory('service.session', fn(Container $c) => new \Api\System\Library\Service\SessionService(
            $c->get('repository.session'),
            $c->get('logger')
        ));
        $this->container->factory('service.notification', fn(Container $c) => new NotificationService(
            $c->get('repository.notification'),
            $c->get('repository.user'),
            $c->get('logger'),
            $c->get('repository.task'),
            $c->get('service.notification_push'),
            $c->get('lang')
        ));
        $this->container->factory('service.chat', fn(Container $c) => new ChatService(
            $c->get('db.pdo'),
            $c->get('service.notification'),
            $c->get('lang')
        ));
        $this->container->factory('service.notification_push', fn(Container $c) => new \Api\System\Library\Service\NotificationPushService(
            $c->get('repository.notification_push_subscription'),
            $c->get('repository.notification_push_queue'),
            $c->get('logger'),
            $this->config,
            $c->get('lang')
        ));
        $this->container->factory('service.reminder', fn(Container $c) => new ReminderService(
            $c->get('repository.reminder'),
            $c->get('repository.task'),
            $c->get('logger'),
            $c->get('service.notification')
        ));
        $this->container->factory('service.calendar', fn(Container $c) => new CalendarService(
            $c->get('repository.calendar_event'),
            $c->get('repository.task'),
            $c->get('repository.project'),
            $c->get('repository.reminder'),
            $c->get('logger'),
            $c->get('service.notification')
        ));
        $this->container->factory('service.business_calendar', fn(Container $c) => new BusinessCalendarService(
            $c->get('repository.business_calendar'),
            $c->get('logger')
        ));
        $this->container->factory('service.worklog', fn(Container $c) => new WorklogService(
            $c->get('repository.worklog'),
            $c->get('repository.task'),
            $c->get('logger')
        ));
        $this->container->factory('service.dashboard', fn(Container $c) => new DashboardService(
            $c->get('repository.dashboard')
        ));
        $this->container->factory('service.analytics', fn(Container $c) => new AnalyticsService(
            $c->get('repository.analytics')
        ));
        $this->container->factory('service.task_activity', fn(Container $c) => new TaskActivityService(
            $c->get('repository.task_activity')
        ));
        $this->container->factory('service.work_cycle', fn(Container $c) => new WorkCycleService(
            $c->get('repository.work_cycle'),
            $c->get('repository.cycle_task'),
            $c->get('repository.cycle_snapshot'),
            $c->get('repository.task'),
            $c->get('service.task'),
            $c->get('service.project'),
            $c->get('service.task_activity')
        ));

        $this->container->factory('service.project_module', fn(Container $c) => new ProjectModuleService(
            new \Api\Model\Project\ProjectModuleRepository($c->get('db.pdo')),
            new \Api\Model\Project\ProjectModuleTaskRepository($c->get('db.pdo')),
            new \Api\Model\Project\ProjectModuleMemberRepository($c->get('db.pdo')),
            new \Api\Model\Project\ProjectModuleLinkRepository($c->get('db.pdo')),
            $c->get('service.project'),
            $c->get('repository.task'),
            $c->get('service.task')
        ));

        $this->container->factory('repository.estimate_set', fn(Container $c) => new \Api\Model\Estimate\EstimateSetRepository($c->get('db.pdo')));
        $this->container->factory('repository.estimate_option', fn(Container $c) => new \Api\Model\Estimate\EstimateOptionRepository($c->get('db.pdo')));
        $this->container->factory('repository.task_estimate', fn(Container $c) => new \Api\Model\Estimate\TaskEstimateRepository($c->get('db.pdo')));

        $this->container->factory('service.task_estimate', fn(Container $c) => new TaskEstimateService(
            $c->get('repository.estimate_set'),
            $c->get('repository.estimate_option'),
            $c->get('repository.task_estimate'),
            $c->get('service.task'),
            $c->get('db.pdo')
        ));

        $this->container->factory('repository.sticky_note', fn(Container $c) => new \Api\Model\Sticky\StickyNoteRepository($c->get('db.pdo')));

        $this->container->factory('service.sticky_note', fn(Container $c) => new StickyNoteService(
            $c->get('repository.sticky_note'),
            $c->get('repository.knowledge'),
            $c->get('repository.project'),
            $c->get('service.task'),
            $c->get('logger'),
            $c->get('request')->requestId
        ));

        $this->container->factory('service.knowledge_page_version', fn(Container $c) => new KnowledgePageVersionService(
            new \Api\Model\Knowledge\KnowledgePageVersionRepository($c->get('db.pdo')),
            $c->get('service.project'),
            $c->get('logger'),
            $c->get('request')->requestId
        ));

        $this->container->factory('service.activity', fn(Container $c) => new ActivityService(
            $c->get('repository.activity')
        ));
        $this->container->factory('service.admin_widget', fn(Container $c) => new AdminWidgetService(
            $c->get('repository.admin_widget'),
            $c->get('config')
        ));
        $this->container->factory('service.api_client', fn(Container $c) => new ApiClientService(
            $c->get('repository.api_client'),
            $c->get('security.token'),
            $c->get('logger')
        ));
        $this->container->factory('service.webhook', fn(Container $c) => new WebhookService(
            $c->get('repository.webhook'),
            $c->get('logger'),
            $c->get('config')
        ));
        $this->container->factory('service.ops', fn(Container $c) => new OpsService(
            $c->get('service.admin_widget'),
            $c->get('service.webhook')
        ));
        $this->container->factory('service.search', fn(Container $c) => new SearchService(
            $c->get('repository.search'),
            $c->get('repository.knowledge')
        ));
        $this->container->factory('service.setting', fn(Container $c) => new SettingService(
            $c->get('repository.setting')
        ));
        $this->container->factory('service.retention', fn(Container $c) => new RetentionService(
            $c->get('service.setting')
        ));
        $this->container->factory('service.ai_provider', fn(Container $c) => new AiProviderService(
            $c->get('repository.ai_provider'),
            $c->get('service.setting'),
            $c->get('logger'),
            $c->get('config'),
            $c->get('service.ai_provider_client_factory'),
            $c->get('request')
        ));
        $this->container->factory('service.ai_provider_client.openai_compatible', fn(Container $c) => new OpenAiCompatibleProviderClient());
        $this->container->factory('service.ai_provider_client.mock', fn(Container $c) => new MockAiProviderClient());
        $this->container->factory('service.ai_provider_client.custom_http', fn(Container $c) => new CustomHttpProviderClient());
        $this->container->factory('service.ai_provider_client_factory', fn(Container $c) => new AiProviderClientFactory(
            $c->get('service.ai_provider_client.openai_compatible'),
            $c->get('service.ai_provider_client.mock'),
            $c->get('service.ai_provider_client.custom_http')
        ));
        $this->container->factory('service.ai_action_type', fn(Container $c) => new AiActionTypeService(
            $c->get('service.setting'),
            $c->get('config'),
            $c->get('repository.ai_intent_settings')
        ));
        $this->container->factory('service.ai_availability', fn(Container $c) => new AiAvailabilityService(
            $c->get('service.ai_intent_settings'),
            $c->get('repository.ai_provider'),
            $c->get('service.feature_flag')
        ));
        $this->container->factory('service.ai_action', fn(Container $c) => new AiActionService(
            $c->get('repository.ai_provider'),
            $c->get('repository.ai_runtime'),
            $c->get('repository.ai_intent_settings'),
            $c->get('service.ai_action_type'),
            $c->get('service.ai_provider'),
            $c->get('service.feature_flag'),
            $c->get('service.ai_rate_limit'),
            $c->get('service.ai_cost_limit'),
            $c->get('logger'),
            $c->get('lang')
        ));
        $this->container->factory('service.ai_rate_limit', fn(Container $c) => new AiRateLimitService(
            $c->get('repository.ai_runtime'),
            $c->get('service.setting')
        ));
        $this->container->factory('service.ai_cost_limit', fn(Container $c) => new AiCostLimitService(
            $c->get('repository.ai_runtime'),
            $c->get('service.setting')
        ));
        $this->container->factory('service.ai_masking', fn(Container $c) => new AiMaskingService());
        $this->container->factory('service.ai_context_builder.task', fn(Container $c) => new TaskAiContextBuilder(
            $c->get('service.ai_masking'),
            $c->get('service.project'),
            $c->get('service.client'),
            $c->get('service.comment'),
            $c->get('service.subtask'),
            $c->get('service.checklist'),
            $c->get('service.task')
        ));
        $this->container->factory('service.ai_context_builder.project', fn(Container $c) => new ProjectAiContextBuilder(
            $c->get('service.project'),
            $c->get('service.project_summary'),
            $c->get('service.ai_masking')
        ));
        $this->container->factory('service.ai_context_builder.client', fn(Container $c) => new ClientAiContextBuilder(
            $c->get('service.client'),
            $c->get('service.project'),
            $c->get('service.task'),
            $c->get('service.calendar'),
            $c->get('service.ai_masking')
        ));
        $this->container->factory('service.ai_context_builder.calendar', fn(Container $c) => new CalendarAiContextBuilder(
            $c->get('service.calendar'),
            $c->get('service.task'),
            $c->get('service.ai_masking')
        ));
        $this->container->factory('service.ai_context_builder.dashboard', fn(Container $c) => new DashboardAiContextBuilder(
            $c->get('service.dashboard'),
            $c->get('service.analytics'),
            $c->get('service.ai_masking')
        ));
        $this->container->factory('service.ai_context_builder.admin', fn(Container $c) => new AdminAiContextBuilder(
            $c->get('service.admin_widget'),
            $c->get('service.logs'),
            $c->get('service.webhook'),
            $c->get('service.workflow'),
            $c->get('service.ai_masking')
        ));
        $this->container->factory('service.ai_context_builder.import', fn(Container $c) => new ImportAiContextBuilder(
            $c->get('service.import'),
            $c->get('service.ai_masking')
        ));
        $this->container->factory('service.ai_context_builder.security', fn(Container $c) => new SecurityAiContextBuilder(
            $c->get('service.logs'),
            $c->get('service.ai_masking')
        ));
        $this->container->factory('service.ai_context_builder', fn(Container $c) => new AiContextBuilder(
            $c->get('service.ai_context_builder.task'),
            $c->get('service.ai_context_builder.project'),
            $c->get('service.ai_context_builder.client'),
            $c->get('service.ai_context_builder.calendar'),
            $c->get('service.ai_context_builder.dashboard'),
            $c->get('service.ai_context_builder.admin'),
            $c->get('service.ai_context_builder.import'),
            $c->get('service.ai_context_builder.security')
        ));
        $this->container->factory('service.ai_token_budget', fn(Container $c) => new AiTokenBudgetService());
        $this->container->factory('service.ai_prompt_builder', fn(Container $c) => new AiPromptBuilderService(
            $c->get('service.ai_token_budget')
        ));
        $this->container->factory('service.ai_suggestion', fn(Container $c) => new AiSuggestionService(
            $c->get('repository.ai_provider'),
            $c->get('repository.ai_runtime'),
            $c->get('repository.ai_intent_settings'),
            $c->get('service.ai_retention'),
            $c->get('service.ai_prompt_schema'),
            $c->get('service.ai_prompt_builder'),
            $c->get('service.ai_context_builder'),
            $c->get('service.ai_rate_limit'),
            $c->get('service.ai_cost_limit'),
            $c->get('service.task'),
            $c->get('service.project'),
            $c->get('service.client'),
            $c->get('service.calendar'),
            $c->get('service.setting'),
            $c->get('service.ai_provider'),
            $c->get('service.feature_flag'),
            $c->get('logger'),
            $c->get('config'),
            $c->get('lang')
        ));
        $this->container->factory('service.ai_usage', fn(Container $c) => new AiUsageService(
            $c->get('repository.ai_runtime'),
            $c->get('service.logs')
        ));
        $this->container->factory('service.ai_job', fn(Container $c) => new AiJobService(
            $c->get('repository.ai_runtime'),
            $c->get('repository.ai_provider'),
            $c->get('service.feature_flag'),
            $c->get('service.setting'),
            $c->get('logger'),
            $c->get('service.ai_retention')
        ));
        $this->container->factory('service.ai_settings', fn(Container $c) => new AiSettingsService(
            $c->get('service.setting'),
            $c->get('service.feature_flag'),
            $c->get('repository.ai_provider')
        ));
        $this->container->factory('service.ai_preference', fn(Container $c) => new AiPreferenceService(
            $c->get('service.setting'),
            $c->get('logger')
        ));
        $this->container->factory('service.ai_user_preference', fn(Container $c) => new AiUserPreferenceService(
            $c->get('service.setting'),
            $c->get('logger')
        ));
        $this->container->factory('service.ai_retention', fn(Container $c) => new AiRetentionPolicyService(
            $c->get('service.setting'),
            $c->get('config'),
            $c->get('logger')
        ));
        $this->container->factory('service.ai_intent_settings', fn(Container $c) => new AiIntentSettingService(
            $c->get('repository.ai_intent_settings'),
            $c->get('repository.ai_json_schemas'),
            $c->get('repository.ai_prompt_templates'),
            $c->get('repository.ai_provider'),
            $c->get('service.setting'),
            $c->get('logger'),
            $c->get('config'),
            $c->get('lang')
        ));
        $this->container->factory('service.ai_prompt_schema', fn(Container $c) => new AiPromptSchemaService(
            $c->get('service.ai_prompt_template'),
            $c->get('service.ai_json_schema')
        ));
        $this->container->factory('service.ai_prompt_template', fn(Container $c) => new AiPromptTemplateService(
            $c->get('repository.ai_prompt_templates'),
            $c->get('logger')
        ));
        $this->container->factory('service.ai_json_schema', fn(Container $c) => new AiJsonSchemaService(
            $c->get('repository.ai_json_schemas'),
            $c->get('logger')
        ));
        $this->container->factory('service.ai_cron_scheduler', fn(Container $c) => new AiCronSchedulerService(
            $c->get('service.ai_job'),
            $c->get('logger')
        ));
        $this->container->factory('service.ai_semantic_index', fn(Container $c) => new AiSemanticIndexService(
            $c->get('config'),
            $c->get('logger')
        ));
        $this->container->factory('service.idempotency', fn(Container $c) => new \Api\System\Library\Service\IdempotencyService(
            $c->get('repository.idempotency')
        ));

        $this->container->factory('cache.api', fn(Container $c) => new ApiFileCache(
            $this->config,
            $c->get('logger')
        ));

        $this->container->factory('controller.module', fn(Container $c) => new \Api\Controller\Module\ModuleController($c));
        $this->container->factory('service.idea', fn(Container $c) => new \Api\System\Library\Service\IdeaService(
            $c->get('db.pdo'),
            $c->get('lang')
        ));

        $this->container->factory('service.intake_item', fn(Container $c) => new IntakeItemService(
            $c->get('repository.intake_item'),
            $c->get('repository.intake_item_activity'),
            $c->get('service.task'),
            $c->get('service.project'),
            $c->get('service.notification'),
            $c->get('lang')
        ));

        $router = new Router();
        /** @var array<int,array<string,mixed>> $routes */
        $routes = require $this->basePath . '/config/routes.php';
        $router->addMany($routes);

        $this->initModuleSystem($router);

        $this->container->set('router', $router);
    }

    private function initModuleSystem(Router $router): void
    {
        try {
            $this->initModuleSystemInternal($router);
        } catch (\Throwable $e) {
            error_log('[ModuleSystem] initModuleSystem failed: ' . $e->getMessage());
        }
    }

    private function initModuleSystemInternal(Router $router): void
    {
        /** @var PluginManager $pluginManager */
        $pluginManager = $this->container->get('plugin.manager');

        $pdo = $this->container->get('db.pdo');
        $dbConfig = $this->config->get('database.connections.' . ($this->config->get('database.default') ?: 'sqlite'));
        $driver = (string)($dbConfig['driver'] ?? 'sqlite');
        $storageBase = (string)($this->config->get('default.storage.base', dirname($this->basePath) . '/../storage_api'));

        $moduleConfig = new ModuleConfig($pdo);
        try { $moduleConfig->ensureTable($driver); } catch (\Throwable) {}
        $this->container->set('module.config', $moduleConfig);

        $moduleMigrations = new ModuleMigrationRunner($pdo);
        try { $moduleMigrations->ensureTable($driver); } catch (\Throwable) {}
        $this->container->set('module.migrations', $moduleMigrations);

        $moduleErrorHandler = new ModuleErrorHandler($pdo);
        try { $moduleErrorHandler->ensureTable($driver); } catch (\Throwable) {}
        $this->container->set('module.error_handler', $moduleErrorHandler);

        $moduleAuditLogger = new ModuleAuditLogger($pdo);
        try { $moduleAuditLogger->ensureTable($driver); } catch (\Throwable) {}
        $this->container->set('module.audit_logger', $moduleAuditLogger);

        $moduleDeprecation = new ModuleDeprecation($pdo);
        try { $moduleDeprecation->ensureTable($driver); } catch (\Throwable) {}
        $this->container->set('module.deprecation', $moduleDeprecation);

        $moduleCache = new ModuleCache($storageBase);
        $this->container->set('module.cache', $moduleCache);

        $eventDispatcher = new EventDispatcher();
        $this->container->set('module.event_dispatcher', $eventDispatcher);

        $codeValidator = new ModuleCodeValidator();
        $this->container->set('module.code_validator', $codeValidator);

        $tableValidator = new ModuleTableValidator();
        $this->container->set('module.table_validator', $tableValidator);

        $resourceLimits = new ModuleResourceLimits();
        $this->container->set('module.resource_limits', $resourceLimits);

        $profiler = new ModuleProfiler();
        $this->container->set('module.profiler', $profiler);

        $cronScheduler = new ModuleCronScheduler($pdo);
        try { $cronScheduler->ensureTables($driver); } catch (\Throwable) {}
        $this->container->set('module.cron_scheduler', $cronScheduler);

        try {
            $cronScheduler->registerTask('knowledge', new \Api\System\Library\Module\ScheduledTask(
                name: 'freshness.scan',
                description: 'Scan published knowledge pages for freshness, mark overdue pages as needs_update',
                schedule: '0 6 * * *',
                handler: [\Api\System\Library\Service\KnowledgeCronTaskHandler::class, 'freshnessScan'],
                timeout: 600,
            ));
            $cronScheduler->registerTask('knowledge', new \Api\System\Library\Module\ScheduledTask(
                name: 'drafts.cleanup',
                description: 'Clean up knowledge drafts older than 30 days',
                schedule: '0 3 * * 0',
                handler: [\Api\System\Library\Service\KnowledgeCronTaskHandler::class, 'draftsCleanup'],
                timeout: 300,
            ));
            $cronScheduler->registerTask('knowledge', new \Api\System\Library\Module\ScheduledTask(
                name: 'versions.cleanup',
                description: 'Clean up old knowledge page versions beyond 50 per page',
                schedule: '0 4 * * 0',
                handler: [\Api\System\Library\Service\KnowledgeCronTaskHandler::class, 'versionsCleanup'],
                timeout: 600,
            ));
            $cronScheduler->registerTask('knowledge', new \Api\System\Library\Module\ScheduledTask(
                name: 'search.reindex',
                description: 'Rebuild knowledge search index',
                schedule: '0 5 * * *',
                handler: [\Api\System\Library\Service\KnowledgeCronTaskHandler::class, 'reindexSearch'],
                timeout: 600,
            ));
        } catch (\Throwable $e) {
            error_log('[KnowledgeCron] Task registration failed: ' . $e->getMessage());
        }

        $jobDispatcher = new ModuleJobDispatcher($pdo);
        try { $jobDispatcher->ensureTable($driver); } catch (\Throwable) {}
        $this->container->set('module.job_dispatcher', $jobDispatcher);

        $webhookDispatcher = new ModuleWebhookDispatcher($pdo);
        try { $webhookDispatcher->ensureTable($driver); } catch (\Throwable) {}
        $this->container->set('module.webhook_dispatcher', $webhookDispatcher);

        $notificationDispatcher = new ModuleNotificationDispatcher($pdo);
        $this->container->set('module.notification_dispatcher', $notificationDispatcher);

        $featureFlags = new ModuleFeatureFlags($pdo);
        $this->container->set('module.feature_flags', $featureFlags);

        $circuitBreaker = new ModuleCircuitBreaker();
        $this->container->set('module.circuit_breaker', $circuitBreaker);

        $bulkhead = new ModuleBulkhead();
        $this->container->set('module.bulkhead', $bulkhead);

        $rateLimiter = new ModuleRateLimiter();
        $this->container->set('module.rate_limiter', $rateLimiter);

        $apiVersionManager = new ModuleApiVersionManager();
        $this->container->set('module.api_version_manager', $apiVersionManager);

        $pluginManager->discover();

        try {
            $activeModules = $moduleConfig->getActiveModules();
            $loadedModuleNames = [];
            foreach ($activeModules as $reg) {
                $moduleName = (string)$reg['module_name'];
                $pluginManager->load($moduleName);
                if ($pluginManager->isLoaded($moduleName)) {
                    $loadedModuleNames[$moduleName] = true;
                }

                $autoloader = $this->container->get('module.autoloader');
                $manifest = $pluginManager->getManifest($moduleName);
                if ($manifest !== null) {
                    $autoloader->registerModule($manifest->name, $manifest->vendor);
                }
            }
        } catch (\Throwable) {}

        try {
            $loadedModules = $pluginManager->getActive();
            foreach ($loadedModules as $name => $manifest) {
                if ($manifest->apiRoutes !== null) {
                    $moduleDir = $pluginManager->getModulesDir() . '/' . $manifest->name;
                    $routeFile = $moduleDir . '/' . $manifest->apiRoutes;
                    if (is_file($routeFile)) {
                        $moduleRoutes = require $routeFile;
                        if (is_array($moduleRoutes) && $moduleRoutes !== []) {
                            $router->addManyFromModule($moduleRoutes, $name);
                        }
                    }
                }
            }
        } catch (\Throwable) {}

        /** @var HookManager $hookManager */
        $hookManager = $this->container->get('hook.manager');

        $spRegistry = new ServiceProviderRegistry($this->container, $pluginManager, $hookManager);
        try { $spRegistry->registerAll(); } catch (\Throwable) {}
        try { $spRegistry->bootAll(); } catch (\Throwable) {}
        $this->container->set('module.service_provider_registry', $spRegistry);

        $modulePermissions = [];
        foreach ($spRegistry->getProviders() as $provider) {
            foreach ($provider->getPermissions() as $permCode) {
                $code = (string)$permCode;
                if ($code !== '') {
                    $modulePermissions[$code] = str_replace('.', ' ', $code);
                }
            }
        }
        if ($modulePermissions !== []) {
            try {
                $permissionService = $this->container->get('service.permission');
                $ref = new \ReflectionClass($permissionService);
                $permRepoProp = $ref->getProperty('permissions');
                $permRepo = $permRepoProp->getValue($permissionService);
                if ($permRepo instanceof \Api\Model\Permission\PermissionRepository) {
                    $permRepo->ensureRegistry($modulePermissions);
                }
            } catch (\Throwable $e) {
                error_log('[ModuleSystem] Permission registration failed: ' . $e->getMessage());
            }
        }
    }

    private function shouldApplyGlobalRouteRateLimit(Request $request, string $routePath): bool
    {
        $method = strtoupper($request->method);
        if ($method === 'OPTIONS') {
            return false;
        }

        $path = strtolower(trim($routePath));
        if ($path === '') {
            return false;
        }

        if (
            $path === '/api/v1/auth/login'
            || $path === '/api/v1/security/password-reset'
            || $path === '/api/v1/security/password-reset/request'
            || $path === '/api/v1/security/password-reset/confirm'
            || $path === '/api/v1/security/invitations/accept'
        ) {
            // Dedicated rate limiters already exist for auth/password-reset flows.
            return false;
        }

        if (
            str_starts_with($path, '/install/')
            || str_starts_with($path, '/internal/migration/')
            || str_starts_with($path, '/api/v1/health/')
        ) {
            return false;
        }

        if (in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
            return true;
        }

        if ($method === 'GET' && (str_contains($path, '/search') || str_contains($path, '/export'))) {
            return true;
        }

        return false;
    }

    /** @param array<string,mixed>|null $auth */
    private function globalRouteRateLimitKey(Request $request, string $routePath, ?array $auth): string
    {
        $actorPart = 'ip:' . trim($request->ip());
        if ($auth !== null) {
            $user = is_array($auth['user'] ?? null) ? $auth['user'] : [];
            $userPublicId = trim((string)($user['public_id'] ?? ''));
            $userId = (int)($user['id'] ?? 0);
            if ($userPublicId !== '') {
                $actorPart = 'usr:' . $userPublicId;
            } elseif ($userId > 0) {
                $actorPart = 'uid:' . (string)$userId;
            }
        }

        return hash('sha256', strtoupper($request->method) . '|' . strtolower(trim($routePath)) . '|' . $actorPart);
    }

    private function authenticate(): ?array
    {
        /** @var Request $request */
        $request = $this->container->get('request');
        $token = $request->bearerToken();
        $transport = 'bearer';
        if (!$token) {
            $cookieName = (string)$this->config->get('security.auth.cookie.name', 'crm_api_session');
            $token = trim((string)$request->cookie($cookieName, ''));
            $transport = 'cookie';
        }
        if (!$token) {
            return null;
        }

        /** @var AuthService $auth */
        $auth = $this->container->get('service.auth');
        $me = $auth->me($token);
        if (!$me) {
            return null;
        }

        /** @var \Api\Model\Common\UserRepository $users */
        $users = $this->container->get('repository.user');
        $user = $users->findByPublicId((string)$me['user']['public_id']);
        if (!$user) {
            return null;
        }

        $me['user']['id'] = (int)$user['id'];
        $me['auth_transport'] = $transport;
        $me['auth_token'] = $token;

        return $me;
    }

    /** @param array<string,mixed> $auth */
    private function passesCookieCsrfPolicy(Request $request, array $auth): bool
    {
        if (($auth['auth_transport'] ?? '') !== 'cookie') {
            return true;
        }

        if (!in_array($request->method, ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
            return true;
        }

        $route = trim($request->path, '/');
        if ($route === 'api/v1/auth/logout') {
            return true;
        }

        $token = (string)($auth['auth_token'] ?? '');
        if ($token === '') {
            return false;
        }

        $origin = trim((string)$request->header('Origin', ''));
        if ($origin !== '' && !$this->isAllowedCookieWriteOrigin($origin, $request)) {
            return false;
        }

        $headerName = (string)$this->config->get('security.auth.csrf.header', 'X-CSRF-Token');
        $provided = trim((string)$request->header($headerName, ''));
        if ($provided === '') {
            return false;
        }

        return hash_equals($this->csrfTokenForSession($token), $provided);
    }

    private function isAllowedCookieWriteOrigin(string $origin, Request $request): bool
    {
        $sameOrigin = $this->requestOrigin($request);
        if ($sameOrigin !== '' && strcasecmp($origin, $sameOrigin) === 0) {
            return true;
        }

        return $this->resolveCorsOrigin($origin) !== '';
    }

    private function requestOrigin(Request $request): string
    {
        $host = trim((string)($request->server['HTTP_HOST'] ?? $request->server['SERVER_NAME'] ?? ''));
        if ($host === '') {
            return '';
        }

        $https = strtolower((string)($request->server['HTTPS'] ?? ''));
        $proto = ($https !== '' && $https !== 'off' && $https !== '0') ? 'https' : 'http';
        $forwardedProto = strtolower((string)$request->header('X-Forwarded-Proto', ''));
        if (in_array($forwardedProto, ['http', 'https'], true)) {
            $proto = $forwardedProto;
        }

        return $proto . '://' . $host;
    }

    private function csrfTokenForSession(string $sessionToken): string
    {
        $secret = (string)$this->config->get('security.auth.csrf.secret_key', '');
        if ($secret === '' && !$this->isProductionEnvironment()) {
            $secret = (string)$this->config->get('install.bootstrap_secret', '');
        }
        if ($secret === '' && !$this->isProductionEnvironment()) {
            $secret = hash('sha256', $this->basePath);
        }
        if ($secret === '') {
            throw new RuntimeException('CONFIG_SECURITY_CSRF_SECRET_REQUIRED');
        }

        return hash_hmac('sha256', $sessionToken, $secret);
    }

    private function resolveCorsOrigin(?string $requestOrigin): string
    {
        $configured = trim((string)$this->config->get('security.cors.allow_origin', '*'));
        if ($configured === '*') {
            if ($this->isProductionEnvironment()) {
                return '';
            }

            return '*';
        }

        if ($requestOrigin === null || trim($requestOrigin) === '') {
            return '';
        }

        $origin = trim($requestOrigin);
        $allowed = array_values(array_filter(array_map(
            static fn(string $item): string => trim($item),
            explode(',', $configured)
        ), static fn(string $item): bool => $item !== ''));

        foreach ($allowed as $candidate) {
            if (strcasecmp($candidate, $origin) === 0) {
                return $origin;
            }
        }

        return '';
    }

    /** @return array<string,mixed> */
    private function buildSafeRequestPayload(Request $request): array
    {
        $input = $request->allInput();
        $safeKeys = [
            'route', 'page', 'limit', 'offset',
            'sort', 'order', 'search', 'query',
            'status', 'type', 'scope',
            'from', 'to', 'date_from', 'date_to',
            'locale', 'is_active',
        ];
        $safe = [];
        $omitted = 0;

        foreach ($safeKeys as $key) {
            if (!array_key_exists($key, $input)) {
                continue;
            }

            $value = $input[$key];
            if (is_scalar($value) || $value === null) {
                $safe[$key] = $value;
            } else {
                $safe[$key] = '[omitted_non_scalar]';
            }
        }

        foreach ($input as $key => $value) {
            if (in_array($key, $safeKeys, true)) {
                continue;
            }

            $normalized = strtolower((string)$key);
            if (
                str_contains($normalized, 'password')
                || str_contains($normalized, 'token')
                || str_contains($normalized, 'secret')
                || str_contains($normalized, 'content_base64')
                || $normalized === 'rows'
            ) {
                $omitted++;
                continue;
            }

            if (str_ends_with($normalized, '_public_id') && (is_scalar($value) || $value === null)) {
                $safe[$key] = $value;
                continue;
            }

            $omitted++;
        }

        if ($omitted > 0) {
            $safe['_omitted_fields_count'] = $omitted;
        }

        return $safe;
    }

    private function isAiRoute(string $routePath): bool
    {
        return str_starts_with(trim($routePath), '/api/v1/ai/');
    }

    private function aiRequestPayloadTooLarge(Request $request): bool
    {
        if (!in_array($request->method, ['POST', 'PATCH', 'PUT'], true)) {
            return false;
        }

        /** @var \Api\System\Library\Service\AiSettingsService $settings */
        $settings = $this->container->get('service.ai_settings');
        $maxBytes = max(100, min(200000, (int)($settings->getSettings()['max_input_chars'] ?? 4000)));

        $contentLength = (int)($request->server['CONTENT_LENGTH'] ?? $request->server['HTTP_CONTENT_LENGTH'] ?? 0);
        if ($contentLength > $maxBytes) {
            return true;
        }

        $encoded = json_encode($request->allInput(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            return false;
        }

        return strlen($encoded) > $maxBytes;
    }

    /** @param array<string,mixed> $result */
    private function emitBinary(array $result, Request $request): void
    {
        if (isset($result['error'])) {
            $errorCode = (string)$result['error'];
            $status = match ($errorCode) {
                'UNAUTHORIZED' => 401,
                'FILE_QUARANTINED' => 423,
                default => 404,
            };
            $resp = JsonResponse::error(
                code: $errorCode,
                message: $this->container->get('lang')->get('file/messages.download_error', 'File download error'),
                status: $status,
                requestId: $request->requestId,
                correlationId: $request->correlationId
            );
            $resp->send();
            return;
        }

        $path = (string)$result['path'];
        $name = (string)($result['name'] ?? basename($path));
        $mime = (string)($result['mime'] ?? 'application/octet-stream');
        $size = (int)($result['size'] ?? filesize($path));

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . $size);
        header('Content-Disposition: ' . $this->contentDispositionAttachment($name));

        $fp = fopen($path, 'rb');
        if ($fp === false) {
            $resp = JsonResponse::error(
                code: 'FILE_READ_ERROR',
                message: $this->container->get('lang')->get('file/messages.read_error', 'Failed to read file'),
                status: 500,
                requestId: $request->requestId,
                correlationId: $request->correlationId
            );
            $resp->send();
            return;
        }

        while (!feof($fp)) {
            echo fread($fp, 8192);
        }
        fclose($fp);
    }

    private function contentDispositionAttachment(string $name): string
    {
        $clean = $this->sanitizeDownloadFilename($name);
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $clean) ?? 'file.bin';
        $ascii = preg_replace('/["\\\\]+/', '_', $ascii) ?? 'file.bin';
        $ascii = trim($ascii);
        if ($ascii === '') {
            $ascii = 'file.bin';
        }

        return 'attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($clean);
    }

    private function sanitizeDownloadFilename(string $name): string
    {
        $normalized = str_replace('\\', '/', $name);
        $normalized = basename($normalized);
        $normalized = preg_replace('/[\x00-\x1F\x7F]+/', '', $normalized) ?? '';
        $normalized = trim($normalized);
        if ($normalized === '' || $normalized === '.' || $normalized === '..') {
            return 'file.bin';
        }

        if (function_exists('mb_substr')) {
            $normalized = mb_substr($normalized, 0, 180, 'UTF-8');
        } else {
            $normalized = substr($normalized, 0, 180);
        }

        return $normalized !== '' ? $normalized : 'file.bin';
    }

    /** @param array<string,mixed> $result */
    private function emitSse(array $result, Request $request): void
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache, no-transform');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        if (isset($result['stream']) && is_callable($result['stream'])) {
            $streamHandler = $result['stream'];
            $streamHandler();
            return;
        }

        $event = (string)($result['event'] ?? 'message');
        $data = $result['data'] ?? [];

        echo 'id: ' . $request->requestId . "\n";
        echo 'event: ' . $event . "\n";
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        @ob_flush();
        @flush();
    }
}
