<?php
declare(strict_types=1);

namespace Api\Controller\Workflow;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\WorkflowService;
use Api\System\Library\Validation\Validator;

final class WorkflowController extends BaseController
{
    private const TRIGGERS = [
        'task_created',
        'task_updated',
        'task_status_changed',
        'comment_added',
        'file_uploaded',
        'deadline_reached',
        'project_archived',
        'user_created',
    ];

    private const ACTIONS = [
        'assign_user',
        'change_status',
        'create_reminder',
        'send_notification',
        'create_comment',
        'create_follow_up_task',
        'call_webhook',
        'escalate_sla',
    ];

    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var WorkflowService $service */
        $service = $this->container->get('service.workflow');
        $result = $service->listRules($this->request()->allInput(), $authUser['user']);

        return $this->success('WORKFLOW_RULE_LIST', $this->t('workflow/messages.list'), [
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
        $v->require($input, 'title', $this->t('common/messages.field_required'))
            ->require($input, 'trigger_code', $this->t('common/messages.field_required'))
            ->require($input, 'action_code', $this->t('common/messages.field_required'))
            ->enum($input, 'trigger_code', self::TRIGGERS, $this->t('workflow/messages.invalid_trigger_code'))
            ->enum($input, 'action_code', self::ACTIONS, $this->t('workflow/messages.invalid_action_code'))
            ->maxLen($input, 'title', 255, $this->t('workflow/messages.max_255'));

        if (isset($input['payload']) && !is_array($input['payload'])) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'payload' => [$this->t('workflow/messages.payload_object')],
            ]);
        }
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        return $this->withIdempotency(function () use ($input, $authUser): \Api\System\Library\Http\JsonResponse {
            /** @var WorkflowService $service */
            $service = $this->container->get('service.workflow');
            $item = $service->createRule($input, $authUser['user']);

            return $this->success('WORKFLOW_RULE_CREATED', $this->t('workflow/messages.created'), [
                'rule' => $item,
            ], 201);
        });
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var WorkflowService $service */
        $service = $this->container->get('service.workflow');
        $item = $service->getRule((string)$params['public_id'], $authUser['user']);
        if (!$item) {
            return $this->error('WORKFLOW_RULE_NOT_FOUND', $this->t('workflow/messages.not_found'), 404);
        }

        return $this->success('WORKFLOW_RULE_DETAIL', $this->t('workflow/messages.detail'), [
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
        $v->enum($input, 'trigger_code', self::TRIGGERS, $this->t('workflow/messages.invalid_trigger_code'))
            ->enum($input, 'action_code', self::ACTIONS, $this->t('workflow/messages.invalid_action_code'))
            ->maxLen($input, 'title', 255, $this->t('workflow/messages.max_255'));

        if (isset($input['payload']) && !is_array($input['payload'])) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'payload' => [$this->t('workflow/messages.payload_object')],
            ]);
        }
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var WorkflowService $service */
        $service = $this->container->get('service.workflow');
        $item = $service->updateRule((string)$params['public_id'], $input, $authUser['user']);
        if (!$item) {
            return $this->error('WORKFLOW_RULE_NOT_FOUND', $this->t('workflow/messages.not_found'), 404);
        }

        return $this->success('WORKFLOW_RULE_UPDATED', $this->t('workflow/messages.updated'), [
            'rule' => $item,
        ]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var WorkflowService $service */
        $service = $this->container->get('service.workflow');
        $ok = $service->deleteRule((string)$params['public_id'], $authUser['user']);
        if (!$ok) {
            return $this->error('WORKFLOW_RULE_NOT_FOUND', $this->t('workflow/messages.not_found'), 404);
        }

        return $this->success('WORKFLOW_RULE_DELETED', $this->t('workflow/messages.deleted'), []);
    }

    public function runTest(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();

        /** @var WorkflowService $service */
        $service = $this->container->get('service.workflow');
        $run = $service->runTest((string)$params['public_id'], $input, $authUser['user']);
        if ($run === 'RULE_NOT_FOUND') {
            return $this->error('WORKFLOW_RULE_NOT_FOUND', $this->t('workflow/messages.not_found'), 404);
        }

        return $this->success('WORKFLOW_RULE_TEST_RUN', $this->t('workflow/messages.test_run'), [
            'run' => $run,
        ], 201);
    }

    public function runs(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var WorkflowService $service */
        $service = $this->container->get('service.workflow');
        $result = $service->listRuns($this->request()->allInput(), $authUser['user']);

        return $this->success('WORKFLOW_RUN_LIST', $this->t('workflow/messages.runs'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function listAlias(): \Api\System\Library\Http\JsonResponse { return $this->list(); }
    public function createAlias(): \Api\System\Library\Http\JsonResponse { return $this->create(); }
    public function updateAlias(array $params): \Api\System\Library\Http\JsonResponse { return $this->update($params); }
    public function logsAlias(): \Api\System\Library\Http\JsonResponse { return $this->runs(); }
}
