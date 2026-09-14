<?php
declare(strict_types=1);

namespace Api\Controller\Module;

use Api\System\Library\Http\JsonResponse;
use Api\System\Library\Module\ModuleMarketplaceClient;
use Api\System\Library\Module\ModulePackageMismatchException;
use Api\System\Library\Module\ModuleRemoteInstaller;
use Api\System\Library\Support\AppLog;
use Api\System\Library\Update\CoreUpdateConfig;
use Api\System\Library\Update\CoreVersion;

/**
 * Official module marketplace bridge (marketplace.tropatt.com).
 *
 * The CRM proxies the marketplace catalog through its own API instead of letting
 * the browser call the marketplace directly:
 *  - the browser never learns the marketplace base URL from request data, so the
 *    admin session cannot redirect installs to another origin;
 *  - responses reach the well-known CRM envelope and the same RBAC checks as the
 *    rest of the admin API;
 *  - the download itself stays server-side, so a short-lived signed URL never
 *    ends up in browser history or logs.
 *
 * Installation is delegated to ModuleRemoteInstaller, which enforces the manifest
 * signature (MODULE_SIGNING_KEY), the AST code validator, archive magic bytes and
 * zip-slip protection before a single file reaches modules/<code>.
 */
final class ModuleMarketplaceController extends \Api\Controller\Common\BaseController
{
    private function client(): ModuleMarketplaceClient
    {
        $config = CoreUpdateConfig::load();
        /** @var array<string,mixed> $marketplace */
        $marketplace = is_array($config['marketplace'] ?? null) ? $config['marketplace'] : [];

        return new ModuleMarketplaceClient(
            $marketplace,
            dirname(__DIR__, 3) . '/storage_api/cache/marketplace',
        );
    }

    private function coreVersion(): string
    {
        $config = CoreUpdateConfig::load();
        $version = new CoreVersion((string)($config['storage_dir'] ?? ''), dirname(__DIR__, 3));
        $current = $version->current();
        $value = trim((string)($current['core_version'] ?? ''));

        return $value !== '' ? $value : '0.1.0';
    }

    private function isRoot(): bool
    {
        $user = $this->user();
        return is_array($user) && (bool)($user['user']['is_root'] ?? false);
    }

    public function catalog(): JsonResponse
    {
        $client = $this->client();
        $status = $client->status();

        if (!$status['enabled'] || !$status['configured']) {
            return $this->success('MODULE_MARKETPLACE_CATALOG', $this->t('module/messages.marketplace_unavailable'), [
                'status' => $status,
                'marketplace_url' => $client->baseUrl(),
                'items' => [],
                'meta' => ['page' => 1, 'limit' => 12, 'total' => 0, 'pages' => 1],
                'can_install' => false,
            ]);
        }

        $request = $this->request();
        try {
            $result = $client->catalog([
                'q' => (string)($request->input('q', '') ?? ''),
                'category' => (string)($request->input('category', '') ?? ''),
                'page' => max(1, (int)($request->input('page', 1) ?? 1)),
                'limit' => max(1, (int)($request->input('limit', 12) ?? 12)),
                'core_version' => $this->coreVersion(),
                'refresh' => (string)($request->input('refresh', '') ?? '') === '1',
            ]);
        } catch (\Throwable $e) {
            AppLog::error('[ModuleMarketplaceController::catalog] ' . $e->getMessage());
            return $this->error('MARKETPLACE_UNAVAILABLE', $this->t('module/messages.marketplace_unreachable'), 502, [], [
                'status' => array_merge($status, ['reachable' => false, 'error' => $e->getMessage()]),
            ]);
        }

        return $this->success('MODULE_MARKETPLACE_CATALOG', $this->t('common/messages.ok'), [
            'status' => array_merge($status, ['total' => (int)($result['meta']['total'] ?? 0)]),
            'marketplace_url' => $client->baseUrl(),
            'items' => $result['items'],
            'meta' => $result['meta'],
            'can_install' => $this->isRoot(),
        ]);
    }

    public function categories(): JsonResponse
    {
        $client = $this->client();
        if (!$client->isEnabled() || $client->baseUrl() === '') {
            return $this->success('MODULE_MARKETPLACE_CATEGORIES', $this->t('module/messages.marketplace_unavailable'), ['categories' => []]);
        }

        try {
            return $this->success('MODULE_MARKETPLACE_CATEGORIES', $this->t('common/messages.ok'), ['categories' => $client->categories()]);
        } catch (\Throwable $e) {
            AppLog::error('[ModuleMarketplaceController::categories] ' . $e->getMessage());
            return $this->error('MARKETPLACE_UNAVAILABLE', $this->t('module/messages.marketplace_unreachable'), 502);
        }
    }

    public function module(array $params = []): JsonResponse
    {
        $fullCode = trim((string)($params['full_code'] ?? ''));
        if (!ModuleMarketplaceClient::isValidCode($fullCode)) {
            return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);
        }

