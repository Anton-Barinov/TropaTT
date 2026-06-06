<?php
declare(strict_types=1);

namespace Api\System\Library\Database;

use PDO;

final class SchemaManager
{
    private function migrateChatsTable(PDO $pdo): void
    {
        $columns = ['archived_at', 'archived_by_user_id', 'archived_participant_ids'];
        foreach ($columns as $col) {
            try {
                $type = $col === 'archived_participant_ids' ? 'TEXT' : 'DATETIME';
                if ($col === 'archived_by_user_id') $type = 'INTEGER';
                $pdo->exec("ALTER TABLE chats ADD COLUMN {$col} {$type} NULL");
            } catch (\Throwable $e) {
                // column already exists — skip
            }
        }
    }

    public function createSchema(PDO $pdo, string $driver): void
    {
        $id = match ($driver) {
            'mysql' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'pgsql' => 'SERIAL PRIMARY KEY',
            'sqlsrv' => 'INT IDENTITY(1,1) PRIMARY KEY',
            default => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        };

        $bool = $driver === 'sqlsrv' ? 'BIT' : 'INTEGER';
        $text = $driver === 'sqlsrv' ? 'NVARCHAR(MAX)' : 'TEXT';
        $dt = $driver === 'sqlsrv' ? 'DATETIME2' : 'DATETIME';

        $tables = [
            "CREATE TABLE IF NOT EXISTS install_state (id {$id}, installed_at {$dt}, version VARCHAR(20), payload {$text})",
            "CREATE TABLE IF NOT EXISTS roles (id {$id}, public_id VARCHAR(64) UNIQUE, code VARCHAR(64) UNIQUE, title VARCHAR(255), is_system {$bool} DEFAULT 0, created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS permissions (id {$id}, public_id VARCHAR(64) UNIQUE, code VARCHAR(128) UNIQUE, title VARCHAR(255), created_at {$dt})",
            "CREATE TABLE IF NOT EXISTS role_permissions (id {$id}, role_id INTEGER, permission_id INTEGER, created_at {$dt})",
            "CREATE TABLE IF NOT EXISTS users (id {$id}, public_id VARCHAR(64) UNIQUE, login VARCHAR(120) UNIQUE, email VARCHAR(190), password_hash VARCHAR(255), auth_token_hash VARCHAR(255), full_name VARCHAR(255), locale VARCHAR(16), is_active {$bool} DEFAULT 1, is_root {$bool} DEFAULT 0, created_by_user_id INTEGER NULL, created_at {$dt}, updated_at {$dt}, deleted_at {$dt} NULL)",
            "CREATE TABLE IF NOT EXISTS user_roles (id {$id}, user_id INTEGER, role_id INTEGER, created_at {$dt})",
            "CREATE TABLE IF NOT EXISTS user_sessions (id {$id}, public_id VARCHAR(64) UNIQUE, user_id INTEGER, token_hash VARCHAR(255), ip VARCHAR(128), user_agent {$text}, device_fingerprint VARCHAR(64) NULL, device_name VARCHAR(190) NULL, expires_at {$dt}, revoked_at {$dt} NULL, created_at {$dt})",
            "CREATE TABLE IF NOT EXISTS api_clients (id {$id}, public_id VARCHAR(64) UNIQUE, title VARCHAR(255), scopes {$text}, is_active {$bool} DEFAULT 1, created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS api_keys (id {$id}, public_id VARCHAR(64) UNIQUE, client_id INTEGER, user_id INTEGER NULL, key_hash VARCHAR(255), scopes {$text}, expires_at {$dt} NULL, revoked_at {$dt} NULL, created_at {$dt})",
            "CREATE TABLE IF NOT EXISTS projects (id {$id}, public_id VARCHAR(64) UNIQUE, title VARCHAR(255), description {$text}, status_code VARCHAR(64), priority_code VARCHAR(64), client_public_id VARCHAR(64) NULL, manager_user_id INTEGER NULL, team_public_id VARCHAR(64) NULL, archived_at {$dt} NULL, created_by_user_id INTEGER, created_at {$dt}, updated_at {$dt}, row_version INTEGER DEFAULT 1)",
            "CREATE TABLE IF NOT EXISTS tasks (id {$id}, public_id VARCHAR(64) UNIQUE, project_id INTEGER NULL, parent_task_id INTEGER NULL, title VARCHAR(255), description {$text}, status_code VARCHAR(64), priority_code VARCHAR(64), sla_breached {$bool} DEFAULT 0, due_at {$dt} NULL, start_at {$dt} NULL, end_at {$dt} NULL, assignee_user_id INTEGER NULL, creator_user_id INTEGER, archived_at {$dt} NULL, deleted_at {$dt} NULL, created_at {$dt}, updated_at {$dt}, row_version INTEGER DEFAULT 1)",
            "CREATE TABLE IF NOT EXISTS task_relations (id {$id}, public_id VARCHAR(64) UNIQUE, parent_task_id INTEGER NULL, child_task_id INTEGER NULL, relation_type VARCHAR(32) DEFAULT 'subtask', sort_order INTEGER DEFAULT 0, legacy_subtask_public_id VARCHAR(64) NULL, created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS task_assignees (id {$id}, task_id INTEGER, user_id INTEGER, created_at {$dt})",
            "CREATE TABLE IF NOT EXISTS task_watchers (id {$id}, task_id INTEGER, user_id INTEGER, created_at {$dt})",
            "CREATE TABLE IF NOT EXISTS task_status_history (id {$id}, public_id VARCHAR(64) UNIQUE, task_id INTEGER, old_status VARCHAR(64), new_status VARCHAR(64), changed_by_user_id INTEGER, created_at {$dt})",
            "CREATE TABLE IF NOT EXISTS comments (id {$id}, public_id VARCHAR(64) UNIQUE, task_id INTEGER, project_id INTEGER NULL, author_user_id INTEGER, body {$text}, visibility VARCHAR(32) DEFAULT 'internal', created_at {$dt}, updated_at {$dt}, deleted_at {$dt} NULL)",
            "CREATE TABLE IF NOT EXISTS comment_drafts (id {$id}, public_id VARCHAR(64) UNIQUE, user_id INTEGER, task_id INTEGER, body {$text}, created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS files (id {$id}, public_id VARCHAR(64) UNIQUE, entity_type VARCHAR(32), entity_public_id VARCHAR(64), uploader_user_id INTEGER, original_name VARCHAR(255), storage_path {$text}, mime_type VARCHAR(128), size_bytes BIGINT, is_deleted {$bool} DEFAULT 0, created_at {$dt}, deleted_at {$dt} NULL)",
            "CREATE TABLE IF NOT EXISTS statuses (id {$id}, public_id VARCHAR(64) UNIQUE, scope VARCHAR(64), code VARCHAR(64), title VARCHAR(255), color VARCHAR(32), sort_order INTEGER, is_active {$bool} DEFAULT 1, created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS priorities (id {$id}, public_id VARCHAR(64) UNIQUE, code VARCHAR(64), title VARCHAR(255), weight INTEGER, color VARCHAR(32), created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS tags (id {$id}, public_id VARCHAR(64) UNIQUE, code VARCHAR(64), title VARCHAR(255), color VARCHAR(32), created_at {$dt})",
            "CREATE TABLE IF NOT EXISTS entity_tags (id {$id}, entity_type VARCHAR(32), entity_public_id VARCHAR(64), tag_id INTEGER, created_at {$dt})",
            "CREATE TABLE IF NOT EXISTS notifications (id {$id}, public_id VARCHAR(64) UNIQUE, user_id INTEGER, category VARCHAR(64), title VARCHAR(255), body {$text}, entity_type VARCHAR(64) NULL, entity_public_id VARCHAR(64) NULL, action_code VARCHAR(64) NULL, actor_user_id INTEGER NULL, actor_public_id VARCHAR(64) NULL, actor_name VARCHAR(255) NULL, link VARCHAR(1024) NULL, payload_json {$text} NULL, is_read {$bool} DEFAULT 0, created_at {$dt}, read_at {$dt} NULL)",
            "CREATE TABLE IF NOT EXISTS chats (id {$id}, public_id VARCHAR(64) UNIQUE, title VARCHAR(255) NULL, type VARCHAR(32) DEFAULT 'direct', project_id INTEGER NULL, team_id INTEGER NULL, last_message_at {$dt} NULL, created_by_user_id INTEGER NULL, archived_at {$dt} NULL, archived_by_user_id INTEGER NULL, archived_participant_ids {$text} NULL, created_at {$dt}, updated_at {$dt} NULL)",
            "CREATE TABLE IF NOT EXISTS chat_participants (id {$id}, chat_id INTEGER NOT NULL, user_id INTEGER NOT NULL, role VARCHAR(32) DEFAULT 'member', is_favorite {$bool} DEFAULT 0, muted_until {$dt} NULL, joined_at {$dt} NULL, UNIQUE KEY uq_chat_participant (chat_id, user_id))",
            "CREATE TABLE IF NOT EXISTS chat_messages (id {$id}, public_id VARCHAR(64) UNIQUE, chat_id INTEGER NOT NULL, sender_user_id INTEGER NOT NULL, reply_to_message_id INTEGER NULL, message_type VARCHAR(32) DEFAULT 'text', text {$text}, created_at {$dt}, edited_at {$dt} NULL, deleted_at {$dt} NULL, deleted_by_user_id INTEGER NULL)",
            "CREATE TABLE IF NOT EXISTS chat_message_audit_logs (id {$id}, public_id VARCHAR(64) UNIQUE, message_id INTEGER NOT NULL, chat_id INTEGER NOT NULL, actor_user_id INTEGER NOT NULL, action VARCHAR(32), before_text {$text} NULL, after_text {$text} NULL, created_at {$dt})",
            "CREATE TABLE IF NOT EXISTS chat_read_markers (id {$id}, chat_id INTEGER NOT NULL, user_id INTEGER NOT NULL, last_read_message_id INTEGER DEFAULT 0, updated_at {$dt} NULL, UNIQUE KEY uq_chat_read (chat_id, user_id))",
            "CREATE TABLE IF NOT EXISTS notification_push_subscriptions (id {$id}, public_id VARCHAR(64) UNIQUE, user_id INTEGER, endpoint {$text}, p256dh VARCHAR(1024), auth VARCHAR(1024), user_agent {$text} NULL, device_label VARCHAR(255) NULL, is_active {$bool} DEFAULT 1, last_error {$text} NULL, last_seen_at {$dt} NULL, created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS reminders (id {$id}, public_id VARCHAR(64) UNIQUE, user_id INTEGER, task_id INTEGER NULL, remind_at {$dt}, status VARCHAR(32), created_at {$dt})",
            "CREATE TABLE IF NOT EXISTS calendar_events (id {$id}, public_id VARCHAR(64) UNIQUE, title VARCHAR(255), description {$text} NULL, starts_at {$dt}, ends_at {$dt}, owner_user_id INTEGER, project_id INTEGER NULL, task_id INTEGER NULL, created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS work_logs (id {$id}, public_id VARCHAR(64) UNIQUE, user_id INTEGER, task_id INTEGER, minutes_spent INTEGER, note {$text}, logged_at {$dt}, created_at {$dt})",
            "CREATE TABLE IF NOT EXISTS settings (id {$id}, public_id VARCHAR(64) UNIQUE, scope VARCHAR(64), name VARCHAR(190), value {$text}, created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS request_logs (id {$id}, public_id VARCHAR(64) UNIQUE, request_id VARCHAR(64), correlation_id VARCHAR(64), user_public_id VARCHAR(64) NULL, route VARCHAR(255), method VARCHAR(16), status_code INTEGER, result_code VARCHAR(64), duration_ms INTEGER, payload {$text}, created_at {$dt})",
            "CREATE TABLE IF NOT EXISTS audit_logs (id {$id}, public_id VARCHAR(64) UNIQUE, actor_public_id VARCHAR(64), entity_type VARCHAR(64), entity_public_id VARCHAR(64), action VARCHAR(64), details {$text}, created_at {$dt})",
            "CREATE TABLE IF NOT EXISTS security_logs (id {$id}, public_id VARCHAR(64) UNIQUE, actor_public_id VARCHAR(64) NULL, event_type VARCHAR(64), ip VARCHAR(128), user_agent {$text}, details {$text}, created_at {$dt})",
            "CREATE TABLE IF NOT EXISTS activity_feed (id {$id}, public_id VARCHAR(64) UNIQUE, entity_type VARCHAR(64), entity_public_id VARCHAR(64), action VARCHAR(64), actor_public_id VARCHAR(64), payload {$text}, created_at {$dt})",
            "CREATE TABLE IF NOT EXISTS teams (id {$id}, public_id VARCHAR(64) UNIQUE, title VARCHAR(255), manager_user_id INTEGER NULL, created_by_user_id INTEGER NULL, member_user_ids {$text} NULL, created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS departments (id {$id}, public_id VARCHAR(64) UNIQUE, title VARCHAR(255), manager_user_id INTEGER NULL, created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS companies (id {$id}, public_id VARCHAR(64) UNIQUE, title VARCHAR(255), created_by_user_id INTEGER NULL, created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS clients (id {$id}, public_id VARCHAR(64) UNIQUE, company_id INTEGER NULL, title VARCHAR(255), client_type VARCHAR(32) NULL, legal_name VARCHAR(255) NULL, person_last_name VARCHAR(120) NULL, person_first_name VARCHAR(120) NULL, person_middle_name VARCHAR(120) NULL, person_birth_date DATE NULL, tax_inn VARCHAR(12) NULL, tax_kpp VARCHAR(9) NULL, tax_ogrn VARCHAR(13) NULL, tax_ogrnip VARCHAR(15) NULL, bank_account VARCHAR(34) NULL, bank_name VARCHAR(255) NULL, bank_bik VARCHAR(9) NULL, bank_corr_account VARCHAR(34) NULL, website VARCHAR(2048) NULL, messenger VARCHAR(190) NULL, address_legal {$text} NULL, address_postal {$text} NULL, notes {$text} NULL, extra_attributes {$text} NULL, email VARCHAR(190), phone VARCHAR(64), status VARCHAR(64), created_by_user_id INTEGER NULL, created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS contacts (id {$id}, public_id VARCHAR(64) UNIQUE, company_id INTEGER NULL, client_id INTEGER NULL, full_name VARCHAR(255), email VARCHAR(190), phone VARCHAR(64), created_by_user_id INTEGER NULL, created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS subtasks (id {$id}, public_id VARCHAR(64) UNIQUE, task_id INTEGER, title VARCHAR(255), status_code VARCHAR(64), assignee_user_id INTEGER NULL, sort_order INTEGER, created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS checklists (id {$id}, public_id VARCHAR(64) UNIQUE, task_id INTEGER, title VARCHAR(255), created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS checklist_items (id {$id}, public_id VARCHAR(64) UNIQUE, checklist_id INTEGER, title VARCHAR(255), is_done {$bool} DEFAULT 0, sort_order INTEGER, created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS task_templates (id {$id}, public_id VARCHAR(64) UNIQUE, title VARCHAR(255), payload {$text}, is_active {$bool} DEFAULT 1, created_by_user_id INTEGER NULL, created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS project_templates (id {$id}, public_id VARCHAR(64) UNIQUE, title VARCHAR(255), payload {$text}, is_active {$bool} DEFAULT 1, created_by_user_id INTEGER NULL, created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS recurring_rules (id {$id}, public_id VARCHAR(64) UNIQUE, entity_type VARCHAR(64), entity_public_id VARCHAR(64), rrule {$text}, is_active {$bool} DEFAULT 1, last_processed_at {$dt} NULL, created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS recurring_instances (id {$id}, public_id VARCHAR(64) UNIQUE, rule_id INTEGER, entity_public_id VARCHAR(64), generated_at {$dt}, next_occurrence {$dt} NULL, processed_at {$dt} NULL, created_at {$dt})",
            "CREATE TABLE IF NOT EXISTS custom_fields (id {$id}, public_id VARCHAR(64) UNIQUE, scope VARCHAR(64), code VARCHAR(64), title VARCHAR(255), type VARCHAR(64), options {$text}, is_required {$bool} DEFAULT 0, created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS custom_field_values (id {$id}, public_id VARCHAR(64) UNIQUE, field_id INTEGER, entity_type VARCHAR(64), entity_public_id VARCHAR(64), value {$text}, created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS automation_rules (id {$id}, public_id VARCHAR(64) UNIQUE, title VARCHAR(255), trigger_code VARCHAR(64), action_code VARCHAR(64), payload {$text}, is_enabled {$bool} DEFAULT 1, created_by_user_id INTEGER NULL, created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS automation_runs (id {$id}, public_id VARCHAR(64) UNIQUE, rule_id INTEGER, status VARCHAR(32), error {$text} NULL, created_at {$dt})",
            "CREATE TABLE IF NOT EXISTS sla_policies (id {$id}, public_id VARCHAR(64) UNIQUE, title VARCHAR(255), response_minutes INTEGER, resolve_minutes INTEGER, escalation_payload {$text}, created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS approval_requests (id {$id}, public_id VARCHAR(64) UNIQUE, entity_type VARCHAR(64), entity_public_id VARCHAR(64), requester_user_id INTEGER, status VARCHAR(32), created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS approval_steps (id {$id}, public_id VARCHAR(64) UNIQUE, request_id INTEGER, reviewer_user_id INTEGER, status VARCHAR(32), comment {$text} NULL, created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS milestones (id {$id}, public_id VARCHAR(64) UNIQUE, project_id INTEGER, title VARCHAR(255), due_at {$dt} NULL, status VARCHAR(32), created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS task_dependencies (id {$id}, public_id VARCHAR(64) UNIQUE, task_id INTEGER, depends_on_task_id INTEGER, dependency_type VARCHAR(32), created_at {$dt})",
            "CREATE TABLE IF NOT EXISTS saved_views (id {$id}, public_id VARCHAR(64) UNIQUE, user_id INTEGER, entity_type VARCHAR(64), title VARCHAR(255), filters {$text}, created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS favorites (id {$id}, public_id VARCHAR(64) UNIQUE, user_id INTEGER, entity_type VARCHAR(64), entity_public_id VARCHAR(64), created_at {$dt})",
            "CREATE TABLE IF NOT EXISTS mentions (id {$id}, public_id VARCHAR(64) UNIQUE, entity_type VARCHAR(64), entity_public_id VARCHAR(64), mentioned_user_id INTEGER, created_at {$dt})",
            "CREATE TABLE IF NOT EXISTS reactions (id {$id}, public_id VARCHAR(64) UNIQUE, entity_type VARCHAR(64), entity_public_id VARCHAR(64), user_id INTEGER, reaction VARCHAR(32), created_at {$dt})",
            "CREATE TABLE IF NOT EXISTS subscriptions (id {$id}, public_id VARCHAR(64) UNIQUE, entity_type VARCHAR(64), entity_public_id VARCHAR(64), user_id INTEGER, created_at {$dt})",
            "CREATE TABLE IF NOT EXISTS recycle_bin (id {$id}, public_id VARCHAR(64) UNIQUE, entity_type VARCHAR(64), entity_public_id VARCHAR(64), payload {$text}, deleted_by_user_id INTEGER, deleted_at {$dt}, restored_at {$dt} NULL)",
            "CREATE TABLE IF NOT EXISTS import_jobs (id {$id}, public_id VARCHAR(64) UNIQUE, user_id INTEGER, type VARCHAR(64), status VARCHAR(32), payload {$text}, result {$text}, created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS export_jobs (id {$id}, public_id VARCHAR(64) UNIQUE, user_id INTEGER, type VARCHAR(64), status VARCHAR(32), payload {$text}, result {$text}, created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS webhook_subscriptions (id {$id}, public_id VARCHAR(64) UNIQUE, title VARCHAR(255), endpoint {$text}, secret_hash VARCHAR(255), events {$text}, is_active {$bool} DEFAULT 1, created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS webhook_deliveries (id {$id}, public_id VARCHAR(64) UNIQUE, webhook_id INTEGER, event_code VARCHAR(64), status VARCHAR(32), response_code INTEGER NULL, created_at {$dt})",
            "CREATE TABLE IF NOT EXISTS idempotency_keys (id {$id}, public_id VARCHAR(64) UNIQUE, key_hash VARCHAR(255), route VARCHAR(255), response_payload {$text}, created_at {$dt})",
            "CREATE TABLE IF NOT EXISTS sync_state (id {$id}, public_id VARCHAR(64) UNIQUE, user_id INTEGER, scope VARCHAR(64), cursor_value VARCHAR(255), updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS business_calendars (id {$id}, public_id VARCHAR(64) UNIQUE, title VARCHAR(255), timezone VARCHAR(64), created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS holidays (id {$id}, public_id VARCHAR(64) UNIQUE, calendar_id INTEGER, holiday_date DATE, title VARCHAR(255), created_at {$dt})",
            "CREATE TABLE IF NOT EXISTS working_hours (id {$id}, public_id VARCHAR(64) UNIQUE, calendar_id INTEGER, weekday INTEGER, start_time VARCHAR(8), end_time VARCHAR(8), created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS feature_flags (id {$id}, public_id VARCHAR(64) UNIQUE, code VARCHAR(128), is_enabled {$bool} DEFAULT 1, payload {$text}, created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS organizations (id {$id}, public_id VARCHAR(64) UNIQUE, title VARCHAR(255), slug VARCHAR(120), created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS invitations (id {$id}, public_id VARCHAR(64) UNIQUE, email VARCHAR(190), invited_by_user_id INTEGER, token_hash VARCHAR(255), expires_at {$dt}, accepted_at {$dt} NULL, created_at {$dt})",
            "CREATE TABLE IF NOT EXISTS password_reset_tokens (id {$id}, public_id VARCHAR(64) UNIQUE, user_id INTEGER, token_hash VARCHAR(255), expires_at {$dt}, used_at {$dt} NULL, created_at {$dt})",
            "CREATE TABLE IF NOT EXISTS two_factor_secrets (id {$id}, public_id VARCHAR(64) UNIQUE, user_id INTEGER, secret_hash VARCHAR(255), backup_codes {$text}, created_at {$dt}, updated_at {$dt})",
            "CREATE TABLE IF NOT EXISTS impersonation_audit (id {$id}, public_id VARCHAR(64) UNIQUE, admin_user_id INTEGER, target_user_id INTEGER, reason {$text}, started_at {$dt}, ended_at {$dt} NULL)",
        ];

        foreach ($tables as $sql) {
            $pdo->exec($sql);
        }

        $this->migrateChatsTable($pdo);
        $this->createIndexes($pdo);
    }

