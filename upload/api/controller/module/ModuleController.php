<?php
declare(strict_types=1);

namespace Api\Controller\Module;

use Api\System\Library\Support\AppLog;
use Api\System\Library\Container;
use Api\System\Library\Http\JsonResponse;
use Api\System\Library\Http\Request;
use Api\System\Library\Language\LanguageManager;
use Api\System\Library\Module\PluginManager;
use Api\System\Library\Module\ModuleConfig;
use Api\System\Library\Module\ModuleMigrationRunner;
use Api\System\Library\Module\ModuleErrorHandler;
use Api\System\Library\Module\ModuleRemoteInstaller;
use Api\System\Library\Module\ModuleSigningKeyMissingException;
use Api\System\Library\Module\ModuleCronScheduler;
use Api\System\Library\Module\ModuleWebhookDispatcher;
use Api\System\Library\Security\UrlSafetyValidator;

final class ModuleController
{
    public function __construct(
        private readonly Container $container,
    ) {}

    private function request(): Request
    {
        return $this->container->get('request');
    }

    private function lang(): LanguageManager
    {
        return $this->container->get('lang');
    }

    private function t(string $key, string $default = ''): string
    {
        return $this->lang()->get($key, $default);
    }

    public function list(array $params = []): JsonResponse
    {
        try {
            $pm = $this->container->get('plugin.manager');
            $mc = $this->container->get('module.config');
            $pm->discover();
            $discovered = $pm->getDiscovered();
            $items = [];
            foreach ($discovered as $name => $manifest) {
                $registry = $mc->getRegistry($name);
                $items[] = [
                    'name' => $name,
                    'version' => $manifest->version,
                    'vendor' => $manifest->vendor,
                    'author' => $manifest->author,
                    'author_url' => $manifest->authorUrl,
                    'title' => $manifest->title,
                    'description' => $manifest->description,
                    'category' => $manifest->category,
                    'is_loaded' => $pm->isLoaded($name),
                    'is_active' => $registry ? (bool)($registry['is_active'] ?? false) : false,
                    'status' => $registry ? ((bool)$registry['is_active'] ? 'active' : 'installed') : 'not_installed',
                    'installed_at' => $registry['installed_at'] ?? null,
                    'activated_at' => $registry['activated_at'] ?? null,
                ];
            }

            return JsonResponse::success('MODULES_LIST', $this->t('common/messages.ok', 'OK'), $items);
        } catch (\Throwable $e) {
            AppLog::error('[ModuleController] list() failed: ' . $e->getMessage());
            return JsonResponse::error('INTERNAL_ERROR', $this->t('common/messages.internal_error', 'Internal error'), 500);
        }
    }

