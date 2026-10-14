<?php
declare(strict_types=1);

namespace Api\Controller\App;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\AppCapabilitiesService;

final class CapabilitiesController extends BaseController
{
    public function show(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth || !is_array($auth['user'] ?? null)) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }
        $user = $auth['user'];

        $permissions = [];
        if ($this->container->has('service.authz')) {
            /** @var \Api\System\Library\Service\AuthzService $authz */
            $authz = $this->container->get('service.authz');
            $permissions = $authz->permissionsForUser($user);
        }

        $organization = null;
        if ($this->container->has('service.organization_context')) {
            /** @var \Api\System\Library\Service\OrganizationContextService $context */
            $context = $this->container->get('service.organization_context');
            $resolved = $context->resolve($this->request(), $user);
            if (($resolved['status'] ?? '') === 'active' && is_array($resolved['organization'] ?? null)) {
                $organization = $resolved['organization'];
            }
        }

        $sessionTtlSeconds = (int)$this->container->get('config')->get('security.auth.access_token_ttl', 604800);

        /** @var AppCapabilitiesService $service */
        $service = $this->container->get('service.app_capabilities');

        return $this->success(
            'APP_CAPABILITIES',
            $this->t('app/messages.capabilities'),
            $service->manifest($user, $organization, $permissions, $sessionTtlSeconds)
        );
    }
}
