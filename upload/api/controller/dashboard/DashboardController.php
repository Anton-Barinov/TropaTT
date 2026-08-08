<?php
declare(strict_types=1);

namespace Api\Controller\Dashboard;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\DashboardService;

final class DashboardController extends BaseController
{
    private const WIDGET_USER_SCOPE_PREFIX = 'user:';
    private const WIDGET_PREF_NAME = 'dashboard_widgets';

    private const WIDGET_DEFINITIONS = [
        'kpi' => ['label_key' => 'dashboard.widget_kpi', 'label' => 'KPI metrics', 'description_key' => 'dashboard.widget_kpi_desc', 'description' => 'Active tasks, overdue, active projects and weekly SLA counters.', 'size' => 'crm-col-12', 'icon' => 'fa-rectangle-list', 'default_enabled' => true, 'permissions' => ['task.manage']],
        'quick_actions' => ['label_key' => 'dashboard.quick_actions', 'label' => 'Quick actions', 'description_key' => 'dashboard.widget_quick_actions_desc', 'description' => 'Shortcuts to open the last task, assign an assignee, create a project and more.', 'size' => 'crm-col-12', 'icon' => 'fa-bolt', 'default_enabled' => true, 'permissions' => ['task.manage', 'project.manage']],
        'ai_digest' => ['label_key' => 'dashboard.ai_digest_title', 'label' => 'AI daily digest', 'description_key' => 'dashboard.widget_ai_digest_desc', 'description' => 'AI summary of risks, highlights and recommended actions for the day.', 'size' => 'crm-col-12', 'icon' => 'fa-wand-magic-sparkles', 'default_enabled' => true, 'permissions' => ['ai.use']],
        'today_tasks' => ['label_key' => 'dashboard.today_tasks', 'label' => 'Tasks for today', 'description_key' => 'dashboard.widget_today_tasks_desc', 'description' => 'Tasks with deadlines today with project, assignee and status.', 'size' => 'crm-col-12', 'icon' => 'fa-list-check', 'default_enabled' => true, 'permissions' => ['task.manage']],
        'risks' => ['label_key' => 'dashboard.risks_title', 'label' => 'Risks', 'description_key' => 'dashboard.widget_risks_desc', 'description' => 'Overdue, blocked tasks and risky projects alerts.', 'size' => 'crm-col-4', 'icon' => 'fa-triangle-exclamation', 'default_enabled' => true, 'permissions' => ['task.manage']],
        'activity' => ['label_key' => 'dashboard.activity_title', 'label' => 'Activity', 'description_key' => 'dashboard.widget_activity_desc', 'description' => 'Recent user activity feed.', 'size' => 'crm-col-4', 'icon' => 'fa-timeline', 'default_enabled' => true, 'permissions' => ['logs.view']],
        'projects_overview' => ['label_key' => 'dashboard.projects_overview', 'label' => 'Projects overview', 'description_key' => 'dashboard.widget_projects_overview_desc', 'description' => 'Unread notifications, events today, tasks and the last updated project.', 'size' => 'crm-col-4', 'icon' => 'fa-folder-open', 'default_enabled' => true, 'permissions' => ['project.manage']],
        'knowledge' => ['label_key' => 'dashboard.knowledge_title', 'label' => 'Knowledge base', 'description_key' => 'dashboard.widget_knowledge_desc', 'description' => 'Knowledge base stats and recent pages.', 'size' => 'crm-col-4', 'icon' => 'fa-book', 'default_enabled' => true, 'permissions' => ['knowledge.view']],
        'cycles' => ['label_key' => 'dashboard.cycles_title', 'label' => 'Active cycles', 'description_key' => 'dashboard.widget_cycles_desc', 'description' => 'Active work cycles with progress bars.', 'size' => 'crm-col-4', 'icon' => 'fa-rotate', 'default_enabled' => true, 'permissions' => ['task.manage']],
        'reminders' => ['label_key' => 'dashboard.widget_reminders', 'label' => 'My reminders', 'description_key' => 'dashboard.widget_reminders_desc', 'description' => 'Upcoming reminders for today.', 'size' => 'crm-col-4', 'icon' => 'fa-bell', 'default_enabled' => false, 'permissions' => ['task.manage']],
        'my_day' => ['label_key' => 'dashboard.widget_my_day', 'label' => 'My day', 'description_key' => 'dashboard.widget_my_day_desc', 'description' => 'Today events, tasks due and reminders in one block.', 'size' => 'crm-col-6', 'icon' => 'fa-sun', 'default_enabled' => false, 'permissions' => ['task.manage']],
        'sticky_notes' => ['label_key' => 'dashboard.widget_sticky_notes', 'label' => 'Sticky notes', 'description_key' => 'dashboard.widget_sticky_notes_desc', 'description' => 'Personal sticky notes with pinning and quick creation.', 'size' => 'crm-col-4', 'icon' => 'fa-note-sticky', 'default_enabled' => false, 'permissions' => ['task.manage']],
        'worklog' => ['label_key' => 'dashboard.widget_worklog', 'label' => 'My time', 'description_key' => 'dashboard.widget_worklog_desc', 'description' => 'Time logged today and this week with a 7-day breakdown.', 'size' => 'crm-col-6', 'icon' => 'fa-hourglass-half', 'default_enabled' => false, 'permissions' => ['task.manage']],
        'my_tasks' => ['label_key' => 'dashboard.widget_my_tasks', 'label' => 'My tasks', 'description_key' => 'dashboard.widget_my_tasks_desc', 'description' => 'Open tasks assigned to you, sorted by deadline and priority.', 'size' => 'crm-col-12', 'icon' => 'fa-user-check', 'default_enabled' => false, 'permissions' => ['task.manage']],
        'approvals' => ['label_key' => 'dashboard.widget_approvals', 'label' => 'Pending approvals', 'description_key' => 'dashboard.widget_approvals_desc', 'description' => 'Approval requests currently awaiting your review.', 'size' => 'crm-col-4', 'icon' => 'fa-file-circle-check', 'default_enabled' => false, 'permissions' => ['approval.manage']],
        'milestones' => ['label_key' => 'dashboard.widget_milestones', 'label' => 'Upcoming milestones', 'description_key' => 'dashboard.widget_milestones_desc', 'description' => 'Nearest milestones across your active projects.', 'size' => 'crm-col-4', 'icon' => 'fa-flag-checkered', 'default_enabled' => false, 'permissions' => ['project.manage']],
        'favorites' => ['label_key' => 'dashboard.widget_favorites', 'label' => 'Favorites', 'description_key' => 'dashboard.widget_favorites_desc', 'description' => 'One-click links to your pinned tasks, projects and comments.', 'size' => 'crm-col-4', 'icon' => 'fa-star', 'default_enabled' => false, 'permissions' => ['task.manage']],
        'intake' => ['label_key' => 'dashboard.widget_intake', 'label' => 'Incoming requests', 'description_key' => 'dashboard.widget_intake_desc', 'description' => 'Pending intake items from clients awaiting processing.', 'size' => 'crm-col-4', 'icon' => 'fa-inbox', 'default_enabled' => false, 'permissions' => ['intake.view']],
        'my_week' => ['label_key' => 'dashboard.widget_my_week', 'label' => 'My week', 'description_key' => 'dashboard.widget_my_week_desc', 'description' => 'Week agenda: events, tasks due and reminders grouped by day.', 'size' => 'crm-col-12', 'icon' => 'fa-calendar-days', 'default_enabled' => false, 'permissions' => ['task.manage']],
        'recurring' => ['label_key' => 'dashboard.widget_recurring', 'label' => 'Recurring tasks', 'description_key' => 'dashboard.widget_recurring_desc', 'description' => 'Active recurring rules that generate tasks on a schedule.', 'size' => 'crm-col-4', 'icon' => 'fa-arrows-rotate', 'default_enabled' => false, 'permissions' => ['task.manage']],
        'mentions' => ['label_key' => 'dashboard.widget_mentions', 'label' => 'My mentions', 'description_key' => 'dashboard.widget_mentions_desc', 'description' => 'Tasks, projects and comments where you were mentioned.', 'size' => 'crm-col-4', 'icon' => 'fa-at', 'default_enabled' => false, 'permissions' => ['task.manage']],
        'unassigned' => ['label_key' => 'dashboard.widget_unassigned', 'label' => 'Unassigned tasks', 'description_key' => 'dashboard.widget_unassigned_desc', 'description' => 'Open tasks without an assignee, sorted by deadline and priority.', 'size' => 'crm-col-6', 'icon' => 'fa-user-slash', 'default_enabled' => false, 'permissions' => ['task.manage']],
        'analytics_summary' => ['label_key' => 'dashboard.extra_analytics_summary', 'label' => 'Analytics pulse', 'description_key' => 'dashboard.extra_analytics_summary_desc', 'description' => 'A compact snapshot of the main analytics indicators.', 'size' => 'crm-col-4', 'icon' => 'fa-chart-line', 'default_enabled' => false, 'permissions' => ['task.manage']],
        'project_health' => ['label_key' => 'dashboard.extra_project_health', 'label' => 'Project health', 'description_key' => 'dashboard.extra_project_health_desc', 'description' => 'Projects with progress, deadlines and current health signals.', 'size' => 'crm-col-6', 'icon' => 'fa-heart-pulse', 'default_enabled' => false, 'permissions' => ['task.manage']],
        'team_workload' => ['label_key' => 'dashboard.extra_team_workload', 'label' => 'Team workload', 'description_key' => 'dashboard.extra_team_workload_desc', 'description' => 'Workload distribution across visible team members.', 'size' => 'crm-col-6', 'icon' => 'fa-people-group', 'default_enabled' => false, 'permissions' => ['task.manage']],
        'time_team' => ['label_key' => 'dashboard.extra_time_team', 'label' => 'Team time', 'description_key' => 'dashboard.extra_time_team_desc', 'description' => 'Hours logged per visible user over the last 7 days with overload and underload signals.', 'size' => 'crm-col-6', 'icon' => 'fa-hourglass-half', 'default_enabled' => false, 'permissions' => ['task.manage']],
        'notification_inbox' => ['label_key' => 'dashboard.extra_notification_inbox', 'label' => 'Notification inbox', 'description_key' => 'dashboard.extra_notification_inbox_desc', 'description' => 'Recent notifications requiring attention.', 'size' => 'crm-col-4', 'icon' => 'fa-inbox', 'default_enabled' => false, 'permissions' => ['task.manage']],
        'chat_unread' => ['label_key' => 'dashboard.extra_chat_unread', 'label' => 'Unread chats', 'description_key' => 'dashboard.extra_chat_unread_desc', 'description' => 'Unread team conversations at a glance.', 'size' => 'crm-col-4', 'icon' => 'fa-comments', 'default_enabled' => false, 'permissions' => ['chat.use']],
        'client_pipeline' => ['label_key' => 'dashboard.extra_client_pipeline', 'label' => 'Client directory', 'description_key' => 'dashboard.extra_client_pipeline_desc', 'description' => 'Recent clients and relationship records available to you.', 'size' => 'crm-col-4', 'icon' => 'fa-handshake', 'default_enabled' => false, 'permissions' => ['client.manage']],
        'company_directory' => ['label_key' => 'dashboard.extra_company_directory', 'label' => 'Company directory', 'description_key' => 'dashboard.extra_company_directory_desc', 'description' => 'A quick view of companies in the CRM.', 'size' => 'crm-col-4', 'icon' => 'fa-building', 'default_enabled' => false, 'permissions' => ['company.manage']],
        'contact_followups' => ['label_key' => 'dashboard.extra_contact_followups', 'label' => 'Contact follow-ups', 'description_key' => 'dashboard.extra_contact_followups_desc', 'description' => 'Recently updated contacts and their communication details.', 'size' => 'crm-col-4', 'icon' => 'fa-address-book', 'default_enabled' => false, 'permissions' => ['contact.manage']],
        'tag_usage' => ['label_key' => 'dashboard.extra_tag_usage', 'label' => 'Tag usage', 'description_key' => 'dashboard.extra_tag_usage_desc', 'description' => 'Most used tags for keeping work organized.', 'size' => 'crm-col-4', 'icon' => 'fa-tags', 'default_enabled' => false, 'permissions' => ['task.manage']],
        'saved_views' => ['label_key' => 'dashboard.extra_saved_views', 'label' => 'Saved views', 'description_key' => 'dashboard.extra_saved_views_desc', 'description' => 'Pinned and recently used task views.', 'size' => 'crm-col-4', 'icon' => 'fa-bookmark', 'default_enabled' => false, 'permissions' => ['task.manage']],
        'subscriptions' => ['label_key' => 'dashboard.extra_subscriptions', 'label' => 'My subscriptions', 'description_key' => 'dashboard.extra_subscriptions_desc', 'description' => 'Entities you follow for important updates.', 'size' => 'crm-col-4', 'icon' => 'fa-rss', 'default_enabled' => false, 'permissions' => ['task.manage']],
        'dependency_watch' => ['label_key' => 'dashboard.extra_dependency_watch', 'label' => 'Dependency watch', 'description_key' => 'dashboard.extra_dependency_watch_desc', 'description' => 'Task dependencies that can block delivery.', 'size' => 'crm-col-6', 'icon' => 'fa-link', 'default_enabled' => false, 'permissions' => ['task.manage']],
        'milestone_watch' => ['label_key' => 'dashboard.extra_milestone_watch', 'label' => 'Milestone watch', 'description_key' => 'dashboard.extra_milestone_watch_desc', 'description' => 'Upcoming project milestones and their dates.', 'size' => 'crm-col-6', 'icon' => 'fa-flag-checkered', 'default_enabled' => false, 'permissions' => ['project.manage']],
        'recurring_health' => ['label_key' => 'dashboard.extra_recurring_health', 'label' => 'Recurring automation', 'description_key' => 'dashboard.extra_recurring_health_desc', 'description' => 'Active recurring task rules and next runs.', 'size' => 'crm-col-4', 'icon' => 'fa-arrows-rotate', 'default_enabled' => false, 'permissions' => ['task.manage']],
        'approval_queue' => ['label_key' => 'dashboard.extra_approval_queue', 'label' => 'Approval queue', 'description_key' => 'dashboard.extra_approval_queue_desc', 'description' => 'Approval requests waiting for a decision.', 'size' => 'crm-col-4', 'icon' => 'fa-file-signature', 'default_enabled' => false, 'permissions' => ['approval.manage']],
        'intake_sla' => ['label_key' => 'dashboard.extra_intake_sla', 'label' => 'Intake SLA', 'description_key' => 'dashboard.extra_intake_sla_desc', 'description' => 'Incoming requests with priority and processing status.', 'size' => 'crm-col-4', 'icon' => 'fa-inbox', 'default_enabled' => false, 'permissions' => ['intake.view']],
        'webhook_health' => ['label_key' => 'dashboard.extra_webhook_health', 'label' => 'Webhook health', 'description_key' => 'dashboard.extra_webhook_health_desc', 'description' => 'Recent webhook deliveries and failures.', 'size' => 'crm-col-6', 'icon' => 'fa-satellite-dish', 'default_enabled' => false, 'permissions' => ['webhook.manage']],
        'workflow_automation' => ['label_key' => 'dashboard.extra_workflow_automation', 'label' => 'Workflow automation', 'description_key' => 'dashboard.extra_workflow_automation_desc', 'description' => 'Active workflow rules and automation status.', 'size' => 'crm-col-6', 'icon' => 'fa-gears', 'default_enabled' => false, 'permissions' => ['settings.manage']],
        'system_health' => ['label_key' => 'dashboard.extra_system_health', 'label' => 'System health', 'description_key' => 'dashboard.extra_system_health_desc', 'description' => 'A lightweight health signal for the current installation.', 'size' => 'crm-col-4', 'icon' => 'fa-heart-pulse', 'default_enabled' => false, 'permissions' => []],
        'active_sessions' => ['label_key' => 'dashboard.extra_active_sessions', 'label' => 'Active sessions', 'description_key' => 'dashboard.extra_active_sessions_desc', 'description' => 'Your currently active sessions and devices.', 'size' => 'crm-col-4', 'icon' => 'fa-shield-halved', 'default_enabled' => false, 'permissions' => ['logs.view']],
    ];

