<?php
declare(strict_types=1);

namespace Module\Crm\Dropbox\Mcp;

use Api\System\Library\Container;
use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Api\System\Library\Module\ModuleExecutionContext;
use Module\Crm\Dropbox\Service\SafeLocalFileReader;
use Module\Crm\Dropbox\Service\DropboxStorageService;
use Throwable;

final class SyncProjectFilesTool implements ModuleMcpToolInterface
{
    public function execute(array $arguments, ModuleExecutionContext $context, Container $container): array
    {
        if ($context->organizationId <= 0 || $context->organizationPublicId === null || !$container->has('auth_user')) {
            return ['success' => false, 'code' => 'WORKSPACE_OR_ACTOR_REQUIRED'];
        }
        $auth = $container->get('auth_user');
        $actor = is_array($auth['user'] ?? null) ? $auth['user'] : [];
        if ((int)($actor['organization_id'] ?? 0) !== $context->organizationId || ($actor['public_id'] ?? null) !== $context->actorPublicId) {
            return ['success' => false, 'code' => 'ACTOR_CONTEXT_MISMATCH'];
        }
        $fileId = trim((string)($arguments['file_public_id'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $fileId)) return ['success' => false, 'code' => 'FILE_ID_INVALID'];
        try {
            $fileService = $container->get('service.file');
            $file = $fileService->get($fileId, $actor);
            if (!is_array($file) || !empty($file['is_deleted'])) return ['success' => false, 'code' => 'FILE_NOT_FOUND'];
            if (!empty($file['is_quarantined'])) return ['success' => false, 'code' => 'FILE_QUARANTINED'];
            $download = $fileService->canDownloadInternal($fileId, $actor);
            if (!(bool)($download['ok'] ?? false)) return ['success' => false, 'code' => 'FILE_NOT_FOUND'];
            $path = (string)($download['path'] ?? '');
            $bytes = SafeLocalFileReader::read($path, (int)($file['size_bytes'] ?? -1), (string)(realpath((string)$container->get('config')->get('default.storage.uploads', '')) ?: ''));
            $result = (new DropboxStorageService($container->get('db.pdo'), $context->organizationId))->uploadFile($file, $bytes);
            return ['success' => true, 'result' => $result];
        } catch (Throwable) {
            return ['success' => false, 'code' => 'DROPBOX_SYNC_FAILED', 'message' => 'The file could not be synchronized. Verify the Dropbox connection and CRM file access.'];
        }
    }
}
