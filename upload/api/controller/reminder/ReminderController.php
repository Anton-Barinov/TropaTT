<?php
declare(strict_types=1);

namespace Api\Controller\Reminder;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\ReminderService;
use Api\System\Library\Validation\Validator;

final class ReminderController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ReminderService $service */
        $service = $this->container->get('service.reminder');
        $result = $service->list($this->request()->allInput(), $authUser['user']);

        return $this->success('REMINDER_LIST', $this->t('reminder/messages.list'), ['items' => $result['items']], meta: $result['meta']);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'remind_at', $this->t('common/messages.field_required'))
            ->date($input, 'remind_at', $this->t('common/messages.invalid_date'))
            ->enum($input, 'status', ['new', 'pending', 'done', 'cancelled'], $this->t('common/messages.invalid_value'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var ReminderService $service */
        $service = $this->container->get('service.reminder');
        $item = $service->create($input, $authUser['user']);
        if ($item === 'TASK_NOT_FOUND') {
            return $this->error('TASK_NOT_FOUND', $this->t('common/messages.task_not_found'), 404, [
                'task_public_id' => [$this->t('common/messages.task_not_found')],
            ]);
        }

        return $this->success('REMINDER_CREATED', $this->t('reminder/messages.created'), ['reminder' => $item], 201);
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ReminderService $service */
        $service = $this->container->get('service.reminder');
        $item = $service->get((string)$params['public_id'], $authUser['user']);
        if (!$item) {
            return $this->error('REMINDER_NOT_FOUND', $this->t('reminder/messages.not_found'), 404, [
                'reminder' => [$this->t('reminder/messages.not_found')],
            ]);
        }

        return $this->success('REMINDER_DETAIL', $this->t('reminder/messages.detail'), ['reminder' => $item]);
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->date($input, 'remind_at', $this->t('common/messages.invalid_date'))
            ->enum($input, 'status', ['new', 'pending', 'done', 'cancelled'], $this->t('common/messages.invalid_value'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var ReminderService $service */
        $service = $this->container->get('service.reminder');
        $item = $service->update((string)$params['public_id'], $input, $authUser['user']);
        if ($item === 'TASK_NOT_FOUND') {
            return $this->error('TASK_NOT_FOUND', $this->t('common/messages.task_not_found'), 404, [
                'task_public_id' => [$this->t('common/messages.task_not_found')],
            ]);
        }
        if (!$item) {
            return $this->error('REMINDER_NOT_FOUND', $this->t('reminder/messages.not_found'), 404, [
                'reminder' => [$this->t('reminder/messages.not_found')],
            ]);
        }

        return $this->success('REMINDER_UPDATED', $this->t('reminder/messages.updated'), ['reminder' => $item]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ReminderService $service */
        $service = $this->container->get('service.reminder');
        $ok = $service->delete((string)$params['public_id'], $authUser['user']);
        if (!$ok) {
            return $this->error('REMINDER_NOT_FOUND', $this->t('reminder/messages.not_found'), 404, [
                'reminder' => [$this->t('reminder/messages.not_found')],
            ]);
        }

        return $this->success('REMINDER_DELETED', $this->t('reminder/messages.deleted'));
    }
}
