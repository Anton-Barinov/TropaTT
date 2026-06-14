<?php
declare(strict_types=1);

namespace Api\Model\Knowledge;

use PDO;

final class KnowledgeRepository
{
    private const PAGE_TYPES = [
        'article',
        'instruction',
        'regulation',
        'faq',
        'checklist',
        'runbook',
        'meeting_note',
        'decision',
        'client_note',
        'project_note',
        'onboarding',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function overview(array $filters = []): array
    {
        return [
            'spaces' => $this->spaces(['include_archived' => false]),
            'recent' => $this->pages(['limit' => 8, 'sort' => 'updated_at', 'order' => 'DESC']),
            'popular' => $this->popular(8),
            'drafts' => $this->pages(['status' => 'draft', 'limit' => 8, 'sort' => 'updated_at', 'order' => 'DESC']),
            'review_queue' => $this->pages(['status' => 'review', 'limit' => 8, 'sort' => 'updated_at', 'order' => 'DESC']),
            'outdated' => $this->outdated(8),
            'totals' => $this->totals(),
        ];
    }

    public function spaces(array $filters = []): array
    {
        $includeArchived = !empty($filters['include_archived']);
        $where = $includeArchived ? '1=1' : 'is_archived = 0';
        $stmt = $this->pdo->query("SELECT * FROM knowledge_spaces WHERE {$where} ORDER BY sort_order ASC, title ASC");
        $spaces = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        foreach ($spaces as &$space) {
            $space['pages_count'] = $this->countPages((int)$space['id']);
            $space['is_archived'] = (int)($space['is_archived'] ?? 0);
        }
        unset($space);
        return $spaces;
    }

    public function createSpace(array $payload, ?int $actorId): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $publicId = $this->publicId('kbs');
        $title = trim((string)($payload['title'] ?? ''));
        $slug = $this->uniqueSlug('knowledge_spaces', $this->slug((string)($payload['slug'] ?? $title), 'space'), null);
        $stmt = $this->pdo->prepare('INSERT INTO knowledge_spaces (public_id, title, slug, description, icon, color, owner_user_id, visibility, default_access_level, sort_order, created_at, updated_at) VALUES (:public_id, :title, :slug, :description, :icon, :color, :owner_user_id, :visibility, :default_access_level, :sort_order, :created_at, :updated_at)');
        $stmt->execute([
            'public_id' => $publicId,
            'title' => $title,
            'slug' => $slug,
            'description' => $this->nullableText($payload['description'] ?? null),
            'icon' => $this->nullableShort($payload['icon'] ?? 'book-open', 64),
            'color' => $this->nullableShort($payload['color'] ?? '#0f8f72', 32),
            'owner_user_id' => $actorId,
            'visibility' => $this->choice((string)($payload['visibility'] ?? 'public'), ['public', 'restricted', 'private'], 'public'),
            'default_access_level' => $this->choice((string)($payload['default_access_level'] ?? 'view'), ['view', 'comment', 'edit'], 'view'),
            'sort_order' => (int)($payload['sort_order'] ?? 100),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $this->space($publicId) ?? [];
    }

    public function space(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM knowledge_spaces WHERE public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $publicId]);
        $space = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($space)) {
            return null;
        }
        $space['pages_count'] = $this->countPages((int)$space['id']);
        return $space;
    }

    public function updateSpace(string $publicId, array $payload): array|string|null
    {
        $current = $this->space($publicId);
        if (!$current) {
            return null;
        }
        if (isset($payload['row_version']) && (int)$payload['row_version'] !== (int)($current['row_version'] ?? 1)) {
            return 'ROW_VERSION_CONFLICT';
        }
        $set = [
            'title' => trim((string)($payload['title'] ?? $current['title'])),
            'description' => $this->nullableText($payload['description'] ?? $current['description'] ?? null),
            'icon' => $this->nullableShort($payload['icon'] ?? $current['icon'] ?? null, 64),
            'color' => $this->nullableShort($payload['color'] ?? $current['color'] ?? null, 32),
            'visibility' => $this->choice((string)($payload['visibility'] ?? $current['visibility'] ?? 'public'), ['public', 'restricted', 'private'], 'public'),
            'default_access_level' => $this->choice((string)($payload['default_access_level'] ?? $current['default_access_level'] ?? 'view'), ['view', 'comment', 'edit'], 'view'),
            'sort_order' => (int)($payload['sort_order'] ?? $current['sort_order'] ?? 100),
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'public_id' => $publicId,
        ];
        if (!empty($payload['slug'])) {
            $set['slug'] = $this->uniqueSlug('knowledge_spaces', $this->slug((string)$payload['slug'], 'space'), (int)$current['id']);
            $sql = 'UPDATE knowledge_spaces SET title = :title, slug = :slug, description = :description, icon = :icon, color = :color, visibility = :visibility, default_access_level = :default_access_level, sort_order = :sort_order, row_version = row_version + 1, updated_at = :updated_at WHERE public_id = :public_id';
        } else {
            $sql = 'UPDATE knowledge_spaces SET title = :title, description = :description, icon = :icon, color = :color, visibility = :visibility, default_access_level = :default_access_level, sort_order = :sort_order, row_version = row_version + 1, updated_at = :updated_at WHERE public_id = :public_id';
        }
        $this->pdo->prepare($sql)->execute($set);
        return $this->space($publicId);
    }

    public function spacePermissions(string $publicId): array
    {
        $space = $this->space($publicId);
        if (!$space) {
            return [];
        }
        $stmt = $this->pdo->prepare('
            SELECT p.id, p.subject_type, p.subject_id, p.access_level, p.created_at,
                   u.public_id AS user_public_id, u.name AS user_name,
                   r.public_id AS role_public_id, r.title AS role_title
            FROM knowledge_space_permissions p
            LEFT JOIN users u ON u.id = p.subject_id AND p.subject_type = \'user\'
            LEFT JOIN roles r ON r.id = p.subject_id AND p.subject_type = \'role\'
            WHERE p.space_id = :space_id
            ORDER BY p.created_at DESC
        ');
        $stmt->execute(['space_id' => (int)$space['id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function addSpacePermission(string $publicId, string $subjectType, int $subjectId, string $accessLevel, ?int $actorId): ?array
    {
        $space = $this->space($publicId);
        if (!$space) {
            return null;
        }
        $allowedTypes = ['user', 'role', 'team', 'department'];
        if (!in_array($subjectType, $allowedTypes, true)) {
            return null;
        }
        $allowedLevels = ['view', 'comment', 'edit', 'manage', 'owner'];
        $accessLevel = $this->choice($accessLevel, $allowedLevels, 'view');

        $now = gmdate('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('INSERT INTO knowledge_space_permissions (space_id, subject_type, subject_id, access_level, created_by_user_id, created_at) VALUES (:space_id, :subject_type, :subject_id, :access_level, :created_by_user_id, :created_at)');
        $stmt->execute([
            'space_id' => (int)$space['id'],
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'access_level' => $accessLevel,
            'created_by_user_id' => $actorId,
            'created_at' => $now,
        ]);
        $id = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare('UPDATE knowledge_spaces SET permissions_version = permissions_version + 1, updated_at = :updated_at WHERE id = :id')->execute(['updated_at' => $now, 'id' => (int)$space['id']]);

        $stmt = $this->pdo->prepare('
            SELECT p.id, p.subject_type, p.subject_id, p.access_level, p.created_at,
                   u.public_id AS user_public_id, u.name AS user_name,
                   r.public_id AS role_public_id, r.title AS role_title
            FROM knowledge_space_permissions p
            LEFT JOIN users u ON u.id = p.subject_id AND p.subject_type = \'user\'
            LEFT JOIN roles r ON r.id = p.subject_id AND p.subject_type = \'role\'
            WHERE p.id = :id
        ');
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function removeSpacePermission(int $permissionId): bool
    {
        $lookup = $this->pdo->prepare('SELECT space_id FROM knowledge_space_permissions WHERE id = :id LIMIT 1');
        $lookup->execute(['id' => $permissionId]);
        $spaceId = $lookup->fetchColumn();
        if ($spaceId === false) {
            return false;
        }

        $stmt = $this->pdo->prepare('DELETE FROM knowledge_space_permissions WHERE id = :id');
        $stmt->execute(['id' => $permissionId]);
        if ($stmt->rowCount() > 0) {
            $now = gmdate('Y-m-d H:i:s');
            $this->pdo->prepare('UPDATE knowledge_spaces SET permissions_version = permissions_version + 1, updated_at = :updated_at WHERE id = :space_id')->execute([
                'updated_at' => $now,
                'space_id' => (int)$spaceId,
            ]);
            return true;
        }
        return false;
    }

    public function archiveSpace(string $publicId, bool $archived): bool
    {
        $stmt = $this->pdo->prepare('UPDATE knowledge_spaces SET is_archived = :archived, row_version = row_version + 1, updated_at = :updated_at WHERE public_id = :public_id');
        $stmt->execute(['archived' => $archived ? 1 : 0, 'updated_at' => gmdate('Y-m-d H:i:s'), 'public_id' => $publicId]);
        return $stmt->rowCount() > 0;
    }

    public function tree(string $spacePublicId, int $depth = 10): array
    {
        $space = $this->space($spacePublicId);
        if (!$space) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT p.*, s.public_id AS space_public_id, s.title AS space_title FROM knowledge_pages p JOIN knowledge_spaces s ON s.id = p.space_id WHERE p.space_id = :space_id AND p.deleted_at IS NULL ORDER BY COALESCE(p.parent_id, 0), p.sort_order ASC, p.title ASC');
        $stmt->execute(['space_id' => (int)$space['id']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $this->buildTree($rows, null, 0, max(1, $depth));
    }

    public function pages(array $filters = []): array
    {
        [$where, $params] = $this->pageWhere($filters);
        $limit = min(100, max(1, (int)($filters['limit'] ?? 30)));
        $page = max(1, (int)($filters['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        $sort = in_array((string)($filters['sort'] ?? ''), ['title', 'created_at', 'updated_at', 'published_at', 'views_count'], true) ? (string)$filters['sort'] : 'updated_at';
        $order = strtoupper((string)($filters['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $stmt = $this->pdo->prepare("SELECT p.*, s.public_id AS space_public_id, s.title AS space_title FROM knowledge_pages p JOIN knowledge_spaces s ON s.id = p.space_id WHERE {$where} ORDER BY p.{$sort} {$order}, p.public_id {$order} LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createPage(array $payload, ?int $actorId): array
    {
        $space = $this->resolveSpace((string)($payload['space_public_id'] ?? $payload['space_id'] ?? ''));
        if (!$space) {
            $spaces = $this->spaces();
            $space = $spaces[0] ?? null;
        }
        if (!$space) {
            throw new \RuntimeException('Knowledge space is required');
        }
        $parent = $this->resolvePage((string)($payload['parent_public_id'] ?? ''));
        $title = trim((string)($payload['title'] ?? ''));
        $html = $this->sanitizeHtml((string)($payload['content_html'] ?? $payload['content'] ?? ''));
        $now = gmdate('Y-m-d H:i:s');
        $publicId = $this->publicId('kbp');
        $stmt = $this->pdo->prepare('INSERT INTO knowledge_pages (public_id, space_id, parent_id, title, slug, page_type, status, content_html, content_text, content_json, excerpt, owner_user_id, last_editor_user_id, sort_order, path, depth, created_at, updated_at) VALUES (:public_id, :space_id, :parent_id, :title, :slug, :page_type, :status, :content_html, :content_text, :content_json, :excerpt, :owner_user_id, :last_editor_user_id, :sort_order, :path, :depth, :created_at, :updated_at)');
        $stmt->execute([
            'public_id' => $publicId,
            'space_id' => (int)$space['id'],
            'parent_id' => $parent ? (int)$parent['id'] : null,
            'title' => $title,
            'slug' => $this->uniquePageSlug((int)$space['id'], $this->slug((string)($payload['slug'] ?? $title), 'page'), null),
            'page_type' => $this->choice((string)($payload['page_type'] ?? 'article'), self::PAGE_TYPES, 'article'),
            'status' => $this->choice((string)($payload['status'] ?? 'draft'), ['draft', 'review', 'published', 'archived'], 'draft'),
            'content_html' => $html,
            'content_text' => $this->contentText($html),
            'content_json' => isset($payload['content_json']) ? json_encode($payload['content_json'], JSON_UNESCAPED_UNICODE) : null,
            'excerpt' => $this->excerpt($html),
            'owner_user_id' => $actorId,
            'last_editor_user_id' => $actorId,
            'sort_order' => (int)($payload['sort_order'] ?? $this->nextPageSort((int)$space['id'], $parent ? (int)$parent['id'] : null)),
            'path' => '',
            'depth' => $parent ? ((int)($parent['depth'] ?? 0) + 1) : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $page = $this->page($publicId) ?? [];
        $this->refreshPagePath((int)$page['id']);
        $this->refreshChildrenCount($parent ? (int)$parent['id'] : null);
        if (($page['status'] ?? '') === 'published') {
            $this->addVersion($publicId, $actorId, 'Initial publish');
        }
        return $this->page($publicId) ?? $page;
    }

    public function page(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT p.*, s.public_id AS space_public_id, s.title AS space_title FROM knowledge_pages p JOIN knowledge_spaces s ON s.id = p.space_id WHERE p.public_id = :public_id AND p.deleted_at IS NULL LIMIT 1');
        $stmt->execute(['public_id' => $publicId]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($page) ? $page : null;
    }

    public function recordView(string $pagePublicId, ?int $userId, string $source = 'direct'): void
    {
        $page = $this->page($pagePublicId);
        if (!$page) {
            return;
        }
        $stmt = $this->pdo->prepare('INSERT INTO knowledge_page_views (page_id, user_id, source, viewed_at) VALUES (:page_id, :user_id, :source, :viewed_at)');
        $stmt->execute([
            'page_id' => (int)$page['id'],
            'user_id' => $userId,
            'source' => mb_substr($source !== '' ? $source : 'direct', 0, 32),
            'viewed_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->pdo->prepare('UPDATE knowledge_pages SET views_count = views_count + 1 WHERE id = :id')->execute(['id' => (int)$page['id']]);
    }

    public function updatePage(string $publicId, array $payload, ?int $actorId): array|string|null
    {
        $current = $this->page($publicId);
        if (!$current) {
            return null;
        }
        if (isset($payload['row_version']) && (int)$payload['row_version'] !== (int)($current['row_version'] ?? 1)) {
            return 'ROW_VERSION_CONFLICT';
        }
        $html = array_key_exists('content_html', $payload) || array_key_exists('content', $payload)
            ? $this->sanitizeHtml((string)($payload['content_html'] ?? $payload['content'] ?? ''))
            : (string)($current['content_html'] ?? '');
        $space = isset($payload['space_public_id']) ? $this->resolveSpace((string)$payload['space_public_id']) : null;
        $parent = array_key_exists('parent_public_id', $payload) ? $this->resolvePage((string)$payload['parent_public_id']) : false;
        $params = [
            'title' => trim((string)($payload['title'] ?? $current['title'])),
            'space_id' => $space ? (int)$space['id'] : (int)$current['space_id'],
            'parent_id' => $parent === false ? ($current['parent_id'] ?? null) : ($parent ? (int)$parent['id'] : null),
            'page_type' => $this->choice((string)($payload['page_type'] ?? $current['page_type'] ?? 'article'), self::PAGE_TYPES, 'article'),
            'status' => $this->choice((string)($payload['status'] ?? $current['status'] ?? 'draft'), ['draft', 'review', 'published', 'archived'], 'draft'),
            'content_html' => $html,
            'content_text' => $this->contentText($html),
            'content_json' => array_key_exists('content_json', $payload) ? json_encode($payload['content_json'], JSON_UNESCAPED_UNICODE) : ($current['content_json'] ?? null),
            'excerpt' => $this->excerpt($html),
            'last_editor_user_id' => $actorId,
            'review_due_at' => $this->nullableText($payload['review_due_at'] ?? $current['review_due_at'] ?? null),
            'sort_order' => (int)($payload['sort_order'] ?? $current['sort_order'] ?? 100),
            'depth' => $parent === false ? (int)($current['depth'] ?? 0) : ($parent ? ((int)($parent['depth'] ?? 0) + 1) : 0),
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'public_id' => $publicId,
        ];
        $this->pdo->prepare('UPDATE knowledge_pages SET title = :title, space_id = :space_id, parent_id = :parent_id, page_type = :page_type, status = :status, content_html = :content_html, content_text = :content_text, content_json = :content_json, excerpt = :excerpt, last_editor_user_id = :last_editor_user_id, review_due_at = :review_due_at, sort_order = :sort_order, depth = :depth, row_version = row_version + 1, updated_at = :updated_at WHERE public_id = :public_id')->execute($params);
        $page = $this->page($publicId);
        if ($page) {
            $this->refreshPagePath((int)$page['id']);
            $this->refreshChildrenCount(isset($current['parent_id']) ? (int)$current['parent_id'] : null);
            $this->refreshChildrenCount($page['parent_id'] !== null ? (int)$page['parent_id'] : null);
        }
        return $this->page($publicId);
    }

    public function publish(string $publicId, ?int $actorId, string $summary = ''): ?array
    {
        $page = $this->page($publicId);
        if (!$page) {
            return null;
        }
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare("UPDATE knowledge_pages SET status = 'published', review_status = 'approved', published_by_user_id = :actor, published_at = :published_at, reviewed_at = :reviewed_at, row_version = row_version + 1, updated_at = :updated_at WHERE public_id = :public_id");
        $stmt->execute([
            'actor' => $actorId,
            'published_at' => $now,
            'reviewed_at' => $now,
            'updated_at' => $now,
            'public_id' => $publicId,
        ]);
        $this->addVersion($publicId, $actorId, $summary !== '' ? $summary : 'Published');
        return $this->page($publicId);
    }

    public function setStatus(string $publicId, string $status, ?int $actorId = null): ?array
    {
        $page = $this->page($publicId);
        if (!$page) {
            return null;
        }
        $stmt = $this->pdo->prepare('UPDATE knowledge_pages SET status = :status, review_status = :review_status, last_editor_user_id = :actor, row_version = row_version + 1, updated_at = :updated_at WHERE public_id = :public_id');
        $stmt->execute([
            'status' => $status,
            'review_status' => $status === 'review' ? 'pending' : ($status === 'archived' ? 'archived' : null),
            'actor' => $actorId,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'public_id' => $publicId,
        ]);
        return $this->page($publicId);
    }

    public function deletePage(string $publicId): bool
    {
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('UPDATE knowledge_pages SET deleted_at = :deleted_at, row_version = row_version + 1, updated_at = :updated_at WHERE public_id = :public_id');
        $stmt->execute([
            'deleted_at' => $now,
            'updated_at' => $now,
            'public_id' => $publicId,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function duplicate(string $publicId, ?int $actorId): ?array
    {
        $page = $this->page($publicId);
        if (!$page) {
            return null;
        }
        return $this->createPage([
            'space_public_id' => $page['space_public_id'],
            'parent_public_id' => null,
            'title' => 'Копия: ' . (string)$page['title'],
            'page_type' => $page['page_type'],
            'content_html' => $page['content_html'],
            'content_json' => $page['content_json'] ? json_decode((string)$page['content_json'], true) : null,
            'status' => 'draft',
        ], $actorId);
    }

    public function saveDraft(string $pagePublicId, array $payload, int $userId): array
    {
        $page = $this->page($pagePublicId);
        if (!$page) {
            throw new \RuntimeException('Knowledge page not found');
        }
        $existing = $this->draft($pagePublicId, $userId);
        $html = $this->sanitizeHtml((string)($payload['content_html'] ?? $payload['content'] ?? $page['content_html'] ?? ''));
        $now = gmdate('Y-m-d H:i:s');
        if ($existing) {
            $stmt = $this->pdo->prepare('UPDATE knowledge_drafts SET title = :title, content_html = :content_html, content_text = :content_text, content_json = :content_json, base_row_version = :base_row_version, autosaved_at = :autosaved_at, updated_at = :updated_at WHERE public_id = :public_id');
            $stmt->execute([
                'title' => trim((string)($payload['title'] ?? $page['title'])),
                'content_html' => $html,
                'content_text' => $this->contentText($html),
                'content_json' => isset($payload['content_json']) ? json_encode($payload['content_json'], JSON_UNESCAPED_UNICODE) : null,
                'base_row_version' => (int)($page['row_version'] ?? 1),
                'autosaved_at' => $now,
                'updated_at' => $now,
                'public_id' => $existing['public_id'],
            ]);
            return $this->draft($pagePublicId, $userId) ?? $existing;
        }
        $publicId = $this->publicId('kbd');
        $stmt = $this->pdo->prepare('INSERT INTO knowledge_drafts (public_id, page_id, user_id, title, content_html, content_text, content_json, base_row_version, autosaved_at, created_at, updated_at) VALUES (:public_id, :page_id, :user_id, :title, :content_html, :content_text, :content_json, :base_row_version, :autosaved_at, :created_at, :updated_at)');
        $stmt->execute([
            'public_id' => $publicId,
            'page_id' => (int)$page['id'],
            'user_id' => $userId,
            'title' => trim((string)($payload['title'] ?? $page['title'])),
            'content_html' => $html,
            'content_text' => $this->contentText($html),
            'content_json' => isset($payload['content_json']) ? json_encode($payload['content_json'], JSON_UNESCAPED_UNICODE) : null,
            'base_row_version' => (int)($page['row_version'] ?? 1),
            'autosaved_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $this->draft($pagePublicId, $userId) ?? [];
    }

    public function draft(string $pagePublicId, int $userId): ?array
    {
        $page = $this->page($pagePublicId);
        if (!$page) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM knowledge_drafts WHERE page_id = :page_id AND user_id = :user_id LIMIT 1');
        $stmt->execute(['page_id' => (int)$page['id'], 'user_id' => $userId]);
        $draft = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($draft) ? $draft : null;
    }

    public function deleteDraft(string $pagePublicId, int $userId): bool
    {
        $page = $this->page($pagePublicId);
        if (!$page) {
            return false;
        }
        $stmt = $this->pdo->prepare('DELETE FROM knowledge_drafts WHERE page_id = :page_id AND user_id = :user_id');
        $stmt->execute(['page_id' => (int)$page['id'], 'user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public function versions(string $pagePublicId): array
    {
        $page = $this->page($pagePublicId);
        if (!$page) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT * FROM knowledge_page_versions WHERE page_id = :page_id ORDER BY version_number DESC');
        $stmt->execute(['page_id' => (int)$page['id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function restoreVersion(string $pagePublicId, int $versionNumber, ?int $actorId): ?array
    {
        $page = $this->page($pagePublicId);
        if (!$page) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM knowledge_page_versions WHERE page_id = :page_id AND version_number = :version_number LIMIT 1');
        $stmt->execute(['page_id' => (int)$page['id'], 'version_number' => $versionNumber]);
        $version = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($version)) {
            return null;
        }
        $this->updatePage($pagePublicId, [
            'title' => $version['title'],
            'content_html' => $version['content_html'],
            'content_json' => $version['content_json'] ? json_decode((string)$version['content_json'], true) : null,
        ], $actorId);
        $this->addVersion($pagePublicId, $actorId, 'Restored version ' . $versionNumber);
        return $this->page($pagePublicId);
    }

    public function diff(string $pagePublicId, int $from, int $to): array
    {
        $versions = [];
        foreach ($this->versions($pagePublicId) as $version) {
            $versions[(int)$version['version_number']] = $version;
        }
        $a = $versions[$from] ?? null;
        $b = $versions[$to] ?? null;
        return [
            'from' => $a,
            'to' => $b,
            'text_changed' => $a && $b ? ((string)($a['content_text'] ?? '') !== (string)($b['content_text'] ?? '')) : false,
        ];
    }

    public function search(string $query, array $filters = []): array
    {
        $query = trim($query);
        if ($query === '') {
            return $this->pages($filters + ['limit' => 20]);
        }
        $filters['q'] = $query;
        return $this->pages($filters + ['limit' => 30]);
    }

    public function popular(int $limit = 10): array
    {
        return $this->pages(['limit' => $limit, 'sort' => 'views_count', 'order' => 'DESC', 'status' => 'published']);
    }

    public function outdated(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("SELECT p.*, s.public_id AS space_public_id, s.title AS space_title FROM knowledge_pages p JOIN knowledge_spaces s ON s.id = p.space_id WHERE p.deleted_at IS NULL AND p.status = 'published' AND p.review_due_at IS NOT NULL AND p.review_due_at < :now ORDER BY p.review_due_at ASC LIMIT {$limit}");
        $stmt->execute(['now' => gmdate('Y-m-d H:i:s')]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function templates(array $filters = []): array
    {
        $where = 'is_active = 1';
        $params = [];
        if (!empty($filters['page_type'])) {
            $where .= ' AND page_type = :page_type';
            $params['page_type'] = (string)$filters['page_type'];
        }
        $stmt = $this->pdo->prepare("SELECT * FROM knowledge_templates WHERE {$where} ORDER BY is_system DESC, title ASC");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createTemplate(array $payload, ?int $actorId): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $publicId = $this->publicId('kbt');
        $stmt = $this->pdo->prepare('INSERT INTO knowledge_templates (public_id, title, page_type, description, content_html, content_json, is_system, is_active, created_by_user_id, created_at, updated_at) VALUES (:public_id, :title, :page_type, :description, :content_html, :content_json, 0, 1, :created_by_user_id, :created_at, :updated_at)');
        $stmt->execute([
            'public_id' => $publicId,
            'title' => trim((string)($payload['title'] ?? '')),
            'page_type' => $this->choice((string)($payload['page_type'] ?? 'article'), self::PAGE_TYPES, 'article'),
            'description' => $this->nullableText($payload['description'] ?? null),
            'content_html' => $this->sanitizeHtml((string)($payload['content_html'] ?? '')),
            'content_json' => isset($payload['content_json']) ? json_encode($payload['content_json'], JSON_UNESCAPED_UNICODE) : null,
            'created_by_user_id' => $actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $this->template($publicId) ?? [];
    }

    public function template(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM knowledge_templates WHERE public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function linkEntity(string $pagePublicId, string $entityType, string $entityPublicId, string $relationType, ?int $actorId): array
    {
        $page = $this->page($pagePublicId);
        if (!$page) {
            throw new \RuntimeException('Knowledge page not found');
        }
        $publicId = $this->publicId('kbl');
        $stmt = $this->pdo->prepare('INSERT INTO knowledge_entity_links (public_id, page_id, entity_type, entity_public_id, relation_type, created_by_user_id, created_at) VALUES (:public_id, :page_id, :entity_type, :entity_public_id, :relation_type, :created_by_user_id, :created_at)');
        $stmt->execute([
            'public_id' => $publicId,
            'page_id' => (int)$page['id'],
            'entity_type' => $entityType,
            'entity_public_id' => $entityPublicId,
            'relation_type' => $relationType !== '' ? $relationType : 'related',
            'created_by_user_id' => $actorId,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        return $this->links($pagePublicId)[0] ?? [];
    }

    public function links(string $pagePublicId): array
    {
        $page = $this->page($pagePublicId);
        if (!$page) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT * FROM knowledge_entity_links WHERE page_id = :page_id ORDER BY id DESC');
        $stmt->execute(['page_id' => (int)$page['id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function unlinkEntity(string $linkPublicId): void
    {
        $stmt = $this->pdo->prepare('SELECT page_id FROM knowledge_entity_links WHERE public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $linkPublicId]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$link) {
            throw new \RuntimeException('Knowledge link not found');
        }
        $stmt = $this->pdo->prepare('DELETE FROM knowledge_entity_links WHERE public_id = :public_id');
        $stmt->execute(['public_id' => $linkPublicId]);
    }

    public function entityPages(string $entityType, string $entityPublicId): array
    {
        $stmt = $this->pdo->prepare("SELECT p.*, s.public_id AS space_public_id, s.title AS space_title, l.relation_type FROM knowledge_entity_links l JOIN knowledge_pages p ON p.id = l.page_id JOIN knowledge_spaces s ON s.id = p.space_id WHERE l.entity_type = :entity_type AND l.entity_public_id = :entity_public_id AND p.deleted_at IS NULL ORDER BY p.updated_at DESC");
        $stmt->execute(['entity_type' => $entityType, 'entity_public_id' => $entityPublicId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function comments(string $pagePublicId): array
    {
        $page = $this->page($pagePublicId);
        if (!$page) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT c.*, u.public_id AS user_public_id, u.full_name AS user_name FROM knowledge_comments c LEFT JOIN users u ON u.id = c.user_id WHERE c.page_id = :page_id ORDER BY c.created_at ASC');
        $stmt->execute(['page_id' => (int)$page['id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function addComment(string $pagePublicId, string $body, int $userId, ?string $parentPublicId = null): ?array
    {
        $page = $this->page($pagePublicId);
        if (!$page) {
            return null;
        }
        $parentId = null;
        if ($parentPublicId !== null) {
            $pStmt = $this->pdo->prepare('SELECT id FROM knowledge_comments WHERE public_id = :public_id AND page_id = :page_id LIMIT 1');
            $pStmt->execute(['public_id' => $parentPublicId, 'page_id' => (int)$page['id']]);
            $parentId = $pStmt->fetchColumn() ?: null;
        }
        $publicId = $this->publicId('kbc');
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('INSERT INTO knowledge_comments (public_id, page_id, parent_id, user_id, body, created_at, updated_at) VALUES (:public_id, :page_id, :parent_id, :user_id, :body, :created_at, :updated_at)');
        $stmt->execute([
            'public_id' => $publicId,
            'page_id' => (int)$page['id'],
            'parent_id' => $parentId,
            'user_id' => $userId,
            'body' => $body,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->pdo->prepare('UPDATE knowledge_pages SET comments_count = comments_count + 1 WHERE id = :id')->execute(['id' => (int)$page['id']]);
        $row = $this->comment($publicId);
        $row['user_public_id'] = null;
        $row['user_name'] = null;
        return $row;
    }

    public function comment(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT c.*, u.public_id AS user_public_id, u.full_name AS user_name FROM knowledge_comments c LEFT JOIN users u ON u.id = c.user_id WHERE c.public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function deleteComment(string $publicId, int $userId): bool
    {
        $comment = $this->comment($publicId);
        if (!$comment) {
            return false;
        }
        $stmt = $this->pdo->prepare('DELETE FROM knowledge_comments WHERE public_id = :public_id AND user_id = :user_id');
        $stmt->execute(['public_id' => $publicId, 'user_id' => $userId]);
        if ($stmt->rowCount() > 0) {
            $this->pdo->prepare('UPDATE knowledge_pages SET comments_count = GREATEST(comments_count - 1, 0) WHERE id = :id')->execute(['id' => (int)$comment['page_id']]);
            return true;
        }
        return false;
    }

    public function resolveComment(string $publicId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE knowledge_comments SET resolved_at = :resolved_at WHERE public_id = :public_id AND resolved_at IS NULL');
        $stmt->execute(['public_id' => $publicId, 'resolved_at' => gmdate('Y-m-d H:i:s')]);
        return $stmt->rowCount() > 0;
    }

    public function reopenComment(string $publicId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE knowledge_comments SET resolved_at = NULL WHERE public_id = :public_id AND resolved_at IS NOT NULL');
        $stmt->execute(['public_id' => $publicId]);
        return $stmt->rowCount() > 0;
    }

    public function isPageFavorited(string $pagePublicId, int $userId): bool
    {
        $page = $this->page($pagePublicId);
        if (!$page) return false;
        $stmt = $this->pdo->prepare('SELECT id FROM favorites WHERE entity_type = :entity_type AND entity_public_id = :entity_public_id AND user_id = :user_id LIMIT 1');
        $stmt->execute(['entity_type' => 'knowledge_page', 'entity_public_id' => $pagePublicId, 'user_id' => $userId]);
        return $stmt->fetchColumn() !== false;
    }

    public function favoritePage(string $pagePublicId, int $userId): ?string
    {
        $page = $this->page($pagePublicId);
        if (!$page) return null;
        $stmt = $this->pdo->prepare('SELECT public_id FROM favorites WHERE entity_type = :entity_type AND entity_public_id = :entity_public_id AND user_id = :user_id LIMIT 1');
        $stmt->execute(['entity_type' => 'knowledge_page', 'entity_public_id' => $pagePublicId, 'user_id' => $userId]);
        $existing = $stmt->fetchColumn();
        if ($existing !== false) {
            return (string)$existing;
        }
        $publicId = 'fav_' . strtoupper(bin2hex(random_bytes(10)));
        $stmt = $this->pdo->prepare('INSERT INTO favorites (public_id, user_id, entity_type, entity_public_id, created_at) VALUES (:public_id, :user_id, :entity_type, :entity_public_id, :created_at)');
        $stmt->execute(['public_id' => $publicId, 'user_id' => $userId, 'entity_type' => 'knowledge_page', 'entity_public_id' => $pagePublicId, 'created_at' => gmdate('Y-m-d H:i:s')]);
        return $publicId;
    }

    public function unfavoritePage(string $pagePublicId, int $userId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM favorites WHERE entity_type = :entity_type AND entity_public_id = :entity_public_id AND user_id = :user_id');
        $stmt->execute(['entity_type' => 'knowledge_page', 'entity_public_id' => $pagePublicId, 'user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public function favorites(int $userId, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare('SELECT p.id, p.public_id, p.title, p.status, p.page_type, p.updated_at, p.published_at, p.views_count, s.title AS space_title, s.public_id AS space_public_id
            FROM favorites f
            INNER JOIN knowledge_pages p ON p.public_id = f.entity_public_id AND p.deleted_at IS NULL
            LEFT JOIN knowledge_spaces s ON s.id = p.space_id
            WHERE f.entity_type = :entity_type AND f.user_id = :user_id
            ORDER BY f.created_at DESC
            LIMIT :limit OFFSET :offset');
        $stmt->execute(['entity_type' => 'knowledge_page', 'user_id' => $userId, 'limit' => $limit, 'offset' => $offset]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function suggest(string $query, int $limit = 10): array
    {
        $q = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';
        $stmt = $this->pdo->prepare('SELECT p.public_id, p.title, p.page_type, s.title AS space_title, s.public_id AS space_public_id
            FROM knowledge_pages p
            LEFT JOIN knowledge_spaces s ON s.id = p.space_id
            WHERE p.deleted_at IS NULL AND p.status = :status AND (p.title LIKE :q_title OR p.content_text LIKE :q_content)
            ORDER BY p.views_count DESC, p.updated_at DESC
            LIMIT :limit');
        $stmt->execute([
            'status' => 'published',
            'q_title' => $q,
            'q_content' => $q,
            'limit' => $limit,
        ]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function analytics(): array
    {
        $stmt = $this->pdo->query("SELECT
            (SELECT COUNT(*) FROM knowledge_pages WHERE deleted_at IS NULL) AS total_pages,
            (SELECT COUNT(*) FROM knowledge_pages WHERE deleted_at IS NULL AND status = 'published') AS published,
            (SELECT COUNT(*) FROM knowledge_pages WHERE deleted_at IS NULL AND status = 'draft') AS drafts,
            (SELECT COUNT(*) FROM knowledge_pages WHERE deleted_at IS NULL AND status = 'review') AS review_queue,
            (SELECT COUNT(*) FROM knowledge_pages WHERE deleted_at IS NULL AND status = 'archived') AS archived,
            (SELECT COUNT(*) FROM knowledge_spaces WHERE is_archived = 0) AS active_spaces,
            (SELECT COUNT(*) FROM knowledge_spaces WHERE is_archived = 1) AS archived_spaces,
            (SELECT COUNT(*) FROM knowledge_comments) AS total_comments,
            (SELECT COUNT(*) FROM knowledge_page_versions) AS total_versions,
            (SELECT COUNT(*) FROM knowledge_entity_links) AS total_links
        ");
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    }

    public function pageSubscriberIds(string $pagePublicId): array
    {
        $stmt = $this->pdo->prepare('SELECT user_id FROM subscriptions WHERE entity_type = :entity_type AND entity_public_id = :entity_public_id');
        $stmt->execute(['entity_type' => 'knowledge_page', 'entity_public_id' => $pagePublicId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function isPageSubscribed(string $pagePublicId, int $userId): bool
    {
        $page = $this->page($pagePublicId);
        if (!$page) return false;
        $stmt = $this->pdo->prepare('SELECT id FROM subscriptions WHERE entity_type = :entity_type AND entity_public_id = :entity_public_id AND user_id = :user_id LIMIT 1');
        $stmt->execute(['entity_type' => 'knowledge_page', 'entity_public_id' => $pagePublicId, 'user_id' => $userId]);
        return $stmt->fetchColumn() !== false;
    }

    public function subscribePage(string $pagePublicId, int $userId): ?string
    {
        $page = $this->page($pagePublicId);
        if (!$page) return null;
        $stmt = $this->pdo->prepare('SELECT public_id FROM subscriptions WHERE entity_type = :entity_type AND entity_public_id = :entity_public_id AND user_id = :user_id LIMIT 1');
        $stmt->execute(['entity_type' => 'knowledge_page', 'entity_public_id' => $pagePublicId, 'user_id' => $userId]);
        $existing = $stmt->fetchColumn();
        if ($existing !== false) {
            return (string)$existing;
        }
        $publicId = 'sub_' . strtoupper(bin2hex(random_bytes(10)));
        $stmt = $this->pdo->prepare('INSERT INTO subscriptions (public_id, user_id, entity_type, entity_public_id, created_at) VALUES (:public_id, :user_id, :entity_type, :entity_public_id, :created_at)');
        $stmt->execute(['public_id' => $publicId, 'user_id' => $userId, 'entity_type' => 'knowledge_page', 'entity_public_id' => $pagePublicId, 'created_at' => gmdate('Y-m-d H:i:s')]);
        return $publicId;
    }

    public function unsubscribePage(string $pagePublicId, int $userId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM subscriptions WHERE entity_type = :entity_type AND entity_public_id = :entity_public_id AND user_id = :user_id');
        $stmt->execute(['entity_type' => 'knowledge_page', 'entity_public_id' => $pagePublicId, 'user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    private function addVersion(string $pagePublicId, ?int $actorId, string $summary): void
    {
        $page = $this->page($pagePublicId);
        if (!$page) {
            return;
        }
        $stmt = $this->pdo->prepare('SELECT MAX(version_number) FROM knowledge_page_versions WHERE page_id = :page_id');
        $stmt->execute(['page_id' => (int)$page['id']]);
        $next = ((int)$stmt->fetchColumn()) + 1;
        $insert = $this->pdo->prepare('INSERT INTO knowledge_page_versions (public_id, page_id, version_number, title, content_html, content_text, content_json, change_summary, created_by_user_id, created_at) VALUES (:public_id, :page_id, :version_number, :title, :content_html, :content_text, :content_json, :change_summary, :created_by_user_id, :created_at)');
        $insert->execute([
            'public_id' => $this->publicId('kbv'),
            'page_id' => (int)$page['id'],
            'version_number' => $next,
            'title' => $page['title'],
            'content_html' => $page['content_html'],
            'content_text' => $page['content_text'],
            'content_json' => $page['content_json'],
            'change_summary' => $summary,
            'created_by_user_id' => $actorId,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    private function pageWhere(array $filters): array
    {
        $where = ['p.deleted_at IS NULL', 's.is_archived = 0'];
        $params = [];
        if (!empty($filters['status'])) {
            $where[] = 'p.status = :status';
            $params['status'] = (string)$filters['status'];
        }
        if (!empty($filters['space_public_id'])) {
            $where[] = 's.public_id = :space_public_id';
            $params['space_public_id'] = (string)$filters['space_public_id'];
        }
        if (!empty($filters['page_type'])) {
            $where[] = 'p.page_type = :page_type';
            $params['page_type'] = (string)$filters['page_type'];
        }
        if (!empty($filters['q'])) {
            $like = '%' . (string)$filters['q'] . '%';
            $where[] = '(p.title LIKE :q_title OR p.content_text LIKE :q_content OR s.title LIKE :q_space)';
            $params['q_title'] = $like;
            $params['q_content'] = $like;
            $params['q_space'] = $like;
        }
        if (!empty($filters['tag_public_id'])) {
            $where[] = 'EXISTS(SELECT 1 FROM entity_tags et2 INNER JOIN tags tg2 ON tg2.id = et2.tag_id WHERE et2.entity_type = :tag_entity_type AND et2.entity_public_id = p.public_id AND tg2.public_id = :tag_public_id)';
            $params['tag_entity_type'] = 'knowledge_page';
            $params['tag_public_id'] = (string)$filters['tag_public_id'];
        }
        return [implode(' AND ', $where), $params];
    }

    private function buildTree(array $rows, ?int $parentId, int $level, int $maxDepth): array
    {
        if ($level >= $maxDepth) {
            return [];
        }
        $items = [];
        foreach ($rows as $row) {
            $rowParent = $row['parent_id'] === null ? null : (int)$row['parent_id'];
            if ($rowParent !== $parentId) {
                continue;
            }
            $row['children'] = $this->buildTree($rows, (int)$row['id'], $level + 1, $maxDepth);
            $items[] = $row;
        }
        return $items;
    }

    private function resolveSpace(string $publicId): ?array
    {
        if ($publicId === '') {
            return null;
        }
        return $this->space($publicId);
    }

    private function resolvePage(string $publicId): ?array
    {
        return $publicId !== '' ? $this->page($publicId) : null;
    }

    private function countPages(int $spaceId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM knowledge_pages WHERE space_id = :space_id AND deleted_at IS NULL');
        $stmt->execute(['space_id' => $spaceId]);
        return (int)$stmt->fetchColumn();
    }

    private function totals(): array
    {
        return [
            'spaces' => (int)$this->pdo->query('SELECT COUNT(*) FROM knowledge_spaces WHERE is_archived = 0')->fetchColumn(),
            'pages' => (int)$this->pdo->query('SELECT COUNT(*) FROM knowledge_pages WHERE deleted_at IS NULL')->fetchColumn(),
            'published' => (int)$this->pdo->query("SELECT COUNT(*) FROM knowledge_pages WHERE deleted_at IS NULL AND status = 'published'")->fetchColumn(),
            'drafts' => (int)$this->pdo->query("SELECT COUNT(*) FROM knowledge_pages WHERE deleted_at IS NULL AND status = 'draft'")->fetchColumn(),
        ];
    }

    private function refreshPagePath(int $pageId): void
    {
        $stmt = $this->pdo->prepare('SELECT id, parent_id, slug FROM knowledge_pages WHERE id = :id');
        $stmt->execute(['id' => $pageId]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($page)) {
            return;
        }
        $slugs = [(string)$page['slug']];
        $parentId = $page['parent_id'] !== null ? (int)$page['parent_id'] : null;
        while ($parentId) {
            $p = $this->pdo->prepare('SELECT id, parent_id, slug FROM knowledge_pages WHERE id = :id');
            $p->execute(['id' => $parentId]);
            $parent = $p->fetch(PDO::FETCH_ASSOC);
            if (!is_array($parent)) {
                break;
            }
            array_unshift($slugs, (string)$parent['slug']);
            $parentId = $parent['parent_id'] !== null ? (int)$parent['parent_id'] : null;
        }
        $this->pdo->prepare('UPDATE knowledge_pages SET path = :path WHERE id = :id')->execute(['path' => '/' . implode('/', $slugs), 'id' => $pageId]);
    }

    private function refreshChildrenCount(?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }
        $stmt = $this->pdo->prepare('UPDATE knowledge_pages SET children_count = (SELECT COUNT(*) FROM knowledge_pages c WHERE c.parent_id = :parent_id_count AND c.deleted_at IS NULL) WHERE id = :parent_id_where');
        $stmt->execute([
            'parent_id_count' => $parentId,
            'parent_id_where' => $parentId,
        ]);
    }

    private function nextPageSort(int $spaceId, ?int $parentId): int
    {
        if ($parentId === null) {
            $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM knowledge_pages WHERE space_id = :space_id AND parent_id IS NULL');
            $stmt->execute(['space_id' => $spaceId]);
        } else {
            $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM knowledge_pages WHERE space_id = :space_id AND parent_id = :parent_id');
            $stmt->execute(['space_id' => $spaceId, 'parent_id' => $parentId]);
        }
        return (int)$stmt->fetchColumn();
    }

    private function uniqueSlug(string $table, string $base, ?int $ignoreId): string
    {
        $slug = $base;
        $i = 2;
        while (true) {
            $sql = "SELECT id FROM {$table} WHERE slug = :slug" . ($ignoreId ? ' AND id <> :id' : '') . ' LIMIT 1';
            $stmt = $this->pdo->prepare($sql);
            $params = ['slug' => $slug];
            if ($ignoreId) {
                $params['id'] = $ignoreId;
            }
            $stmt->execute($params);
            if ($stmt->fetchColumn() === false) {
                return $slug;
            }
            $slug = $base . '-' . $i++;
        }
    }

    private function uniquePageSlug(int $spaceId, string $base, ?int $ignoreId): string
    {
        $slug = $base;
        $i = 2;
        while (true) {
            $sql = 'SELECT id FROM knowledge_pages WHERE space_id = :space_id AND slug = :slug' . ($ignoreId ? ' AND id <> :id' : '') . ' LIMIT 1';
            $stmt = $this->pdo->prepare($sql);
            $params = ['space_id' => $spaceId, 'slug' => $slug];
            if ($ignoreId) {
                $params['id'] = $ignoreId;
            }
            $stmt->execute($params);
            if ($stmt->fetchColumn() === false) {
                return $slug;
            }
            $slug = $base . '-' . $i++;
        }
    }

    private function slug(string $value, string $fallback): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? '';
        $value = trim($value, '-');
        return $value !== '' ? mb_substr($value, 0, 120) : $fallback . '-' . strtolower(bin2hex(random_bytes(3)));
    }

    private function publicId(string $prefix): string
    {
        return $prefix . '_' . bin2hex(random_bytes(10));
    }

    private function sanitizeHtml(string $html): string
    {
        $html = preg_replace('#<(script|style|iframe|object|embed|form|input|button)[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = preg_replace('#</?(script|style|iframe|object|embed|form|input|button)[^>]*>#i', '', $html) ?? '';
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace('/(href|src)\s*=\s*("|\')\s*javascript:[^"\']*("|\')/i', '$1="#"', $html) ?? '';
        return trim($html);
    }

    private function contentText(string $html): string
    {
        $withSpaces = preg_replace('#</(p|div|section|article|h[1-6]|li|ul|ol|br)>#i', ' ', $html) ?? $html;
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($withSpaces), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private function excerpt(string $html): string
    {
        return mb_substr($this->contentText($html), 0, 260);
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string)($value ?? ''));
        return $text !== '' ? $text : null;
    }

    private function nullableShort(mixed $value, int $limit): ?string
    {
        $text = $this->nullableText($value);
        return $text !== null ? mb_substr($text, 0, $limit) : null;
    }

    private function choice(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }
}
