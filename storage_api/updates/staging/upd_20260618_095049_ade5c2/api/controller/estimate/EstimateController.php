<?php
declare(strict_types=1);

namespace Api\Controller\Estimate;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\TaskEstimateService;

final class EstimateController extends BaseController
{
    // ========== Estimate Sets ==========

    public function listSets(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        /** @var TaskEstimateService $service */
        $service = $this->container->get('service.task_estimate');
        $result = $service->listSets($input, $authUser['user']);

        if (is_string($result)) {
            return $this->error($result, $this->t('common/messages.error', 'Error'), 422);
        }

        return $this->success('ESTIMATE_SETS_LIST', $this->t('estimate/messages.list', 'Estimate sets list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function createSet(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        /** @var TaskEstimateService $service */
        $service = $this->container->get('service.task_estimate');
        $set = $service->createSet($input, $authUser['user']);

        if (is_string($set)) {
            $httpStatus = match ($set) {
                'ESTIMATE_SET_PROJECT_NOT_FOUND' => 404,
                'ESTIMATE_SET_CODE_ALREADY_EXISTS' => 409,
                'ESTIMATE_SET_LOCKED' => 423,
                default => 422,
            };
            return $this->error($set, $this->t('common/messages.validation_error', 'Validation error'), $httpStatus);
        }

        return $this->success('ESTIMATE_SET_CREATED', $this->t('estimate/messages.created', 'Estimate set created'), [
            'estimate_set' => $set,
        ], 201, [
            'row_version' => (int)($set['row_version'] ?? 1),
        ]);
    }

    public function getSet(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var TaskEstimateService $service */
        $service = $this->container->get('service.task_estimate');
        $set = $service->getSet((string)$params['public_id'], $authUser['user']);

        if (!$set) {
            return $this->error('ESTIMATE_SET_NOT_FOUND', $this->t('estimate/messages.not_found', 'Estimate set not found'), 404);
        }

        return $this->success('ESTIMATE_SET_DETAIL', $this->t('estimate/messages.detail', 'Estimate set detail'), [
            'estimate_set' => $set,
        ], meta: [
            'row_version' => (int)($set['row_version'] ?? 1),
        ]);
    }

    public function updateSet(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        /** @var TaskEstimateService $service */
        $service = $this->container->get('service.task_estimate');
        $set = $service->updateSet((string)$params['public_id'], $input, $authUser['user']);

        if ($set === 'ROW_VERSION_CONFLICT') {
            return $this->error('ROW_VERSION_CONFLICT', $this->t('estimate/messages.row_version_conflict', 'Set was modified by another user'), 409);
        }
        if (is_string($set)) {
            $httpStatus = match ($set) {
                'ESTIMATE_SET_LOCKED' => 423,
                default => 422,
            };
            return $this->error($set, $this->t('common/messages.validation_error', 'Validation error'), $httpStatus);
        }
        if ($set === null) {
            return $this->error('ESTIMATE_SET_NOT_FOUND', $this->t('estimate/messages.not_found', 'Estimate set not found'), 404);
        }

        return $this->success('ESTIMATE_SET_UPDATED', $this->t('estimate/messages.updated', 'Estimate set updated'), [
            'estimate_set' => $set,
        ], meta: [
            'row_version' => (int)($set['row_version'] ?? 1),
        ]);
    }

    public function archiveSet(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var TaskEstimateService $service */
        $service = $this->container->get('service.task_estimate');
        $ok = $service->archiveSet((string)$params['public_id'], $authUser['user']);

        if ($ok === false) {
            return $this->error('ESTIMATE_SET_NOT_FOUND', $this->t('estimate/messages.not_found', 'Estimate set not found'), 404);
        }

        return $this->success('ESTIMATE_SET_ARCHIVED', $this->t('estimate/messages.archived', 'Estimate set archived'));
    }

    public function deleteSet(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var TaskEstimateService $service */
        $service = $this->container->get('service.task_estimate');
        $ok = $service->deleteSet((string)$params['public_id'], $authUser['user']);

        if ($ok === false) {
            return $this->error('ESTIMATE_SET_NOT_FOUND', $this->t('estimate/messages.not_found', 'Estimate set not found'), 404);
        }

        return $this->success('ESTIMATE_SET_DELETED', $this->t('estimate/messages.deleted', 'Estimate set deleted'));
    }

    // ========== Estimate Options ==========

    public function listOptions(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $filters = $this->request()->allInput();
        /** @var TaskEstimateService $service */
        $service = $this->container->get('service.task_estimate');
        $options = $service->listOptions((string)$params['public_id'], $filters, $authUser['user']);

        if ($options === null) {
            return $this->error('ESTIMATE_SET_NOT_FOUND', $this->t('estimate/messages.not_found', 'Estimate set not found'), 404);
        }

        return $this->success('ESTIMATE_OPTIONS_LIST', $this->t('estimate/messages.options_list', 'Estimate options'), [
            'items' => $options,
        ]);
    }

    public function createOption(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        /** @var TaskEstimateService $service */
        $service = $this->container->get('service.task_estimate');
        $option = $service->createOption((string)$params['public_id'], $input, $authUser['user']);

        if (is_string($option)) {
            $httpStatus = match ($option) {
                'ESTIMATE_OPTION_CODE_ALREADY_EXISTS' => 409,
                default => 422,
            };
            return $this->error($option, $this->t('common/messages.validation_error', 'Validation error'), $httpStatus);
        }
        if ($option === null) {
            return $this->error('ESTIMATE_SET_NOT_FOUND', $this->t('estimate/messages.not_found', 'Estimate set not found'), 404);
        }

        return $this->success('ESTIMATE_OPTION_CREATED', $this->t('estimate/messages.option_created', 'Estimate option created'), [
            'estimate_option' => $option,
        ], 201, [
            'row_version' => (int)($option['row_version'] ?? 1),
        ]);
    }

    public function updateOption(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        /** @var TaskEstimateService $service */
        $service = $this->container->get('service.task_estimate');
        $option = $service->updateOption((string)$params['public_id'], $input, $authUser['user']);

        if ($option === 'ROW_VERSION_CONFLICT') {
            return $this->error('ROW_VERSION_CONFLICT', $this->t('estimate/messages.row_version_conflict', 'Option was modified by another user'), 409);
        }
        if (is_string($option)) {
            return $this->error($option, $this->t('common/messages.validation_error', 'Validation error'), 422);
        }
        if ($option === null) {
            return $this->error('ESTIMATE_OPTION_NOT_FOUND', $this->t('estimate/messages.option_not_found', 'Estimate option not found'), 404);
        }

        return $this->success('ESTIMATE_OPTION_UPDATED', $this->t('estimate/messages.option_updated', 'Estimate option updated'), [
            'estimate_option' => $option,
        ], meta: [
            'row_version' => (int)($option['row_version'] ?? 1),
        ]);
    }

    public function archiveOption(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var TaskEstimateService $service */
        $service = $this->container->get('service.task_estimate');
        $ok = $service->archiveOption((string)$params['public_id'], $authUser['user']);

        if ($ok === false) {
            return $this->error('ESTIMATE_OPTION_NOT_FOUND', $this->t('estimate/messages.option_not_found', 'Estimate option not found'), 404);
        }

        return $this->success('ESTIMATE_OPTION_ARCHIVED', $this->t('estimate/messages.option_archived', 'Estimate option archived'));
    }

    public function deleteOption(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var TaskEstimateService $service */
        $service = $this->container->get('service.task_estimate');
        $ok = $service->deleteOption((string)$params['public_id'], $authUser['user']);

        if ($ok === false) {
            return $this->error('ESTIMATE_OPTION_NOT_FOUND', $this->t('estimate/messages.option_not_found', 'Estimate option not found'), 404);
        }

        return $this->success('ESTIMATE_OPTION_DELETED', $this->t('estimate/messages.option_deleted', 'Estimate option deleted'));
    }

    // ========== Task Estimates ==========

    public function listTaskEstimates(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var TaskEstimateService $service */
        $service = $this->container->get('service.task_estimate');
        $estimates = $service->listTaskEstimates((string)$params['public_id'], $authUser['user']);

        if ($estimates === null) {
            return $this->error('TASK_NOT_FOUND', $this->t('task/messages.not_found', 'Task not found'), 404);
        }

        return $this->success('TASK_ESTIMATES_LIST', $this->t('estimate/messages.task_estimates_list', 'Task estimates'), [
            'items' => $estimates,
        ]);
    }

    public function assignEstimate(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        /** @var TaskEstimateService $service */
        $service = $this->container->get('service.task_estimate');
        $estimate = $service->assignTaskEstimate((string)$params['public_id'], $input, $authUser['user']);

        if (is_string($estimate)) {
            $httpStatus = match ($estimate) {
                'TASK_ESTIMATE_TASK_NOT_FOUND', 'ESTIMATE_SET_NOT_FOUND', 'ESTIMATE_OPTION_NOT_FOUND' => 404,
                default => 422,
            };
            return $this->error($estimate, $this->t('common/messages.validation_error', 'Validation error'), $httpStatus);
        }
        if ($estimate === null) {
            return $this->error('TASK_NOT_FOUND', $this->t('task/messages.not_found', 'Task not found'), 404);
        }

        return $this->success('TASK_ESTIMATE_ASSIGNED', $this->t('estimate/messages.estimate_assigned', 'Estimate assigned'), [
            'task_estimate' => $estimate,
        ], 201, [
            'row_version' => (int)($estimate['row_version'] ?? 1),
        ]);
    }

    public function removeEstimate(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var TaskEstimateService $service */
        $service = $this->container->get('service.task_estimate');
        $ok = $service->removeTaskEstimate((string)$params['public_id'], (string)$params['set_public_id'], $authUser['user']);

        if ($ok === false) {
            return $this->error('TASK_ESTIMATE_NOT_FOUND', $this->t('estimate/messages.estimate_not_found', 'Task estimate not found'), 404);
        }

        return $this->success('TASK_ESTIMATE_REMOVED', $this->t('estimate/messages.estimate_removed', 'Estimate removed'));
    }

    // ========== Summary ==========

    public function projectSummary(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $filters = $this->request()->allInput();
        /** @var TaskEstimateService $service */
        $service = $this->container->get('service.task_estimate');
        $summary = $service->summaryByProject((string)$params['public_id'], $filters, $authUser['user']);

        if ($summary === null) {
            return $this->error('PROJECT_NOT_FOUND', $this->t('project/messages.not_found', 'Project not found'), 404);
        }

        return $this->success('ESTIMATE_PROJECT_SUMMARY', $this->t('estimate/messages.project_summary', 'Project estimate summary'), [
            'summary' => $summary,
        ]);
    }

    public function cycleSummary(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $filters = $this->request()->allInput();
        /** @var TaskEstimateService $service */
        $service = $this->container->get('service.task_estimate');
        $summary = $service->summaryByCycle((string)$params['public_id'], $filters, $authUser['user']);

        if ($summary === null) {
            return $this->error('CYCLE_NOT_FOUND', $this->t('cycle/messages.not_found', 'Cycle not found'), 404);
        }

        return $this->success('ESTIMATE_CYCLE_SUMMARY', $this->t('estimate/messages.cycle_summary', 'Cycle estimate summary'), [
            'summary' => $summary,
        ]);
    }

    public function moduleSummary(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $filters = $this->request()->allInput();
        /** @var TaskEstimateService $service */
        $service = $this->container->get('service.task_estimate');
        $summary = $service->summaryByModule((string)$params['public_id'], $filters, $authUser['user']);

        if ($summary === null) {
            return $this->error('MODULE_NOT_FOUND', $this->t('module/messages.not_found', 'Module not found'), 404);
        }

        return $this->success('ESTIMATE_MODULE_SUMMARY', $this->t('estimate/messages.module_summary', 'Module estimate summary'), [
            'summary' => $summary,
        ]);
    }
}
