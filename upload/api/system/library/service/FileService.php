<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Comment\CommentRepository;
use Api\Model\File\FileRepository;
use Api\Model\Knowledge\KnowledgeRepository;
use Api\Model\Project\ProjectRepository;
use Api\Model\Recycle_bin\RecycleBinRepository;
use Api\Model\Task\TaskRepository;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Security\ActorPermission;
use Api\System\Library\Service\ExternalUserService;
use Api\System\Library\Support\Ulid;

final class FileService
{
    public function __construct(
        private readonly FileRepository $files,
        private readonly string $uploadsDir,
        private readonly string $quarantineDir,
        private readonly int $maxUploadSizeBytes,
        private readonly array $forbiddenExtensions,
        private readonly array $quarantineExtensions,
        private readonly array $quarantineMimePrefixes,
        private readonly TaskRepository $tasks,
        private readonly ProjectRepository $projects,
        private readonly KnowledgeRepository $knowledge,
        private readonly RecycleBinRepository $recycleBin,
        private readonly JsonLogger $logger,
        private readonly ?AiSemanticIndexService $semanticIndex = null,
        private readonly ?TaskActivityService $activity = null,
        private readonly ?ExternalUserService $externalUsers = null,
        private readonly ?CommentRepository $comments = null,
        private readonly ?\PDO $db = null
    ) {
    }

