<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Approval\ApprovalRepository;
use Api\Model\Common\UserRepository;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Support\Ulid;

final class ApprovalService
{
    public function __construct(
        private readonly ApprovalRepository $approvals,
        private readonly UserRepository $users,
        private readonly JsonLogger $logger,
        private readonly ?NotificationService $notifications = null
    ) {
    }

    public function list(array $filters, array $actor): array
    {
        if (!(bool)($actor['is_root'] ?? false)) {
            $filters['involved_user_public_id'] = (string)($actor['public_id'] ?? '');
        }

        [$items, $total, $page, $limit] = $this->approvals->listRequests($filters);

        return [
            'items' => array_map([$this, 'normalizeListItem'], $items),
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int)ceil($total / max(1, $limit)),
                ],
            ],
        ];
    }

    public function create(array $input, array $actor): array
    {
        $reviewerPublicIds = $this->normalizeReviewerPublicIds($input);
        if ($reviewerPublicIds === []) {
            return ['ok' => false, 'code' => 'APPROVAL_REVIEWERS_REQUIRED'];
        }

        $reviewers = [];
        foreach ($reviewerPublicIds as $reviewerPublicId) {
            $reviewer = $this->users->findByPublicId($reviewerPublicId);
            if (!$reviewer) {
                return ['ok' => false, 'code' => 'REVIEWER_NOT_FOUND'];
            }
            if ((int)($reviewer['is_active'] ?? 0) !== 1) {
                return ['ok' => false, 'code' => 'REVIEWER_INACTIVE'];
            }
            $reviewers[] = $reviewer;
        }

        $now = gmdate('Y-m-d H:i:s');
        $requestPublicId = Ulid::generate('apr');
        $requestId = $this->approvals->createRequest([
            'public_id' => $requestPublicId,
            'entity_type' => trim((string)$input['entity_type']),
            'entity_public_id' => trim((string)$input['entity_public_id']),
            'requester_user_id' => (int)$actor['id'],
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $comment = trim((string)($input['comment'] ?? ''));
        foreach ($reviewers as $reviewer) {
            $this->approvals->createStep([
                'public_id' => Ulid::generate('aps'),
                'request_id' => $requestId,
                'reviewer_user_id' => (int)$reviewer['id'],
                'status' => 'pending',
                'comment' => $comment,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->logger->audit([
            'action' => 'approval_request_create',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'approval_request',
            'entity_public_id' => $requestPublicId,
            'target_entity_type' => trim((string)$input['entity_type']),
            'target_entity_public_id' => trim((string)$input['entity_public_id']),
        ]);

        $createdApproval = $this->get($requestPublicId, $actor)['approval'] ?? ['public_id' => $requestPublicId];
        if ($this->notifications !== null) {
            $this->notifications->notifyApprovalRequested(
                is_array($createdApproval) ? $createdApproval : ['public_id' => $requestPublicId],
                array_values(array_unique(array_filter(array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $reviewers), static fn(int $id): bool => $id > 0))),
                $actor
            );
        }

        return [
            'ok' => true,
            'approval' => $createdApproval,
        ];
    }

    public function get(string $publicId, array $actor): array
    {
        $request = $this->approvals->findRequestByPublicId($publicId);
        if (!$request) {
            return ['ok' => false, 'code' => 'APPROVAL_NOT_FOUND'];
        }

        if (!$this->canAccess($request, $actor)) {
            return ['ok' => false, 'code' => 'FORBIDDEN'];
        }

        $steps = $this->approvals->stepsByRequestId((int)$request['id']);
        $stats = $this->approvals->stepStatsByRequestId((int)$request['id']);

        return [
            'ok' => true,
            'approval' => $this->normalizeRequest($request, $steps, $stats),
        ];
    }

    public function approve(string $publicId, array $input, array $actor): array
    {
        return $this->review($publicId, 'approved', $input, $actor);
    }

    public function reject(string $publicId, array $input, array $actor): array
    {
        return $this->review($publicId, 'rejected', $input, $actor);
    }

    private function review(string $publicId, string $decision, array $input, array $actor): array
    {
        $request = $this->approvals->findRequestByPublicId($publicId);
        if (!$request) {
            return ['ok' => false, 'code' => 'APPROVAL_NOT_FOUND'];
        }

        if ((string)($request['status'] ?? '') !== 'pending') {
            return ['ok' => false, 'code' => 'APPROVAL_FINALIZED'];
        }

        $step = $this->approvals->findStepByRequestIdAndReviewerId((int)$request['id'], (int)$actor['id']);
        if (!$step) {
            return ['ok' => false, 'code' => 'APPROVAL_REVIEWER_FORBIDDEN'];
        }
        if ((string)($step['status'] ?? '') !== 'pending') {
            return ['ok' => false, 'code' => 'APPROVAL_STEP_ALREADY_PROCESSED'];
        }

        $comment = trim((string)($input['comment'] ?? ''));
        $now = gmdate('Y-m-d H:i:s');
        $this->approvals->updateStepById((int)$step['id'], [
            'status' => $decision,
            'comment' => $comment,
            'updated_at' => $now,
        ]);

        $stats = $this->approvals->stepStatsByRequestId((int)$request['id']);
        $requestStatus = 'pending';
        if ($decision === 'rejected' || $stats['rejected_steps'] > 0) {
            $requestStatus = 'rejected';
        } elseif ($stats['total'] > 0 && $stats['approved_steps'] >= $stats['total']) {
            $requestStatus = 'approved';
        }

        $this->approvals->updateRequestById((int)$request['id'], [
            'status' => $requestStatus,
            'updated_at' => $now,
        ]);

        $this->logger->audit([
            'action' => $decision === 'approved' ? 'approval_request_approve' : 'approval_request_reject',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'approval_request',
            'entity_public_id' => $publicId,
            'decision' => $decision,
        ]);

        $result = $this->get($publicId, $actor);
        if (($result['ok'] ?? false) === true && $this->notifications !== null) {
            $approval = is_array($result['approval'] ?? null) ? $result['approval'] : ['public_id' => $publicId];
            $targetUserIds = $this->approvalTargetUserIds((int)$request['requester_user_id'], (int)$request['id'], (int)($actor['id'] ?? 0));
            if ($targetUserIds !== []) {
                $this->notifications->notifyApprovalStepDecided($approval, $targetUserIds, $decision, $actor);
            }
            if ($requestStatus === 'approved' || $requestStatus === 'rejected') {
                $this->notifications->notifyApprovalFinalized($approval, $targetUserIds, $requestStatus, $actor);
            }
        }

        return $result;
    }

    /** @return int[] */
    private function approvalTargetUserIds(int $requesterUserId, int $requestId, int $actorUserId): array
    {
        $userIds = [];
        if ($requesterUserId > 0) {
            $userIds[] = $requesterUserId;
        }

        $steps = $this->approvals->stepsByRequestId($requestId);
        foreach ($steps as $step) {
            $reviewerPublicId = trim((string)($step['reviewer_public_id'] ?? ''));
            if ($reviewerPublicId === '') {
                continue;
            }
            $reviewer = $this->users->findByPublicId($reviewerPublicId);
            if ($reviewer) {
                $userIds[] = (int)($reviewer['id'] ?? 0);
            }
        }

        $normalized = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $id): bool => $id > 0)));
        if ($actorUserId > 0) {
            $normalized = array_values(array_filter($normalized, static fn(int $id): bool => $id !== $actorUserId));
        }

        return $normalized;
    }

    private function canAccess(array $request, array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        if ((string)($request['requester_public_id'] ?? '') === (string)($actor['public_id'] ?? '')) {
            return true;
        }

        $step = $this->approvals->findStepByRequestIdAndReviewerId((int)$request['id'], (int)($actor['id'] ?? 0));
        return $step !== null;
    }

    private function normalizeReviewerPublicIds(array $input): array
    {
        $value = $input['reviewer_public_ids'] ?? $input['reviewer_public_id'] ?? [];
        if (is_string($value) && trim($value) !== '') {
            $value = [trim($value)];
        }
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            $item = trim((string)$item);
            if ($item === '') {
                continue;
            }
            $items[] = $item;
        }

        return array_values(array_unique($items));
    }

    /** @param array<string,mixed> $request */
    private function normalizeListItem(array $request): array
    {
        return [
            'public_id' => (string)($request['public_id'] ?? ''),
            'entity_type' => (string)($request['entity_type'] ?? ''),
            'entity_public_id' => (string)($request['entity_public_id'] ?? ''),
            'status' => (string)($request['status'] ?? ''),
            'requester' => [
                'public_id' => (string)($request['requester_public_id'] ?? ''),
                'login' => (string)($request['requester_login'] ?? ''),
                'full_name' => (string)($request['requester_full_name'] ?? ''),
            ],
            'reviewers_total' => (int)($request['reviewers_total'] ?? 0),
            'pending_steps' => (int)($request['pending_steps'] ?? 0),
            'approved_steps' => (int)($request['approved_steps'] ?? 0),
            'rejected_steps' => (int)($request['rejected_steps'] ?? 0),
            'created_at' => (string)($request['created_at'] ?? ''),
            'updated_at' => (string)($request['updated_at'] ?? ''),
        ];
    }

    /** @param array<string,mixed> $request */
    private function normalizeRequest(array $request, array $steps, array $stats): array
    {
        return [
            'public_id' => (string)($request['public_id'] ?? ''),
            'entity_type' => (string)($request['entity_type'] ?? ''),
            'entity_public_id' => (string)($request['entity_public_id'] ?? ''),
            'status' => (string)($request['status'] ?? ''),
            'requester' => [
                'public_id' => (string)($request['requester_public_id'] ?? ''),
                'login' => (string)($request['requester_login'] ?? ''),
                'full_name' => (string)($request['requester_full_name'] ?? ''),
            ],
            'stats' => $stats,
            'steps' => array_map(static function (array $step): array {
                return [
                    'public_id' => (string)($step['public_id'] ?? ''),
                    'status' => (string)($step['status'] ?? ''),
                    'comment' => (string)($step['comment'] ?? ''),
                    'reviewer' => [
                        'public_id' => (string)($step['reviewer_public_id'] ?? ''),
                        'login' => (string)($step['reviewer_login'] ?? ''),
                        'full_name' => (string)($step['reviewer_full_name'] ?? ''),
                    ],
                    'created_at' => (string)($step['created_at'] ?? ''),
                    'updated_at' => (string)($step['updated_at'] ?? ''),
                ];
            }, $steps),
            'created_at' => (string)($request['created_at'] ?? ''),
            'updated_at' => (string)($request['updated_at'] ?? ''),
        ];
    }
}
