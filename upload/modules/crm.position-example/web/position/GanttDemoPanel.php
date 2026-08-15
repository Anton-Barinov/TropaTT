<?php
declare(strict_types=1);

namespace Module\Crm\PositionExample\Position;

/**
 * Example position renderer.
 *
 * Demonstrates how a self-contained module injects content into a core page
 * (the "gantt.content.after" slot) without editing the core. The renderer is
 * declared in manifest.json under "positions" and resolved by the web bootstrap
 * into a static [Class, method] callable.
 */
final class GanttDemoPanel
{
    /**
     * @param array<string, mixed> $context
     */
    public static function render(array $context): string
    {
        $route = (string)($context['route'] ?? 'gantt');

        return '<section class="crm-card crm-position-example-panel" data-position-example data-position="gantt.content.after">'
            . '<div class="crm-section-head"><div>'
            . '<h2 class="h6 mb-0">Position example</h2>'
            . '<div class="crm-section-note">Injected by <code>crm.position-example</code> into <code>gantt.content.after</code>.</div>'
            . '</div></div>'
            . '<p class="text-muted small mb-0">Route: <code>' . htmlspecialchars($route, ENT_QUOTES, 'UTF-8') . '</code>. '
            . 'This panel is rendered by a module position renderer; its styles and script are loaded only on this route.</p>'
            . '<p class="text-muted small mb-0" data-position-example-js>Scoped script not loaded yet…</p>'
            . '</section>';
    }
}
