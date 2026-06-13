<?php
declare(strict_types=1);

namespace Api\Controller\Knowledge;

use Api\Controller\Common\BaseController;
use Api\Model\Knowledge\KnowledgeRepository;
use Api\System\Library\Http\JsonResponse;

final class KnowledgeController extends BaseController
{
    private function repo(): KnowledgeRepository
    {
        return new KnowledgeRepository($this->container->get('db.pdo'));
    }

    public function overview(): JsonResponse
    {
        $cache = $this->cacheApi();
        if ($cache !== null) {
            $data = $cache->remember('knowledge', 'overview:' . $this->cacheUserId(), 60, fn(): array => $this->repo()->overview($this->request()->allInput()));
        } else {
            $data = $this->repo()->overview($this->request()->allInput());
        }

        return $this->success('KNOWLEDGE_OVERVIEW', $this->t('knowledge/messages.overview', 'Knowledge overview loaded'), $data);
    }

    public function spaces(): JsonResponse
    {
        return $this->success('KNOWLEDGE_SPACES', $this->t('knowledge/messages.spaces', 'Knowledge spaces loaded'), [
            'items' => $this->repo()->spaces($this->request()->allInput()),
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
            $space = $this->repo()->createSpace($input, (int)($auth['user']['id'] ?? 0) ?: null);
            $this->invalidateCache('knowledge');
            return $this->success('KNOWLEDGE_SPACE_CREATED', $this->t('knowledge/messages.space_created', 'Knowledge space created'), [
                'space' => $space,
            ], 201, ['row_version' => (int)($space['row_version'] ?? 1)]);
        });
    }

