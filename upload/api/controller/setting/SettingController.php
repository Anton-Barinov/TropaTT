<?php
declare(strict_types=1);

namespace Api\Controller\Setting;

use Api\Controller\Common\BaseController;
use Api\System\Library\Cache\ApiFileCache;
use Api\System\Library\Service\SettingService;

final class SettingController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $cache = $this->cacheApi();
        if ($cache !== null) {
            ksort($input);
            $cachePayload = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $cacheKey = 'list:' . hash('sha256', $cachePayload !== false ? $cachePayload : serialize($input));
            $result = $cache->remember('setting', $cacheKey, 60, function () use ($input) {
                /** @var SettingService $service */
                $service = $this->container->get('service.setting');
                return $service->list($input);
            });
        } else {
            /** @var SettingService $service */
            $service = $this->container->get('service.setting');
            $result = $service->list($input);
        }

        return $this->success('SETTING_LIST', $this->t('setting/messages.list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function get(array $params = []): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $name = trim((string)($params['name'] ?? $this->request()->input('name', '')));
        if ($name === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'name' => [$this->t('setting/messages.name_required')],
            ]);
        }

        $scope = trim((string)$this->request()->input('scope', 'system'));

        $cache = $this->cacheApi();
        if ($cache !== null) {
            $cacheKey = 'get:' . hash('sha256', $scope . ':' . $name);
            $item = $cache->remember('setting', $cacheKey, 60, function () use ($scope, $name) {
                /** @var SettingService $service */
                $service = $this->container->get('service.setting');
                return $service->get($scope, $name);
            });
        } else {
            /** @var SettingService $service */
            $service = $this->container->get('service.setting');
            $item = $service->get($scope, $name);
        }
        if (!$item) {
            return $this->error('SETTING_NOT_FOUND', $this->t('setting/messages.not_found'), 404, [
                'name' => [$this->t('setting/messages.not_found')],
            ]);
        }

        return $this->success('SETTING_GET', $this->t('setting/messages.get'), [
            'setting' => $item,
        ]);
    }

    public function set(array $params = []): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $name = trim((string)($params['name'] ?? $this->request()->input('name', '')));
        if ($name === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'name' => [$this->t('setting/messages.name_required')],
            ]);
        }

        if (!preg_match('/^[A-Za-z0-9._-]{1,190}$/', $name)) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'name' => [$this->t('setting/messages.name_invalid')],
            ]);
        }

        $input = $this->request()->allInput();
        if (!array_key_exists('value', $input)) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'value' => [$this->t('setting/messages.value_required')],
            ]);
        }

        $scope = trim((string)($input['scope'] ?? 'system'));
        /** @var SettingService $service */
        $service = $this->container->get('service.setting');
        $item = $service->set($scope, $name, $input['value']);
        $this->clearSettingCaches($scope, $name);

        return $this->success('SETTING_SET', $this->t('setting/messages.set'), [
            'setting' => $item,
        ]);
    }

    private function clearSettingCaches(string $scope, string $name): void
    {
        if ($this->container->has('cache.api')) {
            $cache = $this->container->get('cache.api');
            if ($cache instanceof ApiFileCache) {
                $cache->invalidateNamespace('setting');
                if ($scope === 'system' && in_array($name, ['api_file_cache_enabled', 'api_file_cache_ttl'], true)) {
                    $cache->clearAll();
                }
                // Board/chart payloads are cached in the 'page' namespace; drop them
                // so a limit change takes effect on the very next page load.
                if ($scope === 'system' && in_array($name, ['kanban_max_cards', 'gantt_max_tasks'], true)) {
                    $cache->invalidateNamespace('page');
                }
            }
        }
    }
}
