<?php
declare(strict_types=1);

namespace Api\Controller\Knowledge;

use Api\Controller\Common\BaseController;
use Api\Model\Knowledge\KnowledgeRepository;
use Api\Model\Tag\TagRepository;
use Api\System\Library\Http\JsonResponse;
use Api\System\Library\Service\FileService;
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

        return $this->withIdempotency(function () use ($input, $auth): JsonResponse {
            $space = $this->repo()->createSpace($input, $this->actorUserId() ?: null);
            $this->invalidateCache('knowledge');
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
        $result = $this->repo()->updateSpace((string)$params['public_id'], $this->request()->allInput());
        if ($result === 'ROW_VERSION_CONFLICT') {
            return $this->error('ROW_VERSION_CONFLICT', $this->t('knowledge/messages.row_version_conflict', 'The record was changed by another user'), 409);
        }
        if (!$result) {
            return $this->error('KNOWLEDGE_SPACE_NOT_FOUND', $this->t('knowledge/messages.space_not_found', 'Knowledge space not found'), 404);
        }
        $this->invalidateCache('knowledge');
        return $this->success('KNOWLEDGE_SPACE_UPDATED', $this->t('knowledge/messages.space_updated', 'Knowledge space updated'), [
            'space' => $result,
        ], meta: ['row_version' => (int)($result['row_version'] ?? 1)]);
    }

    public function archiveSpace(array $params): JsonResponse
    {
        if (!$this->repo()->archiveSpace((string)$params['public_id'], true)) {
            return $this->error('KNOWLEDGE_SPACE_NOT_FOUND', $this->t('knowledge/messages.space_not_found', 'Knowledge space not found'), 404);
        }
        $this->invalidateCache('knowledge');
        return $this->success('KNOWLEDGE_SPACE_ARCHIVED', $this->t('knowledge/messages.space_archived', 'Knowledge space archived'));
    }

    public function restoreSpace(array $params): JsonResponse
    {
        if (!$this->repo()->archiveSpace((string)$params['public_id'], false)) {
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
        $items = $this->repo()->spacePermissions((string)$params['public_id']);
        return $this->success('KNOWLEDGE_SPACE_PERMISSIONS', $this->t('knowledge/messages.space_permissions', 'Space permissions loaded'), [
            'items' => $items,
        ]);
    }

    public function addSpacePermission(array $params): JsonResponse
    {
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
        $this->repo()->removeSpacePermission($id);
        $this->invalidateCache('knowledge');
        return $this->success('KNOWLEDGE_SPACE_PERMISSION_REMOVED', $this->t('knowledge/messages.space_permission_removed', 'Space permission removed'));
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

        return $this->withIdempotency(function () use ($input, $auth): JsonResponse {
            try {
                $page = $this->repo()->createPage($input, $this->actorUserId() ?: null, $this->actor());
            } catch (\RuntimeException $e) {
                return $this->error('VALIDATION_ERROR', $e->getMessage(), 422);
            }
            $this->invalidateCache('knowledge');
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
            'links' => $this->repo()->links((string)$params['public_id']),
        ], meta: ['row_version' => (int)($page['row_version'] ?? 1)]);
    }

    public function updatePage(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'edit')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $auth = $this->user();
        $result = $this->repo()->updatePage((string)$params['public_id'], $this->request()->allInput(), $this->actorUserId() ?: null, $this->actor());
        if ($result === 'ROW_VERSION_CONFLICT') {
            return $this->error('ROW_VERSION_CONFLICT', $this->t('knowledge/messages.row_version_conflict', 'The record was changed by another user'), 409);
        }
        if (!$result) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $this->invalidateCache('knowledge');
        if (($result['status'] ?? '') === 'published') {
            $this->notifyPageEvent($result, 'updated', $auth);
        }
        return $this->success('KNOWLEDGE_PAGE_UPDATED', $this->t('knowledge/messages.page_updated', 'Knowledge page updated'), [
            'page' => $result,
        ], meta: ['row_version' => (int)($result['row_version'] ?? 1)]);
    }

    public function deletePage(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'manage')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        if (!$this->repo()->deletePage((string)$params['public_id'])) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $this->invalidateCache('knowledge');
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
        $this->notifyPageEvent($page, 'published', $auth);
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
        return $this->publish($params);
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
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $e->getMessage(), 404);
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

    public function versions(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'view')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        return $this->success('KNOWLEDGE_VERSIONS', $this->t('knowledge/messages.versions', 'Versions loaded'), [
            'items' => $this->repo()->versions((string)$params['public_id']),
        ]);
    }

    public function restoreVersion(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'edit')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $auth = $this->user();
        $page = $this->repo()->restoreVersion((string)$params['public_id'], (int)$params['version_number'], $this->actorUserId() ?: null, $this->actor());
        if (!$page) {
            return $this->error('KNOWLEDGE_VERSION_NOT_FOUND', $this->t('knowledge/messages.version_not_found', 'Version not found'), 404);
        }
        $this->invalidateCache('knowledge');
        return $this->success('KNOWLEDGE_VERSION_RESTORED', $this->t('knowledge/messages.version_restored', 'Version restored'), [
            'page' => $page,
        ]);
    }

    public function diff(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'view')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $from = (int)$this->request()->input('from', 0);
        $to = (int)$this->request()->input('to', 0);
        return $this->success('KNOWLEDGE_VERSION_DIFF', $this->t('knowledge/messages.diff', 'Version diff loaded'), $this->repo()->diff((string)$params['public_id'], $from, $to));
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
            'stats' => $this->repo()->analytics(),
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
            'items' => $this->repo()->links((string)$params['public_id']),
        ]);
    }

    public function linkEntity(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'edit')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $auth = $this->user();
        $input = $this->request()->allInput();
        try {
            $link = $this->repo()->linkEntity(
                (string)$params['public_id'],
                (string)($input['entity_type'] ?? ''),
                (string)($input['entity_public_id'] ?? ''),
                (string)($input['relation_type'] ?? 'related'),
                $this->actorUserId() ?: null
            );
        } catch (\RuntimeException $e) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $e->getMessage(), 404);
        }
        return $this->success('KNOWLEDGE_LINK_CREATED', $this->t('knowledge/messages.link_created', 'Link created'), [
            'link' => $link,
        ], 201);
    }

    public function deleteLink(array $params): JsonResponse
    {
        $pageId = (string)$params['public_id'];
        if (!$this->requirePageAccess($pageId, 'edit')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        try {
            $this->repo()->unlinkEntity((string)$params['link_public_id']);
        } catch (\RuntimeException $e) {
            return $this->error('KNOWLEDGE_LINK_NOT_FOUND', $e->getMessage(), 404);
        }
        return $this->success('KNOWLEDGE_LINK_DELETED', $this->t('knowledge/messages.link_deleted', 'Link deleted'));
    }

    public function comments(array $params): JsonResponse
    {
        if (!$this->requirePageAccess((string)$params['public_id'], 'view')) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        return $this->success('KNOWLEDGE_COMMENTS', $this->t('knowledge/messages.comments', 'Comments loaded'), [
            'items' => $this->repo()->comments((string)$params['public_id']),
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
        return $this->success('KNOWLEDGE_ENTITY_PAGES', $this->t('knowledge/messages.entity_pages', 'Related pages loaded'), [
            'items' => array_values(array_filter(
                $this->repo()->entityPages((string)$params['entity_type'], (string)$params['entity_public_id']),
                fn(array $page): bool => $this->repo()->page((string)($page['public_id'] ?? ''), $this->actor()) !== null
            )),
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
            return $this->error('FILE_UPLOAD_ERROR', $this->t('file/messages.upload_error', 'Upload error'), 422, [
                'file' => [$e->getMessage()],
            ]);
        }
    }

    public function deleteFile(array $params): JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var FileService $service */
        $service = $this->container->get('service.file');
        $ok = $service->delete((string)$params['file_public_id'], $authUser['user']);

        if (!$ok) {
            return $this->error('FILE_NOT_FOUND', $this->t('file/messages.not_found', 'File not found'), 404);
        }

        return $this->success('KNOWLEDGE_FILE_DELETED', $this->t('knowledge/messages.file_deleted', 'File deleted'));
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
        return $this->success($code, $message, ['page' => $page]);
    }

    private function notifyPageEvent(array $page, string $event, array $auth): void
    {
        if (!$this->container->has('service.notification')) {
            return;
        }
        $subscriberIds = $this->repo()->pageSubscriberIds((string)($page['public_id'] ?? ''));
        if (empty($subscriberIds)) {
            return;
        }
        $actorId = $this->actorUserId();
        $title = ($page['title'] ?? '');
        $publicId = ($page['public_id'] ?? '');
        $actorName = ($auth['user']['name'] ?? $auth['user']['login'] ?? '');
        $link = 'index.php?route=knowledge-page&id=' . urlencode($publicId);
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
            default:
                $notifTitle = $this->t('knowledge/messages.notif_updated_title', 'Page updated');
                $notifBody = $this->t('knowledge/messages.notif_updated_body', 'Page "%s" was updated by %s');
                $actionCode = 'knowledge_page_updated';
        }
        $this->container->get('service.notification')->notifyUsers($subscriberIds, [
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
}
