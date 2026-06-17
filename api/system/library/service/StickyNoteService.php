<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Sticky\StickyNoteRepository;
use Api\Model\Knowledge\KnowledgeRepository;
use Api\Model\Project\ProjectRepository;
use Api\Model\Common\UserRepository;
use Api\Model\Task\TaskRepository;
use Psr\Log\LoggerInterface;

final class StickyNoteService
{
    private const MAX_BODY_LENGTH = 65535;
    private const MAX_TITLE_LENGTH = 255;
    private const ALLOWED_CONTEXT = ['personal', 'dashboard', 'project', 'task'];
    private const ALLOWED_VISIBILITY = ['private', 'shared'];
    private const ALLOWED_COLORS = ['yellow', 'green', 'blue', 'purple', 'pink', 'red', 'orange', 'teal', 'gray', 'white'];
    private const SORT_STEP = 10;

    public function __construct(
        private readonly StickyNoteRepository $repo,
        private readonly KnowledgeRepository $knowledgeRepo,
        private readonly ProjectRepository $projectRepo,
        private readonly UserRepository $userRepo,
        private readonly TaskRepository $taskRepo,
        private readonly ?TaskService $taskService,
        private readonly LoggerInterface $logger,
        private readonly string $requestId,
    ) {
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{items:array<int,array<string,mixed>>,total:int,page:int,limit:int,pages:int}
     */
    public function list(array $filters, int $actorUserId, bool $isRoot): array
    {
        return $this->repo->list($filters, $actorUserId, $isRoot);
    }

    public function get(string $publicId, int $actorUserId, bool $isRoot): array
    {
        $note = $this->repo->findByPublicId($publicId);
        if ($note === null || $note === []) {
            $this->logger->warning('sticky_note_not_found', ['public_id' => $publicId, 'request_id' => $this->requestId]);
            return ['error' => 'STICKY_NOTE_NOT_FOUND'];
        }

        if (!$isRoot && (int)$note['owner_user_id'] !== $actorUserId && $note['visibility'] !== 'shared') {
            $this->logger->warning('sticky_note_forbidden', ['public_id' => $publicId, 'actor' => $actorUserId, 'request_id' => $this->requestId]);
            return ['error' => 'FORBIDDEN'];
        }

        return $note;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function create(array $payload, int $actorUserId): array
    {
        $errors = $this->validate($payload);
        if ($errors !== []) {
            return ['error' => 'VALIDATION_ERROR', 'errors' => $errors];
        }

        $contextType = (string)($payload['context_type'] ?? 'personal');
        $contextPublicId = $payload['context_public_id'] ?? null;

        // Validate context references
        if ($contextType !== 'personal') {
            $ctxResult = $this->validateContext($contextType, $contextPublicId, $actorUserId);
            if ($ctxResult !== null) {
                return $ctxResult;
            }
        }

        $note = $this->repo->create([
            'owner_user_id' => $actorUserId,
            'context_type' => $contextType,
            'context_public_id' => $contextPublicId,
            'title' => $payload['title'] ?? null,
            'body' => (string)($payload['body'] ?? ''),
            'color' => (string)($payload['color'] ?? 'yellow'),
            'background_color' => $payload['background_color'] ?? null,
            'visibility' => (string)($payload['visibility'] ?? 'private'),
            'is_pinned' => !empty($payload['is_pinned']),
            'sort_order' => (int)($payload['sort_order'] ?? 65535),
            'meta_json' => $payload['meta_json'] ?? null,
        ]);

        if ($note === []) {
            return ['error' => 'INTERNAL_ERROR'];
        }

        $this->logger->info('sticky_note_created', [
            'public_id' => $note['public_id'] ?? '',
            'context_type' => $contextType,
            'actor' => $actorUserId,
            'request_id' => $this->requestId,
        ]);

        return $note;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function update(string $publicId, array $payload, int $actorUserId, bool $isRoot): array
    {
        $note = $this->repo->findByPublicId($publicId);
        if ($note === null || $note === []) {
            return ['error' => 'STICKY_NOTE_NOT_FOUND'];
        }

        if (!$isRoot && (int)$note['owner_user_id'] !== $actorUserId) {
            return ['error' => 'FORBIDDEN'];
        }

        $updateAllowed = ['title', 'body', 'color', 'background_color', 'visibility', 'is_pinned', 'sort_order', 'meta_json'];
        $set = [];

        foreach ($updateAllowed as $field) {
            if (array_key_exists($field, $payload)) {
                $set[$field] = $payload[$field];
            }
        }

        if ($set === []) {
            return $note;
        }

        // Re-validate
        $merged = array_merge($note, $set);
        $errors = $this->validate($merged);
        if ($errors !== []) {
            return ['error' => 'VALIDATION_ERROR', 'errors' => $errors];
        }

        // Handle boolean fields
        if (array_key_exists('is_pinned', $set)) {
            $set['is_pinned'] = !empty($set['is_pinned']) ? 1 : 0;
        }

        $ok = $this->repo->updateByPublicId($publicId, $set);
        if (!$ok) {
            return ['error' => 'STICKY_NOTE_NOT_FOUND'];
        }

        $this->logger->info('sticky_note_updated', [
            'public_id' => $publicId,
            'fields' => array_keys($set),
            'actor' => $actorUserId,
            'request_id' => $this->requestId,
        ]);

        return $this->repo->findByPublicId($publicId) ?? ['error' => 'INTERNAL_ERROR'];
    }

    public function delete(string $publicId, int $actorUserId, bool $isRoot): array
    {
        $note = $this->repo->findByPublicId($publicId);
        if ($note === null || $note === []) {
            return ['error' => 'STICKY_NOTE_NOT_FOUND'];
        }

        if (!$isRoot && (int)$note['owner_user_id'] !== $actorUserId) {
            return ['error' => 'FORBIDDEN'];
        }

        $ok = $this->repo->softDeleteByPublicId($publicId, gmdate('Y-m-d H:i:s'));
        if (!$ok) {
            return ['error' => 'STICKY_NOTE_NOT_FOUND'];
        }

        $this->logger->info('sticky_note_deleted', [
            'public_id' => $publicId,
            'actor' => $actorUserId,
            'request_id' => $this->requestId,
        ]);

        return ['success' => true, 'public_id' => $publicId];
    }

    public function archive(string $publicId, int $actorUserId, bool $isRoot): array
    {
        $note = $this->repo->findByPublicId($publicId);
        if ($note === null || $note === []) {
            return ['error' => 'STICKY_NOTE_NOT_FOUND'];
        }

        if (!$isRoot && (int)$note['owner_user_id'] !== $actorUserId) {
            return ['error' => 'FORBIDDEN'];
        }

        $ok = $this->repo->archiveByPublicId($publicId, gmdate('Y-m-d H:i:s'));
        if (!$ok) {
            return ['error' => 'STICKY_NOTE_NOT_FOUND'];
        }

        $this->logger->info('sticky_note_archived', [
            'public_id' => $publicId,
            'actor' => $actorUserId,
            'request_id' => $this->requestId,
        ]);

        return ['success' => true, 'public_id' => $publicId];
    }

    public function unarchive(string $publicId, int $actorUserId, bool $isRoot): array
    {
        $note = $this->repo->findByPublicId($publicId);
        if ($note === null || $note === []) {
            return ['error' => 'STICKY_NOTE_NOT_FOUND'];
        }

        if (!$isRoot && (int)$note['owner_user_id'] !== $actorUserId) {
            return ['error' => 'FORBIDDEN'];
        }

        $ok = $this->repo->unarchiveByPublicId($publicId);
        if (!$ok) {
            return ['error' => 'STICKY_NOTE_NOT_FOUND'];
        }

        $this->logger->info('sticky_note_unarchived', [
            'public_id' => $publicId,
            'actor' => $actorUserId,
            'request_id' => $this->requestId,
        ]);

        return ['success' => true, 'public_id' => $publicId];
    }

    /**
     * @param array<int,array{public_id:string,sort_order:int}> $items
     */
    public function reorder(array $items, int $actorUserId): array
    {
        if ($items === []) {
            return ['success' => true];
        }

        // Validate ownership
        foreach ($items as $item) {
            $note = $this->repo->findByPublicId((string)$item['public_id']);
            if ($note === null || $note === []) {
                return ['error' => 'STICKY_NOTE_NOT_FOUND', 'public_id' => $item['public_id']];
            }
            if ((int)$note['owner_user_id'] !== $actorUserId) {
                return ['error' => 'FORBIDDEN', 'public_id' => $item['public_id']];
            }
        }

        $this->repo->reorder($items, $actorUserId);

        $this->logger->info('sticky_notes_reordered', [
            'count' => count($items),
            'actor' => $actorUserId,
            'request_id' => $this->requestId,
        ]);

        return ['success' => true];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function convertToTask(string $publicId, array $payload, int $actorUserId, bool $isRoot): array
    {
        $note = $this->getForConvert($publicId, $actorUserId, $isRoot);
        if (isset($note['error'])) {
            return $note;
        }

        if ($this->taskService === null) {
            return ['error' => 'TASK_SERVICE_UNAVAILABLE'];
        }

        $projectPublicId = $payload['project_public_id'] ?? null;
        if ($projectPublicId === null) {
            return ['error' => 'VALIDATION_ERROR', 'errors' => ['project_public_id' => 'Required for task conversion']];
        }

        $project = $this->projectRepo->findByPublicId((string)$projectPublicId);
        if ($project === null) {
            return ['error' => 'PROJECT_NOT_FOUND'];
        }

        // Build task title
        $title = $payload['title'] ?? $note['title'] ?? '';
        if ($title === '') {
            $title = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string)$note['body']))), 0, 200);
            if ($title === '') {
                $title = 'Sticky note #' . substr($publicId, 0, 8);
            }
        }

