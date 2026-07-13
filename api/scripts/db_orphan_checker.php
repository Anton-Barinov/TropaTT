<?php

declare(strict_types=1);
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }


use Api\System\Library\Config;
use Api\System\Library\Database\ConnectionManager;
use Api\System\Library\Support\Autoloader;

require_once __DIR__ . '/../system/library/support/Autoloader.php';

$basePath = dirname(__DIR__);
$autoloader = new Autoloader($basePath);
$autoloader->register();

$config = new Config();
$config->load($basePath . '/config/default.php', 'default');
$config->load($basePath . '/config/database.php', 'database');
$config->load($basePath . '/config/install.php', 'install');
$config->load($basePath . '/config/database.local.php', 'database');

$connections = new ConnectionManager($config);
$pdo = $connections->connect();

$failOnOrphans = in_array('--fail-on-orphans', $argv, true);
$json = in_array('--json', $argv, true);

/** @var list<array{key:string,child:string,parent:string,join:string,where:string,description:string}> $checks */
$checks = [
    ['key' => 'tasks.project_id->projects.id', 'child' => 'tasks', 'parent' => 'projects', 'join' => 'c.project_id = p.id', 'where' => 'c.project_id IS NOT NULL', 'description' => 'Task must reference existing project when project_id set'],
    ['key' => 'tasks.assignee_user_id->users.id', 'child' => 'tasks', 'parent' => 'users', 'join' => 'c.assignee_user_id = p.id', 'where' => 'c.assignee_user_id IS NOT NULL', 'description' => 'Task assignee must exist'],
    ['key' => 'tasks.creator_user_id->users.id', 'child' => 'tasks', 'parent' => 'users', 'join' => 'c.creator_user_id = p.id', 'where' => 'c.creator_user_id IS NOT NULL', 'description' => 'Task creator must exist'],
    ['key' => 'task_status_history.task_id->tasks.id', 'child' => 'task_status_history', 'parent' => 'tasks', 'join' => 'c.task_id = p.id', 'where' => 'c.task_id IS NOT NULL', 'description' => 'Task status history must reference existing task'],
    ['key' => 'task_status_history.changed_by_user_id->users.id', 'child' => 'task_status_history', 'parent' => 'users', 'join' => 'c.changed_by_user_id = p.id', 'where' => 'c.changed_by_user_id IS NOT NULL', 'description' => 'Task status changer must exist'],
    ['key' => 'comments.task_id->tasks.id', 'child' => 'comments', 'parent' => 'tasks', 'join' => 'c.task_id = p.id', 'where' => 'c.task_id IS NOT NULL', 'description' => 'Comment must reference existing task'],
    ['key' => 'comments.author_user_id->users.id', 'child' => 'comments', 'parent' => 'users', 'join' => 'c.author_user_id = p.id', 'where' => 'c.author_user_id IS NOT NULL', 'description' => 'Comment author must exist'],
    ['key' => 'comment_drafts.user_id->users.id', 'child' => 'comment_drafts', 'parent' => 'users', 'join' => 'c.user_id = p.id', 'where' => 'c.user_id IS NOT NULL', 'description' => 'Comment draft owner must exist'],
    ['key' => 'comment_drafts.task_id->tasks.id', 'child' => 'comment_drafts', 'parent' => 'tasks', 'join' => 'c.task_id = p.id', 'where' => 'c.task_id IS NOT NULL', 'description' => 'Comment draft task must exist'],
    ['key' => 'task_assignees.task_id->tasks.id', 'child' => 'task_assignees', 'parent' => 'tasks', 'join' => 'c.task_id = p.id', 'where' => 'c.task_id IS NOT NULL', 'description' => 'Task assignee relation must reference existing task'],
    ['key' => 'task_assignees.user_id->users.id', 'child' => 'task_assignees', 'parent' => 'users', 'join' => 'c.user_id = p.id', 'where' => 'c.user_id IS NOT NULL', 'description' => 'Task assignee relation must reference existing user'],
    ['key' => 'task_watchers.task_id->tasks.id', 'child' => 'task_watchers', 'parent' => 'tasks', 'join' => 'c.task_id = p.id', 'where' => 'c.task_id IS NOT NULL', 'description' => 'Task watcher relation must reference existing task'],
    ['key' => 'task_watchers.user_id->users.id', 'child' => 'task_watchers', 'parent' => 'users', 'join' => 'c.user_id = p.id', 'where' => 'c.user_id IS NOT NULL', 'description' => 'Task watcher relation must reference existing user'],
    ['key' => 'subtasks.task_id->tasks.id', 'child' => 'subtasks', 'parent' => 'tasks', 'join' => 'c.task_id = p.id', 'where' => 'c.task_id IS NOT NULL', 'description' => 'Subtask must reference existing task'],
    ['key' => 'subtasks.assignee_user_id->users.id', 'child' => 'subtasks', 'parent' => 'users', 'join' => 'c.assignee_user_id = p.id', 'where' => 'c.assignee_user_id IS NOT NULL', 'description' => 'Subtask assignee must exist'],
    ['key' => 'checklists.task_id->tasks.id', 'child' => 'checklists', 'parent' => 'tasks', 'join' => 'c.task_id = p.id', 'where' => 'c.task_id IS NOT NULL', 'description' => 'Checklist must reference existing task'],
    ['key' => 'checklist_items.checklist_id->checklists.id', 'child' => 'checklist_items', 'parent' => 'checklists', 'join' => 'c.checklist_id = p.id', 'where' => 'c.checklist_id IS NOT NULL', 'description' => 'Checklist item must reference existing checklist'],
    ['key' => 'work_logs.task_id->tasks.id', 'child' => 'work_logs', 'parent' => 'tasks', 'join' => 'c.task_id = p.id', 'where' => 'c.task_id IS NOT NULL', 'description' => 'Work log must reference existing task'],
    ['key' => 'work_logs.user_id->users.id', 'child' => 'work_logs', 'parent' => 'users', 'join' => 'c.user_id = p.id', 'where' => 'c.user_id IS NOT NULL', 'description' => 'Work log author must exist'],
    ['key' => 'user_sessions.user_id->users.id', 'child' => 'user_sessions', 'parent' => 'users', 'join' => 'c.user_id = p.id', 'where' => 'c.user_id IS NOT NULL', 'description' => 'Session must reference existing user'],
    ['key' => 'api_keys.client_id->api_clients.id', 'child' => 'api_keys', 'parent' => 'api_clients', 'join' => 'c.client_id = p.id', 'where' => 'c.client_id IS NOT NULL', 'description' => 'API key must reference existing API client'],
    ['key' => 'api_keys.user_id->users.id', 'child' => 'api_keys', 'parent' => 'users', 'join' => 'c.user_id = p.id', 'where' => 'c.user_id IS NOT NULL', 'description' => 'User-bound API key must reference existing user'],
    ['key' => 'user_roles.user_id->users.id', 'child' => 'user_roles', 'parent' => 'users', 'join' => 'c.user_id = p.id', 'where' => 'c.user_id IS NOT NULL', 'description' => 'User role assignment must reference existing user'],
    ['key' => 'user_roles.role_id->roles.id', 'child' => 'user_roles', 'parent' => 'roles', 'join' => 'c.role_id = p.id', 'where' => 'c.role_id IS NOT NULL', 'description' => 'User role assignment must reference existing role'],
    ['key' => 'role_permissions.role_id->roles.id', 'child' => 'role_permissions', 'parent' => 'roles', 'join' => 'c.role_id = p.id', 'where' => 'c.role_id IS NOT NULL', 'description' => 'Role permission assignment must reference existing role'],
    ['key' => 'role_permissions.permission_id->permissions.id', 'child' => 'role_permissions', 'parent' => 'permissions', 'join' => 'c.permission_id = p.id', 'where' => 'c.permission_id IS NOT NULL', 'description' => 'Role permission assignment must reference existing permission'],
    ['key' => 'notification_push_subscriptions.user_id->users.id', 'child' => 'notification_push_subscriptions', 'parent' => 'users', 'join' => 'c.user_id = p.id', 'where' => 'c.user_id IS NOT NULL', 'description' => 'Push subscription must reference existing user'],
    ['key' => 'notifications.user_id->users.id', 'child' => 'notifications', 'parent' => 'users', 'join' => 'c.user_id = p.id', 'where' => 'c.user_id IS NOT NULL', 'description' => 'Notification must reference recipient user'],
    ['key' => 'notifications.actor_user_id->users.id', 'child' => 'notifications', 'parent' => 'users', 'join' => 'c.actor_user_id = p.id', 'where' => 'c.actor_user_id IS NOT NULL', 'description' => 'Notification actor must reference existing user when stored by id'],
    ['key' => 'reminders.user_id->users.id', 'child' => 'reminders', 'parent' => 'users', 'join' => 'c.user_id = p.id', 'where' => 'c.user_id IS NOT NULL', 'description' => 'Reminder owner must exist'],
    ['key' => 'reminders.task_id->tasks.id', 'child' => 'reminders', 'parent' => 'tasks', 'join' => 'c.task_id = p.id', 'where' => 'c.task_id IS NOT NULL', 'description' => 'Reminder task must exist when set'],
    ['key' => 'calendar_events.owner_user_id->users.id', 'child' => 'calendar_events', 'parent' => 'users', 'join' => 'c.owner_user_id = p.id', 'where' => 'c.owner_user_id IS NOT NULL', 'description' => 'Calendar event owner must exist'],
    ['key' => 'calendar_events.project_id->projects.id', 'child' => 'calendar_events', 'parent' => 'projects', 'join' => 'c.project_id = p.id', 'where' => 'c.project_id IS NOT NULL', 'description' => 'Calendar event project must exist when set'],
    ['key' => 'calendar_events.task_id->tasks.id', 'child' => 'calendar_events', 'parent' => 'tasks', 'join' => 'c.task_id = p.id', 'where' => 'c.task_id IS NOT NULL', 'description' => 'Calendar event task must exist when set'],
    ['key' => 'webhook_deliveries.webhook_id->webhook_subscriptions.id', 'child' => 'webhook_deliveries', 'parent' => 'webhook_subscriptions', 'join' => 'c.webhook_id = p.id', 'where' => 'c.webhook_id IS NOT NULL', 'description' => 'Webhook delivery must reference existing subscription'],
    ['key' => 'clients.company_id->companies.id', 'child' => 'clients', 'parent' => 'companies', 'join' => 'c.company_id = p.id', 'where' => 'c.company_id IS NOT NULL', 'description' => 'Client.company_id must reference existing company'],
    ['key' => 'contacts.company_id->companies.id', 'child' => 'contacts', 'parent' => 'companies', 'join' => 'c.company_id = p.id', 'where' => 'c.company_id IS NOT NULL', 'description' => 'Contact.company_id must reference existing company'],
    ['key' => 'contacts.client_id->clients.id', 'child' => 'contacts', 'parent' => 'clients', 'join' => 'c.client_id = p.id', 'where' => 'c.client_id IS NOT NULL', 'description' => 'Contact.client_id must reference existing client'],
    ['key' => 'files.uploader_user_id->users.id', 'child' => 'files', 'parent' => 'users', 'join' => 'c.uploader_user_id = p.id', 'where' => 'c.uploader_user_id IS NOT NULL', 'description' => 'File uploader must exist'],
    ['key' => 'entity_tags.tag_id->tags.id', 'child' => 'entity_tags', 'parent' => 'tags', 'join' => 'c.tag_id = p.id', 'where' => 'c.tag_id IS NOT NULL', 'description' => 'Entity tag must reference existing tag'],
    ['key' => 'custom_field_values.field_id->custom_fields.id', 'child' => 'custom_field_values', 'parent' => 'custom_fields', 'join' => 'c.field_id = p.id', 'where' => 'c.field_id IS NOT NULL', 'description' => 'Custom field value must reference existing field'],
    ['key' => 'automation_runs.rule_id->automation_rules.id', 'child' => 'automation_runs', 'parent' => 'automation_rules', 'join' => 'c.rule_id = p.id', 'where' => 'c.rule_id IS NOT NULL', 'description' => 'Automation run must reference existing rule'],
    ['key' => 'approval_requests.requester_user_id->users.id', 'child' => 'approval_requests', 'parent' => 'users', 'join' => 'c.requester_user_id = p.id', 'where' => 'c.requester_user_id IS NOT NULL', 'description' => 'Approval requester must exist'],
    ['key' => 'approval_steps.request_id->approval_requests.id', 'child' => 'approval_steps', 'parent' => 'approval_requests', 'join' => 'c.request_id = p.id', 'where' => 'c.request_id IS NOT NULL', 'description' => 'Approval step must reference existing request'],
    ['key' => 'approval_steps.reviewer_user_id->users.id', 'child' => 'approval_steps', 'parent' => 'users', 'join' => 'c.reviewer_user_id = p.id', 'where' => 'c.reviewer_user_id IS NOT NULL', 'description' => 'Approval reviewer must exist'],
    ['key' => 'milestones.project_id->projects.id', 'child' => 'milestones', 'parent' => 'projects', 'join' => 'c.project_id = p.id', 'where' => 'c.project_id IS NOT NULL', 'description' => 'Milestone must reference existing project'],
    ['key' => 'task_dependencies.task_id->tasks.id', 'child' => 'task_dependencies', 'parent' => 'tasks', 'join' => 'c.task_id = p.id', 'where' => 'c.task_id IS NOT NULL', 'description' => 'Task dependency owner task must exist'],
    ['key' => 'task_dependencies.depends_on_task_id->tasks.id', 'child' => 'task_dependencies', 'parent' => 'tasks', 'join' => 'c.depends_on_task_id = p.id', 'where' => 'c.depends_on_task_id IS NOT NULL', 'description' => 'Task dependency target task must exist'],
    ['key' => 'saved_views.user_id->users.id', 'child' => 'saved_views', 'parent' => 'users', 'join' => 'c.user_id = p.id', 'where' => 'c.user_id IS NOT NULL', 'description' => 'Saved view owner must exist'],
    ['key' => 'favorites.user_id->users.id', 'child' => 'favorites', 'parent' => 'users', 'join' => 'c.user_id = p.id', 'where' => 'c.user_id IS NOT NULL', 'description' => 'Favorite owner must exist'],
    ['key' => 'mentions.mentioned_user_id->users.id', 'child' => 'mentions', 'parent' => 'users', 'join' => 'c.mentioned_user_id = p.id', 'where' => 'c.mentioned_user_id IS NOT NULL', 'description' => 'Mentioned user must exist'],
    ['key' => 'reactions.user_id->users.id', 'child' => 'reactions', 'parent' => 'users', 'join' => 'c.user_id = p.id', 'where' => 'c.user_id IS NOT NULL', 'description' => 'Reaction owner must exist'],
    ['key' => 'subscriptions.user_id->users.id', 'child' => 'subscriptions', 'parent' => 'users', 'join' => 'c.user_id = p.id', 'where' => 'c.user_id IS NOT NULL', 'description' => 'Subscription owner must exist'],
    ['key' => 'recycle_bin.deleted_by_user_id->users.id', 'child' => 'recycle_bin', 'parent' => 'users', 'join' => 'c.deleted_by_user_id = p.id', 'where' => 'c.deleted_by_user_id IS NOT NULL', 'description' => 'Recycle-bin deleter must exist'],
    ['key' => 'import_jobs.user_id->users.id', 'child' => 'import_jobs', 'parent' => 'users', 'join' => 'c.user_id = p.id', 'where' => 'c.user_id IS NOT NULL', 'description' => 'Import job must reference existing owner user'],
    ['key' => 'export_jobs.user_id->users.id', 'child' => 'export_jobs', 'parent' => 'users', 'join' => 'c.user_id = p.id', 'where' => 'c.user_id IS NOT NULL', 'description' => 'Export job must reference existing owner user'],
    ['key' => 'holidays.calendar_id->business_calendars.id', 'child' => 'holidays', 'parent' => 'business_calendars', 'join' => 'c.calendar_id = p.id', 'where' => 'c.calendar_id IS NOT NULL', 'description' => 'Holiday must reference existing business calendar'],
    ['key' => 'working_hours.calendar_id->business_calendars.id', 'child' => 'working_hours', 'parent' => 'business_calendars', 'join' => 'c.calendar_id = p.id', 'where' => 'c.calendar_id IS NOT NULL', 'description' => 'Working-hours row must reference existing business calendar'],
    ['key' => 'invitations.invited_by_user_id->users.id', 'child' => 'invitations', 'parent' => 'users', 'join' => 'c.invited_by_user_id = p.id', 'where' => 'c.invited_by_user_id IS NOT NULL', 'description' => 'Invitation creator must exist'],
    ['key' => 'password_reset_tokens.user_id->users.id', 'child' => 'password_reset_tokens', 'parent' => 'users', 'join' => 'c.user_id = p.id', 'where' => 'c.user_id IS NOT NULL', 'description' => 'Password reset token must reference existing user'],
    ['key' => 'two_factor_secrets.user_id->users.id', 'child' => 'two_factor_secrets', 'parent' => 'users', 'join' => 'c.user_id = p.id', 'where' => 'c.user_id IS NOT NULL', 'description' => '2FA secret must reference existing user'],
    ['key' => 'impersonation_audit.admin_user_id->users.id', 'child' => 'impersonation_audit', 'parent' => 'users', 'join' => 'c.admin_user_id = p.id', 'where' => 'c.admin_user_id IS NOT NULL', 'description' => 'Impersonation admin must exist'],
    ['key' => 'impersonation_audit.target_user_id->users.id', 'child' => 'impersonation_audit', 'parent' => 'users', 'join' => 'c.target_user_id = p.id', 'where' => 'c.target_user_id IS NOT NULL', 'description' => 'Impersonation target must exist'],
];

