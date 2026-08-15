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
 */
final class PositionRegistry
{
    private static ?PositionRegistry $instance = null;

    /** @var array<string, array<int, array{renderer: callable, priority: int}>> */
    private array $positions = [];

    public static function setInstance(PositionRegistry $registry): void
    {
        self::$instance = $registry;
    }

    public static function getInstance(): ?PositionRegistry
    {
        return self::$instance;
    }

    public function register(string $position, callable $renderer, int $priority = 10): void
    {
        $this->positions[$position][] = ['renderer' => $renderer, 'priority' => $priority];
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
                    $html[] = $result;
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
}
