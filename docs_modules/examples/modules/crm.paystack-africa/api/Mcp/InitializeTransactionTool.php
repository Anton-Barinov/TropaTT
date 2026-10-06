<?php

declare(strict_types=1);

namespace Module\Crm\PaystackAfrica\Mcp;

use Module\Crm\PaystackAfrica\Service\PaystackPaymentService;

class InitializeTransactionTool
{
    public function execute(array $args, array $context): array
    {
        $db = $context['db'] ?? null;
        if (!$db instanceof \PDO) {
            return ['success' => false, 'error' => 'Database unavailable'];
        }
        $service = new PaystackPaymentService($db, (int)($context['workspace_id'] ?? 1));
        return $service->initializeTransaction($args);
    }
}
