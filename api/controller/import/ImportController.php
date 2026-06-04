<?php
declare(strict_types=1);

namespace Api\Controller\Import;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\ImportService;
use Api\System\Library\Validation\Validator;

final class ImportController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ImportService $service */
        $service = $this->container->get('service.import');
        $result = $service->list($this->request()->allInput(), $auth['user']);

        return $this->success('IMPORT_JOB_LIST', $this->t('import/messages.list'), [
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
            ->enum($input, 'type', ['projects', 'tasks'], $this->t('import/messages.invalid_type'))
            ->enum($input, 'format', ['csv', 'json_rows'], $this->t('import/messages.invalid_format'));

        $hasRows = is_array($input['rows'] ?? null) && (array)$input['rows'] !== [];
        $hasContent = trim((string)($input['content_base64'] ?? '')) !== '';
        if (!$hasRows && !$hasContent) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'content' => [$this->t('import/messages.content_required')],
            ]);
        }

        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $validator->errors());
        }

        return $this->withIdempotency(function () use ($input, $auth): \Api\System\Library\Http\JsonResponse {
            /** @var ImportService $service */
            $service = $this->container->get('service.import');
            $result = $service->create($input, $auth['user']);

            return $this->success('IMPORT_JOB_CREATED', $this->t('import/messages.created'), [
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

        /** @var ImportService $service */
        $service = $this->container->get('service.import');
        $result = $service->get((string)$params['public_id'], $auth['user']);
        if (!$result) {
            return $this->error('IMPORT_JOB_NOT_FOUND', $this->t('import/messages.not_found'), 404, [
                'import' => [$this->t('import/messages.not_found')],
            ]);
        }

        return $this->success('IMPORT_JOB_DETAIL', $this->t('import/messages.detail'), [
            'job' => $result['job'],
        ]);
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

        /** @var ImportService $service */
        $service = $this->container->get('service.import');
        $result = $service->cancel((string)$params['public_id'], $auth['user']);
        if (($result['ok'] ?? false) !== true) {
            $code = (string)($result['code'] ?? 'IMPORT_JOB_CANCEL_NOT_ALLOWED');
            $status = $code === 'IMPORT_JOB_NOT_FOUND' ? 404 : 409;
            return $this->error($code, $this->t('import/messages.not_found'), $status);
        }

        return $this->success('IMPORT_JOB_CANCELLED', $this->t('import/messages.updated'), [
            'job' => $result['job'] ?? [],
        ]);
    }

    public function retry(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ImportService $service */
        $service = $this->container->get('service.import');
        $result = $service->retry((string)$params['public_id'], $auth['user']);
        if (($result['ok'] ?? false) !== true) {
            $code = (string)($result['code'] ?? 'IMPORT_JOB_RETRY_NOT_ALLOWED');
            $status = $code === 'IMPORT_JOB_NOT_FOUND' ? 404 : 409;
            return $this->error($code, $this->t('import/messages.not_found'), $status);
        }

        return $this->success('IMPORT_JOB_RETRIED', $this->t('import/messages.created'), [
            'job' => $result['job'] ?? [],
        ], 201);
    }

    public function status(array $params = []): \Api\System\Library\Http\JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? $this->request()->input('public_id', ''));
        if ($publicId === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'public_id' => [$this->t('import/messages.public_id_required')],
            ]);
        }

        return $this->get(['public_id' => $publicId]);
    }
}
