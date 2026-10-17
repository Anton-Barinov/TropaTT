<?php
declare(strict_types=1);

namespace Module\Crm\StarterKits\Mcp;

use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Api\System\Library\Container;
use Api\System\Library\Module\ModuleExecutionContext;
use Module\Crm\StarterKits\Service\StarterKitService;

final class ApplyKitTool implements ModuleMcpToolInterface
{
    public function execute(array $arguments, ModuleExecutionContext $context, Container $container): array
    {
        $kitId = is_string($arguments['kit_id'] ?? null) ? trim($arguments['kit_id']) : '';
        if (!preg_match('/^kit_[a-z0-9_]{1,60}$/', $kitId)) {
            throw new \InvalidArgumentException('A valid starter kit identifier is required.');
        }
        if ($context->organizationId <= 0 || $context->actorPublicId === null || $context->actorPublicId === '') {
            throw new \RuntimeException('An authorized workspace and actor are required.');
        }
        $components = $arguments['selected_components'] ?? null;
        if (!is_array($components) || $components === [] || count($components) > 5) {
            throw new \InvalidArgumentException('Select one or more starter kit components.');
        }
        $locale = is_string($arguments['locale'] ?? null) ? substr(trim($arguments['locale']), 0, 35) : 'ru-ru';

        $db = $container->get('db.pdo');
        $identity = $db->prepare('SELECT id, is_root FROM users WHERE public_id = :public_id LIMIT 1');
        $identity->execute([':public_id' => $context->actorPublicId]);
        $actor = $identity->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($actor) || (int)$actor['id'] <= 0) {
            throw new \RuntimeException('Authenticated actor could not be resolved.');
        }
        $events = $container->has('module.domain_event') ? $container->get('module.domain_event') : null;
        $service = new StarterKitService($db, null, $events);
        $result = $service->apply($kitId, $context->organizationId, (int)$actor['id'], $components, $locale, (bool)$actor['is_root']);

        return $result;
    }
}
