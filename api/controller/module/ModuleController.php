<?php
declare(strict_types=1);

namespace Api\Controller\Module;

use Api\System\Library\Container;
use Api\System\Library\Http\JsonResponse;
use Api\System\Library\Language\LanguageManager;
use Api\System\Library\Module\PluginManager;
use Api\System\Library\Module\ModuleConfig;
use Api\System\Library\Module\ModuleMigrationRunner;
use Api\System\Library\Module\ModuleErrorHandler;
use Api\System\Library\Module\ModuleRemoteInstaller;
use Api\System\Library\Module\ModuleCronScheduler;
use Api\System\Library\Module\ModuleWebhookDispatcher;

final class ModuleController
{
    public function __construct(
        private readonly Container $container,
    ) {}

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
            return JsonResponse::error('MODULE_NOT_FOUND', 'Module not found', 404);
        }

        $errors = $pm->validate($manifest);
        if ($errors !== []) {
            return JsonResponse::error('VALIDATION_ERROR', 'Module validation failed', 400, ['errors' => $errors]);
        }

        $registry = $mc->getRegistry($name);
        if ($registry !== null) {
            return JsonResponse::error('ALREADY_INSTALLED', 'Module already installed', 409);
        }

        $mc->register($name, $manifest->vendor, $manifest->version);

        if ($manifest->migrations !== null) {
            $migrationDir = $pm->getModulesDir() . '/' . $manifest->name . '/' . $manifest->migrations;
            $result = $mm->migrate($name, $migrationDir);

            if ($result['errors'] !== []) {
                return JsonResponse::error('MIGRATION_ERROR', 'Migration failed', 500, ['errors' => $result['errors']]);
            }
        }

        $mc->initFromManifest($name, $manifest);

        return JsonResponse::success('MODULE_INSTALLED', 'Module installed successfully', ['name' => $name, 'version' => $manifest->version]);
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
            return JsonResponse::error('NOT_INSTALLED', 'Module not installed', 400);
        }

        $manifest = $pm->getManifest($name);
        if ($manifest === null) {
            return JsonResponse::error('MODULE_NOT_FOUND', 'Module manifest not found', 404);
        }

        if (!$pm->checkCoreCompatibility($manifest, '1.0.0')) {
            return JsonResponse::error('CORE_INCOMPATIBLE', 'Module requires core ' . $manifest->coreVersion, 400);
        }

        $pm->load($name);

        $mc->setActive($name);

        return JsonResponse::success('MODULE_ACTIVATED', 'Module activated successfully', ['name' => $name]);
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
            return JsonResponse::error('NOT_INSTALLED', 'Module not installed', 400);
        }

        $mc->setInactive($name);

        return JsonResponse::success('MODULE_DEACTIVATED', 'Module deactivated successfully', ['name' => $name]);
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
            return JsonResponse::error('NOT_INSTALLED', 'Module not installed', 400);
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
        } catch (\Throwable) {}

        try {
            $cs = $this->container->get('module.cron_scheduler');
            if ($cs instanceof ModuleCronScheduler) $cs->deleteAllForModule($name);
        } catch (\Throwable) {}

        try {
            $wd = $this->container->get('module.webhook_dispatcher');
            if ($wd instanceof ModuleWebhookDispatcher) $wd->deleteWebhooks($name);
        } catch (\Throwable) {}

        return JsonResponse::success('MODULE_REMOVED', 'Module removed successfully', ['name' => $name]);
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
        $url = trim((string)($params['url'] ?? ''));
        if ($url === '') {
            return JsonResponse::error('INVALID_PARAM', 'URL is required', 400);
        }

        $projectRoot = dirname(__DIR__, 3);
        $pm = $this->container->get('plugin.manager');
        $mc = $this->container->get('module.config');
        $mm = $this->container->get('module.migrations');

        try {
            $installer = new ModuleRemoteInstaller($pm, $mc, $mm, $projectRoot);
            $name = $installer->installFromUrl($url, true);
            return JsonResponse::success('MODULE_INSTALLED', 'Module installed from URL', ['name' => $name]);
        } catch (\Throwable $e) {
            return JsonResponse::error('INSTALL_FAILED', $e->getMessage(), 500);
        }
    }

    public function installFromFile(array $params = []): JsonResponse
    {
        $fileData = trim((string)($params['file_data'] ?? ''));
        $fileName = trim((string)($params['file_name'] ?? 'module.zip'));
        if ($fileData === '') {
            return JsonResponse::error('INVALID_PARAM', 'File data is required', 400);
        }

        $projectRoot = dirname(__DIR__, 3);
        $tmpDir = sys_get_temp_dir() . '/crm_module_' . bin2hex(random_bytes(8));
        @mkdir($tmpDir, 0755, true);

        try {
            $decoded = base64_decode($fileData, true);
            if ($decoded === false) {
                return JsonResponse::error('INVALID_PARAM', 'Invalid base64 data', 400);
            }

            $archivePath = $tmpDir . '/' . basename($fileName);
            file_put_contents($archivePath, $decoded);

            $pm = $this->container->get('plugin.manager');
            $mc = $this->container->get('module.config');
            $mm = $this->container->get('module.migrations');

            $installer = new ModuleRemoteInstaller($pm, $mc, $mm, $projectRoot);
            $name = $installer->installFromFile($archivePath, true);
            return JsonResponse::success('MODULE_INSTALLED', 'Module installed from file', ['name' => $name]);
        } catch (\Throwable $e) {
            return JsonResponse::error('INSTALL_FAILED', $e->getMessage(), 500);
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
            return JsonResponse::error('INVALID_PARAM', 'Module name is required', 400);
        }

        try {
            $mc = $this->container->get('module.config');
            $mc->setMultiple($name, $config);
            return JsonResponse::success('CONFIG_UPDATED', 'Configuration updated', ['name' => $name, 'config' => $mc->getAll($name)]);
        } catch (\Throwable $e) {
            return JsonResponse::error('UPDATE_FAILED', $e->getMessage(), 500);
        }
    }

    public function migrations(array $params = []): JsonResponse
    {
        $name = $params['name'] ?? '';
        if ($name === '') {
            return JsonResponse::error('INVALID_PARAM', 'Module name is required', 400);
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
            return JsonResponse::error('INVALID_PARAM', 'Module name is required', 400);
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
            return JsonResponse::error('INVALID_PARAM', 'Module name is required', 400);
        }

        try {
            $eh = $this->container->get('module.error_handler');
            $eh->clearErrors($name);
            return JsonResponse::success('ERRORS_CLEARED', 'Errors cleared');
        } catch (\Throwable $e) {
            return JsonResponse::error('CLEAR_FAILED', $e->getMessage(), 500);
        }
    }
}
