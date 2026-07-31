<?php
declare(strict_types=1);

namespace Api\Controller\Contact;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\ContactService;
use Api\System\Library\Validation\Validator;
use Throwable;

final class ContactController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ContactService $service */
        $service = $this->container->get('service.contact');
        $result = $service->list($this->request()->allInput(), $authUser['user']);

        return $this->success('CONTACT_LIST', $this->t('contact/messages.list'), ['items' => $result['items']], meta: $result['meta']);
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ContactService $service */
        $service = $this->container->get('service.contact');
        $item = $service->get((string)$params['public_id'], $authUser['user']);
        if (!$item) {
            return $this->error('CONTACT_NOT_FOUND', $this->t('contact/messages.not_found'), 404, [
                'contact' => [$this->t('contact/messages.not_found')],
            ]);
        }

        return $this->success('CONTACT_DETAIL', $this->t('contact/messages.detail'), ['contact' => $item]);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'full_name', $this->t('common/messages.field_required'))
            ->maxLen($input, 'full_name', 255, $this->t('contact/messages.max_255'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var ContactService $service */
        $service = $this->container->get('service.contact');
        try {
            $item = $service->create($input, $authUser['user']);
        } catch (Throwable $e) {
            if ($e->getMessage() === 'COMPANY_NOT_FOUND') {
                return $this->error('COMPANY_NOT_FOUND', $this->t('contact/messages.company_not_found'), 422, [
                    'company_public_id' => [$this->t('contact/messages.company_not_found')],
                ]);
            }
            if ($e->getMessage() === 'CLIENT_NOT_FOUND') {
                return $this->error('CLIENT_NOT_FOUND', $this->t('contact/messages.client_not_found'), 422, [
                    'client_public_id' => [$this->t('contact/messages.client_not_found')],
                ]);
            }
            if ($e->getMessage() === 'COUNTERPARTY_NOT_FOUND') {
                return $this->error('COUNTERPARTY_NOT_FOUND', $this->t('contact/messages.counterparty_not_found'), 422, [
                    'counterparty_public_id' => [$this->t('contact/messages.counterparty_not_found')],
                ]);
            }
            throw $e;
        }

        return $this->success('CONTACT_CREATED', $this->t('contact/messages.created'), ['contact' => $item], 201);
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ContactService $service */
        $service = $this->container->get('service.contact');
        try {
            $item = $service->update((string)$params['public_id'], $this->request()->allInput(), $authUser['user']);
        } catch (Throwable $e) {
            if ($e->getMessage() === 'COMPANY_NOT_FOUND') {
                return $this->error('COMPANY_NOT_FOUND', $this->t('contact/messages.company_not_found'), 422, [
                    'company_public_id' => [$this->t('contact/messages.company_not_found')],
                ]);
            }
            if ($e->getMessage() === 'CLIENT_NOT_FOUND') {
                return $this->error('CLIENT_NOT_FOUND', $this->t('contact/messages.client_not_found'), 422, [
                    'client_public_id' => [$this->t('contact/messages.client_not_found')],
                ]);
            }
            if ($e->getMessage() === 'COUNTERPARTY_NOT_FOUND') {
                return $this->error('COUNTERPARTY_NOT_FOUND', $this->t('contact/messages.counterparty_not_found'), 422, [
                    'counterparty_public_id' => [$this->t('contact/messages.counterparty_not_found')],
                ]);
            }
            throw $e;
        }
        if (!$item) {
            return $this->error('CONTACT_NOT_FOUND', $this->t('contact/messages.not_found'), 404, [
                'contact' => [$this->t('contact/messages.not_found')],
            ]);
        }

        return $this->success('CONTACT_UPDATED', $this->t('contact/messages.updated'), ['contact' => $item]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ContactService $service */
        $service = $this->container->get('service.contact');
        $ok = $service->delete((string)$params['public_id'], $authUser['user']);
        if (!$ok) {
            return $this->error('CONTACT_NOT_FOUND', $this->t('contact/messages.not_found'), 404, [
                'contact' => [$this->t('contact/messages.not_found')],
            ]);
        }

        return $this->success('CONTACT_DELETED', $this->t('contact/messages.deleted'));
    }
}