    public function get(array $params = []): JsonResponse
    {
        try {
            $name = $params['name'] ?? '';
            if ($name === '') {
                return JsonResponse::error('INVALID_PARAM', $this->t('common/messages.invalid_parameter', 'Invalid parameter'), 400);
            }

            $pm = $this->container->get('plugin.manager');
            $mc = $this->container->get('module.config');

            $manifest = $pm->getManifest($name);
            if ($manifest === null) {
                return JsonResponse::error('MODULE_NOT_FOUND', $this->t('common/messages.not_found', 'Not found'), 404);
            }

            $registry = $mc->getRegistry($name);

            // Page routes the module exposes (web/config/routes.php keys). Used by
            // the admin module detail page to link straight to the module's own
            // settings page, which is otherwise only reachable from the sidebar.
            $pageRoutes = [];
            if ($manifest->webRoutes !== null) {
                $routeFile = $pm->getModulesDir() . '/' . $manifest->name . '/' . $manifest->webRoutes;
                // SEC (audit 2026-10, finding #2): web_routes comes from the
                // manifest and is concatenated into a path that gets require()d
                // inside api/index.php, bypassing .htaccess protection of
                // storage_api. "../../../api/.env" used to be required and
                // printed verbatim (no PHP open tag = inline HTML output).
                // The resolved file must stay inside the module directory.
                $moduleDir = realpath($pm->getModulesDir() . '/' . $manifest->name);
                $realRouteFile = realpath($routeFile);
                if ($moduleDir !== false && $realRouteFile !== false
                    && str_starts_with($realRouteFile, $moduleDir . DIRECTORY_SEPARATOR)) {
                    $moduleRoutes = require $realRouteFile;
                    if (is_array($moduleRoutes)) {
                        foreach (array_keys($moduleRoutes) as $routeKey) {
                            $routeKey = (string)$routeKey;
                            if ($routeKey !== '') {
                                $pageRoutes[] = $routeKey;
                            }
                        }
                    }
                }
            }

            return JsonResponse::success('MODULE_INFO', $this->t('common/messages.ok', 'OK'), [
                'name' => $manifest->name,
                'version' => $manifest->version,
                'vendor' => $manifest->vendor,
                'author' => $manifest->author,
                'author_url' => $manifest->authorUrl,
                'title' => $manifest->title,
                'description' => $manifest->description,
                'category' => $manifest->category,
                'core_version' => $manifest->coreVersion,
                'dependencies' => $manifest->dependencies,
                'require_permissions' => $manifest->requirePermissions,
                'api_routes' => $manifest->apiRoutes,
                'web_routes' => $manifest->webRoutes,
                'page_routes' => $pageRoutes,
                'migrations' => $manifest->migrations,
                'service_provider' => $manifest->serviceProvider,
                'is_loaded' => $pm->isLoaded($name),
                'is_active' => $registry ? (bool)($registry['is_active'] ?? false) : false,
                'installed_at' => $registry['installed_at'] ?? null,
                'activated_at' => $registry['activated_at'] ?? null,
            ]);
        } catch (\Throwable $e) {
            AppLog::error('[ModuleController] get() failed: ' . $e->getMessage());
            return JsonResponse::error('INTERNAL_ERROR', $this->t('common/messages.internal_error', 'Internal error'), 500);
        }
    }

    public function install(array $params = []): JsonResponse
    {
        try {
            $name = $params['name'] ?? '';
            if ($name === '') {
                return JsonResponse::error('INVALID_PARAM', $this->t('common/messages.invalid_parameter', 'Invalid parameter'), 400);
            }

            $pm = $this->container->get('plugin.manager');
            $mc = $this->container->get('module.config');
            $mm = $this->container->get('module.migrations');

            $manifest = $pm->getManifest($name);
            if ($manifest === null) {
                return JsonResponse::error('MODULE_NOT_FOUND', $this->t('module/messages.not_found'), 404);
            }

            $errors = $pm->validate($manifest);
            if ($errors !== []) {
                return JsonResponse::error('VALIDATION_ERROR', $this->t('module/messages.validation_failed'), 400, ['errors' => $errors]);
            }

            $registry = $mc->getRegistry($name);
            if ($registry !== null) {
                return JsonResponse::error('ALREADY_INSTALLED', $this->t('module/messages.already_installed'), 409);
            }

            $mc->register($name, $manifest->vendor, $manifest->version);

            if ($manifest->migrations !== null) {
                $migrationDir = $pm->getModulesDir() . '/' . $manifest->name . '/' . $manifest->migrations;
                $result = $mm->migrate($name, $migrationDir);

                if ($result['errors'] !== []) {
                    $mc->unregister($name);
                    return JsonResponse::error('MIGRATION_ERROR', $this->t('module/messages.migration_failed'), 500, ['errors' => $result['errors']]);
                }
            }

            $mc->initFromManifest($name, $manifest);

            return JsonResponse::success('MODULE_INSTALLED', $this->t('module/messages.installed'), ['name' => $name, 'version' => $manifest->version]);
        } catch (\Throwable $e) {
            AppLog::error('[ModuleController::install] ' . ($name ?? '') . ': ' . $e->getMessage());
            return JsonResponse::error('INSTALL_FAILED', $this->t('module/messages.install_failed', 'Failed to install module') . ': ' . $e->getMessage(), 500);
        }
    }

