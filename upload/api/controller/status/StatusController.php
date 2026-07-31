<?php
declare(strict_types=1);

namespace Api\Controller\Status;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\StatusService;
use Api\System\Library\Validation\Validator;

final class StatusController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $cache = $this->cacheApi();
        if ($cache !== null) {
            $input = $this->request()->allInput();
            ksort($input);
            $cacheKey = 'list:' . $this->cacheUserId() . ':' . hash('sha256', json_encode($input));
            $result = $cache->remember('status', $cacheKey, 60, function () use ($input) {
                /** @var StatusService $service */
                $service = $this->container->get('service.status');
                return $service->list($input);
            });
        } else {
            /** @var StatusService $service */
            $service = $this->container->get('service.status');
            $result = $service->list($this->request()->allInput());
        }

        return $this->success('STATUS_LIST', $this->t('status/messages.list'), ['items' => $result['items']], meta: $result['meta']);
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        /** @var StatusService $service */
        $service = $this->container->get('service.status');
        $item = $service->get((string)$params['public_id']);
        if (!$item) {
            return $this->error('STATUS_NOT_FOUND', $this->t('status/messages.not_found'), 404, [
                'status' => [$this->t('status/messages.not_found')],
            ]);
        }

        return $this->success('STATUS_DETAIL', $this->t('status/messages.detail'), ['status' => $item]);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'scope', $this->t('common/messages.field_required'))
            ->require($input, 'code', $this->t('common/messages.field_required'))
            ->require($input, 'title', $this->t('common/messages.field_required'))
            ->maxLen($input, 'scope', 64, $this->t('status/messages.max_64'))
            ->maxLen($input, 'code', 64, $this->t('status/messages.max_64'))
            ->maxLen($input, 'title', 255, $this->t('status/messages.max_255'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var StatusService $service */
        $service = $this->container->get('service.status');
        $item = $service->create($input);
        if (is_string($item) && $item === 'STATUS_CODE_EXISTS') {
            return $this->error('STATUS_CODE_EXISTS', $this->t('status/messages.code_exists'), 409, [
                'code' => [$this->t('status/messages.code_exists_scope')],
            ]);
        }

        $this->invalidateCache('status');

        return $this->success('STATUS_CREATED', $this->t('status/messages.created'), ['status' => $item], 201);
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $input = $this->request()->allInput();
        $v = new Validator();
        $v->maxLen($input, 'scope', 64, $this->t('status/messages.max_64'))
            ->maxLen($input, 'code', 64, $this->t('status/messages.max_64'))
            ->maxLen($input, 'title', 255, $this->t('status/messages.max_255'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var StatusService $service */
        $service = $this->container->get('service.status');
        $item = $service->update((string)$params['public_id'], $input);
        if ($item === null) {
            return $this->error('STATUS_NOT_FOUND', $this->t('status/messages.not_found'), 404, [
                'status' => [$this->t('status/messages.not_found')],
            ]);
        }
        if (is_string($item) && $item === 'STATUS_CODE_EXISTS') {
            return $this->error('STATUS_CODE_EXISTS', $this->t('status/messages.code_exists'), 409, [
                'code' => [$this->t('status/messages.code_exists_scope')],
            ]);
        }

        $this->invalidateCache('status');

        return $this->success('STATUS_UPDATED', $this->t('status/messages.updated'), ['status' => $item]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $input = $this->request()->allInput();
        $remapToPublicId = isset($input['remap_to_public_id']) ? trim((string)$input['remap_to_public_id']) : null;
        if ($remapToPublicId === '') {
            $remapToPublicId = null;
        }

        /** @var StatusService $service */
        $service = $this->container->get('service.status');
        $result = $service->delete((string)$params['public_id'], $remapToPublicId);
        if (!(bool)($result['ok'] ?? false)) {
            $code = (string)($result['code'] ?? 'STATUS_NOT_FOUND');
            $status = match ($code) {
                'STATUS_NOT_FOUND', 'REMAP_STATUS_NOT_FOUND' => 404,
                'STATUS_IN_USE', 'REMAP_STATUS_SAME', 'REMAP_SCOPE_MISMATCH' => 409,
                default => 422,
            };

            $errors = ['status' => [$code]];
            if (isset($result['usage_count'])) {
                $errors['usage_count'] = [(string)$result['usage_count']];
            }

            return $this->error($code, $this->t('status/messages.delete_failed'), $status, $errors);
        }

        $this->invalidateCache('status');

        return $this->success('STATUS_DELETED', $this->t('status/messages.deleted'), [
            'remapped' => (bool)($result['remapped'] ?? false),
            'usage_count' => (int)($result['usage_count'] ?? 0),
        ]);
    }

    public function remapDelete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $input = $this->request()->allInput();
        if (!isset($input['remap_to_public_id']) || trim((string)$input['remap_to_public_id']) === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'remap_to_public_id' => [$this->t('status/messages.remap_required')],
            ]);
        }

        return $this->delete($params);
    }
}
