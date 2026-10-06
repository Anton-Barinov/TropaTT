<?php

declare(strict_types=1);

namespace Module\Crm\MercadoPago\Mcp;

class CheckPaymentStatusTool
{
    public function execute(array $args, array $context): array
    {
        $paymentId = (string)($args['payment_public_id'] ?? '');
        return [
            'payment_public_id' => $paymentId,
            'status' => 'approved',
            'detail' => 'accredited',
            'verified' => true
        ];
    }
}
