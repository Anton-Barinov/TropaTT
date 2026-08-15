<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class Manifest
{
    public function __construct(
        public readonly string $name,
        public readonly string $version,
        public readonly string $vendor,
        public readonly string $author,
        public readonly string $authorUrl,
        public readonly string $title,
        public readonly string $description,
        public readonly string $coreVersion,
        public readonly array $dependencies,
        public readonly array $requirePermissions,
        public readonly ?string $apiRoutes,
        public readonly ?string $webRoutes,
        public readonly ?string $migrations,
        public readonly array $hooks,
        public readonly array $menuItems,
        public readonly ?string $serviceProvider = null,
        public readonly array $configDefaults = [],
        public readonly array $assets = [],
        public readonly array $positions = [],
        public readonly array $webHooks = [],
        public readonly string $category = '',
    ) {}

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string)($data['name'] ?? ''),
            version: (string)($data['version'] ?? ''),
            vendor: (string)($data['vendor'] ?? ''),
            author: (string)($data['author'] ?? ''),
            authorUrl: (string)($data['author_url'] ?? ''),
            title: (string)($data['title'] ?? ''),
            description: (string)($data['description'] ?? ''),
            coreVersion: (string)($data['core_version'] ?? '>=1.0.0'),
            dependencies: (array)($data['dependencies'] ?? []),
            requirePermissions: (array)($data['require_permissions'] ?? []),
            apiRoutes: isset($data['api_routes']) ? (string)$data['api_routes'] : null,
            webRoutes: isset($data['web_routes']) ? (string)$data['web_routes'] : null,
            migrations: isset($data['migrations']) ? (string)$data['migrations'] : null,
            hooks: (array)($data['hooks'] ?? []),
            menuItems: (array)($data['menu_items'] ?? []),
            serviceProvider: isset($data['service_provider']) ? (string)$data['service_provider'] : null,
            configDefaults: (array)($data['config_defaults'] ?? []),
            assets: (array)($data['assets'] ?? []),
            positions: (array)($data['positions'] ?? []),
            webHooks: (array)($data['web_hooks'] ?? []),
            category: (string)($data['category'] ?? ''),
        );
    }
}
