<?php
declare(strict_types=1);

namespace Api\Controller\Admin;

use Api\System\Library\Support\AppLog;
use Api\Controller\Common\BaseController;
use Api\System\Library\Cache\ApiFileCache;
use Api\System\Library\Service\SettingService;

final class CacheController extends BaseController
{
    private function isRoot(): bool
    {
        $auth = $this->user();
        if (!$auth || !isset($auth['user'])) {
            return false;
        }
        $rawUser = (array)$auth['user'];
        if ((bool)($rawUser['is_root'] ?? false)) {
            return true;
        }
        $actor = $this->organizationScopedActor($rawUser);
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }
        $roles = is_array($actor['roles'] ?? null) ? $actor['roles'] : [];
        $normalizedRoles = array_values(array_unique(array_filter(array_map(
            static function (mixed $role): string {
                if (is_array($role)) {
                    $role = $role['code'] ?? $role['public_id'] ?? $role['name'] ?? '';
                }
                return strtolower(str_replace('-', '_', trim(is_scalar($role) ? (string)$role : '')));
            },
            $roles
        ), static fn(string $role): bool => $role !== '')));
        return array_intersect($normalizedRoles, ['super_admin', 'super_administrator', 'root']) !== [];
    }

    public function stats(): \Api\System\Library\Http\JsonResponse
    {
        if (($contextError = $this->rejectInvalidOrganizationContext()) !== null) {
            return $contextError;
        }
        if (!$this->isRoot()) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        /** @var ApiFileCache $cache */
        $cache = $this->container->get('cache.api');

        $enabled = $cache->isEnabled();
        $ttl = $cache->getDefaultTtl();

        if ($this->container->has('service.setting')) {
            try {
                $settingSvc = $this->container->get('service.setting');

                $enabledSetting = $settingSvc->get('system', 'api_file_cache_enabled');
                if ($enabledSetting !== null) {
                    $enabled = (bool)($enabledSetting['value'] ?? true);
                }

                $ttlSetting = $settingSvc->get('system', 'api_file_cache_ttl');
                if ($ttlSetting !== null) {
                    $val = $ttlSetting['value'] ?? null;
                    if ($val !== null && $val !== '') {
                        $ttl = max(1, (int)$val);
                    }
                }
            } catch (\Throwable $e) {
                AppLog::error('[CacheController::stats] ' . $e->getMessage());
            }
        }

        $stats = $cache->stats();

        return $this->success('CACHE_STATS', $this->t('admin/messages.cache_stats'), [
            'enabled' => $enabled,
            'ttl' => $ttl,
            'fileCount' => $stats['fileCount'],
            'totalSizeBytes' => $stats['totalSizeBytes'],
            'basePath' => $stats['basePath'],
        ]);
    }

    public function clear(): \Api\System\Library\Http\JsonResponse
    {
        if (($contextError = $this->rejectInvalidOrganizationContext()) !== null) {
            return $contextError;
        }
        if (!$this->isRoot()) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        /** @var ApiFileCache $cache */
        $cache = $this->container->get('cache.api');
        $cache->clearAll();

        return $this->success('CACHE_CLEARED', $this->t('admin/messages.cache_cleared'));
    }
}
