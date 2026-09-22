<?php
declare(strict_types=1);

// SEC-002: Block direct web access
if (PHP_SAPI !== 'cli' && ($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}


$storageBase = (string)(getenv('CRM_STORAGE_BASE') ?: dirname(__DIR__, 2) . '/storage_api');

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
            // TROPATTCRM-618: one intent per step of the Ideas AI-analysis
            // pipeline, so model / max_tokens / temperature can be tuned (and
            // usage measured) per step instead of through one shared code.
            'idea_interview',
            'idea_clarifications',
            'idea_understanding',
            'idea_gap_questions',
            'idea_refined',
            'idea_potential',
            'idea_risks',
            'idea_pitfalls',
            'idea_plan',
            'idea_final',
            'idea_tasks',
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