    public function getSpace(array $params): JsonResponse
    {
        $space = $this->repo()->space((string)$params['public_id']);
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
            'items' => $this->repo()->tree((string)$params['public_id'], (int)($this->request()->input('depth', 10))),
        ]);
    }

    public function pages(): JsonResponse
    {
        return $this->success('KNOWLEDGE_PAGES', $this->t('knowledge/messages.pages', 'Knowledge pages loaded'), [
            'items' => $this->repo()->pages($this->request()->allInput()),
        ]);
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
                $page = $this->repo()->createPage($input, (int)($auth['user']['id'] ?? 0) ?: null);
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
        $page = $this->repo()->page((string)$params['public_id']);
        if (!$page) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $auth = $this->user();
        $this->repo()->recordView((string)$params['public_id'], (int)($auth['user']['id'] ?? 0) ?: null, (string)$this->request()->input('source', 'direct'));
        return $this->success('KNOWLEDGE_PAGE_DETAIL', $this->t('knowledge/messages.page_detail', 'Knowledge page loaded'), [
            'page' => $page,
            'links' => $this->repo()->links((string)$params['public_id']),
        ], meta: ['row_version' => (int)($page['row_version'] ?? 1)]);
    }

    public function updatePage(array $params): JsonResponse
    {
        $auth = $this->user();
        $result = $this->repo()->updatePage((string)$params['public_id'], $this->request()->allInput(), (int)($auth['user']['id'] ?? 0) ?: null);
        if ($result === 'ROW_VERSION_CONFLICT') {
            return $this->error('ROW_VERSION_CONFLICT', $this->t('knowledge/messages.row_version_conflict', 'The record was changed by another user'), 409);
        }
        if (!$result) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $this->invalidateCache('knowledge');
        return $this->success('KNOWLEDGE_PAGE_UPDATED', $this->t('knowledge/messages.page_updated', 'Knowledge page updated'), [
            'page' => $result,
        ], meta: ['row_version' => (int)($result['row_version'] ?? 1)]);
    }

    public function deletePage(array $params): JsonResponse
    {
        if (!$this->repo()->deletePage((string)$params['public_id'])) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $this->invalidateCache('knowledge');
        return $this->success('KNOWLEDGE_PAGE_DELETED', $this->t('knowledge/messages.page_deleted', 'Knowledge page deleted'));
    }

    public function publish(array $params): JsonResponse
    {
        $auth = $this->user();
        $page = $this->repo()->publish((string)$params['public_id'], (int)($auth['user']['id'] ?? 0) ?: null, (string)$this->request()->input('change_summary', ''));
        if (!$page) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $this->invalidateCache('knowledge');
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
        return $this->setPageStatus($params, 'review', 'KNOWLEDGE_REVIEW_REQUESTED', $this->t('knowledge/messages.review_requested', 'Review requested'));
    }

    public function approveReview(array $params): JsonResponse
    {
        return $this->publish($params);
    }

    public function rejectReview(array $params): JsonResponse
    {
        return $this->setPageStatus($params, 'draft', 'KNOWLEDGE_REVIEW_REJECTED', $this->t('knowledge/messages.review_rejected', 'Review rejected'));
    }

    public function duplicatePage(array $params): JsonResponse
    {
        $auth = $this->user();
        $page = $this->repo()->duplicate((string)$params['public_id'], (int)($auth['user']['id'] ?? 0) ?: null);
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
        $auth = $this->user();
        $input = $this->request()->allInput();
        $result = $this->repo()->updatePage((string)$params['public_id'], [
            'space_public_id' => $input['space_public_id'] ?? null,
            'parent_public_id' => $input['parent_public_id'] ?? null,
            'sort_order' => $input['sort_order'] ?? null,
        ], (int)($auth['user']['id'] ?? 0) ?: null);
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
        $auth = $this->user();
        $draft = $this->repo()->draft((string)$params['public_id'], (int)($auth['user']['id'] ?? 0));
        return $this->success('KNOWLEDGE_DRAFT', $this->t('knowledge/messages.draft', 'Draft loaded'), [
            'draft' => $draft,
        ]);
    }

    public function saveDraft(array $params): JsonResponse
    {
        $auth = $this->user();
        try {
            $draft = $this->repo()->saveDraft((string)$params['public_id'], $this->request()->allInput(), (int)($auth['user']['id'] ?? 0));
        } catch (\RuntimeException $e) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $e->getMessage(), 404);
        }
        return $this->success('KNOWLEDGE_DRAFT_SAVED', $this->t('knowledge/messages.draft_saved', 'Draft saved'), [
            'draft' => $draft,
        ]);
    }

    public function deleteDraft(array $params): JsonResponse
    {
        $auth = $this->user();
        $this->repo()->deleteDraft((string)$params['public_id'], (int)($auth['user']['id'] ?? 0));
        return $this->success('KNOWLEDGE_DRAFT_DELETED', $this->t('knowledge/messages.draft_deleted', 'Draft deleted'));
    }

    public function versions(array $params): JsonResponse
    {
        return $this->success('KNOWLEDGE_VERSIONS', $this->t('knowledge/messages.versions', 'Versions loaded'), [
            'items' => $this->repo()->versions((string)$params['public_id']),
        ]);
    }

    public function restoreVersion(array $params): JsonResponse
    {
        $auth = $this->user();
        $page = $this->repo()->restoreVersion((string)$params['public_id'], (int)$params['version_number'], (int)($auth['user']['id'] ?? 0) ?: null);
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
        $from = (int)$this->request()->input('from', 0);
        $to = (int)$this->request()->input('to', 0);
        return $this->success('KNOWLEDGE_VERSION_DIFF', $this->t('knowledge/messages.diff', 'Version diff loaded'), $this->repo()->diff((string)$params['public_id'], $from, $to));
    }

    public function search(): JsonResponse
    {
        $query = (string)$this->request()->input('q', '');
        return $this->success('KNOWLEDGE_SEARCH', $this->t('knowledge/messages.search', 'Knowledge search completed'), [
            'items' => $this->repo()->search($query, $this->request()->allInput()),
        ]);
    }

    public function recent(): JsonResponse
    {
        return $this->success('KNOWLEDGE_RECENT', $this->t('knowledge/messages.recent', 'Recent pages loaded'), [
            'items' => $this->repo()->pages(['limit' => (int)$this->request()->input('limit', 20), 'sort' => 'updated_at', 'order' => 'DESC']),
        ]);
    }

    public function popular(): JsonResponse
    {
        return $this->success('KNOWLEDGE_POPULAR', $this->t('knowledge/messages.popular', 'Popular pages loaded'), [
            'items' => $this->repo()->popular((int)$this->request()->input('limit', 20)),
        ]);
    }

    public function reviewQueue(): JsonResponse
    {
        return $this->success('KNOWLEDGE_REVIEW_QUEUE', $this->t('knowledge/messages.review_queue', 'Review queue loaded'), [
            'items' => $this->repo()->pages(['status' => 'review', 'limit' => (int)$this->request()->input('limit', 50)]),
        ]);
    }

    public function outdated(): JsonResponse
    {
        return $this->success('KNOWLEDGE_OUTDATED', $this->t('knowledge/messages.outdated', 'Outdated pages loaded'), [
            'items' => $this->repo()->outdated((int)$this->request()->input('limit', 50)),
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
        $template = $this->repo()->createTemplate($input, (int)($auth['user']['id'] ?? 0) ?: null);
        return $this->success('KNOWLEDGE_TEMPLATE_CREATED', $this->t('knowledge/messages.template_created', 'Template created'), [
            'template' => $template,
        ], 201);
    }

    public function links(array $params): JsonResponse
    {
        return $this->success('KNOWLEDGE_LINKS', $this->t('knowledge/messages.links', 'Links loaded'), [
            'items' => $this->repo()->links((string)$params['public_id']),
        ]);
    }

    public function linkEntity(array $params): JsonResponse
    {
        $auth = $this->user();
        $input = $this->request()->allInput();
        try {
            $link = $this->repo()->linkEntity(
                (string)$params['public_id'],
                (string)($input['entity_type'] ?? ''),
                (string)($input['entity_public_id'] ?? ''),
                (string)($input['relation_type'] ?? 'related'),
                (int)($auth['user']['id'] ?? 0) ?: null
            );
        } catch (\RuntimeException $e) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $e->getMessage(), 404);
        }
        return $this->success('KNOWLEDGE_LINK_CREATED', $this->t('knowledge/messages.link_created', 'Link created'), [
            'link' => $link,
        ], 201);
    }

    public function comments(array $params): JsonResponse
    {
        return $this->success('KNOWLEDGE_COMMENTS', $this->t('knowledge/messages.comments', 'Comments loaded'), [
            'items' => $this->repo()->comments((string)$params['public_id']),
        ]);
    }

    public function addComment(array $params): JsonResponse
    {
        $auth = $this->user();
        $input = $this->request()->allInput();
        $body = trim((string)($input['body'] ?? ''));
        if ($body === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'body' => [$this->t('common/messages.field_required', 'Field is required')],
            ]);
        }
        $comment = $this->repo()->addComment((string)$params['public_id'], $body, (int)($auth['user']['id'] ?? 0), (string)($input['parent_public_id'] ?? '') ?: null);
        if (!$comment) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        return $this->success('KNOWLEDGE_COMMENT_CREATED', $this->t('knowledge/messages.comment_created', 'Comment added'), [
            'comment' => $comment,
        ], 201);
    }

    public function deleteComment(array $params): JsonResponse
    {
        $auth = $this->user();
        if (!$this->repo()->deleteComment((string)$params['comment_public_id'], (int)($auth['user']['id'] ?? 0))) {
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
        $auth = $this->user();
        $publicId = $this->repo()->favoritePage((string)$params['public_id'], (int)($auth['user']['id'] ?? 0));
        if ($publicId === null) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        return $this->success('KNOWLEDGE_PAGE_FAVORITED', $this->t('knowledge/messages.page_favorited', 'Page added to favorites'), [
            'favorite_public_id' => $publicId,
        ]);
    }

    public function unfavoritePage(array $params): JsonResponse
    {
        $auth = $this->user();
        $this->repo()->unfavoritePage((string)$params['public_id'], (int)($auth['user']['id'] ?? 0));
        return $this->success('KNOWLEDGE_PAGE_UNFAVORITED', $this->t('knowledge/messages.page_unfavorited', 'Page removed from favorites'));
    }

    public function subscribePage(array $params): JsonResponse
    {
        $auth = $this->user();
        $publicId = $this->repo()->subscribePage((string)$params['public_id'], (int)($auth['user']['id'] ?? 0));
        if ($publicId === null) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        return $this->success('KNOWLEDGE_PAGE_SUBSCRIBED', $this->t('knowledge/messages.page_subscribed', 'Subscribed to page updates'), [
            'subscription_public_id' => $publicId,
        ]);
    }

    public function unsubscribePage(array $params): JsonResponse
    {
        $auth = $this->user();
        $this->repo()->unsubscribePage((string)$params['public_id'], (int)($auth['user']['id'] ?? 0));
        return $this->success('KNOWLEDGE_PAGE_UNSUBSCRIBED', $this->t('knowledge/messages.page_unsubscribed', 'Unsubscribed from page updates'));
    }

    public function entityPages(array $params): JsonResponse
    {
        return $this->success('KNOWLEDGE_ENTITY_PAGES', $this->t('knowledge/messages.entity_pages', 'Related pages loaded'), [
            'items' => $this->repo()->entityPages((string)$params['entity_type'], (string)$params['entity_public_id']),
        ]);
    }

    private function setPageStatus(array $params, string $status, string $code, string $message): JsonResponse
    {
        $auth = $this->user();
        $page = $this->repo()->setStatus((string)$params['public_id'], $status, (int)($auth['user']['id'] ?? 0) ?: null);
        if (!$page) {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }
        $this->invalidateCache('knowledge');
        return $this->success($code, $message, ['page' => $page]);
    }
}
