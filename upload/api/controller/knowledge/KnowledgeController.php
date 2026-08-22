<?php
declare(strict_types=1);

namespace Api\Controller\Knowledge;

use Api\Controller\Common\BaseController;
use Api\Model\Knowledge\KnowledgeRepository;
use Api\Model\Tag\TagRepository;
use Api\System\Library\Http\JsonResponse;
use Api\System\Library\Service\FileService;
use Api\System\Library\Service\AiSemanticIndexService;
use Api\System\Library\Service\TaskService;
use Api\System\Library\Validation\Validator;
use Throwable;

final class KnowledgeController extends BaseController
{
    private function repo(): KnowledgeRepository
    {
        return new KnowledgeRepository($this->container->get('db.pdo'));
    }

    private function actor(): array
    {
        $auth = $this->user();
        return is_array($auth['user'] ?? null) ? $auth['user'] : [];
    }

    private function actorUserId(): int
    {
        $actor = $this->actor();
        $id = (int)($actor['id'] ?? 0);
        if ($id > 0) {
            return $id;
        }
        $publicId = trim((string)($actor['public_id'] ?? ''));
        if ($publicId === '') {
            return 0;
        }
        $stmt = $this->container->get('db.pdo')->prepare('SELECT id FROM users WHERE public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $publicId]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    private function requirePageAccess(string $publicId, string $minAccess = 'view'): ?array
    {
        return $this->repo()->page($publicId, $this->actor(), $minAccess);
    }

    private function canLinkEntity(string $entityType, string $entityPublicId): bool
    {
        $actor = $this->actor();
        switch ($entityType) {
            case 'task':
                /** @var TaskService $service */
                $service = $this->container->get('service.task');
                return $service->get($entityPublicId, $actor) !== null;
            case 'project':
                return $this->container->get('service.project')->get($entityPublicId, $actor) !== null;
            case 'counterparty':
                return $this->container->get('service.counterparty')->get($entityPublicId, $actor) !== null;
            case 'client':
                return $this->container->get('service.client')->get($entityPublicId, $actor) !== null;
            case 'contact':
                return $this->container->get('service.contact')->get($entityPublicId, $actor) !== null;
            case 'knowledge_page':
                return $this->repo()->page($entityPublicId, $actor) !== null;
            case 'team':
                return $this->container->get('service.team')->get($entityPublicId, $actor) !== null;
            case 'department':
                return $this->container->get('service.department')->get($entityPublicId, $actor) !== null;
            case 'chat':
                $stmt = $this->container->get('db.pdo')->prepare(
                    'SELECT 1 FROM chats c JOIN chat_participants cp ON cp.chat_id = c.id WHERE c.public_id = :public_id AND cp.user_id = :user_id LIMIT 1'
                );
                $stmt->execute(['public_id' => $entityPublicId, 'user_id' => $this->actorUserId()]);
                return $stmt->fetchColumn() !== false;
            default:
                return false;
        }
    }

    private function actorCanManagePermissions(): bool
    {
        $actor = $this->actor();
        if (empty($actor)) {
            return false;
        }
        if (!empty($actor['is_root'])) {
            return true;
        }
        $permissions = array_map('strval', (array)($actor['permission_codes'] ?? []));
        return in_array('*', $permissions, true) || in_array('knowledge.admin', $permissions, true);
    }

    private function requireSpaceOwnerOrAdmin(string $publicId): ?array
    {
        $space = $this->repo()->space($publicId, $this->actor());
        if (!$space) {
            return null;
        }
        if ($this->actorCanManagePermissions()) {
            return $space;
        }
        return (int)($space['owner_user_id'] ?? 0) === $this->actorUserId() ? $space : null;
    }

    private function requirePageOwnerOrAdmin(string $publicId): ?array
    {
        $page = $this->repo()->page($publicId, $this->actor());
        if (!$page) {
            return null;
        }
        if ($this->actorCanManagePermissions()) {
            return $page;
        }
        return (int)($page['owner_user_id'] ?? 0) === $this->actorUserId() ? $page : null;
    }

    public function overview(): JsonResponse
    {
        $actor = $this->actor();
        $cache = $this->cacheApi();
        if ($cache !== null) {
            $cacheUser = (string)($actor['public_id'] ?? $this->cacheUserId());
            $data = $cache->remember('knowledge', 'overview:' . $cacheUser, 60, fn(): array => $this->repo()->overview($this->request()->allInput(), $actor));
        } else {
            $data = $this->repo()->overview($this->request()->allInput(), $actor);
        }

        return $this->success('KNOWLEDGE_OVERVIEW', $this->t('knowledge/messages.overview', 'Knowledge overview loaded'), $data);
    }

    public function spaces(): JsonResponse
    {
        return $this->success('KNOWLEDGE_SPACES', $this->t('knowledge/messages.spaces', 'Knowledge spaces loaded'), [
            'items' => $this->repo()->spaces($this->request()->allInput(), $this->actor()),
        ]);
    }

    public function spacesTree(): JsonResponse
    {
        return $this->success('KNOWLEDGE_SPACES_TREE', $this->t('knowledge/messages.spaces_tree', 'Knowledge spaces tree loaded'), [
            'items' => $this->repo()->spacesTree($this->request()->allInput(), $this->actor()),
        ]);
    }

    public function createSpace(): JsonResponse
    {
        $auth = $this->user();
        $input = $this->request()->allInput();
        $title = trim((string)($input['title'] ?? ''));
        if ($title === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error', 'Validation error'), 422, [
                'title' => [$this->t('common/messages.field_required', 'Field is required')],
            ]);
        }

        return $this->withIdempotency(function () use ($input): JsonResponse {
            $space = $this->repo()->createSpace($input, $this->actorUserId() ?: null);
            $this->invalidateCache('knowledge');
            $this->auditLog('knowledge_space', $space['public_id'] ?? '', 'space_created', [
                'title' => $space['title'] ?? '',
            ]);
            return $this->success('KNOWLEDGE_SPACE_CREATED', $this->t('knowledge/messages.space_created', 'Knowledge space created'), [
                'space' => $space,
            ], 201, ['row_version' => (int)($space['row_version'] ?? 1)]);
        });
    }

    public function getSpace(array $params): JsonResponse
    {
        $space = $this->repo()->space((string)$params['public_id'], $this->actor());
        if (!$space) {
            return $this->error('KNOWLEDGE_SPACE_NOT_FOUND', $this->t('knowledge/messages.space_not_found', 'Knowledge space not found'), 404);
        }
        return $this->success('KNOWLEDGE_SPACE_DETAIL', $this->t('knowledge/messages.space_detail', 'Knowledge space loaded'), [
            'space' => $space,
        ], meta: ['row_version' => (int)($space['row_version'] ?? 1)]);
    }

    public function updateSpace(array $params): JsonResponse
    {
        $result = $this->repo()->updateSpace((string)$params['public_id'], $this->request()->allInput(), $this->actor());
        if ($result === 'ROW_VERSION_CONFLICT') {
            return $this->error('ROW_VERSION_CONFLICT', $this->t('knowledge/messages.row_version_conflict', 'The record was changed by another user'), 409);
        }
        if (!$result) {
            return $this->error('KNOWLEDGE_SPACE_NOT_FOUND', $this->t('knowledge/messages.space_not_found', 'Knowledge space not found'), 404);
        }
        $this->invalidateCache('knowledge');
        $this->auditLog('knowledge_space', (string)$params['public_id'], 'space_updated');
        return $this->success('KNOWLEDGE_SPACE_UPDATED', $this->t('knowledge/messages.space_updated', 'Knowledge space updated'), [
            'space' => $result,
        ], meta: ['row_version' => (int)($result['row_version'] ?? 1)]);
    }

    public function archiveSpace(array $params): JsonResponse
    {
        if (!$this->repo()->archiveSpace((string)$params['public_id'], true, $this->actor())) {
            return $this->error('KNOWLEDGE_SPACE_NOT_FOUND', $this->t('knowledge/messages.space_not_found', 'Knowledge space not found'), 404);
        }
        $this->invalidateCache('knowledge');
        return $this->success('KNOWLEDGE_SPACE_ARCHIVED', $this->t('knowledge/messages.space_archived', 'Knowledge space archived'));
    }

    public function restoreSpace(array $params): JsonResponse
    {
        if (!$this->repo()->archiveSpace((string)$params['public_id'], false, $this->actor())) {
            return $this->error('KNOWLEDGE_SPACE_NOT_FOUND', $this->t('knowledge/messages.space_not_found', 'Knowledge space not found'), 404);
        }
        $this->invalidateCache('knowledge');
        return $this->success('KNOWLEDGE_SPACE_RESTORED', $this->t('knowledge/messages.space_restored', 'Knowledge space restored'));
    }

    public function tree(array $params): JsonResponse
    {
        return $this->success('KNOWLEDGE_TREE', $this->t('knowledge/messages.tree', 'Knowledge tree loaded'), [
            'items' => $this->repo()->tree((string)$params['public_id'], (int)($this->request()->input('depth', 10)), $this->actor()),
        ]);
    }

    public function pages(): JsonResponse
    {
        return $this->success('KNOWLEDGE_PAGES', $this->t('knowledge/messages.pages', 'Knowledge pages loaded'), [
            'items' => $this->repo()->pages($this->request()->allInput(), $this->actor()),
        ]);
    }

