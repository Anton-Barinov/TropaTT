<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleCacheInvalidator
{
    /** @var array<string, ModuleCache> */
    private array $caches = [];

    public function __construct(
        private readonly ModuleCache $moduleCache,
    ) {}

    public function invalidateByTag(string $moduleName, string $tag): void
    {
        $cacheKey = "tag:{$moduleName}:{$tag}";
        $this->moduleCache->invalidateModule($cacheKey);
        error_log("[CacheInvalidator] Tag invalidated: {$moduleName}::{$tag}");
    }

    public function invalidateByPrefix(string $moduleName, string $prefix): void
    {
        $cacheKey = "prefix:{$moduleName}:{$prefix}";
        $this->moduleCache->invalidateModule($cacheKey);
    }

    public function invalidateAll(string $moduleName): void
    {
        $this->moduleCache->invalidateModule($moduleName);
    }

    public function onDataChange(string $moduleName, string $entity, int $entityId): void
    {
        $tag = "data:{$entity}:{$entityId}";
        $this->invalidateByTag($moduleName, $tag);
    }
}
