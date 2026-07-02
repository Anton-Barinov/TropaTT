<?php
declare(strict_types=1);

namespace Api\Controller\VisualEditor;

use Api\Controller\Common\BaseController;
use Api\System\Library\Http\JsonResponse;
use Throwable;

final class UploadController extends BaseController
{
    private const ALLOWED_MIME = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB

    private const MAX_RESOLUTION = 8000;

    public function upload(): JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $files = $this->request()->files;
        if (empty($files['file']) || $files['file']['error'] !== UPLOAD_ERR_OK) {
            return $this->error('FILE_NOT_PROVIDED', $this->t('file/messages.not_provided'), 422, [
                'file' => [$this->t('file/messages.not_provided')],
            ]);
        }

        $file = $files['file'];

        // Validate size
        if ($file['size'] > self::MAX_FILE_SIZE) {
            return $this->error('FILE_TOO_LARGE', $this->t('file/messages.too_large', 'Файл слишком большой. Максимум 10 МБ.'), 422, [
                'file' => [$this->t('file/messages.too_large', 'Файл слишком большой. Максимум 10 МБ.')],
            ]);
        }

        // Validate MIME by content
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($detectedMime, self::ALLOWED_MIME, true)) {
            return $this->error('INVALID_FILE_TYPE', $this->t('file/messages.invalid_type', 'Недопустимый тип файла. Разрешены: JPEG, PNG, WebP, GIF.'), 422, [
                'file' => [$this->t('file/messages.invalid_type', 'Недопустимый тип файла. Разрешены: JPEG, PNG, WebP, GIF.')],
            ]);
        }

        // Validate dimensions
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return $this->error('INVALID_IMAGE', $this->t('file/messages.invalid_image', 'Не удалось прочитать изображение.'), 422, [
                'file' => [$this->t('file/messages.invalid_image', 'Не удалось прочитать изображение.')],
            ]);
        }

        $width = (int)$imageInfo[0];
        $height = (int)$imageInfo[1];

        if ($width > self::MAX_RESOLUTION || $height > self::MAX_RESOLUTION) {
            return $this->error('IMAGE_TOO_LARGE', $this->t('file/messages.image_too_large', 'Разрешение изображения превышает 8000x8000.'), 422, [
                'file' => [$this->t('file/messages.image_too_large', 'Разрешение изображения превышает 8000x8000.')],
            ]);
        }

        // Generate safe file name
        $extension = $this->extensionFromMime($detectedMime);
        $safeName = bin2hex(random_bytes(16)) . '.' . $extension;

        // Determine storage path
        $baseDir = dirname(__DIR__, 3) . '/storage_api/uploads/visual-editor';
        $yearMonth = date('Y/m');
        $targetDir = $baseDir . '/' . $yearMonth;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            return $this->error('UPLOAD_ERROR', $this->t('file/messages.upload_error', 'Ошибка при сохранении файла.'), 500, [
                'file' => [$this->t('file/messages.upload_error', 'Ошибка при сохранении файла.')],
            ]);
        }

        $targetPath = $targetDir . '/' . $safeName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return $this->error('UPLOAD_ERROR', $this->t('file/messages.upload_error', 'Ошибка при сохранении файла.'), 500, [
                'file' => [$this->t('file/messages.upload_error', 'Ошибка при сохранении файла.')],
            ]);
        }

        $url = '/storage_api/uploads/visual-editor/' . $yearMonth . '/' . $safeName;

        return $this->success('IMAGE_UPLOADED', $this->t('file/messages.uploaded', 'Изображение загружено.'), [
            'url' => $url,
            'width' => $width,
            'height' => $height,
            'mime' => $detectedMime,
            'size' => $file['size'],
        ], 201);
    }

    private function extensionFromMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'bin',
        };
    }
}
