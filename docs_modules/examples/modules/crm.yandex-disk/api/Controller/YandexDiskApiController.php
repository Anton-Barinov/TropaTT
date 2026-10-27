<?php
declare(strict_types=1);

namespace Module\Crm\YandexDisk\Controller;

use Api\Controller\Common\BaseController;
use Module\Crm\YandexDisk\Service\SafeLocalFileReader;
use Module\Crm\YandexDisk\Service\YandexDiskService;
use Throwable;

final class YandexDiskApiController extends BaseController
{
    public function getStatus(): \Api\System\Library\Http\JsonResponse
    {
        return $this->success('YANDEX_DISK_STATUS', 'Состояние Яндекс Диска загружено.', $this->service()->getStatus());
    }

    public function saveSettings(): \Api\System\Library\Http\JsonResponse
    {
        $input = $this->request()->allInput();
        try {
            $this->service()->saveToken((string)($input['oauth_token'] ?? ''), (string)($input['root_folder'] ?? '/TropaTT_CRM'));
            return $this->success('YANDEX_DISK_SETTINGS_SAVED', 'Токен зашифрован и сохранён. Проверьте подключение.');
        } catch (Throwable) {
            return $this->error('YANDEX_DISK_SETTINGS_REJECTED', 'Не удалось сохранить настройки. Проверьте токен, путь и APP_KEY.', 422);
        }
    }

    public function testConnection(): \Api\System\Library\Http\JsonResponse
    {
        try {
            return $this->success('YANDEX_DISK_CONNECTED', 'Подключение Яндекс Диска проверено.', $this->service()->testConnection());
        } catch (Throwable) {
            return $this->error('YANDEX_DISK_CONNECTION_FAILED', 'Не удалось проверить подключение. Проверьте OAuth-токен и доступ сервера к HTTPS.', 400);
        }
    }

    public function uploadFile(): \Api\System\Library\Http\JsonResponse
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
            try { $bytes = SafeLocalFileReader::read($path, (int)($file['size_bytes'] ?? -1), (string)(realpath((string)$this->container->get('config')->get('default.storage.uploads', '')) ?: '')); }
            catch (Throwable) { return $this->error('FILE_READ_FAILED', 'Файл изменён, небезопасен или превышает лимит 20 МБ.', 422); }
            return $this->success('YANDEX_DISK_FILE_UPLOADED', 'Файл передан в Яндекс Диск.', $this->service()->uploadFile($file, $bytes));
        } catch (Throwable) {
            return $this->error('YANDEX_DISK_UPLOAD_FAILED', 'Передача не завершилась. Проверьте доступ к файлу и подключение Яндекс Диска.', 400);
        }
    }

    private function service(): YandexDiskService
    {
        $auth = $this->user();
        return new YandexDiskService($this->container->get('db.pdo'), (int)($auth['user']['organization_id'] ?? 0));
    }
}
