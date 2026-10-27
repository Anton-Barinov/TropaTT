<?php
declare(strict_types=1);

namespace Module\Crm\GoogleDrive\Controller;

use Api\Controller\Common\BaseController;
use Module\Crm\GoogleDrive\Service\SafeLocalFileReader;
use Module\Crm\GoogleDrive\Service\GoogleDriveStorageService;
use Throwable;

final class GoogleDriveApiController extends BaseController
{
    public function getStatus(): \Api\System\Library\Http\JsonResponse
    {
        return $this->success('GOOGLE_DRIVE_STATUS', 'Состояние Google Drive загружено.', $this->service()->getStatus());
    }

    public function saveSettings(): \Api\System\Library\Http\JsonResponse
    {
        $input = $this->request()->allInput();
        try {
            $this->service()->saveSettings((string)($input['client_id'] ?? ''), (string)($input['client_secret'] ?? ''), (string)($input['refresh_token'] ?? ''), (string)($input['root_folder_id'] ?? ''));
            return $this->success('GOOGLE_DRIVE_SETTINGS_SAVED', 'OAuth-настройки зашифрованы и сохранены. Проверьте подключение.');
        } catch (Throwable) {
            return $this->error('GOOGLE_DRIVE_SETTINGS_REJECTED', 'Не удалось сохранить настройки. Проверьте поля и APP_KEY.', 422);
        }
    }

    public function testConnection(): \Api\System\Library\Http\JsonResponse
    {
        try {
            return $this->success('GOOGLE_DRIVE_CONNECTED', 'Подключение Google Drive проверено.', $this->service()->testConnection());
        } catch (Throwable) {
            return $this->error('GOOGLE_DRIVE_CONNECTION_FAILED', 'Не удалось проверить Google OAuth и Drive API. Проверьте настройки, разрешения OAuth и доступ сервера к HTTPS.', 400);
        }
    }

    public function uploadFile(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        $actor = is_array($auth['user'] ?? null) ? $auth['user'] : [];
        $input = $this->request()->allInput();
        $fileId = trim((string)($input['file_public_id'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $fileId)) return $this->error('FILE_ID_INVALID', 'Укажите корректный идентификатор файла CRM.', 422);
        if ((int)($actor['organization_id'] ?? 0) <= 0) return $this->error('WORKSPACE_REQUIRED', 'Не выбрано рабочее пространство.', 409);
        try {
            $fileService = $this->container->get('service.file');
            $file = $fileService->get($fileId, $actor);
            if (!is_array($file) || !empty($file['is_deleted'])) return $this->error('FILE_NOT_FOUND', 'Файл недоступен в текущем рабочем пространстве.', 404);
            if (!empty($file['is_quarantined'])) return $this->error('FILE_QUARANTINED', 'Файл в карантине нельзя передавать в Google Drive.', 422);
            $download = $fileService->canDownloadInternal($fileId, $actor);
            if (!(bool)($download['ok'] ?? false)) return $this->error('FILE_NOT_FOUND', 'Файл недоступен в текущем рабочем пространстве.', 404);
            $path = (string)($download['path'] ?? '');
            try { $bytes = SafeLocalFileReader::read($path, (int)($file['size_bytes'] ?? -1), (string)(realpath((string)$this->container->get('config')->get('default.storage.uploads', '')) ?: '')); }
            catch (Throwable) { return $this->error('FILE_READ_FAILED', 'Файл изменён, небезопасен или превышает лимит 20 МБ.', 422); }
            return $this->success('GOOGLE_DRIVE_FILE_UPLOADED', 'Файл передан в приватное хранилище Google Drive.', $this->service()->uploadFile($file, $bytes));
        } catch (Throwable) {
            return $this->error('GOOGLE_DRIVE_UPLOAD_FAILED', 'Передача файла не завершилась. Проверьте доступ к файлу, OAuth и Google Drive.', 400);
        }
    }

    private function service(): GoogleDriveStorageService
    {
        $auth = $this->user();
        return new GoogleDriveStorageService($this->container->get('db.pdo'), (int)($auth['user']['organization_id'] ?? 0));
    }
}
