<?php
declare(strict_types=1);

namespace Api\Controller\Language;

use Api\Controller\Common\BaseController;
use Api\System\Library\Http\JsonResponse;
use Api\System\Library\Service\LanguageRegistryService;
use InvalidArgumentException;

final class LanguageController extends BaseController
{
    private function registry(): LanguageRegistryService
    {
        /** @var LanguageRegistryService $service */
        $service = $this->container->get('service.language_registry');
        return $service;
    }

    /**
     * Public list of enabled languages (for login screen and public selectors).
     */
    public function list(): JsonResponse
    {
        $registry = $this->registry();
        $items = $registry->listEnabled();
        $default = $registry->getDefaultLocale();

        return $this->success('LANGUAGE_LIST', $this->t('common/messages.success', 'Success'), [
            'items' => $items,
            'languages' => $items,
            'default_locale' => $default,
        ]);
    }

    /**
     * Admin list of all installed languages with status and details.
     */
    public function adminList(): JsonResponse
    {
        $registry = $this->registry();
        $items = $registry->listAll();
        $default = $registry->getDefaultLocale();

        return $this->success('ADMIN_LANGUAGE_LIST', $this->t('common/messages.success', 'Success'), [
            'items' => $items,
            'languages' => $items,
            'default_locale' => $default,
        ]);
    }

    /**
     * Toggle enabled state for a language.
     */
    public function adminToggle(): JsonResponse
    {
        $input = $this->request()->allInput();
        $code = trim((string)($input['code'] ?? ''));
        if ($code === '') {
            return $this->error('VALIDATION_ERROR', 'Field "code" is required.', 422);
        }

        $isEnabled = null;
        if (isset($input['is_enabled'])) {
            $isEnabled = (bool)$input['is_enabled'];
        } elseif (isset($input['enabled'])) {
            $isEnabled = (bool)$input['enabled'];
        }
        if ($isEnabled === null) {
            return $this->error('VALIDATION_ERROR', 'Field "is_enabled" is required.', 422);
        }
        $enabled = $isEnabled;

        try {
            $result = $this->registry()->toggle($code, $enabled);
            return $this->success('LANGUAGE_TOGGLED', $this->t('common/messages.success', 'Success'), $result);
        } catch (InvalidArgumentException $e) {
            return $this->error('VALIDATION_ERROR', $e->getMessage(), 422);
        }
    }

    private function installer(): \Api\System\Library\Service\LanguagePackInstaller
    {
        /** @var \Api\System\Library\Service\LanguagePackInstaller $service */
        $service = $this->container->get('service.language_pack_installer');
        return $service;
    }

    /**
     * Set system default language.
     */
    public function adminSetDefault(): JsonResponse
    {
        $input = $this->request()->allInput();
        $code = trim((string)($input['code'] ?? ''));
        if ($code === '') {
            return $this->error('VALIDATION_ERROR', 'Field "code" is required.', 422);
        }

        try {
            $result = $this->registry()->setDefaultLocale($code);
            return $this->success('LANGUAGE_DEFAULT_SET', $this->t('common/messages.success', 'Success'), $result);
        } catch (InvalidArgumentException $e) {
            return $this->error('VALIDATION_ERROR', $e->getMessage(), 422);
        }
    }

    /**
     * Install a language pack via uploaded ZIP or URL.
     */
    public function adminInstall(): JsonResponse
    {
        $installer = $this->installer();

        // 1. Check for uploaded file (supports 'file' or 'package' parameter name)
        $uploaded = $_FILES['file'] ?? $_FILES['package'] ?? null;
        if (is_array($uploaded) && !empty($uploaded['tmp_name'])) {
            $tmpPath = (string)$uploaded['tmp_name'];
            $error = (int)($uploaded['error'] ?? UPLOAD_ERR_OK);
            if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($tmpPath)) {
                return $this->error('UPLOAD_FAILED', 'File upload failed with error code ' . $error, 400);
            }

            try {
                $result = $installer->installArchive($tmpPath);
                return $this->success('LANGUAGE_INSTALLED', $this->t('common/messages.success', 'Success'), $result);
            } catch (\Throwable $e) {
                return $this->error('INSTALLATION_FAILED', $e->getMessage(), 422);
            }
        }

        // 2. Check for URL input (marketplace)
        $input = $this->request()->allInput();
        $url = trim((string)($input['url'] ?? ''));
        if ($url !== '') {
            $expectedHash = isset($input['package_hash']) ? trim((string)$input['package_hash']) : null;
            try {
                $result = $installer->installFromUrl($url, $expectedHash);
                return $this->success('LANGUAGE_INSTALLED', $this->t('common/messages.success', 'Success'), $result);
            } catch (\Throwable $e) {
                return $this->error('INSTALLATION_FAILED', $e->getMessage(), 422);
            }
        }

        return $this->error('VALIDATION_ERROR', 'Either a ZIP file upload or a package URL is required.', 422);
    }

    /**
     * Export an installed language pack as a ZIP archive.
     */
    public function adminExport(string $code): void
    {
        $installer = $this->installer();
        try {
            $zipPath = $installer->exportPackage($code);
            $filename = basename($zipPath);

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($zipPath));
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            readfile($zipPath);
            @unlink($zipPath);
            exit(0);
        } catch (\Throwable $e) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'error' => [
                    'code' => 'EXPORT_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], JSON_UNESCAPED_UNICODE);
            exit(0);
        }
    }

    /**
     * Delete a custom language pack.
     */
    public function adminDelete(string $code): JsonResponse
    {
        $installer = $this->installer();
        try {
            $result = $installer->deletePackage($code);
            return $this->success('LANGUAGE_DELETED', $this->t('common/messages.success', 'Success'), $result);
        } catch (\Throwable $e) {
            return $this->error('DELETE_FAILED', $e->getMessage(), 422);
        }
    }
}
