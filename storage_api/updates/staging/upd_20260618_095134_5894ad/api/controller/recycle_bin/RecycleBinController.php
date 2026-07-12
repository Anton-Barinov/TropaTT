<?php
declare(strict_types=1);

namespace Api\Controller\Recycle_bin;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\RecycleBinService;

final class RecycleBinController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var RecycleBinService $service */
        $service = $this->container->get('service.recycle_bin');
        $result = $service->list($this->request()->allInput());

        return $this->success('RECYCLE_BIN_LIST', $this->t('recycle_bin/messages.list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function restore(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var RecycleBinService $service */
        $service = $this->container->get('service.recycle_bin');
        $result = $service->restore((string)$params['public_id'], $auth['user']);
        if (!(bool)($result['ok'] ?? false)) {
            $status = match ((string)($result['code'] ?? '')) {
                'RECYCLE_BIN_ITEM_NOT_FOUND', 'RECYCLE_BIN_ENTITY_NOT_FOUND' => 404,
                'RECYCLE_BIN_ALREADY_RESTORED' => 409,
                'RECYCLE_BIN_ENTITY_UNSUPPORTED' => 422,
                default => 400,
            };

            return $this->error((string)$result['code'], $this->t('recycle_bin/messages.restore_failed'), $status, [
                'recycle_bin' => [(string)$result['code']],
            ]);
        }

        return $this->success('RECYCLE_BIN_RESTORED', $this->t('recycle_bin/messages.restored'), [
            'item' => $result['item'],
        ]);
    }

    public function purge(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var RecycleBinService $service */
        $service = $this->container->get('service.recycle_bin');
        $result = $service->purge((string)$params['public_id'], $auth['user']);
        if (!(bool)($result['ok'] ?? false)) {
            $status = match ((string)($result['code'] ?? '')) {
                'RECYCLE_BIN_ITEM_NOT_FOUND', 'RECYCLE_BIN_ENTITY_NOT_FOUND' => 404,
                'RECYCLE_BIN_ALREADY_RESTORED' => 409,
                'RECYCLE_BIN_ENTITY_UNSUPPORTED' => 422,
                default => 400,
            };

            return $this->error((string)$result['code'], $this->t('recycle_bin/messages.purge_failed'), $status, [
                'recycle_bin' => [(string)$result['code']],
            ]);
        }

        return $this->success('RECYCLE_BIN_PURGED', $this->t('recycle_bin/messages.purged'), [
            'item' => $result['item'],
        ]);
    }

    public function listAlias(): \Api\System\Library\Http\JsonResponse
    {
        return $this->list();
    }

    public function restoreAlias(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->restore($params);
    }

    public function purgeAlias(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->purge($params);
    }
}
