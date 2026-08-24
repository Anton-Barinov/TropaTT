<?php
declare(strict_types=1);

namespace Api\Model\Knowledge;

use PDO;
use Api\System\Library\Support\LikeEscaper;

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

    private const ACCESS_RANK = [
        'view' => 10,
        'comment' => 20,
        'edit' => 30,
        'manage' => 40,
        'owner' => 50,
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function overview(array $filters = [], ?array $actor = null): array
    {
        return [
            'spaces' => $this->spaces(['include_archived' => false], $actor),
            'recent' => $this->pages(['limit' => 8, 'sort' => 'updated_at', 'order' => 'DESC'], $actor),
            'popular' => $this->popular(8, $actor),
            'drafts' => $this->pages(['status' => 'draft', 'limit' => 8, 'sort' => 'updated_at', 'order' => 'DESC'], $actor),
            'review_queue' => $this->pages(['status' => 'review', 'limit' => 8, 'sort' => 'updated_at', 'order' => 'DESC'], $actor),
            'outdated' => $this->outdated(8, $actor),
            'totals' => $this->totals($actor),
        ];
    }

    public function spaces(array $filters = [], ?array $actor = null): array
    {
        $includeArchived = !empty($filters['include_archived']);
        $where = $includeArchived ? ['1=1'] : ['s.is_archived = 0'];
        $params = [];
        [$aclSql, $aclParams] = $this->spaceAccessSql('s', $actor, (string)($filters['min_access'] ?? 'view'));
        $where[] = $aclSql;
        $params += $aclParams;
        $stmt = $this->pdo->prepare(
            'SELECT s.*, '
            . '(SELECT COUNT(*) FROM knowledge_pages p WHERE p.space_id = s.id AND p.deleted_at IS NULL) AS pages_count '
            . 'FROM knowledge_spaces s WHERE ' . implode(' AND ', $where)
            . ' ORDER BY s.sort_order ASC, s.title ASC'
        );
        $stmt->execute($params);
        $spaces = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($spaces as &$space) {
            $space['pages_count'] = (int)($space['pages_count'] ?? 0);
            $space['is_archived'] = (int)($space['is_archived'] ?? 0);
            $space['parent_id'] = isset($space['parent_id']) ? (int)$space['parent_id'] : null;
        }
        unset($space);

        return $spaces;
    }

    public function spacesTree(array $filters = [], ?array $actor = null): array
    {
        $flat = $this->spaces($filters, $actor);
        $byId = [];
        foreach ($flat as $s) {
            $s['children'] = [];
            $byId[(int)$s['id']] = $s;
        }
        $roots = [];
        foreach ($byId as $id => &$space) {
            $pid = $space['parent_id'];
            if ($pid !== null && isset($byId[$pid])) {
                $byId[$pid]['children'][] = &$space;
            } else {
                $roots[] = &$space;
            }
        }
        unset($space);
        return $roots;
    }

    public function createSpace(array $payload, ?int $actorId): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $publicId = $this->publicId('kbs');
        $title = trim((string)($payload['title'] ?? ''));
        $slug = $this->uniqueSlug('knowledge_spaces', $this->slug((string)($payload['slug'] ?? $title), 'space'), null);
        $parentId = null;
        if (!empty($payload['parent_public_id'])) {
            $parent = $this->space((string)$payload['parent_public_id']);
            $parentId = $parent ? (int)$parent['id'] : null;
        } elseif (!empty($payload['parent_id'])) {
            $parentId = (int)$payload['parent_id'];
        }
        $hasParentCol = $this->columnExists('knowledge_spaces', 'parent_id');
        if ($hasParentCol) {
            $stmt = $this->pdo->prepare('INSERT INTO knowledge_spaces (public_id, title, slug, description, icon, color, owner_user_id, visibility, default_access_level, parent_id, sort_order, created_at, updated_at) VALUES (:public_id, :title, :slug, :description, :icon, :color, :owner_user_id, :visibility, :default_access_level, :parent_id, :sort_order, :created_at, :updated_at)');
            $params = [
                'public_id' => $publicId,
                'title' => $title,
                'slug' => $slug,
                'description' => $this->nullableText($payload['description'] ?? null),
                'icon' => $this->nullableShort($payload['icon'] ?? 'book-open', 64),
                'color' => $this->nullableShort($payload['color'] ?? '#0f8f72', 32),
                'owner_user_id' => $actorId,
                'visibility' => $this->choice((string)($payload['visibility'] ?? 'public'), ['public', 'restricted', 'private'], 'public'),
                'default_access_level' => $this->choice((string)($payload['default_access_level'] ?? 'view'), ['view', 'comment', 'edit'], 'view'),
                'parent_id' => $parentId,
                'sort_order' => (int)($payload['sort_order'] ?? 100),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO knowledge_spaces (public_id, title, slug, description, icon, color, owner_user_id, visibility, default_access_level, sort_order, created_at, updated_at) VALUES (:public_id, :title, :slug, :description, :icon, :color, :owner_user_id, :visibility, :default_access_level, :sort_order, :created_at, :updated_at)');
            $params = [
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
            ];
        }
        $stmt->execute($params);
        return $this->space($publicId) ?? [];
    }

    public function space(string $publicId, ?array $actor = null, string $minAccess = 'view'): ?array
    {
        [$aclSql, $aclParams] = $this->spaceAccessSql('s', $actor, $minAccess);
        $stmt = $this->pdo->prepare(
            "SELECT s.*, (SELECT COUNT(*) FROM knowledge_pages p WHERE p.space_id = s.id AND p.deleted_at IS NULL) AS pages_count "
            . "FROM knowledge_spaces s WHERE s.public_id = :public_id AND {$aclSql} LIMIT 1"
        );
        $stmt->execute(['public_id' => $publicId] + $aclParams);
        $space = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($space)) {
            return null;
        }
        $space['pages_count'] = (int)($space['pages_count'] ?? 0);
        return $space;
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
            $stmt->execute([$table, $column]);
            return $stmt->fetch() !== false;
        } catch (\Throwable $e) {
            error_log('[KnowledgeRepository::columnExists] DB prepare: ' . $e->getMessage());
            return false;
        }
    }

    public function updateSpace(string $publicId, array $payload, ?array $actor = null): array|string|null
    {
        $current = $this->space($publicId, $actor, 'manage');
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
            SELECT p.id AS permission_key, p.subject_type, p.subject_id, p.access_level, p.created_at,
                   u.public_id AS user_public_id, COALESCE(u.full_name, u.login, u.public_id) AS user_name,
                   r.public_id AS role_public_id, r.title AS role_title,
                   t.public_id AS team_public_id, t.title AS team_title,
                   d.public_id AS department_public_id, d.title AS department_title
            FROM knowledge_space_permissions p
            LEFT JOIN users u ON u.id = p.subject_id AND p.subject_type = \'user\'
            LEFT JOIN roles r ON r.id = p.subject_id AND p.subject_type = \'role\'
            LEFT JOIN teams t ON t.id = p.subject_id AND p.subject_type = \'team\'
            LEFT JOIN departments d ON d.id = p.subject_id AND p.subject_type = \'department\'
            WHERE p.space_id = :space_id
            ORDER BY p.created_at DESC
        ');
        $stmt->execute(['space_id' => (int)$space['id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function addSpacePermission(string $publicId, string $subjectType, int $subjectId, string $accessLevel, ?int $actorId, string $subjectPublicId = ''): ?array
    {
        $space = $this->space($publicId);
        if (!$space) {
            return null;
        }
        $allowedTypes = ['user', 'role', 'team', 'department'];
        if (!in_array($subjectType, $allowedTypes, true)) {
            return null;
        }
        $resolvedSubjectId = $this->resolvePermissionSubjectId($subjectType, $subjectId, $subjectPublicId);
        if ($resolvedSubjectId <= 0) {
            return null;
        }
        $allowedLevels = ['view', 'comment', 'edit', 'manage', 'owner'];
        $accessLevel = $this->choice($accessLevel, $allowedLevels, 'view');

        $now = gmdate('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('INSERT INTO knowledge_space_permissions (space_id, subject_type, subject_id, access_level, created_by_user_id, created_at) VALUES (:space_id, :subject_type, :subject_id, :access_level, :created_by_user_id, :created_at)');
        $stmt->execute([
            'space_id' => (int)$space['id'],
            'subject_type' => $subjectType,
            'subject_id' => $resolvedSubjectId,
            'access_level' => $accessLevel,
            'created_by_user_id' => $actorId,
            'created_at' => $now,
        ]);
        $id = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare('UPDATE knowledge_spaces SET permissions_version = permissions_version + 1, updated_at = :updated_at WHERE id = :id')->execute(['updated_at' => $now, 'id' => (int)$space['id']]);

        $stmt = $this->pdo->prepare('
            SELECT p.id AS permission_key, p.subject_type, p.subject_id, p.access_level, p.created_at,
                   u.public_id AS user_public_id, COALESCE(u.full_name, u.login, u.public_id) AS user_name,
                   r.public_id AS role_public_id, r.title AS role_title,
                   t.public_id AS team_public_id, t.title AS team_title,
                   d.public_id AS department_public_id, d.title AS department_title
            FROM knowledge_space_permissions p
            LEFT JOIN users u ON u.id = p.subject_id AND p.subject_type = \'user\'
            LEFT JOIN roles r ON r.id = p.subject_id AND p.subject_type = \'role\'
            LEFT JOIN teams t ON t.id = p.subject_id AND p.subject_type = \'team\'
            LEFT JOIN departments d ON d.id = p.subject_id AND p.subject_type = \'department\'
            WHERE p.id = :id
        ');
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getSpacePublicIdByPermissionId(int $permissionId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT s.public_id FROM knowledge_space_permissions p JOIN knowledge_spaces s ON s.id = p.space_id WHERE p.id = :id LIMIT 1');
        $stmt->execute(['id' => $permissionId]);
        return ($val = $stmt->fetchColumn()) !== false ? (string)$val : null;
    }

    public function getPagePublicIdByPermissionId(int $permissionId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT p.public_id FROM knowledge_page_permissions perm JOIN knowledge_pages p ON p.id = perm.page_id WHERE perm.id = :id LIMIT 1');
        $stmt->execute(['id' => $permissionId]);
        return ($val = $stmt->fetchColumn()) !== false ? (string)$val : null;
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

    public function pagePermissions(string $publicId): array
    {
        $page = $this->pageIdentity($publicId);
        if (!$page) {
            return [];
        }
        $stmt = $this->pdo->prepare('
            SELECT p.id AS permission_key, p.subject_type, p.subject_id, p.access_level, p.created_at,
                   u.public_id AS user_public_id, COALESCE(u.full_name, u.login, u.public_id) AS user_name,
                   r.public_id AS role_public_id, r.title AS role_title,
                   t.public_id AS team_public_id, t.title AS team_title,
                   d.public_id AS department_public_id, d.title AS department_title
            FROM knowledge_page_permissions p
            LEFT JOIN users u ON u.id = p.subject_id AND p.subject_type = \'user\'
            LEFT JOIN roles r ON r.id = p.subject_id AND p.subject_type = \'role\'
            LEFT JOIN teams t ON t.id = p.subject_id AND p.subject_type = \'team\'
            LEFT JOIN departments d ON d.id = p.subject_id AND p.subject_type = \'department\'
            WHERE p.page_id = :page_id
            ORDER BY p.created_at DESC
        ');
        $stmt->execute(['page_id' => (int)$page['id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function addPagePermission(string $publicId, string $subjectType, int $subjectId, string $accessLevel, ?int $actorId, string $subjectPublicId = ''): ?array
    {
        $page = $this->pageIdentity($publicId);
        if (!$page) {
            return null;
        }
        $allowedTypes = ['user', 'role', 'team', 'department'];
        if (!in_array($subjectType, $allowedTypes, true)) {
            return null;
        }
        $resolvedSubjectId = $this->resolvePermissionSubjectId($subjectType, $subjectId, $subjectPublicId);
        if ($resolvedSubjectId <= 0) {
            return null;
        }
        $allowedLevels = ['view', 'comment', 'edit', 'manage', 'owner'];
        $accessLevel = $this->choice($accessLevel, $allowedLevels, 'view');

        $now = gmdate('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('INSERT INTO knowledge_page_permissions (page_id, subject_type, subject_id, access_level, created_by_user_id, created_at) VALUES (:page_id, :subject_type, :subject_id, :access_level, :created_by_user_id, :created_at)');
        $stmt->execute([
            'page_id' => (int)$page['id'],
            'subject_type' => $subjectType,
            'subject_id' => $resolvedSubjectId,
            'access_level' => $accessLevel,
            'created_by_user_id' => $actorId,
            'created_at' => $now,
        ]);
        $id = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare('UPDATE knowledge_pages SET updated_at = :updated_at WHERE id = :id')->execute(['updated_at' => $now, 'id' => (int)$page['id']]);

        $stmt = $this->pdo->prepare('
            SELECT p.id AS permission_key, p.subject_type, p.subject_id, p.access_level, p.created_at,
                   u.public_id AS user_public_id, COALESCE(u.full_name, u.login, u.public_id) AS user_name,
                   r.public_id AS role_public_id, r.title AS role_title,
                   t.public_id AS team_public_id, t.title AS team_title,
                   d.public_id AS department_public_id, d.title AS department_title
            FROM knowledge_page_permissions p
            LEFT JOIN users u ON u.id = p.subject_id AND p.subject_type = \'user\'
            LEFT JOIN roles r ON r.id = p.subject_id AND p.subject_type = \'role\'
            LEFT JOIN teams t ON t.id = p.subject_id AND p.subject_type = \'team\'
            LEFT JOIN departments d ON d.id = p.subject_id AND p.subject_type = \'department\'
            WHERE p.id = :id
        ');
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function removePagePermission(int $permissionId): bool
    {
        $lookup = $this->pdo->prepare('SELECT page_id FROM knowledge_page_permissions WHERE id = :id LIMIT 1');
        $lookup->execute(['id' => $permissionId]);
        $pageId = $lookup->fetchColumn();
        if ($pageId === false) {
            return false;
        }

        $stmt = $this->pdo->prepare('DELETE FROM knowledge_page_permissions WHERE id = :id');
        $stmt->execute(['id' => $permissionId]);
        if ($stmt->rowCount() > 0) {
            $this->pdo->prepare('UPDATE knowledge_pages SET updated_at = :updated_at WHERE id = :page_id')->execute([
                'updated_at' => gmdate('Y-m-d H:i:s'),
                'page_id' => (int)$pageId,
            ]);
            return true;
        }
        return false;
    }

    public function archiveSpace(string $publicId, bool $archived, ?array $actor = null): bool
    {
        $space = $this->space($publicId, $actor, 'manage');
        if (!$space) {
            return false;
        }
        $stmt = $this->pdo->prepare('UPDATE knowledge_spaces SET is_archived = :archived, row_version = row_version + 1, updated_at = :updated_at WHERE public_id = :public_id');
        $stmt->execute(['archived' => $archived ? 1 : 0, 'updated_at' => gmdate('Y-m-d H:i:s'), 'public_id' => $publicId]);
        return $stmt->rowCount() > 0;
    }

    public function tree(string $spacePublicId, int $depth = 10, ?array $actor = null): array
    {
        static $cache = [];
        $actorId = $this->actorUserId($actor);
        $cacheKey = $spacePublicId . '|' . $actorId;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }
        $space = $this->space($spacePublicId, $actor);
        if (!$space) {
            return [];
        }
        [$aclSql, $aclParams] = $this->pageAccessSql('p', 's', $actor, 'view');
        $stmt = $this->pdo->prepare("SELECT p.*, s.public_id AS space_public_id, s.title AS space_title FROM knowledge_pages p JOIN knowledge_spaces s ON s.id = p.space_id WHERE p.space_id = :space_id AND p.deleted_at IS NULL AND {$aclSql} ORDER BY COALESCE(p.parent_id, 0), p.sort_order ASC, p.title ASC");
        $stmt->execute(['space_id' => (int)$space['id']] + $aclParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $result = $this->buildTree($rows, null, 0, max(1, $depth));
        $cache[$cacheKey] = $result;
        return $result;
    }

    public function pages(array $filters = [], ?array $actor = null): array
    {
        [$where, $params] = $this->pageWhere($filters, $actor);
        $limit = min(100, max(1, (int)($filters['limit'] ?? 30)));
        $page = max(1, (int)($filters['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        $sort = in_array((string)($filters['sort'] ?? ''), ['title', 'created_at', 'updated_at', 'published_at', 'views_count'], true) ? (string)$filters['sort'] : 'updated_at';
        $order = strtoupper((string)($filters['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $stmt = $this->pdo->prepare("SELECT p.*, s.public_id AS space_public_id, s.title AS space_title FROM knowledge_pages p JOIN knowledge_spaces s ON s.id = p.space_id WHERE {$where} ORDER BY p.{$sort} {$order}, p.public_id {$order} LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createPage(array $payload, ?int $actorId, ?array $actor = null): array
    {
        $space = $this->resolveSpace((string)($payload['space_public_id'] ?? $payload['space_id'] ?? ''), $actor, 'edit');
        if (!$space) {
            $spaces = $this->spaces(['min_access' => 'edit'], $actor);
            $space = $spaces[0] ?? null;
        }
        if (!$space) {
            throw new \RuntimeException('Knowledge space with edit access is required');
        }
        $parent = $this->resolvePage((string)($payload['parent_public_id'] ?? ''), $actor, 'edit');
        $title = trim((string)($payload['title'] ?? ''));
        $html = $this->sanitizeHtml((string)($payload['content_html'] ?? $payload['content'] ?? ''));
        $now = gmdate('Y-m-d H:i:s');
        $publicId = $this->publicId('kbp');
        $stmt = $this->pdo->prepare('INSERT INTO knowledge_pages (public_id, space_id, parent_id, title, slug, page_type, status, content_html, content_text, content_json, excerpt, owner_user_id, last_editor_user_id, sort_order, path, depth, client_visible, created_at, updated_at) VALUES (:public_id, :space_id, :parent_id, :title, :slug, :page_type, :status, :content_html, :content_text, :content_json, :excerpt, :owner_user_id, :last_editor_user_id, :sort_order, :path, :depth, :client_visible, :created_at, :updated_at)');
        $stmt->execute([
            'public_id' => $publicId,
            'space_id' => (int)$space['id'],
            'parent_id' => $parent ? (int)$parent['id'] : null,
            'title' => $title,
            'slug' => $this->uniquePageSlug((int)$space['id'], $this->slug((string)($payload['slug'] ?? $title), 'page'), null),
            'page_type' => $this->choice((string)($payload['page_type'] ?? 'article'), self::PAGE_TYPES, 'article'),
            'status' => $this->choice((string)($payload['status'] ?? 'draft'), ['draft', 'review', 'published', 'archived', 'needs_update'], 'draft'),
            'content_html' => $html,
            'content_text' => $this->contentText($html),
            'content_json' => isset($payload['content_json']) ? json_encode($payload['content_json'], JSON_UNESCAPED_UNICODE) : null,
            'excerpt' => $this->excerpt($html),
            'owner_user_id' => $actorId,
            'last_editor_user_id' => $actorId,
            'sort_order' => (int)($payload['sort_order'] ?? $this->nextPageSort((int)$space['id'], $parent ? (int)$parent['id'] : null)),
            'path' => '',
            'depth' => $parent ? ((int)($parent['depth'] ?? 0) + 1) : 0,
            'client_visible' => (int)($payload['client_visible'] ?? 0),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $page = $this->page($publicId) ?? [];
        $this->refreshPagePath((int)$page['id']);
        $this->refreshChildrenCount($parent ? (int)$parent['id'] : null);
        if (($page['status'] ?? '') === 'published') {
            $this->legacyAddVersion($publicId, $actorId, 'Initial publish');
        }
        return $this->page($publicId) ?? $page;
    }

    public function page(string $publicId, ?array $actor = null, string $minAccess = 'view'): ?array
    {
        [$aclSql, $aclParams] = $this->pageAccessSql('p', 's', $actor, $minAccess);
        $stmt = $this->pdo->prepare("SELECT p.*, s.public_id AS space_public_id, s.title AS space_title, u.full_name AS author_name FROM knowledge_pages p JOIN knowledge_spaces s ON s.id = p.space_id LEFT JOIN users u ON u.id = p.owner_user_id WHERE p.public_id = :public_id AND p.deleted_at IS NULL AND {$aclSql} LIMIT 1");
        $stmt->execute(['public_id' => $publicId] + $aclParams);
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

    public function updatePage(string $publicId, array $payload, ?int $actorId, ?array $actor = null): array|string|null
    {
        $current = $this->page($publicId, $actor, 'edit');
        if (!$current) {
            return null;
        }
        if (isset($payload['row_version']) && (int)$payload['row_version'] !== (int)($current['row_version'] ?? 1)) {
            return 'ROW_VERSION_CONFLICT';
        }
        $html = array_key_exists('content_html', $payload) || array_key_exists('content', $payload)
            ? $this->sanitizeHtml((string)($payload['content_html'] ?? $payload['content'] ?? ''))
            : (string)($current['content_html'] ?? '');
        $space = isset($payload['space_public_id']) ? $this->resolveSpace((string)$payload['space_public_id'], $actor, 'edit') : null;
        $parent = array_key_exists('parent_public_id', $payload) ? $this->resolvePage((string)$payload['parent_public_id'], $actor, 'edit') : false;
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
            'client_visible' => (int)(array_key_exists('client_visible', $payload) ? (int)$payload['client_visible'] : (int)($current['client_visible'] ?? 0)),
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'public_id' => $publicId,
        ];
        $this->pdo->prepare('UPDATE knowledge_pages SET title = :title, space_id = :space_id, parent_id = :parent_id, page_type = :page_type, status = :status, content_html = :content_html, content_text = :content_text, content_json = :content_json, excerpt = :excerpt, last_editor_user_id = :last_editor_user_id, review_due_at = :review_due_at, sort_order = :sort_order, depth = :depth, client_visible = :client_visible, row_version = row_version + 1, updated_at = :updated_at WHERE public_id = :public_id')->execute($params);
        $page = $this->page($publicId);
        if ($page) {
            $this->legacyAddVersion($publicId, $actorId, 'Updated page');
        }
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
        $defaultReviewDue = gmdate('Y-m-d H:i:s', time() + 90 * 86400); // 90 days from now
        $stmt = $this->pdo->prepare("UPDATE knowledge_pages SET status = 'published', review_status = 'approved', published_by_user_id = :actor, published_at = :published_at, reviewed_at = :reviewed_at, review_due_at = COALESCE(review_due_at, :default_review_due), row_version = row_version + 1, updated_at = :updated_at WHERE public_id = :public_id");
        $stmt->execute([
            'actor' => $actorId,
            'published_at' => $now,
            'reviewed_at' => $now,
            'default_review_due' => $defaultReviewDue,
            'updated_at' => $now,
            'public_id' => $publicId,
        ]);
        $this->legacyAddVersion($publicId, $actorId, $summary !== '' ? $summary : 'Published');
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

    public function duplicate(string $publicId, ?int $actorId, ?array $actor = null): ?array
    {
        $page = $this->page($publicId, $actor);
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
        ], $actorId, $actor);
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
        $stmt = $this->pdo->prepare('SELECT id, public_id, page_id, user_id, title, content_html, content_text, content_json, base_row_version, autosaved_at, created_at, updated_at FROM knowledge_drafts WHERE page_id = :page_id AND user_id = :user_id LIMIT 1');
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
        $stmt = $this->pdo->prepare('SELECT id, public_id, page_public_id, version_number, title, change_type, change_note, created_by_display_name, content_hash, created_at FROM knowledge_page_versions WHERE page_id = :page_id AND deleted_at IS NULL ORDER BY version_number DESC');
        $stmt->execute(['page_id' => (int)$page['id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // Restore is now handled by KnowledgePageVersionController and service
    public function legacyRestoreVersion(string $pagePublicId, int $versionNumber, ?int $actorId, ?array $actor = null): ?array
    {
        $page = $this->page($pagePublicId, $actor, 'edit');
        if (!$page) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT id, public_id, page_id, page_public_id, version_number, title, content, content_text, change_type, change_note, created_by_user_id, created_at FROM knowledge_page_versions WHERE page_id = :page_id AND version_number = :version_number AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['page_id' => (int)$page['id'], 'version_number' => $versionNumber]);
        $version = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($version)) {
            return null;
        }
        $this->updatePage($pagePublicId, [
            'title' => $version['title'],
            'content_html' => $version['content'] ?? '',
        ], $actorId, $actor);
        $this->legacyAddVersion($pagePublicId, $actorId, 'Restored version ' . $versionNumber);
        return $this->page($pagePublicId);
    }

    // Diff is now handled by KnowledgePageVersionController and service
    public function legacyDiff(string $pagePublicId, int $from, int $to): array
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

    public function search(string $query, array $filters = [], ?array $actor = null): array
    {
        $query = trim($query);
        if ($query === '') {
            return $this->pages($filters + ['limit' => 20], $actor);
        }
        $filters['q'] = $query;
        return $this->pages($filters + ['limit' => 30], $actor);
    }

    public function popular(int $limit = 10, ?array $actor = null): array
    {
        return $this->pages(['limit' => $limit, 'sort' => 'views_count', 'order' => 'DESC', 'status' => 'published'], $actor);
    }

    public function outdated(int $limit = 10, ?array $actor = null): array
    {
        $limit = max(1, min(100, $limit));
        [$aclSql, $aclParams] = $this->pageAccessSql('p', 's', $actor, 'view');
        $stmt = $this->pdo->prepare("SELECT p.*, s.public_id AS space_public_id, s.title AS space_title FROM knowledge_pages p JOIN knowledge_spaces s ON s.id = p.space_id WHERE p.deleted_at IS NULL AND p.status = 'published' AND p.review_due_at IS NOT NULL AND p.review_due_at < :now AND {$aclSql} ORDER BY p.review_due_at ASC LIMIT {$limit}");
        $stmt->execute(['now' => gmdate('Y-m-d H:i:s')] + $aclParams);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function templates(array $filters = [], ?array $actor = null): array
    {
        $where = 'is_active = 1';
        $params = [];
        if (!empty($filters['page_type'])) {
            $where .= ' AND page_type = :page_type';
            $params['page_type'] = (string)$filters['page_type'];
        }
        // ACL: non-root users see only global templates (space_id IS NULL)
        // and templates from spaces they have access to.
        if ($actor !== null && !$this->actorBypassesKnowledgeAcl($actor)) {
            [$spaceAclSql, $spaceAclParams] = $this->spaceAccessSql('ks', $actor, 'view');
            $params = array_merge($params, $spaceAclParams);
            $where .= " AND (t.space_id IS NULL OR EXISTS (
                SELECT 1 FROM knowledge_spaces ks
                WHERE ks.id = t.space_id AND ({$spaceAclSql})
            ))";
        }
        $stmt = $this->pdo->prepare("SELECT t.id, t.public_id, t.title, t.page_type, t.description, t.content_html, t.content_json, t.is_system, t.is_active, t.space_id, t.created_by_user_id, t.created_at, t.updated_at FROM knowledge_templates t WHERE {$where} ORDER BY t.is_system DESC, t.title ASC");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createTemplate(array $payload, ?int $actorId): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $publicId = $this->publicId('kbt');
        $spaceId = !empty($payload['space_id']) ? (int)$payload['space_id'] : null;
        $stmt = $this->pdo->prepare('INSERT INTO knowledge_templates (public_id, title, page_type, description, content_html, content_json, is_system, is_active, space_id, created_by_user_id, created_at, updated_at) VALUES (:public_id, :title, :page_type, :description, :content_html, :content_json, 0, 1, :space_id, :created_by_user_id, :created_at, :updated_at)');
        $stmt->execute([
            'public_id' => $publicId,
            'title' => trim((string)($payload['title'] ?? '')),
            'page_type' => $this->choice((string)($payload['page_type'] ?? 'article'), self::PAGE_TYPES, 'article'),
            'description' => $this->nullableText($payload['description'] ?? null),
            'content_html' => $this->sanitizeHtml((string)($payload['content_html'] ?? '')),
            'content_json' => isset($payload['content_json']) ? json_encode($payload['content_json'], JSON_UNESCAPED_UNICODE) : null,
            'space_id' => $spaceId,
            'created_by_user_id' => $actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $this->template($publicId) ?? [];
    }

    public function template(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, public_id, title, page_type, description, content_html, content_json, is_system, is_active, created_by_user_id, created_at, updated_at FROM knowledge_templates WHERE public_id = :public_id LIMIT 1');
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
        $relation = $relationType !== '' ? $relationType : 'related';
        $existing = $this->pdo->prepare('SELECT id, public_id, page_id, entity_type, entity_public_id, relation_type, created_by_user_id, created_at FROM knowledge_entity_links WHERE page_id = :page_id AND entity_type = :entity_type AND entity_public_id = :entity_public_id LIMIT 1');
        $existing->execute([
            'page_id' => (int)$page['id'],
            'entity_type' => $entityType,
            'entity_public_id' => $entityPublicId,
        ]);
        $existingLink = $existing->fetch(PDO::FETCH_ASSOC);
        if (is_array($existingLink)) {
            return $existingLink;
        }

        $publicId = $this->publicId('kbl');
        $stmt = $this->pdo->prepare('INSERT INTO knowledge_entity_links (public_id, page_id, entity_type, entity_public_id, relation_type, created_by_user_id, created_at) VALUES (:public_id, :page_id, :entity_type, :entity_public_id, :relation_type, :created_by_user_id, :created_at)');
        try {
            $stmt->execute([
                'public_id' => $publicId,
                'page_id' => (int)$page['id'],
                'entity_type' => $entityType,
                'entity_public_id' => $entityPublicId,
                'relation_type' => $relation,
                'created_by_user_id' => $actorId,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (\PDOException $e) {
            // A concurrent request may have won the unique page/entity race.
            $existing->execute([
                'page_id' => (int)$page['id'],
                'entity_type' => $entityType,
                'entity_public_id' => $entityPublicId,
            ]);
            $existingLink = $existing->fetch(PDO::FETCH_ASSOC);
            if (is_array($existingLink)) {
                return $existingLink;
            }
            throw $e;
        }
        $created = $this->pdo->prepare('SELECT id, public_id, page_id, entity_type, entity_public_id, relation_type, created_by_user_id, created_at FROM knowledge_entity_links WHERE public_id = :public_id LIMIT 1');
        $created->execute(['public_id' => $publicId]);
        return $created->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function links(string $pagePublicId): array
    {
        $page = $this->page($pagePublicId);
        if (!$page) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT id, public_id, page_id, entity_type, entity_public_id, relation_type, created_by_user_id, created_at FROM knowledge_entity_links WHERE page_id = :page_id ORDER BY id DESC');
        $stmt->execute(['page_id' => (int)$page['id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function unlinkEntity(string $pagePublicId, string $linkPublicId, array $actor): void
    {
        $page = $this->page($pagePublicId, $actor, 'edit');
        if (!$page) {
            throw new \RuntimeException('Knowledge page not found');
        }
        $stmt = $this->pdo->prepare('DELETE FROM knowledge_entity_links WHERE public_id = :public_id AND page_id = :page_id');
        $stmt->execute([
            'public_id' => $linkPublicId,
            'page_id' => (int)$page['id'],
        ]);
        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException('Knowledge link not found');
        }
    }

    public function linkContext(string $linkPublicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT l.public_id, p.public_id AS page_public_id FROM knowledge_entity_links l JOIN knowledge_pages p ON p.id = l.page_id WHERE l.public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $linkPublicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function entityPages(string $entityType, string $entityPublicId, ?array $actor = null): array
    {
        // Legacy internal callers omit an actor; preserve their historical
        // behavior while authenticated callers receive actor-scoped results.
        if ($actor === null) {
            $stmt = $this->pdo->prepare("SELECT p.*, s.public_id AS space_public_id, s.title AS space_title, l.relation_type, l.public_id AS link_public_id FROM knowledge_entity_links l JOIN knowledge_pages p ON p.id = l.page_id JOIN knowledge_spaces s ON s.id = p.space_id WHERE l.entity_type = :entity_type AND l.entity_public_id = :entity_public_id AND p.deleted_at IS NULL AND s.visibility = 'public' ORDER BY p.updated_at DESC");
            $stmt->execute(['entity_type' => $entityType, 'entity_public_id' => $entityPublicId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        [$aclSql, $aclParams] = $this->pageAccessSql('p', 's', $actor, 'view');
        $stmt = $this->pdo->prepare("SELECT p.*, s.public_id AS space_public_id, s.title AS space_title, l.relation_type, l.public_id AS link_public_id FROM knowledge_entity_links l JOIN knowledge_pages p ON p.id = l.page_id JOIN knowledge_spaces s ON s.id = p.space_id WHERE l.entity_type = :entity_type AND l.entity_public_id = :entity_public_id AND p.deleted_at IS NULL AND {$aclSql} ORDER BY p.updated_at DESC");
        $stmt->execute(['entity_type' => $entityType, 'entity_public_id' => $entityPublicId] + $aclParams);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Resolve the team(s) related to an entity.
     * - 'project': the project's own team.
     * - 'task': the team of the task's project.
     * - 'client'/'counterparty': teams of the entity's projects (client and
     *   counterparty share the same public_id space after consolidation).
     * - 'contact': teams of the projects of the contact's counterparty/client/company.
     * Returns a list of distinct, non-empty team public_ids (possibly empty).
     */
    public function entityTeamPublicIds(string $entityType, string $entityPublicId): array
    {
        if ($entityType === 'project') {
            $stmt = $this->pdo->prepare("SELECT team_public_id FROM projects WHERE public_id = :id AND team_public_id IS NOT NULL AND team_public_id <> '' LIMIT 1");
            $stmt->execute(['id' => $entityPublicId]);
            $team = trim((string)($stmt->fetchColumn() ?: ''));
            return $team !== '' ? [$team] : [];
        }
        if ($entityType === 'task') {
            $stmt = $this->pdo->prepare("SELECT p.team_public_id FROM tasks t JOIN projects p ON p.id = t.project_id WHERE t.public_id = :id AND p.team_public_id IS NOT NULL AND p.team_public_id <> '' LIMIT 1");
            $stmt->execute(['id' => $entityPublicId]);
            $team = trim((string)($stmt->fetchColumn() ?: ''));
            return $team !== '' ? [$team] : [];
        }
        if ($entityType === 'client' || $entityType === 'counterparty') {
            $stmt = $this->pdo->prepare("SELECT DISTINCT team_public_id FROM projects WHERE client_public_id = :id AND team_public_id IS NOT NULL AND team_public_id <> '' ORDER BY team_public_id ASC");
            $stmt->execute(['id' => $entityPublicId]);
            return $this->columnList($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'team_public_id');
        }
        if ($entityType === 'contact') {
            $stmt = $this->pdo->prepare('SELECT counterparty_id, client_id, company_id FROM contacts WHERE public_id = :id LIMIT 1');
            $stmt->execute(['id' => $entityPublicId]);
            $contact = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $publicIds = [];
            foreach (['counterparty_id' => 'counterparties', 'client_id' => 'clients', 'company_id' => 'companies'] as $column => $table) {
                $referenceId = (int)($contact[$column] ?? 0);
                if ($referenceId <= 0) {
                    continue;
                }
                $ref = $this->pdo->prepare("SELECT public_id FROM {$table} WHERE id = :id LIMIT 1");
                $ref->execute(['id' => $referenceId]);
                $publicId = trim((string)($ref->fetchColumn() ?: ''));
                if ($publicId !== '') {
                    $publicIds[] = $publicId;
                }
            }
            $publicIds = array_values(array_unique($publicIds));
            if ($publicIds === []) {
                return [];
            }
            $placeholders = implode(',', array_fill(0, count($publicIds), '?'));
            $stmt = $this->pdo->prepare("SELECT DISTINCT team_public_id FROM projects WHERE client_public_id IN ({$placeholders}) AND team_public_id IS NOT NULL AND team_public_id <> '' ORDER BY team_public_id ASC");
            $stmt->execute($publicIds);
            return $this->columnList($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'team_public_id');
        }
        return [];
    }

    /**
     * Single-team convenience wrapper: first related team ('' when none).
     */
    public function entityTeamPublicId(string $entityType, string $entityPublicId): string
    {
        $ids = $this->entityTeamPublicIds($entityType, $entityPublicId);
        return $ids[0] ?? '';
    }

    private function columnList(array $rows, string $column): array
    {
        return array_values(array_filter(array_map(
            fn(array $row): string => trim((string)($row[$column] ?? '')),
            $rows
        ), fn(string $value): bool => $value !== ''));
    }

    public function comments(string $pagePublicId, int $offset = 0, int $limit = 0): array
    {
        $page = $this->pageIdentity($pagePublicId);
        if (!$page) {
            return [];
        }
        $sql = 'SELECT c.*, u.public_id AS user_public_id, u.full_name AS user_name, pu.full_name AS parent_user_name FROM knowledge_comments c LEFT JOIN users u ON u.id = c.user_id LEFT JOIN knowledge_comments pc ON pc.id = c.parent_id LEFT JOIN users pu ON pu.id = pc.user_id WHERE c.page_id = :page_id ORDER BY c.created_at ASC';
        $params = ['page_id' => (int)$page['id']];
        if ($limit > 0) {
            $sql .= ' LIMIT :limit OFFSET :offset';
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':page_id', $params['page_id'], \PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function addComment(string $pagePublicId, string $body, int $userId, ?string $parentPublicId = null): ?array
    {
        $page = $this->pageIdentity($pagePublicId);
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
        $body = $this->sanitizeHtml($body);
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

    public function favorites(int $userId, int $limit = 20, int $offset = 0, ?array $actor = null): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        [$aclSql, $aclParams] = $this->pageAccessSql('p', 's', $actor, 'view');
        $stmt = $this->pdo->prepare('SELECT p.id, p.public_id, p.title, p.status, p.page_type, p.updated_at, p.published_at, p.views_count, s.title AS space_title, s.public_id AS space_public_id
            FROM favorites f
            INNER JOIN knowledge_pages p ON p.public_id = f.entity_public_id AND p.deleted_at IS NULL
            LEFT JOIN knowledge_spaces s ON s.id = p.space_id
            WHERE f.entity_type = :entity_type AND f.user_id = :user_id AND ' . $aclSql . '
            ORDER BY f.created_at DESC
            LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':entity_type', 'knowledge_page');
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        foreach ($aclParams as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function suggest(string $query, int $limit = 10, ?array $actor = null): array
    {
        $limit = max(1, min(50, $limit));
        $q = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';
        [$aclSql, $aclParams] = $this->pageAccessSql('p', 's', $actor, 'view');
        $stmt = $this->pdo->prepare('SELECT p.public_id, p.title, p.page_type, s.title AS space_title, s.public_id AS space_public_id
            FROM knowledge_pages p
            LEFT JOIN knowledge_spaces s ON s.id = p.space_id
            WHERE p.deleted_at IS NULL AND p.status = :status AND (p.title LIKE :q_title OR p.content_text LIKE :q_content) AND ' . $aclSql . '
            ORDER BY p.views_count DESC, p.updated_at DESC
            LIMIT :limit');
        $stmt->bindValue(':status', 'published');
        $stmt->bindValue(':q_title', $q);
        $stmt->bindValue(':q_content', $q);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        foreach ($aclParams as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function analytics(?array $actor = null): array
    {
        [$pageAclSql, $pageAclParams] = $this->pageAccessSql('p', 's', $actor, 'view');
        [$spaceAclSql, $spaceAclParams] = $this->spaceAccessSql('s', $actor, 'view');
        $pageWhere = 'p.deleted_at IS NULL AND ' . $pageAclSql;
        $spaceWhere = 's.is_archived = 0 AND ' . $spaceAclSql;
        $archivedSpaceWhere = 's.is_archived = 1 AND ' . $spaceAclSql;
        $params = array_merge($pageAclParams, $spaceAclParams);
        $stmt = $this->pdo->prepare("SELECT
            (SELECT COUNT(*) FROM knowledge_pages p JOIN knowledge_spaces s ON s.id = p.space_id WHERE {$pageWhere}) AS total_pages,
            (SELECT COUNT(*) FROM knowledge_pages p JOIN knowledge_spaces s ON s.id = p.space_id WHERE {$pageWhere} AND p.status = 'published') AS published,
            (SELECT COUNT(*) FROM knowledge_pages p JOIN knowledge_spaces s ON s.id = p.space_id WHERE {$pageWhere} AND p.status = 'draft') AS drafts,
            (SELECT COUNT(*) FROM knowledge_pages p JOIN knowledge_spaces s ON s.id = p.space_id WHERE {$pageWhere} AND p.status = 'review') AS review_queue,
            (SELECT COUNT(*) FROM knowledge_pages p JOIN knowledge_spaces s ON s.id = p.space_id WHERE {$pageWhere} AND p.status = 'archived') AS archived,
            (SELECT COUNT(*) FROM knowledge_spaces s WHERE {$spaceWhere}) AS active_spaces,
            (SELECT COUNT(*) FROM knowledge_spaces s WHERE {$archivedSpaceWhere}) AS archived_spaces,
            (SELECT COUNT(*) FROM knowledge_comments) AS total_comments,
            (SELECT COUNT(*) FROM knowledge_page_versions) AS total_versions,
            (SELECT COUNT(*) FROM knowledge_entity_links) AS total_links
        ");
        $stmt->execute($params);
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

    private function legacyAddVersion(string $pagePublicId, ?int $actorId, string $summary): void
    {
        $page = $this->page($pagePublicId);
        if (!$page) {
            return;
        }
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(version_number), 0) + 1 FROM knowledge_page_versions WHERE page_id = :page_id');
        $stmt->execute(['page_id' => (int)$page['id']]);
        $next = (int)$stmt->fetchColumn();
        $insert = $this->pdo->prepare('INSERT INTO knowledge_page_versions (public_id, page_id, page_public_id, version_number, title, content, content_text, change_type, change_note, created_by_user_id, created_at) VALUES (:public_id, :page_id, :page_public_id, :version_number, :title, :content, :content_text, :change_type, :change_note, :created_by_user_id, :created_at)');
        $insert->execute([
            'public_id' => 'kpv_' . bin2hex(random_bytes(16)),
            'page_id' => (int)$page['id'],
            'page_public_id' => (string)$page['public_id'],
            'version_number' => $next,
            'title' => (string)$page['title'],
            'content' => (string)$page['content_html'],
            'content_text' => (string)$page['content_text'],
            'change_type' => 'update',
            'change_note' => $summary,
            'created_by_user_id' => $actorId,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    private function pageWhere(array $filters, ?array $actor = null): array
    {
        $where = ['p.deleted_at IS NULL', 's.is_archived = 0'];
        $params = [];
        [$aclSql, $aclParams] = $this->pageAccessSql('p', 's', $actor, (string)($filters['min_access'] ?? 'view'));
        $where[] = $aclSql;
        $params += $aclParams;
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
            $q = (string)$filters['q'];
            $like = '%' . $q . '%';
            if (strlen($q) >= 4) {
                $safeQ = preg_replace('/[^\p{L}\p{N}\s\-\_\']/u', ' ', $q);
                $safeQ = trim(preg_replace('/\s+/', ' ', $safeQ));
                if ($safeQ !== '') {
                    $words = explode(' ', $safeQ);
                    $terms = [];
                    foreach ($words as $w) {
                        $w = trim($w);
                        if (strlen($w) >= 2) {
                            $terms[] = '+' . $w . '*';
                        }
                    }
                    if ($terms !== []) {
                        $where[] = 'MATCH(p.title, p.content_text) AGAINST(:q_ft IN BOOLEAN MODE)';
                        $params['q_ft'] = implode(' ', $terms);
                    } else {
                        $where[] = '(p.title LIKE :q_title OR p.content_text LIKE :q_content OR s.title LIKE :q_space)';
                        $params['q_title'] = $like;
                        $params['q_content'] = $like;
                        $params['q_space'] = $like;
                    }
                } else {
                    $where[] = '(p.title LIKE :q_title OR p.content_text LIKE :q_content OR s.title LIKE :q_space)';
                    $params['q_title'] = $like;
                    $params['q_content'] = $like;
                    $params['q_space'] = $like;
                }
            } else {
                $where[] = '(p.title LIKE :q_title OR p.content_text LIKE :q_content OR s.title LIKE :q_space)';
                $params['q_title'] = $like;
                $params['q_content'] = $like;
                $params['q_space'] = $like;
            }
        }
        if (!empty($filters['tag_public_id'])) {
            $where[] = 'EXISTS(SELECT 1 FROM entity_tags et2 INNER JOIN tags tg2 ON tg2.id = et2.tag_id WHERE et2.entity_type = :tag_entity_type AND et2.entity_public_id = p.public_id AND tg2.public_id = :tag_public_id)';
            $params['tag_entity_type'] = 'knowledge_page';
            $params['tag_public_id'] = (string)$filters['tag_public_id'];
        }
        return [implode(' AND ', $where), $params];
    }

    private function pageAccessSql(string $pageAlias, string $spaceAlias, ?array $actor, string $minAccess = 'view'): array
    {
        if ($this->actorBypassesKnowledgeAcl($actor)) {
            return ['1=1', []];
        }

        $actorId = $this->actorUserId($actor);
        $rank = $this->accessRank($minAccess);
        $roleIds = $actorId > 0 ? $this->actorRoleIds($actorId) : [];
        $teamIds = $actorId > 0 ? $this->actorTeamIds($actorId) : [];
        $departmentIds = $actorId > 0 ? $this->actorDepartmentIds($actorId) : [];
        $params = [
            'acl_public_rank' => $rank,
            'acl_space_perm_rank' => $rank,
            'acl_page_perm_rank' => $rank,
            'acl_space_owner_user_id' => $actorId,
            'acl_page_owner_user_id' => $actorId,
            'acl_space_perm_user_id' => $actorId,
            'acl_page_perm_user_id' => $actorId,
        ];

        $spaceRoleClause = '0=1';
        $pageRoleClause = '0=1';
        if ($roleIds !== []) {
            $spacePlaceholders = [];
            $pagePlaceholders = [];
            foreach ($roleIds as $index => $roleId) {
                $spaceKey = 'acl_space_role_' . $index;
                $pageKey = 'acl_page_role_' . $index;
                $spacePlaceholders[] = ':' . $spaceKey;
                $pagePlaceholders[] = ':' . $pageKey;
                $params[$spaceKey] = $roleId;
                $params[$pageKey] = $roleId;
            }
            $spaceRoleClause = 'perm.subject_type = \'role\' AND perm.subject_id IN (' . implode(',', $spacePlaceholders) . ')';
            $pageRoleClause = 'perm.subject_type = \'role\' AND perm.subject_id IN (' . implode(',', $pagePlaceholders) . ')';
        }

        $spaceTeamClause = '0=1';
        $pageTeamClause = '0=1';
        if ($teamIds !== []) {
            $spacePlaceholders = [];
            $pagePlaceholders = [];
            foreach ($teamIds as $index => $teamId) {
                $spaceKey = 'acl_space_team_' . $index;
                $pageKey = 'acl_page_team_' . $index;
                $spacePlaceholders[] = ':' . $spaceKey;
                $pagePlaceholders[] = ':' . $pageKey;
                $params[$spaceKey] = $teamId;
                $params[$pageKey] = $teamId;
            }
            $spaceTeamClause = 'perm.subject_type = \'team\' AND perm.subject_id IN (' . implode(',', $spacePlaceholders) . ')';
            $pageTeamClause = 'perm.subject_type = \'team\' AND perm.subject_id IN (' . implode(',', $pagePlaceholders) . ')';
        }

        $spaceDepartmentClause = '0=1';
        $pageDepartmentClause = '0=1';
        if ($departmentIds !== []) {
            $spacePlaceholders = [];
            $pagePlaceholders = [];
            foreach ($departmentIds as $index => $departmentId) {
                $spaceKey = 'acl_space_department_' . $index;
                $pageKey = 'acl_page_department_' . $index;
                $spacePlaceholders[] = ':' . $spaceKey;
                $pagePlaceholders[] = ':' . $pageKey;
                $params[$spaceKey] = $departmentId;
                $params[$pageKey] = $departmentId;
            }
            $spaceDepartmentClause = 'perm.subject_type = \'department\' AND perm.subject_id IN (' . implode(',', $spacePlaceholders) . ')';
            $pageDepartmentClause = 'perm.subject_type = \'department\' AND perm.subject_id IN (' . implode(',', $pagePlaceholders) . ')';
        }

        $rankSql = $this->accessRankSql('perm.access_level');
        $defaultRankSql = $this->accessRankSql($spaceAlias . '.default_access_level');
        $sql = "(
            ({$spaceAlias}.visibility = 'public' AND {$defaultRankSql} >= :acl_public_rank)
            OR {$spaceAlias}.owner_user_id = :acl_space_owner_user_id
            OR {$pageAlias}.owner_user_id = :acl_page_owner_user_id
            OR EXISTS (
                SELECT 1 FROM knowledge_space_permissions perm
                WHERE perm.space_id = {$spaceAlias}.id
                  AND {$rankSql} >= :acl_space_perm_rank
                  AND ((perm.subject_type = 'user' AND perm.subject_id = :acl_space_perm_user_id) OR ({$spaceRoleClause}) OR ({$spaceTeamClause}) OR ({$spaceDepartmentClause}))
            )
            OR EXISTS (
                SELECT 1 FROM knowledge_page_permissions perm
                WHERE perm.page_id = {$pageAlias}.id
                  AND {$rankSql} >= :acl_page_perm_rank
                  AND ((perm.subject_type = 'user' AND perm.subject_id = :acl_page_perm_user_id) OR ({$pageRoleClause}) OR ({$pageTeamClause}) OR ({$pageDepartmentClause}))
            )
        )";

        return [$sql, $params];
    }

    private function spaceAccessSql(string $spaceAlias, ?array $actor, string $minAccess = 'view'): array
    {
        if ($this->actorBypassesKnowledgeAcl($actor)) {
            return ['1=1', []];
        }

        $actorId = $this->actorUserId($actor);
        $rank = $this->accessRank($minAccess);
        $roleIds = $actorId > 0 ? $this->actorRoleIds($actorId) : [];
        $teamIds = $actorId > 0 ? $this->actorTeamIds($actorId) : [];
        $departmentIds = $actorId > 0 ? $this->actorDepartmentIds($actorId) : [];
        $params = [
            'acl_space_public_rank' => $rank,
            'acl_space_owner_user_id' => $actorId,
            'acl_space_perm_rank' => $rank,
            'acl_space_perm_user_id' => $actorId,
        ];

        $roleClause = '0=1';
        if ($roleIds !== []) {
            $placeholders = [];
            foreach ($roleIds as $index => $roleId) {
                $key = 'acl_space_role_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $roleId;
            }
            $roleClause = 'perm.subject_type = \'role\' AND perm.subject_id IN (' . implode(',', $placeholders) . ')';
        }

        $teamClause = '0=1';
        if ($teamIds !== []) {
            $placeholders = [];
            foreach ($teamIds as $index => $teamId) {
                $key = 'acl_space_team_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $teamId;
            }
            $teamClause = 'perm.subject_type = \'team\' AND perm.subject_id IN (' . implode(',', $placeholders) . ')';
        }

        $departmentClause = '0=1';
        if ($departmentIds !== []) {
            $placeholders = [];
            foreach ($departmentIds as $index => $departmentId) {
                $key = 'acl_space_department_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $departmentId;
            }
            $departmentClause = 'perm.subject_type = \'department\' AND perm.subject_id IN (' . implode(',', $placeholders) . ')';
        }

        $rankSql = $this->accessRankSql('perm.access_level');
        $defaultRankSql = $this->accessRankSql($spaceAlias . '.default_access_level');
        $sql = "(
            ({$spaceAlias}.visibility = 'public' AND {$defaultRankSql} >= :acl_space_public_rank)
            OR {$spaceAlias}.owner_user_id = :acl_space_owner_user_id
            OR EXISTS (
                SELECT 1 FROM knowledge_space_permissions perm
                WHERE perm.space_id = {$spaceAlias}.id
                  AND {$rankSql} >= :acl_space_perm_rank
                  AND ((perm.subject_type = 'user' AND perm.subject_id = :acl_space_perm_user_id) OR ({$roleClause}) OR ({$teamClause}) OR ({$departmentClause}))
            )
        )";

        return [$sql, $params];
    }

    private function actorBypassesKnowledgeAcl(?array $actor): bool
    {
        if (!$actor) {
            return false;
        }
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }
        $permissions = array_map('strval', (array)($actor['permission_codes'] ?? []));
        return in_array('*', $permissions, true) || in_array('knowledge.admin', $permissions, true);
    }

    private function actorUserId(?array $actor): int
    {
        if (!$actor) {
            return 0;
        }
        $id = (int)($actor['id'] ?? 0);
        if ($id > 0) {
            return $id;
        }
        $publicId = trim((string)($actor['public_id'] ?? ''));
        if ($publicId === '') {
            return 0;
        }
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $publicId]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    private function actorRoleIds(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT role_id FROM user_roles WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        return array_values(array_unique(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [])));
    }

    private function actorTeamIds(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        static $cache = [];
        if (isset($cache[$userId])) {
            return $cache[$userId];
        }
        $stmt = $this->pdo->prepare('SELECT id FROM teams WHERE manager_user_id = :uid1 OR created_by_user_id = :uid2 OR FIND_IN_SET(:uid3, COALESCE(member_user_ids, \'\')) > 0');
        $stmt->execute(['uid1' => $userId, 'uid2' => $userId, 'uid3' => $userId]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        $ids = array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id > 0)));
        $cache[$userId] = $ids;
        return $ids;
    }

    private function actorDepartmentIds(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        static $cache = [];
        if (isset($cache[$userId])) {
            return $cache[$userId];
        }
        $stmt = $this->pdo->prepare('SELECT id FROM departments WHERE manager_user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        $cache[$userId] = $ids;
        return $ids;
    }

    private function resolvePermissionSubjectId(string $subjectType, int $subjectId, string $subjectPublicId): int
    {
        if ($subjectId > 0) {
            return $subjectId;
        }
        $publicId = trim($subjectPublicId);
        if ($publicId === '') {
            return 0;
        }
        $tables = [
            'user' => 'users',
            'role' => 'roles',
            'team' => 'teams',
            'department' => 'departments',
        ];
        $table = $tables[$subjectType] ?? '';
        if ($table === '') {
            return 0;
        }
        $stmt = $this->pdo->prepare("SELECT id FROM {$table} WHERE public_id = :public_id LIMIT 1");
        $stmt->execute(['public_id' => $publicId]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    private function pageIdentity(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, public_id, space_id FROM knowledge_pages WHERE public_id = :public_id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['public_id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function decodeIdList(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('intval', $decoded), static fn(int $id): bool => $id > 0)));
    }

    private function accessRank(string $level): int
    {
        return self::ACCESS_RANK[$level] ?? self::ACCESS_RANK['view'];
    }

    private function accessRankSql(string $expr): string
    {
        return "CASE {$expr}
            WHEN 'owner' THEN 50
            WHEN 'manage' THEN 40
            WHEN 'edit' THEN 30
            WHEN 'comment' THEN 20
            WHEN 'view' THEN 10
            ELSE 0
        END";
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

    private function resolveSpace(string $publicId, ?array $actor = null, string $minAccess = 'view'): ?array
    {
        if ($publicId === '') {
            return null;
        }
        return $this->space($publicId, $actor, $minAccess);
    }

    private function resolvePage(string $publicId, ?array $actor = null, string $minAccess = 'view'): ?array
    {
        return $publicId !== '' ? $this->page($publicId, $actor, $minAccess) : null;
    }

    private function countPages(int $spaceId, ?array $actor = null): int
    {
        [$aclSql, $aclParams] = $this->pageAccessSql('p', 's', $actor, 'view');
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM knowledge_pages p JOIN knowledge_spaces s ON s.id = p.space_id WHERE p.space_id = :space_id AND p.deleted_at IS NULL AND {$aclSql}");
        $stmt->execute(['space_id' => $spaceId] + $aclParams);
        return (int)$stmt->fetchColumn();
    }

    private function totals(?array $actor = null): array
    {
        if ($this->actorBypassesKnowledgeAcl($actor)) {
            return [
                'spaces' => (int)$this->pdo->query('SELECT COUNT(*) FROM knowledge_spaces WHERE is_archived = 0')->fetchColumn(),
                'pages' => (int)$this->pdo->query('SELECT COUNT(*) FROM knowledge_pages WHERE deleted_at IS NULL')->fetchColumn(),
                'published' => (int)$this->pdo->query("SELECT COUNT(*) FROM knowledge_pages WHERE deleted_at IS NULL AND status = 'published'")->fetchColumn(),
                'drafts' => (int)$this->pdo->query("SELECT COUNT(*) FROM knowledge_pages WHERE deleted_at IS NULL AND status = 'draft'")->fetchColumn(),
            ];
        }
        [$spaceAclSql, $spaceAclParams] = $this->spaceAccessSql('s', $actor, 'view');
        [$pageAclSql, $pageAclParams] = $this->pageAccessSql('p', 's', $actor, 'view');
        $spaces = $this->pdo->prepare("SELECT COUNT(*) FROM knowledge_spaces s WHERE s.is_archived = 0 AND {$spaceAclSql}");
        $spaces->execute($spaceAclParams);
        $pageCount = function (string $extraWhere = '') use ($pageAclSql, $pageAclParams): int {
            $sql = "SELECT COUNT(*) FROM knowledge_pages p JOIN knowledge_spaces s ON s.id = p.space_id WHERE p.deleted_at IS NULL AND {$pageAclSql}";
            if ($extraWhere !== '') {
                $sql .= ' AND ' . $extraWhere;
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($pageAclParams);
            return (int)$stmt->fetchColumn();
        };
        return [
            'spaces' => (int)$spaces->fetchColumn(),
            'pages' => $pageCount(),
            'published' => $pageCount("p.status = 'published'"),
            'drafts' => $pageCount("p.status = 'draft'"),
        ];
    }

    // ── Batch import methods (Confluence migration support) ──

    public function createSpaceWithSource(array $payload, ?int $actorId): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $publicId = $this->publicId('kbs');
        $title = trim((string)($payload['title'] ?? ''));
        $slug = $this->uniqueSlug('knowledge_spaces', $this->slug((string)($payload['slug'] ?? $title), 'space'), null);
        $parentId = null;
        if (!empty($payload['parent_public_id'])) {
            $parent = $this->space((string)$payload['parent_public_id']);
            $parentId = $parent ? (int)$parent['id'] : null;
        } elseif (!empty($payload['parent_id'])) {
            $parentId = (int)$payload['parent_id'];
        }
        $hasParentCol = $this->columnExists('knowledge_spaces', 'parent_id');
        if ($hasParentCol) {
            $stmt = $this->pdo->prepare('INSERT INTO knowledge_spaces (public_id, title, slug, description, icon, color, owner_user_id, visibility, default_access_level, parent_id, sort_order, source_type, source_id, source_url, source_payload_json, created_at, updated_at) VALUES (:public_id, :title, :slug, :description, :icon, :color, :owner_user_id, :visibility, :default_access_level, :parent_id, :sort_order, :source_type, :source_id, :source_url, :source_payload_json, :created_at, :updated_at)');
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO knowledge_spaces (public_id, title, slug, description, icon, color, owner_user_id, visibility, default_access_level, sort_order, source_type, source_id, source_url, source_payload_json, created_at, updated_at) VALUES (:public_id, :title, :slug, :description, :icon, :color, :owner_user_id, :visibility, :default_access_level, :sort_order, :source_type, :source_id, :source_url, :source_payload_json, :created_at, :updated_at)');
        }
        $params = [
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
            'source_type' => $this->nullableShort($payload['source_type'] ?? null, 64),
            'source_id' => $this->nullableShort($payload['source_id'] ?? null, 255),
            'source_url' => $this->nullableShort($payload['source_url'] ?? null, 2048),
            'source_payload_json' => isset($payload['source_payload_json']) ? json_encode($payload['source_payload_json'], JSON_UNESCAPED_UNICODE) : null,
            'created_at' => (string)($payload['created_at'] ?? $now),
            'updated_at' => (string)($payload['updated_at'] ?? $now),
        ];
        if ($hasParentCol) {
            $params['parent_id'] = $parentId;
        }
        $stmt->execute($params);
        return $this->space($publicId) ?? [];
    }

    public function updateSpaceSource(string $publicId, array $source): ?array
    {
        $current = $this->space($publicId);
        if (!$current) {
            return null;
        }
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('UPDATE knowledge_spaces SET source_type = :source_type, source_id = :source_id, source_url = :source_url, source_payload_json = :source_payload_json, row_version = row_version + 1, updated_at = :updated_at WHERE public_id = :public_id');
        $stmt->execute([
            'source_type' => $this->nullableShort($source['source_type'] ?? null, 64),
            'source_id' => $this->nullableShort($source['source_id'] ?? null, 255),
            'source_url' => $this->nullableShort($source['source_url'] ?? null, 2048),
            'source_payload_json' => isset($source['source_payload_json']) ? json_encode($source['source_payload_json'], JSON_UNESCAPED_UNICODE) : null,
            'updated_at' => $now,
            'public_id' => $publicId,
        ]);
        return $this->space($publicId);
    }

    public function createPageShell(array $payload, ?int $actorId, ?array $actor = null): array
    {
        $space = !empty($payload['space_public_id'])
            ? $this->resolveSpace((string)$payload['space_public_id'], $actor, 'edit')
            : null;
        if (!$space) {
            throw new \RuntimeException('Knowledge space with edit access is required');
        }
        $parent = !empty($payload['parent_public_id'])
            ? $this->resolvePage((string)$payload['parent_public_id'], $actor, 'edit')
            : null;
        $title = trim((string)($payload['title'] ?? ''));
        $now = gmdate('Y-m-d H:i:s');
        $publicId = $this->publicId('kbp');

        $stmt = $this->pdo->prepare('INSERT INTO knowledge_pages (public_id, space_id, parent_id, title, slug, page_type, status, sort_order, path, depth, owner_user_id, last_editor_user_id, source_type, source_id, source_url, source_payload_json, created_at, updated_at) VALUES (:public_id, :space_id, :parent_id, :title, :slug, :page_type, :status, :sort_order, :path, :depth, :owner_user_id, :last_editor_user_id, :source_type, :source_id, :source_url, :source_payload_json, :created_at, :updated_at)');
        $stmt->execute([
            'public_id' => $publicId,
            'space_id' => (int)$space['id'],
            'parent_id' => $parent ? (int)$parent['id'] : null,
            'title' => $title,
            'slug' => $this->uniquePageSlug((int)$space['id'], $this->slug((string)($payload['slug'] ?? $title), 'page'), null),
            'page_type' => $this->choice((string)($payload['page_type'] ?? 'article'), self::PAGE_TYPES, 'article'),
            'status' => $this->choice((string)($payload['status'] ?? 'draft'), ['draft', 'review', 'published', 'archived', 'needs_update'], 'draft'),
            'sort_order' => (int)($payload['sort_order'] ?? 0),
            'path' => '',
            'depth' => $parent ? ((int)($parent['depth'] ?? 0) + 1) : 0,
            'owner_user_id' => $actorId,
            'last_editor_user_id' => $actorId,
            'source_type' => $this->nullableShort($payload['source_type'] ?? null, 64),
            'source_id' => $this->nullableShort($payload['source_id'] ?? null, 255),
            'source_url' => $this->nullableShort($payload['source_url'] ?? null, 2048),
            'source_payload_json' => isset($payload['source_payload_json']) ? json_encode($payload['source_payload_json'], JSON_UNESCAPED_UNICODE) : null,
            'created_at' => (string)($payload['created_at'] ?? $now),
            'updated_at' => (string)($payload['updated_at'] ?? $now),
        ]);
        $page = $this->page($publicId) ?? [];
        $this->refreshPagePath((int)$page['id']);
        $this->refreshChildrenCount($parent ? (int)$parent['id'] : null);
        return $this->page($publicId) ?? $page;
    }

    public function updatePageParent(string $publicId, ?string $parentPublicId, ?array $actor = null): ?array
    {
        $current = $this->page($publicId, $actor, 'edit');
        if (!$current) {
            return null;
        }
        $parent = $parentPublicId !== null ? $this->resolvePage($parentPublicId, $actor, 'edit') : null;
        $now = gmdate('Y-m-d H:i:s');
        $this->pdo->prepare('UPDATE knowledge_pages SET parent_id = :parent_id, depth = :depth, row_version = row_version + 1, updated_at = :updated_at WHERE public_id = :public_id')->execute([
            'parent_id' => $parent ? (int)$parent['id'] : null,
            'depth' => $parent ? ((int)($parent['depth'] ?? 0) + 1) : 0,
            'updated_at' => $now,
            'public_id' => $publicId,
        ]);
        $this->refreshPagePath((int)$current['id']);
        $this->refreshChildrenCount(isset($current['parent_id']) ? (int)$current['parent_id'] : null);
        $this->refreshChildrenCount($parent ? (int)$parent['id'] : null);
        return $this->page($publicId);
    }

    public function batchPublish(string $publicId, ?int $actorId, bool $silent = false): ?array
    {
        $page = $this->page($publicId);
        if (!$page) {
            return null;
        }
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare("UPDATE knowledge_pages SET status = 'published', review_status = 'approved', published_by_user_id = :actor, published_at = :published_at, reviewed_at = :reviewed_at, row_version = row_version + 1, updated_at = :updated_at WHERE public_id = :public_id");
        $stmt->execute([
            'actor' => $actorId,
            'published_at' => !$silent ? $now : ((string)($page['published_at'] ?? $now)),
            'reviewed_at' => $now,
            'updated_at' => $now,
            'public_id' => $publicId,
        ]);
        if (!$silent) {
            $this->legacyAddVersion($publicId, $actorId, 'Imported and published');
        }
        return $this->page($publicId);
    }

    public function findPageBySource(string $sourceType, string $sourceId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT public_id FROM knowledge_pages WHERE source_type = :source_type AND source_id = :source_id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['source_type' => $sourceType, 'source_id' => $sourceId]);
        $publicId = $stmt->fetchColumn();
        if ($publicId === false) {
            return null;
        }
        return $this->page((string)$publicId);
    }

    public function findSpaceBySource(string $sourceType, string $sourceId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT public_id FROM knowledge_spaces WHERE source_type = :source_type AND source_id = :source_id LIMIT 1');
        $stmt->execute(['source_type' => $sourceType, 'source_id' => $sourceId]);
        $publicId = $stmt->fetchColumn();
        if ($publicId === false) {
            return null;
        }
        return $this->space((string)$publicId);
    }

    // ── Page Properties ──

    public function pageProperties(string $pagePublicId): array
    {
        $page = $this->pageIdentity($pagePublicId);
        if (!$page) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT id, page_id, property_key, property_value, property_type, source_type, source_id, sort_order, created_at, updated_at FROM knowledge_page_properties WHERE page_id = :page_id ORDER BY sort_order ASC, property_key ASC');
        $stmt->execute(['page_id' => (int)$page['id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function setPageProperty(string $pagePublicId, string $key, mixed $value, string $type = 'string', ?string $sourceType = null, ?string $sourceId = null): ?array
    {
        $page = $this->pageIdentity($pagePublicId);
        if (!$page) {
            return null;
        }
        $now = gmdate('Y-m-d H:i:s');
        $jsonValue = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        $stmt = $this->pdo->prepare('INSERT INTO knowledge_page_properties (page_id, property_key, property_value, property_type, source_type, source_id, sort_order, created_at, updated_at) VALUES (:page_id, :pkey, :pvalue, :ptype, :stype, :sid, 0, :now, :now) ON DUPLICATE KEY UPDATE property_value = :pvalue2, property_type = :ptype2, source_type = :stype2, source_id = :sid2, updated_at = :now2');
        $stmt->execute([
            'page_id' => (int)$page['id'],
            'pkey' => $key,
            'pvalue' => $jsonValue,
            'ptype' => $type,
            'stype' => $sourceType,
            'sid' => $sourceId,
            'now' => $now,
            'pvalue2' => $jsonValue,
            'ptype2' => $type,
            'stype2' => $sourceType,
            'sid2' => $sourceId,
            'now2' => $now,
        ]);
        $stmt = $this->pdo->prepare('SELECT id, page_id, property_key, property_value, property_type, source_type, source_id, sort_order, created_at, updated_at FROM knowledge_page_properties WHERE page_id = :page_id AND property_key = :pkey LIMIT 1');
        $stmt->execute(['page_id' => (int)$page['id'], 'pkey' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function deletePageProperty(string $pagePublicId, string $key): bool
    {
        $page = $this->pageIdentity($pagePublicId);
        if (!$page) {
            return false;
        }
        $stmt = $this->pdo->prepare('DELETE FROM knowledge_page_properties WHERE page_id = :page_id AND property_key = :pkey');
        $stmt->execute(['page_id' => (int)$page['id'], 'pkey' => $key]);
        return $stmt->rowCount() > 0;
    }

    // ── Comments with source metadata ──

    public function addCommentWithSource(string $pagePublicId, string $body, int $userId, array $source = [], ?string $parentPublicId = null): ?array
    {
        $page = $this->pageIdentity($pagePublicId);
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
        $bodyClean = $this->sanitizeHtml($body);
        $stmt = $this->pdo->prepare('INSERT INTO knowledge_comments (public_id, page_id, parent_id, user_id, body, source_type, source_id, source_author_name, source_created_at, anchor_text, anchor_path, is_inline, created_at, updated_at) VALUES (:public_id, :page_id, :parent_id, :user_id, :body, :source_type, :source_id, :source_author_name, :source_created_at, :anchor_text, :anchor_path, :is_inline, :created_at, :updated_at)');
        $stmt->execute([
            'public_id' => $publicId,
            'page_id' => (int)$page['id'],
            'parent_id' => $parentId,
            'user_id' => $userId,
            'body' => $bodyClean,
            'source_type' => $this->nullableShort($source['source_type'] ?? null, 64),
            'source_id' => $this->nullableShort($source['source_id'] ?? null, 255),
            'source_author_name' => $this->nullableShort($source['source_author_name'] ?? null, 255),
            'source_created_at' => $source['source_created_at'] ?? null,
            'anchor_text' => $this->nullableShort($source['anchor_text'] ?? null, 500),
            'anchor_path' => $this->nullableShort($source['anchor_path'] ?? null, 500),
            'is_inline' => !empty($source['is_inline']) ? 1 : 0,
            'created_at' => (string)($source['source_created_at'] ?? $now),
            'updated_at' => $now,
        ]);
        $this->pdo->prepare('UPDATE knowledge_pages SET comments_count = comments_count + 1 WHERE id = :id')->execute(['id' => (int)$page['id']]);
        $row = $this->comment($publicId);
        $row['user_public_id'] = null;
        $row['user_name'] = null;
        return $row;
    }

    public function reindexPage(int $pageId): void
    {
        $stmt = $this->pdo->prepare('SELECT p.id, p.space_id, p.title, p.content_text, p.status, p.page_type, p.updated_at FROM knowledge_pages p WHERE p.id = :id LIMIT 1');
        $stmt->execute(['id' => $pageId]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($page)) {
            return;
        }
        // Gather tags
        $tagStmt = $this->pdo->prepare("SELECT t.title FROM entity_tags et JOIN tags t ON t.id = et.tag_id WHERE et.entity_type = 'knowledge_page' AND et.entity_public_id = (SELECT public_id FROM knowledge_pages WHERE id = :id)");
        $tagStmt->execute(['id' => $pageId]);
        $tags = $tagStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $tagsText = implode(' ', $tags);
        $stmt = $this->pdo->prepare('REPLACE INTO knowledge_search_index (page_id, space_id, title, content_text, tags_text, entity_text, status, page_type, updated_at) VALUES (:page_id, :space_id, :title, :content_text, :tags_text, :entity_text, :status, :page_type, :updated_at)');
        $stmt->execute([
            'page_id' => (int)$page['id'],
            'space_id' => (int)$page['space_id'],
            'title' => (string)$page['title'],
            'content_text' => (string)$page['content_text'],
            'tags_text' => $tagsText,
            'entity_text' => '',
            'status' => (string)$page['status'],
            'page_type' => (string)$page['page_type'],
            'updated_at' => (string)$page['updated_at'],
        ]);
    }

    public function removePageFromSearchIndex(int $pageId): void
    {
        $this->pdo->prepare('DELETE FROM knowledge_search_index WHERE page_id = :page_id')->execute(['page_id' => $pageId]);
    }

    public function hasColumn(string $table, string $column): bool
    {
        return $this->columnExists($table, $column);
    }

    // ── End batch import methods ──

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

    public function reindexSearch(): void
    {
        $this->pdo->exec("UPDATE knowledge_pages SET content_text = TRIM(REGEXP_REPLACE(REGEXP_REPLACE(content_html, '<[^>]+>', ' '), '[[:space:]]+', ' ')) WHERE content_text IS NULL OR content_text = ''");
        $res = $this->pdo->query("SHOW INDEX FROM knowledge_pages WHERE Key_name = 'ft_search'");
        if (!$res || $res->rowCount() === 0) {
            $this->pdo->exec('ALTER TABLE knowledge_pages ADD FULLTEXT INDEX ft_search (title, content_text)');
        }
    }

    private function sanitizeHtml(string $html): string
    {
        $html = preg_replace('#<(script|style|iframe|object|embed|form|input|button|svg|math)[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = preg_replace('#</?(script|style|iframe|object|embed|form|input|button|svg|math)[^>]*>#i', '', $html) ?? '';
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace('/(href|src|action|formaction)\s*=\s*("|\')\s*(javascript|data|vbscript):[^"\']*("|\')/i', '$1="#"', $html) ?? '';
        $html = preg_replace('/<meta[^>]*>/i', '', $html) ?? '';
        $html = preg_replace('/<link[^>]*>/i', '', $html) ?? '';
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
