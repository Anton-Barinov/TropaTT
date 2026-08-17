<?php
declare(strict_types=1);

namespace Web\System\Module;

/**
 * Named content positions ("slots") that core templates expose and modules fill.
 *
 * A page template calls module_position('task.detail.sidebar', $context); every
 * active module that registered a renderer for that position contributes its
 * HTML. Positions keep module output predictable and let the same module inject
 * content into several pages without any core edit.
 *
 * For configurable positions (currently "task.detail.sidebar") each entry may
 * carry a stable "key". When a key is present the renderer output is wrapped in
 * a marker element so the frontend can hide/reorder blocks individually.
 */
final class PositionRegistry
{
    private static ?PositionRegistry $instance = null;

    /** @var array<string, array<int, array{renderer: callable, priority: int, key: string}>> */
    private array $positions = [];

    public static function setInstance(PositionRegistry $registry): void
    {
        self::$instance = $registry;
    }

    public static function getInstance(): ?PositionRegistry
    {
        return self::$instance;
    }

    public function register(string $position, callable $renderer, int $priority = 10, string $key = ''): void
    {
        $this->positions[$position][] = ['renderer' => $renderer, 'priority' => $priority, 'key' => $key];
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(string $position, array $context = []): string
    {
        if (!isset($this->positions[$position]) || $this->positions[$position] === []) {
            return '';
        }

        $entries = $this->positions[$position];
        usort($entries, static fn(array $a, array $b): int => $b['priority'] <=> $a['priority']);

        $html = [];
        foreach ($entries as $entry) {
            try {
                $result = ($entry['renderer'])($context);
                if (is_string($result) && $result !== '') {
                    $html[] = $this->wrap($result, (string)($entry['key'] ?? ''));
                }
            } catch (\Throwable $e) {
                error_log(sprintf('[PositionRegistry] Error rendering "%s": %s', $position, $e->getMessage()));
            }
        }

        return implode("\n", $html);
    }

    public function has(string $position): bool
    {
        return isset($this->positions[$position]) && $this->positions[$position] !== [];
    }

    private function wrap(string $html, string $key): string
    {
        if ($key === '') {
            return $html;
        }

        return '<div class="crm-task-sidebar-module-block" data-task-sidebar-block="'
            . htmlspecialchars($key, ENT_QUOTES, 'UTF-8')
            . '">' . $html . '</div>';
    }

    /**
     * Derive a stable block key from a renderer "Class::method" string.
     * MUST match Api\Controller\Task\TaskSidebarController::deriveBlockKey().
     */
    public static function deriveBlockKey(string $renderer): string
    {
        $class = str_contains($renderer, '::') ? explode('::', $renderer, 2)[0] : $renderer;
        $parts = explode('\\', $class);
        $short = end($parts);
        $short = is_string($short) ? $short : '';
        $snake = strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $short));
        $snake = (string)preg_replace('/[^a-z0-9_]+/', '_', $snake);

        return trim($snake, '_');
    }
}
