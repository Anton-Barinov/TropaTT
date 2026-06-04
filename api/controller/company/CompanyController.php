<?php
declare(strict_types=1);

namespace Api\Controller\Company;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\CompanyService;
use Api\System\Library\Validation\Validator;

final class CompanyController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var CompanyService $service */
        $service = $this->container->get('service.company');
        $result = $service->list($this->request()->allInput(), $authUser['user']);

        return $this->success('COMPANY_LIST', $this->t('company/messages.list'), ['items' => $result['items']], meta: $result['meta']);
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var CompanyService $service */
        $service = $this->container->get('service.company');
        $item = $service->get((string)$params['public_id'], $authUser['user']);
        if (!$item) {
            return $this->error('COMPANY_NOT_FOUND', $this->t('company/messages.not_found'), 404, [
                'company' => [$this->t('company/messages.not_found')],
            ]);
        }

        return $this->success('COMPANY_DETAIL', $this->t('company/messages.detail'), ['company' => $item]);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'title', $this->t('common/messages.field_required'))
            ->maxLen($input, 'title', 255, $this->t('company/messages.max_255'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var CompanyService $service */
        $service = $this->container->get('service.company');
        $item = $service->create($input, $authUser['user']);

        return $this->success('COMPANY_CREATED', $this->t('company/messages.created'), ['company' => $item], 201);
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var CompanyService $service */
        $service = $this->container->get('service.company');
        $item = $service->update((string)$params['public_id'], $this->request()->allInput(), $authUser['user']);
        if (!$item) {
            return $this->error('COMPANY_NOT_FOUND', $this->t('company/messages.not_found'), 404, [
                'company' => [$this->t('company/messages.not_found')],
            ]);
        }

        return $this->success('COMPANY_UPDATED', $this->t('company/messages.updated'), ['company' => $item]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var CompanyService $service */
        $service = $this->container->get('service.company');
        $ok = $service->delete((string)$params['public_id'], $authUser['user']);
        if (!$ok) {
            return $this->error('COMPANY_NOT_FOUND', $this->t('company/messages.not_found'), 404, [
                'company' => [$this->t('company/messages.not_found')],
            ]);
        }

        return $this->success('COMPANY_DELETED', $this->t('company/messages.deleted'));
    }
}
