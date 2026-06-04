<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\User\UserManagementRepository;
use Api\Model\Workflow\WorkflowRepository;
use Api\System\Library\Policy\HierarchyPolicy;
use Api\System\Library\Support\Ulid;

final class WorkflowService
{
    public function __construct(
        private readonly WorkflowRepository $workflow,
        private readonly UserManagementRepository $users,
        private readonly HierarchyPolicy $hierarchy
    ) {
    }

    public function listRules(array $filters, array $actor): array
    {
        $scope = $this->accessScope($actor);
        if ($scope['limit_to_creator_ids'] !== null) {
            $filters['created_by_user_ids'] = $scope['limit_to_creator_ids'];
            $filters['include_unowned'] = true;
        }

        [$items, $total, $page, $limit] = $this->workflow->listRules($filters);
        $items = array_map([$this, 'normalizeRule'], $items);

        return [
            'items' => $items,
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

    public function createRule(array $input, array $actor): array
    {
        $publicId = Ulid::generate('wfr');
        $now = gmdate('Y-m-d H:i:s');
        $this->workflow->createRule([
            'public_id' => $publicId,
            'title' => trim((string)$input['title']),
            'trigger_code' => trim((string)$input['trigger_code']),
            'action_code' => trim((string)$input['action_code']),
            'payload' => $this->encodePayload($input['payload'] ?? []),
            'is_enabled' => isset($input['is_enabled']) && (int)$input['is_enabled'] === 0 ? 0 : 1,
            'created_by_user_id' => (int)($actor['id'] ?? 0) ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->getRule($publicId, $actor) ?? ['public_id' => $publicId];
    }

    public function getRule(string $publicId, array $actor): ?array
    {
        $item = $this->workflow->findRuleByPublicId($publicId);
        if (!$item || !$this->canAccess($item, $actor)) {
            return null;
        }

        return $this->normalizeRule($item);
    }

    public function updateRule(string $publicId, array $input, array $actor): ?array
    {
        $existing = $this->workflow->findRuleByPublicId($publicId);
        if (!$existing || !$this->canAccess($existing, $actor)) {
            return null;
        }

        $set = ['updated_at' => gmdate('Y-m-d H:i:s')];
        if (array_key_exists('title', $input)) {
            $set['title'] = trim((string)$input['title']);
        }
        if (array_key_exists('trigger_code', $input)) {
            $set['trigger_code'] = trim((string)$input['trigger_code']);
        }
        if (array_key_exists('action_code', $input)) {
            $set['action_code'] = trim((string)$input['action_code']);
        }
        if (array_key_exists('payload', $input)) {
            $set['payload'] = $this->encodePayload($input['payload']);
        }
        if (array_key_exists('is_enabled', $input)) {
            $set['is_enabled'] = ((int)$input['is_enabled'] === 0) ? 0 : 1;
        }

        $this->workflow->updateRuleByPublicId($publicId, $set);

        return $this->getRule($publicId, $actor);
    }

    public function deleteRule(string $publicId, array $actor): bool
    {
        $existing = $this->workflow->findRuleByPublicId($publicId);
        if (!$existing || !$this->canAccess($existing, $actor)) {
            return false;
        }

        return $this->workflow->deleteRuleByPublicId($publicId);
    }

    public function runTest(string $rulePublicId, array $input, array $actor): array|string
    {
        $rule = $this->workflow->findRuleByPublicId($rulePublicId);
        if (!$rule || !$this->canAccess($rule, $actor)) {
            return 'RULE_NOT_FOUND';
        }

        $status = (!empty($input['simulate_error']) && (int)$input['simulate_error'] === 1) ? 'failed' : 'success';
        $error = null;
        if ($status === 'failed') {
            $error = trim((string)($input['error_message'] ?? 'Simulated workflow failure'));
            if ($error === '') {
                $error = 'Simulated workflow failure';
            }
        }

        $runPublicId = Ulid::generate('wfrun');
        $this->workflow->createRun([
            'public_id' => $runPublicId,
            'rule_id' => (int)$rule['id'],
            'status' => $status,
            'error' => $error,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return [
            'run_public_id' => $runPublicId,
            'status' => $status,
            'error' => $error,
            'rule_public_id' => (string)$rule['public_id'],
        ];
    }

    public function listRuns(array $filters, array $actor): array
    {
        $scope = $this->accessScope($actor);
        if ($scope['limit_to_creator_ids'] !== null) {
            $filters['created_by_user_ids'] = $scope['limit_to_creator_ids'];
            $filters['include_unowned'] = true;
        }

        [$items, $total, $page, $limit] = $this->workflow->listRuns($filters);

        return [
            'items' => $items,
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

    private function encodePayload(mixed $payload): string
    {
        if (is_array($payload)) {
            return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @param array<string,mixed> $item */
    private function normalizeRule(array $item): array
    {
        $item['is_enabled'] = (int)($item['is_enabled'] ?? 1) === 1;
        $rawPayload = $item['payload'] ?? null;
        $item['payload'] = [];

        if (is_string($rawPayload) && $rawPayload !== '') {
            $decoded = json_decode($rawPayload, true);
            if (is_array($decoded)) {
                $item['payload'] = $decoded;
            }
        }

        return $item;
    }

    private function canAccess(array $item, array $actor): bool
    {
        if ((int)($actor['is_root'] ?? 0) === 1) {
            return true;
        }

        $actorId = (int)($actor['id'] ?? 0);
        $creatorId = (int)($item['created_by_user_id'] ?? 0);
        if ($creatorId <= 0) {
            return true;
        }

        if ($actorId <= 0) {
            return false;
        }

        if ($actorId === $creatorId) {
            return true;
        }

        return $this->hierarchy->isAncestor($actorId, $creatorId);
    }

    /** @return array{limit_to_creator_ids:int[]|null} */
    private function accessScope(array $actor): array
    {
        if ((int)($actor['is_root'] ?? 0) === 1) {
            return ['limit_to_creator_ids' => null];
        }

        $actorId = (int)($actor['id'] ?? 0);
        if ($actorId <= 0) {
            return ['limit_to_creator_ids' => [-1]];
        }

        $descendants = $this->users->descendantIds($actorId);
        if ($descendants === []) {
            $descendants = [$actorId];
        }

        return ['limit_to_creator_ids' => $descendants];
    }

    /**
     * Find all enabled rules matching trigger and execute their actions.
     * @return array{rules_fired: int, runs: array<int,array{rule:string,status:string}>}
     */
    public function fireTrigger(string $triggerCode, array $context): array
    {
        $rules = $this->workflow->findEnabledRulesByTrigger($triggerCode);
        $results = ['rules_fired' => 0, 'runs' => []];

        foreach ($rules as $rule) {
            try {
                $runResult = $this->executeAction((string)$rule['action_code'], (string)$rule['public_id'], (array)($rule['payload'] ?? []), $context);
                $status = $runResult['success'] ? 'success' : 'failed';

                $runPublicId = Ulid::generate('wfrun');
                $this->workflow->createRun([
                    'public_id' => $runPublicId,
                    'rule_id' => (int)$rule['id'],
                    'status' => $status,
                    'error' => $runResult['error'] ?? null,
                    'created_at' => gmdate('Y-m-d H:i:s'),
                ]);

                $results['rules_fired']++;
                $results['runs'][] = ['rule' => $rule['public_id'], 'status' => $status];
            } catch (\Throwable $e) {
                $results['runs'][] = ['rule' => $rule['public_id'] ?? 'unknown', 'status' => 'error', 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Execute a single workflow action.
     * @param array<string,mixed> $payload Rule payload (parameters for the action)
     * @param array<string,mixed> $context Event context (task data, actor, etc.)
     * @return array{success: bool, error: string|null}
     */
    public function executeAction(string $actionCode, string $rulePublicId, array $payload, array $context): array
    {
        try {
            switch ($actionCode) {
                case 'assign_user':
                    $assigneeId = (int)($payload['assignee_user_id'] ?? 0);
                    $taskPublicId = (string)($context['task_public_id'] ?? '');
                    if ($assigneeId > 0 && $taskPublicId !== '') {
                        $this->workflow->updateTaskField($taskPublicId, 'assignee_user_id', $assigneeId);
                    }
                    return ['success' => true, 'error' => null];

                case 'change_status':
                    $newStatus = trim((string)($payload['status_code'] ?? ''));
                    $taskPublicId = (string)($context['task_public_id'] ?? '');
                    if ($newStatus !== '' && $taskPublicId !== '') {
                        $this->workflow->updateTaskField($taskPublicId, 'status_code', $newStatus);
                    }
                    return ['success' => true, 'error' => null];

                case 'send_notification':
                    $userIds = isset($payload['user_ids']) && is_array($payload['user_ids']) ? $payload['user_ids'] : [];
                    $title = (string)($payload['title'] ?? 'Workflow notification');
                    $body = (string)($payload['body'] ?? '');
                    if ($userIds !== []) {
                        $this->workflow->createNotifications($userIds, $title, $body, (string)($context['task_public_id'] ?? ''));
                    }
                    return ['success' => true, 'error' => null];

                case 'create_comment':
                    $commentText = trim((string)($payload['comment_text'] ?? ''));
                    $taskPublicId = (string)($context['task_public_id'] ?? '');
                    if ($commentText !== '' && $taskPublicId !== '') {
                        $this->workflow->createTaskComment($taskPublicId, $commentText, 'workflow');
                    }
                    return ['success' => true, 'error' => null];

                case 'create_reminder':
                    return ['success' => true, 'error' => null];

                case 'create_follow_up_task':
                    $followUpTitle = trim((string)($payload['task_title'] ?? 'Follow-up: ' . ($context['task_title'] ?? '')));
                    $followUpAssignee = (int)($payload['assignee_user_id'] ?? $context['actor_id'] ?? 0);
                    $followUpProject = (int)($payload['project_id'] ?? $context['project_id'] ?? 0);
                    if ($followUpTitle !== '') {
                        $this->workflow->createFollowUpTask($followUpTitle, $followUpAssignee ?: null, $followUpProject ?: null, (string)($context['task_public_id'] ?? ''));
                    }
                    return ['success' => true, 'error' => null];

                case 'call_webhook':
                    $webhookUrl = trim((string)($payload['url'] ?? ''));
                    if ($webhookUrl !== '') {
                        $this->workflow->callWebhookAsync($webhookUrl, $context);
                    }
                    return ['success' => true, 'error' => null];

                case 'escalate_sla':
                    $taskPublicId = (string)($context['task_public_id'] ?? '');
                    if ($taskPublicId !== '') {
                        $this->workflow->escalateSla($taskPublicId);
                    }
                    return ['success' => true, 'error' => null];

                default:
                    return ['success' => false, 'error' => 'Unknown action: ' . $actionCode];
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
