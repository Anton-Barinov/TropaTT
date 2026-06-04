<?php
declare(strict_types=1);

namespace Api\Controller\Counterparty;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\CounterpartyService;
use Throwable;

final class CounterpartyController extends BaseController
{
    private const COUNTERPARTY_TYPES = ['organization', 'individual', 'sole_proprietor', 'legal_entity'];

    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var CounterpartyService $service */
        $service = $this->container->get('service.counterparty');
        $result = $service->list($this->request()->allInput(), $authUser['user']);

        return $this->success('COUNTERPARTY_LIST', $this->t('counterparty/messages.list'), ['items' => $result['items']], meta: $result['meta']);
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var CounterpartyService $service */
        $service = $this->container->get('service.counterparty');
        $item = $service->get((string)$params['public_id'], $authUser['user']);
        if (!$item) {
            return $this->error('COUNTERPARTY_NOT_FOUND', $this->t('counterparty/messages.not_found'), 404, [
                'counterparty' => [$this->t('counterparty/messages.not_found')],
            ]);
        }

        return $this->success('COUNTERPARTY_DETAIL', $this->t('counterparty/messages.detail'), ['counterparty' => $item]);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $errors = $this->validateCounterpartyPayload($input, true);
        if ($errors !== []) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $errors);
        }

        /** @var CounterpartyService $service */
        $service = $this->container->get('service.counterparty');
        try {
            $item = $service->create($input, $authUser['user']);
        } catch (Throwable $e) {
            throw $e;
        }

        return $this->success('COUNTERPARTY_CREATED', $this->t('counterparty/messages.created'), ['counterparty' => $item], 201);
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var CounterpartyService $service */
        $service = $this->container->get('service.counterparty');
        $current = $service->get((string)$params['public_id'], $authUser['user']);
        if (!$current) {
            return $this->error('COUNTERPARTY_NOT_FOUND', $this->t('counterparty/messages.not_found'), 404, [
                'counterparty' => [$this->t('counterparty/messages.not_found')],
            ]);
        }

        $input = $this->request()->allInput();
        $errors = $this->validateCounterpartyPayload($input, false, $current);
        if ($errors !== []) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $errors);
        }

        try {
            $item = $service->update((string)$params['public_id'], $input, $authUser['user']);
        } catch (Throwable $e) {
            if ($e->getMessage() === 'COUNTERPARTY_NOT_FOUND') {
                return $this->error('COUNTERPARTY_NOT_FOUND', $this->t('counterparty/messages.not_found'), 404, [
                    'counterparty' => [$this->t('counterparty/messages.not_found')],
                ]);
            }
            throw $e;
        }

        return $this->success('COUNTERPARTY_UPDATED', $this->t('counterparty/messages.updated'), ['counterparty' => $item]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var CounterpartyService $service */
        $service = $this->container->get('service.counterparty');
        try {
            $service->delete((string)$params['public_id'], $authUser['user']);
        } catch (Throwable $e) {
            if ($e->getMessage() === 'COUNTERPARTY_NOT_FOUND') {
                return $this->error('COUNTERPARTY_NOT_FOUND', $this->t('counterparty/messages.not_found'), 404, [
                    'counterparty' => [$this->t('counterparty/messages.not_found')],
                ]);
            }
            throw $e;
        }

        return $this->success('COUNTERPARTY_DELETED', $this->t('counterparty/messages.deleted'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function validateCounterpartyPayload(array $input, bool $isCreate, ?array $current = null): array
    {
        $errors = [];

        if ($isCreate && empty($input['title'])) {
            $errors['title'] = [$this->t('counterparty/messages.title_required')];
        }

        if (!empty($input['counterparty_type']) && !in_array($input['counterparty_type'], self::COUNTERPARTY_TYPES, true)) {
            $errors['counterparty_type'] = [$this->t('counterparty/messages.invalid_type')];
        }

        if (!empty($input['tax_inn'])) {
            $inn = preg_replace('/\D+/', '', (string)$input['tax_inn']);
            if (strlen($inn) !== 10 && strlen($inn) !== 12) {
                $errors['tax_inn'] = [$this->t('counterparty/messages.invalid_inn')];
            }
        }

        if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = [$this->t('counterparty/messages.invalid_email')];
        }

        return $errors;
    }
}
