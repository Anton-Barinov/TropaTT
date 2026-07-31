<?php
declare(strict_types=1);

namespace Api\Controller\Recurring;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\RecurringService;
use Api\System\Library\Validation\Validator;

final class RecurringController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var RecurringService $service */
        $service = $this->container->get('service.recurring');
        $result = $service->list($this->request()->allInput());

        return $this->success('RECURRING_LIST', $this->t('recurring/messages.list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'entity_type', $this->t('common/messages.field_required'))
            ->require($input, 'entity_public_id', $this->t('common/messages.field_required'))
            ->require($input, 'rrule', $this->t('common/messages.field_required'))
            ->maxLen($input, 'title', 255, $this->t('recurring/messages.max_255'))
            ->enum($input, 'entity_type', ['task', 'project', 'reminder', 'calendar_event'], $this->t('recurring/messages.invalid_entity_type'))
            ->maxLen($input, 'entity_public_id', 64, $this->t('recurring/messages.max_64'))
            ->maxLen($input, 'rrule', 1000, $this->t('recurring/messages.max_1000'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var RecurringService $service */
        $service = $this->container->get('service.recurring');
        if (!$service->isValidRrule((string)($input['rrule'] ?? ''))) {
            return $this->error('INVALID_RRULE', $this->t('common/messages.validation_error'), 422, [
                'rrule' => [$this->t('common/messages.invalid_value')],
            ]);
        }
        $item = $service->create($input);

        return $this->success('RECURRING_CREATED', $this->t('recurring/messages.created'), [
            'rule' => $item,
        ], 201);
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var RecurringService $service */
        $service = $this->container->get('service.recurring');
        $item = $service->get((string)$params['public_id']);
        if (!$item) {
            return $this->error('RECURRING_NOT_FOUND', $this->t('recurring/messages.not_found'), 404);
        }

        return $this->success('RECURRING_DETAIL', $this->t('recurring/messages.detail'), [
            'rule' => $item,
        ]);
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->maxLen($input, 'title', 255, $this->t('recurring/messages.max_255'))
            ->enum($input, 'entity_type', ['task', 'project', 'reminder', 'calendar_event'], $this->t('recurring/messages.invalid_entity_type'))
            ->maxLen($input, 'entity_public_id', 64, $this->t('recurring/messages.max_64'))
            ->maxLen($input, 'rrule', 1000, $this->t('recurring/messages.max_1000'));
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var RecurringService $service */
        $service = $this->container->get('service.recurring');
        if (array_key_exists('rrule', $input) && !$service->isValidRrule((string)($input['rrule'] ?? ''))) {
            return $this->error('INVALID_RRULE', $this->t('common/messages.validation_error'), 422, [
                'rrule' => [$this->t('common/messages.invalid_value')],
            ]);
        }
        $item = $service->update((string)$params['public_id'], $input);
        if (!$item) {
            return $this->error('RECURRING_NOT_FOUND', $this->t('recurring/messages.not_found'), 404);
        }

        return $this->success('RECURRING_UPDATED', $this->t('recurring/messages.updated'), [
            'rule' => $item,
        ]);
    }

    public function pause(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->setState((string)$params['public_id'], false);
    }

    public function resume(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->setState((string)$params['public_id'], true);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var RecurringService $service */
        $service = $this->container->get('service.recurring');
        $ok = $service->delete((string)$params['public_id']);
        if (!$ok) {
            return $this->error('RECURRING_NOT_FOUND', $this->t('recurring/messages.not_found'), 404);
        }

        return $this->success('RECURRING_DELETED', $this->t('recurring/messages.deleted'), []);
    }

    public function listAlias(): \Api\System\Library\Http\JsonResponse
    {
        return $this->list();
    }

    public function createAlias(): \Api\System\Library\Http\JsonResponse
    {
        return $this->create();
    }

    private function setState(string $publicId, bool $active): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var RecurringService $service */
        $service = $this->container->get('service.recurring');
        $item = $active ? $service->resume($publicId) : $service->pause($publicId);
        if (!$item) {
            return $this->error('RECURRING_NOT_FOUND', $this->t('recurring/messages.not_found'), 404);
        }

        return $this->success(
            $active ? 'RECURRING_RESUMED' : 'RECURRING_PAUSED',
            $active
                ? $this->t('recurring/messages.resumed')
                : $this->t('recurring/messages.paused'),
            ['rule' => $item]
        );
    }
}
