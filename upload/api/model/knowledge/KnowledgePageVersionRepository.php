<?php
declare(strict_types=1);

namespace Api\Model\Knowledge;

use PDO;

final class KnowledgePageVersionRepository
{
    private const ALLOWED_SORT = ['version_number', 'created_at', 'change_type'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(array $payload): array
    {
        $publicId = $payload['public_id'] ?? $this->publicId();
        $now = gmdate('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare('INSERT INTO knowledge_page_versions (
            public_id, page_id, page_public_id, version_number,
            title, content, content_text, summary,
            visibility, status,
            tags_json, links_json, meta_json,
            change_type, change_note,
            restored_from_version_number, restored_from_version_public_id,
            created_by_user_id, created_by_actor_type, created_by_display_name,
            request_id, source_type, source_ref,
            content_hash,
            created_at, deleted_at
        ) VALUES (
            :public_id, :page_id, :page_public_id, :version_number,
            :title, :content, :content_text, :summary,
            :visibility, :status,
            :tags_json, :links_json, :meta_json,
            :change_type, :change_note,
            :restored_from_version_number, :restored_from_version_public_id,
            :created_by_user_id, :created_by_actor_type, :created_by_display_name,
            :request_id, :source_type, :source_ref,
            :content_hash,
            :created_at, :deleted_at
        )');

        $stmt->execute([
            'public_id' => $publicId,
            'page_id' => (int)($payload['page_id'] ?? 0),
            'page_public_id' => (string)($payload['page_public_id'] ?? ''),
            'version_number' => (int)($payload['version_number'] ?? 1),
            'title' => (string)($payload['title'] ?? ''),
            'content' => $payload['content'] ?? null,
            'content_text' => $payload['content_text'] ?? null,
            'summary' => $payload['summary'] ?? null,
            'visibility' => $payload['visibility'] ?? null,
            'status' => $payload['status'] ?? null,
            'tags_json' => isset($payload['tags_json']) ? (is_string($payload['tags_json']) ? $payload['tags_json'] : json_encode($payload['tags_json'], JSON_UNESCAPED_UNICODE)) : null,
            'links_json' => isset($payload['links_json']) ? (is_string($payload['links_json']) ? $payload['links_json'] : json_encode($payload['links_json'], JSON_UNESCAPED_UNICODE)) : null,
            'meta_json' => isset($payload['meta_json']) ? (is_string($payload['meta_json']) ? $payload['meta_json'] : json_encode($payload['meta_json'], JSON_UNESCAPED_UNICODE)) : null,
            'change_type' => (string)($payload['change_type'] ?? 'update'),
            'change_note' => $payload['change_note'] ?? null,
            'restored_from_version_number' => $payload['restored_from_version_number'] ?? null,
            'restored_from_version_public_id' => $payload['restored_from_version_public_id'] ?? null,
            'created_by_user_id' => $payload['created_by_user_id'] ?? null,
            'created_by_actor_type' => (string)($payload['created_by_actor_type'] ?? 'user'),
            'created_by_display_name' => $payload['created_by_display_name'] ?? null,
            'request_id' => $payload['request_id'] ?? null,
            'source_type' => $payload['source_type'] ?? null,
            'source_ref' => $payload['source_ref'] ?? null,
            'content_hash' => $payload['content_hash'] ?? null,
            'created_at' => $now,
            'deleted_at' => null,
        ]);

        return $this->findByPublicId($publicId) ?? [];
    }

    public function listByPageId(int $pageId, array $filters = []): array
    {
        $where = ['kpv.page_id = :page_id', 'kpv.deleted_at IS NULL'];
        $params = ['page_id' => $pageId];
        $limit = min(100, max(1, (int)($filters['limit'] ?? 30)));
        $page = max(1, (int)($filters['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        $sort = in_array((string)($filters['sort'] ?? ''), self::ALLOWED_SORT, true) ? (string)$filters['sort'] : 'version_number';
        $order = strtoupper((string)($filters['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        if (!empty($filters['change_type'])) {
            $where[] = 'kpv.change_type = :change_type';
            $params['change_type'] = (string)$filters['change_type'];
        }
        if (!empty($filters['created_by_user_id'])) {
            $where[] = 'kpv.created_by_user_id = :created_by_user_id';
            $params['created_by_user_id'] = (int)$filters['created_by_user_id'];
        }
        if (!empty($filters['created_from'])) {
            $where[] = 'kpv.created_at >= :created_from';
            $params['created_from'] = (string)$filters['created_from'];
        }
        if (!empty($filters['created_to'])) {
            $where[] = 'kpv.created_at <= :created_to';
            $params['created_to'] = (string)$filters['created_to'];
        }

        // Count total
        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM knowledge_page_versions kpv WHERE ' . implode(' AND ', $where));
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Fetch items (without LONGTEXT content for performance)
        $selectFields = 'kpv.id, kpv.public_id, kpv.page_public_id, kpv.version_number, kpv.title, kpv.summary, kpv.change_type, kpv.change_note, kpv.created_by_user_id, kpv.created_by_display_name, kpv.content_hash, kpv.created_at';
        $stmt = $this->pdo->prepare("SELECT {$selectFields} FROM knowledge_page_versions kpv WHERE " . implode(' AND ', $where) . " ORDER BY kpv.{$sort} {$order} LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => $total > 0 ? (int)ceil($total / $limit) : 0,
        ];
    }

    public function findByPublicId(string $versionPublicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, public_id, page_id, page_public_id, version_number, title, content, content_text, summary, visibility, status, tags_json, links_json, meta_json, change_type, change_note, created_by_user_id, created_by_display_name, content_hash, created_at FROM knowledge_page_versions WHERE public_id = :public_id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['public_id' => $versionPublicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function findByPageAndNumber(int $pageId, int $versionNumber): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, public_id, page_id, page_public_id, version_number, title, content, content_text, summary, visibility, status, tags_json, links_json, meta_json, change_type, change_note, created_by_user_id, created_by_display_name, content_hash, created_at FROM knowledge_page_versions WHERE page_id = :page_id AND version_number = :version_number AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['page_id' => $pageId, 'version_number' => $versionNumber]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function latestByPageId(int $pageId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, public_id, page_id, page_public_id, version_number, title, content, content_text, summary, visibility, status, tags_json, links_json, meta_json, change_type, change_note, created_by_user_id, created_by_display_name, content_hash, created_at FROM knowledge_page_versions WHERE page_id = :page_id AND deleted_at IS NULL ORDER BY version_number DESC LIMIT 1');
        $stmt->execute(['page_id' => $pageId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function nextVersionNumberForPageId(int $pageId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(version_number), 0) + 1 FROM knowledge_page_versions WHERE page_id = :page_id');
        $stmt->execute(['page_id' => $pageId]);
        return (int)$stmt->fetchColumn();
    }

    public function softDeleteByPublicId(string $versionPublicId, string $deletedAt): bool
    {
        $stmt = $this->pdo->prepare('UPDATE knowledge_page_versions SET deleted_at = :deleted_at WHERE public_id = :public_id');
        $stmt->execute(['deleted_at' => $deletedAt, 'public_id' => $versionPublicId]);
        return $stmt->rowCount() > 0;
    }

    public function pageIdByPublicId(string $pagePublicId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM knowledge_pages WHERE public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $pagePublicId]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (int)$val : null;
    }

    /**
     * Canonical snapshot of a page row for version hashing.
     *
     * Both the version service and the page create/update path
     * (`KnowledgeRepository`) build their snapshots here, so a hash produced by
     * one path is comparable with a hash produced by the other — without that,
     * duplicate detection silently stops working for mixed history.
     *
     * @param array<string,mixed> $page
     * @return array<string,mixed>
     */
    public static function buildSnapshot(array $page): array
    {
        return [
            'title' => (string)($page['title'] ?? ''),
            'content' => $page['content_html'] ?? '',
            'content_text' => $page['content_text'] ?? '',
            'summary' => $page['excerpt'] ?? '',
            'visibility' => $page['visibility'] ?? null,
            'status' => $page['status'] ?? null,
            'tags' => $page['tags_json'] ?? null,
            'links' => $page['links_json'] ?? null,
            'meta' => $page['meta_json'] ?? null,
        ];
    }

    /**
     * SHA-256 of a snapshot, used to detect "saved without changes".
     *
     * @param array<string,mixed> $snapshot
     */
    public static function hashSnapshot(array $snapshot): string
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

    /**
     * Record a version snapshot for a page directly from its stored row.
     *
     * This is the path used when a page is created or updated through
     * `KnowledgeRepository`. It used to insert the version by hand: the snapshot
     * carried no `content_hash` and the page's `last_version_number` was never
     * advanced. As a result the counter claimed "no versions" while versions
     * existed, and saving a page without changing anything kept piling up
     * identical snapshots (whereas the version service correctly rejects them).
     *
     * @return array|string|null version row, or 'KNOWLEDGE_PAGE_VERSION_DUPLICATE_CONTENT'
     *                           when the page content did not change
     */
    public function recordSnapshot(
        string $pagePublicId,
        ?int $actorId,
        string $changeNote,
        string $changeType = 'update'
    ): array|string|null {
        $page = $this->getPage($pagePublicId);
        if ($page === null) {
            return null;
        }

        $snapshot = self::buildSnapshot($page);
        $contentHash = self::hashSnapshot($snapshot);

        $latest = $this->latestByPageId((int)$page['id']);
        if ($latest !== null && (string)($latest['content_hash'] ?? '') === $contentHash) {
            return 'KNOWLEDGE_PAGE_VERSION_DUPLICATE_CONTENT';
        }

        $nextVersion = $this->nextVersionNumberForPageId((int)$page['id']);

        $version = $this->create([
            'page_id' => (int)$page['id'],
            'page_public_id' => (string)$page['public_id'],
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
            'content_hash' => $contentHash,
        ]);

        // Keep the page counter in sync with the version that was just written.
        $this->updatePageLock($pagePublicId, ['last_version_number' => $nextVersion]);

        return $version;
    }

    public function updatePageLock(string $pagePublicId, array $set): bool
    {
        $setParts = [];
        $params = ['page_public_id' => $pagePublicId];
        foreach ($set as $column => $value) {
            $setParts[] = "{$column} = :{$column}";
            $params[$column] = $value;
        }
        $setParts[] = 'row_version = row_version + 1';
        $sql = 'UPDATE knowledge_pages SET ' . implode(', ', $setParts) . ' WHERE public_id = :page_public_id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function incrementPageRowVersion(string $pagePublicId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE knowledge_pages SET row_version = row_version + 1 WHERE public_id = :public_id');
        $stmt->execute(['public_id' => $pagePublicId]);
        return $stmt->rowCount() > 0;
    }

    public function getPage(string $pagePublicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, public_id, space_id, parent_id, title, slug, page_type, status, content_html, content_text, content_json, excerpt, owner_user_id, last_editor_user_id, sort_order, path, depth, source_type, source_id, source_url, source_payload_json, locked_at, locked_by_user_id, lock_reason, row_version, last_version_number, created_at, updated_at FROM knowledge_pages WHERE public_id = :public_id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['public_id' => $pagePublicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function updatePage(string $pagePublicId, array $set): ?array
    {
        $setParts = [];
        $params = ['page_public_id' => $pagePublicId];
        foreach ($set as $column => $value) {
            $setParts[] = "{$column} = :{$column}";
            $params[$column] = $value;
        }
        $sql = 'UPDATE knowledge_pages SET ' . implode(', ', $setParts) . ' WHERE public_id = :page_public_id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $this->getPage($pagePublicId);
    }

    public function userById(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, public_id, full_name, login FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function publicId(): string
    {
        return 'kpv_' . bin2hex(random_bytes(16));
    }
}
