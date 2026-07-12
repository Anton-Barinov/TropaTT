<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Knowledge\KnowledgePageVersionRepository;
use Api\System\Library\Logger\JsonLogger;

final class KnowledgePageVersionService
{
    private const ALLOWED_COLORS = ['yellow', 'blue', 'green', 'pink', 'purple', 'gray', 'orange', 'red'];

    public function __construct(
        private readonly KnowledgePageVersionRepository $versions,
        private readonly ?ProjectService $projectService = null,
        private readonly ?JsonLogger $logger = null,
        private readonly ?string $requestId = null,
    ) {
    }

    /**
     * List versions for a page.
     * @return array|string|null
     */
    public function listVersions(string $pagePublicId, array $filters, array $actor): array|string|null
    {
        $pageId = $this->versions->pageIdByPublicId($pagePublicId);
        if ($pageId === null) {
            return 'KNOWLEDGE_PAGE_NOT_FOUND';
        }

        $page = $this->versions->getPage($pagePublicId);
        if (!$page) {
            return 'KNOWLEDGE_PAGE_NOT_FOUND';
        }

        $result = $this->versions->listByPageId($pageId, $filters);

        // Enrich with user display names
        $userIds = [];
        foreach ($result['items'] as $item) {
            if (!empty($item['created_by_user_id'])) {
                $userIds[(int)$item['created_by_user_id']] = true;
            }
        }
        $userNames = [];
        foreach (array_keys($userIds) as $uid) {
            $u = $this->versions->userById($uid);
            if ($u) {
                $userNames[$uid] = (string)($u['full_name'] ?: $u['login'] ?: $u['public_id']);
            }
        }

        $items = [];
        foreach ($result['items'] as $item) {
            $uid = (int)($item['created_by_user_id'] ?? 0);
            $items[] = [
                'public_id' => $item['public_id'],
                'page_public_id' => $item['page_public_id'],
                'version_number' => (int)$item['version_number'],
                'title' => $item['title'],
                'change_type' => $item['change_type'],
                'change_note' => $item['change_note'],
                'created_by_user_public_id' => $uid > 0 ? ($userNames[$uid] ?? null) : null,
                'created_by_display_name' => $item['created_by_display_name'] ?: ($userNames[$uid] ?? null),
                'content_hash' => $item['content_hash'],
                'created_at' => $item['created_at'],
            ];
        }

        return [
            'items' => $items,
            'meta' => [
                'pagination' => [
                    'page' => $result['page'],
                    'limit' => $result['limit'],
                    'total' => $result['total'],
                    'pages' => $result['pages'],
                ],
            ],
        ];
    }

    /**
     * Get a specific version.
     * @return array|string|null
     */
    public function getVersion(string $pagePublicId, string $versionPublicId, array $actor): array|string|null
    {
        $page = $this->versions->getPage($pagePublicId);
        if (!$page) {
            return 'KNOWLEDGE_PAGE_NOT_FOUND';
        }

        $version = $this->versions->findByPublicId($versionPublicId);
        if (!$version) {
            return 'KNOWLEDGE_PAGE_VERSION_NOT_FOUND';
        }

        if ((string)($version['page_public_id'] ?? '') !== $pagePublicId) {
            return 'KNOWLEDGE_PAGE_VERSION_NOT_FOUND';
        }

        // Enrich with user info
        $uid = (int)($version['created_by_user_id'] ?? 0);
        $version['created_by_display_name'] = $version['created_by_display_name']
            ?: ($uid > 0 ? ($this->versions->userById($uid)['full_name'] ?? null) : null);

        return $version;
    }

    /**
     * Create a version from page data snapshot.
     */
    public function createVersionFromPage(array $page, array $actor, array $context = []): array|string|null
    {
        $pageId = (int)($page['id'] ?? 0);
        if ($pageId <= 0) {
            return 'KNOWLEDGE_PAGE_NOT_FOUND';
        }

        $pagePublicId = (string)($page['public_id'] ?? '');

        // Build snapshot
        $snapshot = $this->buildSnapshot($page);
        $contentHash = $this->computeContentHash($snapshot);

        // Check for duplicate content
        $latest = $this->versions->latestByPageId($pageId);
        if ($latest && ($latest['content_hash'] ?? '') === $contentHash) {
            return 'KNOWLEDGE_PAGE_VERSION_DUPLICATE_CONTENT';
        }

        $nextVersion = $this->versions->nextVersionNumberForPageId($pageId);
        $changeType = (string)($context['change_type'] ?? ($nextVersion === 1 ? 'create' : 'update'));
        $sourceType = (string)($context['source_type'] ?? 'web');
        $changeNote = $context['change_note'] ?? null;

        $actorId = (int)($actor['id'] ?? 0);
        $actorDisplayName = (string)($actor['full_name'] ?: $actor['login'] ?: $actor['public_id'] ?? '');

        $payload = [
            'page_id' => $pageId,
            'page_public_id' => $pagePublicId,
            'version_number' => $nextVersion,
            'title' => $snapshot['title'],
            'content' => $snapshot['content'],
            'content_text' => $snapshot['content_text'],
            'summary' => $snapshot['summary'],
            'visibility' => $snapshot['visibility'],
            'status' => $snapshot['status'],
            'tags_json' => $snapshot['tags'],
            'links_json' => $snapshot['links'],
            'meta_json' => $snapshot['meta'],
            'change_type' => $changeType,
            'change_note' => $changeNote,
            'created_by_user_id' => $actorId,
            'created_by_display_name' => $actorDisplayName,
            'source_type' => $sourceType,
            'request_id' => $this->requestId,
            'content_hash' => $contentHash,
        ];

        $version = $this->versions->create($payload);

        // Update last_version_number on the page
        $this->versions->updatePageLock($pagePublicId, [
            'last_version_number' => $nextVersion,
        ]);

        return $version;
    }