    public function summary(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var DashboardService $service */
        $service = $this->container->get('service.dashboard');
        $summary = $service->summary($authUser['user']);

        return $this->success('DASHBOARD_SUMMARY', $this->t('dashboard/messages.summary'), ['summary' => $summary]);
    }

    public function widgets(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $user = $authUser['user'] ?? [];
        $userPublicId = (string)($user['public_id'] ?? '');
        if ($userPublicId === '') {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $active = $this->resolveActive($this->loadWidgetPreference($userPublicId), $user);

        return $this->success('DASHBOARD_WIDGETS', $this->t('dashboard/messages.widgets'), [
            'catalog' => $this->buildCatalog($user),
            'active' => $active,
            'widgets' => $this->buildLegacyWidgets($active, $user),
        ]);
    }

    public function saveWidgets(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $user = $authUser['user'] ?? [];
        $userPublicId = (string)($user['public_id'] ?? '');
        if ($userPublicId === '') {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $activeRaw = $input['active'] ?? null;

        if (is_array($activeRaw)) {
            $active = $this->normalizeActive($activeRaw);
        } else {
            $items = $input['widgets'] ?? null;
            if (!is_array($items)) {
                return $this->error('VALIDATION_ERROR', $this->t('dashboard/messages.widgets_required'), 422);
            }
            $active = [];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $key = $item['key'] ?? null;
                if (!is_string($key) || !array_key_exists($key, self::WIDGET_DEFINITIONS)) {
                    continue;
                }
                if ((bool)($item['enabled'] ?? true) && !in_array($key, $active, true)) {
                    $active[] = $key;
                }
            }
        }

        $active = $this->resolveActive($active, $user);

        /** @var \Api\System\Library\Service\SettingService $settingService */
        $settingService = $this->container->get('service.setting');
        $settingService->set(self::WIDGET_USER_SCOPE_PREFIX . $userPublicId, self::WIDGET_PREF_NAME, $active);

        return $this->success('DASHBOARD_WIDGETS_SAVED', $this->t('dashboard/messages.widgets_saved'), [
            'catalog' => $this->buildCatalog($user),
            'active' => $active,
            'widgets' => $this->buildLegacyWidgets($active, $user),
        ]);
    }

    private function loadWidgetPreference(string $userPublicId): mixed
    {
        if ($userPublicId === '' || !$this->container->has('service.setting')) {
            return [];
        }

        try {
            /** @var \Api\System\Library\Service\SettingService $settingService */
            $settingService = $this->container->get('service.setting');
            $setting = $settingService->get(self::WIDGET_USER_SCOPE_PREFIX . $userPublicId, self::WIDGET_PREF_NAME);
            if ($setting === null) {
                return [];
            }
            $value = $setting['value'] ?? [];
            return is_array($value) ? $value : [];
        } catch (\Throwable $e) {
            error_log('[DashboardController::loadWidgetPreference] ' . $e->getMessage());
            return [];
        }
    }

    private function normalizeActive(array $raw): array
    {
        $seen = [];
        $active = [];
        foreach ($raw as $key) {
            if (!is_string($key) || !array_key_exists($key, self::WIDGET_DEFINITIONS)) {
                continue;
            }
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $active[] = $key;
        }

        return $active;
    }

    private function resolveActive(array $stored, array $user): array
    {
        $candidate = [];
        if (empty($stored)) {
            foreach (self::WIDGET_DEFINITIONS as $key => $definition) {
                if ((bool)($definition['default_enabled'] ?? false)) {
                    $candidate[] = $key;
                }
            }
        } elseif (array_is_list($stored)) {
            $candidate = $this->normalizeActive($stored);
        } else {
            // Legacy format: associative map key => enabled boolean.
            foreach (self::WIDGET_DEFINITIONS as $key => $definition) {
                if (array_key_exists($key, $stored) && (bool)$stored[$key]) {
                    $candidate[] = $key;
                }
            }
        }

        return array_values(array_filter($candidate, fn (string $key): bool => $this->widgetAllowed($key, $user)));
    }

    private function widgetAllowed(string $key, array $user): bool
    {
        $definition = self::WIDGET_DEFINITIONS[$key] ?? null;
        if (!is_array($definition)) {
            return false;
        }

        $required = array_values(array_filter(array_map('strval', (array)($definition['permissions'] ?? []))));
        if ($required === []) {
            return true;
        }

        if ((bool)($user['is_root'] ?? false)) {
            return true;
        }

        // Permission checks must fail closed. The user-provided auth context and
        // widget preferences must never become an authorization fallback when
        // the authoritative RBAC service is unavailable or throws.
        if (!$this->container->has('service.authz')) {
            error_log('[DashboardController::widgetAllowed] Authorization service is unavailable');
            return false;
        }

        try {
            /** @var \\Api\\System\\Library\\Service\\AuthzService $authz */
            $authz = $this->container->get('service.authz');
            return $authz->hasPermissions($user, $required);
        } catch (\Throwable $e) {
            error_log('[DashboardController::widgetAllowed] ' . $e->getMessage());
            return false;
        }
    }

    private function buildCatalog(array $user): array
    {
        $catalog = [];
        foreach (self::WIDGET_DEFINITIONS as $key => $definition) {
            if (!$this->widgetAllowed($key, $user)) {
                continue;
            }
            $catalog[] = [
                'key' => $key,
                'label_key' => $definition['label_key'],
                'label' => $definition['label'],
                'description_key' => $definition['description_key'],
                'description' => $definition['description'],
                'size' => $definition['size'],
                'icon' => $definition['icon'],
                'permissions' => $definition['permissions'] ?? [],
                'default_enabled' => (bool)($definition['default_enabled'] ?? false),
            ];
        }

        return $catalog;
    }

    private function buildLegacyWidgets(array $active, array $user): array
    {
        $widgets = [];
        foreach (self::WIDGET_DEFINITIONS as $key => $definition) {
            if (!$this->widgetAllowed($key, $user)) {
                continue;
            }
            $widgets[] = [
                'key' => $key,
                'label_key' => $definition['label_key'],
                'label' => $definition['label'],
                'enabled' => in_array($key, $active, true),
                'default_enabled' => (bool)($definition['default_enabled'] ?? false),
            ];
        }

        return $widgets;
    }
}
