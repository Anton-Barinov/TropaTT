<?php
declare(strict_types=1);

namespace Api\Controller\Setting;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\SettingService;

final class SettingController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var SettingService $service */
        $service = $this->container->get('service.setting');
        $result = $service->list($this->request()->allInput());

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

        /** @var SettingService $service */
        $service = $this->container->get('service.setting');
        $item = $service->get($scope, $name);
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

        return $this->success('SETTING_SET', $this->t('setting/messages.set'), [
            'setting' => $item,
        ]);
    }
}
