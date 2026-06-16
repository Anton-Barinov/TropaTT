<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\File\FileRepository;
use Api\Model\Knowledge\KnowledgeRepository;
use Api\Model\Project\ProjectRepository;
use Api\Model\Recycle_bin\RecycleBinRepository;
use Api\Model\Task\TaskRepository;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Support\Ulid;

final class FileService
{
    public function __construct(
        private readonly FileRepository $files,
        private readonly string $uploadsDir,
        private readonly string $quarantineDir,
        private readonly int $maxUploadSizeBytes,
        private readonly array $quarantineExtensions,
        private readonly array $quarantineMimePrefixes,
        private readonly TaskRepository $tasks,
        private readonly ProjectRepository $projects,
        private readonly KnowledgeRepository $knowledge,
        private readonly RecycleBinRepository $recycleBin,
        private readonly JsonLogger $logger,
        private readonly ?AiSemanticIndexService $semanticIndex = null,
        private readonly ?TaskActivityService $activity = null
    ) {
    }

    public function create(array $input, array $rawFiles, int $uploaderUserId, array $actor): array
    {
        $publicId = Ulid::generate('fil');
        $now = gmdate('Y-m-d H:i:s');

        $entityType = trim((string)($input['entity_type'] ?? 'task'));
        $entityPublicId = trim((string)($input['entity_public_id'] ?? ''));
        if ($entityPublicId !== '' && !$this->canAccessEntity($entityType, $entityPublicId, $actor)) {
            throw new \RuntimeException('ENTITY_ACCESS_DENIED');
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
            [$isQuarantined, $quarantineReason] = $this->quarantineDecision($name, $mime, $detectedMime);
            $mime = $detectedMime !== '' ? $detectedMime : $mime;
            $dir = rtrim($isQuarantined ? $this->quarantineDir : $this->uploadsDir, '/');
            $this->ensureDir($dir);
            $storedPath = $dir . '/' . $publicId . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);

            if (!move_uploaded_file($tmp, $storedPath)) {
                throw new \RuntimeException('UPLOAD_MOVE_FAILED');
            }
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
            $detectedMime = $this->detectMime($tmp);
            @unlink($tmp);
            [$isQuarantined, $quarantineReason] = $this->quarantineDecision($name, $mime, $detectedMime);
            $mime = $detectedMime !== '' ? $detectedMime : $mime;
            $dir = rtrim($isQuarantined ? $this->quarantineDir : $this->uploadsDir, '/');
            $this->ensureDir($dir);
            $storedPath = $dir . '/' . $publicId . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
            file_put_contents($storedPath, $bin);
        } else {
            throw new \RuntimeException('FILE_REQUIRED');
        }

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
            'created_at' => $now,
        ]);

        $created = $this->files->findByPublicId($publicId) ?: ['public_id' => $publicId];

        if ($entityType === 'task' && $entityPublicId !== '') {
            $task = $this->tasks->findByPublicId($entityPublicId);
            if ($task) {
                $this->activity?->recordFileEvent($task, 'task.file_added', [
                    'file_public_id' => $publicId,
                    'original_name' => $name,
                    'mime_type' => $mime,
                    'size_bytes' => $size,
                ], $actor);
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
        $file = $this->files->findByPublicId($publicId);
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
        $type = trim($entityType);
        $entityId = trim($entityPublicId);
        if ($type === '' || $entityId === '') {
            return [];
        }

        if (!$this->canAccessEntity($type, $entityId, $actor)) {
            return null;
        }

        $items = $this->files->listByEntity($type, $entityId);

        return array_map(fn(array $file): array => $this->presentForApi($file), $items);
    }

    public function delete(string $publicId, array $actor): bool
    {
        $file = $this->files->findByPublicId($publicId);
        if (!$file) {
            return false;
        }
        if (!$this->canAccessFile($file, $actor)) {
            return false;
        }

        $deleted = $this->files->softDelete($publicId, gmdate('Y-m-d H:i:s'));
        if ($deleted) {
            $this->semanticIndex?->removeEntityDocument('file', $publicId);
            $path = (string)$file['storage_path'];
            if ($path !== '' && is_file($path)) {
                @rename($path, $path . '.deleted');
            }
            if (($file['entity_type'] ?? '') === 'task' && ($file['entity_public_id'] ?? '') !== '') {
                $task = $this->tasks->findByPublicId((string)$file['entity_public_id']);
                if ($task) {
                    $this->activity?->recordFileEvent($task, 'task.file_deleted', [
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
        $file = $this->files->findByPublicId($publicId);
        if (!$file || (int)($file['is_deleted'] ?? 0) === 1) {
            return ['ok' => false, 'error' => 'FILE_NOT_FOUND'];
        }
        if (!$this->canAccessFile($file, $actor)) {
            return ['ok' => false, 'error' => 'FILE_NOT_FOUND'];
        }
        $path = (string)($file['storage_path'] ?? '');
        if ($path === '' || !is_file($path)) {
            return ['ok' => false, 'error' => 'FILE_NOT_ON_STORAGE'];
        }
        if ($this->isQuarantinedPath($path)) {
            $this->logger->security([
                'actor_public_id' => (string)($actor['public_id'] ?? ''),
                'event_type' => 'file_download_quarantine_denied',
                'details' => [
                    'file_public_id' => $publicId,
                    'original_name' => (string)($file['original_name'] ?? ''),
                ],
            ]);

            return ['ok' => false, 'error' => 'FILE_QUARANTINED'];
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

        return [
            'ok' => true,
            'path' => $path,
            'name' => (string)$file['original_name'],
            'mime' => (string)$file['mime_type'],
            'size' => (int)$file['size_bytes'],
        ];
    }

    /** @param array<string,mixed> $file */
    /** @param array<string,mixed> $actor */
    private function canAccessFile(array $file, array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
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

        $actorId = (int)($actor['id'] ?? 0);
        if ($actorId <= 0) {
            return false;
        }

        if ($entityType === 'task') {
            $task = $this->tasks->findByPublicId($entityPublicId);
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
            $project = $this->projects->findByPublicId($entityPublicId);
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

        return false;
    }

    /** @param array<string,mixed> $file */
    /** @param array<string,mixed> $actor */
    private function queueRecycleBinEntry(array $file, array $actor): void
    {
        $publicId = (string)($file['public_id'] ?? '');
        if ($publicId === '') {
            return;
        }

        $existing = $this->recycleBin->findActiveByEntity('file', $publicId);
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
        ]);
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
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

    private function quarantineDecision(string $fileName, string $mimeType, string $detectedMimeType = ''): array
    {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($ext !== '' && in_array($ext, $this->quarantineExtensions, true)) {
            return [true, 'extension:' . $ext];
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
        $dir = rtrim($this->uploadsDir, '/');
        $parent = dirname($dir);
        $tmpDir = is_dir($parent) ? $parent . '/temp' : sys_get_temp_dir();
        $this->ensureDir($tmpDir);
        $path = tempnam($tmpDir, 'upload_probe_');
        if ($path === false) {
            throw new \RuntimeException('UPLOAD_TEMP_FAILED');
        }
        file_put_contents($path, $bin);

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
        $normalizedPath = str_replace('\\', '/', $path);
        $normalizedQuarantine = str_replace('\\', '/', rtrim($this->quarantineDir, '/'));

        return $normalizedQuarantine !== '' && str_starts_with($normalizedPath, $normalizedQuarantine . '/');
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
