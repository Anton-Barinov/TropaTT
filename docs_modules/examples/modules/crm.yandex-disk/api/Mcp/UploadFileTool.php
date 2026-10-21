<?php

declare(strict_types=1);

namespace Module\Crm\YandexDisk\Mcp;

use Api\System\Library\Container;
use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Api\System\Library\Module\ModuleExecutionContext;

/**
 * Deliberately fail closed until a real, streamed Yandex Disk upload exists.
 * No file metadata is written and no public URL is fabricated.
 */
final class UploadFileTool implements ModuleMcpToolInterface
{
    public function execute(array $arguments, ModuleExecutionContext $context, Container $container): array
    {
        if ($context->organizationId <= 0 || $context->organizationPublicId === null) {
            return ['success' => false, 'error' => 'ACTIVE_WORKSPACE_REQUIRED'];
        }

        return [
            'success' => false,
            'error' => 'UPLOAD_NOT_AVAILABLE',
            'message' => 'Yandex Disk upload is unavailable until secure provider transfer is implemented.',
        ];
    }
}