        // Build task description from sticky note body
        $description = (string)$note['body'];
        $description .= "\n\n---\n*Converted from sticky note: " . $publicId . '*';

        $taskPayload = [
            'project_public_id' => $projectPublicId,
            'title' => $title,
            'description' => $description,
            'status_code' => $payload['status_code'] ?? 'new',
            'priority_code' => $payload['priority_code'] ?? 'normal',
        ];

        if (isset($payload['assignee_public_id'])) {
            $taskPayload['assignee_public_id'] = $payload['assignee_public_id'];
        }
        if (isset($payload['due_date'])) {
            $taskPayload['due_date'] = $payload['due_date'];
        }

        // Use task service to create task
        $actorArr = ['id' => $actorUserId, 'is_root' => $isRoot];
        try {
            $task = $this->taskService->create($taskPayload, $actorArr);
        } catch (\Throwable $e) {
            $this->logger->error('sticky_note_convert_task_failed', [
                'public_id' => $publicId,
                'error' => $e->getMessage(),
                'request_id' => $this->requestId,
            ]);
            return ['error' => 'TASK_CREATION_FAILED'];
        }

        if (isset($task['error'])) {
            return $task;
        }

        // Mark note as converted
        $this->repo->markConverted($publicId, [
            'converted_to_entity_type' => 'task',
            'converted_to_entity_public_id' => $task['public_id'] ?? '',
            'converted_at' => gmdate('Y-m-d H:i:s'),
            'converted_by_user_id' => $actorUserId,
        ]);