$results = [];
$totalOrphans = 0;
$executed = 0;
$skipped = 0;

foreach ($checks as $check) {
    if (!tableExists($pdo, $check['child']) || !tableExists($pdo, $check['parent'])) {
        $skipped++;
        $results[] = [
            'key' => $check['key'],
            'status' => 'skipped',
            'orphans' => 0,
            'reason' => 'table_missing',
            'description' => $check['description'],
        ];
        continue;
    }

    $executed++;
    $orphans = orphanCount($pdo, $check['child'], $check['parent'], $check['join'], $check['where']);
    $totalOrphans += $orphans;
    $results[] = [
        'key' => $check['key'],
        'status' => $orphans > 0 ? 'orphan_found' : 'ok',
        'orphans' => $orphans,
        'description' => $check['description'],
    ];
}

$summary = [
    'executed_checks' => $executed,
    'skipped_checks' => $skipped,
    'total_orphans' => $totalOrphans,
    'result' => $totalOrphans > 0 ? 'WARN' : 'OK',
    'generated_at' => gmdate('c'),
];

if ($json) {
    fwrite(STDOUT, json_encode(['summary' => $summary, 'checks' => $results], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL);
} else {
    fwrite(STDOUT, '[INFO] DB orphan checker' . PHP_EOL);
    fwrite(STDOUT, '[INFO] Executed checks: ' . $executed . ', skipped: ' . $skipped . PHP_EOL);
    foreach ($results as $result) {
        fwrite(STDOUT, sprintf(
            '[%s] %s orphans=%d%s',
            strtoupper((string)$result['status']),
            (string)$result['key'],
            (int)$result['orphans'],
            isset($result['reason']) ? ' reason=' . (string)$result['reason'] : ''
        ) . PHP_EOL);
    }
    fwrite(STDOUT, '[SUMMARY] total_orphans=' . $totalOrphans . ' result=' . (string)$summary['result'] . PHP_EOL);
}

if ($failOnOrphans && $totalOrphans > 0) {
    exit(2);
}

exit(0);

function tableExists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->query('SELECT 1 FROM ' . $table . ' WHERE 1=0');
        return $stmt !== false;
    } catch (Throwable) {
        return false;
    }
}

