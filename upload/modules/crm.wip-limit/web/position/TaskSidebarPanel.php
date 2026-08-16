<?php
declare(strict_types=1);

namespace Module\Crm\WipLimit\Position;

/**
 * Injects a WIP panel into the core task detail sidebar (position
 * "task.detail.sidebar"). Declared declaratively in manifest.json under
 * "positions", so the core renders it without any core-side edit.
 *
 * The panel shows the task assignee's live WIP load and lets the user edit the
 * assignee's limit inline; the JS hydrates it from the module's own endpoint.
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
            . '<div data-wip-assignee-editor class="mt-3 d-none">'
            . '<label class="form-label small mb-1">Лимит исполнителя: <strong data-wip-assignee-name></strong></label>'
            . '<div class="input-group input-group-sm">'
            . '<input type="number" min="1" max="50" class="form-control" data-wip-limit-input value="5">'
            . '<button class="btn crm-btn-primary" type="button" data-wip-save>Сохранить</button>'
            . '</div>'
            . '<div class="form-text" data-wip-status></div>'
            . '</div>'
            . '<a class="small d-inline-block mt-2" href="index.php?route=module-wip-limit">Все лимиты →</a>'
            . '</div></div>';
    }
}
