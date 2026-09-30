<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use InvalidArgumentException;

/**
 * The immutable workspace identity carried from module intake to its worker.
 * Authorization belongs to the REST/MCP/event entry point; workers verify the
 * stored organization again before executing the job.
 */
final class ModuleExecutionContext
{
    public function __construct(
        public readonly string $moduleName,
        public readonly int $organizationId,
        public readonly ?string $organizationPublicId,
        public readonly ?string $actorPublicId,
        public readonly string $source,
        public readonly string $correlationId,
    ) {
        if ($moduleName === '' || !preg_match('/^[a-z0-9][a-z0-9._-]{0,189}$/i', $moduleName)) {
            throw new InvalidArgumentException('Invalid module name');
        }
        if ($organizationId < 0 || ($organizationId > 0 && !$organizationPublicId)) {
            throw new InvalidArgumentException('A workspace job requires an organization public ID');
        }
        if ($organizationId === 0 && $organizationPublicId !== null) {
            throw new InvalidArgumentException('A global job cannot contain a workspace public ID');
        }
        if (!in_array($source, ['web', 'api', 'mcp', 'cron', 'module'], true)) {
            throw new InvalidArgumentException('Invalid module execution source');
        }
        if ($correlationId === '' || strlen($correlationId) > 64) {
            throw new InvalidArgumentException('Invalid correlation ID');
        }
    }

    public static function forWorkspace(
        string $moduleName,
        int $organizationId,
        string $organizationPublicId,
        ?string $actorPublicId,
        string $source,
        ?string $correlationId = null,
    ): self {
        return new self(
            $moduleName,
            $organizationId,
            $organizationPublicId,
            $actorPublicId,
            $source,
            $correlationId ?? bin2hex(random_bytes(16)),
        );
    }

    /** Explicit service job; never inferred from a missing browser selection. */
    public static function global(string $moduleName, string $source = 'cron', ?string $correlationId = null): self
    {
        if (!in_array($source, ['cron', 'module'], true)) {
            throw new InvalidArgumentException('Global module jobs are only allowed for service sources');
        }
        return new self($moduleName, 0, null, null, $source, $correlationId ?? bin2hex(random_bytes(16)));
    }
}
