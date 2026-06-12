<?php
declare(strict_types=1);

namespace Api\Controller\Notification;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\NotificationService;
use Api\System\Library\Service\NotificationPushService;
use Api\System\Library\Service\ReminderService;
use Api\System\Library\Validation\Validator;

final class NotificationController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var NotificationService $service */
        $service = $this->container->get('service.notification');
        $result = $service->list($this->request()->allInput(), $authUser['user']);

        return $this->success('NOTIFICATION_LIST', $this->t('notification/messages.list'), ['items' => $result['items']], meta: $result['meta']);
    }

    public function counters(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var NotificationService $service */
        $service = $this->container->get('service.notification');
        /** @var ReminderService $reminders */
        $reminders = $this->container->get('service.reminder');

        $reminders->dispatchDueNotificationsForUser($authUser['user'], gmdate('Y-m-d H:i:s'));
        $service->dispatchOverdueSignalsForUser((int)($authUser['user']['id'] ?? 0), $authUser['user']);
        $counters = $service->counters($authUser['user']);
        $counters['reminders_due'] = $reminders->pendingDueCount($authUser['user'], gmdate('Y-m-d H:i:s'));

        return $this->success('NOTIFICATION_COUNTERS', $this->t('notification/messages.counters'), ['counters' => $counters]);
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
            ->maxLen($input, 'title', 255, $this->t('notification/messages.max_255'))
            ->maxLen($input, 'category', 64, $this->t('notification/messages.max_64'))
            ->maxLen($input, 'body', 8000, $this->t('notification/messages.max_8000'))
            ->maxLen($input, 'entity_type', 64, $this->t('notification/messages.max_64'))
            ->maxLen($input, 'entity_public_id', 64, $this->t('notification/messages.max_64'))
            ->maxLen($input, 'action_code', 64, $this->t('notification/messages.max_64'))
            ->maxLen($input, 'actor_public_id', 64, $this->t('notification/messages.max_64'))
            ->maxLen($input, 'actor_name', 255, $this->t('notification/messages.max_255'));

        if (isset($input['link'])) {
            $v->maxLen($input, 'link', 1024, $this->t('notification/messages.max_8000'));
        }

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var NotificationService $service */
        $service = $this->container->get('service.notification');
        $item = $service->create($input, $authUser['user']);

        return $this->success('NOTIFICATION_CREATED', $this->t('notification/messages.created'), ['notification' => $item], 201);
    }

    public function markRead(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var NotificationService $service */
        $service = $this->container->get('service.notification');
        $item = $service->markRead((string)$params['public_id'], $authUser['user']);
        if (!$item) {
            return $this->error('NOTIFICATION_NOT_FOUND', $this->t('notification/messages.not_found'), 404, [
                'notification' => [$this->t('notification/messages.not_found')],
            ]);
        }

        return $this->success('NOTIFICATION_MARKED_READ', $this->t('notification/messages.marked_read'), ['notification' => $item]);
    }

    public function markUnread(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var NotificationService $service */
        $service = $this->container->get('service.notification');
        $item = $service->markUnread((string)$params['public_id'], $authUser['user']);
        if (!$item) {
            return $this->error('NOTIFICATION_NOT_FOUND', $this->t('notification/messages.not_found'), 404, [
                'notification' => [$this->t('notification/messages.not_found')],
            ]);
        }

        return $this->success('NOTIFICATION_MARKED_UNREAD', $this->t('notification/messages.marked_unread'), ['notification' => $item]);
    }

    public function markAllRead(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $category = trim((string)($this->request()->allInput()['category'] ?? ''));
        if ($category === '') {
            $category = null;
        }

        /** @var NotificationService $service */
        $service = $this->container->get('service.notification');
        $updated = $service->markAllRead($authUser['user'], $category);

        return $this->success('NOTIFICATION_MARK_ALL_READ', $this->t('notification/messages.mark_all_read'), [
            'updated' => $updated,
        ]);
    }

    public function listPushSubscriptions(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var NotificationPushService $service */
        $service = $this->container->get('service.notification_push');
        $result = $service->list($this->request()->allInput(), $authUser['user']);

        return $this->success('NOTIFICATION_PUSH_SUBSCRIPTIONS_LIST', $this->t('notification/messages.push_subscriptions_list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function createPushSubscription(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'endpoint', $this->t('common/messages.field_required'))
            ->require($input, 'p256dh', $this->t('common/messages.field_required'))
            ->require($input, 'auth', $this->t('common/messages.field_required'))
            ->maxLen($input, 'endpoint', 4000, $this->t('notification/messages.max_8000'))
            ->maxLen($input, 'p256dh', 1024, $this->t('notification/messages.max_8000'))
            ->maxLen($input, 'auth', 1024, $this->t('notification/messages.max_8000'))
            ->maxLen($input, 'device_label', 255, $this->t('notification/messages.max_255'))
            ->maxLen($input, 'user_agent', 2048, $this->t('notification/messages.max_8000'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var NotificationPushService $service */
        $service = $this->container->get('service.notification_push');
        $item = $service->upsert($input, $authUser['user']);
        if (!$item) {
            return $this->error('NOTIFICATION_PUSH_SUBSCRIPTION_INVALID', $this->t('notification/messages.push_subscription_invalid'), 422, [
                'subscription' => ['invalid_payload'],
            ]);
        }

        return $this->success('NOTIFICATION_PUSH_SUBSCRIPTION_SAVED', $this->t('notification/messages.push_subscription_saved'), [
            'subscription' => $item,
        ], 201);
    }

    public function deletePushSubscription(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var NotificationPushService $service */
        $service = $this->container->get('service.notification_push');
        $deleted = $service->delete((string)($params['public_id'] ?? ''), $authUser['user']);
        if (!$deleted) {
            return $this->error('NOTIFICATION_PUSH_SUBSCRIPTION_NOT_FOUND', $this->t('notification/messages.push_subscription_not_found'), 404, [
                'subscription' => ['not_found'],
            ]);
        }

        return $this->success('NOTIFICATION_PUSH_SUBSCRIPTION_DELETED', $this->t('notification/messages.push_subscription_deleted'));
    }

    public function pushTest(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var NotificationPushService $service */
        $service = $this->container->get('service.notification_push');
        $result = $service->sendTestToUser((int)($authUser['user']['id'] ?? 0), $authUser['user']);

        return $this->success('NOTIFICATION_PUSH_TEST', $this->t('notification/messages.push_test_prepared'), [
            'push' => [
                'title' => $this->t('notification/messages.test_push_title'),
                'body' => $this->t('notification/messages.test_push_body'),
                'link' => 'index.php?route=notifications',
                'timestamp' => gmdate('c'),
            ],
            'dispatch' => $result,
        ]);
    }
}