function orphanCount(PDO $pdo, string $child, string $parent, string $join, string $where): int
{
    // NOT EXISTS позволяет MySQL прервать сканирование при первом совпадении,
    // что быстрее LEFT JOIN ... WHERE p.id IS NULL на больших таблицах.
    // Из join вида "c.project_id = p.id" извлекаем FK и PK имена.
    $pk = 'id';
    $fk = '';
    if (preg_match('/^c\.(\w+)\s*=\s*p\.(\w+)$/', $join, $m)) {
        $fk = $m[1];
        $pk = $m[2];
    }

    // Если FK не распознан, используем безопасный LEFT JOIN (fallback)
    if ($fk === '') {
        $sql = sprintf(
            'SELECT COUNT(*) AS c FROM %s c LEFT JOIN %s p ON %s WHERE %s AND p.id IS NULL',
            $child,
            $parent,
            $join,
            $where
        );
    } else {
        $sql = sprintf(
            'SELECT COUNT(*) AS c FROM %s c WHERE %s AND NOT EXISTS (SELECT 1 FROM %s p WHERE p.%s = c.%s)',
            $child,
            $where,
            $parent,
            $pk,
            $fk
        );
    }

    $stmt = $pdo->query($sql);
    if ($stmt === false) {
        return 0;
    }

    $value = $stmt->fetchColumn();
    return max(0, (int)$value);
}
