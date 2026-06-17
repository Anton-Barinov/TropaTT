<?php
declare(strict_types=1);

namespace Api\Controller\Sticky;

use Api\System\Library\Controller\BaseController;
use Api\System\Library\Http\JsonResponse;
use Api\System\Library\Language\LanguageManager;
use Api\System\Library\Service\StickyNoteService;

final class StickyNoteController extends BaseController
{
    private const ERROR_MAP = [
        'STICKY_NOTE_NOT_FOUND' => 404,
        'STICKY_NOTE_ALREADY_CONVERTED' => 409,
        'FORBIDDEN' => 403,
        'VALIDATION_ERROR' => 422,
        'INTERNAL_ERROR' => 500,
        'PROJECT_NOT_FOUND' => 404,
        'TASK_NOT_FOUND' => 404,
        'TASK_CREATION_FAILED' => 500,
        'PAGE_CREATION_FAILED' => 500,
        'TASK_SERVICE_UNAVAILABLE' => 500,
        'KNOWLEDGE_SERVICE_UNAVAILABLE' => 500,
    ];

    public function list(): JsonResponse
    {
        $filters = $this->request->allInput();
        $actor = $this->authUser();
        $isRoot = $this->isRoot();
        $result = $this->service()->list($filters, (int)$actor['user']['id'], $isRoot);

        return JsonResponse::success(
            'STICKY_NOTES_LISTED',
            $this->lang()->get('sticky/messages.listed', 'Sticky notes listed'),
            $result
        );
    }

    public function get(array $params): JsonResponse
    {
        $actor = $this->authUser();
        $isRoot = $this->isRoot();
        $result = $this->service()->get((string)$params['public_id'], (int)$actor['user']['id'], $isRoot);

        if (isset($result['error'])) {
            return $this->errorResponse($result);
        }

        return JsonResponse::success(
            'STICKY_NOTE_FOUND',
            $this->lang()->get('sticky/messages.found', 'Sticky note found'),
            ['sticky_note' => $result]
        );
    }

    public function create(): JsonResponse
    {
        $payload = $this->request->allInput();
        $actor = $this->authUser();
        $result = $this->service()->create($payload, (int)$actor['user']['id']);

        if (isset($result['error'])) {
            return $this->errorResponse($result);
        }

        return JsonResponse::success(
            'STICKY_NOTE_CREATED',
            $this->lang()->get('sticky/messages.created', 'Sticky note created'),
            ['sticky_note' => $result],
            null,
            201
        );
    }

    public function update(array $params): JsonResponse
    {
        $payload = $this->request->allInput();
        $actor = $this->authUser();
        $isRoot = $this->isRoot();
        $result = $this->service()->update((string)$params['public_id'], $payload, (int)$actor['user']['id'], $isRoot);

        if (isset($result['error'])) {
            return $this->errorResponse($result);
        }

        return JsonResponse::success(
            'STICKY_NOTE_UPDATED',
            $this->lang()->get('sticky/messages.updated', 'Sticky note updated'),
            ['sticky_note' => $result]
        );
    }

    public function delete(array $params): JsonResponse
    {
        $actor = $this->authUser();
        $isRoot = $this->isRoot();
        $result = $this->service()->delete((string)$params['public_id'], (int)$actor['user']['id'], $isRoot);

        if (isset($result['error'])) {
            return $this->errorResponse($result);
        }

        return JsonResponse::success(
            'STICKY_NOTE_DELETED',
            $this->lang()->get('sticky/messages.deleted', 'Sticky note deleted')
        );
    }

    public function archive(array $params): JsonResponse
    {
        $actor = $this->authUser();
        $isRoot = $this->isRoot();
        $result = $this->service()->archive((string)$params['public_id'], (int)$actor['user']['id'], $isRoot);

        if (isset($result['error'])) {
            return $this->errorResponse($result);
        }

        return JsonResponse::success(
            'STICKY_NOTE_ARCHIVED',
            $this->lang()->get('sticky/messages.archived', 'Sticky note archived'),
            $result
        );
    }

