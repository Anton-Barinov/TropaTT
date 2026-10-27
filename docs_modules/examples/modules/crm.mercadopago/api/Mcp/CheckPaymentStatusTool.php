<?php

declare(strict_types=1);

namespace Module\Crm\MercadoPago\Mcp;

use Module\Crm\MercadoPago\Service\MercadoPagoPaymentService;
use Throwable;

final class CheckPaymentStatusTool
{
    public function execute(array $args, array $context): array
    {
        $db = $context['db'] ?? null;
        $workspaceId = (int)($context['workspace_id'] ?? 0);
        if (!$db instanceof \PDO || $workspaceId < 1) {
            return ['success' => false, 'error' => 'Database and workspace context are required.'];
        }
        try {
            return (new MercadoPagoPaymentService($db, $workspaceId))->checkPaymentStatus((string)($args['payment_public_id'] ?? ''), (string)($args['provider_payment_id'] ?? ''));
        } catch (Throwable) {
            return ['success' => false, 'error' => 'Payment status could not be verified. Check the payment reference and workspace configuration.'];
        }
    }
}
