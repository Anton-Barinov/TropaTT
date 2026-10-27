<?php

declare(strict_types=1);

namespace Module\Crm\MercadoPago\Mcp;

use Module\Crm\MercadoPago\Service\MercadoPagoPaymentService;
use Throwable;

final class CreatePaymentLinkTool
{
    public function execute(array $args, array $context): array
    {
        $db = $context['db'] ?? null;
        $workspaceId = (int)($context['workspace_id'] ?? 0);
        if (!$db instanceof \PDO || $workspaceId < 1) {
            return ['success' => false, 'error' => 'Database and workspace context are required.'];
        }
        if (!is_string($args['idempotency_key'] ?? null)) {
            return ['success' => false, 'error' => 'An idempotency_key is required; reuse it when retrying this checkout request.'];
        }
        try {
            return (new MercadoPagoPaymentService($db, $workspaceId))->createPaymentPreference($args);
        } catch (Throwable) {
            // Never expose provider responses, transport exceptions or authorization headers to MCP callers.
            return ['success' => false, 'error' => 'Checkout result could not be confirmed. Reuse the same idempotency_key for retries; verify with the provider before starting a new checkout.'];
        }
    }
}