    public function unarchive(array $params): JsonResponse
    {
        $actor = $this->authUser();
        $isRoot = $this->isRoot();
        $result = $this->service()->unarchive((string)$params['public_id'], (int)$actor['user']['id'], $isRoot);

        if (isset($result['error'])) {
            return $this->errorResponse($result);
        }

        return JsonResponse::success(
            'STICKY_NOTE_UNARCHIVED',
            $this->lang()->get('sticky/messages.unarchived', 'Sticky note unarchived'),
            $result
        );
    }

    public function reorder(): JsonResponse
    {
        $payload = $this->request->allInput();
        $items = (array)($payload['items'] ?? []);
        $actor = $this->authUser();
        $result = $this->service()->reorder($items, (int)$actor['user']['id']);

        if (isset($result['error'])) {
            return $this->errorResponse($result);
        }

        return JsonResponse::success(
            'STICKY_NOTES_REORDERED',
            $this->lang()->get('sticky/messages.reordered', 'Sticky notes reordered')
        );
    }

    public function convertToTask(array $params): JsonResponse
    {
        $payload = $this->request->allInput();
        $actor = $this->authUser();
        $isRoot = $this->isRoot();
        $result = $this->service()->convertToTask((string)$params['public_id'], $payload, (int)$actor['user']['id'], $isRoot);

        if (isset($result['error'])) {
            return $this->errorResponse($result);
        }

        return JsonResponse::success(
            'STICKY_NOTE_CONVERTED_TO_TASK',
            $this->lang()->get('sticky/messages.converted_to_task', 'Sticky note converted to task'),
            $result
        );
    }

    public function convertToKnowledgePage(array $params): JsonResponse
    {
        $payload = $this->request->allInput();
        $actor = $this->authUser();
        $isRoot = $this->isRoot();
        $result = $this->service()->convertToKnowledgePage((string)$params['public_id'], $payload, (int)$actor['user']['id'], $isRoot);

        if (isset($result['error'])) {
            return $this->errorResponse($result);
        }

        return JsonResponse::success(
            'STICKY_NOTE_CONVERTED_TO_PAGE',
            $this->lang()->get('sticky/messages.converted_to_page', 'Sticky note converted to knowledge page'),
            $result
        );
    }

    private function service(): StickyNoteService
    {
        return $this->container->get('service.sticky_note');
    }

    /**
     * @param array<string,mixed> $result
     */
    private function errorResponse(array $result): JsonResponse
    {
        $code = (string)$result['error'];
        $status = self::ERROR_MAP[$code] ?? 400;

        $message = match ($code) {
            'STICKY_NOTE_NOT_FOUND' => $this->lang()->get('sticky/messages.not_found', 'Sticky note not found'),
            'STICKY_NOTE_ALREADY_CONVERTED' => $this->lang()->get('sticky/messages.already_converted', 'Sticky note already converted'),
            'FORBIDDEN' => $this->lang()->get('common/messages.forbidden', 'Forbidden'),
            'VALIDATION_ERROR' => $this->lang()->get('common/messages.validation_error', 'Validation error'),
            'PROJECT_NOT_FOUND' => $this->lang()->get('project/messages.not_found', 'Project not found'),
            'TASK_NOT_FOUND' => $this->lang()->get('task/messages.not_found', 'Task not found'),
            default => $this->lang()->get('common/messages.internal_error', 'Internal server error'),
        };

        $errors = $result['errors'] ?? null;
        if ($errors === null && $code !== 'VALIDATION_ERROR') {
            $errors = [$code => [$message]];
        }

        return JsonResponse::error(
            code: $code,
            message: $message,
            status: $status,
            errors: $errors
        );
    }

    private function lang(): LanguageManager
    {
        return $this->container->get('lang');
    }

    private function authUser(): array
    {
        return $this->container->get('auth_user');
    }

    private function isRoot(): bool
    {
        $user = $this->authUser();
        return !empty($user['user']['root']);
    }
}