    public function activate(array $params = []): JsonResponse
    {
        try {
            if ($this->requireRoot() !== null) { return $this->requireRoot(); }
            $name = $params['name'] ?? '';
            if ($name === '') {
                return JsonResponse::error('INVALID_PARAM', $this->t('common/messages.invalid_parameter', 'Invalid parameter'), 400);
            }

            $pm = $this->container->get('plugin.manager');
            $mc = $this->container->get('module.config');

            $registry = $mc->getRegistry($name);
            if ($registry === null) {
                return JsonResponse::error('NOT_INSTALLED', $this->t('module/messages.not_installed'), 400);
            }

            $manifest = $pm->getManifest($name);
            if ($manifest === null) {
                return JsonResponse::error('MODULE_NOT_FOUND', $this->t('module/messages.manifest_not_found'), 404);
            }

            if (!$pm->checkCoreCompatibility($manifest, '1.0.0')) {
                return JsonResponse::error('CORE_INCOMPATIBLE', $this->t('module/messages.core_incompatible') . ' ' . $manifest->coreVersion, 400);
            }

            $loaded = $pm->load($name);
            if (!$loaded) {
                $errors = $pm->getModuleErrors($name);
                return JsonResponse::error('VALIDATION_ERROR', $this->t('module/messages.validation_failed'), 400, ['errors' => $errors ?? []]);
            }

            $mc->setActive($name);

            try {
                $jobs = $this->container->get('module.job_dispatcher');
                if ($jobs instanceof \Api\System\Library\Module\ModuleJobDispatcher) {
                    $jobs->resumeModule($name);
                }
            } catch (\Throwable $e) {
                AppLog::error('[ModuleController::activate] Could not resume module jobs: ' . $e->getMessage());
            }

            try {
                if (str_starts_with($name, 'crm.language-pack-') && $this->container->has('service.language_registry')) {
                    $code = substr($name, strlen('crm.language-pack-'));
                    $this->container->get('service.language_registry')->toggle($code, true);
                }
            } catch (\Throwable) {}

            return JsonResponse::success('MODULE_ACTIVATED', $this->t('module/messages.activated'), ['name' => $name]);
        } catch (\Throwable $e) {
            AppLog::error('[ModuleController::activate] ' . $name . ': ' . $e->getMessage());
            return JsonResponse::error('ACTIVATE_FAILED', $this->t('module/messages.activate_failed', 'Failed to activate module') . ': ' . $e->getMessage(), 500);
        }
    }    public function deactivate(array $params = []): JsonResponse
    {
        try {
            $name = $params['name'] ?? '';
            if ($name === '') {
                return JsonResponse::error('INVALID_PARAM', $this->t('common/messages.invalid_parameter', 'Invalid parameter'), 400);
            }

            $mc = $this->container->get('module.config');
            $registry = $mc->getRegistry($name);
            if ($registry === null) {
                return JsonResponse::error('NOT_INSTALLED', $this->t('module/messages.not_installed'), 400);
            }

            $mc->setInactive($name);

            try {
                if (str_starts_with($name, 'crm.language-pack-') && $this->container->has('service.language_registry')) {
                    $code = substr($name, strlen('crm.language-pack-'));
                    $this->container->get('service.language_registry')->toggle($code, false);
                }
            } catch (\Throwable) {}

            return JsonResponse::success('MODULE_DEACTIVATED', $this->t('module/messages.deactivated'), ['name' => $name]);
        } catch (\Throwable $e) {
            AppLog::error('[ModuleController::deactivate] ' . ($name ?? '') . ': ' . $e->getMessage());
            return JsonResponse::error('DEACTIVATE_FAILED', $this->t('module/messages.deactivate_failed', 'Failed to deactivate module') . ': ' . $e->getMessage(), 500);
        }
    }

