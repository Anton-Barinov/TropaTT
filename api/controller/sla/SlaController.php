<?php
declare(strict_types=1);

namespace Api\Controller\Sla;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\SlaService;
use Api\System\Library\Validation\Validator;

final class SlaController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->user()) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var SlaService $service */
        $service = $this->container->get('service.sla');
        $result = $service->list($this->request()->allInput());

        return $this->success('SLA_POLICY_LIST', $this->t('sla/messages.list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->user()) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $validation = $this->validateInput($input, true);
        if ($validation !== null) {
            return $validation;
        }

        return $this->withIdempotency(function () use ($input): \Api\System\Library\Http\JsonResponse {
            /** @var SlaService $service */
            $service = $this->container->get('service.sla');
            $item = $service->create($input);

            return $this->success('SLA_POLICY_CREATED', $this->t('sla/messages.created'), [
                'policy' => $item,
            ], 201);
        });
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->user()) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var SlaService $service */
        $service = $this->container->get('service.sla');
        $item = $service->get((string)$params['public_id']);
        if (!$item) {
            return $this->error('SLA_POLICY_NOT_FOUND', $this->t('sla/messages.not_found'), 404);
        }

        return $this->success('SLA_POLICY_DETAIL', $this->t('sla/messages.detail'), [
            'policy' => $item,
        ]);
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->user()) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $validation = $this->validateInput($input, false);
        if ($validation !== null) {
            return $validation;
        }

        /** @var SlaService $service */
        $service = $this->container->get('service.sla');
        $item = $service->update((string)$params['public_id'], $input);
        if (!$item) {
            return $this->error('SLA_POLICY_NOT_FOUND', $this->t('sla/messages.not_found'), 404);
        }

        return $this->success('SLA_POLICY_UPDATED', $this->t('sla/messages.updated'), [
            'policy' => $item,
        ]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->user()) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var SlaService $service */
        $service = $this->container->get('service.sla');
        $ok = $service->delete((string)$params['public_id']);
        if (!$ok) {
            return $this->error('SLA_POLICY_NOT_FOUND', $this->t('sla/messages.not_found'), 404);
        }

        return $this->success('SLA_POLICY_DELETED', $this->t('sla/messages.deleted'), []);
    }

    public function report(): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->user()) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var SlaService $service */
        $service = $this->container->get('service.sla');

        return $this->success('SLA_REPORT', $this->t('sla/messages.report'), [
            'report' => $service->report(),
        ]);
    }

    public function listAlias(): \Api\System\Library\Http\JsonResponse { return $this->list(); }
    public function createAlias(): \Api\System\Library\Http\JsonResponse { return $this->create(); }
    public function updateAlias(array $params): \Api\System\Library\Http\JsonResponse { return $this->update($params); }
    public function reportAlias(): \Api\System\Library\Http\JsonResponse { return $this->report(); }

    private function validateInput(array $input, bool $strict): ?\Api\System\Library\Http\JsonResponse
    {
        $v = new Validator();
        if ($strict) {
            $v->require($input, 'title', $this->t('common/messages.field_required'))
                ->require($input, 'response_minutes', $this->t('common/messages.field_required'))
                ->require($input, 'resolve_minutes', $this->t('common/messages.field_required'));
        }

        $v->maxLen($input, 'title', 255, $this->t('sla/messages.max_255'));

        if (array_key_exists('response_minutes', $input) && (int)$input['response_minutes'] <= 0) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'response_minutes' => [$this->t('sla/messages.minutes_gt_zero')],
            ]);
        }

        if (array_key_exists('resolve_minutes', $input) && (int)$input['resolve_minutes'] <= 0) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'resolve_minutes' => [$this->t('sla/messages.minutes_gt_zero')],
            ]);
        }

        if (isset($input['escalation_payload']) && !is_array($input['escalation_payload'])) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'escalation_payload' => [$this->t('sla/messages.escalation_payload_object')],
            ]);
        }

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        return null;
    }

    public function assignToTask(array $params): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->user()) return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);

        $taskId = (string)($params['public_id'] ?? '');
        $slaId = (string)($this->request()->allInput()['sla_policy_id'] ?? '');
        if ($taskId === '' || $slaId === '') return $this->error('INVALID_PARAM', $this->t('common/messages.invalid_parameter'), 400);

        $service = $this->container->get('service.sla');
        $result = $service->assignToTask($taskId, $slaId);
        if ($result === null) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);

        return $this->success('SLA_ASSIGNED', $this->t('sla/messages.assigned'), ['task' => $result]);
    }
}