    public function spacePermissions(array $params): JsonResponse
    {
        if (!$this->requireSpaceOwnerOrAdmin((string)$params['public_id'])) {
            return $this->error('KNOWLEDGE_SPACE_NOT_FOUND', $this->t('knowledge/messages.space_not_found', 'Knowledge space not found'), 404);
        }
        $items = $this->repo()->spacePermissions((string)$params['public_id']);
        return $this->success('KNOWLEDGE_SPACE_PERMISSIONS', $this->t('knowledge/messages.space_permissions', 'Space permissions loaded'), [
            'items' => $items,
        ]);
    }

    public function addSpacePermission(array $params): JsonResponse
    {
        if (!$this->requireSpaceOwnerOrAdmin((string)$params['public_id'])) {
            return $this->error('KNOWLEDGE_SPACE_NOT_FOUND', $this->t('knowledge/messages.space_not_found', 'Knowledge space not found'), 404);
        }
        $auth = $this->user();
        $input = $this->request()->allInput();
        $subjectType = trim((string)($input['subject_type'] ?? ''));
        $subjectId = (int)($input['subject_id'] ?? 0);
        $subjectPublicId = trim((string)($input['subject_public_id'] ?? ''));
        $accessLevel = trim((string)($input['access_level'] ?? 'view'));
        if ($subjectType === '' || ($subjectId <= 0 && $subjectPublicId === '')) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'subject_type' => [$this->t('common/messages.field_required', 'Field is required')],
            ]);
        }
        $result = $this->repo()->addSpacePermission((string)$params['public_id'], $subjectType, $subjectId, $accessLevel, $this->actorUserId() ?: null, $subjectPublicId);
        if ($result === null) {
            return $this->error('KNOWLEDGE_SPACE_NOT_FOUND', $this->t('knowledge/messages.space_not_found', 'Knowledge space not found'), 404);
        }
        $this->invalidateCache('knowledge');
        return $this->success('KNOWLEDGE_SPACE_PERMISSION_ADDED', $this->t('knowledge/messages.space_permission_added', 'Space permission added'), [
            'permission' => $result,
        ], 201);
    }

    public function removeSpacePermission(array $params): JsonResponse
    {
        $id = (int)($params['permission_id'] ?? 0);
        if ($id <= 0) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422);
        }
        $spacePublicId = $this->repo()->getSpacePublicIdByPermissionId($id);
        if (!$spacePublicId || !$this->requireSpaceOwnerOrAdmin($spacePublicId)) {
            return $this->error('KNOWLEDGE_SPACE_NOT_FOUND', $this->t('knowledge/messages.space_not_found', 'Knowledge space not found'), 404);
        }
        $this->repo()->removeSpacePermission($id);
        $this->invalidateCache('knowledge');
        return $this->success('KNOWLEDGE_SPACE_PERMISSION_REMOVED', $this->t('knowledge/messages.space_permission_removed', 'Space permission removed'));
    }

    public function pagePermissions(array $params): JsonResponse
    {
        if (!$this->requirePageOwnerOrAdmin((string)$params['public_id'])) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $items = $this->repo()->pagePermissions((string)$params['public_id']);
        return $this->success('KNOWLEDGE_PAGE_PERMISSIONS', $this->t('knowledge/messages.page_permissions', 'Page permissions loaded'), [
            'items' => $items,
        ]);
    }

    public function addPagePermission(array $params): JsonResponse
    {
        if (!$this->requirePageOwnerOrAdmin((string)$params['public_id'])) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $input = $this->request()->allInput();
        $subjectType = trim((string)($input['subject_type'] ?? ''));
        $subjectId = (int)($input['subject_id'] ?? 0);
        $subjectPublicId = trim((string)($input['subject_public_id'] ?? ''));
        $accessLevel = trim((string)($input['access_level'] ?? 'view'));
        if ($subjectType === '' || ($subjectId <= 0 && $subjectPublicId === '')) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'subject_type' => [$this->t('common/messages.field_required', 'Field is required')],
            ]);
        }
        $result = $this->repo()->addPagePermission((string)$params['public_id'], $subjectType, $subjectId, $accessLevel, $this->actorUserId() ?: null, $subjectPublicId);
        if ($result === null) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $this->invalidateCache('knowledge');
        return $this->success('KNOWLEDGE_PAGE_PERMISSION_ADDED', $this->t('knowledge/messages.page_permission_added', 'Page permission added'), [
            'permission' => $result,
        ], 201);
    }

    public function removePagePermission(array $params): JsonResponse
    {
        $id = (int)($params['permission_id'] ?? 0);
        if ($id <= 0) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422);
        }
        $pagePublicId = $this->repo()->getPagePublicIdByPermissionId($id);
        if (!$pagePublicId || !$this->requirePageOwnerOrAdmin($pagePublicId)) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $this->repo()->removePagePermission($id);
        $this->invalidateCache('knowledge');
        return $this->success('KNOWLEDGE_PAGE_PERMISSION_REMOVED', $this->t('knowledge/messages.page_permission_removed', 'Page permission removed'));
    }

    public function createPage(): JsonResponse
    {
        $auth = $this->user();
        $input = $this->request()->allInput();
        if (trim((string)($input['title'] ?? '')) === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error', 'Validation error'), 422, [
                'title' => [$this->t('common/messages.field_required', 'Field is required')],
            ]);
        }

        $v = new Validator();
        $v->maxLen($input, 'title', 255, 'Title is too long')
            ->maxLen($input, 'content_html', 2000000, 'Content is too long')
            ->maxLen($input, 'content_json', 2000000, 'Content is too long');
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error', 'Validation error'), 422, $v->errors());
        }

        return $this->withIdempotency(function () use ($input): JsonResponse {
            try {
                $page = $this->repo()->createPage($input, $this->actorUserId() ?: null, $this->actor());
            } catch (\RuntimeException $e) {
                error_log('[KnowledgeController::createPage] ' . $e->getMessage());
                return $this->error('VALIDATION_ERROR', $this->t('knowledge/messages.validation_failed'), 422);
            }
            $this->invalidateCache('knowledge');
            $this->auditLog('knowledge_page', $page['public_id'] ?? '', 'page_created', [
                'title' => $page['title'] ?? '',
            ]);
            return $this->success('KNOWLEDGE_PAGE_CREATED', $this->t('knowledge/messages.page_created', 'Knowledge page created'), [
                'page' => $page,
            ], 201, ['row_version' => (int)($page['row_version'] ?? 1)]);
        });
    }

    public function getPage(array $params): JsonResponse
    {
        $page = $this->requirePageAccess((string)$params['public_id']);
        if (!$page) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $auth = $this->user();
        $this->repo()->recordView((string)$params['public_id'], $this->actorUserId() ?: null, (string)$this->request()->input('source', 'direct'));
        return $this->success('KNOWLEDGE_PAGE_DETAIL', $this->t('knowledge/messages.page_detail', 'Knowledge page loaded'), [
            'page' => $page,
            'links' => $this->visibleLinks((string)$params['public_id']),
        ], meta: ['row_version' => (int)($page['row_version'] ?? 1)]);
    }

    public function updatePage(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'edit')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->maxLen($input, 'title', 255, 'Title is too long')
            ->maxLen($input, 'content_html', 2000000, 'Content is too long')
            ->maxLen($input, 'content_json', 2000000, 'Content is too long');
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error', 'Validation error'), 422, $v->errors());
        }

        $auth = $this->user();
        $result = $this->repo()->updatePage((string)$params['public_id'], $input, $this->actorUserId() ?: null, $this->actor());
        if ($result === 'ROW_VERSION_CONFLICT') {
            return $this->error('ROW_VERSION_CONFLICT', $this->t('knowledge/messages.row_version_conflict', 'The record was changed by another user'), 409);
        }
        if (!$result) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $this->invalidateCache('knowledge');
        if (($result['status'] ?? '') === 'published') {
            $this->notifyPageEvent($result, 'updated', $auth);
            $this->indexPageForSemanticSearch($result);
        }
        $this->auditLog('knowledge_page', (string)$params['public_id'], 'page_updated', [
            'title' => $result['title'] ?? '',
        ]);
        return $this->success('KNOWLEDGE_PAGE_UPDATED', $this->t('knowledge/messages.page_updated', 'Knowledge page updated'), [
            'page' => $result,
        ], meta: ['row_version' => (int)($result['row_version'] ?? 1)]);
    }

    public function deletePage(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'manage')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $page = $this->repo()->page((string)$params['public_id']);
        if ($page) {
            $this->notifyPageEvent($page, 'deleted', $this->user() ?: []);
        }
        if (!$this->repo()->deletePage((string)$params['public_id'])) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $this->invalidateCache('knowledge');
        $this->auditLog('knowledge_page', (string)$params['public_id'], 'page_deleted');
        return $this->success('KNOWLEDGE_PAGE_DELETED', $this->t('knowledge/messages.page_deleted', 'Knowledge page deleted'));
    }

    public function publish(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'edit')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $auth = $this->user();
        $page = $this->repo()->publish((string)$params['public_id'], $this->actorUserId() ?: null, (string)$this->request()->input('change_summary', ''));
        if (!$page) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $this->invalidateCache('knowledge');
        $this->indexPageForSemanticSearch($page);
        $this->notifyPageEvent($page, 'published', $auth);
        $this->auditLog('knowledge_page', (string)$params['public_id'], 'page_published', [
            'title' => $page['title'] ?? '',
        ]);
        return $this->success('KNOWLEDGE_PAGE_PUBLISHED', $this->t('knowledge/messages.page_published', 'Knowledge page published'), [
            'page' => $page,
        ]);
    }

    public function archivePage(array $params): JsonResponse
    {
        return $this->setPageStatus($params, 'archived', 'KNOWLEDGE_PAGE_ARCHIVED', $this->t('knowledge/messages.page_archived', 'Knowledge page archived'));
    }

    public function restorePage(array $params): JsonResponse
    {
        return $this->setPageStatus($params, 'draft', 'KNOWLEDGE_PAGE_RESTORED', $this->t('knowledge/messages.page_restored', 'Knowledge page restored'));
    }

    public function requestReview(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'edit')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $auth = $this->user();
        $page = $this->repo()->setStatus((string)$params['public_id'], 'review', $this->actorUserId() ?: null);
        if (!$page) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $this->invalidateCache('knowledge');
        $this->notifyPageEvent($page, 'review_requested', $auth);
        return $this->success('KNOWLEDGE_REVIEW_REQUESTED', $this->t('knowledge/messages.review_requested', 'Review requested'), ['page' => $page]);
    }

    public function approveReview(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'edit')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $auth = $this->user();
        $page = $this->repo()->publish((string)$params['public_id'], $this->actorUserId() ?: null, (string)$this->request()->input('change_summary', ''));
        if (!$page) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $this->invalidateCache('knowledge');
        $this->notifyPageEvent($page, 'review_approved', $auth);
        return $this->success('KNOWLEDGE_REVIEW_APPROVED', $this->t('knowledge/messages.review_approved', 'Review approved'), [
            'page' => $page,
        ]);
    }

    public function rejectReview(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'edit')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $auth = $this->user();
        $page = $this->repo()->setStatus((string)$params['public_id'], 'draft', $this->actorUserId() ?: null);
        if (!$page) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $this->invalidateCache('knowledge');
        $this->notifyPageEvent($page, 'review_rejected', $auth);
        return $this->success('KNOWLEDGE_REVIEW_REJECTED', $this->t('knowledge/messages.review_rejected', 'Review rejected'), ['page' => $page]);
    }

    public function duplicatePage(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'view')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $auth = $this->user();
        $page = $this->repo()->duplicate((string)$params['public_id'], $this->actorUserId() ?: null, $this->actor());
        if (!$page) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $this->invalidateCache('knowledge');
        return $this->success('KNOWLEDGE_PAGE_DUPLICATED', $this->t('knowledge/messages.page_duplicated', 'Knowledge page duplicated'), [
            'page' => $page,
        ], 201);
    }

    public function movePage(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'edit')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $auth = $this->user();
        $input = $this->request()->allInput();
        $result = $this->repo()->updatePage((string)$params['public_id'], [
            'space_public_id' => $input['space_public_id'] ?? null,
            'parent_public_id' => $input['parent_public_id'] ?? null,
            'sort_order' => $input['sort_order'] ?? null,
        ], $this->actorUserId() ?: null, $this->actor());
        if (!$result || $result === 'ROW_VERSION_CONFLICT') {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $this->invalidateCache('knowledge');
        return $this->success('KNOWLEDGE_PAGE_MOVED', $this->t('knowledge/messages.page_moved', 'Knowledge page moved'), [
            'page' => $result,
        ]);
    }

    public function getDraft(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'edit')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $auth = $this->user();
        $draft = $this->repo()->draft((string)$params['public_id'], $this->actorUserId());
        return $this->success('KNOWLEDGE_DRAFT', $this->t('knowledge/messages.draft', 'Draft loaded'), [
            'draft' => $draft,
        ]);
    }

    public function saveDraft(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'edit')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $auth = $this->user();
        try {
            $draft = $this->repo()->saveDraft((string)$params['public_id'], $this->request()->allInput(), $this->actorUserId());
        } catch (\RuntimeException $e) {
            error_log('[KnowledgeController::saveDraft] ' . $e->getMessage());
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found'), 404);
        }
        return $this->success('KNOWLEDGE_DRAFT_SAVED', $this->t('knowledge/messages.draft_saved', 'Draft saved'), [
            'draft' => $draft,
        ]);
    }

    public function deleteDraft(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'edit')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $auth = $this->user();
        $this->repo()->deleteDraft((string)$params['public_id'], $this->actorUserId());
        return $this->success('KNOWLEDGE_DRAFT_DELETED', $this->t('knowledge/messages.draft_deleted', 'Draft deleted'));
    }

    public function search(): JsonResponse
    {
        $query = (string)$this->request()->input('q', '');
        return $this->success('KNOWLEDGE_SEARCH', $this->t('knowledge/messages.search', 'Knowledge search completed'), [
            'items' => $this->repo()->search($query, $this->request()->allInput(), $this->actor()),
        ]);
    }

    public function recent(): JsonResponse
    {
        return $this->success('KNOWLEDGE_RECENT', $this->t('knowledge/messages.recent', 'Recent pages loaded'), [
            'items' => $this->repo()->pages(['limit' => (int)$this->request()->input('limit', 20), 'sort' => 'updated_at', 'order' => 'DESC'], $this->actor()),
        ]);
    }

    public function popular(): JsonResponse
    {
        return $this->success('KNOWLEDGE_POPULAR', $this->t('knowledge/messages.popular', 'Popular pages loaded'), [
            'items' => $this->repo()->popular((int)$this->request()->input('limit', 20), $this->actor()),
        ]);
    }

    public function reviewQueue(): JsonResponse
    {
        return $this->success('KNOWLEDGE_REVIEW_QUEUE', $this->t('knowledge/messages.review_queue', 'Review queue loaded'), [
            'items' => $this->repo()->pages(['status' => 'review', 'limit' => (int)$this->request()->input('limit', 50)], $this->actor()),
        ]);
    }

    public function outdated(): JsonResponse
    {
        return $this->success('KNOWLEDGE_OUTDATED', $this->t('knowledge/messages.outdated', 'Outdated pages loaded'), [
            'items' => $this->repo()->outdated((int)$this->request()->input('limit', 50), $this->actor()),
        ]);
    }

    public function favorites(): JsonResponse
    {
        $auth = $this->user();
        return $this->success('KNOWLEDGE_FAVORITES', $this->t('knowledge/messages.favorites', 'Favorites loaded'), [
            'items' => $this->repo()->favorites($this->actorUserId(), (int)$this->request()->input('limit', 20), (int)$this->request()->input('offset', 0), $this->actor()),
        ]);
    }

    public function suggest(): JsonResponse
    {
        $q = trim((string)$this->request()->input('q', ''));
        return $this->success('KNOWLEDGE_SUGGEST', $this->t('knowledge/messages.suggest', 'Suggestions loaded'), [
            'items' => $q === '' ? [] : $this->repo()->suggest($q, (int)$this->request()->input('limit', 10), $this->actor()),
        ]);
    }

    public function analytics(): JsonResponse
    {
        return $this->success('KNOWLEDGE_ANALYTICS', $this->t('knowledge/messages.analytics', 'Analytics loaded'), [
            'stats' => $this->repo()->analytics($this->actor()),
        ]);
    }

    public function templates(): JsonResponse
    {
        return $this->success('KNOWLEDGE_TEMPLATES', $this->t('knowledge/messages.templates', 'Templates loaded'), [
            'items' => $this->repo()->templates($this->request()->allInput()),
        ]);
    }

    public function createTemplate(): JsonResponse
    {
        $auth = $this->user();
        $input = $this->request()->allInput();
        if (trim((string)($input['title'] ?? '')) === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error', 'Validation error'), 422, [
                'title' => [$this->t('common/messages.field_required', 'Field is required')],
            ]);
        }
        $template = $this->repo()->createTemplate($input, $this->actorUserId() ?: null);
        return $this->success('KNOWLEDGE_TEMPLATE_CREATED', $this->t('knowledge/messages.template_created', 'Template created'), [
            'template' => $template,
        ], 201);
    }

    public function links(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'view')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        return $this->success('KNOWLEDGE_LINKS', $this->t('knowledge/messages.links', 'Links loaded'), [
            'items' => $this->visibleLinks((string)$params['public_id']),
        ]);
    }

    /**
     * Return only links whose target entity is still visible to the actor.
     * This prevents a previously linked task/client/etc. public ID from being
     * disclosed after access to the target entity has been revoked.
     *
     * @return array<int,array<string,mixed>>
     */
    private function visibleLinks(string $pagePublicId): array
    {
        return array_values(array_filter(
            $this->repo()->links($pagePublicId),
            function (array $link): bool {
                return $this->canLinkEntity(
                    strtolower(trim((string)($link['entity_type'] ?? ''))),
                    trim((string)($link['entity_public_id'] ?? ''))
                );
            }
        ));
    }

    public function linkEntity(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'edit')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $input = $this->request()->allInput();
        $entityType = strtolower(trim((string)($input['entity_type'] ?? '')));
        $entityPublicId = trim((string)($input['entity_public_id'] ?? ''));
        if ($entityType === '' || $entityPublicId === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error', 'Validation error'), 422, [
                'entity_public_id' => [$this->t('common/messages.field_required', 'Field is required')],
            ]);
        }
        if (!$this->canLinkEntity($entityType, $entityPublicId)) {
            return $this->error('ENTITY_NOT_FOUND', $this->t('common/messages.not_found', 'Entity not found'), 404);
        }
        $relationType = strtolower(trim((string)($input['relation_type'] ?? 'related')));
        if (!in_array($relationType, ['related', 'instruction', 'reference', 'derived_from'], true)) {
            $relationType = 'related';
        }
        return $this->withIdempotency(function () use ($params, $entityType, $entityPublicId, $relationType): JsonResponse {
            try {
                $link = $this->repo()->linkEntity(
                    (string)$params['public_id'],
                    $entityType,
                    $entityPublicId,
                    $relationType,
                    $this->actorUserId() ?: null
                );
            } catch (\RuntimeException $e) {
                error_log('[KnowledgeController::createLink] ' . $e->getMessage());
                return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found'), 404);
            }
            return $this->success('KNOWLEDGE_LINK_CREATED', $this->t('knowledge/messages.link_created', 'Link created'), [
                'link' => $link,
            ], 201);
        });
    }

    public function attachPageToTask(array $params): JsonResponse
    {
        $taskPublicId = trim((string)($params['public_id'] ?? ''));
        $input = $this->request()->allInput();
        $pagePublicId = trim((string)($input['page_public_id'] ?? ''));
        if ($taskPublicId === '' || $pagePublicId === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error', 'Validation error'), 422, [
                'page_public_id' => [$this->t('common/messages.field_required', 'Field is required')],
            ]);
        }
        if (!$this->canLinkEntity('task', $taskPublicId)) {
            return $this->error('ENTITY_NOT_FOUND', $this->t('common/messages.not_found', 'Entity not found'), 404);
        }
        if (!$this->requirePageAccess($pagePublicId, 'view')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }

        $relationType = strtolower(trim((string)($input['relation_type'] ?? 'related')));
        if (!in_array($relationType, ['related', 'instruction', 'reference', 'derived_from'], true)) {
            $relationType = 'related';
        }

        return $this->withIdempotency(function () use ($pagePublicId, $taskPublicId, $relationType): JsonResponse {
            try {
                $link = $this->repo()->linkEntity(
                    $pagePublicId,
                    'task',
                    $taskPublicId,
                    $relationType,
                    $this->actorUserId() ?: null
                );
            } catch (\RuntimeException $e) {
                error_log('[KnowledgeController::attachPageToTask] ' . $e->getMessage());
                return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
            }
            return $this->success('KNOWLEDGE_LINK_CREATED', $this->t('knowledge/messages.link_created', 'Link created'), [
                'link' => $link,
            ], 201);
        });
    }

    public function deleteLink(array $params): JsonResponse
    {
        $pageId = (string)$params['public_id'];
        if (!$this->requirePageAccess($pageId, 'edit')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        try {
            $this->repo()->unlinkEntity($pageId, (string)$params['link_public_id'], $this->actor());
        } catch (\RuntimeException $e) {
            error_log('[KnowledgeController::deleteLink] ' . $e->getMessage());
            return $this->error('KNOWLEDGE_LINK_NOT_FOUND', $this->t('knowledge/messages.link_not_found'), 404);
        }
        return $this->success('KNOWLEDGE_LINK_DELETED', $this->t('knowledge/messages.link_deleted', 'Link deleted'));
    }

    public function comments(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'view')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $items = $this->repo()->comments((string)$params['public_id']);
        return $this->success('KNOWLEDGE_COMMENTS', $this->t('knowledge/messages.comments', 'Comments loaded'), [
            'items' => $items,
        ]);
    }

    public function addComment(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'comment')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $auth = $this->user();
        $input = $this->request()->allInput();
        $body = trim((string)($input['body'] ?? ''));
        if ($body === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'body' => [$this->t('common/messages.field_required', 'Field is required')],
            ]);
        }
        $comment = $this->repo()->addComment((string)$params['public_id'], $body, $this->actorUserId(), (string)($input['parent_public_id'] ?? '') ?: null);
        if (!$comment) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $this->notifyComment((string)$params['public_id'], $comment, $auth);

        // Check for knowledge page mentions (kb:public_id pattern) in the comment
        if (preg_match('/\bkb:([a-zA-Z0-9_]+)\b/', $body, $kbMatch)) {
            $mentionedPublicId = $kbMatch[1];
            $mentionedPage = $this->repo()->page($mentionedPublicId);
            if ($mentionedPage) {
                $this->notifyPageEvent($mentionedPage, 'mentioned', $auth);
            }
        }

        $this->auditLog('knowledge_page', (string)$params['public_id'], 'comment_added', [
            'comment_public_id' => $comment['public_id'] ?? '',
        ]);

        return $this->success('KNOWLEDGE_COMMENT_CREATED', $this->t('knowledge/messages.comment_created', 'Comment added'), [
            'comment' => $comment,
        ], 201);
    }

    public function deleteComment(array $params): JsonResponse
    {
        $auth = $this->user();
        if (!$this->repo()->deleteComment((string)$params['comment_public_id'], $this->actorUserId())) {
            return $this->error('KNOWLEDGE_COMMENT_NOT_FOUND', $this->t('knowledge/messages.comment_not_found', 'Comment not found'), 404);
        }
        $this->auditLog('knowledge_comment', (string)$params['comment_public_id'], 'comment_deleted');
        return $this->success('KNOWLEDGE_COMMENT_DELETED', $this->t('knowledge/messages.comment_deleted', 'Comment deleted'));
    }

    public function resolveComment(array $params): JsonResponse
    {
        if (!$this->repo()->resolveComment((string)$params['comment_public_id'])) {
            return $this->error('KNOWLEDGE_COMMENT_NOT_FOUND', $this->t('knowledge/messages.comment_not_found', 'Comment not found'), 404);
        }
        return $this->success('KNOWLEDGE_COMMENT_RESOLVED', $this->t('knowledge/messages.comment_resolved', 'Comment resolved'));
    }

    public function reopenComment(array $params): JsonResponse
    {
        if (!$this->repo()->reopenComment((string)$params['comment_public_id'])) {
            return $this->error('KNOWLEDGE_COMMENT_NOT_FOUND', $this->t('knowledge/messages.comment_not_found', 'Comment not found'), 404);
        }
        return $this->success('KNOWLEDGE_COMMENT_REOPENED', $this->t('knowledge/messages.comment_reopened', 'Comment reopened'));
    }

    public function favoritePage(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'view')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $publicId = $this->repo()->favoritePage((string)$params['public_id'], $this->actorUserId());
        if ($publicId === null) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        return $this->success('KNOWLEDGE_PAGE_FAVORITED', $this->t('knowledge/messages.page_favorited', 'Page added to favorites'), [
            'favorite_public_id' => $publicId,
        ]);
    }

    public function unfavoritePage(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'view')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $this->repo()->unfavoritePage((string)$params['public_id'], $this->actorUserId());
        return $this->success('KNOWLEDGE_PAGE_UNFAVORITED', $this->t('knowledge/messages.page_unfavorited', 'Page removed from favorites'));
    }

    public function subscribePage(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'view')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $publicId = $this->repo()->subscribePage((string)$params['public_id'], $this->actorUserId());
        if ($publicId === null) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        return $this->success('KNOWLEDGE_PAGE_SUBSCRIBED', $this->t('knowledge/messages.page_subscribed', 'Subscribed to page updates'), [
            'subscription_public_id' => $publicId,
        ]);
    }

    public function unsubscribePage(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'view')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $this->repo()->unsubscribePage((string)$params['public_id'], $this->actorUserId());
        return $this->success('KNOWLEDGE_PAGE_UNSUBSCRIBED', $this->t('knowledge/messages.page_unsubscribed', 'Unsubscribed from page updates'));
    }

    public function entityPages(array $params): JsonResponse
    {
        $entityType = strtolower(trim((string)($params['entity_type'] ?? '')));
        $entityPublicId = trim((string)($params['entity_public_id'] ?? ''));
        if ($entityType === '' || $entityPublicId === '' || !$this->canLinkEntity($entityType, $entityPublicId)) {
            return $this->error('ENTITY_NOT_FOUND', $this->t('common/messages.not_found', 'Entity not found'), 404);
        }

        return $this->success('KNOWLEDGE_ENTITY_PAGES', $this->t('knowledge/messages.entity_pages', 'Related pages loaded'), [
            'items' => array_values(array_filter(
                $this->repo()->entityPages($entityType, $entityPublicId, $this->actor()),
                fn(array $page): bool => $this->repo()->page((string)($page['public_id'] ?? ''), $this->actor()) !== null
            )),
        ]);
    }

    /**
     * Return knowledge pages attached to the team(s) that own the given entity
     * (a project's team, a task's project's team, or the teams behind a
     * client/counterparty/contact's projects). This surfaces team materials
     * where the team actually works, not just in the team editor.
     */
    public function teamPages(array $params): JsonResponse
    {
        $entityType = strtolower(trim((string)($params['entity_type'] ?? '')));
        $entityPublicId = trim((string)($params['entity_public_id'] ?? ''));
        if ($entityType === '' || $entityPublicId === '' || !$this->canLinkEntity($entityType, $entityPublicId)) {
            return $this->error('ENTITY_NOT_FOUND', $this->t('common/messages.not_found', 'Entity not found'), 404);
        }

        $teamPublicIds = array_values(array_filter(
            $this->repo()->entityTeamPublicIds($entityType, $entityPublicId),
            fn(string $teamPublicId): bool => $this->canLinkEntity('team', $teamPublicId)
        ));

        $items = [];
        $seen = [];
        foreach ($teamPublicIds as $teamPublicId) {
            foreach ($this->repo()->entityPages('team', $teamPublicId, $this->actor()) as $page) {
                $pagePublicId = (string)($page['public_id'] ?? '');
                if ($pagePublicId === '' || isset($seen[$pagePublicId])) {
                    continue;
                }
                if ($this->repo()->page($pagePublicId, $this->actor()) === null) {
                    continue;
                }
                $seen[$pagePublicId] = true;
                $items[] = $page;
            }
        }

        return $this->success('KNOWLEDGE_TEAM_PAGES', $this->t('knowledge/messages.entity_pages', 'Related pages loaded'), [
            'team_public_id' => $teamPublicIds[0] ?? null,
            'team_public_ids' => $teamPublicIds,
            'items' => $items,
        ]);
    }

    /**
     * Batch counter for the "team materials" badge on list pages. Resolves the
     * related team(s) per entity and returns the number of team-linked pages the
     * actor can view, keyed by entity public_id. Entities the actor cannot
     * access, or that have no team materials, resolve to 0.
     */
    public function teamMaterialsCounts(): JsonResponse
    {
        $input = $this->request()->allInput();
        $entityType = strtolower(trim((string)($input['entity_type'] ?? '')));
        $rawIds = trim((string)($input['entity_public_ids'] ?? ''));
        if ($entityType === '' || $rawIds === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error', 'Validation error'), 422);
        }

        $ids = array_values(array_unique(array_filter(
            array_map('trim', explode(',', $rawIds)),
            fn(string $id): bool => $id !== ''
        )));
        $ids = array_slice($ids, 0, 200);

        $counts = [];
        $teamPageCache = [];
        foreach ($ids as $id) {
            $counts[$id] = 0;
            if (!$this->canLinkEntity($entityType, $id)) {
                continue;
            }
            $teamPublicIds = array_values(array_filter(
                $this->repo()->entityTeamPublicIds($entityType, $id),
                fn(string $teamPublicId): bool => $this->canLinkEntity('team', $teamPublicId)
            ));
            $seen = [];
            $count = 0;
            foreach ($teamPublicIds as $teamPublicId) {
                if (!isset($teamPageCache[$teamPublicId])) {
                    $teamPageCache[$teamPublicId] = $this->repo()->entityPages('team', $teamPublicId, $this->actor());
                }
                foreach ($teamPageCache[$teamPublicId] as $page) {
                    $pagePublicId = (string)($page['public_id'] ?? '');
                    if ($pagePublicId !== '' && !isset($seen[$pagePublicId])) {
                        $seen[$pagePublicId] = true;
                        $count++;
                    }
                }
            }
            $counts[$id] = $count;
        }

        return $this->success('KNOWLEDGE_TEAM_MATERIALS_COUNTS', $this->t('knowledge/messages.entity_pages', 'Team materials counts loaded'), [
            'counts' => $counts,
        ]);
    }

    public function listFiles(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'view')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var FileService $service */
        $service = $this->container->get('service.file');
        $items = $service->listByEntity('knowledge_page', (string)$params['public_id'], $authUser['user']);
        if ($items === null) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }

        return $this->success('KNOWLEDGE_PAGE_FILES', $this->t('knowledge/messages.files_listed', 'Files loaded'), [
            'items' => $items,
        ]);
    }

    public function uploadFile(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'edit')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $input['entity_type'] = 'knowledge_page';
        $input['entity_public_id'] = (string)$params['public_id'];

        /** @var FileService $service */
        $service = $this->container->get('service.file');

        try {
            $item = $service->create($input, $this->request()->files, $this->actorUserId(), $authUser['user']);
            return $this->success('KNOWLEDGE_FILE_UPLOADED', $this->t('knowledge/messages.file_uploaded', 'File uploaded'), [
                'file' => $item,
            ], 201);
        } catch (Throwable $e) {
            if ($e->getMessage() === 'ENTITY_ACCESS_DENIED') {
                return $this->error('FORBIDDEN', $this->t('knowledge/messages.entity_access_denied', 'Access denied'), 403);
            }
            // SEC-001: Forbidden file types rejected before disk write
            if ($e->getMessage() === 'FILE_TYPE_FORBIDDEN') {
                return $this->error('FILE_TYPE_FORBIDDEN', $this->t('file/messages.type_forbidden', 'This file type is forbidden for security reasons'), 422, [
                    'file' => [$this->t('file/messages.type_forbidden', 'This file type is forbidden for security reasons')],
                ]);
            }
            error_log('[KnowledgeController::uploadFile] ' . $e->getMessage());
            return $this->error('FILE_UPLOAD_ERROR', $this->t('file/messages.upload_error', 'Upload error'), 422, [
                'file' => ['File upload failed. Check server logs for details.'],
            ]);
        }
    }

    public function deleteFile(array $params): JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $stmt = $this->container->get('db.pdo')->prepare('SELECT entity_type, entity_public_id FROM files WHERE public_id = :public_id AND is_deleted = 0 LIMIT 1');
        $stmt->execute(['public_id' => (string)$params['file_public_id']]);
        $fileRow = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$fileRow) {
            return $this->error('FILE_NOT_FOUND', $this->t('file/messages.not_found', 'File not found'), 404);
        }
        if (($fileRow['entity_type'] ?? '') === 'knowledge_page') {
            if (!$this->requirePageAccess((string)$fileRow['entity_public_id'], 'edit')) {
                return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
            }
        }

        /** @var FileService $service */
        $service = $this->container->get('service.file');
        $ok = $service->delete((string)$params['file_public_id'], $authUser['user']);

        if (!$ok) {
            return $this->error('FILE_NOT_FOUND', $this->t('file/messages.not_found', 'File not found'), 404);
        }

        return $this->success('KNOWLEDGE_FILE_DELETED', $this->t('knowledge/messages.file_deleted', 'File deleted'));
    }

    public function exportAll(): JsonResponse
    {
        $actor = $this->actor();
        $format = (string)$this->request()->input('format', 'json');
        $spaces = $this->repo()->spaces([], $actor);
        $allPages = [];
        foreach ($spaces as $space) {
            $pages = $this->repo()->pages(['space_public_id' => (string)$space['public_id'], 'limit' => 500], $actor);
            foreach ($pages as $page) {
                $allPages[] = [
                    'public_id' => $page['public_id'],
                    'title' => $page['title'],
                    'page_type' => $page['page_type'],
                    'status' => $page['status'],
                    'content_html' => $page['content_html'],
                    'content_text' => $page['content_text'],
                    'slug' => $page['slug'],
                    'space_title' => $page['space_title'] ?? $space['title'],
                    'space_public_id' => $space['public_id'],
                    'path' => $page['path'],
                    'created_at' => $page['created_at'],
                    'updated_at' => $page['updated_at'],
                ];
            }
        }
        if ($format === 'markdown') {
            $md = '# Knowledge Base Export' . "\n\n";
            $md .= 'Exported: ' . gmdate('Y-m-d H:i:s') . " UTC\n\n";
            $md .= '---' . "\n\n";
            foreach ($allPages as $ep) {
                $md .= '## ' . $ep['title'] . "\n\n";
                $md .= '**Space:** ' . $ep['space_title'] . ' | **Type:** ' . $ep['page_type'] . ' | **Status:** ' . $ep['status'] . "\n\n";
                $md .= $this->htmlToMarkdown((string)($ep['content_html'] ?? '')) . "\n\n";
                $md .= '---' . "\n\n";
            }
            return $this->success('KNOWLEDGE_EXPORT_ALL', $this->t('knowledge/messages.export_all', 'All knowledge exported'), [
                'format' => 'markdown',
                'content' => $md,
                'filename' => 'knowledge-base-export.md',
            ]);
        }
        return $this->success('KNOWLEDGE_EXPORT_ALL', $this->t('knowledge/messages.export_all', 'All knowledge exported'), [
            'format' => 'json',
            'exported_at' => gmdate('Y-m-d H:i:s'),
            'spaces_count' => count($spaces),
            'pages_count' => count($allPages),
            'spaces' => array_map(fn($s) => ['public_id' => $s['public_id'], 'title' => $s['title'], 'slug' => $s['slug']], $spaces),
            'pages' => $allPages,
        ]);
    }

    public function exportPage(array $params): JsonResponse
    {
        $page = $this->requirePageAccess((string)$params['public_id'], 'view');
        if (!$page) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $format = (string)$this->request()->input('format', 'json');
        $export = [
            'public_id' => $page['public_id'],
            'title' => $page['title'],
            'page_type' => $page['page_type'],
            'status' => $page['status'],
            'content_html' => $page['content_html'],
            'content_text' => $page['content_text'],
            'content_json' => $page['content_json'] ? json_decode((string)$page['content_json'], true) : null,
            'excerpt' => $page['excerpt'],
            'space_public_id' => $page['space_public_id'],
            'space_title' => $page['space_title'],
            'slug' => $page['slug'],
            'path' => $page['path'],
            'created_at' => $page['created_at'],
            'updated_at' => $page['updated_at'],
            'published_at' => $page['published_at'],
            'tags' => $this->tagRepo()->listByEntity('knowledge_page', (string)$page['public_id']),
            'links' => $this->repo()->links((string)$page['public_id']),
        ];
        if ($format === 'markdown') {
            $markdown = '# ' . $page['title'] . "\n\n";
            $markdown .= '**Type:** ' . $page['page_type'] . ' | **Status:** ' . $page['status'] . "\n\n";
            $markdown .= $this->htmlToMarkdown((string)($page['content_html'] ?? ''));
            return $this->success('KNOWLEDGE_EXPORT_PAGE', $this->t('knowledge/messages.export_page', 'Page exported'), [
                'format' => 'markdown',
                'content' => $markdown,
                'filename' => $page['slug'] . '.md',
            ]);
        }
        return $this->success('KNOWLEDGE_EXPORT_PAGE', $this->t('knowledge/messages.export_page', 'Page exported'), [
            'format' => 'json',
            'page' => $export,
        ]);
    }

    public function exportSpace(array $params): JsonResponse
    {
        $space = $this->repo()->space((string)$params['public_id'], $this->actor());
        if (!$space) {
            return $this->error('KNOWLEDGE_SPACE_NOT_FOUND', $this->t('knowledge/messages.space_not_found', 'Knowledge space not found'), 404);
        }
        $format = (string)$this->request()->input('format', 'json');
        $pages = $this->repo()->pages(['space_public_id' => (string)$params['public_id'], 'limit' => 500], $this->actor());
        $exportPages = [];
        foreach ($pages as $page) {
            $exportPages[] = [
                'public_id' => $page['public_id'],
                'title' => $page['title'],
                'page_type' => $page['page_type'],
                'status' => $page['status'],
                'content_html' => $page['content_html'],
                'content_text' => $page['content_text'],
                'slug' => $page['slug'],
                'path' => $page['path'],
                'created_at' => $page['created_at'],
                'updated_at' => $page['updated_at'],
            ];
        }
        if ($format === 'markdown') {
            $md = '# Space: ' . $space['title'] . "\n\n";
            $md .= 'Description: ' . ($space['description'] ?? '') . "\n\n";
            $md .= '---' . "\n\n";
            foreach ($exportPages as $ep) {
                $md .= '## ' . $ep['title'] . "\n\n";
                $md .= '**Type:** ' . $ep['page_type'] . ' | **Status:** ' . $ep['status'] . "\n\n";
                $md .= $this->htmlToMarkdown((string)($ep['content_html'] ?? '')) . "\n\n";
                $md .= '---' . "\n\n";
            }
            return $this->success('KNOWLEDGE_EXPORT_SPACE', $this->t('knowledge/messages.export_space', 'Space exported'), [
                'format' => 'markdown',
                'content' => $md,
                'filename' => $space['slug'] . '.md',
            ]);
        }
        return $this->success('KNOWLEDGE_EXPORT_SPACE', $this->t('knowledge/messages.export_space', 'Space exported'), [
            'format' => 'json',
            'space' => [
                'title' => $space['title'],
                'slug' => $space['slug'],
                'description' => $space['description'],
            ],
            'pages' => $exportPages,
        ]);
    }

    public function importPages(): JsonResponse
    {
        $auth = $this->user();
        $input = $this->request()->allInput();
        $format = (string)($input['format'] ?? 'json');
        $spacePublicId = trim((string)($input['space_public_id'] ?? ''));
        $data = $input['data'] ?? null;

        if ($data === null || !is_array($data)) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'data' => [$this->t('common/messages.field_required', 'Field is required')],
            ]);
        }

        if ($format === 'json') {
            return $this->importFromJson($data, $spacePublicId, $auth);
        }

        if ($format === 'markdown') {
            return $this->importFromMarkdown($data, $spacePublicId, $auth);
        }

        return $this->error('VALIDATION_ERROR', $this->t('knowledge/messages.invalid_import_format', 'Invalid import format'), 422);
    }

    private function importFromJson(array $data, string $spacePublicId, array $auth): JsonResponse
    {
        $userId = $this->actorUserId() ?: null;
        $imported = [];
        $errors = [];

        // Single page import
        if (isset($data['title']) || isset($data['page'])) {
            $pageData = $data['page'] ?? $data;
            try {
                $input = [
                    'title' => (string)($pageData['title'] ?? 'Imported Page'),
                    'space_public_id' => $spacePublicId ?: (string)($pageData['space_public_id'] ?? ''),
                    'page_type' => (string)($pageData['page_type'] ?? 'article'),
                    'status' => (string)($pageData['status'] ?? 'draft'),
                    'content_html' => (string)($pageData['content_html'] ?? ''),
                    'content_text' => (string)($pageData['content_text'] ?? ''),
                ];
                if (empty($input['space_public_id'])) {
                    $spaces = $this->repo()->spaces([], $this->actor());
                    if (!empty($spaces)) {
                        $input['space_public_id'] = (string)$spaces[0]['public_id'];
                    } else {
                        $errors[] = 'No available space for import';
                    }
                }
                if (!empty($input['space_public_id'])) {
                    $page = $this->repo()->createPage($input, $userId, $this->actor());
                    $imported[] = [
                        'public_id' => $page['public_id'] ?? null,
                        'title' => $page['title'] ?? $input['title'],
                    ];
                }
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        // Bulk pages import (from export format)
        if (isset($data['pages']) && is_array($data['pages'])) {
            foreach ($data['pages'] as $pageData) {
                try {
                    $input = [
                        'title' => (string)($pageData['title'] ?? 'Imported Page'),
                        'space_public_id' => $spacePublicId ?: (string)($pageData['space_public_id'] ?? (isset($data['space']) ? ($data['space']['slug'] ?? '') : '')),
                        'page_type' => (string)($pageData['page_type'] ?? 'article'),
                        'status' => (string)($pageData['status'] ?? 'draft'),
                        'content_html' => (string)($pageData['content_html'] ?? ''),
                        'content_text' => (string)($pageData['content_text'] ?? ''),
                    ];
                    if (empty($input['space_public_id'])) {
                        $spaces = $this->repo()->spaces([], $this->actor());
                        if (!empty($spaces)) {
                            $input['space_public_id'] = (string)$spaces[0]['public_id'];
                        } else {
                            $errors[] = 'No available space for import';
                            continue;
                        }
                    }
                    $page = $this->repo()->createPage($input, $userId, $this->actor());
                    $imported[] = [
                        'public_id' => $page['public_id'] ?? null,
                        'title' => $page['title'] ?? $input['title'],
                    ];
                } catch (\Throwable $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        return $this->success('KNOWLEDGE_IMPORT_COMPLETED', $this->t('knowledge/messages.import_completed', 'Import completed'), [
            'imported' => count($imported),
            'pages' => $imported,
            'errors' => $errors,
        ], 201);
    }

    private function importFromMarkdown(array $data, string $spacePublicId, array $auth): JsonResponse
    {
        $userId = $this->actorUserId() ?: null;
        $imported = [];
        $errors = [];

        // Support both single markdown and array of markdown pages
        $pages = [];
        if (isset($data['content'])) {
            $pages[] = ['title' => $data['title'] ?? 'Imported', 'content' => $data['content']];
        } elseif (isset($data['pages'])) {
            foreach ($data['pages'] as $p) {
                $pages[] = $p;
            }
        } elseif (isset($data['content_raw'])) {
            $pages[] = ['title' => $data['title'] ?? 'Imported', 'content' => $data['content_raw']];
        }

        foreach ($pages as $pageData) {
            try {
                $title = (string)($pageData['title'] ?? 'Imported');
                $markdown = (string)($pageData['content'] ?? $pageData['content_raw'] ?? '');
                $html = $this->markdownToHtml($markdown);

                $input = [
                    'title' => $title,
                    'space_public_id' => $spacePublicId,
                    'page_type' => 'article',
                    'status' => 'draft',
                    'content_html' => $html,
                    'content_text' => strip_tags($html),
                ];
                if (empty($input['space_public_id'])) {
                    $spaces = $this->repo()->spaces([], $this->actor());
                    if (!empty($spaces)) {
                        $input['space_public_id'] = (string)$spaces[0]['public_id'];
                    } else {
                        $errors[] = 'No available space for import';
                        continue;
                    }
                }
                $page = $this->repo()->createPage($input, $userId, $this->actor());
                $imported[] = [
                    'public_id' => $page['public_id'] ?? null,
                    'title' => $page['title'] ?? $title,
                ];
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        return $this->success('KNOWLEDGE_IMPORT_COMPLETED', $this->t('knowledge/messages.import_completed', 'Import completed'), [
            'imported' => count($imported),
            'pages' => $imported,
            'errors' => $errors,
        ], 201);
    }

    /**
     * Basic Markdown to HTML converter for import.
     */
    private function markdownToHtml(string $markdown): string
    {
        $html = $markdown;

        // Headers
        $html = preg_replace('/^##### (.+)$/m', '<h5>$1</h5>', $html) ?? $html;
        $html = preg_replace('/^#### (.+)$/m', '<h4>$1</h4>', $html) ?? $html;
        $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html) ?? $html;
        $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html) ?? $html;
        $html = preg_replace('/^# (.+)$/m', '<h2>$1</h2>', $html) ?? $html;

        // Bold and italic
        $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html) ?? $html;
        $html = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $html) ?? $html;

        // Links
        $html = preg_replace('/\[([^\]]+)\]\(([^\)]+)\)/', '<a href="$2">$1</a>', $html) ?? $html;

        // Unordered lists
        $html = preg_replace('/^\s*[-*]\s+(.+)$/m', '<li>$1</li>', $html) ?? $html;
        $html = preg_replace('/(<li>.*?<\/li>(\s*<li>.*?<\/li>)*)/s', '<ul>$1</ul>', $html) ?? $html;

        // Ordered lists
        $html = preg_replace('/^\s*\d+\.\s+(.+)$/m', '<li>$1</li>', $html) ?? $html;

        // Code blocks
        $html = preg_replace('/```(\w*)\n(.*?)```/s', '<pre><code>$2</code></pre>', $html) ?? $html;
        $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html) ?? $html;

        // Blockquotes
        $html = preg_replace('/^>\s+(.+)$/m', '<blockquote><p>$1</p></blockquote>', $html) ?? $html;

        // Horizontal rules
        $html = preg_replace('/^---$/m', '<hr>', $html) ?? $html;

        // Paragraphs - wrap remaining text
        $html = preg_replace('/^(?!<[houblcp]|\s*$)(.+)$/m', '<p>$1</p>', $html) ?? $html;

        // Clean up nested paragraphs inside blockquotes/lists
        $html = preg_replace('/<blockquote><p>(.*?)<\/p><\/blockquote>/s', '<blockquote>$1</blockquote>', $html) ?? $html;

        return $html;
    }

    private function htmlToMarkdown(string $html): string
    {
        $html = preg_replace('#<hr[^>]*>#i', "\n---\n", $html) ?? $html;
        $html = preg_replace('#</?(p|div|section|article)>#i', "\n\n", $html) ?? $html;
        $html = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        $html = preg_replace('#<(h[1-6])[^>]*>(.*?)</\1>#is', function (array $m): string {
            $level = (int)substr($m[1], 1);
            return str_repeat('#', $level) . ' ' . trim($m[2]) . "\n\n";
        }, $html) ?? $html;
        $html = preg_replace('#<li[^>]*>(.*?)</li>#is', "- $1\n", $html) ?? $html;
        $html = preg_replace('#</?ul[^>]*>#i', "\n", $html) ?? $html;
        $html = preg_replace('#</?ol[^>]*>#i', "\n", $html) ?? $html;
        $html = preg_replace('#<strong[^>]*>(.*?)</strong>#is', '**$1**', $html) ?? $html;
        $html = preg_replace('#<em[^>]*>(.*?)</em>#is', '*$1*', $html) ?? $html;
        $html = preg_replace('#<a[^>]*href=["\'](.*?)["\'][^>]*>(.*?)</a>#is', '[$2]($1)', $html) ?? $html;
        $html = preg_replace('#<code[^>]*>(.*?)</code>#is', '`$1`', $html) ?? $html;
        $html = preg_replace('#<pre[^>]*>(.*?)</pre>#is', "```\n$1\n```\n", $html) ?? $html;
        $html = preg_replace('#<blockquote[^>]*>(.*?)</blockquote>#is', "> $1\n\n", $html) ?? $html;
        $html = preg_replace('#<img[^>]*src=["\'](.*?)["\'][^>]*alt=["\'](.*?)["\'][^>]*/?>#is', '![$2]($1)', $html) ?? $html;
        $html = preg_replace('#<img[^>]*src=["\'](.*?)["\'][^>]*/?>#is', '![]($1)', $html) ?? $html;
        $html = strip_tags($html);
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = preg_replace('/\n{4,}/', "\n\n\n", $html) ?? $html;
        return trim($html);
    }

    private function tagRepo(): TagRepository
    {
        return new TagRepository($this->container->get('db.pdo'));
    }

    public function listPageTags(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'view')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $items = $this->tagRepo()->listByEntity('knowledge_page', (string)$params['public_id']);
        return $this->success('KNOWLEDGE_PAGE_TAGS', $this->t('knowledge/messages.page_tags', 'Page tags loaded'), ['items' => $items]);
    }

    public function attachPageTag(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'edit')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $tag = $this->tagRepo()->findByPublicId((string)$params['tag_public_id']);
        if (!$tag) {
            return $this->error('TAG_NOT_FOUND', $this->t('knowledge/messages.tag_not_found', 'Tag not found'), 404);
        }
        $this->tagRepo()->assignToEntity('knowledge_page', (string)$params['public_id'], (int)$tag['id']);
        $this->invalidateCache('knowledge');
        return $this->success('KNOWLEDGE_PAGE_TAG_ATTACHED', $this->t('knowledge/messages.tag_attached', 'Tag attached'));
    }

    public function detachPageTag(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'edit')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $tag = $this->tagRepo()->findByPublicId((string)$params['tag_public_id']);
        if (!$tag) {
            return $this->error('TAG_NOT_FOUND', $this->t('knowledge/messages.tag_not_found', 'Tag not found'), 404);
        }
        $ok = $this->tagRepo()->detachFromEntity('knowledge_page', (string)$params['public_id'], (int)$tag['id']);
        if (!$ok) {
            return $this->error('PAGE_TAG_NOT_FOUND', $this->t('knowledge/messages.page_tag_not_found', 'Tag not attached to this page'), 404);
        }
        $this->invalidateCache('knowledge');
        return $this->success('KNOWLEDGE_PAGE_TAG_DETACHED', $this->t('knowledge/messages.tag_detached', 'Tag detached'));
    }

    private function setPageStatus(array $params, string $status, string $code, string $message): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'edit')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $auth = $this->user();
        $page = $this->repo()->setStatus((string)$params['public_id'], $status, $this->actorUserId() ?: null);
        if (!$page) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $this->invalidateCache('knowledge');
        $this->auditLog('knowledge_page', (string)$params['public_id'], 'page_status_' . $status, [
            'title' => $page['title'] ?? '',
        ]);
        return $this->success($code, $message, ['page' => $page]);
    }

    private function auditLog(string $entityType, string $entityPublicId, string $action, array $details = []): void
    {
        try {
            $pdo = $this->container->get('db.pdo');
            $now = gmdate('Y-m-d H:i:s');
            $publicId = bin2hex(random_bytes(10));
            $auth = $this->user();
            $actorPub = ($auth['user']['public_id'] ?? '');
            $detailsJson = json_encode($details, JSON_UNESCAPED_UNICODE);
            $stmt = $pdo->prepare('INSERT INTO audit_logs (public_id, actor_public_id, entity_type, entity_public_id, action, details, created_at) VALUES (:public_id, :actor, :entity_type, :entity_pub, :action, :details, :created_at)');
            $stmt->execute([
                'public_id' => $publicId,
                'actor' => $actorPub,
                'entity_type' => $entityType,
                'entity_pub' => $entityPublicId,
                'action' => $action,
                'details' => $detailsJson,
                'created_at' => $now,
            ]);
        } catch (\Throwable) {
        }
    }

    private function notifyPageEvent(array $page, string $event, array $auth): void
    {
        if (!$this->container->has('service.notification')) {
            return;
        }
        $subscriberIds = $this->repo()->pageSubscriberIds((string)($page['public_id'] ?? ''));
        $actorId = $this->actorUserId();
        $title = ($page['title'] ?? '');
        $publicId = ($page['public_id'] ?? '');
        $actorName = ($auth['user']['name'] ?? $auth['user']['login'] ?? '');
        $link = 'index.php?route=knowledge-page&id=' . urlencode($publicId);
        $targetIds = $subscriberIds;
        switch ($event) {
            case 'published':
                $notifTitle = $this->t('knowledge/messages.notif_published_title', 'Page published');
                $notifBody = $this->t('knowledge/messages.notif_published_body', 'Page "%s" was published by %s');
                $actionCode = 'knowledge_page_published';
                break;
            case 'review_requested':
                $notifTitle = $this->t('knowledge/messages.notif_review_requested_title', 'Review requested');
                $notifBody = $this->t('knowledge/messages.notif_review_requested_body', 'Page "%s" was sent for review by %s');
                $actionCode = 'knowledge_review_requested';
                break;
            case 'review_rejected':
                $notifTitle = $this->t('knowledge/messages.notif_review_rejected_title', 'Review rejected');
                $notifBody = $this->t('knowledge/messages.notif_review_rejected_body', 'Review for page "%s" was rejected by %s');
                $actionCode = 'knowledge_review_rejected';
                break;
            case 'review_approved':
                $notifTitle = $this->t('knowledge/messages.notif_review_approved_title', 'Review approved');
                $notifBody = $this->t('knowledge/messages.notif_review_approved_body', 'Review for page "%s" was approved by %s');
                $actionCode = 'knowledge_review_approved';
                break;
            case 'mentioned':
                $notifTitle = $this->t('knowledge/messages.notif_mentioned_title', 'Page mentioned');
                $notifBody = $this->t('knowledge/messages.notif_mentioned_body', 'Page "%s" was mentioned by %s');
                $actionCode = 'knowledge_page_mentioned';
                break;
            case 'deleted':
                $notifTitle = $this->t('knowledge/messages.notif_deleted_title', 'Page deleted');
                $notifBody = $this->t('knowledge/messages.notif_deleted_body', 'Page "%s" was deleted by %s');
                $actionCode = 'knowledge_page_deleted';
                break;
            default:
                $notifTitle = $this->t('knowledge/messages.notif_updated_title', 'Page updated');
                $notifBody = $this->t('knowledge/messages.notif_updated_body', 'Page "%s" was updated by %s');
                $actionCode = 'knowledge_page_updated';
        }
        if (empty($targetIds)) {
            return;
        }
        $this->container->get('service.notification')->notifyUsers($targetIds, [
            'category' => 'knowledge',
            'title' => $notifTitle,
            'body' => sprintf($notifBody, $title, $actorName),
            'entity_type' => 'knowledge_page',
            'entity_public_id' => $publicId,
            'action_code' => $actionCode,
            'actor_user_id' => $actorId,
            'actor_public_id' => $auth['user']['public_id'] ?? null,
            'actor_name' => $actorName,
            'link' => $link,
        ], $actorId);
    }

    private function indexPageForSemanticSearch(array $page): void
    {
        if (!$this->container->has('service.ai_semantic_index')) {
            return;
        }
        $publicId = trim((string)($page['public_id'] ?? ''));
        $title = trim((string)($page['title'] ?? ''));
        $contentText = trim((string)($page['content_text'] ?? ''));
        $combined = $title . "\n\n" . $contentText;
        if ($publicId === '' || $combined === '') {
            return;
        }
        $spaceTitle = trim((string)($page['space_title'] ?? ''));
        $meta = [
            'entity_type' => 'knowledge',
            'entity_public_id' => $publicId,
            'space_title' => $spaceTitle,
            'page_type' => trim((string)($page['page_type'] ?? 'article')),
            'status' => trim((string)($page['status'] ?? 'published')),
        ];
        try {
            $this->container->get('service.ai_semantic_index')->indexEntityDocument('knowledge', $publicId, $combined, $meta);
        } catch (\Throwable $e) {
            // Indexing failure is non-critical
        }
    }

    private function notifyComment(string $pagePublicId, array $comment, array $auth): void
    {
        if (!$this->container->has('service.notification')) {
            return;
        }
        $page = $this->repo()->page($pagePublicId);
        if (!$page) {
            return;
        }
        $actorId = $this->actorUserId();
        $title = ($page['title'] ?? '');
        $publicId = $pagePublicId;
        $link = 'index.php?route=knowledge-page&id=' . urlencode($publicId);
        $subscriberIds = $this->repo()->pageSubscriberIds($publicId);
        $targetIds = array_values(array_unique(array_map('intval', $subscriberIds)));
        if (empty($targetIds)) {
            return;
        }
        $this->container->get('service.notification')->notifyUsers($targetIds, [
            'category' => 'knowledge',
            'title' => $this->t('knowledge/messages.notif_commented_title', 'New comment'),
            'body' => sprintf($this->t('knowledge/messages.notif_commented_body', 'Page "%s" has a new comment'), $title),
            'entity_type' => 'knowledge_page',
            'entity_public_id' => $publicId,
            'action_code' => 'knowledge_page_commented',
            'actor_user_id' => $actorId,
            'actor_public_id' => $auth['user']['public_id'] ?? null,
            'actor_name' => $auth['user']['name'] ?? $auth['user']['login'] ?? '',
            'link' => $link,
        ], $actorId);
    }

    public function adminGetSettings(): JsonResponse
    {
        $settings = $this->container->get('service.setting');
        $items = $settings->list(['scope' => 'knowledge', 'limit' => 100]);
        $map = [];
        foreach (($items['items'] ?? []) as $item) {
            $map[(string)$item['name']] = $item['value'] ?? null;
        }
        return $this->success('KNOWLEDGE_SETTINGS', $this->t('knowledge/messages.settings_loaded', 'Settings loaded'), [
            'settings' => $map,
        ]);
    }

    public function adminUpdateSettings(): JsonResponse
    {
        $input = $this->request()->allInput();
        $settings = $this->container->get('service.setting');
        foreach ($input as $name => $value) {
            $settings->set('knowledge', (string)$name, $value);
        }
        return $this->success('KNOWLEDGE_SETTINGS_UPDATED', $this->t('knowledge/messages.settings_updated', 'Settings updated'));
    }

    public function adminReindex(): JsonResponse
    {
        $this->repo()->reindexSearch();
        $this->auditLog('knowledge_admin', 'all', 'reindex');
        return $this->success('KNOWLEDGE_REINDEX_STARTED', $this->t('knowledge/messages.reindex_completed', 'Search index rebuild completed'));
    }

    public function adminRebuildPermissions(): JsonResponse
    {
        $pdo = $this->container->get('db.pdo');
        $pdo->exec('UPDATE knowledge_spaces SET permissions_version = permissions_version + 1');
        $this->auditLog('knowledge_admin', 'all', 'rebuild_permissions');
        return $this->success('KNOWLEDGE_PERMISSIONS_REBUILT', $this->t('knowledge/messages.permissions_rebuilt', 'Permissions version bumped'));
    }

    public function adminCleanupDrafts(): JsonResponse
    {
        $stmt = $this->container->get('db.pdo')->prepare("DELETE FROM knowledge_drafts WHERE updated_at < :cutoff");
        $cutoff = gmdate('Y-m-d H:i:s', time() - 90 * 86400);
        $stmt->execute(['cutoff' => $cutoff]);
        $deleted = $stmt->rowCount();
        $this->auditLog('knowledge_admin', 'all', 'cleanup_drafts', ['deleted_count' => $deleted]);
        return $this->success('KNOWLEDGE_DRAFTS_CLEANED', $this->t('knowledge/messages.drafts_cleaned', 'Old drafts cleaned'), [
            'deleted_count' => $deleted,
        ]);
    }

    /**
     * GET /api/v1/knowledge/project/{project_public_id}/client-pages
     *
     * Returns client-visible knowledge pages linked to a project. Used by
     * external (portal) users to read knowledge articles that staff have
     * explicitly marked as client-visible. Read-only, no comments or
     * internal metadata exposed.
     */
    public function clientProjectPages(array $params): JsonResponse
    {
        $actor = $this->actor();
        if (empty((int)($actor['is_external'] ?? 0))) {
            return $this->error('EXTERNAL_ACCESS_DENIED', $this->t('common/messages.external_access_only'), 403);
        }

        $projectPublicId = trim((string)($params['project_public_id'] ?? ''));
        if ($projectPublicId === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error', 'Validation error'), 422);
        }

        // Verify the external user has access to this project
        $projectService = $this->container->get('service.project');
        $project = $projectService->get($projectPublicId, $actor);
        if (!$project) {
            return $this->error('KNOWLEDGE_PROJECT_NOT_FOUND', $this->t('knowledge/messages.space_not_found', 'Not found'), 404);
        }

        // Query client-visible pages linked to this project via entity links
        $pdo = $this->container->get('db.pdo');
        $stmt = $pdo->prepare(
            "SELECT p.public_id, p.title, p.excerpt, p.status, p.page_type,
                    p.updated_at, p.views_count, p.content_html
             FROM knowledge_entity_links l
             JOIN knowledge_pages p ON p.id = l.page_id
             JOIN knowledge_spaces s ON s.id = p.space_id
             WHERE l.entity_type = 'project'
               AND l.entity_public_id = :project_pid
               AND p.deleted_at IS NULL
               AND p.client_visible = 1
               AND s.visibility = 'public'
             ORDER BY p.updated_at DESC"
        );
        $stmt->execute(['project_pid' => $projectPublicId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Strip internal metadata for external users
        $items = array_map(static function (array $row): array {
            return [
                'public_id' => $row['public_id'] ?? '',
                'title' => $row['title'] ?? '',
                'excerpt' => $row['excerpt'] ?? '',
                'status' => $row['status'] ?? '',
                'page_type' => $row['page_type'] ?? '',
                'updated_at' => $row['updated_at'] ?? '',
                'views_count' => (int)($row['views_count'] ?? 0),
                // Return content_html directly for external users (read-only view)
                'content_html' => $row['content_html'] ?? null,
            ];
        }, $rows);

        return $this->success('KNOWLEDGE_CLIENT_PAGES', $this->t('knowledge/messages.entity_pages', 'Related pages loaded'), [
            'items' => $items,
        ]);
    }

    /**
     * GET /api/v1/knowledge/client-page/{page_public_id}
     *
     * Returns a single client-visible knowledge page for an external user.
     * The page must have client_visible=1 and be linked to a project the
     * user has access to.
     */
    public function getClientPage(array $params): JsonResponse
    {
        $actor = $this->actor();
        if (empty((int)($actor['is_external'] ?? 0))) {
            return $this->error('EXTERNAL_ACCESS_DENIED', $this->t('common/messages.external_access_only'), 403);
        }

        $pagePublicId = trim((string)($params['public_id'] ?? ''));
        if ($pagePublicId === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error', 'Validation error'), 422);
        }

        $pdo = $this->container->get('db.pdo');
        $stmt = $pdo->prepare(
            "SELECT p.public_id, p.title, p.excerpt, p.status, p.page_type,
                    p.content_html, p.content_text, p.updated_at, p.created_at,
                    p.views_count, s.public_id AS space_public_id, s.title AS space_title
             FROM knowledge_pages p
             JOIN knowledge_spaces s ON s.id = p.space_id
             WHERE p.public_id = :pid
               AND p.deleted_at IS NULL
               AND p.client_visible = 1"
        );
        $stmt->execute(['pid' => $pagePublicId]);
        $page = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($page)) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Page not found'), 404);
        }

        // Verify the page is linked to a project the external user can access
        $stmt2 = $pdo->prepare(
            "SELECT l.entity_public_id FROM knowledge_entity_links l
             WHERE l.page_id = (SELECT id FROM knowledge_pages WHERE public_id = :pid LIMIT 1)
               AND l.entity_type = 'project'"
        );
        $stmt2->execute(['pid' => $pagePublicId]);
        $linkedProjectIds = $stmt2->fetchAll(\PDO::FETCH_COLUMN) ?: [];

        if ($linkedProjectIds === []) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Page not found'), 404);
        }

        // Check access to at least one linked project
        $projectService = $this->container->get('service.project');
        $hasAccess = false;
        foreach ($linkedProjectIds as $projectPid) {
            if ($projectService->get((string)$projectPid, $actor)) {
                $hasAccess = true;
                break;
            }
        }
        if (!$hasAccess) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Page not found'), 404);
        }

        // Increment views
        $pdo->prepare('UPDATE knowledge_pages SET views_count = views_count + 1 WHERE public_id = :pid')
            ->execute(['pid' => $pagePublicId]);

        // Strip internal metadata for external users
        $item = [
            'public_id' => $page['public_id'] ?? '',
            'title' => $page['title'] ?? '',
            'excerpt' => $page['excerpt'] ?? '',
            'status' => $page['status'] ?? '',
            'page_type' => $page['page_type'] ?? '',
            'content_html' => $page['content_html'] ?? null,
            'content_text' => $page['content_text'] ?? null,
            'updated_at' => $page['updated_at'] ?? '',
            'created_at' => $page['created_at'] ?? '',
            'views_count' => (int)($page['views_count'] ?? 0) + 1,
            'space_public_id' => $page['space_public_id'] ?? '',
            'space_title' => $page['space_title'] ?? '',
        ];

        return $this->success('KNOWLEDGE_CLIENT_PAGE_DETAIL', $this->t('knowledge/messages.page_detail', 'Page loaded'), [
            'page' => $item,
        ]);
    }
}
