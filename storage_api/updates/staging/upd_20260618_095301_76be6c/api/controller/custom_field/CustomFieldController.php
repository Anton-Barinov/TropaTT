<?php
declare(strict_types=1);

namespace Api\Controller\Custom_field;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\CustomFieldService;
use Api\System\Library\Validation\Validator;

final class CustomFieldController extends BaseController
{
    private const SCOPES = ['task', 'project', 'client', 'company', 'contact', 'user'];
    private const TYPES = ['text', 'textarea', 'number', 'boolean', 'date', 'datetime', 'select', 'multiselect', 'url', 'email', 'phone'];

    public function list(): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->user()) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $cache = $this->cacheApi();
        if ($cache !== null) {
            ksort($input);
            $cachePayload = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $cacheKey = 'list:' . md5($cachePayload !== false ? $cachePayload : serialize($input));
            $result = $cache->remember('custom_field', $cacheKey, 60, function () use ($input) {
                /** @var CustomFieldService $service */
                $service = $this->container->get('service.custom_field');
                return $service->list($input);
            });
        } else {
            /** @var CustomFieldService $service */
            $service = $this->container->get('service.custom_field');
            $result = $service->list($input);
        }

        return $this->success('CUSTOM_FIELD_LIST', $this->t('custom_field/messages.list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->user()) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'scope', $this->t('common/messages.field_required'))
            ->require($input, 'code', $this->t('common/messages.field_required'))
            ->require($input, 'title', $this->t('common/messages.field_required'))
            ->require($input, 'type', $this->t('common/messages.field_required'))
            ->enum($input, 'scope', self::SCOPES, $this->t('custom_field/messages.invalid_scope'))
            ->enum($input, 'type', self::TYPES, $this->t('custom_field/messages.invalid_type'))
            ->maxLen($input, 'code', 64, $this->t('custom_field/messages.max_64'))
            ->maxLen($input, 'title', 255, $this->t('custom_field/messages.max_255'));

        if (isset($input['options']) && !is_array($input['options'])) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'options' => [$this->t('custom_field/messages.options_array')],
            ]);
        }
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var CustomFieldService $service */
        $service = $this->container->get('service.custom_field');
        $item = $service->create($input);
        if ($item === 'FIELD_CODE_EXISTS') {
            return $this->error('CUSTOM_FIELD_CODE_EXISTS', $this->t('custom_field/messages.code_exists'), 409, [
                'code' => [$this->t('custom_field/messages.code_exists')],
            ]);
        }
        $this->invalidateCache('custom_field');

        return $this->success('CUSTOM_FIELD_CREATED', $this->t('custom_field/messages.created'), [
            'field' => $item,
        ], 201);
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->user()) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $publicId = (string)$params['public_id'];
        $cache = $this->cacheApi();
        if ($cache !== null) {
            $item = $cache->remember('custom_field', 'get:' . md5($publicId), 60, function () use ($publicId) {
                /** @var CustomFieldService $service */
                $service = $this->container->get('service.custom_field');
                return $service->get($publicId);
            });
        } else {
            /** @var CustomFieldService $service */
            $service = $this->container->get('service.custom_field');
            $item = $service->get($publicId);
        }
        if (!$item) {
            return $this->error('CUSTOM_FIELD_NOT_FOUND', $this->t('custom_field/messages.not_found'), 404);
        }

        return $this->success('CUSTOM_FIELD_DETAIL', $this->t('custom_field/messages.detail'), [
            'field' => $item,
        ]);
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->user()) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->enum($input, 'scope', self::SCOPES, $this->t('custom_field/messages.invalid_scope'))
            ->enum($input, 'type', self::TYPES, $this->t('custom_field/messages.invalid_type'))
            ->maxLen($input, 'code', 64, $this->t('custom_field/messages.max_64'))
            ->maxLen($input, 'title', 255, $this->t('custom_field/messages.max_255'));
        if (isset($input['options']) && !is_array($input['options'])) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'options' => [$this->t('custom_field/messages.options_array')],
            ]);
        }
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var CustomFieldService $service */
        $service = $this->container->get('service.custom_field');
        $item = $service->update((string)$params['public_id'], $input);
        if ($item === 'FIELD_CODE_EXISTS') {
            return $this->error('CUSTOM_FIELD_CODE_EXISTS', $this->t('custom_field/messages.code_exists'), 409, [
                'code' => [$this->t('custom_field/messages.code_exists')],
            ]);
        }
        if (!$item) {
            return $this->error('CUSTOM_FIELD_NOT_FOUND', $this->t('custom_field/messages.not_found'), 404);
        }
        $this->invalidateCache('custom_field');

        return $this->success('CUSTOM_FIELD_UPDATED', $this->t('custom_field/messages.updated'), [
            'field' => $item,
        ]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->user()) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var CustomFieldService $service */
        $service = $this->container->get('service.custom_field');
        $ok = $service->delete((string)$params['public_id']);
        if (!$ok) {
            return $this->error('CUSTOM_FIELD_NOT_FOUND', $this->t('custom_field/messages.not_found'), 404);
        }
        $this->invalidateCache('custom_field');

        return $this->success('CUSTOM_FIELD_DELETED', $this->t('custom_field/messages.deleted'), []);
    }

    public function values(): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->user()) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'entity_type', $this->t('common/messages.field_required'))
            ->require($input, 'entity_public_id', $this->t('common/messages.field_required'))
            ->maxLen($input, 'entity_type', 64, $this->t('custom_field/messages.max_64'))
            ->maxLen($input, 'entity_public_id', 64, $this->t('custom_field/messages.max_64'));
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var CustomFieldService $service */
        $service = $this->container->get('service.custom_field');
        $items = $service->values((string)$input['entity_type'], (string)$input['entity_public_id']);

        return $this->success('CUSTOM_FIELD_VALUES', $this->t('custom_field/messages.values'), [
            'items' => $items,
        ]);
    }

    public function setValues(): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->user()) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'entity_type', $this->t('common/messages.field_required'))
            ->require($input, 'entity_public_id', $this->t('common/messages.field_required'))
            ->maxLen($input, 'entity_type', 64, $this->t('custom_field/messages.max_64'))
            ->maxLen($input, 'entity_public_id', 64, $this->t('custom_field/messages.max_64'));
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }
        if (!isset($input['values']) || !is_array($input['values']) || $input['values'] === []) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'values' => [$this->t('custom_field/messages.values_required')],
            ]);
        }

        /** @var CustomFieldService $service */
        $service = $this->container->get('service.custom_field');
        $result = $service->setValues(
            (string)$input['entity_type'],
            (string)$input['entity_public_id'],
            (array)$input['values']
        );
        if ($result === 'FIELD_NOT_FOUND') {
            return $this->error('CUSTOM_FIELD_NOT_FOUND', $this->t('custom_field/messages.any_field_not_found'), 404);
        }

        return $this->success('CUSTOM_FIELD_VALUES_SAVED', $this->t('custom_field/messages.values_saved'), $result);
    }

    public function listAlias(): \Api\System\Library\Http\JsonResponse { return $this->list(); }
    public function createAlias(): \Api\System\Library\Http\JsonResponse { return $this->create(); }
}
