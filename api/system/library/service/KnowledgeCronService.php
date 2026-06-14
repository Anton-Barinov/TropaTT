<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use PDO;

final class KnowledgeCronService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?NotificationService $notifications = null,
    ) {
    }

    /**
     * Scan published pages for freshness.
     * Marks pages as needs_update when review_due_at is past.
     * Sends notifications for pages that became overdue today.
     * @return array{checked: int, marked: int, notified: int}
     */
    public function freshnessScan(): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $todayStart = gmdate('Y-m-d 00:00:00');
        $todayEnd = gmdate('Y-m-d 23:59:59');

        // Find overdue pages
        $stmt = $this->pdo->prepare("
            SELECT p.id, p.public_id, p.title, p.review_due_at, p.owner_user_id
            FROM knowledge_pages p
            WHERE p.deleted_at IS NULL
              AND p.status = 'published'
              AND p.review_due_at IS NOT NULL
              AND p.review_due_at < :now
              AND (p.review_status IS NULL OR p.review_status != 'overdue')
        ");
        $stmt->execute(['now' => $now]);
        $overdue = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $checked = count($overdue);
        $marked = 0;
        $notified = 0;

        foreach ($overdue as $page) {
            // Mark as needs_update/overdue
            $updateStmt = $this->pdo->prepare("
                UPDATE knowledge_pages
                SET status = 'needs_update', review_status = 'overdue', row_version = row_version + 1, updated_at = :now
                WHERE id = :id
            ");
            $updateStmt->execute([
                'now' => $now,
                'id' => (int)$page['id'],
            ]);
            $marked++;

            // Notify owner if notification service is available
            if ($this->notifications !== null && !empty($page['owner_user_id'])) {
                $dueDate = (string)($page['review_due_at'] ?? '');
                $this->notifications->notifyUsers(
                    [(int)$page['owner_user_id']],
                    [
                        'category' => 'knowledge',
                        'title' => 'Review due: ' . ($page['title'] ?? ''),
                        'body' => 'Page "' . ($page['title'] ?? '') . '" requires a review. Due date: ' . $dueDate,
                    'entity_type' => 'knowledge_page',
                        'entity_public_id' => (string)($page['public_id'] ?? ''),
                        'action_code' => 'knowledge_review_due',
                        'link' => 'index.php?route=knowledge-page&id=' . urlencode((string)($page['public_id'] ?? '')),
                    ]
                );
                $notified++;
            }
        }

        return [
            'checked' => $checked,
            'marked' => $marked,
            'notified' => $notified,
        ];
    }

    /**
     * Clean up old drafts that haven't been updated in N days.
     * @param int $olderThanDays Delete drafts older than this many days without update
     * @return array{deleted: int}
     */
    public function draftsCleanup(int $olderThanDays = 30): array
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($olderThanDays * 86400));
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM knowledge_drafts WHERE updated_at < :cutoff
        ");
        $stmt->execute(['cutoff' => $cutoff]);
        $count = (int)$stmt->fetchColumn();

        $deleteStmt = $this->pdo->prepare("
            DELETE FROM knowledge_drafts WHERE updated_at < :cutoff
        ");
        $deleteStmt->execute(['cutoff' => $cutoff]);

        return ['deleted' => $count];
    }

    /**
     * Clean up old page versions beyond the retention limit.
     * @param int $keepLatest Keep this many latest versions per page
     * @return array{deleted: int}
     */
    public function versionsCleanup(int $keepLatest = 50): array
    {
        $deleted = 0;
        $pages = $this->pdo->query("SELECT id FROM knowledge_pages WHERE deleted_at IS NULL")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($pages as $pageId) {
            $stmt = $this->pdo->prepare("
                SELECT id FROM knowledge_page_versions
                WHERE page_id = :page_id
                ORDER BY version_number DESC
                LIMIT 1 OFFSET :keep
            ");
            $stmt->bindValue(':page_id', (int)$pageId, PDO::PARAM_INT);
            $stmt->bindValue(':keep', $keepLatest, PDO::PARAM_INT);
            $stmt->execute();
            $toDelete = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($toDelete)) {
                $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
                $delStmt = $this->pdo->prepare("DELETE FROM knowledge_page_versions WHERE id IN ({$placeholders})");
                $delStmt->execute(array_map('intval', $toDelete));
                $deleted += $delStmt->rowCount();
            }
        }

        return ['deleted' => $deleted];
    }

    /**
     * Rebuild the search index for all published pages.
     * @return array{reindexed: int}
     */
    public function reindexSearch(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.id, p.public_id, p.space_id, p.title, p.content_text, p.status, p.page_type, p.updated_at
            FROM knowledge_pages p
            WHERE p.deleted_at IS NULL AND p.status = 'published'
        ");
        $stmt->execute();
        $pages = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $insertStmt = $this->pdo->prepare("
            INSERT INTO knowledge_search_index (page_id, space_id, title, content_text, status, page_type, updated_at)
            VALUES (:page_id, :space_id, :title, :content_text, :status, :page_type, :updated_at)
            ON DUPLICATE KEY UPDATE
                space_id = VALUES(space_id),
                title = VALUES(title),
                content_text = VALUES(content_text),
                status = VALUES(status),
                page_type = VALUES(page_type),
                updated_at = VALUES(updated_at)
        ");

        $count = 0;
        foreach ($pages as $page) {
            $insertStmt->execute([
                'page_id' => (int)$page['id'],
                'space_id' => (int)$page['space_id'],
                'title' => (string)$page['title'],
                'content_text' => (string)$page['content_text'],
                'status' => (string)$page['status'],
                'page_type' => (string)$page['page_type'],
                'updated_at' => (string)$page['updated_at'],
            ]);
            $count++;
        }

        return ['reindexed' => $count];
    }
}
