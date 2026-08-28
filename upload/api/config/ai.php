<?php
declare(strict_types=1);

// SEC-002: Block direct web access
if (PHP_SAPI !== 'cli' && ($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}


$storageBase = (string)(getenv('CRM_STORAGE_BASE') ?: dirname(__DIR__, 1) . '/../storage_api');

return [
    'enabled_by_default' => false,
    'provider' => [
        'allowed_schemes' => ['https', 'http'],
        'block_private_networks_in_production' => true,
    ],
    'actions' => [
        'allowlist' => [
            'task_summary',
            'task_decomposition',
            'task_checklist',
            'task_quality',
            'task_next_action',
            'task_comment_draft',
            'project_summary',
            'project_risk_summary',
            'project_client_report',
            'client_summary',
            'client_meeting_prep',
            'client_data_quality',
            'client_safe_report',
            'calendar_event_agenda',
            'dashboard_daily_digest',
            'analytics_kpi_explanation',
            'analytics_risks_explanation',
            'analytics_team_workload_summary',
            'admin_log_review',
            'webhook_health_review',
            'workflow_rule_audit',
            'my_day_plan',
            'my_week_plan',
            'task_list_priority',
            'knowledge_summary',
            'knowledge_simplify',
        ],
    ],
    'intent_settings' => [
        'allowlist' => [
            'daily_work_plan',
            'security_log_review',
            'semantic_search',
        ],
    ],
    'retention' => [
        'suggestions_ttl_days' => 30,
        'jobs_ttl_days' => 30,
        'usage_logs_ttl_days' => 90,
        'prompts_ttl_days' => 30,
    ],
    'storage' => [
        'base' => $storageBase . '/ai',
        'cache' => $storageBase . '/ai/cache',
        'jobs' => $storageBase . '/ai/jobs',
    ],
];
