<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleConflictResolver
{
    public const FIRST_WINS = 'first_wins';
    public const LAST_WINS = 'last_wins';
    public const MERGE = 'merge';
    public const ERROR = 'error';

    /** @var array<string, string> */
    private array $strategies = [];

    /**
     * Resolve hook conflicts between modules.
     * @param array<int, array{handler: string, priority: int}> $existing
     * @param array<int, array{handler: string, priority: int}> $new
     * @return array<int, array{handler: string, priority: int}>
     */
    public function resolveHooks(string $hookName, array $existing, array $new): array
    {
        $strategy = $this->strategies[$hookName] ?? self::MERGE;

        return match ($strategy) {
            self::FIRST_WINS => $existing,
            self::LAST_WINS => $new,
            self::ERROR => throw new \RuntimeException("Hook conflict: {$hookName}"),
            default => array_merge($existing, $new),
        };
    }

    public function setStrategy(string $hookName, string $strategy): void
    {
        $allowed = [self::FIRST_WINS, self::LAST_WINS, self::MERGE, self::ERROR];
        if (!in_array($strategy, $allowed, true)) {
            throw new \InvalidArgumentException("Unknown strategy: {$strategy}");
        }
        $this->strategies[$hookName] = $strategy;
    }
}
