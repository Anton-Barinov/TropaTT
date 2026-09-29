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

        if (!isset($input['is_enabled'])) {
            return $this->error('VALIDATION_ERROR', 'Field "is_enabled" is required.', 422);
        }
        $enabled = (bool)$input['is_enabled'];

        try {
            $result = $this->registry()->toggle($code, $enabled);
            return $this->success('LANGUAGE_TOGGLED', $this->t('common/messages.success', 'Success'), $result);
        } catch (InvalidArgumentException $e) {
            return $this->error('VALIDATION_ERROR', $e->getMessage(), 422);
        }
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
}
