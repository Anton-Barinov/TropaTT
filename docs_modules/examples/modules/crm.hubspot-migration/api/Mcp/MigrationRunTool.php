<?php

declare(strict_types=1);

namespace Module\Crm\HubSpotMigration\Mcp;

use Api\System\Library\Cache\ApiFileCache;
use Api\System\Library\Container;
use Api\System\Library\Service\ClientService;
use Module\Crm\HubSpotMigration\Service\HubSpotMigrationService;
use Throwable;

final class MigrationRunTool
{
    public function execute(array $args, array $context, Container $container): array
    {
        $db = $context['db'] ?? null;
        $workspaceId = (int)($context['workspace_id'] ?? 0);
        $actorPublicId = (string)($context['actor_public_id'] ?? '');
        $operation = (string)($args['operation'] ?? '');
        $sessionId = (string)($args['session_id'] ?? '');
        if (!$db instanceof \PDO || $workspaceId < 1 || $actorPublicId === '') return ['success' => false, 'error' => 'Authorized workspace and actor context are required.'];
        if (!in_array($operation, ['import_batch', 'rollback'], true) || !preg_match('/^hsm_[a-f0-9]{32}$/D', $sessionId)) return ['success' => false, 'error' => 'Choose a valid operation and migration session.'];
        $limit = filter_var($args['limit'] ?? 25, FILTER_VALIDATE_INT);
        if ($operation === 'import_batch' && ($limit === false || $limit < 1 || $limit > 25)) return ['success' => false, 'error' => 'Batch size must be between 1 and 25.'];
        try {
            // The MCP execution context has already authorized this actor, module permission and workspace.
            $actorQuery = $db->prepare('SELECT id FROM users WHERE public_id=:public_id LIMIT 1');
            $actorQuery->execute(['public_id' => $actorPublicId]);
            $row = $actorQuery->fetch(\PDO::FETCH_ASSOC);
            if (!is_array($row)) return ['success' => false, 'error' => 'Authorized actor could not be resolved.'];
            $actor = ['id' => (int)$row['id'], 'organization_id' => $workspaceId];
            $clients = $container->get('service.client');
            if (!$clients instanceof ClientService) return ['success' => false, 'error' => 'CRM contact service is unavailable.'];
            $service = new HubSpotMigrationService($db, $workspaceId, $clients, $actor);
            $result = $operation === 'import_batch' ? $service->importBatch($sessionId, (int)$limit) : $service->rollback($sessionId);
            if ($container->has('cache.api')) {
                $cache = $container->get('cache.api');
                if ($cache instanceof ApiFileCache) $cache->invalidateNamespace('client');
            }
            return self::publicResult($operation, $result);
        } catch (Throwable) {
            return ['success' => false, 'error' => 'Migration action failed safely. Check the session report in the CRM module.'];
        }
    }

    /** MCP returns operation counts only; contact names and reports remain in the CRM UI. */
    public static function publicResult(string $operation, array $result): array
    {
        $keys = $operation === 'rollback'
            ? ['deleted_unchanged_records', 'retained_changed_or_unavailable_records']
            : ['processed', 'imported', 'skipped', 'errors'];
        $safe = ['success' => true, 'operation' => $operation];
        if (isset($result['session_id']) && is_string($result['session_id'])) $safe['session_id'] = $result['session_id'];
        foreach ($keys as $key) $safe[$key] = max(0, (int)($result[$key] ?? 0));
        return $safe;
    }
}