    public function create(array $input, array $rawFiles, int $uploaderUserId, array $actor): array
    {
        $organizationId = isset($actor['organization_id']) ? (int)$actor['organization_id'] : null;
        $publicId = Ulid::generate('fil');
        $now = gmdate('Y-m-d H:i:s');

        $entityType = trim((string)($input['entity_type'] ?? 'task'));
        $entityPublicId = trim((string)($input['entity_public_id'] ?? ''));
        if ($entityPublicId !== '' && !$this->canAccessEntity($entityType, $entityPublicId, $actor)) {
            throw new \RuntimeException('ENTITY_ACCESS_DENIED');
        }

        // M-3: Internal users' files default to internal visibility.
        // External users always create client-visible files.
        $isExternal = (bool)($actor['is_external'] ?? false);
        $isInternal = $isExternal ? 0 : 1;

        // L-10: External users must attach files to an accessible entity.
        // Orphan files (no entity_public_id) are not allowed for external actors.
        if ($isExternal && $entityPublicId === '') {
            throw new \RuntimeException('ENTITY_REQUIRED');
        }

        $storedPath = '';
        $mime = 'application/octet-stream';
        $size = 0;
        $name = 'file.bin';
        $isQuarantined = false;
        $quarantineReason = null;

        if (isset($rawFiles['file']) && is_array($rawFiles['file']) && (int)($rawFiles['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tmp = (string)$rawFiles['file']['tmp_name'];
            $name = $this->sanitizeFileName((string)$rawFiles['file']['name']);
            $mime = (string)($rawFiles['file']['type'] ?? $mime);
            $size = (int)($rawFiles['file']['size'] ?? 0);
            $this->assertUploadSize($size);
            $detectedMime = $this->detectMime($tmp);
            // SEC-001: Check forbidden BEFORE any disk write
            if ($this->isForbidden($name, $detectedMime)) {
                throw new \RuntimeException('FILE_TYPE_FORBIDDEN');
            }
            [$isQuarantined, $quarantineReason] = $this->quarantineDecision($name, $mime, $detectedMime);
            $mime = $detectedMime !== '' ? $detectedMime : $mime;
            $dir = rtrim($isQuarantined ? $this->quarantineDir : $this->uploadsDir, '/');
            $this->ensureDir($dir);
            // SEC-001: Store as .bin — no user-controlled extension on disk
            $storedPath = $dir . '/' . $publicId . '.bin';

            if (!move_uploaded_file($tmp, $storedPath)) {
                throw new \RuntimeException('UPLOAD_MOVE_FAILED');
            }
            @chmod($storedPath, 0640);
        } elseif (!empty($input['content_base64'])) {
            $name = $this->sanitizeFileName((string)($input['name'] ?? $name));
            $bin = base64_decode((string)$input['content_base64'], true);
            if ($bin === false) {
                throw new \RuntimeException('INVALID_BASE64');
            }
            $size = strlen($bin);
            $this->assertUploadSize($size);
            $mime = (string)($input['mime_type'] ?? $mime);
            $tmp = $this->writeTempProbe($bin);
            try {
                $detectedMime = $this->detectMime($tmp);
            } finally {
                @unlink($tmp);
            }
            // SEC-001: Check forbidden BEFORE any disk write
            if ($this->isForbidden($name, $detectedMime)) {
                throw new \RuntimeException('FILE_TYPE_FORBIDDEN');
            }
            [$isQuarantined, $quarantineReason] = $this->quarantineDecision($name, $mime, $detectedMime);
            $mime = $detectedMime !== '' ? $detectedMime : $mime;
            $dir = rtrim($isQuarantined ? $this->quarantineDir : $this->uploadsDir, '/');
            $this->ensureDir($dir);
            // SEC-001: Store as .bin — no user-controlled extension on disk
            $storedPath = $dir . '/' . $publicId . '.bin';
            file_put_contents($storedPath, $bin);
            @chmod($storedPath, 0640);
        } else {
            throw new \RuntimeException('FILE_REQUIRED');
        }

        // The file belongs to its linked entity, so it must carry the ENTITY's
        // organization, not the caller's. A bearer/MCP caller without an active
        // workspace resolved organization_id = null, and the file then vanished
        // for every workspace-scoped list/download (the entity org filter no
        // longer matched). Falls back to the actor's org when the entity has
        // none (for example a not-yet-scoped knowledge page).
        $fileOrganizationId = $this->resolveEntityOrganizationId($entityType, $entityPublicId) ?? $organizationId;

        $this->files->create([
            'public_id' => $publicId,
            'entity_type' => $entityType,
            'entity_public_id' => $entityPublicId,
            'uploader_user_id' => $uploaderUserId,
            'original_name' => $name,
            'storage_path' => $storedPath,
            'mime_type' => $mime,
            'size_bytes' => $size,
            'is_deleted' => 0,
            'is_internal' => $isInternal,
            'created_at' => $now,
        ], $fileOrganizationId);

        $created = $this->files->findByPublicId($publicId, $organizationId) ?: ['public_id' => $publicId];

        if ($entityType === 'task' && $entityPublicId !== '') {
            $task = $this->tasks->findByPublicId($entityPublicId, $organizationId);
            if ($task) {
                $this->activity?->recordFileAdded($task, [
                    'public_id' => $publicId,
                    'original_name' => $name,
                    'mime_type' => $mime,
                    'size_bytes' => $size,
                ], $actor, [
                    'file_public_id' => $publicId,
                    'original_name' => $name,
                    'mime_type' => $mime,
                    'size_bytes' => $size,
                ]);
            }
        }

        $actorPublicId = (string)($actor['public_id'] ?? '');
        $this->logger->audit([
            'actor_public_id' => $actorPublicId,
            'entity_type' => 'file',
            'entity_public_id' => $publicId,
            'action' => 'file_upload',
            'details' => [
                'entity_type' => $entityType,
                'entity_public_id' => $entityPublicId,
                'original_name' => $name,
                'mime_type' => $mime,
                'size_bytes' => $size,
                'is_quarantined' => $isQuarantined,
                'quarantine_reason' => $quarantineReason,
            ],
        ]);
        if ($isQuarantined) {
            $this->logger->security([
                'actor_public_id' => $actorPublicId,
                'event_type' => 'file_quarantined',
                'details' => [
                    'file_public_id' => $publicId,
                    'reason' => $quarantineReason,
                    'original_name' => $name,
                ],
            ]);
        }

        return $this->presentForApi($created);
    }

    public function get(string $publicId, array $actor): ?array
    {
        $organizationId = isset($actor['organization_id']) ? (int)$actor['organization_id'] : null;
        $file = $this->files->findByPublicId($publicId, $organizationId);
        if (!$file) {
            return null;
        }

        if (!$this->canAccessFile($file, $actor)) {
            return null;
        }

        return $this->presentForApi($file);
    }

    public function listByEntity(string $entityType, string $entityPublicId, array $actor): ?array
    {
        $organizationId = isset($actor['organization_id']) ? (int)$actor['organization_id'] : null;
        $type = trim($entityType);
        $entityId = trim($entityPublicId);
        if ($type === '' || $entityId === '') {
            return [];
        }

        if (!$this->canAccessEntity($type, $entityId, $actor)) {
            return null;
        }

        // Access is already gated by canAccessEntity() above. Do NOT narrow the
        // listing by the actor's workspace org: a file row whose organization_id
        // does not match the actor's current workspace (legacy null-org rows, or
        // rows written before this fix) must still appear for the entity it is
        // attached to, otherwise an uploaded file "disappears" after refresh.
        $items = $this->files->listByEntity($type, $entityId, null);

        // M-3: Filter out internal files for external users.
        if ((bool)($actor['is_external'] ?? false)) {
            $items = array_values(array_filter($items, fn(array $file): bool => (int)($file['is_internal'] ?? 0) === 0));
        }

        return array_map(fn(array $file): array => $this->presentForApi($file), $items);
    }

    public function delete(string $publicId, array $actor): bool
    {
        $organizationId = isset($actor['organization_id']) ? (int)$actor['organization_id'] : null;
        $file = $this->files->findByPublicId($publicId, $organizationId);
        if (!$file) {
            return false;
        }
        if (!$this->canDeleteFile($file, $actor)) {
            return false;
        }

        $deleted = $this->files->softDelete($publicId, gmdate('Y-m-d H:i:s'), $organizationId);
        if ($deleted) {
            $this->semanticIndex?->removeEntityDocument('file', $publicId);
            $path = (string)$file['storage_path'];
            if ($path !== '' && is_file($path)) {
                @rename($path, $path . '.deleted');
            }
            if (($file['entity_type'] ?? '') === 'task' && ($file['entity_public_id'] ?? '') !== '') {
                $task = $this->tasks->findByPublicId((string)$file['entity_public_id'], $organizationId);
                if ($task) {
                    $this->activity?->recordFileDeleted($task, [
                        'public_id' => $publicId,
                        'file_public_id' => $publicId,
                        'original_name' => (string)($file['original_name'] ?? ''),
                    ], $actor);
                }
            }
            $this->queueRecycleBinEntry($file, $actor);
            $this->logger->audit([
                'actor_public_id' => (string)($actor['public_id'] ?? ''),
                'entity_type' => 'file',
                'entity_public_id' => $publicId,
                'action' => 'file_delete',
                'details' => [
                    'original_name' => (string)($file['original_name'] ?? ''),
                    'is_quarantined' => $this->isQuarantinedPath((string)($file['storage_path'] ?? '')),
                ],
            ]);
        }

        return $deleted;
    }

    public function canDownloadInternal(string $publicId, array $actor): array
    {
        $organizationId = isset($actor['organization_id']) ? (int)$actor['organization_id'] : null;
        $file = $this->files->findByPublicId($publicId, $organizationId);
        if (!$file || (int)($file['is_deleted'] ?? 0) === 1) {
            return ['ok' => false, 'error' => 'FILE_NOT_FOUND'];
        }
        if (!$this->canAccessFile($file, $actor)) {
            return ['ok' => false, 'error' => 'FILE_NOT_FOUND'];
        }
        $path = (string)($file['storage_path'] ?? '');
        if ($path === '' || !is_file($path)) {
            // SEC-001: Backward compatibility — try .bin path as fallback
            $fallbackPath = $this->resolveStoragePath((string)($file['public_id'] ?? ''), $path);
            if ($fallbackPath === null || !is_file($fallbackPath)) {
                return ['ok' => false, 'error' => 'FILE_NOT_ON_STORAGE'];
            }
            $path = $fallbackPath;
        }
        $safeStoragePath = $this->validatedStoragePath($path);
        if ($safeStoragePath === null) {
            return ['ok' => false, 'error' => 'FILE_NOT_ON_STORAGE'];
        }
        $path = $safeStoragePath;

        if ($this->isQuarantinedPath($path)) {
            $this->logger->security([
                'actor_public_id' => (string)($actor['public_id'] ?? ''),
                'event_type' => 'file_download_quarantined_safe_stream',
                'details' => [
                    'file_public_id' => $publicId,
                    'original_name' => (string)($file['original_name'] ?? ''),
                    'mime_override' => 'application/octet-stream',
                ],
            ]);
        }

        $this->logger->audit([
            'actor_public_id' => (string)($actor['public_id'] ?? ''),
            'entity_type' => 'file',
            'entity_public_id' => $publicId,
            'action' => 'file_download',
            'details' => [
                'original_name' => (string)($file['original_name'] ?? ''),
                'mime_type' => (string)($file['mime_type'] ?? ''),
            ],
        ]);

        $originalName = (string)($file['original_name'] ?? '');
        $mime = $this->quarantineMimeOverride($path, (string)$file['mime_type'], $originalName);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $isSafeRasterImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)
            && in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);

        return [
            'ok' => true,
            'path' => $path,
            'name' => $originalName !== '' ? $originalName : 'file.bin',
            'mime' => $mime,
            'size' => (int)$file['size_bytes'],
            'inline' => $isSafeRasterImage,
        ];
    }