        $this->logger->info('sticky_note_converted_to_task', [
            'note_public_id' => $publicId,
            'task_public_id' => $task['public_id'] ?? '',
            'actor' => $actorUserId,
            'request_id' => $this->requestId,
        ]);

        return [
            'success' => true,
            'note_public_id' => $publicId,
            'task' => $task,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function convertToKnowledgePage(string $publicId, array $payload, int $actorUserId, bool $isRoot): array
    {
        $note = $this->getForConvert($publicId, $actorUserId, $isRoot);
        if (isset($note['error'])) {
            return $note;
        }

        $spacePublicId = $payload['space_public_id'] ?? null;
        if ($spacePublicId === null) {
            return ['error' => 'VALIDATION_ERROR', 'errors' => ['space_public_id' => 'Required for knowledge page conversion']];
        }

        // Build title and content
        $title = $payload['title'] ?? $note['title'] ?? '';
        if ($title === '') {
            $title = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string)$note['body']))), 0, 200);
            if ($title === '') {
                $title = 'Sticky note #' . substr($publicId, 0, 8);
            }
        }

        $content = (string)$note['body'];
        $content .= "\n\n---\n*Converted from sticky note: " . $publicId . '*';

        $pagePayload = [
            'space_public_id' => $spacePublicId,
            'title' => $title,
            'content' => $content,
            'page_type' => $payload['page_type'] ?? 'documentation',
            'status' => $payload['status'] ?? 'draft',
        ];

        $actorArr = ['id' => $actorUserId, 'is_root' => $isRoot];
        try {
            $page = $this->knowledgeRepo->createPage($pagePayload, $actorUserId, $actorArr);
        } catch (\Throwable $e) {
            $this->logger->error('sticky_note_convert_page_failed', [
                'public_id' => $publicId,
                'error' => $e->getMessage(),
                'request_id' => $this->requestId,
            ]);
            return ['error' => 'PAGE_CREATION_FAILED'];
        }

        // createPage throws RuntimeException on validation failure, but returns array on success
        if (isset($page['error'])) {
            return $page;
        }

        // Mark note as converted
        $this->repo->markConverted($publicId, [
            'converted_to_entity_type' => 'knowledge_page',
            'converted_to_entity_public_id' => $page['public_id'] ?? '',
            'converted_at' => gmdate('Y-m-d H:i:s'),
            'converted_by_user_id' => $actorUserId,
        ]);

        $this->logger->info('sticky_note_converted_to_page', [
            'note_public_id' => $publicId,
            'page_public_id' => $page['public_id'] ?? '',
            'actor' => $actorUserId,
            'request_id' => $this->requestId,
        ]);

        return [
            'success' => true,
            'note_public_id' => $publicId,
            'page' => $page,
        ];
        return [
            'success' => true,
            'note_public_id' => $publicId,
            'page' => $page,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,string>
     */
    private function validate(array $payload): array
    {
        $errors = [];

        if (isset($payload['title']) && mb_strlen((string)$payload['title']) > self::MAX_TITLE_LENGTH) {
            $errors['title'] = 'Title too long (max ' . self::MAX_TITLE_LENGTH . ')';
        }

        if (isset($payload['body']) && mb_strlen((string)$payload['body']) > self::MAX_BODY_LENGTH) {
            $errors['body'] = 'Body too long (max ' . self::MAX_BODY_LENGTH . ')';
        }

        if (isset($payload['context_type']) && !in_array((string)$payload['context_type'], self::ALLOWED_CONTEXT, true)) {
            $errors['context_type'] = 'Invalid context type. Allowed: ' . implode(', ', self::ALLOWED_CONTEXT);
        }

        if (isset($payload['visibility']) && !in_array((string)$payload['visibility'], self::ALLOWED_VISIBILITY, true)) {
            $errors['visibility'] = 'Invalid visibility. Allowed: ' . implode(', ', self::ALLOWED_VISIBILITY);
        }

        if (isset($payload['color']) && !in_array((string)$payload['color'], self::ALLOWED_COLORS, true)) {
            $errors['color'] = 'Invalid color. Allowed: ' . implode(', ', self::ALLOWED_COLORS);
        }

        return $errors;
    }

    private function validateContext(string $contextType, ?string $contextPublicId, int $actorUserId): ?array
    {
        if ($contextPublicId === null || $contextPublicId === '') {
            return ['error' => 'VALIDATION_ERROR', 'errors' => ['context_public_id' => 'Required when context_type is not personal']];
        }

        if ($contextType === 'task') {
            $task = $this->repo->taskByPublicId($contextPublicId);
            if ($task === null) {
                return ['error' => 'TASK_NOT_FOUND'];
            }
        } elseif ($contextType === 'project') {
            $project = $this->repo->projectByPublicId($contextPublicId);
            if ($project === null) {
                return ['error' => 'PROJECT_NOT_FOUND'];
            }
        }

        return null;
    }

    private function getForConvert(string $publicId, int $actorUserId, bool $isRoot): array
    {
        $note = $this->repo->findByPublicId($publicId);
        if ($note === null || $note === []) {
            $this->logger->warning('sticky_note_not_found', ['public_id' => $publicId, 'request_id' => $this->requestId]);
            return ['error' => 'STICKY_NOTE_NOT_FOUND'];
        }

        if (!$isRoot && (int)$note['owner_user_id'] !== $actorUserId) {
            $this->logger->warning('sticky_note_forbidden', ['public_id' => $publicId, 'actor' => $actorUserId, 'request_id' => $this->requestId]);
            return ['error' => 'FORBIDDEN'];
        }

        if ($note['converted_to_entity_type'] !== null) {
            return ['error' => 'STICKY_NOTE_ALREADY_CONVERTED', 'converted_to' => $note['converted_to_entity_type']];
        }

        return $note;
    }
}
