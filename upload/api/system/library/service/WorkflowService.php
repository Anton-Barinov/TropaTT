<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\User\UserManagementRepository;
use Api\Model\Workflow\WorkflowRepository;
use Api\Model\Worklog\WorklogRepository;
use Api\System\Library\Language\LanguageManager;
use Api\System\Library\Language\TranslatableTrait;
use Api\System\Library\Policy\HierarchyPolicy;
use Api\System\Library\Support\Ulid;

final class WorkflowService
{
    use TranslatableTrait;

    public function __construct(
        private readonly WorkflowRepository $workflow,
        private readonly UserManagementRepository $users,
        private readonly HierarchyPolicy $hierarchy,
        LanguageManager $lang,
        private readonly ?NotificationService $notification = null,
        private readonly ?WorklogRepository $worklogs = null,
    ) {
        $this->lang = $lang;
    }

    public function listRules(array $filters, array $actor): array
    {
        $scope = $this->accessScope($actor);
        if ($scope['limit_to_creator_ids'] !== null) {
            $filters['created_by_user_ids'] = $scope['limit_to_creator_ids'];
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

        $context = $this->testContext($input, $actor);
        $payload = $this->decodePayload($rule['payload'] ?? []);
        if (($rule['trigger_code'] ?? '') === 'worklog_logged') {
            // Make the Test run produce meaningful {user}/{total}/{threshold}/{day}
            // placeholders for time-tracking rules.
            $context['user_id'] = (int)($actor['id'] ?? 0);
            $context['user_public_id'] = (string)($actor['public_id'] ?? '');
            $context['user_full_name'] = (string)($actor['full_name'] ?? '');
            $context['minutes_spent'] = max(1, (int)($input['minutes_spent'] ?? 60));
            $context['total_minutes'] = max(1, (int)($input['total_minutes'] ?? $context['minutes_spent']));
            $context['threshold_minutes'] = max(1, (int)($payload['threshold_minutes'] ?? 0));
            $context['day'] = (string)($input['day'] ?? gmdate('Y-m-d'));
        }
        $result = $this->executeAction(
            (string)$rule['action_code'],
            (string)$rule['public_id'],
            $payload,
            $context
        );

        $status = $result['success'] ? 'success' : 'failed';
        $error = $result['error'] ?? null;

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
            'context' => $context,
        ];
    }