    public function uninstall(array $params = []): JsonResponse
    {
        try {
            $name = $params['name'] ?? '';
            if ($name === '') {
                return JsonResponse::error('INVALID_PARAM', $this->t('common/messages.invalid_parameter', 'Invalid parameter'), 400);
            }

            $pm = $this->container->get('plugin.manager');
            $mc = $this->container->get('module.config');
        $mm = $this->container->get('module.migrations');

        $registry = $mc->getRegistry($name);
        if ($registry === null) {
            return JsonResponse::error('NOT_INSTALLED', $this->t('module/messages.not_installed'), 400);
        }

        $manifest = $pm->getManifest($name);
        if ($manifest !== null && $manifest->migrations !== null) {
            $migrationDir = $pm->getModulesDir() . '/' . $manifest->name . '/' . $manifest->migrations;
            $mm->rollbackAll($name, $migrationDir);
        }

        $mc->unregister($name);

        try {
            $eh = $this->container->get('module.error_handler');
            if ($eh instanceof ModuleErrorHandler) $eh->clearErrors($name);
        } catch (\Throwable $e) {
            AppLog::error('[ModuleController::remove] error_handler cleanup failed for ' . $name . ': ' . $e->getMessage());
        }

        try {
            $cs = $this->container->get('module.cron_scheduler');
            if ($cs instanceof ModuleCronScheduler) $cs->deleteAllForModule($name);
        } catch (\Throwable $e) {
            AppLog::error('[ModuleController::remove] cron_scheduler cleanup failed for ' . $name . ': ' . $e->getMessage());
        }

        try {
            $wd = $this->container->get('module.webhook_dispatcher');
            if ($wd instanceof ModuleWebhookDispatcher) $wd->deleteWebhooks($name);
        } catch (\Throwable $e) {
            AppLog::error('[ModuleController::remove] webhook_dispatcher cleanup failed for ' . $name . ': ' . $e->getMessage());
        }

        return JsonResponse::success('MODULE_REMOVED', $this->t('module/messages.removed'), ['name' => $name]);
        } catch (\Throwable $e) {
            AppLog::error('[ModuleController::uninstall] ' . $name . ': ' . $e->getMessage());
            return JsonResponse::error('UNINSTALL_FAILED', $this->t('module/messages.uninstall_failed', 'Failed to uninstall module') . ': ' . $e->getMessage(), 500);
        }
    }

    /**
     * Fully remove a module from disk (in addition to uninstalling it).
     * Uninstalls (rollback migrations + unregister + cleanup) when the module
     * is installed, then deletes the module directory and its runtime storage.
     */
    public function purge(array $params = []): JsonResponse
    {
        if ($this->requireRoot() !== null) { return $this->requireRoot(); }
        $name = trim((string)($params['name'] ?? ''));
        if ($name === '') {
            return JsonResponse::error('INVALID_PARAM', $this->t('common/messages.invalid_parameter', 'Invalid parameter'), 400);
        }
        if (!preg_match('/^[a-z0-9]+\.[a-z0-9\-]+$/', $name)) {
            return JsonResponse::error('INVALID_PARAM', $this->t('module/messages.invalid_name', 'Invalid module name'), 400);
        }

        $pm = $this->container->get('plugin.manager');
        $mc = $this->container->get('module.config');

        $wasInstalled = $mc->getRegistry($name) !== null;

        // Uninstall first (rollback migrations + registry + cron/webhook cleanup).
        // A NOT_INSTALLED result is fine — we still want to delete the files.
        if ($wasInstalled) {
            try {
                $this->uninstall(['name' => $name]);
            } catch (\Throwable $e) {
                AppLog::error('[ModuleController::purge] uninstall failed for ' . $name . ': ' . $e->getMessage());
            }
        }

        // Physically delete the module directory (guarded by the name regex,
        // so no path traversal is possible).
        $modulesDir = rtrim((string)$pm->getModulesDir(), '/');
        $targetDir = $modulesDir . '/' . $name;
        $filesDeleted = $this->removeDirectoryRecursively($targetDir);

        // Remove runtime storage for the module (uploads, temp, exports, cache).
        $this->removeDirectoryRecursively($this->storageBase() . '/modules/' . $name);

        if (!$filesDeleted && is_dir($targetDir)) {
            AppLog::error('[ModuleController::purge] directory not fully removed (permissions?): ' . $targetDir);
        }

        if (!$filesDeleted && !$wasInstalled) {
            return JsonResponse::error('MODULE_NOT_FOUND', $this->t('module/messages.not_found'), 404);
        }

        return JsonResponse::success('MODULE_PURGED', $this->t('module/messages.purged', 'Module physically deleted'), [
            'name' => $name,
            'files_deleted' => $filesDeleted,
            'was_installed' => $wasInstalled,
        ]);
    }

