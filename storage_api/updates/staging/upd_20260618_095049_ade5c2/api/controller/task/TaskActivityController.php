<?php
declare(strict_types=1);

namespace Api\Controller\Task;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\TaskActivityService;

final class TaskActivityController extends BaseController
{
    public function list(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $taskPublicId = (string)($params['public_id'] ?? '');
        if ($taskPublicId === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'public_id' => [$this->t('common/messages.field_required')],
            ]);
        }

        /** @var TaskActivityService $service */
        $service = $this->container->get('service.task_activity');

        $filters = $this->request()->allInput();
        // Remove route param
        unset($filters['route']);

        $result = $service->list($taskPublicId, $filters, $authUser['user']);

        if ($result === 'TASK_NOT_FOUND') {
            return $this->error('TASK_NOT_FOUND', $this->t('common/messages.task_not_found'), 404, [
                'task' => [$this->t('common/messages.task_not_found')],
            ]);
        }

        return $this->success('TASK_ACTIVITY_LIST', $this->t('task/messages.activity_list', 'Task activity'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }
}