    public function listRuns(array $filters, array $actor): array
    {
        $scope = $this->accessScope($actor);
        if ($scope['limit_to_creator_ids'] !== null) {
            $filters['created_by_user_ids'] = $scope['limit_to_creator_ids'];
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

    /**
     * Fail-closed object access: root may access anything; non-root may only
     * access records created by themselves or by their own hierarchy subtree.
     * Records without an owner (created_by_user_id IS NULL) belong to nobody
     * and are therefore root-only (see AGENTS.md object-level authorization).
     */
    private function canAccess(array $item, array $actor): bool
    {
        if ((int)($actor['is_root'] ?? 0) === 1) {
            return true;
        }

        $actorId = (int)($actor['id'] ?? 0);
        $creatorId = (int)($item['created_by_user_id'] ?? 0);
        if ($actorId <= 0 || $creatorId <= 0) {
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
                $payload = $this->decodePayload($rule['payload'] ?? []);
                $ruleContext = $context;
                if ($triggerCode === 'worklog_logged') {
                    $evaluation = $this->evaluateWorklogTrigger($payload, $ruleContext);
                    if (!$evaluation['matched']) {
                        continue;
                    }
                    // Expose the computed stats to actions so notifications can
                    // reference {total} / {threshold} placeholders.
                    $ruleContext['total_minutes'] = $evaluation['after'];
                    $ruleContext['threshold_minutes'] = $evaluation['threshold'];
                    $ruleContext['window_type'] = $evaluation['window'];
                    $ruleContext['scope'] = $evaluation['scope'];
                } elseif (!$this->matchesTriggerConditions($triggerCode, $payload, $context)) {
                    continue;
                }

                $runResult = $this->executeAction(
                    (string)$rule['action_code'],
                    (string)$rule['public_id'],
                    $payload,
                    $ruleContext
                );
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
                error_log('[WorkflowService::fireTrigger] ' . $e->getMessage());
                $results['runs'][] = ['rule' => $rule['public_id'] ?? 'unknown', 'status' => 'error', 'error' => 'Workflow rule execution failed. Check server logs for details.'];
            }
        }

        return $results;
    }

    private function matchesTriggerConditions(string $triggerCode, array $payload, array $context): bool
    {
        if ($triggerCode === 'worklog_logged') {
            return $this->evaluateWorklogTrigger($payload, $context)['matched'];
        }

        if ($triggerCode !== 'task_status_changed') {
            return true;
        }

        $from = trim((string)($payload['from_status_code'] ?? ''));
        $to = trim((string)($payload['to_status_code'] ?? ''));
        $previous = trim((string)($context['previous_status'] ?? ''));
        $next = trim((string)($context['new_status'] ?? $context['task_status'] ?? ''));

        if ($from !== '' && $from !== $previous) {
            return false;
        }

        if ($to !== '' && $to !== $next) {
            return false;
        }

        $conditionTag = trim((string)($payload['condition_tag_public_id'] ?? ''));
        if ($conditionTag !== '') {
            $taskTags = $context['task_tags'] ?? [];
            if (!is_array($taskTags) || !in_array($conditionTag, $taskTags, true)) {
                return false;
            }
        }

        return true;
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
                    $assigneeId = $this->resolveUserId($payload['assignee_user_id'] ?? $payload['assignee_user_public_id'] ?? 0);
                    $taskPublicId = (string)($context['task_public_id'] ?? '');
                    if ($assigneeId > 0 && $taskPublicId !== '') {
                        $this->workflow->updateTaskField($taskPublicId, 'assignee_user_id', $assigneeId);
                        return ['success' => true, 'error' => null];
                    }
                    return ['success' => false, 'error' => $this->t('workflow/messages.action_no_task_or_assignee')];

                case 'change_status':
                    $newStatus = trim((string)($payload['status_code'] ?? $payload['status'] ?? ''));
                    $taskPublicId = (string)($context['task_public_id'] ?? '');
                    if ($newStatus !== '' && $taskPublicId !== '') {
                        $this->workflow->updateTaskField($taskPublicId, 'status_code', $newStatus);
                        return ['success' => true, 'error' => null];
                    }
                    return ['success' => false, 'error' => $this->t('workflow/messages.action_no_task_or_status')];

                case 'send_notification':
                    $userIds = $this->resolveUserIds($payload['user_ids'] ?? $payload['user_public_ids'] ?? $payload['recipient_user_public_ids'] ?? []);
                    $title = trim((string)($payload['title'] ?? $this->t('workflow/messages.default_notification_title')));
                    $body = trim((string)($payload['body'] ?? $payload['message'] ?? ''));
                    if ($userIds !== []) {
                        $taskPublicId = (string)($context['task_public_id'] ?? '');
                        if ($this->notification !== null) {
                            $this->notification->notifyUsers($userIds, [
                                'category' => 'workflow',
                                'title' => $title,
                                'body' => $body,
                                'entity_type' => $taskPublicId !== '' ? 'task' : null,
                                'entity_public_id' => $taskPublicId !== '' ? $taskPublicId : null,
                                'action_code' => 'workflow',
                            ]);
                        } else {
                            $this->workflow->createNotifications($userIds, $title, $body, $taskPublicId);
                        }
                        return ['success' => true, 'error' => null];
                    }
                    return ['success' => false, 'error' => $this->t('workflow/messages.action_no_notification_recipient')];

                case 'notify_manager':
                    // Auto-resolve the executor's manager (team manager, then
                    // the user's creator, then the task manager) unless the rule
                    // pins explicit recipients.
                    $explicitManagers = $this->resolveUserIds($payload['manager_user_public_ids'] ?? []);
                    $recipients = $explicitManagers !== [] ? $explicitManagers : $this->resolveManagerIds($context);
                    if ($recipients !== []) {
                        $managerTitle = $this->interpolate((string)(($payload['title'] ?? '') ?: $this->t('workflow/messages.manager_notification_title')), $context);
                        $managerBody = $this->interpolate((string)(($payload['body'] ?? '') ?: $this->t('workflow/messages.manager_notification_body')), $context);
                        $taskPublicId = (string)($context['task_public_id'] ?? '');
                        if ($this->notification !== null) {
                            $this->notification->notifyUsers($recipients, [
                                'category' => 'workflow',
                                'title' => $managerTitle,
                                'body' => $managerBody,
                                'entity_type' => $taskPublicId !== '' ? 'task' : null,
                                'entity_public_id' => $taskPublicId !== '' ? $taskPublicId : null,
                                'action_code' => 'workflow',
                            ]);
                        } else {
                            $this->workflow->createNotifications($recipients, $managerTitle, $managerBody, $taskPublicId);
                        }
                        return ['success' => true, 'error' => null];
                    }
                    return ['success' => false, 'error' => $this->t('workflow/messages.action_no_manager')];

                case 'create_comment':
                    $commentText = trim((string)($payload['comment_text'] ?? $payload['comment'] ?? $payload['message'] ?? ''));
                    $taskPublicId = (string)($context['task_public_id'] ?? '');
                    if ($commentText !== '' && $taskPublicId !== '') {
                        $this->workflow->createTaskComment($taskPublicId, $commentText, 'workflow');
                        return ['success' => true, 'error' => null];
                    }
                    return ['success' => false, 'error' => $this->t('workflow/messages.action_no_task_or_comment')];

                case 'create_reminder':
                    $userId = $this->resolveUserId($payload['user_id'] ?? $payload['user_public_id'] ?? $payload['assignee_user_public_id'] ?? ($context['actor_id'] ?? 0));
                    $taskPublicId = (string)($context['task_public_id'] ?? '');
                    $taskId = $taskPublicId !== '' ? $this->workflow->taskIdByPublicId($taskPublicId) : null;
                    $remindAt = $this->normalizeReminderTime((string)($payload['remind_at'] ?? '+1 hour'));
                    $this->workflow->createReminder($userId ?: null, $taskId, $remindAt);
                    return $userId > 0
                        ? ['success' => true, 'error' => null]
                        : ['success' => false, 'error' => $this->t('workflow/messages.action_no_user_for_reminder')];

                case 'create_follow_up_task':
                    $followUpTitle = trim((string)($payload['task_title'] ?? $payload['title'] ?? $this->t('workflow/messages.follow_up_prefix', 'Follow-up: ') . ($context['task_title'] ?? '')));
                    $followUpAssignee = $this->resolveUserId($payload['assignee_user_id'] ?? $payload['assignee_user_public_id'] ?? $context['actor_id'] ?? 0);
                    $followUpProject = $this->resolveProjectId($payload['project_id'] ?? $payload['project_public_id'] ?? $context['project_id'] ?? 0);
                    if ($followUpTitle !== '') {
                        $sourceTaskPublicId = (string)($context['task_public_id'] ?? '');
                        $description = $sourceTaskPublicId !== '' ? $this->t('workflow/messages.follow_up_description') . ' ' . $sourceTaskPublicId : '';
                        $this->workflow->createFollowUpTask($followUpTitle, $followUpAssignee ?: null, $followUpProject ?: null, $sourceTaskPublicId, (int)($context['actor_id'] ?? 0) ?: null, $description);
                        return ['success' => true, 'error' => null];
                    }
                    return ['success' => false, 'error' => $this->t('workflow/messages.action_no_follow_up_title')];

                case 'call_webhook':
                    $webhookUrl = trim((string)($payload['url'] ?? ''));
                    if ($webhookUrl !== '' && preg_match('/^https?:\/\//i', $webhookUrl)) {
                        $this->workflow->callWebhookAsync($webhookUrl, $context);
                        return ['success' => true, 'error' => null];
                    }
                    return ['success' => false, 'error' => $this->t('workflow/messages.action_invalid_webhook_url')];

                case 'escalate_sla':
                    $taskPublicId = (string)($context['task_public_id'] ?? '');
                    if ($taskPublicId !== '') {
                        $this->workflow->escalateSla($taskPublicId);
                        return ['success' => true, 'error' => null];
                    }
                    return ['success' => false, 'error' => $this->t('workflow/messages.action_no_task_for_sla')];

                default:
                    return ['success' => false, 'error' => $this->t('workflow/messages.unknown_action', 'Unknown action: ') . $actionCode];
            }
        } catch (\Throwable $e) {
            error_log('[WorkflowService::executeAction] ' . $e->getMessage());
            return ['success' => false, 'error' => 'Workflow action execution failed. Check server logs for details.'];
        }
    }

    /** @return array<string,mixed> */
    private function decodePayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (is_string($payload) && trim($payload) !== '') {
            $decoded = json_decode($payload, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function resolveUserId(mixed $value): int
    {
        if (is_int($value) || ctype_digit((string)$value)) {
            return max(0, (int)$value);
        }

        $publicId = trim((string)$value);
        if ($publicId === '') {
            return 0;
        }

        return $this->workflow->userIdByPublicId($publicId) ?? 0;
    }

    /** @return int[] */
    private function resolveUserIds(mixed $values): array
    {
        $items = is_array($values) ? $values : [$values];
        $ids = [];
        foreach ($items as $value) {
            $id = $this->resolveUserId($value);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function resolveProjectId(mixed $value): int
    {
        if (is_int($value) || ctype_digit((string)$value)) {
            return max(0, (int)$value);
        }

        $publicId = trim((string)$value);
        if ($publicId === '') {
            return 0;
        }

        return $this->workflow->projectIdByPublicId($publicId) ?? 0;
    }

    private function normalizeReminderTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            $value = '+1 hour';
        }

        try {
            return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            error_log('[WorkflowService::normalizeReminderTime] ' . $e->getMessage());
            return gmdate('Y-m-d H:i:s', time() + 3600);
        }
    }

    /** @return array<string,mixed> */
    private function testContext(array $input, array $actor): array
    {
        return [
            'task_public_id' => trim((string)($input['task_public_id'] ?? '')),
            'task_title' => trim((string)($input['task_title'] ?? $this->t('workflow/messages.test_task_title'))),
            'task_status' => trim((string)($input['task_status'] ?? 'new')),
            'task_assignee_id' => $this->resolveUserId($input['task_assignee_user_public_id'] ?? 0),
            'project_id' => $this->resolveProjectId($input['project_public_id'] ?? 0),
            'actor_id' => (int)($actor['id'] ?? 0),
            'actor_public_id' => (string)($actor['public_id'] ?? ''),
            'task_tags' => array_map('trim', explode(',', (string)($input['task_tags'] ?? ''))),
            'is_test' => true,
        ];
    }

    /**
     * Evaluate the time-tracking conditions of a worklog_logged rule.
     *
     * The rule fires exactly once per threshold crossing: the aggregated time
     * before this entry is below the threshold and the total including it is
     * at or above it. Later entries on the same day/scope do not re-fire.
     *
     * @return array{matched: bool, before: int, after: int, threshold: int, window: string, scope: string}
     */
    private function evaluateWorklogTrigger(array $payload, array $context): array
    {
        $threshold = max(1, (int)($payload['threshold_minutes'] ?? 480));
        $window = (string)($payload['window_type'] ?? 'day');
        $window = in_array($window, ['day', 'continuous'], true) ? $window : 'day';
        $scope = (string)($payload['scope'] ?? 'task');
        $scope = in_array($scope, ['task', 'user'], true) ? $scope : 'task';
        $breakMinutes = max(0, (int)($payload['break_threshold_minutes'] ?? 90));

        $failed = ['matched' => false, 'before' => 0, 'after' => 0, 'threshold' => $threshold, 'window' => $window, 'scope' => $scope];

        try {
            $conditionUsers = $payload['condition_user_public_ids'] ?? [];
            if (is_array($conditionUsers) && $conditionUsers !== []) {
                $executor = (string)($context['user_public_id'] ?? '');
                if ($executor === '' || !in_array($executor, $conditionUsers, true)) {
                    return $failed;
                }
            }

            // Fail-closed: without the worklog repository the rule never fires.
            if ($this->worklogs === null) {
                return $failed;
            }

            $userId = (int)($context['user_id'] ?? 0);
            $entryId = (int)($context['worklog_id'] ?? 0);
            $entryMinutes = max(0, (int)($context['minutes_spent'] ?? 0));
            $previousMinutes = max(0, (int)($context['previous_minutes_spent'] ?? 0));
            if ($userId <= 0 || $entryMinutes <= 0) {
                return $failed;
            }

            // Task scope: restrict to the entry's task (null = task-less group).
            // User scope: aggregate across ALL tasks of the user (0 = no filter).
            $taskId = $scope === 'task' ? ((int)($context['task_id'] ?? 0) ?: null) : 0;
            $day = (string)($context['day'] ?? gmdate('Y-m-d'));
            $entries = $this->worklogs->automationEntriesByDay($userId, $taskId, $day);

            if ($window === 'continuous') {
                [$before, $after] = self::continuousSessionTotals($entries, $entryId, $entryMinutes, $breakMinutes, $previousMinutes);
            } else {
                // For updates the day total before this change includes the
                // entry's previous minutes; for creates previous is 0.
                $before = $previousMinutes;
                foreach ($entries as $entry) {
                    if ((int)($entry['id'] ?? 0) === $entryId) {
                        continue;
                    }
                    $before += max(0, (int)($entry['minutes_spent'] ?? 0));
                }
                $after = $before + $entryMinutes - $previousMinutes;
            }

            $matched = $before < $threshold && $after >= $threshold;
        } catch (\Throwable $e) {
            error_log('[WorkflowService::evaluateWorklogTrigger] ' . $e->getMessage());
            $matched = false;
        }

        return ['matched' => $matched, 'before' => $before ?? 0, 'after' => $after ?? 0, 'threshold' => $threshold, 'window' => $window, 'scope' => $scope];
    }

    /**
     * Split a day's entries into continuous sessions and return the totals of
     * the session containing $entryId as [before, after]. A gap above
     * $breakMinutes between the previous entry's end and the next entry's start
     * starts a new session. Entries without interval data cannot prove a break,
     * so they continue the current session (conservative towards continuity).
     *
     * @param array<int,array<string,mixed>> $entries
     * @return array{0: int, 1: int}
     */
    private static function continuousSessionTotals(array $entries, int $entryId, int $entryMinutes, int $breakMinutes, int $previousMinutes = 0): array
    {
        if ($entries === []) {
            return [0, $entryMinutes];
        }

        usort($entries, static function (array $a, array $b): int {
            $ta = strtotime((string)($a['started_at'] ?? $a['logged_at'] ?? ''));
            $tb = strtotime((string)($b['started_at'] ?? $b['logged_at'] ?? ''));
            if ($ta === false) {
                $ta = PHP_INT_MAX;
            }
            if ($tb === false) {
                $tb = PHP_INT_MAX;
            }
            if ($ta === $tb) {
                return (int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0);
            }
            return $ta <=> $tb;
        });

        $sessionTotal = 0;
        $prevEndTs = null;
        $entrySession = null;
        $breakSeconds = $breakMinutes * 60;

        foreach ($entries as $entry) {
            $startTs = strtotime((string)($entry['started_at'] ?? $entry['logged_at'] ?? ''));
            $endTs = strtotime((string)($entry['ended_at'] ?? ''));
            if ($startTs === false) {
                $startTs = null;
            }
            if ($endTs === false) {
                $endTs = null;
            }

            $gapOk = true;
            if ($startTs !== null && $prevEndTs !== null) {
                $gapOk = ($startTs - $prevEndTs) <= $breakSeconds;
            }
            if (!$gapOk) {
                $sessionTotal = 0;
                $prevEndTs = null;
            }

            $sessionTotal += max(0, (int)($entry['minutes_spent'] ?? 0));
            if ($endTs !== null) {
                $prevEndTs = max($prevEndTs ?? 0, $endTs);
            } elseif ($startTs !== null) {
                $prevEndTs = max($prevEndTs ?? 0, $startTs);
            }

            if ((int)($entry['id'] ?? 0) === $entryId) {
                $entrySession = $sessionTotal;
            }
        }

        if ($entrySession === null) {
            $entrySession = $sessionTotal;
        }

        // For updates the session total before this change includes the
        // entry's previous minutes; for creates previous is 0.
        $before = $entrySession - $entryMinutes + $previousMinutes;
        return [$before > 0 ? $before : 0, $entrySession];
    }

    /**
     * Resolve the manager(s) of the executor referenced in the context:
     * team managers, then the user's creator (hierarchy), then the task manager.
     * The executor itself is never notified.
     *
     * @return int[]
     */
    private function resolveManagerIds(array $context): array
    {
        $executorId = (int)($context['user_id'] ?? $context['task_assignee_id'] ?? 0);
        $ids = [];

        if ($executorId > 0) {
            foreach ($this->workflow->findManagerIdsByMember($executorId) as $managerId) {
                if ((int)$managerId > 0) {
                    $ids[] = (int)$managerId;
                }
            }
            $user = $this->users->findById($executorId);
            $creatorId = (int)($user['created_by_user_id'] ?? 0);
            if ($creatorId > 0) {
                $ids[] = $creatorId;
            }
        }

        $taskPublicId = (string)($context['task_public_id'] ?? '');
        if ($taskPublicId !== '') {
            $taskManagerId = $this->workflow->taskManagerUserId($taskPublicId);
            if ($taskManagerId !== null && $taskManagerId > 0) {
                $ids[] = $taskManagerId;
            }
        }

        return array_values(array_unique(array_filter(
            $ids,
            static fn(int $id): bool => $id > 0 && $id !== $executorId
        )));
    }

    /**
     * Replace {user} / {task} / {minutes} / {total} / {threshold} / {day}
     * placeholders in notification texts with context values.
     */
    private function interpolate(string $text, array $context): string
    {
        $values = [
            '{user}' => (string)($context['user_full_name'] ?? $context['user_login'] ?? ''),
            '{task}' => (string)($context['task_title'] ?? ''),
            '{minutes}' => (string)($context['minutes_spent'] ?? ''),
            '{total}' => (string)($context['total_minutes'] ?? ''),
            '{threshold}' => (string)($context['threshold_minutes'] ?? ''),
            '{day}' => (string)($context['day'] ?? ''),
        ];

        return strtr($text, $values);
    }
}
