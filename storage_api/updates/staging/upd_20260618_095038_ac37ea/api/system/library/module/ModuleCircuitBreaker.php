<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleCircuitBreaker
{
    public const CLOSED = 'closed';
    public const OPEN = 'open';
    public const HALF_OPEN = 'half_open';

    private int $failureThreshold = 5;
    private int $timeoutSeconds = 60;
    private int $halfOpenMaxRequests = 3;

    /** @var array<string, array{failures: int, lastFailure: int, state: string, halfOpenCount: int}> */
    private array $breakers = [];

    public function recordSuccess(string $moduleName): void
    {
        $b = &$this->getBreaker($moduleName);
        $b['failures'] = 0;
        $b['halfOpenCount'] = 0;
        if ($b['state'] === self::HALF_OPEN) {
            $b['state'] = self::CLOSED;
        }
    }

    public function recordFailure(string $moduleName): void
    {
        $b = &$this->getBreaker($moduleName);
        $b['failures']++;
        $b['lastFailure'] = time();

        if ($b['state'] === self::CLOSED && $b['failures'] >= $this->failureThreshold) {
            $b['state'] = self::OPEN;
            error_log("[CircuitBreaker] OPEN for {$moduleName} after {$b['failures']} failures");
        }

        if ($b['state'] === self::HALF_OPEN) {
            $b['state'] = self::OPEN;
            $b['halfOpenCount'] = 0;
        }
    }

    public function isAvailable(string $moduleName): bool
    {
        $b = $this->getBreaker($moduleName);

        if ($b['state'] === self::OPEN) {
            if (time() - $b['lastFailure'] >= $this->timeoutSeconds) {
                $b['state'] = self::HALF_OPEN;
                $b['halfOpenCount'] = 0;
                return true;
            }
            return false;
        }

        if ($b['state'] === self::HALF_OPEN) {
            if ($b['halfOpenCount'] >= $this->halfOpenMaxRequests) {
                return false;
            }
            $b['halfOpenCount']++;
        }

        return true;
    }

    public function getState(string $moduleName): string
    {
        return $this->getBreaker($moduleName)['state'];
    }

    public function reset(string $moduleName): void
    {
        unset($this->breakers[$moduleName]);
    }

    /**
     * @return array{failures: int, lastFailure: int, state: string, halfOpenCount: int}
     */
    private function &getBreaker(string $moduleName): array
    {
        if (!isset($this->breakers[$moduleName])) {
            $this->breakers[$moduleName] = [
                'failures' => 0,
                'lastFailure' => 0,
                'state' => self::CLOSED,
                'halfOpenCount' => 0,
            ];
        }

        return $this->breakers[$moduleName];
    }
}