    /**
     * Apply one action to several modules at once.
     * Body: { "action": "install|activate|deactivate|uninstall|purge", "modules": ["name1", ...] }
     */
    public function bulk(array $params = []): JsonResponse
    {
        $input = $this->request()->allInput();
        $action = trim((string)($input['action'] ?? ''));
        $modules = $input['modules'] ?? [];
        if (!is_array($modules)) {
            $modules = [];
        }
        $modules = array_values(array_unique(array_filter(array_map('strval', $modules), static fn(string $m): bool => $m !== '')));

        $allowed = ['install', 'activate', 'deactivate', 'uninstall', 'purge'];
        if (!in_array($action, $allowed, true)) {
            return JsonResponse::error('INVALID_PARAM', $this->t('common/messages.invalid_parameter', 'Invalid parameter'), 400);
        }
        if ($modules === []) {
            return JsonResponse::error('INVALID_PARAM', $this->t('module/messages.name_required', 'Module name is required'), 400);
        }
        if (count($modules) > 200) {
            return JsonResponse::error('INVALID_PARAM', $this->t('common/messages.invalid_parameter', 'Invalid parameter'), 400);
        }

        $results = [];
        $succeeded = 0;
        $failed = 0;

        foreach ($modules as $name) {
            try {
                $response = match ($action) {
                    'install' => $this->install(['name' => $name]),
                    'activate' => $this->activate(['name' => $name]),
                    'deactivate' => $this->deactivate(['name' => $name]),
                    'uninstall' => $this->uninstall(['name' => $name]),
                    'purge' => $this->purge(['name' => $name]),
                };
                $payload = $response->payload();
                $ok = $payload['success'] === true;
                if ($ok) {
                    $succeeded++;
                } else {
                    $failed++;
                }
                $results[] = [
                    'name' => $name,
                    'success' => $ok,
                    'code' => (string)($payload['code'] ?? ''),
                    'message' => (string)($payload['message'] ?? ''),
                ];
            } catch (\Throwable $e) {
                AppLog::error('[ModuleController::bulk] ' . $action . ' on ' . $name . ' failed: ' . $e->getMessage());
                $failed++;
                $results[] = [
                    'name' => $name,
                    'success' => false,
                    'code' => 'EXCEPTION',
                    'message' => $this->t('module/messages.operation_failed', 'Module operation failed'),
                ];
            }
        }

        return JsonResponse::success('MODULES_BULK', $this->t('module/messages.bulk_completed', 'Bulk action completed'), [
            'action' => $action,
            'total' => count($results),
            'succeeded' => $succeeded,
            'failed' => $failed,
            'results' => $results,
        ]);
    }

