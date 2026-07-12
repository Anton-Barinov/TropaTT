<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use PDO;
use RuntimeException;

final class ModuleDatabasePool
{
    private int $maxConnectionsPerModule = 3;

    /** @var array<string, array<int, PDO>> */
    private array $pools = [];

    /** @var array<string, PDO> */
    private array $moduleToPool = [];

    public function __construct(
        private readonly PDO $defaultPdo,
    ) {}

    public function getConnection(string $moduleName): PDO
    {
        if (isset($this->moduleToPool[$moduleName])) {
            return $this->moduleToPool[$moduleName];
        }

        $this->moduleToPool[$moduleName] = $this->defaultPdo;
        return $this->defaultPdo;
    }

    public function releaseConnection(string $moduleName): void
    {
        unset($this->moduleToPool[$moduleName]);
    }

    public function getActiveConnectionCount(string $moduleName): int
    {
        return isset($this->moduleToPool[$moduleName]) ? 1 : 0;
    }

    public function getMaxConnectionsPerModule(): int
    {
        return $this->maxConnectionsPerModule;
    }
}