    /**
     * Restore a previous version.
     */
    public function restoreVersion(string $pagePublicId, string $versionPublicId, array $input, array $actor): array|string|null
    {
        $page = $this->versions->getPage($pagePublicId);
        if (!$page) {
            return 'KNOWLEDGE_PAGE_NOT_FOUND';
        }

        // Check if page is locked
        if (!empty($page['locked_at']) && !empty($page['locked_by_user_id'])) {
            return 'KNOWLEDGE_PAGE_LOCKED';
        }

        // Check row_version if provided
        if (isset($input['row_version']) && (int)$input['row_version'] !== (int)($page['row_version'] ?? 1)) {
            return 'ROW_VERSION_CONFLICT';
        }

        $version = $this->versions->findByPublicId($versionPublicId);
        if (!$version) {
            return 'KNOWLEDGE_PAGE_VERSION_NOT_FOUND';
        }

        if ((string)($version['page_public_id'] ?? '') !== $pagePublicId) {
            return 'KNOWLEDGE_PAGE_VERSION_NOT_FOUND';
        }

        // Build update set from version data
        $updateSet = [
            'title' => $version['title'],
            'content_html' => $version['content'] ?? '',
            'content_text' => $version['content_text'] ?? '',
            'last_editor_user_id' => (int)($actor['id'] ?? 0),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];

        $changeNote = (string)($input['change_note'] ?? '');
        if ($changeNote === '') {
            $changeNote = 'Restored version ' . (int)$version['version_number'];
        }

        // Update the page
        $updatedPage = $this->versions->updatePage($pagePublicId, $updateSet);

        // Create a new version marking the restore
        if ($updatedPage) {
            $actorDisplayName = (string)($actor['full_name'] ?: $actor['login'] ?: $actor['public_id'] ?? '');
            $snapshot = $this->buildSnapshot($updatedPage);
            $contentHash = $this->computeContentHash($snapshot);
            $nextVersion = $this->versions->nextVersionNumberForPageId((int)$updatedPage['id']);

            $this->versions->create([
                'page_id' => (int)$updatedPage['id'],
                'page_public_id' => $pagePublicId,
                'version_number' => $nextVersion,
                'title' => $snapshot['title'],
                'content' => $snapshot['content'],
                'content_text' => $snapshot['content_text'],
                'summary' => $snapshot['summary'],
                'visibility' => $snapshot['visibility'],
                'status' => $snapshot['status'],
                'tags_json' => $snapshot['tags'],
                'links_json' => $snapshot['links'],
                'meta_json' => $snapshot['meta'],
                'change_type' => 'restore',
                'change_note' => $changeNote,
                'restored_from_version_number' => (int)$version['version_number'],
                'restored_from_version_public_id' => $versionPublicId,
                'created_by_user_id' => (int)($actor['id'] ?? 0),
                'created_by_display_name' => $actorDisplayName,
                'source_type' => 'web',
                'request_id' => $this->requestId,
                'content_hash' => $contentHash,
            ]);

            $this->versions->updatePageLock($pagePublicId, [
                'last_version_number' => $nextVersion,
            ]);
        }

        return $this->versions->getPage($pagePublicId);
    }

