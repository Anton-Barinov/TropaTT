<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Intake\IntakeItemActivityRepository;
use Api\Model\Intake\IntakeItemRepository;
use Api\System\Library\Language\LanguageManager;
use Api\System\Library\Language\TranslatableTrait;
use Api\System\Library\Support\Ulid;

final class IntakeItemService
{
    use TranslatableTrait;
    private const VALID_SOURCE_TYPES = ['manual', 'client', 'api', 'webhook', 'email', 'ai', 'import', 'system'];

    private const VALID_STATUS_TRANSITIONS = [
        'pending' => ['accepted', 'rejected', 'snoozed', 'duplicate'],
        'snoozed' => ['pending', 'accepted', 'rejected', 'duplicate'],
        'rejected' => ['pending'],
        'duplicate' => ['pending'],
    ];

    private const ERROR_CODES = [
        'NOT_FOUND' => 'INTAKE_NOT_FOUND',
        'FORBIDDEN' => 'INTAKE_FORBIDDEN',
        'INVALID_STATUS' => 'INTAKE_INVALID_STATUS',
        'INVALID_TRANSITION' => 'INTAKE_INVALID_STATUS_TRANSITION',
        'TITLE_REQUIRED' => 'INTAKE_TITLE_REQUIRED',
        'REASON_REQUIRED' => 'INTAKE_REASON_REQUIRED',
        'SNOOZED_UNTIL_REQUIRED' => 'INTAKE_SNOOZED_UNTIL_REQUIRED',
        'DUPLICATE_TARGET_REQUIRED' => 'INTAKE_DUPLICATE_TARGET_REQUIRED',
        'DUPLICATE_TARGET_NOT_FOUND' => 'INTAKE_DUPLICATE_TARGET_NOT_FOUND',
        'ALREADY_ACCEPTED' => 'INTAKE_ALREADY_ACCEPTED',
        'ACCEPT_TASK_CREATE_FAILED' => 'INTAKE_ACCEPT_TASK_CREATE_FAILED',
        'ROW_VERSION_CONFLICT' => 'ROW_VERSION_CONFLICT',
        'FIELD_NOT_EDITABLE' => 'INTAKE_FIELD_NOT_EDITABLE',
        'PROJECT_NOT_FOUND' => 'INTAKE_PROJECT_NOT_FOUND',
        'CLIENT_NOT_FOUND' => 'INTAKE_CLIENT_NOT_FOUND',
        'CONTACT_NOT_FOUND' => 'INTAKE_CONTACT_NOT_FOUND',
        'ASSIGNEE_NOT_FOUND' => 'INTAKE_ASSIGNEE_NOT_FOUND',
    ];

    /** @var array<string, true> */
    private array $nonEditableFields = [
        'status' => true,
        'accepted_task_id' => true,
        'duplicate_task_id' => true,
        'duplicate_intake_item_id' => true,
        'creator_user_id' => true,
        'created_at' => true,
        'deleted_at' => true,
    ];

    public function __construct(
        private readonly IntakeItemRepository $repository,
        private readonly IntakeItemActivityRepository $activityRepository,
        private readonly TaskService $taskService,
        private readonly ProjectService $projectService,
        private readonly ?NotificationService $notificationService = null,
        ?LanguageManager $lang = null
    ) {
        $this->lang = $lang ?? new LanguageManager(__DIR__ . '/../../language');
    }

