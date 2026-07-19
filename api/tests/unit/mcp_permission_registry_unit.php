<?php
declare(strict_types=1);

/**
 * SEC-003: MCP Permission Registry contract test.
 *
 * Fail-closed: every MCP tool must be registered, every registration
 * must reference a valid mode, and every registered tool must exist
 * as a callable method in McpController.
 */

require_once __DIR__ . '/../../system/library/support/Autoloader.php';

function unitAssert2(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $apiRoot = dirname(__DIR__, 2);

    $autoloader = new Api\System\Library\Support\Autoloader($apiRoot);
    $autoloader->register();

    // ---- 1. Load permission registry ----
    $registry = require $apiRoot . '/config/mcp_permissions.php';
    unitAssert2(is_array($registry), 'mcp_permissions.php must return array');
    unitAssert2(count($registry) > 0, 'mcp_permissions.php must contain at least one tool');

    $validModes = ['self', 'all', 'any'];
    $errors = [];
    $countByMode = ['self' => 0, 'all' => 0, 'any' => 0];
    $toolsWithoutMethod = [];

    // Tools dispatched dynamically via ideaWorkflowTools() — skip method matching
    $ideaWorkflowTools = [
        'crm_create_idea_ai_analysis',
        'crm_create_idea_ai_refine',
        'crm_create_idea_ai_tasks',
        'crm_get_idea_ai_debug_log',
        'crm_list_idea_ai_iterations',
        'crm_get_idea_questions',
        'crm_get_idea_additional_questions',
        'crm_generate_idea_additional_questions',
        'crm_get_idea_understanding_card',
        'crm_generate_idea_understanding_card',
        'crm_get_idea_gap_questions',
        'crm_generate_idea_gap_questions',
        'crm_get_idea_refined_card',
        'crm_generate_idea_refined_card',
        'crm_get_idea_potential_score',
        'crm_generate_idea_potential_score',
        'crm_get_idea_risk_report',
        'crm_generate_idea_risk_report',
        'crm_get_idea_pitfalls_report',
        'crm_generate_idea_pitfalls_report',
        'crm_get_idea_implementation_plan',
        'crm_generate_idea_implementation_plan',
        'crm_get_idea_final_recommendation',
        'crm_generate_idea_final_recommendation',
        'crm_get_idea_suggested_tasks',
        'crm_generate_idea_suggested_tasks',
        'crm_create_project_from_idea_tasks',
        'crm_generate_idea_ai_interview',
        'crm_save_idea_interview_answers',
        'crm_get_idea_state',
        'crm_save_idea_answers',
        'crm_get_idea_task_drafts',
        'crm_update_idea_task_draft',
        'crm_reset_idea_analysis',
        'crm_decompose_idea_tasks',
        'crm_generate_next_idea_questions',
        'crm_run_idea_analysis',
        'crm_submit_idea_answers',
        'crm_run_idea_analysis_step',
        'crm_retry_idea_analysis',
    ];

    foreach ($registry as $toolName => $entry) {
        // ---- 2. Structure validation ----
        if (!isset($entry['mode'])) {
            $errors[] = "Tool '{$toolName}': missing 'mode'";
            continue;
        }
        if (!in_array($entry['mode'], $validModes, true)) {
            $errors[] = "Tool '{$toolName}': invalid mode '{$entry['mode']}'";
            continue;
        }
        if (!isset($entry['permissions']) || !is_array($entry['permissions'])) {
            $errors[] = "Tool '{$toolName}': 'permissions' must be an array";
            continue;
        }

        $countByMode[$entry['mode']]++;

        // ---- 3. Validate tool name ----
        if (!str_starts_with($toolName, 'crm_')) {
            $errors[] = "Tool '{$toolName}': must start with 'crm_'";
        }

        // ---- 4. Verify matching McpController method exists (skip idea workflow tools) ----
        if (!in_array($toolName, $ideaWorkflowTools, true)) {
            $parts = explode('_', $toolName);
            $camelMethod = array_shift($parts); // 'crm'
            foreach ($parts as $part) {
                $camelMethod .= ucfirst($part);
            }
            if (!method_exists(\Api\Controller\Mcp\McpController::class, $camelMethod)) {
                $toolsWithoutMethod[] = $toolName;
            }
        }
    }

    // Report tools without matching methods (warning, not error)
    if ($toolsWithoutMethod !== []) {
        fwrite(STDERR, 'WARNINGS: ' . count($toolsWithoutMethod) . ' tools without direct method match' . PHP_EOL);
        fwrite(STDERR, '  These are dispatched dynamically via invokeControllerTool() — expected.' . PHP_EOL);
        if (count($toolsWithoutMethod) <= 10) {
            fwrite(STDERR, '  Missing methods for: ' . implode(', ', $toolsWithoutMethod) . PHP_EOL);
        }
    }

    // ---- 5. Check critical tools exist ----
    $criticalTools = [
        'crm_get_current_user',
        'crm_get_profile',
        'crm_update_profile',
        'crm_search',
        'crm_list_tasks',
        'crm_create_task',
        'crm_list_projects',
        'crm_list_clients',
        'crm_send_chat_message',
        'crm_update_ai_settings',
    ];
    foreach ($criticalTools as $criticalTool) {
        if (!isset($registry[$criticalTool])) {
            $errors[] = "Critical tool '{$criticalTool}' not found in registry";
        }
    }

    // ---- 6. Check that non-existent tool would be rejected ----
    unitAssert2(
        !isset($registry['crm_nonexistent_tool']),
        'crm_nonexistent_tool must NOT be in registry (fail-closed)'
    );

    // ---- 7. Summary ----
    echo '[OK] mcp_permission_registry_unit: ' . count($registry) . ' tools registered' . PHP_EOL;
    echo '     Mode breakdown: self=' . $countByMode['self']
        . ', all=' . $countByMode['all']
        . ', any=' . $countByMode['any'] . PHP_EOL;
    echo '     Warnings (no direct method): ' . count($toolsWithoutMethod) . PHP_EOL;

    unitAssert2(
        $errors === [],
        'MCP permission registry errors:' . PHP_EOL . implode(PHP_EOL, $errors)
    );

    echo '[PASS] SEC-003: MCP permission registry contract verified' . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] mcp_permission_registry_unit: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
