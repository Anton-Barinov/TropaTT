<?php
declare(strict_types=1);

namespace Module\Crm\StarterKits\Mcp;

use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Api\System\Library\Container;
use Api\System\Library\Module\ModuleExecutionContext;
use Module\Crm\StarterKits\Service\StarterKitService;

final class ListKitsTool implements ModuleMcpToolInterface
{
    public function execute(array $arguments, ModuleExecutionContext $context, Container $container): array
    {
        if ($context->organizationId <= 0 || $context->actorPublicId === null || $context->actorPublicId === '') {
            throw new \RuntimeException('An authorized workspace and actor are required.');
        }
        $db = $container->get('db.pdo');
        $service = new StarterKitService($db);
        $locale = is_string($arguments['locale'] ?? null) ? substr(trim($arguments['locale']), 0, 35) : 'ru-ru';
        $kits = $service->listKits($locale);

        return [
            'ok' => true,
            'kits' => $kits,
        ];
    }
}
