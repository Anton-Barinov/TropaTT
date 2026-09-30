<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

/** A module job must consume the authoritative scope supplied by the worker. */
interface WorkspaceModuleJobInterface
{
    /** @param array<string,mixed> $payload */
    public function handle(array $payload, ModuleExecutionContext $context): void;
}
