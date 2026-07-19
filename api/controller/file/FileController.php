<?php
declare(strict_types=1);

namespace Api\Controller\File;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\FileService;
use Throwable;

final class FileController extends BaseController
{
    public function listByTask(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var FileService $service */
        $service = $this->container->get('service.file');
        $items = $service->listByEntity('task', (string)$params['public_id'], $authUser['user']);
        if ($items === null) {
            return $this->error('TASK_NOT_FOUND', $this->t('common/messages.task_not_found'), 404, [
                'task' => [$this->t('common/messages.task_not_found')],
            ]);
        }

        return $this->success('FILE_LIST', $this->t('file/messages.list'), [
            'items' => $items,
        ]);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var FileService $service */
        $service = $this->container->get('service.file');

        try {
            $item = $service->create($this->request()->allInput(), $this->request()->files, (int)$authUser['user']['id'], $authUser['user']);

            return $this->success('FILE_CREATED', $this->t('file/messages.created'), [
                'file' => $item,
            ], 201);
        } catch (Throwable $e) {
            if ($e->getMessage() === 'ENTITY_ACCESS_DENIED') {
                return $this->error('FORBIDDEN', $this->t('file/messages.linked_entity_forbidden'), 403, [
                    'file' => [$this->t('file/messages.linked_entity_forbidden')],
                ]);
            }

            error_log('[FileController::create] ' . $e->getMessage());
            return $this->error('FILE_UPLOAD_ERROR', $this->t('file/messages.upload_error'), 422, [
                'file' => ['File upload failed. Check server logs for details.'],
            ]);
        }
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var FileService $service */
        $service = $this->container->get('service.file');
        $file = $service->get((string)$params['public_id'], $authUser['user']);

        if (!$file || (int)($file['is_deleted'] ?? 0) === 1) {
            return $this->error('FILE_NOT_FOUND', $this->t('file/messages.not_found'), 404, [
                'file' => [$this->t('file/messages.not_found')],
            ]);
        }

        return $this->success('FILE_DETAIL', $this->t('file/messages.detail'), [
            'file' => $file,
        ]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var FileService $service */
        $service = $this->container->get('service.file');
        $ok = $service->delete((string)$params['public_id'], $authUser['user']);

        if (!$ok) {
            return $this->error('FILE_NOT_FOUND', $this->t('file/messages.not_found'), 404, [
                'file' => [$this->t('file/messages.not_found')],
            ]);
        }

        return $this->success('FILE_DELETED', $this->t('file/messages.deleted'));
    }

    public function download(array $params): array
    {
        $authUser = $this->user();
        if (!$authUser) {
            return ['error' => 'UNAUTHORIZED'];
        }

        /** @var FileService $service */
        $service = $this->container->get('service.file');
        $result = $service->canDownloadInternal((string)$params['public_id'], $authUser['user']);
        if (!(bool)($result['ok'] ?? false)) {
            return ['error' => (string)($result['error'] ?? 'FILE_NOT_FOUND')];
        }

        return $result;
    }
}
