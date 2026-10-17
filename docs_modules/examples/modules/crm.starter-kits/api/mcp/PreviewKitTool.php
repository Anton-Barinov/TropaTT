<?php
declare(strict_types=1);

namespace Module\Crm\StarterKits\Mcp;

use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Api\System\Library\Container;
use Api\System\Library\Module\ModuleExecutionContext;
use Module\Crm\StarterKits\Service\StarterKitService;

final class PreviewKitTool implements ModuleMcpToolInterface
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
        $locale = is_string($arguments['locale'] ?? null) ? substr(trim($arguments['locale']), 0, 35) : 'ru-ru';

        $db = $container->get('db.pdo');
        $service = new StarterKitService($db);
        $preview = $service->preview($kitId, $context->organizationId, $locale);

        return [
            'ok' => true,
            'preview' => $preview,
        ];
    }
}