    /**
     * @param array<string,mixed> $filters
     * @param array<string,mixed> $actor
     * @return array<string,mixed>|string
     */
    public function list(array $filters, array $actor): array|string
    {
        $isRoot = (bool)($actor['is_root'] ?? false);
        $actorId = (int)($actor['id'] ?? 0);
        $result = $this->repository->list($filters, $actorId, $isRoot);

        return [
            'items' => $result['items'],
            'meta' => [
                'pagination' => [
                    'page' => $result['page'],
                    'limit' => $result['limit'],
                    'total' => $result['total'],
                    'pages' => (int)ceil($result['total'] / max(1, $result['limit'])),
                ],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>|string
     */
    public function create(array $input, array $actor): array|string
    {
        $title = trim((string)($input['title'] ?? ''));
        if ($title === '') {
            return self::ERROR_CODES['TITLE_REQUIRED'];
        }
        if (mb_strlen($title) > 255) {
            $title = mb_substr($title, 0, 255);
        }

        $description = trim((string)($input['description'] ?? ''));
        if (mb_strlen($description) > 65535) {
            return $this->error('FIELD_TOO_LONG', $this->t('intake/field_too_long', 'Description exceeds 65535 characters'));
        }

        $sourceType = (string)($input['source_type'] ?? 'manual');
        if (!in_array($sourceType, self::VALID_SOURCE_TYPES, true)) {
            $sourceType = 'manual';
        }

        $extraJson = null;
        if (isset($input['extra'])) {
            $encoded = json_encode($input['extra'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false || strlen($encoded) > 65535) {
                return $this->error('VALIDATION_ERROR', $this->t('intake/extra_json_invalid', 'Extra JSON exceeds 65535 characters or invalid'));
            }
            $extraJson = $encoded;
        }

        $projectId = null;
        if (!empty($input['project_public_id'])) {
            $projectId = $this->repository->projectIdByPublicId((string)$input['project_public_id']);
            if ($projectId === null) {
                return self::ERROR_CODES['PROJECT_NOT_FOUND'];
            }
        }

        $clientId = null;
        if (!empty($input['client_public_id'])) {
            $clientId = $this->repository->clientIdByPublicId((string)$input['client_public_id']);
            if ($clientId === null) {
                return self::ERROR_CODES['CLIENT_NOT_FOUND'];
            }
        }

        $contactId = null;
        if (!empty($input['contact_public_id'])) {
            $contactId = $this->repository->contactIdByPublicId((string)$input['contact_public_id']);
            if ($contactId === null) {
                return self::ERROR_CODES['CONTACT_NOT_FOUND'];
            }
        }

        $assigneeUserId = null;
        if (!empty($input['assignee_user_id'])) {
            $assigneeUserId = (int)$input['assignee_user_id'];
        }

        $publicId = Ulid::generate('iin');
        $now = gmdate('Y-m-d H:i:s');

        $item = $this->repository->create([
            'public_id' => $publicId,
            'project_id' => $projectId,
            'client_id' => $clientId,
            'contact_id' => $contactId,
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'status' => 'pending',
            'priority_code' => !empty($input['priority_code']) ? (string)$input['priority_code'] : null,
            'source_type' => $sourceType,
            'source_ref' => !empty($input['source_ref']) ? (string)$input['source_ref'] : null,
            'source_email' => !empty($input['source_email']) ? (string)$input['source_email'] : null,
            'external_source' => !empty($input['external_source']) ? (string)$input['external_source'] : null,
            'external_id' => !empty($input['external_id']) ? (string)$input['external_id'] : null,
            'extra_json' => $extraJson,
            'due_at' => !empty($input['due_at']) ? (string)$input['due_at'] : null,
            'assignee_user_id' => $assigneeUserId,
            'creator_user_id' => (int)($actor['id'] ?? 0),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Log created activity
        $this->logActivity($item['id'], $actor, 'created');

        // @todo TZ §16: send notification for intake.created / intake.assigned via $this->notificationService

        return $item;
    }

    /**
     * @param array<string,mixed> $actor
     */
    public function get(string $publicId, array $actor): ?array
    {
        $item = $this->repository->findByPublicId($publicId);
        if (!$item || ($item['deleted_at'] ?? null) !== null) {
            return null;
        }
        return $item;
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>|string|null
     */
    public function update(string $publicId, array $input, array $actor): array|string|null
    {
        $item = $this->repository->findByPublicId($publicId);
        if (!$item || ($item['deleted_at'] ?? null) !== null) {
            return null;
        }

        if (array_key_exists('row_version', $input)) {
            $expected = (int)$input['row_version'];
            $current = (int)($item['row_version'] ?? 0);
            if ($expected > 0 && $expected !== $current) {
                return 'ROW_VERSION_CONFLICT';
            }
        }

        // Check for non-editable fields
        foreach ($input as $key => $value) {
            if (isset($this->nonEditableFields[$key])) {
                return self::ERROR_CODES['FIELD_NOT_EDITABLE'];
            }
        }

        $set = [];
        $changedFields = [];

        if (array_key_exists('title', $input)) {
            $title = trim((string)$input['title']);
            if ($title === '') {
                return self::ERROR_CODES['TITLE_REQUIRED'];
            }
            $set['title'] = mb_substr($title, 0, 255);
            $changedFields[] = 'title';
        }

        if (array_key_exists('description', $input)) {
            $desc = trim((string)$input['description']);
            if (mb_strlen($desc) > 65535) {
                return $this->error('VALIDATION_ERROR', $this->t('intake/field_too_long', 'Description exceeds 65535 characters'));
            }
            $set['description'] = $desc !== '' ? $desc : null;
            $changedFields[] = 'description';
        }

        if (array_key_exists('project_public_id', $input)) {
            $pid = null;
            if (!empty($input['project_public_id'])) {
                $projectId = $this->repository->projectIdByPublicId((string)$input['project_public_id']);
                if ($projectId === null) {
                    return self::ERROR_CODES['PROJECT_NOT_FOUND'];
                }
                $pid = $projectId;
            }
            $set['project_id'] = $pid;
            $changedFields[] = 'project_id';
        }

        if (array_key_exists('client_public_id', $input)) {
            $cid = null;
            if (!empty($input['client_public_id'])) {
                $clientId = $this->repository->clientIdByPublicId((string)$input['client_public_id']);
                if ($clientId === null) {
                    return self::ERROR_CODES['CLIENT_NOT_FOUND'];
                }
                $cid = $clientId;
            }
            $set['client_id'] = $cid;
            $changedFields[] = 'client_id';
        }

        if (array_key_exists('contact_public_id', $input)) {
            $coid = null;
            if (!empty($input['contact_public_id'])) {
                $contactId = $this->repository->contactIdByPublicId((string)$input['contact_public_id']);
                if ($contactId === null) {
                    return self::ERROR_CODES['CONTACT_NOT_FOUND'];
                }
                $coid = $contactId;
            }
            $set['contact_id'] = $coid;
            $changedFields[] = 'contact_id';
        }

        if (array_key_exists('priority_code', $input)) {
            $set['priority_code'] = !empty($input['priority_code']) ? (string)$input['priority_code'] : null;
            $changedFields[] = 'priority_code';
        }

        if (array_key_exists('source_type', $input)) {
            $st = (string)$input['source_type'];
            if (!in_array($st, self::VALID_SOURCE_TYPES, true)) {
                $st = 'manual';
            }
            $set['source_type'] = $st;
            $changedFields[] = 'source_type';
        }

        if (array_key_exists('source_ref', $input)) {
            $set['source_ref'] = !empty($input['source_ref']) ? (string)$input['source_ref'] : null;
        }

        if (array_key_exists('source_email', $input)) {
            $set['source_email'] = !empty($input['source_email']) ? (string)$input['source_email'] : null;
        }

        if (array_key_exists('external_source', $input)) {
            $set['external_source'] = !empty($input['external_source']) ? (string)$input['external_source'] : null;
        }

        if (array_key_exists('external_id', $input)) {
            $set['external_id'] = !empty($input['external_id']) ? (string)$input['external_id'] : null;
        }

        if (array_key_exists('extra', $input)) {
            $encoded = json_encode($input['extra'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false || strlen($encoded) > 65535) {
                return $this->error('VALIDATION_ERROR', $this->t('intake/extra_json_invalid', 'Extra JSON exceeds 65535 characters or invalid'));
            }
            $set['extra_json'] = $encoded;
        }

        if (array_key_exists('due_at', $input)) {
            $set['due_at'] = !empty($input['due_at']) ? (string)$input['due_at'] : null;
            $changedFields[] = 'due_at';
        }

        if (array_key_exists('assignee_user_id', $input)) {
            $set['assignee_user_id'] = !empty($input['assignee_user_id']) ? (int)$input['assignee_user_id'] : null;
            $changedFields[] = 'assignee_user_id';
        }

        if ($set === []) {
            return $item;
        }

        $set['row_version'] = (int)($item['row_version'] ?? 0) + 1;
        $this->repository->updateByPublicId($publicId, $set);

        // Log activities for changed fields
        $itemId = (int)$item['id'];
        foreach ($changedFields as $field) {
            $oldVal = $this->getFieldDisplayValue($item, $field);
            $newVal = $this->getFieldDisplayValue($set, $field);
            if ($oldVal !== $newVal) {
                $this->logActivity($itemId, $actor, 'updated', $field, $oldVal, $newVal);
            }
        }

        $updated = $this->repository->findByPublicId($publicId);
        return $updated;
    }

    /**
     * @param array<string,mixed> $actor
     * @return bool|string
     */
    public function delete(string $publicId, array $actor): bool|string
    {
        $item = $this->repository->findByPublicId($publicId);
        if (!$item || ($item['deleted_at'] ?? null) !== null) {
            return false;
        }

        $now = gmdate('Y-m-d H:i:s');
        $deleted = $this->repository->softDeleteByPublicId($publicId, $now);
        if ($deleted) {
            $this->logActivity((int)$item['id'], $actor, 'deleted');
        }

        return $deleted;
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>|string|null
     */
    public function accept(string $publicId, array $input, array $actor): array|string|null
    {
        $item = $this->repository->findByPublicId($publicId);
        if (!$item || ($item['deleted_at'] ?? null) !== null) {
            return null;
        }

        if ((string)($item['accepted_task_id'] ?? '') !== '' && (int)($item['accepted_task_id'] ?? 0) > 0) {
            return self::ERROR_CODES['ALREADY_ACCEPTED'];
        }

        $currentStatus = (string)($item['status'] ?? 'pending');
        if (!in_array('accepted', self::VALID_STATUS_TRANSITIONS[$currentStatus] ?? [], true)) {
            return self::ERROR_CODES['INVALID_TRANSITION'];
        }

        if (array_key_exists('row_version', $input)) {
            $expected = (int)$input['row_version'];
            $current = (int)($item['row_version'] ?? 0);
            if ($expected > 0 && $expected !== $current) {
                return 'ROW_VERSION_CONFLICT';
            }
        }

        $title = !empty($input['title']) ? trim((string)$input['title']) : (string)($item['title'] ?? '');
        $description = array_key_exists('description', $input) ? trim((string)$input['description']) : ((string)($item['description'] ?? ''));
        $projectPublicId = !empty($input['project_public_id']) ? (string)$input['project_public_id'] : ((string)($item['project_public_id'] ?? ''));
        $priority = !empty($input['priority']) ? (string)$input['priority'] : ((string)($item['priority_code'] ?? 'normal'));
        $dueAt = !empty($input['due_at']) ? (string)$input['due_at'] : ((string)($item['due_at'] ?? ''));
        $assigneeUserId = !empty($input['assignee_user_id']) ? (int)$input['assignee_user_id'] : ((int)($item['assignee_user_id'] ?? 0));

        // Create task via TaskService
        $taskInput = [
            'title' => $title,
            'description' => $description,
            'status' => !empty($input['status']) ? (string)$input['status'] : 'new',
            'priority' => $priority,
        ];

        if ($projectPublicId !== '') {
            $taskInput['project_public_id'] = $projectPublicId;
        }

        if ($dueAt !== '') {
            $taskInput['due_at'] = $dueAt;
        }

        if ($assigneeUserId > 0) {
            $taskInput['assignee_user_id'] = $assigneeUserId;
        }

        $task = $this->taskService->create($taskInput, $actor);
        if ($task === 'PROJECT_NOT_FOUND') {
            return self::ERROR_CODES['PROJECT_NOT_FOUND'];
        }
        if (!is_array($task)) {
            return self::ERROR_CODES['ACCEPT_TASK_CREATE_FAILED'];
        }

        $taskId = (int)($task['id'] ?? 0);
        $now = gmdate('Y-m-d H:i:s');

        $this->repository->updateByPublicId($publicId, [
            'status' => 'accepted',
            'accepted_task_id' => $taskId > 0 ? $taskId : null,
            'row_version' => (int)($item['row_version'] ?? 0) + 1,
            'updated_at' => $now,
        ]);

        // @todo TZ §16: send notification for intake.accepted via $this->notificationService

        $this->logActivity((int)$item['id'], $actor, 'accepted');
        $this->logActivity((int)$item['id'], $actor, 'linked_task_created', null, null, null, 'Task: ' . $task['public_id']);

        $updated = $this->repository->findByPublicId($publicId);
        return [
            'item' => $updated,
            'task' => $task,
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>|string|null
     */
    public function reject(string $publicId, array $input, array $actor): array|string|null
    {
        $item = $this->repository->findByPublicId($publicId);
        if (!$item || ($item['deleted_at'] ?? null) !== null) {
            return null;
        }

        $currentStatus = (string)($item['status'] ?? 'pending');
        if (!in_array('rejected', self::VALID_STATUS_TRANSITIONS[$currentStatus] ?? [], true)) {
            return self::ERROR_CODES['INVALID_TRANSITION'];
        }

        if (array_key_exists('row_version', $input)) {
            $expected = (int)$input['row_version'];
            $current = (int)($item['row_version'] ?? 0);
            if ($expected > 0 && $expected !== $current) {
                return 'ROW_VERSION_CONFLICT';
            }
        }

        $reason = trim((string)($input['reason'] ?? ''));
        if ($reason === '') {
            return self::ERROR_CODES['REASON_REQUIRED'];
        }

        $now = gmdate('Y-m-d H:i:s');
        $this->repository->updateByPublicId($publicId, [
            'status' => 'rejected',
            'resolution_note' => $reason,
            'resolved_by_user_id' => (int)($actor['id'] ?? 0),
            'resolved_at' => $now,
            'row_version' => (int)($item['row_version'] ?? 0) + 1,
            'updated_at' => $now,
        ]);

        // @todo TZ §16: send notification for intake.rejected via $this->notificationService

        $this->logActivity((int)$item['id'], $actor, 'rejected', null, null, null, $reason);

        return $this->repository->findByPublicId($publicId);
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>|string|null
     */
    public function snooze(string $publicId, array $input, array $actor): array|string|null
    {
        $item = $this->repository->findByPublicId($publicId);
        if (!$item || ($item['deleted_at'] ?? null) !== null) {
            return null;
        }

        $currentStatus = (string)($item['status'] ?? 'pending');
        if (!in_array('snoozed', self::VALID_STATUS_TRANSITIONS[$currentStatus] ?? [], true)) {
            return self::ERROR_CODES['INVALID_TRANSITION'];
        }

        if (array_key_exists('row_version', $input)) {
            $expected = (int)$input['row_version'];
            $current = (int)($item['row_version'] ?? 0);
            if ($expected > 0 && $expected !== $current) {
                return 'ROW_VERSION_CONFLICT';
            }
        }

        $snoozedUntil = trim((string)($input['snoozed_until'] ?? ''));
        if ($snoozedUntil === '') {
            return self::ERROR_CODES['SNOOZED_UNTIL_REQUIRED'];
        }

        $reason = !empty($input['reason']) ? trim((string)$input['reason']) : null;

        $now = gmdate('Y-m-d H:i:s');
        $this->repository->updateByPublicId($publicId, [
            'status' => 'snoozed',
            'snoozed_until' => $snoozedUntil,
            'row_version' => (int)($item['row_version'] ?? 0) + 1,
            'updated_at' => $now,
        ]);

        // @todo TZ §16: send notification for intake.snoozed via $this->notificationService

        $this->logActivity((int)$item['id'], $actor, 'snoozed', null, null, null, $reason);

        return $this->repository->findByPublicId($publicId);
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>|string|null
     */
    public function markDuplicate(string $publicId, array $input, array $actor): array|string|null
    {
        $item = $this->repository->findByPublicId($publicId);
        if (!$item || ($item['deleted_at'] ?? null) !== null) {
            return null;
        }

        $currentStatus = (string)($item['status'] ?? 'pending');
        if (!in_array('duplicate', self::VALID_STATUS_TRANSITIONS[$currentStatus] ?? [], true)) {
            return self::ERROR_CODES['INVALID_TRANSITION'];
        }

        if (array_key_exists('row_version', $input)) {
            $expected = (int)$input['row_version'];
            $current = (int)($item['row_version'] ?? 0);
            if ($expected > 0 && $expected !== $current) {
                return 'ROW_VERSION_CONFLICT';
            }
        }

        $duplicateIntakeItemPublicId = trim((string)($input['duplicate_intake_item_public_id'] ?? ''));
        $duplicateTaskPublicId = trim((string)($input['duplicate_task_public_id'] ?? ''));

        if ($duplicateIntakeItemPublicId === '' && $duplicateTaskPublicId === '') {
            return self::ERROR_CODES['DUPLICATE_TARGET_REQUIRED'];
        }

        $duplicateIntakeItemId = null;
        $duplicateTaskId = null;
        $reason = !empty($input['reason']) ? trim((string)$input['reason']) : null;

        if ($duplicateIntakeItemPublicId !== '') {
            $dupItem = $this->repository->findByPublicId($duplicateIntakeItemPublicId);
            if (!$dupItem) {
                return self::ERROR_CODES['DUPLICATE_TARGET_NOT_FOUND'];
            }
            $duplicateIntakeItemId = (int)$dupItem['id'];
        }

        if ($duplicateTaskPublicId !== '') {
            $dupTaskId = $this->repository->taskIdByPublicId($duplicateTaskPublicId);
            if ($dupTaskId === null) {
                return self::ERROR_CODES['DUPLICATE_TARGET_NOT_FOUND'];
            }
            $duplicateTaskId = $dupTaskId;
        }

        $now = gmdate('Y-m-d H:i:s');
        $this->repository->updateByPublicId($publicId, [
            'status' => 'duplicate',
            'duplicate_intake_item_id' => $duplicateIntakeItemId,
            'duplicate_task_id' => $duplicateTaskId,
            'resolution_note' => $reason,
            'resolved_by_user_id' => (int)($actor['id'] ?? 0),
            'resolved_at' => $now,
            'row_version' => (int)($item['row_version'] ?? 0) + 1,
            'updated_at' => $now,
        ]);

        // @todo TZ §16: send notification for intake.duplicate via $this->notificationService

        $this->logActivity((int)$item['id'], $actor, 'marked_duplicate', null, null, null, $reason);

        return $this->repository->findByPublicId($publicId);
    }

    /**
     * @param array<string,mixed> $actor
     * @return array<string,mixed>|string|null
     */
    public function reopen(string $publicId, array $actor): array|string|null
    {
        $item = $this->repository->findByPublicId($publicId);
        if (!$item || ($item['deleted_at'] ?? null) !== null) {
            return null;
        }

        $currentStatus = (string)($item['status'] ?? 'pending');
        if (!in_array($currentStatus, ['rejected', 'snoozed', 'duplicate'], true)) {
            return self::ERROR_CODES['INVALID_TRANSITION'];
        }

        $now = gmdate('Y-m-d H:i:s');
        $this->repository->updateByPublicId($publicId, [
            'status' => 'pending',
            'snoozed_until' => null,
            'resolution_note' => null,
            'resolved_by_user_id' => null,
            'resolved_at' => null,
            'row_version' => (int)($item['row_version'] ?? 0) + 1,
            'updated_at' => $now,
        ]);

        $this->logActivity((int)$item['id'], $actor, 'reopened');

        return $this->repository->findByPublicId($publicId);
    }

    /**
     * @param array<string,mixed> $filters
     * @param array<string,mixed> $actor
     * @return array<string,mixed>|string|null
     */
    public function activities(string $publicId, array $filters, array $actor): array|string|null
    {
        $item = $this->repository->findByPublicId($publicId);
        if (!$item || ($item['deleted_at'] ?? null) !== null) {
            return null;
        }

        $activities = $this->activityRepository->listByIntakeItemId((int)$item['id']);
        return [
            'items' => $activities,
        ];
    }

    /**
     * @param array<string,mixed> $item
     */
    private function getFieldDisplayValue(array $item, string $field): string
    {
        return match ($field) {
            'project_id' => (string)($item['project_title'] ?? $item['project_id'] ?? ''),
            'client_id' => (string)($item['client_name'] ?? $item['client_id'] ?? ''),
            'contact_id' => (string)($item['contact_name'] ?? $item['contact_id'] ?? ''),
            'priority_code' => (string)($item['priority_code'] ?? ''),
            'assignee_user_id' => (string)($item['assignee_name'] ?? $item['assignee_user_id'] ?? ''),
            'due_at' => (string)($item['due_at'] ?? ''),
            'source_type' => (string)($item['source_type'] ?? ''),
            default => (string)($item[$field] ?? ''),
        };
    }

    /**
     * @param array<string,mixed> $actor
     */
    private function logActivity(
        int $intakeItemId,
        array $actor,
        string $eventType,
        ?string $fieldName = null,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?string $comment = null,
    ): void {
        $this->activityRepository->create([
            'public_id' => Ulid::generate('ina'),
            'intake_item_id' => $intakeItemId,
            'actor_user_id' => (int)($actor['id'] ?? 0),
            'event_type' => $eventType,
            'field_name' => $fieldName,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'comment' => $comment,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    private function error(string $code, string $message): string
    {
        return $code;
    }
}