    /**
     * Lock a page.
     */
    public function lockPage(string $pagePublicId, array $input, array $actor): array|string|null
    {
        $page = $this->versions->getPage($pagePublicId);
        if (!$page) {
            return 'KNOWLEDGE_PAGE_NOT_FOUND';
        }

        // Check row_version if provided
        if (isset($input['row_version']) && (int)$input['row_version'] !== (int)($page['row_version'] ?? 1)) {
            return 'ROW_VERSION_CONFLICT';
        }

        // Check if already locked
        if (!empty($page['locked_at']) && !empty($page['locked_by_user_id'])) {
            return 'KNOWLEDGE_PAGE_ALREADY_LOCKED';
        }

        $now = gmdate('Y-m-d H:i:s');
        $reason = trim((string)($input['reason'] ?? ''));
        if (mb_strlen($reason) > 1000) {
            return 'KNOWLEDGE_PAGE_INVALID_CHANGE_NOTE';
        }

        $this->versions->updatePageLock($pagePublicId, [
            'locked_at' => $now,
            'locked_by_user_id' => (int)($actor['id'] ?? 0),
            'lock_reason' => $reason !== '' ? $reason : null,
        ]);

        return $this->versions->getPage($pagePublicId);
    }

    /**
     * Unlock a page.
     */
    public function unlockPage(string $pagePublicId, array $input, array $actor): array|string|null
    {
        $page = $this->versions->getPage($pagePublicId);
        if (!$page) {
            return 'KNOWLEDGE_PAGE_NOT_FOUND';
        }

        // Check row_version if provided
        if (isset($input['row_version']) && (int)$input['row_version'] !== (int)($page['row_version'] ?? 1)) {
            return 'ROW_VERSION_CONFLICT';
        }

        // Idempotent: if not locked, return success
        if (empty($page['locked_at'])) {
            return $page;
        }

        $this->versions->updatePageLock($pagePublicId, [
            'locked_at' => null,
            'locked_by_user_id' => null,
            'lock_reason' => null,
        ]);

        return $this->versions->getPage($pagePublicId);
    }

    /**
     * Diff current version with a specific version.
     */
    public function diffVersion(string $pagePublicId, string $versionPublicId, array $actor): array|string|null
    {
        $page = $this->versions->getPage($pagePublicId);
        if (!$page) {
            return 'KNOWLEDGE_PAGE_NOT_FOUND';
        }

        $version = $this->versions->findByPublicId($versionPublicId);
        if (!$version) {
            return 'KNOWLEDGE_PAGE_VERSION_NOT_FOUND';
        }

        if ((string)($version['page_public_id'] ?? '') !== $pagePublicId) {
            return 'KNOWLEDGE_PAGE_VERSION_NOT_FOUND';
        }

        $currentTitle = (string)($page['title'] ?? '');
        $versionTitle = (string)($version['title'] ?? '');
        $currentText = (string)($page['content_text'] ?? '');
        $versionText = (string)($version['content_text'] ?? '');
        $currentSummary = (string)($page['excerpt'] ?? '');
        $versionSummary = (string)($version['summary'] ?? '');

        return [
            'title_changed' => $currentTitle !== $versionTitle,
            'content_changed' => $currentText !== $versionText,
            'summary_changed' => $currentSummary !== $versionSummary,
            'current' => [
                'title' => $currentTitle,
                'content_text' => mb_substr($currentText, 0, 5000),
                'summary' => $currentSummary,
            ],
            'version' => [
                'version_number' => (int)$version['version_number'],
                'title' => $versionTitle,
                'content_text' => mb_substr($versionText, 0, 5000),
                'summary' => $versionSummary,
            ],
            'stats' => [
                'current_chars' => mb_strlen($currentText),
                'version_chars' => mb_strlen($versionText),
                'delta_chars' => mb_strlen($currentText) - mb_strlen($versionText),
            ],
        ];
    }

    /**
     * Build a snapshot from a page record.
     */
    public function buildSnapshot(array $page): array
    {
        $tagsJson = $page['tags_json'] ?? null;
        $linksJson = $page['links_json'] ?? null;
        $metaJson = $page['meta_json'] ?? null;

        return [
            'title' => (string)($page['title'] ?? ''),
            'content' => $page['content_html'] ?? '',
            'content_text' => $page['content_text'] ?? '',
            'summary' => $page['excerpt'] ?? '',
            'visibility' => $page['visibility'] ?? null,
            'status' => $page['status'] ?? null,
            'tags' => $tagsJson,
            'links' => $linksJson,
            'meta' => $metaJson,
        ];
    }

    /**
     * Compute SHA-256 content hash from a snapshot.
     */
    public function computeContentHash(array $snapshot): string
    {
        $normalized = json_encode([
            'title' => $snapshot['title'] ?? '',
            'content' => $snapshot['content'] ?? '',
            'content_text' => $snapshot['content_text'] ?? '',
            'summary' => $snapshot['summary'] ?? '',
            'visibility' => $snapshot['visibility'] ?? '',
            'status' => $snapshot['status'] ?? '',
            'tags' => $snapshot['tags'] ?? '[]',
            'links' => $snapshot['links'] ?? '[]',
            'meta' => $snapshot['meta'] ?? '{}',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', $normalized);
    }
}
