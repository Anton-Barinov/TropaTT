<?php
declare(strict_types=1);

namespace Module\Crm\Dropbox\Controller;

use Api\Controller\Common\BaseController;
use Module\Crm\Dropbox\Service\SafeLocalFileReader;
use Module\Crm\Dropbox\Service\DropboxStorageService;
use Throwable;

final class DropboxApiController extends BaseController
{
    public function getStatus(): \Api\System\Library\Http\JsonResponse
    {
        $service = $this->service();
        return $this->success('DROPBOX_STATUS', 'Состояние Dropbox загружено.', $service->getStatus());
    }

    public function saveSettings(): \Api\System\Library\Http\JsonResponse
    {
        $input = $this->request()->allInput();
        try {
            $this->service()->saveCredentials([
                'app_key' => (string)($input['app_key'] ?? ''),
                'app_secret' => (string)($input['app_secret'] ?? ''),
                'refresh_token' => (string)($input['refresh_token'] ?? ''),
                'base_folder_path' => (string)($input['base_folder_path'] ?? '/TropaTT_CRM'),
            ]);
            return $this->success('DROPBOX_SETTINGS_SAVED', 'Реквизиты Dropbox сохранены в зашифрованном виде. Проверьте подключение.');
        } catch (Throwable) {
            return $this->error('DROPBOX_SETTINGS_REJECTED', 'Не удалось сохранить настройки. Проверьте APP_KEY, OpenSSL и заполнение обязательных полей.', 422);
        }
    }

    public function testConnection(): \Api\System\Library\Http\JsonResponse
    {
        try {
            return $this->success('DROPBOX_CONNECTION_OK', 'Подключение Dropbox проверено.', $this->service()->testConnection());
        } catch (Throwable) {
            return $this->error('DROPBOX_CONNECTION_FAILED', 'Не удалось проверить Dropbox. Проверьте ключ приложения, секрет и refresh token; подробности авторизации скрыты.', 400);
        }
    }

    public function syncFile(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        $actor = is_array($auth['user'] ?? null) ? $auth['user'] : [];
        $fileId = trim((string)($this->request()->allInput()['file_public_id'] ?? ''));
        if ($fileId === '' || strlen($fileId) > 64) return $this->error('FILE_ID_REQUIRED', 'Укажите идентификатор файла CRM.', 422);
        if ((int)($actor['organization_id'] ?? 0) <= 0) return $this->error('WORKSPACE_REQUIRED', 'Не выбрано рабочее пространство.', 409);
        try {
            $fileService = $this->container->get('service.file');
            $file = $fileService->get($fileId, $actor);
            if (!is_array($file) || !empty($file['is_deleted'])) return $this->error('FILE_NOT_FOUND', 'Файл недоступен в текущем рабочем пространстве.', 404);
            if (!empty($file['is_quarantined'])) return $this->error('FILE_QUARANTINED', 'Файл в карантине нельзя передавать во внешнее хранилище.', 422);
            $download = $fileService->canDownloadInternal($fileId, $actor);
            if (!(bool)($download['ok'] ?? false)) return $this->error('FILE_NOT_FOUND', 'Файл недоступен в текущем рабочем пространстве.', 404);
            $path = (string)($download['path'] ?? '');
            $expectedSize = (int)($file['size_bytes'] ?? -1);
            try { $bytes = SafeLocalFileReader::read($path, $expectedSize, (string)(realpath((string)$this->container->get('config')->get('default.storage.uploads', '')) ?: '')); }
            catch (Throwable) { return $this->error('FILE_READ_FAILED', 'Файл изменён, небезопасен или превышает лимит 20 МБ.', 422); }
            $result = $this->service()->uploadFile($file, $bytes);
            return $this->success('DROPBOX_FILE_SYNCED', 'Файл передан в Dropbox.', $result);
        } catch (Throwable) {
            return $this->error('DROPBOX_FILE_SYNC_FAILED', 'Передача не завершилась. Проверьте соединение и доступ Dropbox; подробности ответа скрыты.', 400);
        }
    }

    private function service(): DropboxStorageService
    {
        $auth = $this->user();
        $organizationId = (int)($auth['user']['organization_id'] ?? 0);
        return new DropboxStorageService($this->container->get('db.pdo'), $organizationId);
    }
}
