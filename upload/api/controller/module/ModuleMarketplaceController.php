<?php
declare(strict_types=1);

namespace Api\Controller\Module;

use Api\System\Library\Http\JsonResponse;
use Api\System\Library\Module\ModuleMarketplaceClient;
use Api\System\Library\Module\ModulePackageHashMismatchException;
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
        if (is_array($user) && (bool)($user['user']['is_root'] ?? false)) {
            return true;
        }
        if (!is_array($user) || !isset($user['user'])) {
            return false;
        }
        if (!$this->container->has('service.authz')) {
            return false;
        }
        /** @var \Api\System\Library\Service\AuthzService $authz */
        $authz = $this->container->get('service.authz');
        return $authz->hasAnyPermissions($user['user'], ['module.install']);
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

    /** List Marketplace releases newer than the installed registry version. */
    public function updates(): JsonResponse
    {
        $pm = $this->container->get('plugin.manager');
        $mc = $this->container->get('module.config');
        $pm->discover();
        $installed = [];
        foreach ($pm->getDiscovered() as $name => $manifest) {
            $registry = $mc->getRegistry((string)$name);
            if ($registry === null) continue;
            $installed[(string)$name] = [
                // The manifest version reflects the files currently on disk;
                // a stale registry value must not advertise an impossible update.
                'installed_version' => (string)$manifest->version,
                'is_active' => (bool)($registry['is_active'] ?? false),
                'title' => $manifest->title,
                'is_language_pack' => str_starts_with((string)$name, 'crm.language-pack-'),
            ];
        }

        try {
            $client = $this->client();
            $items = [];
            $page = 1;
            $pages = 1;
            do {
                $result = $client->catalog(['page' => $page, 'limit' => 48, 'core_version' => $this->coreVersion()]);
                foreach ($result['items'] as $item) {
                    $code = (string)($item['full_code'] ?? '');
                    if (!isset($installed[$code])) continue;
                    $installedVersion = $installed[$code]['installed_version'];
                    $latestVersion = (string)($item['latest_version'] ?? '');
                    if ($latestVersion === '' || !preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $installedVersion)
                        || !preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $latestVersion)
                        || version_compare($latestVersion, $installedVersion, '<=')) continue;
                    $items[] = [
                        'full_code' => $code,
                        'title' => $installed[$code]['title'] !== '' ? $installed[$code]['title'] : (string)($item['title'] ?? $code),
                        'installed_version' => $installedVersion,
                        'latest_version' => $latestVersion,
                        'min_core_version' => (string)($item['min_core_version'] ?? ''),
                        'is_active' => $installed[$code]['is_active'],
                        // Language packs use a separate locale installer and are
                        // intentionally withheld until that installer has an
                        // atomic update/rollback path of its own.
                        'update_supported' => !$installed[$code]['is_language_pack'],
                    ];
                }
                $pages = max(1, min(20, (int)($result['meta']['pages'] ?? 1)));
                $page++;
            } while ($page <= $pages);

            return $this->success('MODULE_MARKETPLACE_UPDATES', $this->t('common/messages.ok'), [
                'items' => $items,
                'count' => count($items),
                'can_update' => $this->isRoot(),
            ]);
        } catch (\Throwable $e) {
            AppLog::error('[ModuleMarketplaceController::updates] ' . $e->getMessage());
            return $this->error('MARKETPLACE_UNAVAILABLE', $this->t('module/messages.marketplace_unreachable'), 502);
        }
    }

    /**
     * Perform the actual update of a single module.
     * Returns metadata array on success or throws an exception.
     *
     * @return array{full_code: string, version: string, is_active: bool}
     */
    public function performUpdate(string $fullCode): array
    {
        $pm = $this->container->get('plugin.manager');
        $mc = $this->container->get('module.config');
        $pm->discover();
        $registry = $mc->getRegistry($fullCode);
        $manifest = $pm->getManifest($fullCode);
        if ($registry === null || $manifest === null || !is_dir($pm->getModulesDir() . '/' . $fullCode)) {
            throw new RuntimeException($this->t('module/messages.not_found'));
        }
        if (str_starts_with($fullCode, 'crm.language-pack-')) {
            throw new RuntimeException($this->t('module/messages.marketplace_update_unsupported'));
        }

        $client = $this->client();
        $detail = $client->module($fullCode, true);
        $release = $client->requestInstall($fullCode, $this->coreVersion(), $client->currentDomain());
        $targetVersion = trim((string)($release['version'] ?? ''));
        $sha256 = strtolower(trim((string)($release['sha256'] ?? '')));
        if ((string)($release['full_code'] ?? $release['module_name'] ?? $fullCode) !== $fullCode) {
            throw new RuntimeException('Marketplace returned a different module code');
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $sha256)) {
            throw new RuntimeException('Marketplace update has no valid SHA-256 pin');
        }
        $releaseInfo = null;
        foreach ((array)($detail['releases'] ?? []) as $candidate) {
            if (is_array($candidate) && (string)($candidate['version'] ?? '') === $targetVersion) {
                $releaseInfo = $candidate;
                break;
            }
        }
        if ($releaseInfo === null || !hash_equals($sha256, strtolower((string)($releaseInfo['sha256_checksum'] ?? '')))) {
            throw new RuntimeException('Marketplace release metadata does not match the signed update request');
        }
        $minCore = trim((string)($releaseInfo['min_core_version'] ?? $detail['min_core_version'] ?? ''));
        $releaseConstraint = \Api\System\Library\Module\Manifest::fromArray([
            'name' => $fullCode,
            'version' => $targetVersion,
            'vendor' => 'marketplace',
            'core_version' => $minCore,
        ]);
        if ($minCore !== '' && !$pm->checkCoreCompatibility($releaseConstraint, $this->coreVersion())) {
            throw new RuntimeException($this->t('module/messages.marketplace_core_incompatible'));
        }
        $minPhp = trim((string)($releaseInfo['min_php_version'] ?? ''));
        if ($minPhp !== '' && version_compare(PHP_VERSION, $minPhp, '<')) {
            throw new RuntimeException($this->t('module/messages.marketplace_php_incompatible'));
        }

        $installer = new ModuleRemoteInstaller(
            $pm,
            $mc,
            $this->container->get('module.migrations'),
            dirname(__DIR__, 3),
        );
        $installer->updateFromUrl(
            (string)$release['download_url'],
            $fullCode,
            $targetVersion,
            $this->coreVersion(),
            $sha256,
        );

        $this->dispatchModuleHook('module.updated', ['name' => $fullCode, 'source' => 'marketplace', 'version' => $targetVersion]);

        return [
            'full_code' => $fullCode,
            'version' => $targetVersion,
            'is_active' => (bool)($registry['is_active'] ?? false),
        ];
    }

    /** Update one or multiple registered modules to their latest published Marketplace release. */
    public function update(): JsonResponse
    {
        if (!$this->isRoot()) {
            return $this->error('FORBIDDEN', $this->t('module/messages.marketplace_root_required'), 403);
        }
        $input = $this->request()->allInput();
        $isAll = !empty($input['all']);
        $fullCodes = $input['full_codes'] ?? null;
        if (is_array($fullCodes)) {
            $codes = array_values(array_filter(array_map('trim', array_map('strval', $fullCodes)), [ModuleMarketplaceClient::class, 'isValidCode']));
        } elseif ($isAll) {
            $updatesResult = $this->updates();
            $updatesPayload = $updatesResult->payload();
            if ($updatesResult->status() >= 400 || ($updatesPayload['success'] ?? false) !== true) {
                return $updatesResult;
            }
            $updatesData = $updatesPayload['data'] ?? null;
            if (!is_array($updatesData) || !is_array($updatesData['items'] ?? null)) {
                return $this->error(
                    'MARKETPLACE_INVALID_RESPONSE',
                    $this->t('module/messages.marketplace_unreachable'),
                    502
                );
            }
            $items = $updatesData['items'];
            $codes = [];
            foreach ($items as $item) {
                if (!empty($item['update_supported']) && !empty($item['full_code'])) {
                    $codes[] = (string)$item['full_code'];
                }
            }
        } else {
            $codes = null;
        }

        if ($codes !== null) {
            if ($codes === []) {
                return $this->success('MODULE_MARKETPLACE_BATCH_UPDATED', $this->t('common/messages.ok'), [
                    'updated' => [],
                    'failed' => [],
                    'total' => 0,
                    'success_count' => 0,
                    'fail_count' => 0,
                ]);
            }

            $updated = [];
            $failed = [];
            foreach ($codes as $code) {
                try {
                    $res = $this->performUpdate($code);
                    $updated[] = $res;
                } catch (\Throwable $e) {
                    AppLog::error('[ModuleMarketplaceController::update batch] ' . $code . ': ' . $e->getMessage());
                    $failed[] = [
                        'full_code' => $code,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            return $this->success('MODULE_MARKETPLACE_BATCH_UPDATED', $this->t('module/messages.marketplace_updated'), [
                'updated' => $updated,
                'failed' => $failed,
                'total' => count($codes),
                'success_count' => count($updated),
                'fail_count' => count($failed),
            ]);
        }

        $fullCode = trim((string)($input['full_code'] ?? ''));
        if (!ModuleMarketplaceClient::isValidCode($fullCode)) {
            return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);
        }

        try {
            $res = $this->performUpdate($fullCode);
            return $this->success('MODULE_MARKETPLACE_UPDATED', $this->t('module/messages.marketplace_updated'), $res);
        } catch (ModulePackageHashMismatchException $e) {
            AppLog::error('[ModuleMarketplaceController::update] ' . $fullCode . ': ' . $e->getMessage());
            return $this->error('MARKETPLACE_SHA256_MISMATCH', $this->t('module/messages.marketplace_sha256_mismatch'), 502);
        } catch (\Throwable $e) {
            AppLog::error('[ModuleMarketplaceController::update] ' . $fullCode . ': ' . $e->getMessage());
            return $this->error('MODULE_UPDATE_FAILED', $e->getMessage() ?: $this->t('module/messages.marketplace_update_failed'), 500, [
                'full_code' => $fullCode,
            ]);
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

        // A language pack whose locale is gone (deleted under Administration →
        // Languages) leaves only its proxy module behind: the directory check
        // below would then answer ALREADY_INSTALLED forever and the pack could
        // never be installed again. Repair that state instead of refusing —
        // the same philosophy as the registry-only leftover handled below.
        if (str_starts_with($fullCode, 'crm.language-pack-') && is_dir($pm->getModulesDir() . '/' . $fullCode)) {
            $localeCode = substr($fullCode, strlen('crm.language-pack-'));
            /** @var \Api\System\Library\Service\LanguagePackInstaller $langInstaller */
            $langInstaller = $this->container->get('service.language_pack_installer');
            if (!$langInstaller->isLocaleInstalled($localeCode)) {
                if ($registry !== null) {
                    $mc->unregister($fullCode);
                }
                if ($langInstaller->removeModuleProxy($localeCode)) {
                    AppLog::warning('[ModuleMarketplaceController::install] removed orphaned language pack proxy for ' . $fullCode);
                }
                $registry = $mc->getRegistry($fullCode);
            }
        }

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

        $isLanguagePack = false;
        $detail = null;
        try {
            $detail = $client->module($fullCode);
            if (($detail['category_slug'] ?? '') === 'localization' || ($detail['product_type'] ?? '') === 'language_pack') {
                $isLanguagePack = true;
            }
        } catch (\Throwable) {}
        if (!$isLanguagePack && str_starts_with($fullCode, 'crm.language-pack-')) {
            $isLanguagePack = true;
        }

        try {
            $release = $client->requestInstall($fullCode, $this->coreVersion(), $client->currentDomain());
            $expectedSha256 = (string)($release['sha256'] ?? '');

            if ($isLanguagePack) {
                /** @var \Api\System\Library\Service\LanguagePackInstaller $langInstaller */
                $langInstaller = $this->container->get('service.language_pack_installer');
                $langResult = $langInstaller->installFromUrl(
                    $release['download_url'],
                    $expectedSha256 !== '' ? $expectedSha256 : null
                );

                // Register proxy manifest in modules directory so admin-modules sees it
                $moduleDir = $pm->getModulesDir() . '/' . $fullCode;
                if (!is_dir($moduleDir)) {
                    @mkdir($moduleDir, 0755, true);
                }
                $moduleManifest = [
                    'name' => $fullCode,
                    'version' => (string)($release['version'] ?? '1.0.0'),
                    'vendor' => 'crm',
                    'author' => (string)($detail['author'] ?? 'Anton Barinov'),
                    'author_url' => (string)($detail['author_url'] ?? 'https://tropatt.com'),
                    'title' => (string)($detail['title'] ?? $langResult['name'] ?? $fullCode),
                    'description' => (string)($detail['summary'] ?? 'Language pack'),
                    'core_version' => '>=' . $this->coreVersion(),
                    'license' => 'MIT',
                    'category' => 'localization',
                    'dependencies' => [],
                    'require_permissions' => [],
                    'type' => 'language_pack',
                    'code' => (string)($langResult['code'] ?? 'ar-sa'),
                ];
                file_put_contents($moduleDir . '/manifest.json', (string)json_encode($moduleManifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                $pm->discover();
                $mc->register($fullCode, 'crm', (string)($release['version'] ?? '1.0.0'));
                if ($activate) {
                    $mc->setActive($fullCode);
                }

                $name = $fullCode;
            } else {
                $installer = new ModuleRemoteInstaller(
                    $pm,
                    $mc,
                    $this->container->get('module.migrations'),
                    dirname(__DIR__, 3),
                );
                // The catalog code is authoritative: the downloaded package must
                // declare exactly this name, otherwise the release was published
                // inconsistently and installing it would register the wrong module.
                // The install-request sha256 pins the archive: the download and the
                // hash both come from the configured marketplace over TLS, so a
                // matching hash is the channel proof that replaces the shared
                // MODULE_SIGNING_KEY (which a fresh installation never has).
                $name = $installer->installFromUrl(
                    $release['download_url'],
                    true,
                    $fullCode,
                    $expectedSha256 !== '' ? $expectedSha256 : null,
                );
            }
        } catch (ModulePackageHashMismatchException $e) {
            AppLog::error('[ModuleMarketplaceController::install] ' . $fullCode . ': ' . $e->getMessage());
            return $this->error('MARKETPLACE_SHA256_MISMATCH', $this->t('module/messages.marketplace_sha256_mismatch'), 502, [
                'name' => $fullCode,
            ]);
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
            if ($isLanguagePack) {
                $activated = true;
            } else {
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
