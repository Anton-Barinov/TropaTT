<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

final class KnowledgeBaseMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260613_000001_knowledge_base';
    }

    public function description(): string
    {
        return 'Add Knowledge Base spaces, pages, drafts, versions, links, templates and permissions';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $id = match ($driver) {
            'mysql' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'pgsql' => 'SERIAL PRIMARY KEY',
            'sqlsrv' => 'INT IDENTITY(1,1) PRIMARY KEY',
            default => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        };
        $dt = $driver === 'sqlsrv' ? 'DATETIME2' : 'DATETIME';
        $text = $driver === 'sqlsrv' ? 'NVARCHAR(MAX)' : 'TEXT';
        $bool = $driver === 'sqlsrv' ? 'BIT' : 'INTEGER';

        $collation = $driver === 'mysql' ? ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' : '';
        $tables = [
            "CREATE TABLE IF NOT EXISTS knowledge_spaces (id {$id}, public_id VARCHAR(64) UNIQUE, title VARCHAR(255) NOT NULL, slug VARCHAR(160) UNIQUE, description {$text} NULL, icon VARCHAR(64) NULL, color VARCHAR(32) NULL, owner_user_id INTEGER NULL, visibility VARCHAR(32) DEFAULT 'public', default_access_level VARCHAR(32) DEFAULT 'view', tree_version INTEGER DEFAULT 1, content_version INTEGER DEFAULT 1, permissions_version INTEGER DEFAULT 1, sort_order INTEGER DEFAULT 0, is_system {$bool} DEFAULT 0, is_archived {$bool} DEFAULT 0, row_version INTEGER DEFAULT 1, created_at {$dt}, updated_at {$dt}){$collation}",
            "CREATE TABLE IF NOT EXISTS knowledge_pages (id {$id}, public_id VARCHAR(64) UNIQUE, space_id INTEGER NOT NULL, parent_id INTEGER NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(190) NULL, page_type VARCHAR(64) DEFAULT 'article', status VARCHAR(32) DEFAULT 'draft', content_html {$text} NULL, content_text {$text} NULL, content_json {$text} NULL, excerpt {$text} NULL, owner_user_id INTEGER NULL, last_editor_user_id INTEGER NULL, published_by_user_id INTEGER NULL, published_at {$dt} NULL, review_due_at {$dt} NULL, reviewed_at {$dt} NULL, review_status VARCHAR(32) NULL, reviewer_user_id INTEGER NULL, sort_order INTEGER DEFAULT 0, path VARCHAR(2048) NULL, depth INTEGER DEFAULT 0, children_count INTEGER DEFAULT 0, comments_count INTEGER DEFAULT 0, attachments_count INTEGER DEFAULT 0, views_count INTEGER DEFAULT 0, likes_count INTEGER DEFAULT 0, row_version INTEGER DEFAULT 1, created_at {$dt}, updated_at {$dt}, deleted_at {$dt} NULL){$collation}",
            "CREATE TABLE IF NOT EXISTS knowledge_page_versions (id {$id}, public_id VARCHAR(64) UNIQUE, page_id INTEGER NOT NULL, version_number INTEGER NOT NULL, title VARCHAR(255), content_html {$text} NULL, content_text {$text} NULL, content_json {$text} NULL, change_summary {$text} NULL, created_by_user_id INTEGER NULL, created_at {$dt}){$collation}",
            "CREATE TABLE IF NOT EXISTS knowledge_drafts (id {$id}, public_id VARCHAR(64) UNIQUE, page_id INTEGER NOT NULL, user_id INTEGER NOT NULL, title VARCHAR(255), content_html {$text} NULL, content_text {$text} NULL, content_json {$text} NULL, base_row_version INTEGER DEFAULT 1, autosaved_at {$dt}, created_at {$dt}, updated_at {$dt}){$collation}",
            "CREATE TABLE IF NOT EXISTS knowledge_space_permissions (id {$id}, space_id INTEGER NOT NULL, subject_type VARCHAR(32), subject_id INTEGER NULL, access_level VARCHAR(32), created_by_user_id INTEGER NULL, created_at {$dt}){$collation}",
            "CREATE TABLE IF NOT EXISTS knowledge_page_permissions (id {$id}, page_id INTEGER NOT NULL, subject_type VARCHAR(32), subject_id INTEGER NULL, access_level VARCHAR(32), created_by_user_id INTEGER NULL, created_at {$dt}){$collation}",
            "CREATE TABLE IF NOT EXISTS knowledge_entity_links (id {$id}, public_id VARCHAR(64) UNIQUE, page_id INTEGER NOT NULL, entity_type VARCHAR(64), entity_public_id VARCHAR(64), relation_type VARCHAR(64) DEFAULT 'related', created_by_user_id INTEGER NULL, created_at {$dt}){$collation}",
            "CREATE TABLE IF NOT EXISTS knowledge_search_index (page_id INTEGER PRIMARY KEY, space_id INTEGER NOT NULL, title VARCHAR(255), content_text {$text} NULL, tags_text {$text} NULL, entity_text {$text} NULL, status VARCHAR(32), page_type VARCHAR(64), updated_at {$dt}){$collation}",
            "CREATE TABLE IF NOT EXISTS knowledge_templates (id {$id}, public_id VARCHAR(64) UNIQUE, title VARCHAR(255), page_type VARCHAR(64), description {$text} NULL, content_html {$text} NULL, content_json {$text} NULL, is_system {$bool} DEFAULT 0, is_active {$bool} DEFAULT 1, created_by_user_id INTEGER NULL, created_at {$dt}, updated_at {$dt}){$collation}",
            "CREATE TABLE IF NOT EXISTS knowledge_page_views (id {$id}, page_id INTEGER NOT NULL, user_id INTEGER NULL, source VARCHAR(32) DEFAULT 'direct', viewed_at {$dt}){$collation}",
            "CREATE TABLE IF NOT EXISTS knowledge_search_queries (id {$id}, query VARCHAR(255), user_id INTEGER NULL, results_count INTEGER DEFAULT 0, clicked_page_id INTEGER NULL, created_at {$dt}){$collation}",
            "CREATE TABLE IF NOT EXISTS knowledge_comments (id {$id}, public_id VARCHAR(64) UNIQUE, page_id INTEGER NOT NULL, parent_id INTEGER NULL, user_id INTEGER NOT NULL, body {$text} NOT NULL, resolved_at {$dt} NULL, created_at {$dt}, updated_at {$dt}){$collation}",
        ];

        foreach ($tables as $sql) {
            $pdo->exec($sql);
        }

        $this->createIndexes($pdo, $driver);
        $this->seedPermissions($pdo);
        $this->seedTemplates($pdo);
        $this->seedSpace($pdo);
    }

    private function createIndexes(PDO $pdo, string $driver): void
    {
        $indexes = [
            ['knowledge_spaces', 'idx_knowledge_spaces_archived_sort', 'is_archived, sort_order'],
            ['knowledge_spaces', 'idx_knowledge_spaces_owner', 'owner_user_id'],
            ['knowledge_pages', 'idx_knowledge_pages_space_parent_sort', 'space_id, parent_id, sort_order'],
            ['knowledge_pages', 'idx_knowledge_pages_space_status_updated', 'space_id, status, updated_at'],
            ['knowledge_pages', 'idx_knowledge_pages_parent', 'parent_id'],
            ['knowledge_pages', 'idx_knowledge_pages_owner', 'owner_user_id'],
            ['knowledge_pages', 'idx_knowledge_pages_review_due', 'review_due_at'],
            ['knowledge_pages', 'idx_knowledge_pages_type', 'page_type'],
            ['knowledge_drafts', 'uq_knowledge_drafts_page_user', 'page_id, user_id', true],
            ['knowledge_page_versions', 'uq_knowledge_versions_page_number', 'page_id, version_number', true],
            ['knowledge_entity_links', 'idx_knowledge_links_entity', 'entity_type, entity_public_id'],
            ['knowledge_search_index', 'idx_knowledge_search_space_status_updated', 'space_id, status, updated_at'],
            ['knowledge_search_index', 'idx_knowledge_search_page_type', 'page_type'],
            ['knowledge_templates', 'idx_knowledge_templates_type_active', 'page_type, is_active'],
            ['knowledge_page_views', 'idx_knowledge_views_page', 'page_id, viewed_at'],
            ['knowledge_comments', 'idx_knowledge_comments_page', 'page_id, created_at'],
            ['knowledge_comments', 'idx_knowledge_comments_parent', 'parent_id'],
        ];

        foreach ($indexes as $index) {
            [$table, $name, $columns] = $index;
            $unique = (bool)($index[3] ?? false);
            if ($this->indexExists($pdo, $driver, $table, $name)) {
                continue;
            }
            try {
                $pdo->exec(sprintf('CREATE %s INDEX %s ON %s(%s)', $unique ? 'UNIQUE' : '', $name, $table, $columns));
            } catch (\Throwable) {
                // Keep migration portable across existing local/test databases.
            }
        }

        if ($driver === 'mysql' && !$this->indexExists($pdo, $driver, 'knowledge_pages', 'ft_knowledge_pages_title_text')) {
            try {
                $pdo->exec('CREATE FULLTEXT INDEX ft_knowledge_pages_title_text ON knowledge_pages(title, content_text)');
            } catch (\Throwable) {
            }
        }
    }

    private function seedPermissions(PDO $pdo): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $permissions = [
            'knowledge.view' => 'Knowledge: view published pages',
            'knowledge.create' => 'Knowledge: create pages',
            'knowledge.edit' => 'Knowledge: edit pages',
            'knowledge.publish' => 'Knowledge: publish pages',
            'knowledge.delete' => 'Knowledge: delete/archive pages',
            'knowledge.comment' => 'Knowledge: comment pages',
            'knowledge.manage' => 'Knowledge: manage spaces',
            'knowledge.admin' => 'Knowledge: administration',
            'knowledge.template_manage' => 'Knowledge: manage templates',
            'knowledge.permission_manage' => 'Knowledge: manage permissions',
            'knowledge.analytics_view' => 'Knowledge: analytics',
            'knowledge.export' => 'Knowledge: export',
            'knowledge.import' => 'Knowledge: import',
        ];
        $stmt = $pdo->prepare('INSERT INTO permissions (public_id, code, title, created_at) VALUES (:public_id, :code, :title, :created_at)');
        foreach ($permissions as $code => $title) {
            $exists = $pdo->prepare('SELECT id FROM permissions WHERE code = :code');
            $exists->execute(['code' => $code]);
            if ($exists->fetchColumn() !== false) {
                continue;
            }
            $stmt->execute([
                'public_id' => 'perm_' . strtoupper(bin2hex(random_bytes(8))),
                'code' => $code,
                'title' => $title,
                'created_at' => $now,
            ]);
        }

        $adminRoleId = $this->fetchRoleId($pdo, 'admin');
        if ($adminRoleId > 0) {
            foreach (array_keys($permissions) as $code) {
                $permissionId = $this->fetchPermissionId($pdo, $code);
                if ($permissionId <= 0) {
                    continue;
                }
                $exists = $pdo->prepare('SELECT id FROM role_permissions WHERE role_id = :role_id AND permission_id = :permission_id');
                $exists->execute(['role_id' => $adminRoleId, 'permission_id' => $permissionId]);
                if ($exists->fetchColumn() !== false) {
                    continue;
                }
                $insert = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (:role_id, :permission_id, :created_at)');
                $insert->execute(['role_id' => $adminRoleId, 'permission_id' => $permissionId, 'created_at' => $now]);
            }
        }
    }

    private function seedTemplates(PDO $pdo): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $templates = [
            ['Регламент', 'regulation', '<h2>Цель</h2><p></p><h2>Правила</h2><ul><li></li></ul><h2>Ответственные</h2><p></p>'],
            ['Инструкция', 'instruction', '<h2>Когда использовать</h2><p></p><h2>Порядок действий</h2><ol><li></li></ol><h2>Проверка результата</h2><p></p>'],
            ['FAQ', 'faq', '<h2>Вопрос</h2><p></p><h2>Ответ</h2><p></p>'],
            ['Чеклист', 'checklist', '<h2>Перед началом</h2><ul><li>[ ] </li></ul><h2>Готово, когда</h2><ul><li>[ ] </li></ul>'],
            ['Runbook', 'runbook', '<h2>Симптомы</h2><p></p><h2>Диагностика</h2><ol><li></li></ol><h2>Восстановление</h2><ol><li></li></ol>'],
            ['Протокол встречи', 'meeting_note', '<h2>Участники</h2><p></p><h2>Решения</h2><ul><li></li></ul><h2>Следующие шаги</h2><ul><li></li></ul>'],
        ];
        $stmt = $pdo->prepare('INSERT INTO knowledge_templates (public_id, title, page_type, description, content_html, content_json, is_system, is_active, created_at, updated_at) VALUES (:public_id, :title, :page_type, :description, :content_html, :content_json, 1, 1, :created_at, :updated_at)');
        foreach ($templates as [$title, $type, $html]) {
            $exists = $pdo->prepare('SELECT id FROM knowledge_templates WHERE title = :title AND page_type = :page_type');
            $exists->execute(['title' => $title, 'page_type' => $type]);
            if ($exists->fetchColumn() !== false) {
                continue;
            }
            $stmt->execute([
                'public_id' => 'kbt_' . strtoupper(bin2hex(random_bytes(8))),
                'title' => $title,
                'page_type' => $type,
                'description' => 'Системный шаблон: ' . $title,
                'content_html' => $html,
                'content_json' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedSpace(PDO $pdo): void
    {
        $exists = $pdo->query("SELECT id FROM knowledge_spaces WHERE slug = 'general'")->fetchColumn();
        if ($exists !== false) {
            return;
        }
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $pdo->prepare("INSERT INTO knowledge_spaces (public_id, title, slug, description, icon, color, visibility, default_access_level, sort_order, is_system, created_at, updated_at) VALUES (:public_id, 'Общие материалы', 'general', 'Стартовый раздел базы знаний: инструкции, FAQ и регламенты.', 'book-open', '#0f8f72', 'public', 'view', 10, 1, :created_at, :updated_at)");
        $stmt->execute([
            'public_id' => 'kbs_' . strtoupper(bin2hex(random_bytes(8))),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function fetchRoleId(PDO $pdo, string $code): int
    {
        try {
            $stmt = $pdo->prepare('SELECT id FROM roles WHERE code = :code LIMIT 1');
            $stmt->execute(['code' => $code]);
            return (int)($stmt->fetchColumn() ?: 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function fetchPermissionId(PDO $pdo, string $code): int
    {
        $stmt = $pdo->prepare('SELECT id FROM permissions WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    private function indexExists(PDO $pdo, string $driver, string $table, string $index): bool
    {
        try {
            if ($driver === 'mysql') {
                $stmt = $pdo->prepare('SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index LIMIT 1');
                $stmt->execute(['table' => $table, 'index' => $index]);
                return $stmt->fetchColumn() !== false;
            }
            if ($driver === 'pgsql') {
                $stmt = $pdo->prepare('SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND tablename = :table AND indexname = :index LIMIT 1');
                $stmt->execute(['table' => $table, 'index' => $index]);
                return $stmt->fetchColumn() !== false;
            }
            $rows = $pdo->query('PRAGMA index_list(' . $table . ')')->fetchAll() ?: [];
            foreach ($rows as $row) {
                if ((string)($row['name'] ?? '') === $index) {
                    return true;
                }
            }
        } catch (\Throwable) {
            return false;
        }
        return false;
    }
}
