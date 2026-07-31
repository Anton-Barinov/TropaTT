<?php
declare(strict_types=1);

namespace Api\Controller\Admin;

use Api\Controller\Common\BaseController;
use Api\System\Library\Cache\ApiFileCache;
use Api\System\Library\Service\SettingService;

final class CacheController extends BaseController
{
    public function stats(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth || !(bool)($auth['user']['is_root'] ?? false)) {
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
                error_log('[CacheController::stats] ' . $e->getMessage());
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
        $auth = $this->user();
        if (!$auth || !(bool)($auth['user']['is_root'] ?? false)) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        /** @var ApiFileCache $cache */
        $cache = $this->container->get('cache.api');
        $cache->clearAll();

        return $this->success('CACHE_CLEARED', $this->t('admin/messages.cache_cleared'));
    }
}
