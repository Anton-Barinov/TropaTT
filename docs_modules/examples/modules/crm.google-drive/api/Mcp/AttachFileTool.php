<?php
declare(strict_types=1);

namespace Module\Crm\GoogleDrive\Mcp;

use Api\System\Library\Container;
use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Api\System\Library\Module\ModuleExecutionContext;
use Module\Crm\GoogleDrive\Service\SafeLocalFileReader;
use Module\Crm\GoogleDrive\Service\GoogleDriveStorageService;
use Throwable;

final class AttachFileTool implements ModuleMcpToolInterface
{
    public function execute(array $arguments, ModuleExecutionContext $context, Container $container): array
    {
        if ($context->organizationId <= 0 || $context->organizationPublicId === null || $context->actorPublicId === null) return ['success' => false, 'code' => 'WORKSPACE_OR_ACTOR_REQUIRED'];
        if (!$container->has('auth_user')) return ['success' => false, 'code' => 'WORKSPACE_OR_ACTOR_REQUIRED'];
        $auth = $container->get('auth_user');
        $actor = is_array($auth['user'] ?? null) ? $auth['user'] : [];
        if ((int)($actor['organization_id'] ?? 0) !== $context->organizationId || ($actor['public_id'] ?? null) !== $context->actorPublicId) return ['success' => false, 'code' => 'ACTOR_CONTEXT_MISMATCH'];
        $fileId = trim((string)($arguments['file_public_id'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $fileId)) return ['success' => false, 'code' => 'FILE_ID_INVALID'];
        try {
            $files = $container->get('service.file');
            $file = $files->get($fileId, $actor);
            if (!is_array($file) || !empty($file['is_deleted'])) return ['success' => false, 'code' => 'FILE_NOT_FOUND'];
            if (!empty($file['is_quarantined'])) return ['success' => false, 'code' => 'FILE_QUARANTINED'];
            $download = $files->canDownloadInternal($fileId, $actor);
            if (!(bool)($download['ok'] ?? false)) return ['success' => false, 'code' => 'FILE_NOT_FOUND'];
            $path = (string)($download['path'] ?? '');
            $content = SafeLocalFileReader::read($path, (int)($file['size_bytes'] ?? -1), (string)(realpath((string)$container->get('config')->get('default.storage.uploads', '')) ?: ''));
            $result = (new GoogleDriveStorageService($container->get('db.pdo'), $context->organizationId))->uploadFile($file, $content);
            return ['success' => true, 'result' => $result];
        } catch (Throwable) {
            return ['success' => false, 'code' => 'GOOGLE_DRIVE_UPLOAD_FAILED', 'message' => 'The CRM file could not be transferred. Check workspace file access and Google Drive connection.'];
        }
    }
}
