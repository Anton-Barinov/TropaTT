<?php

declare(strict_types=1);

namespace Module\Crm\MercadoPago\Mcp;

use Module\Crm\MercadoPago\Service\MercadoPagoPaymentService;

class CreatePaymentLinkTool
{
    public function execute(array $args, array $context): array
    {
        $db = $context['db'] ?? null;
        if (!$db instanceof \PDO) {
            return [
                'success' => false,
                'error' => 'Database connection unavailable'
            ];
        }

        $service = new MercadoPagoPaymentService($db, (int)($context['workspace_id'] ?? 1));
        return $service->createPaymentPreference($args);
    }
}
