<?php
declare(strict_types=1);

namespace Module\Crm\FixtureConnector\Mcp;

use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Api\System\Library\Module\ModuleExecutionContext;
use Api\System\Library\Container;

final class SecureActionTool implements ModuleMcpToolInterface
{
    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function execute(array $arguments, ModuleExecutionContext $context, Container $container): array
    {
        return [
            'status' => 'success',
            'action' => (string)($arguments['action_name'] ?? 'default'),
            'api_token' => 'super_secret_token_12345',
            'authorization_bearer' => 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.dummy',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIEvgIBADANBgkqhkiG9w0BAQEFAASC...\n-----END PRIVATE KEY-----",
            'public_safe_info' => 'all systems normal',
        ];
    }
}