    public function seedDictionaries(PDO $pdo): void
    {
        $now = gmdate('Y-m-d H:i:s');

        $statuses = [
            ['task', 'new', 'Новая', '#64748b', 10],
            ['task', 'in_progress', 'В работе', '#2563eb', 20],
            ['task', 'blocked', 'Заблокирована', '#d97706', 30],
            ['task', 'done', 'Завершена', '#16a34a', 40],
            ['project', 'active', 'Активный', '#2563eb', 10],
            ['project', 'on_hold', 'На паузе', '#d97706', 20],
            ['project', 'archived', 'В архиве', '#475569', 30],
        ];

        $stmt = $pdo->prepare('INSERT INTO statuses (public_id, scope, code, title, color, sort_order, created_at, updated_at) VALUES (:public_id,:scope,:code,:title,:color,:sort_order,:created_at,:updated_at)');
        foreach ($statuses as $s) {
            $check = $pdo->prepare('SELECT id FROM statuses WHERE scope = :scope AND code = :code');
            $check->execute(['scope' => $s[0], 'code' => $s[1]]);
            if ($check->fetch()) {
                continue;
            }

            $stmt->execute([
                'public_id' => 'sts_' . strtoupper(bin2hex(random_bytes(8))),
                'scope' => $s[0],
                'code' => $s[1],
                'title' => $s[2],
                'color' => $s[3],
                'sort_order' => $s[4],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $priorities = [
            ['low', 'Низкий', 10, '#16a34a'],
            ['normal', 'Обычный', 20, '#2563eb'],
            ['high', 'Высокий', 30, '#f59e0b'],
            ['urgent', 'Критичный', 40, '#dc2626'],
        ];

        $pstmt = $pdo->prepare('INSERT INTO priorities (public_id, code, title, weight, color, created_at, updated_at) VALUES (:public_id,:code,:title,:weight,:color,:created_at,:updated_at)');
        foreach ($priorities as $p) {
            $check = $pdo->prepare('SELECT id FROM priorities WHERE code = :code');
            $check->execute(['code' => $p[0]]);
            if ($check->fetch()) {
                continue;
            }

            $pstmt->execute([
                'public_id' => 'pri_' . strtoupper(bin2hex(random_bytes(8))),
                'code' => $p[0],
                'title' => $p[1],
                'weight' => $p[2],
                'color' => $p[3],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function createIndexes(PDO $pdo): void
    {
        $indexes = [
            ['users', 'idx_users_login', 'login', false],
            ['users', 'idx_users_created_by', 'created_by_user_id', false],
            ['user_sessions', 'idx_sessions_token', 'token_hash', false],
            ['user_sessions', 'idx_sessions_user_device', 'user_id, device_fingerprint', false],
            ['tasks', 'idx_tasks_project', 'project_id', false],
            ['tasks', 'idx_tasks_status', 'status_code', false],
            ['tasks', 'idx_tasks_due', 'due_at', false],
            ['tasks', 'idx_tasks_updated_public', 'updated_at, public_id', false],
            ['tasks', 'idx_tasks_active_updated', 'deleted_at, archived_at, updated_at, public_id', false],
            ['tasks', 'idx_tasks_project_active_updated', 'project_id, deleted_at, archived_at, updated_at', false],
            ['tasks', 'idx_tasks_status_active_updated', 'status_code, deleted_at, archived_at, updated_at', false],
            ['tasks', 'idx_tasks_priority_active_updated', 'priority_code, deleted_at, archived_at, updated_at', false],
            ['tasks', 'idx_tasks_assignee_active_updated', 'assignee_user_id, deleted_at, archived_at, updated_at', false],
            ['tasks', 'idx_tasks_creator_active_updated', 'creator_user_id, deleted_at, archived_at, updated_at', false],
            ['projects', 'idx_projects_status', 'status_code', false],
            ['projects', 'idx_projects_updated_public', 'updated_at, public_id', false],
            ['projects', 'idx_projects_archived_updated', 'archived_at, updated_at, public_id', false],
            ['projects', 'idx_projects_creator_archived_updated', 'created_by_user_id, archived_at, updated_at', false],
            ['projects', 'idx_projects_manager_archived_updated', 'manager_user_id, archived_at, updated_at', false],
            ['comments', 'idx_comments_task', 'task_id', false],
            ['companies', 'idx_companies_created_by', 'created_by_user_id', false],
            ['teams', 'idx_teams_created_by', 'created_by_user_id', false],
            ['clients', 'idx_clients_created_by', 'created_by_user_id', false],
            ['clients', 'idx_clients_type', 'client_type', false],
            ['clients', 'idx_clients_tax_inn', 'tax_inn', false],
            ['contacts', 'idx_contacts_created_by', 'created_by_user_id', false],
            ['task_templates', 'idx_task_templates_created_by', 'created_by_user_id', false],
            ['project_templates', 'idx_project_templates_created_by', 'created_by_user_id', false],
            ['automation_rules', 'idx_automation_rules_created_by', 'created_by_user_id', false],
            ['comment_drafts', 'uq_comment_drafts_user_task', 'user_id, task_id', true],
            ['files', 'idx_files_entity', 'entity_type, entity_public_id', false],
            ['notifications', 'idx_notifications_user_created', 'user_id, created_at', false],
            ['notifications', 'idx_notifications_user_unread_created', 'user_id, is_read, created_at', false],
            ['notifications', 'idx_notifications_user_category_unread', 'user_id, category, is_read', false],
            ['notifications', 'idx_notifications_entity', 'entity_type, entity_public_id', false],
            ['chats', 'idx_chats_type_project', 'type, project_id', false],
            ['chats', 'idx_chats_type_team', 'type, team_id', false],
            ['chats', 'idx_chats_last_message', 'last_message_at', false],
            ['chats', 'idx_chats_archived', 'archived_by_user_id, archived_at', false],
            ['chat_participants', 'idx_chat_participants_user', 'user_id, chat_id', false],
            ['chat_messages', 'idx_chat_messages_chat_id', 'chat_id, id', false],
            ['chat_read_markers', 'idx_chat_read_markers_user', 'user_id, chat_id', false],
            ['notification_push_subscriptions', 'idx_notif_push_subscriptions_user_active', 'user_id, is_active, updated_at', false],
            ['notification_push_subscriptions', 'idx_notif_push_subscriptions_endpoint', 'endpoint(255)', false],
            ['request_logs', 'idx_request_logs_request', 'request_id', false],
            ['request_logs', 'idx_request_logs_created', 'created_at', false],
            ['request_logs', 'idx_request_logs_user_created', 'user_public_id, created_at', false],
            ['request_logs', 'idx_request_logs_method_created', 'method, created_at', false],
            ['request_logs', 'idx_request_logs_result_created', 'result_code, created_at', false],
            ['audit_logs', 'idx_audit_entity', 'entity_type, entity_public_id', false],
            ['audit_logs', 'idx_audit_logs_created', 'created_at', false],
            ['audit_logs', 'idx_audit_logs_actor_created', 'actor_public_id, created_at', false],
            ['security_logs', 'idx_security_logs_created', 'created_at', false],
            ['security_logs', 'idx_security_logs_actor_created', 'actor_public_id, created_at', false],
            ['security_logs', 'idx_security_logs_event_created', 'event_type, created_at', false],
        ];

        foreach ($indexes as [$table, $name, $columns, $unique]) {
            $this->createIndexIfMissing($pdo, (string)$table, (string)$name, (string)$columns, (bool)$unique);
        }
    }

    private function createIndexIfMissing(PDO $pdo, string $table, string $name, string $columns, bool $unique): void
    {
        if ($this->indexExists($pdo, $table, $name)) {
            return;
        }

        $sql = sprintf(
            'CREATE %s INDEX %s ON %s(%s)',
            $unique ? 'UNIQUE' : '',
            $name,
            $table,
            $columns
        );

        try {
            $pdo->exec(trim(preg_replace('/\s+/', ' ', $sql) ?? $sql));
        } catch (\Throwable) {
            // Ignore race/duplicate errors in concurrent setup paths.
        }
    }

    private function indexExists(PDO $pdo, string $table, string $name): bool
    {
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        try {
            if ($driver === 'sqlite') {
                $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = :table AND name = :name LIMIT 1");
                $stmt->execute(['table' => $table, 'name' => $name]);
                return (bool)$stmt->fetchColumn();
            }

            if ($driver === 'sqlsrv') {
                $stmt = $pdo->prepare('SELECT TOP 1 i.name
                    FROM sys.indexes i
                    INNER JOIN sys.objects o ON o.object_id = i.object_id
                    WHERE o.name = :table AND i.name = :name');
                $stmt->execute(['table' => $table, 'name' => $name]);
                return (bool)$stmt->fetchColumn();
            }

            $stmt = $pdo->prepare(
                'SELECT 1
                 FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = :table
                   AND index_name = :name
                 LIMIT 1'
            );
            $stmt->execute(['table' => $table, 'name' => $name]);
            return (bool)$stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }
}
