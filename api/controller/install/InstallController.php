<?php
declare(strict_types=1);

namespace Api\Controller\Install;

use Api\Controller\Common\BaseController;
use Api\System\Library\Config;
use Api\System\Library\Service\InstallService;
use Throwable;

final class InstallController extends BaseController
{
    public function status(): \Api\System\Library\Http\JsonResponse
    {
        /** @var InstallService $service */
        $service = $this->container->get('service.install');

        return $this->success('INSTALL_STATUS', $this->t('install/messages.status'), $service->status());
    }

    public function check(): \Api\System\Library\Http\JsonResponse
    {
        if ($this->request()->method === 'GET') {
            return $this->error('METHOD_NOT_ALLOWED', $this->t('install/messages.method_not_allowed'), 405, [
                'method' => ['POST_REQUIRED'],
            ]);
        }

        if ($denied = $this->authorizeBootstrapAccess()) {
            return $denied;
        }

        /** @var InstallService $service */
        $service = $this->container->get('service.install');

        try {
            $data = $service->checkConnection($this->request()->allInput());
            return $this->success('INSTALL_CHECK_OK', $this->t('install/messages.check_ok'), $data);
        } catch (Throwable $e) {
            return $this->error('INSTALL_CHECK_FAILED', $this->t('install/messages.check_failed'), 422, [
                'database' => [$this->t('install/messages.check_failed')],
            ]);
        }
    }

    public function setup(): \Api\System\Library\Http\JsonResponse
    {
        if ($denied = $this->authorizeBootstrapAccess()) {
            return $denied;
        }

        /** @var InstallService $service */
        $service = $this->container->get('service.install');

        try {
            $result = $service->setup($this->request()->allInput());
            if (($result['ok'] ?? false) !== true) {
                $code = (string)($result['code'] ?? 'INSTALL_FAILED');
                $status = $code === 'ALREADY_INSTALLED' ? 409 : 422;
                return $this->error($code, $this->t('install/messages.install_failed'), $status, [
                    'install' => [$code],
                ]);
            }

            return $this->success('INSTALL_COMPLETED', $this->t('install/messages.install_completed'), [
                'installed' => true,
            ]);
        } catch (Throwable $e) {
            return $this->error('INSTALL_FAILED', $this->t('install/messages.install_failed'), 422, [
                'install' => [$this->t('install/messages.install_failed')],
            ]);
        }
    }

    private function authorizeBootstrapAccess(): ?\Api\System\Library\Http\JsonResponse
    {
        if ($this->isLoopbackRequest() && $this->isLoopbackAllowed()) {
            return null;
        }

        /** @var Config $config */
        $config = $this->container->get('config');
        $expected = trim((string)$config->get('install.bootstrap_secret', ''));
        if ($expected === '') {
            return $this->error('INSTALL_BOOTSTRAP_FORBIDDEN', $this->t('install/messages.bootstrap_not_configured'), 403);
        }

        $actual = trim((string)(
            $this->request()->header('X-Install-Token')
            ?? $this->request()->header('X-Bootstrap-Token')
            ?? ''
        ));
        if ($actual === '' || !hash_equals($expected, $actual)) {
            return $this->error('INSTALL_BOOTSTRAP_FORBIDDEN', $this->t('install/messages.bootstrap_token_required'), 403);
        }

        return null;
    }

    private function isLoopbackAllowed(): bool
    {
        /** @var Config $config */
        $config = $this->container->get('config');
        $env = strtolower(trim((string)$config->get('default.app.env', 'prod')));
        if (in_array($env, ['prod', 'production'], true)) {
            return false;
        }

        return (bool)$config->get('install.allow_loopback', true);
    }

    private function isLoopbackRequest(): bool
    {
        $ip = trim((string)$this->request()->ip());
        return in_array($ip, ['127.0.0.1', '::1'], true);
    }
}