    /**
     * Organization that owns the linked entity (task/project/knowledge page).
     *
     * A file must inherit the entity's organization rather than the caller's
     * active workspace: a bearer/MCP caller with no resolved workspace used to
     * write files with organization_id = null, and a workspace-scoped list then
     * filtered them out. Entity lookups here intentionally pass a null org —
     * access was already validated by canAccessEntity().
     */
    private function resolveEntityOrganizationId(string $entityType, string $entityPublicId): ?int
    {
        if ($entityPublicId === '') {
            return null;
        }
        try {
            $row = match ($entityType) {
                'task' => $this->tasks->findByPublicId($entityPublicId, null),
                'project' => $this->projects->findByPublicId($entityPublicId, null),
                'knowledge_page' => $this->knowledge->page($entityPublicId),
                'comment' => $this->resolveCommentParentRow($entityPublicId),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
        $orgId = is_array($row) ? (int)($row['organization_id'] ?? 0) : 0;
        return $orgId > 0 ? $orgId : null;
    }

    private function resolveCommentParentRow(string $commentPublicId): ?array
    {
        if ($this->comments === null) {
            return null;
        }
        $comment = $this->comments->findByPublicId($commentPublicId);
        if (!$comment) {
            return null;
        }
        if (!empty($comment['task_public_id'])) {
            return $this->tasks->findByPublicId((string)$comment['task_public_id'], null);
        }
        if (!empty($comment['project_public_id'])) {
            return $this->projects->findByPublicId((string)$comment['project_public_id'], null);
        }
        return null;
    }

    /** @param array<string,mixed> $file */
    /** @param array<string,mixed> $actor */
    public function canDeleteFile(array $file, array $actor): bool
    {
        if (!$this->canAccessFile($file, $actor)) {
            return false;
        }

        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        $actorId = (int)($actor['id'] ?? 0);
        if ($actorId <= 0) {
            return false;
        }

        $uploaderId = (int)($file['uploader_user_id'] ?? 0);
        // The uploader can always delete their own file
        if ($uploaderId > 0 && $actorId === $uploaderId) {
            return true;
        }

        // External users (guests) can never delete someone else's file
        if (!empty((int)($actor['is_external'] ?? 0))) {
            return false;
        }

        // Permission = capability: task.manage may remove attachments of their
        // workspace (the upload/download routes are gated by task.manage too).
        if (ActorPermission::canManageTasks($actor)) {
            return true;
        }

        $roleCode = (string)($actor['role_code'] ?? '');
        if (in_array($roleCode, ['admin', 'super_admin'], true)) {
            return true;
        }

        $organizationId = isset($actor['organization_id']) ? (int)$actor['organization_id'] : null;
        $entityType = (string)($file['entity_type'] ?? '');
        $entityPublicId = (string)($file['entity_public_id'] ?? '');

        if ($entityType === 'task') {
            $task = $this->tasks->findByPublicId($entityPublicId, $organizationId);
            if ($task) {
                return (int)($task['creator_user_id'] ?? 0) === $actorId
                    || (int)($task['project_creator_user_id'] ?? 0) === $actorId
                    || (int)($task['project_manager_user_id'] ?? 0) === $actorId
                    || (int)($task['project_team_manager_user_id'] ?? 0) === $actorId;
            }
        }

        if ($entityType === 'project') {
            $project = $this->projects->findByPublicId($entityPublicId, $organizationId);
            if ($project) {
                return (int)($project['created_by_user_id'] ?? 0) === $actorId
                    || (int)($project['manager_user_id'] ?? 0) === $actorId
                    || (int)($project['team_manager_user_id'] ?? 0) === $actorId;
            }
        }

        return false;
    }

    /** @param array<string,mixed> $file */
    /** @param array<string,mixed> $actor */
    private function canAccessFile(array $file, array $actor): bool
    {
        // M-3: External users cannot access internal files.
        if ((bool)($actor['is_external'] ?? false) && (int)($file['is_internal'] ?? 0) === 1) {
            return false;
        }

        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        $roleCode = (string)($actor['role_code'] ?? '');
        if (in_array($roleCode, ['admin', 'super_admin'], true)) {
            return true;
        }

        $actorId = (int)($actor['id'] ?? 0);
        if ($actorId <= 0) {
            return false;
        }

        if ((int)($file['uploader_user_id'] ?? 0) === $actorId) {
            return true;
        }

        $entityType = (string)($file['entity_type'] ?? '');
        $entityPublicId = (string)($file['entity_public_id'] ?? '');
        if ($entityType === '' || $entityPublicId === '') {
            return false;
        }

        return $this->canAccessEntity($entityType, $entityPublicId, $actor);
    }

    /** @param array<string,mixed> $actor */
    private function canAccessEntity(string $entityType, string $entityPublicId, array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        $roleCode = (string)($actor['role_code'] ?? '');
        if (in_array($roleCode, ['admin', 'super_admin'], true)) {
            return true;
        }

        $actorId = (int)($actor['id'] ?? 0);
        if ($actorId <= 0) {
            return false;
        }

        $organizationId = isset($actor['organization_id']) ? (int)$actor['organization_id'] : null;

        // RLS for client-portal guests. An external user is never a creator, assignee,
        // manager or team member, so every ownership check below would deny them — including
        // files attached to their own tasks, which is the main thing the portal exists for.
        // Scope them by counterparty instead, mirroring TaskService::canAccess() and
        // ProjectService::canAccess(). Unknown entity types stay closed.
        if (!empty((int)($actor['is_external'] ?? 0))) {
            if (!$this->externalUsers) {
                return false;
            }

            $isExecutor = $this->externalUsers->getExternalRole($actorId) === ExternalUserService::ROLE_EXECUTOR;
            $cpPublicId = $isExecutor ? '' : $this->externalUsers->getCounterpartyPublicId($actorId);
            if (!$isExecutor && $cpPublicId === '') {
                return false;
            }

            if ($entityType === 'task') {
                $task = $this->tasks->findByPublicId($entityPublicId, $organizationId);
                if (!$task) {
                    return false;
                }

                if ($isExecutor) {
                    return $this->externalUsers->hasExecutorProjectAccess($actorId, (int)($task['project_id'] ?? 0));
                }

                $taskClientPublicId = (string)($task['task_client_public_id'] ?? '');
                $projectClientPublicId = (string)($task['client_public_id'] ?? '');
                // L-9: Use canonical client: task-level if set, otherwise project-level.
                // This prevents a task with client X in project of client Y from being
                // visible to observers of both (mirrors TaskService::canAccess()).
                $effectiveClient = $taskClientPublicId !== '' ? $taskClientPublicId : $projectClientPublicId;

                return $effectiveClient !== '' && $effectiveClient === $cpPublicId;
            }

            if ($entityType === 'project') {
                $project = $this->projects->findByPublicId($entityPublicId, $organizationId);
                if (!$project) {
                    return false;
                }

                if ($isExecutor) {
                    return $this->externalUsers->hasExecutorProjectAccess($actorId, (int)($project['id'] ?? 0));
                }

                return (string)($project['client_public_id'] ?? '') === $cpPublicId;
            }

            if ($entityType === 'chat_message') {
                return $this->actorCanReadChatMessage($entityPublicId, $actor);
            }

            return false;
        }

        if ($entityType === 'chat_message') {
            return $this->actorCanReadChatMessage($entityPublicId, $actor);
        }

        if ($entityType === 'intake_item') {
            return $this->actorCanReadIntakeItem($entityPublicId, $actor);
        }

        // Permission = capability: task.manage may reach every task attachment
        // of their workspace, project.manage every project one; ownership
        // below stays the fallback for everyone else.
        $canManageEntity = match ($entityType) {
            'task' => ActorPermission::canManageTasks($actor),
            'project' => ActorPermission::canManageProjects($actor),
            default => false,
        };
        if ($canManageEntity) {
            $ownerRow = $entityType === 'task'
                ? $this->tasks->findByPublicId($entityPublicId, $organizationId)
                : $this->projects->findByPublicId($entityPublicId, $organizationId);
            if ($ownerRow && ActorPermission::sameOrganization($actor, $ownerRow)) {
                return true;
            }
        }

        if ($entityType === 'task') {
            $task = $this->tasks->findByPublicId($entityPublicId, $organizationId);
            if (!$task) {
                return false;
            }

            return (int)($task['creator_user_id'] ?? 0) === $actorId
                || (int)($task['assignee_user_id'] ?? 0) === $actorId
                || (int)($task['project_creator_user_id'] ?? 0) === $actorId
                || (int)($task['project_manager_user_id'] ?? 0) === $actorId
                || (int)($task['project_team_manager_user_id'] ?? 0) === $actorId
                || in_array($actorId, $this->decodeTeamMemberIds($task['project_team_member_user_ids'] ?? null), true);
        }

        if ($entityType === 'project') {
            $project = $this->projects->findByPublicId($entityPublicId, $organizationId);
            if (!$project) {
                return false;
            }

            return (int)($project['created_by_user_id'] ?? 0) === $actorId
                || (int)($project['manager_user_id'] ?? 0) === $actorId
                || (int)($project['team_manager_user_id'] ?? 0) === $actorId
                || in_array($actorId, $this->decodeTeamMemberIds($project['team_member_user_ids'] ?? null), true);
        }

        if ($entityType === 'knowledge_page') {
            return $this->knowledge->page($entityPublicId, $actor) !== null;
        }

        if ($entityType === 'comment') {
            if ($this->comments === null) {
                return false;
            }
            $comment = $this->comments->findByPublicId($entityPublicId);
            if (!$comment) {
                return false;
            }
            if (!empty($comment['task_public_id'])) {
                return $this->canAccessEntity('task', (string)$comment['task_public_id'], $actor);
            }
            if (!empty($comment['project_public_id'])) {
                return $this->canAccessEntity('project', (string)$comment['project_public_id'], $actor);
            }
            return false;
        }

        return false;
    }

    /** Attachments on chat messages are visible only to current members of the same workspace chat. */
    private function actorCanReadChatMessage(string $messagePublicId, array $actor): bool
    {
        $organizationId = (int)($actor['organization_id'] ?? 0);
        $actorId = (int)($actor['id'] ?? 0);
        if ($this->db === null || $organizationId <= 0 || $actorId <= 0 || $messagePublicId === '') {
            return false;
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT 1
                 FROM chat_messages cm
                 INNER JOIN chats c ON c.id = cm.chat_id AND c.organization_id = :chat_organization_id
                 INNER JOIN chat_participants cp ON cp.chat_id = c.id AND cp.user_id = :actor_id AND cp.organization_id = :participant_organization_id
                 WHERE cm.public_id = :message_public_id
                   AND cm.organization_id = :message_organization_id
                   AND cm.deleted_at IS NULL
                 LIMIT 1'
            );
            $stmt->execute([
                ':chat_organization_id' => $organizationId,
                ':actor_id' => $actorId,
                ':participant_organization_id' => $organizationId,
                ':message_public_id' => $messagePublicId,
                ':message_organization_id' => $organizationId,
            ]);
            return (bool)$stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /** Intake attachments follow the same workspace-scoped read capability as the Intake API. */
    private function actorCanReadIntakeItem(string $itemPublicId, array $actor): bool
    {
        if ($this->db === null || !empty((int)($actor['is_external'] ?? 0))) {
            return false;
        }
        $organizationId = (int)($actor['organization_id'] ?? 0);
        $permissionCodes = $actor['permission_codes'] ?? [];
        if ($organizationId <= 0 || $itemPublicId === '' || !is_array($permissionCodes)
            || (!in_array('*', $permissionCodes, true) && !in_array('intake.view', $permissionCodes, true) && !in_array('intake.manage', $permissionCodes, true))
        ) {
            return false;
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT 1 FROM intake_items
                 WHERE public_id = :public_id AND organization_id = :organization_id AND deleted_at IS NULL
                 LIMIT 1'
            );
            $stmt->execute([':public_id' => $itemPublicId, ':organization_id' => $organizationId]);
            return (bool)$stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param array<string,mixed> $file */
    /** @param array<string,mixed> $actor */
    private function queueRecycleBinEntry(array $file, array $actor): void
    {
        $publicId = (string)($file['public_id'] ?? '');
        if ($publicId === '') {
            return;
        }

        $organizationId = isset($actor['organization_id']) ? (int)$actor['organization_id'] : null;
        $existing = $this->recycleBin->findActiveByEntity('file', $publicId, $organizationId);
        if ($existing) {
            return;
        }

        $this->recycleBin->create([
            'public_id' => Ulid::generate('rcb'),
            'entity_type' => 'file',
            'entity_public_id' => $publicId,
            'payload' => json_encode([
                'original_name' => (string)($file['original_name'] ?? ''),
                'mime_type' => (string)($file['mime_type'] ?? ''),
                'size_bytes' => (int)($file['size_bytes'] ?? 0),
                'entity_type' => (string)($file['entity_type'] ?? ''),
                'entity_public_id' => (string)($file['entity_public_id'] ?? ''),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'deleted_by_user_id' => (int)($actor['id'] ?? 0),
            'deleted_at' => gmdate('Y-m-d H:i:s'),
            'restored_at' => null,
        ], $organizationId);
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
        // Restrict permissions: quarantine gets 700, uploads gets 750
        $quarantined = $this->quarantineDir !== '' && str_starts_with($dir, rtrim($this->quarantineDir, '/'));
        @chmod($dir, $quarantined ? 0700 : 0750);

        // SEC-001: Prevent directory listing where Options -Indexes is not set
        $indexFile = $dir . '/index.html';
        if (!is_file($indexFile)) {
            @file_put_contents($indexFile, '');
        }
    }

    private function sanitizeFileName(string $name): string
    {
        $normalized = str_replace('\\', '/', $name);
        $normalized = basename($normalized);
        $normalized = preg_replace('/[\x00-\x1F\x7F]+/', '', $normalized) ?? '';
        $normalized = trim($normalized);

        if ($normalized === '' || $normalized === '.' || $normalized === '..') {
            return 'file.bin';
        }

        if (function_exists('mb_substr')) {
            $normalized = mb_substr($normalized, 0, 180, 'UTF-8');
        } else {
            $normalized = substr($normalized, 0, 180);
        }

        return $normalized !== '' ? $normalized : 'file.bin';
    }

    private function assertUploadSize(int $size): void
    {
        if ($size > $this->maxUploadSizeBytes) {
            throw new \RuntimeException('FILE_TOO_LARGE');
        }
    }

    /**
     * SEC-001: Check if file should be REJECTED entirely (not even saved to quarantine).
     * Checks all segments of the name to catch double extensions.
     */
    private function isForbidden(string $fileName, string $detectedMimeType = ''): bool
    {
        // Check by extension — including all segments (double extensions)
        $cleanName = str_replace('\\', '/', $fileName);
        $cleanName = basename($cleanName);
        $parts = explode('.', $cleanName);
        foreach ($parts as $part) {
            $part = strtolower(trim($part));
            if ($part !== '' && in_array($part, $this->forbiddenExtensions, true)) {
                return true;
            }
        }

        // SEC-001: Check for compound extensions (e.g. user.ini, .htaccess.part)
        if (count($parts) >= 2) {
            for ($i = 0; $i < count($parts) - 1; $i++) {
                if ($parts[$i] === '') {
                    continue; // skip leading dot
                }
                $compound = strtolower(trim($parts[$i] . '.' . $parts[$i + 1]));
                if (in_array($compound, $this->forbiddenExtensions, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * SEC-001: Resolve storage path with backward compatibility.
     * 1. Try the path from DB (authoritative source)
     * 2. Fallback to <dir>/<publicId>.bin (new naming scheme)
     * Returns null if neither exists.
     */
    /** Resolve only regular files below the configured CRM upload/quarantine roots. */
    private function validatedStoragePath(string $path): ?string
    {
        if ($path === '' || preg_match('/[\x00-\x1F\x7F]/', $path)) return null;
        $input = str_replace('\\', DIRECTORY_SEPARATOR, $path);
        if (!str_starts_with($input, DIRECTORY_SEPARATOR)) return null;
        foreach (explode(DIRECTORY_SEPARATOR, $input) as $segment) {
            if ($segment === '.' || $segment === '..') return null;
        }
        $real = realpath($input);
        if ($real === false || !is_file($real) || is_link($input)) return null;
        foreach ([$this->uploadsDir, $this->quarantineDir] as $directory) {
            $rootLexical = rtrim(str_replace('\\', DIRECTORY_SEPARATOR, $directory), DIRECTORY_SEPARATOR);
            $root = realpath($directory);
            if ($root === false || !is_dir($root)) continue;
            $lexicalPrefix = $rootLexical . DIRECTORY_SEPARATOR;
            $realPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (!str_starts_with($input, $lexicalPrefix) || !str_starts_with($real, $realPrefix)) continue;
            $relative = substr($input, strlen($lexicalPrefix));
            $cursor = $rootLexical;
            foreach (explode(DIRECTORY_SEPARATOR, $relative) as $segment) {
                if ($segment === '' || $segment === '.' || $segment === '..') return null;
                $cursor .= DIRECTORY_SEPARATOR . $segment;
                clearstatcache(true, $cursor);
                if (is_link($cursor)) return null;
            }
            clearstatcache(true, $input);
            $after = realpath($input);
            if ($after !== $real || !str_starts_with($after, $realPrefix)) return null;
            return $real;
        }
        return null;
    }

    /**
     * Resolve the storage path for a file, with backward compatibility fallback.
     * 1. Try the path from DB (authoritative source)
     * 2. Fallback to <dir>/<publicId>.bin (new naming scheme)
     * Returns null if neither exists.
     */
    private function resolveStoragePath(string $publicId, string $dbPath): ?string
    {
        // If DB path exists, use it
        if ($dbPath !== '' && is_file($dbPath)) {
            return $dbPath;
        }

        // Try .bin path — compute from DB path's directory + publicId
        $dir = dirname($dbPath);
        $binPath = $dir . '/' . $publicId . '.bin';
        if (is_file($binPath)) {
            return $binPath;
        }

        return null;
    }

    /**
     * SEC-001: For quarantined files, override MIME to neutral value.
     * Forces safe download of SVG/HTML/etc. files.
     */
    private function quarantineMimeOverride(string $path, string $originalMime, string $originalName = ''): string
    {
        if ($this->isQuarantinedPath($path)) {
            return 'application/octet-stream';
        }

        // Also check if original extension is in quarantine list
        $ext = strtolower(pathinfo($originalName !== '' ? $originalName : $path, PATHINFO_EXTENSION));
        if ($ext !== '' && in_array($ext, $this->quarantineExtensions, true)) {
            return 'application/octet-stream';
        }

        return $originalMime;
    }

    private function quarantineDecision(string $fileName, string $mimeType, string $detectedMimeType = ''): array
    {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($ext !== '' && in_array($ext, $this->quarantineExtensions, true)) {
            return [true, 'extension:' . $ext];
        }

        // SEC-004: Check for double extensions like file.php.jpg or file.phtml.svg
        $cleanName = str_replace('\\', '/', $fileName);
        $cleanName = basename($cleanName);
        $parts = explode('.', $cleanName);
        if (count($parts) >= 3) {
            // Remove the last part (final extension), check all intermediate extensions
            array_pop($parts);
            foreach ($parts as $part) {
                $part = strtolower(trim($part));
                if ($part !== '' && in_array($part, $this->quarantineExtensions, true)) {
                    return [true, 'double_extension:' . $part];
                }
            }
        }

        $mime = strtolower(trim($mimeType));
        $detectedMime = strtolower(trim($detectedMimeType));
        if ($detectedMime !== '' && $detectedMime !== $mime) {
            foreach ($this->quarantineMimePrefixes as $prefix) {
                $normalizedPrefix = strtolower(trim($prefix));
                if ($normalizedPrefix !== '' && str_starts_with($detectedMime, $normalizedPrefix)) {
                    return [true, 'detected_mime:' . $normalizedPrefix];
                }
            }

            if ($this->containsExecutableSignature($detectedMime)) {
                return [true, 'detected_signature:' . $detectedMime];
            }
        }

        foreach ($this->quarantineMimePrefixes as $prefix) {
            $normalizedPrefix = strtolower(trim($prefix));
            if ($normalizedPrefix !== '' && str_starts_with($mime, $normalizedPrefix)) {
                return [true, 'mime:' . $normalizedPrefix];
            }
        }

        return [false, null];
    }

    private function detectMime(string $path): string
    {
        if ($path === '' || !is_file($path)) {
            return '';
        }

        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = finfo_file($finfo, $path);
                if (is_string($detected)) {
                    $mime = trim($detected);
                }
            }
        }

        if ($mime === '') {
            $sample = file_get_contents($path, false, null, 0, 512);
            if (is_string($sample) && preg_match('/<\\?(php|=|\\s)/i', $sample)) {
                return 'application/x-php';
            }
        }

        return $mime;
    }

    private function writeTempProbe(string $bin): string
    {
        $path = tempnam(sys_get_temp_dir(), 'upload_probe_');
        if ($path === false) {
            throw new \RuntimeException('UPLOAD_TEMP_FAILED');
        }
        file_put_contents($path, $bin);
        @chmod($path, 0600);

        return $path;
    }

    private function containsExecutableSignature(string $detectedMime): bool
    {
        return in_array($detectedMime, [
            'application/x-php',
            'text/x-php',
            'application/x-msdownload',
            'application/x-dosexec',
            'application/x-sh',
            'text/x-shellscript',
        ], true);
    }

    private function isQuarantinedPath(string $path): bool
    {
        $normalize = static fn(string $value): string => rtrim(str_replace('\\', '/', $value), '/');
        $normalizedPath = $normalize($path);
        $normalizedQuarantine = $normalize($this->quarantineDir);
        if ($normalizedQuarantine === '') {
            return false;
        }

        // Compare canonical paths when possible: on systems such as macOS /tmp
        // and /var may resolve through symlinks to /private/var. Download paths
        // have already passed validatedStoragePath(), which rejects symlinked
        // file components, so canonical comparison preserves quarantine MIME
        // handling without widening the allowed storage roots.
        $realPath = realpath($path);
        $realQuarantine = realpath($this->quarantineDir);
        if ($realPath !== false && $realQuarantine !== false) {
            $canonicalPath = $normalize($realPath);
            $canonicalQuarantine = $normalize($realQuarantine);
            return str_starts_with($canonicalPath, $canonicalQuarantine . '/');
        }

        return str_starts_with($normalizedPath, $normalizedQuarantine . '/');
    }

    private function presentForApi(array $file): array
    {
        $path = (string)($file['storage_path'] ?? '');

        return [
            'public_id' => (string)($file['public_id'] ?? ''),
            'entity_type' => (string)($file['entity_type'] ?? ''),
            'entity_public_id' => (string)($file['entity_public_id'] ?? ''),
            'original_name' => (string)($file['original_name'] ?? ''),
            'mime_type' => (string)($file['mime_type'] ?? ''),
            'size_bytes' => (int)($file['size_bytes'] ?? 0),
            'created_at' => (string)($file['created_at'] ?? ''),
            'deleted_at' => $file['deleted_at'] ?? null,
            'is_deleted' => (bool)($file['is_deleted'] ?? false),
            'is_quarantined' => $this->isQuarantinedPath($path),
            'uploader' => [
                'public_id' => (string)($file['uploader_public_id'] ?? ''),
                'name' => (string)($file['uploader_name'] ?? ''),
            ],
        ];
    }

    /** @return int[] */
    private function decodeTeamMemberIds(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $decoded), static fn(int $value): bool => $value > 0)));
    }
}
