<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Service;

use PDO;

/**
 * Routing Matrix: maps entity_type and form_id to target CRM funnel/project,
 * priority, SLA timers, and assignee rules (E-COM-11 §3).
 */
final class RoutingMatrixService
{
    /** Default SLA response limits per entity type in minutes. */
    public const DEFAULT_SLA_MINUTES = [
        'callback' => 5,        // Urgent phone callbacks: 5 min SLA
        'order' => 15,          // Store orders: 15 min SLA
        'quick_order' => 10,    // 1-click orders: 10 min SLA
        'feedback' => 120,      // Customer feedback: 2 hours SLA
        'form' => 30,           // General leads / forms: 30 min SLA
    ];

    /** Default priority per entity type. */
    public const DEFAULT_PRIORITIES = [
        'callback' => 'urgent',
        'order' => 'high',
        'quick_order' => 'urgent',
        'feedback' => 'normal',
        'form' => 'normal',
    ];

    public function __construct(private readonly ?PDO $pdo = null)
    {
    }

    /**
     * Resolves target project, priority, assignee and SLA deadlines for an intake item.
     *
     * @param string $type Ingestion entity type (order, callback, quick_order, feedback, form)
     * @param array<string,mixed> $store Store record
     * @param array<string,mixed> $payload Validated payload
     * @return array{
     *     project_id: ?int,
     *     project_public_id: ?string,
     *     priority: string,
     *     assignee_user_id: ?int,
     *     sla_minutes: int,
     *     sla_deadline: string
     * }
     */
    public function resolveRoute(string $type, array $store, array $payload = []): array
    {
        $settings = json_decode((string)($store['settings_json'] ?? '{}'), true) ?: [];
        $routingMatrix = is_array($settings['routing_matrix'] ?? null) ? $settings['routing_matrix'] : [];

        $formId = trim((string)($payload['form_id'] ?? ''));

        // 1. Check specific form_id override in routing_matrix
        $rule = null;
        if ($formId !== '' && isset($routingMatrix["form:{$formId}"]) && is_array($routingMatrix["form:{$formId}"])) {
            $rule = $routingMatrix["form:{$formId}"];
        }

        // 2. Check entity_type override
        if ($rule === null && isset($routingMatrix[$type]) && is_array($routingMatrix[$type])) {
            $rule = $routingMatrix[$type];
        }

        // 3. Resolve Project
        $projectId = isset($rule['project_id']) && is_numeric($rule['project_id'])
            ? (int)$rule['project_id']
            : (isset($settings['default_project_id']) && is_numeric($settings['default_project_id']) ? (int)$settings['default_project_id'] : null);

        $projectPublicId = null;
        if ($projectId !== null && $this->pdo !== null) {
            $projectPublicId = $this->resolveProjectPublicId($projectId);
        }

        // 4. Resolve Priority
        $priority = !empty($rule['priority']) && is_string($rule['priority'])
            ? $rule['priority']
            : (self::DEFAULT_PRIORITIES[$type] ?? (string)($settings['default_priority_code'] ?? 'normal'));

        // 5. Resolve Assignee (specific user ID or Round-robin fallback)
        $assigneeUserId = isset($rule['assignee_id']) && is_numeric($rule['assignee_id'])
            ? (int)$rule['assignee_id']
            : (isset($settings['default_assignee_id']) && is_numeric($settings['default_assignee_id']) ? (int)$settings['default_assignee_id'] : null);

        // 6. Resolve SLA
        $slaMinutes = isset($rule['sla_minutes']) && is_numeric($rule['sla_minutes'])
            ? (int)$rule['sla_minutes']
            : (self::DEFAULT_SLA_MINUTES[$type] ?? 30);

        $slaDeadline = gmdate('Y-m-d H:i:s', time() + ($slaMinutes * 60));

        return [
            'project_id' => $projectId,
            'project_public_id' => $projectPublicId,
            'priority' => $priority,
            'assignee_user_id' => $assigneeUserId,
            'sla_minutes' => $slaMinutes,
            'sla_deadline' => $slaDeadline,
        ];
    }

    private function resolveProjectPublicId(int $projectId): ?string
    {
        if ($this->pdo === null || $projectId <= 0) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT public_id FROM projects WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $projectId]);
            $publicId = $stmt->fetchColumn();

            return $publicId ? (string)$publicId : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