    /**
     * Recursively delete a directory. Returns true only when the directory is
     * fully removed (false when it did not exist or could not be deleted, e.g.
     * due to filesystem permissions on shared hosting).
     */
    private function removeDirectoryRecursively(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }
        $real = realpath($dir);
        $target = $real !== false ? $real : $dir;
        $this->cleanDir($target);
        @rmdir($target);
        return !is_dir($target);
    }

    private function storageBase(): string
    {
        try {
            $config = $this->container->get('config');
            return rtrim((string)$config->get('default.storage.base', dirname(__DIR__, 3) . '/storage'), '/');
        } catch (\Throwable $e) {
            return dirname(__DIR__, 3) . '/storage';
        }
    }

    public function config(array $params = []): JsonResponse
    {
        $name = $params['name'] ?? '';
        if ($name === '') {
            return JsonResponse::error('INVALID_PARAM', $this->t('common/messages.invalid_parameter', 'Invalid parameter'), 400);
        }

        $mc = $this->container->get('module.config');
        $config = $mc->getAll($name);

        return JsonResponse::success('MODULE_CONFIG', $this->t('common/messages.ok', 'OK'), ['name' => $name, 'config' => $config]);
    }

    public function health(array $params = []): JsonResponse
    {
        $name = $params['name'] ?? '';
        if ($name === '') {
            return JsonResponse::error('INVALID_PARAM', $this->t('common/messages.invalid_parameter', 'Invalid parameter'), 400);
        }

        $pm = $this->container->get('plugin.manager');
        $mc = $this->container->get('module.config');
        $manifest = $pm->getManifest($name);

        return JsonResponse::success('MODULE_HEALTH', $this->t('common/messages.ok', 'OK'), [
            'name' => $name,
            'status' => $manifest !== null ? 'healthy' : 'not_found',
            'is_active' => $mc->getRegistry($name) ? (bool)($mc->getRegistry($name)['is_active'] ?? false) : false,
            'checks' => [
                'manifest' => ['status' => $manifest !== null],
                'installed' => ['status' => $mc->getRegistry($name) !== null],
            ],
            'timestamp' => time(),
        ]);
    }

    public function installFromUrl(array $params = []): JsonResponse
    {
        if ($this->requireRoot() !== null) { return $this->requireRoot(); }
        $input = $this->request()->allInput();
        $url = trim((string)($input['url'] ?? $params['url'] ?? ''));
        if ($url === '') {
            return JsonResponse::error('INVALID_PARAM', $this->t('module/messages.url_required'), 400);
        }

        $validator = new UrlSafetyValidator();
        $validation = $validator->validateProviderUrl($url, true);
        if (!$validation['ok']) {
            return JsonResponse::error('INVALID_URL', $this->t('module/messages.url_not_allowed'), 422);
        }

        $projectRoot = dirname(__DIR__, 3);
        $pm = $this->container->get('plugin.manager');
        $mc = $this->container->get('module.config');
        $mm = $this->container->get('module.migrations');

        try {
            $installer = new ModuleRemoteInstaller($pm, $mc, $mm, $projectRoot);
            $name = $installer->installFromUrl($url, true);
            return JsonResponse::success('MODULE_INSTALLED', $this->t('module/messages.installed_from_url'), ['name' => $name]);
        } catch (ModuleSigningKeyMissingException $e) {
            AppLog::error('[ModuleController::installFromUrl] ' . $e->getMessage());
            return JsonResponse::error('MODULE_SIGNING_KEY_MISSING', $this->t('module/messages.signing_key_missing'), 500);
        } catch (\Throwable $e) {
            AppLog::error('[ModuleController::installFromUrl] ' . $e->getMessage());
            return JsonResponse::error('INSTALL_FAILED', $this->t('module/messages.install_failed'), 500);
        }
    }

    public function installFromFile(array $params = []): JsonResponse
    {
        if ($this->requireRoot() !== null) { return $this->requireRoot(); }
        $input = $this->request()->allInput();
        $fileData = trim((string)($input['file_data'] ?? $params['file_data'] ?? ''));
        if ($fileData === '') {
            return JsonResponse::error('INVALID_PARAM', $this->t('module/messages.file_data_required'), 400);
        }

        if (strlen($fileData) > 100 * 1024 * 1024) {
            return JsonResponse::error('INVALID_PARAM', $this->t('module/messages.file_too_large'), 400);
        }

        $projectRoot = dirname(__DIR__, 3);
        $tmpDir = sys_get_temp_dir() . '/crm_module_' . bin2hex(random_bytes(8));
        @mkdir($tmpDir, 0755, true);

        try {
            $decoded = base64_decode($fileData, true);
            if ($decoded === false) {
                return JsonResponse::error('INVALID_PARAM', $this->t('module/messages.invalid_base64'), 400);
            }

            // SEC (audit 2026-10, finding #5): the archive is written before
            // its HMAC signature is verified, and both content and file name
            // were attacker controlled. The extension is no longer taken from
            // the request — the temp file is always module.zip, so an arbitrary
            // suffix can never land on disk ahead of verification. (The proper
            // validate-then-write reorder is not possible here: the signature
            // covers the manifest inside the archive, which requires reading
            // the archive first.)
            $archivePath = $tmpDir . '/module.zip';
            file_put_contents($archivePath, $decoded);
            @chmod($archivePath, 0600);

            $pm = $this->container->get('plugin.manager');
            $mc = $this->container->get('module.config');
            $mm = $this->container->get('module.migrations');

            $installer = new ModuleRemoteInstaller($pm, $mc, $mm, $projectRoot);
            $name = $installer->installFromFile($archivePath, true);
            return JsonResponse::success('MODULE_INSTALLED', $this->t('module/messages.installed_from_file'), ['name' => $name]);
        } catch (ModuleSigningKeyMissingException $e) {
            AppLog::error('[ModuleController::installFromFile] ' . $e->getMessage());
            return JsonResponse::error('MODULE_SIGNING_KEY_MISSING', $this->t('module/messages.signing_key_missing'), 500);
        } catch (\Throwable $e) {
            AppLog::error('[ModuleController::unknown] ' . $e->getMessage());
            return JsonResponse::error('INSTALL_FAILED', $this->t('module/messages.install_failed'), 500);
        } finally {
            // SEC: glob('*') does not match dotfiles, so a crafted name could
            // survive the cleanup and rmdir then failed silently on a
            // non-empty directory. cleanDir() walks scandir() and removes
            // everything, including dotfiles and subdirectories.
            $this->cleanDir($tmpDir);
            @rmdir($tmpDir);
        }
    }

    private function cleanDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            if (is_dir($path)) { $this->cleanDir($path); @rmdir($path); }
            else @unlink($path);
        }
    }

    public function updateConfig(array $params = []): JsonResponse
    {
        $name = $params['name'] ?? '';
        $config = $params['config'] ?? [];
        if (!is_array($config)) $config = [];

        if ($name === '') {
            return JsonResponse::error('INVALID_PARAM', $this->t('module/messages.name_required'), 400);
        }

        try {
            $mc = $this->container->get('module.config');
            $mc->setMultiple($name, $config);
            return JsonResponse::success('CONFIG_UPDATED', $this->t('module/messages.config_updated'), ['name' => $name, 'config' => $mc->getAll($name)]);
        } catch (\Throwable $e) {
            AppLog::error('[ModuleController::updateConfig] ' . $e->getMessage());
            return JsonResponse::error('UPDATE_FAILED', $this->t('module/messages.update_failed'), 500);
        }
    }

    public function migrations(array $params = []): JsonResponse
    {
        $name = $params['name'] ?? '';
        if ($name === '') {
            return JsonResponse::error('INVALID_PARAM', $this->t('module/messages.name_required'), 400);
        }

        try {
            $pm = $this->container->get('plugin.manager');
            $mm = $this->container->get('module.migrations');
            $manifest = $pm->getManifest($name);

            if ($manifest === null || $manifest->migrations === null) {
                return JsonResponse::success('MIGRATIONS_LIST', 'OK', ['migrations' => []]);
            }

            $dir = $pm->getModulesDir() . '/' . $manifest->name . '/' . $manifest->migrations;
            $status = $mm->getStatus($name, $dir);
            return JsonResponse::success('MIGRATIONS_LIST', 'OK', $status);
        } catch (\Throwable $e) {
            return JsonResponse::success('MIGRATIONS_LIST', 'OK', ['migrations' => []]);
        }
    }

    public function errors(array $params = []): JsonResponse
    {
        $name = $params['name'] ?? '';
        if ($name === '') {
            return JsonResponse::error('INVALID_PARAM', $this->t('module/messages.name_required'), 400);
        }

        try {
            $eh = $this->container->get('module.error_handler');
            $errors = $eh->getErrors($name, 50);
            return JsonResponse::success('ERRORS_LIST', 'OK', ['errors' => $errors]);
        } catch (\Throwable $e) {
            return JsonResponse::success('ERRORS_LIST', 'OK', ['errors' => []]);
        }
    }

    public function clearErrors(array $params = []): JsonResponse
    {
        $name = $params['name'] ?? '';
        if ($name === '') {
            return JsonResponse::error('INVALID_PARAM', $this->t('module/messages.name_required'), 400);
        }

        try {
            $eh = $this->container->get('module.error_handler');
            $eh->clearErrors($name);
            return JsonResponse::success('ERRORS_CLEARED', $this->t('module/messages.errors_cleared'));
        } catch (\Throwable $e) {
            AppLog::error('[ModuleController::clearErrors] ' . $e->getMessage());
            return JsonResponse::error('CLEAR_FAILED', $this->t('module/messages.clear_failed'), 500);
        }
    }

    public function queueTick(): JsonResponse
    {
        $input = $this->request()->allInput();
        $limit = isset($input['limit']) ? max(1, min(20, (int)$input['limit'])) : 5;
        $maxSeconds = isset($input['max_seconds']) ? max(0.5, min(20.0, (float)$input['max_seconds'])) : 8.0;

        try {
            $dispatcher = $this->container->has('module.job_dispatcher')
                ? $this->container->get('module.job_dispatcher')
                : new \Api\System\Library\Module\ModuleJobDispatcher(
                    $this->container->get('db.pdo'),
                    $this->container->get('plugin.manager'),
                    $this->container->get('module.config')
                );

            $batch = $dispatcher->runBatch($limit, $maxSeconds);

            return JsonResponse::success('MODULE_QUEUE_TICK', $this->t('common/messages.ok', 'OK'), $batch);
        } catch (\Throwable $e) {
            AppLog::error('[ModuleController::queueTick] ' . $e->getMessage());
            return JsonResponse::error('QUEUE_TICK_FAILED', $e->getMessage(), 500);
        }
    }

    public function diagnostics(): JsonResponse
    {
        $pm = $this->container->get('plugin.manager');
        $mc = $this->container->get('module.config');
        $pdo = $this->container->get('db.pdo');

        $activeModules = $pm->getActive();
        $diagnostics = [];

        $user = $this->user();
        $isRoot = !empty($user['is_root']);
        $userOrgId = (int)($user['organization_id'] ?? 0);
        $input = $this->request()->allInput();
        $targetOrgId = $isRoot && isset($input['organization_id']) ? (int)$input['organization_id'] : ($isRoot ? 0 : $userOrgId);

        foreach ($activeModules as $name => $manifest) {
            $reg = $mc->getRegistry($name);
            $diag = [
                'name' => $name,
                'title' => $manifest->title,
                'version' => $manifest->version,
                'is_active' => (bool)($reg['is_active'] ?? false),
                'installed_at' => $reg['installed_at'] ?? null,
                'has_api_routes' => $manifest->apiRoutes !== null,
                'has_mcp_tools' => count($manifest->mcpTools) > 0,
            ];

            // Get job queue metrics for this module
            try {
                if ($targetOrgId > 0) {
                    $stmt = $pdo->prepare("
                        SELECT j.status, count(*) as cnt
                        FROM module_jobs j
                        JOIN module_job_contexts c ON c.job_id = j.id
                        WHERE j.module_name = :mod AND c.organization_id = :org_id
                        GROUP BY j.status
                    ");
                    $stmt->execute(['mod' => $name, 'org_id' => $targetOrgId]);
                } else {
                    $stmt = $pdo->prepare("SELECT status, count(*) as cnt FROM module_jobs WHERE module_name = :mod GROUP BY status");
                    $stmt->execute(['mod' => $name]);
                }
                $counts = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
                $diag['jobs'] = [
                    'pending' => (int)($counts['pending'] ?? 0),
                    'running' => (int)($counts['running'] ?? 0),
                    'retrying' => (int)($counts['retrying'] ?? 0),
                    'failed' => (int)($counts['failed'] ?? 0),
                    'completed' => (int)($counts['completed'] ?? 0),
                ];
            } catch (\Throwable) {
                $diag['jobs'] = null;
            }

            $diagnostics[] = $diag;
        }

        return JsonResponse::success('MODULE_DIAGNOSTICS', $this->t('common/messages.ok', 'OK'), [
            'modules' => $diagnostics,
            'timestamp' => time(),
        ]);
    }

    private function user(): ?array
    {
        $auth = $this->container->has('auth_user') ? $this->container->get('auth_user') : null;
        return $auth['user'] ?? null;
    }

    private function requireRoot(): ?JsonResponse
    {
        $user = $this->user();
        if (!$user || empty($user['is_root'])) {
            // Translated like every other refusal on this controller: a hardcoded
            // English string here left non-English users without a translation.
            return JsonResponse::error('FORBIDDEN', $this->t('module/messages.root_required'), 403);
        }
        return null;
    }
}
