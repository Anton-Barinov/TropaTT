<?php
declare(strict_types=1);

namespace Api\System\Library\Hook;

use RuntimeException;

final class HookManager
{
    /** @var array<string, array<int, array<callable>>> */
    private array $hooks = [];

    public function register(string $hookName, callable $handler, int $priority = 10): void
    {
        if (!isset($this->hooks[$hookName])) {
            $this->hooks[$hookName] = [];
        }
        if (!isset($this->hooks[$hookName][$priority])) {
            $this->hooks[$hookName][$priority] = [];
        }
        $this->hooks[$hookName][$priority][] = $handler;
    }

    public function dispatch(string $hookName, array &$context = []): void
    {
        if (!isset($this->hooks[$hookName])) {
            return;
        }

        ksort($this->hooks[$hookName]);

        foreach ($this->hooks[$hookName] as $priority => $handlers) {
            foreach ($handlers as $handler) {
                try {
                    $handler($context);
                } catch (\Throwable $e) {
                    error_log(sprintf(
                        '[HookManager] Error in hook "%s" (priority %d): %s',
                        $hookName,
                        $priority,
                        $e->getMessage()
                    ));
                }
            }
        }
    }

    public function has(string $hookName): bool
    {
        return isset($this->hooks[$hookName]) && $this->hooks[$hookName] !== [];
    }

    public function clear(?string $hookName = null): void
    {
        if ($hookName === null) {
            $this->hooks = [];
        } else {
            unset($this->hooks[$hookName]);
        }
    }
}
