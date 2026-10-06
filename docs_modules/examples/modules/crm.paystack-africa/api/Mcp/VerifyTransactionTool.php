<?php

declare(strict_types=1);

namespace Module\Crm\PaystackAfrica\Mcp;

class VerifyTransactionTool
{
    public function execute(array $args, array $context): array
    {
        $ref = (string)($args['reference'] ?? '');
        return [
            'reference' => $ref,
            'status' => 'success',
            'gateway_response' => 'Successful',
            'verified' => true
        ];
    }
}
