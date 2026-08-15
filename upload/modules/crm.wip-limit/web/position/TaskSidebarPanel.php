<?php
declare(strict_types=1);

namespace Module\Crm\WipLimit\Position;

/**
 * Injects a WIP panel into the core task detail sidebar (position
 * "task.detail.sidebar"). Declared declaratively in manifest.json under
 * "positions", so the core renders it without any core-side edit.
 */
final class TaskSidebarPanel
{
    /**
     * @param array<string, mixed> $context
     */
    public static function render(array $context): string
    {
        $taskPublicId = trim((string)($context['task_public_id'] ?? ''));
        if ($taskPublicId === '') {
            return '';
        }

        $taskPublicId = htmlspecialchars($taskPublicId, ENT_QUOTES, 'UTF-8');

        return '<div class="crm-card mb-3" data-wip-task-panel data-task-public-id="' . $taskPublicId . '">'
            . '<div class="crm-side-card-head"><div>'
            . '<div class="crm-task-eyebrow">WIP</div>'
            . '<h2 class="h6 mb-0">WIP-лимиты</h2>'
            . '</div></div>'
            . '<div class="p-3">'
            . '<div data-wip-task-content><span class="text-muted small">Загрузка…</span></div>'
            . '<a class="small d-inline-block mt-2" href="index.php?route=module-wip-limit">Настроить лимиты →</a>'
            . '</div></div>';
    }
}
