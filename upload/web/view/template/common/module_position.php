<?php
declare(strict_types=1);

use Web\System\Module\PositionRegistry;

if (!function_exists('module_position')) {
    /**
     * Render the content contributed by active modules for a named position.
     *
     * Usage inside a page template:
     *   <?= module_position('task.detail.sidebar', ['task' => $task]) ?>
     *
     * @param array<string, mixed> $context
     */
    function module_position(string $position, array $context = []): string
    {
        $registry = PositionRegistry::getInstance();
        if ($registry === null) {
            return '';
        }

        return $registry->render($position, $context);
    }
}