        try {
            $detail = $this->client()->module($fullCode);
            $installable = $this->client()->isInstallableProduct($detail);

            return $this->success('MODULE_MARKETPLACE_MODULE', $this->t('common/messages.ok'), [
                'module' => $detail,
                // A virtual product (the donation) is payable but not installable,
                // so it is never offered as an install even to a root admin.
                'can_install' => $this->isRoot() && $installable,
                'installable' => $installable,
            ]);
        } catch (\Throwable $e) {
            AppLog::error('[ModuleMarketplaceController::module] ' . $e->getMessage());
            return $this->error('MARKETPLACE_MODULE_UNAVAILABLE', $this->t('module/messages.marketplace_module_unavailable'), 502);
        }
    }

    /**
     * One-click install: ask for a signed download URL, then install it through
     * the same hardened path as an uploaded package.
     */
    public function install(): JsonResponse
    {
        if (!$this->isRoot()) {
            return $this->error('FORBIDDEN', $this->t('module/messages.marketplace_root_required'), 403);
        }

        $input = $this->request()->allInput();
        $fullCode = trim((string)($input['full_code'] ?? ''));
        $activate = (bool)($input['activate'] ?? true);
        if ($fullCode === '') {
            return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);
        }

        // The code becomes a filesystem path and part of an outbound request, so
        // it is validated here: a malformed value must answer "invalid parameter"
        // instead of failing later as a generic install error.
        if (!ModuleMarketplaceClient::isValidCode($fullCode)) {
            return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);
        }

        $client = $this->client();
        $pm = $this->container->get('plugin.manager');
        $mc = $this->container->get('module.config');
        $registry = $mc->getRegistry($fullCode);

        // Virtual products (the donation) carry no package and must not be turned
        // into a CRM module. Checked after the local conflict checks so a request
        // that can be answered from disk never reaches the network.
        $assertInstallable = function () use ($client, $fullCode): bool {
            try {
                return $client->isInstallableProduct($client->module($fullCode));
            } catch (\Throwable $e) {
                AppLog::warning('[ModuleMarketplaceController::install] installability check failed for ' . $fullCode . ': ' . $e->getMessage());
                return true;
            }
        };

        // Installing over an existing directory would fail deep inside the
        // installer with a filesystem error; report it as a normal conflict. The
        // directory is the only thing that can block the install: a registry row
        // on its own is not an installed module — it is the leftover of a failed
        // uninstall, and leaving it in place would make the module impossible to
        // install ever again (absent from /api/v1/modules, yet every attempt
        // answered ALREADY_INSTALLED). Repair that state instead of refusing.
        if (is_dir($pm->getModulesDir() . '/' . $fullCode)) {
            // Files without a registry entry are not an installed module (the
            // core build ships module directories), and a marketplace download
            // cannot be used either — the installer refuses an existing target
            // directory. Name the state instead of answering "already installed".
            if ($registry === null) {
                return $this->error('MODULE_DISCOVERED_LOCALLY', $this->t('module/messages.marketplace_discovered_locally'), 409, [
                    'name' => $fullCode,
                    'install_endpoint' => '/api/v1/modules/' . $fullCode . '/install',
                ]);
            }

            return $this->error('ALREADY_INSTALLED', $this->t('module/messages.marketplace_already_installed'), 409, [
                'name' => $fullCode,
            ]);
        }
        if ($mc->unregisterStale($fullCode, $pm->getModulesDir())) {
            AppLog::warning('[ModuleMarketplaceController::install] removed stale registry entry for ' . $fullCode);
        }

        if (!$assertInstallable()) {
            return $this->error('MARKETPLACE_NOT_INSTALLABLE', $this->t('module/messages.marketplace_not_installable'), 409, [
                'name' => $fullCode,
            ]);
        }

        try {
            $release = $client->requestInstall($fullCode, $this->coreVersion(), $client->currentDomain());
            $installer = new ModuleRemoteInstaller(
                $pm,
                $mc,
                $this->container->get('module.migrations'),
                dirname(__DIR__, 3),
            );
            // The catalog code is authoritative: the downloaded package must
            // declare exactly this name, otherwise the release was published
            // inconsistently and installing it would register the wrong module.
            $name = $installer->installFromUrl($release['download_url'], true, $fullCode);
        } catch (ModulePackageMismatchException $e) {
            AppLog::error('[ModuleMarketplaceController::install] ' . $fullCode . ': ' . $e->getMessage());
            return $this->error('MARKETPLACE_PACKAGE_MISMATCH', $this->t('module/messages.marketplace_package_mismatch'), 502, [
                'name' => $fullCode,
                'declared_name' => $e->declaredName(),
                'expected_name' => $e->expectedName(),
            ]);
        } catch (\Throwable $e) {
            AppLog::error('[ModuleMarketplaceController::install] ' . $fullCode . ': ' . $e->getMessage());
            return $this->error('INSTALL_FAILED', $this->t('module/messages.marketplace_install_failed'), 500, [
                'name' => $fullCode,
            ]);
        }

        $activated = false;
        $activationError = '';
        if ($activate) {
            try {
                $manifest = $pm->getManifest($name);
                if ($manifest !== null && $pm->checkCoreCompatibility($manifest, $this->coreVersion()) && $pm->load($name)) {
                    $mc->setActive($name);
                    $activated = true;
                } else {
                    $activationError = $this->t('module/messages.marketplace_activation_failed');
                }
            } catch (\Throwable $e) {
                $activationError = $this->t('module/messages.marketplace_activation_failed');
                AppLog::error('[ModuleMarketplaceController::install] activation failed for ' . $name . ': ' . $e->getMessage());
            }
        }

        $this->dispatchModuleHook('module.installed', ['name' => $name, 'source' => 'marketplace', 'version' => (string)$release['version']]);

        return $this->success('MODULE_MARKETPLACE_INSTALLED', $this->t('module/messages.marketplace_installed'), [
            'name' => $name,
            'version' => (string)$release['version'],
            'activated' => $activated,
            'activation_error' => $activationError,
            'sha256' => (string)$release['sha256'],
        ]);
    }
}
