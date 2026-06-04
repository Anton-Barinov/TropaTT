<?php
declare(strict_types=1);

namespace Api\Controller\Approval;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\ApprovalService;
use Api\System\Library\Validation\Validator;

final class ApprovalController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ApprovalService $service */
        $service = $this->container->get('service.approval');
        $result = $service->list($this->request()->allInput(), $auth['user']);

        return $this->success('APPROVAL_LIST', $this->t('approval/messages.list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $validation = $this->validateCreate($input);
        if ($validation !== null) {
            return $validation;
        }

        return $this->withIdempotency(function () use ($input, $auth): \Api\System\Library\Http\JsonResponse {
            /** @var ApprovalService $service */
            $service = $this->container->get('service.approval');
            $result = $service->create($input, $auth['user']);
            if (!(bool)($result['ok'] ?? false)) {
                $status = match ((string)($result['code'] ?? '')) {
                    'REVIEWER_NOT_FOUND' => 404,
                    'REVIEWER_INACTIVE', 'APPROVAL_REVIEWERS_REQUIRED' => 422,
                    default => 400,
                };

                return $this->error((string)$result['code'], $this->t('approval/messages.create_failed'), $status, [
                    'approval' => [(string)$result['code']],
                ]);
            }

            return $this->success('APPROVAL_CREATED', $this->t('approval/messages.created'), [
                'approval' => $result['approval'],
            ], 201);
        });
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ApprovalService $service */
        $service = $this->container->get('service.approval');
        $result = $service->get((string)$params['public_id'], $auth['user']);
        if (!(bool)($result['ok'] ?? false)) {
            $status = (string)($result['code'] ?? '') === 'APPROVAL_NOT_FOUND' ? 404 : 403;
            return $this->error((string)$result['code'], $this->t('approval/messages.unavailable'), $status, [
                'approval' => [(string)$result['code']],
            ]);
        }

        return $this->success('APPROVAL_DETAIL', $this->t('approval/messages.detail'), [
            'approval' => $result['approval'],
        ]);
    }

    public function approve(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->review((string)$params['public_id'], 'approve');
    }

    public function reject(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->review((string)$params['public_id'], 'reject');
    }

    public function listAlias(): \Api\System\Library\Http\JsonResponse
    {
        return $this->list();
    }

    public function requestAlias(): \Api\System\Library\Http\JsonResponse
    {
        return $this->create();
    }

    public function approveAlias(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->approve($params);
    }

    public function rejectAlias(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->reject($params);
    }

    private function review(string $publicId, string $action): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->maxLen($input, 'comment', 1000, $this->t('approval/messages.max_1000'));
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var ApprovalService $service */
        $service = $this->container->get('service.approval');
        $result = $action === 'approve'
            ? $service->approve($publicId, $input, $auth['user'])
            : $service->reject($publicId, $input, $auth['user']);

        if (!(bool)($result['ok'] ?? false)) {
            $status = match ((string)($result['code'] ?? '')) {
                'APPROVAL_NOT_FOUND' => 404,
                'APPROVAL_REVIEWER_FORBIDDEN' => 403,
                'APPROVAL_FINALIZED', 'APPROVAL_STEP_ALREADY_PROCESSED' => 409,
                default => 400,
            };

            return $this->error((string)$result['code'], $this->t('approval/messages.review_failed'), $status, [
                'approval' => [(string)$result['code']],
            ]);
        }

        return $this->success(
            $action === 'approve' ? 'APPROVAL_APPROVED' : 'APPROVAL_REJECTED',
            $action === 'approve'
                ? $this->t('approval/messages.approved')
                : $this->t('approval/messages.rejected'),
            ['approval' => $result['approval']]
        );
    }

    private function validateCreate(array $input): ?\Api\System\Library\Http\JsonResponse
    {
        $v = new Validator();
        $v->require($input, 'entity_type', $this->t('common/messages.field_required'))
            ->require($input, 'entity_public_id', $this->t('common/messages.field_required'))
            ->maxLen($input, 'entity_type', 64, $this->t('approval/messages.max_64'))
            ->maxLen($input, 'entity_public_id', 64, $this->t('approval/messages.max_64'))
            ->maxLen($input, 'comment', 1000, $this->t('approval/messages.max_1000'));
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        $reviewers = $input['reviewer_public_ids'] ?? $input['reviewer_public_id'] ?? null;
        if (is_string($reviewers)) {
            $reviewers = [trim($reviewers)];
        }
        if (!is_array($reviewers) || $reviewers === []) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'reviewer_public_ids' => [$this->t('approval/messages.reviewers_required')],
            ]);
        }

        return null;
    }
}
