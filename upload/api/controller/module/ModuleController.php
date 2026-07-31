<?php
declare(strict_types=1);

namespace Api\Controller\Module;

use Api\System\Library\Container;
use Api\System\Library\Http\JsonResponse;
use Api\System\Library\Http\Request;
use Api\System\Library\Language\LanguageManager;
use Api\System\Library\Module\PluginManager;
use Api\System\Library\Module\ModuleConfig;
use Api\System\Library\Module\ModuleMigrationRunner;
use Api\System\Library\Module\ModuleErrorHandler;
use Api\System\Library\Module\ModuleRemoteInstaller;
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
                    'title' => $manifest->title,
                    'description' => $manifest->description,
                    'is_loaded' => $pm->isLoaded($name),
                    'is_active' => $registry ? (bool)($registry['is_active'] ?? false) : false,
                    'status' => $registry ? ((bool)$registry['is_active'] ? 'active' : 'installed') : 'not_installed',
                    'installed_at' => $registry['installed_at'] ?? null,
                    'activated_at' => $registry['activated_at'] ?? null,
                ];
            }

            return JsonResponse::success('MODULES_LIST', $this->t('common/messages.ok', 'OK'), $items);
        } catch (\Throwable $e) {
            error_log('[ModuleController] list() failed: ' . $e->getMessage());
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

            return JsonResponse::success('MODULE_INFO', $this->t('common/messages.ok', 'OK'), [
                'name' => $manifest->name,
                'version' => $manifest->version,
                'vendor' => $manifest->vendor,
                'title' => $manifest->title,
                'description' => $manifest->description,
                'core_version' => $manifest->coreVersion,
                'dependencies' => $manifest->dependencies,
                'require_permissions' => $manifest->requirePermissions,
                'api_routes' => $manifest->apiRoutes,
                'web_routes' => $manifest->webRoutes,
                'migrations' => $manifest->migrations,
                'service_provider' => $manifest->serviceProvider,
                'is_loaded' => $pm->isLoaded($name),
                'is_active' => $registry ? (bool)($registry['is_active'] ?? false) : false,
                'installed_at' => $registry['installed_at'] ?? null,
                'activated_at' => $registry['activated_at'] ?? null,
            ]);
        } catch (\Throwable $e) {
            error_log('[ModuleController] get() failed: ' . $e->getMessage());
            return JsonResponse::error('INTERNAL_ERROR', $this->t('common/messages.internal_error', 'Internal error'), 500);
        }
    }

    public function install(array $params = []): JsonResponse
    {
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
    }

    public function activate(array $params = []): JsonResponse
    {
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

        $pm->load($name);

        $mc->setActive($name);

        return JsonResponse::success('MODULE_ACTIVATED', $this->t('module/messages.activated'), ['name' => $name]);
    }

    public function deactivate(array $params = []): JsonResponse
    {
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

        return JsonResponse::success('MODULE_DEACTIVATED', $this->t('module/messages.deactivated'), ['name' => $name]);
    }

    public function uninstall(array $params = []): JsonResponse
    {
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
            error_log('[ModuleController::remove] error_handler cleanup failed for ' . $name . ': ' . $e->getMessage());
        }

        try {
            $cs = $this->container->get('module.cron_scheduler');
            if ($cs instanceof ModuleCronScheduler) $cs->deleteAllForModule($name);
        } catch (\Throwable $e) {
            error_log('[ModuleController::remove] cron_scheduler cleanup failed for ' . $name . ': ' . $e->getMessage());
        }

        try {
            $wd = $this->container->get('module.webhook_dispatcher');
            if ($wd instanceof ModuleWebhookDispatcher) $wd->deleteWebhooks($name);
        } catch (\Throwable $e) {
            error_log('[ModuleController::remove] webhook_dispatcher cleanup failed for ' . $name . ': ' . $e->getMessage());
        }

        return JsonResponse::success('MODULE_REMOVED', $this->t('module/messages.removed'), ['name' => $name]);
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
        } catch (\Throwable $e) {
            error_log('[ModuleController::installFromUrl] ' . $e->getMessage());
            return JsonResponse::error('INSTALL_FAILED', 'Module operation failed. Check server logs for details.', 500);
        }
    }

    public function installFromFile(array $params = []): JsonResponse
    {
        $input = $this->request()->allInput();
        $fileData = trim((string)($input['file_data'] ?? $params['file_data'] ?? ''));
        $fileName = trim((string)($input['file_name'] ?? $params['file_name'] ?? 'module.zip'));
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

            $safeFileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($fileName));
            if ($safeFileName === '' || $safeFileName === '.' || $safeFileName === '..') {
                $safeFileName = 'module.zip';
            }
            $archivePath = $tmpDir . '/' . $safeFileName;
            file_put_contents($archivePath, $decoded);

            $pm = $this->container->get('plugin.manager');
            $mc = $this->container->get('module.config');
            $mm = $this->container->get('module.migrations');

            $installer = new ModuleRemoteInstaller($pm, $mc, $mm, $projectRoot);
            $name = $installer->installFromFile($archivePath, true);
            return JsonResponse::success('MODULE_INSTALLED', $this->t('module/messages.installed_from_file'), ['name' => $name]);
        } catch (\Throwable $e) {
            error_log('[ModuleController::unknown] ' . $e->getMessage());
            return JsonResponse::error('INSTALL_FAILED', 'Module operation failed. Check server logs for details.', 500);
        } finally {
            foreach (glob($tmpDir . '/*') ?: [] as $f) @unlink($f);
            foreach (glob($tmpDir . '/*') ?: [] as $d) { if (is_dir($d)) { $this->cleanDir($d); } }
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
            error_log('[ModuleController::updateConfig] ' . $e->getMessage());
            return JsonResponse::error('UPDATE_FAILED', 'Module update failed. Check server logs for details.', 500);
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
            error_log('[ModuleController::clearErrors] ' . $e->getMessage());
            return JsonResponse::error('CLEAR_FAILED', 'Module clear operation failed. Check server logs for details.', 500);
        }
    }
}
