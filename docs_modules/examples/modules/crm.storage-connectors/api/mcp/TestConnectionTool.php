<?php
declare(strict_types=1);

namespace Module\Crm\StorageConnectors\Mcp;

use Api\System\Library\Container;
use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Api\System\Library\Module\ModuleExecutionContext;
use Module\Crm\StorageConnectors\Service\StorageManagerService;
use Throwable;

final class TestConnectionTool implements ModuleMcpToolInterface
{
    public function execute(array $arguments, ModuleExecutionContext $context, Container $container): array
    {
        if ($context->organizationId <= 0 || $context->actorPublicId === null) return ['ok' => false, 'code' => 'WORKSPACE_OR_ACTOR_REQUIRED'];
        if (!$container->has('auth_user')) return ['ok' => false, 'code' => 'WORKSPACE_OR_ACTOR_REQUIRED'];
        $auth = $container->get('auth_user'); $actor = is_array($auth['user'] ?? null) ? $auth['user'] : [];
        if ((int)($actor['organization_id'] ?? 0) !== $context->organizationId || ($actor['public_id'] ?? null) !== $context->actorPublicId) return ['ok' => false, 'code' => 'ACTOR_CONTEXT_MISMATCH'];
        $requestedProvider = trim((string)($arguments['provider_type'] ?? ''));
        if (!in_array($requestedProvider, ['s3', 'nextcloud_webdav'], true)) return ['ok' => false, 'code' => 'PROVIDER_TYPE_INVALID'];
        try {
            $manager = new StorageManagerService($container->get('db.pdo'));
            $config = $manager->getConfig($context->organizationId);
            if (!$config || (string)$config['provider_type'] !== $requestedProvider) return ['ok' => false, 'code' => 'PROVIDER_NOT_CONFIGURED_FOR_WORKSPACE'];
            $adapter = $manager->getAdapter($context->organizationId);
            if ($adapter === null) return ['ok' => false, 'code' => 'PROVIDER_NOT_CONFIGURED_FOR_WORKSPACE'];
            return $adapter->testConnection();
        } catch (Throwable) {
            return ['ok' => false, 'code' => 'STORAGE_CONNECTION_FAILED'];
        }
    }
}
