<?php
declare(strict_types=1);

namespace Api\Controller\Export;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\ExportService;
use Api\System\Library\Validation\Validator;

final class ExportController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ExportService $service */
        $service = $this->container->get('service.export');
        $result = $service->list($this->request()->allInput(), $auth['user']);

        return $this->success('EXPORT_JOB_LIST', $this->t('export/messages.list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $validator = new Validator();
        $validator->require($input, 'type', $this->t('common/messages.field_required'))
            ->enum($input, 'type', ['projects', 'tasks'], $this->t('export/messages.invalid_type'));

        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $validator->errors());
        }

        return $this->withIdempotency(function () use ($input, $auth): \Api\System\Library\Http\JsonResponse {
            /** @var ExportService $service */
            $service = $this->container->get('service.export');
            $result = $service->create($input, $auth['user']);

            return $this->success('EXPORT_JOB_CREATED', $this->t('export/messages.created'), [
                'job' => $result['job'],
            ], 201);
        });
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ExportService $service */
        $service = $this->container->get('service.export');
        $result = $service->get((string)$params['public_id'], $auth['user']);
        if (!$result) {
            return $this->error('EXPORT_JOB_NOT_FOUND', $this->t('export/messages.not_found'), 404, [
                'export' => [$this->t('export/messages.not_found')],
            ]);
        }

        return $this->success('EXPORT_JOB_DETAIL', $this->t('export/messages.detail'), [
            'job' => $result['job'],
        ]);
    }

    public function download(array $params): array
    {
        $auth = $this->user();
        if (!$auth) {
            return ['error' => 'UNAUTHORIZED'];
        }

        /** @var ExportService $service */
        $service = $this->container->get('service.export');

        return $service->download((string)$params['public_id'], $auth['user']);
    }

    public function createAlias(): \Api\System\Library\Http\JsonResponse
    {
        return $this->create();
    }

    public function cancel(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ExportService $service */
        $service = $this->container->get('service.export');
        $result = $service->cancel((string)$params['public_id'], $auth['user']);
        if (($result['ok'] ?? false) !== true) {
            $code = (string)($result['code'] ?? 'EXPORT_JOB_CANCEL_NOT_ALLOWED');
            $status = $code === 'EXPORT_JOB_NOT_FOUND' ? 404 : 409;
            return $this->error($code, $this->t('export/messages.not_found'), $status);
        }

        return $this->success('EXPORT_JOB_CANCELLED', $this->t('export/messages.detail'), [
            'job' => $result['job'] ?? [],
        ]);
    }

    public function retry(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ExportService $service */
        $service = $this->container->get('service.export');
        $result = $service->retry((string)$params['public_id'], $auth['user']);
        if (($result['ok'] ?? false) !== true) {
            $code = (string)($result['code'] ?? 'EXPORT_JOB_RETRY_NOT_ALLOWED');
            $status = $code === 'EXPORT_JOB_NOT_FOUND' ? 404 : 409;
            return $this->error($code, $this->t('export/messages.not_found'), $status);
        }

        return $this->success('EXPORT_JOB_RETRIED', $this->t('export/messages.created'), [
            'job' => $result['job'] ?? [],
        ], 201);
    }

    public function status(array $params = []): \Api\System\Library\Http\JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? $this->request()->input('public_id', ''));
        if ($publicId === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'public_id' => [$this->t('export/messages.public_id_required')],
            ]);
        }

        return $this->get(['public_id' => $publicId]);
    }

    public function downloadAlias(array $params): array
    {
        return $this->download($params);
    }
}
