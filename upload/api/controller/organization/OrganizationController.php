<?php
declare(strict_types=1);

namespace Api\Controller\Organization;

use Api\System\Library\Module\ModuleEvents;
use Api\Controller\Common\BaseController;
use Api\System\Library\Service\OrganizationService;
use Api\System\Library\Validation\Validator;

final class OrganizationController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var OrganizationService $service */
        $service = $this->container->get('service.organization');
        $result = $service->list($this->request()->allInput(), $auth['user']);

        return $this->success('ORGANIZATION_LIST', $this->t('organization/messages.list'), ['items' => $result['items']], meta: $result['meta']);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'title', $this->t('common/messages.field_required'))->maxLen($input, 'title', 255, $this->t('organization/messages.max_255'));
        $v->maxLen($input, 'slug', 120, $this->t('organization/messages.max_120'));
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var OrganizationService $service */
        $service = $this->container->get('service.organization');
        $organization = $service->create($input, $auth['user']);

        $this->dispatchModuleHook(ModuleEvents::ORGANIZATION_CREATED, [
            'organization_public_id' => (string)($organization['public_id'] ?? ''),
            'title' => (string)($organization['title'] ?? ''),
            'actor_id' => (int)($this->user()['user']['id'] ?? 0),
        ]);

        return $this->success('ORGANIZATION_CREATED', $this->t('organization/messages.created'), ['organization' => $organization], 201);
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var OrganizationService $service */
        $service = $this->container->get('service.organization');
        $organization = $service->get((string)$params['public_id'], $auth['user']);
        if (!$organization) {
            return $this->error('ORGANIZATION_NOT_FOUND', $this->t('organization/messages.not_found'), 404, ['organization' => [$this->t('organization/messages.not_found')]]);
        }

        return $this->success('ORGANIZATION_DETAIL', $this->t('organization/messages.detail'), ['organization' => $organization]);
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->maxLen($input, 'title', 255, $this->t('organization/messages.max_255'));
        $v->maxLen($input, 'slug', 120, $this->t('organization/messages.max_120'));
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var OrganizationService $service */
        $service = $this->container->get('service.organization');
        $organization = $service->update((string)$params['public_id'], $input, $auth['user']);
        if (!$organization) {
            return $this->error('ORGANIZATION_NOT_FOUND', $this->t('organization/messages.not_found'), 404, ['organization' => [$this->t('organization/messages.not_found')]]);
        }

        $this->dispatchModuleHook(ModuleEvents::ORGANIZATION_UPDATED, [
            'organization_public_id' => (string)($organization['public_id'] ?? ''),
            'title' => (string)($organization['title'] ?? ''),
            'actor_id' => (int)($this->user()['user']['id'] ?? 0),
        ]);

        return $this->success('ORGANIZATION_UPDATED', $this->t('organization/messages.updated'), ['organization' => $organization]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var OrganizationService $service */
        $service = $this->container->get('service.organization');
        $ok = $service->delete((string)$params['public_id'], $auth['user']);
        if (!$ok) {
            return $this->error('ORGANIZATION_NOT_FOUND', $this->t('organization/messages.delete_not_found_or_forbidden'), 404, ['organization' => [$this->t('organization/messages.delete_not_found_or_forbidden')]]);
        }

        $this->dispatchModuleHook(ModuleEvents::ORGANIZATION_DELETED, [
            'organization_public_id' => (string)($params['public_id']),
            'actor_id' => (int)($this->user()['user']['id'] ?? 0),
        ]);

        return $this->success('ORGANIZATION_DELETED', $this->t('organization/messages.deleted'));
    }

    public function listMembers(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var OrganizationService $service */
        $service = $this->container->get('service.organization');
        $items = $service->listMembers((string)$params['public_id'], $auth['user'], $this->request()->allInput());
        if ($items === null) {
            return $this->error('ORGANIZATION_NOT_FOUND', $this->t('organization/messages.not_found'), 404, ['organization' => [$this->t('organization/messages.not_found')]]);
        }

        return $this->success('ORGANIZATION_MEMBERS_LIST', $this->t('organization/messages.members_list'), ['items' => $items]);
    }

    public function addMember(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'user_public_id', $this->t('common/messages.field_required'));
        $v->enum($input, 'role_code', ['owner', 'admin', 'member'], $this->t('organization/messages.invalid_role'));
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        $roleCode = (string)($input['role_code'] ?? 'member');

        /** @var OrganizationService $service */
        $service = $this->container->get('service.organization');
        $ok = $service->addMember((string)$params['public_id'], (string)$input['user_public_id'], $roleCode, $auth['user']);
        if (!$ok) {
            $code = $service->memberError();
            return $this->error($code, $this->t('organization/messages.' . strtolower($code), $this->t('organization/messages.member_upsert_failed')), 422, ['organization' => [$code]]);
        }

        return $this->success('ORGANIZATION_MEMBER_UPSERTED', $this->t('organization/messages.member_upserted'));
    }

    public function updateMemberRole(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        $input = $this->request()->allInput();
        $v = new Validator();
        $v->enum($input, 'role_code', ['owner', 'admin', 'member'], $this->t('organization/messages.invalid_role'));
        if ($v->fails()) return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        /** @var OrganizationService $service */
        $service = $this->container->get('service.organization');
        $ok = $service->updateMemberRole((string)$params['public_id'], (string)$params['user_public_id'], (string)$input['role_code'], $auth['user']);
        if (!$ok) {
            $code = $service->memberError();
            return $this->error($code, $this->t('organization/messages.' . strtolower($code), $this->t('organization/messages.member_role_update_failed')), 422, ['member' => [$code]]);
        }
        return $this->success('ORGANIZATION_MEMBER_ROLE_UPDATED', $this->t('organization/messages.member_role_updated'));
    }

    public function removeMember(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var OrganizationService $service */
        $service = $this->container->get('service.organization');
        $ok = $service->removeMember((string)$params['public_id'], (string)$params['user_public_id'], $auth['user']);
        if (!$ok) {
            $code = $service->memberError();
            return $this->error($code, $this->t('organization/messages.' . strtolower($code), $this->t('organization/messages.member_remove_failed')), 422, ['organization' => [$code]]);
        }

        return $this->success('ORGANIZATION_MEMBER_REMOVED', $this->t('organization/messages.member_removed'));
    }
}
