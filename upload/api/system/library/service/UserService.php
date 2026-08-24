<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Role\RoleRepository;
use Api\Model\Security\SessionRepository;
use Api\Model\Team\TeamRepository;
use Api\Model\User\UserManagementRepository;
use Api\System\Library\Service\LogsService;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Policy\HierarchyPolicy;
use Api\System\Library\Security\PasswordHasher;
use Api\System\Library\Support\Ulid;

final class UserService
{
    public function __construct(
        private readonly UserManagementRepository $users,
        private readonly RoleRepository $roles,
        private readonly PasswordHasher $hasher,
        private readonly HierarchyPolicy $policy,
        private readonly JsonLogger $logger,
        private readonly LogsService $logs,
        private readonly SessionRepository $sessions,
        private readonly TeamRepository $teams
    ) {
    }

    public function list(array $filters): array
    {
        [$items, $total, $page, $limit] = $this->users->list($filters);

        return [
            'items' => $items,
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int)ceil($total / max(1, $limit)),
                ],
            ],
        ];
    }

    /**
     * Get a user by public_id.
     *
     * H-4 fix: when $actor is provided, verify hierarchy access for non-root
     * actors (same as update/delete/activity). Controllers that expose user
     * details to arbitrary authenticated callers must pass the actor.
     */
    public function get(string $publicId, ?array $actor = null): ?array
    {
        $item = $this->users->findByPublicId($publicId);
        if (!$item) {
            return null;
        }

        // H-4: object-level authorization — non-root actors may only view
        // users they can manage (own subtree) or themselves.
        if ($actor !== null && !$this->policy->canManageUser($actor, $item)) {
            $actorId = (int)($actor['id'] ?? 0);
            $targetId = (int)($item['id'] ?? 0);
            if ($actorId !== $targetId && !(bool)($actor['is_root'] ?? false)) {
                return null;
            }
        }

        $item['roles'] = $this->users->roleCodesByUserId((int)$item['id']);

        return $item;
    }

    public function create(array $input, array $actor): array
    {
        $actorId = (int)$actor['id'];
        $actorIsRoot = (int)$actor['is_root'] === 1;
        $requestedRoot = (int)($input['is_root'] ?? 0) === 1;

        if ($requestedRoot && !$actorIsRoot) {
            return ['ok' => false, 'code' => 'FORBIDDEN_ROOT_ASSIGN'];
        }

        // Non-root must pass hierarchy check: can only create users in own subtree
        if (!$actorIsRoot) {
            // Actor must be able to manage itself (which is always true) -
            // canManageUser with the actor as both sides confirms the actor
            // has a valid hierarchy position. All created users will be
            // descendants of the actor (created_by_user_id = actorId).
            // HierarchyPolicy::canManageUser will verify actor is not a child
            // of someone else trying to escalate.
            $target = $this->users->findById($actorId);
            if (!$target || !$this->policy->canManageUser($actor, $target)) {
                return ['ok' => false, 'code' => 'FORBIDDEN_HIERARCHY'];
            }
        }

        $rolePublicIds = is_array($input['role_public_ids'] ?? null) ? array_values(array_filter($input['role_public_ids'], 'is_string')) : [];

        $roles = [];
        foreach ($rolePublicIds as $rolePublicId) {
            $role = $this->roles->findByPublicId($rolePublicId);
            if (!$role) {
                return ['ok' => false, 'code' => 'ROLE_NOT_FOUND'];
            }
            if ((string)$role['code'] === 'root' && !$actorIsRoot) {
                return ['ok' => false, 'code' => 'FORBIDDEN_ROOT_ASSIGN'];
            }
            $roles[] = $role;
        }

        $now = gmdate('Y-m-d H:i:s');
        $publicId = Ulid::generate('usr');

        // SEC-002: financial rates (cost_rate, bill_rate, payout_rate)
        // are root-only OR finance.rate.manage data. Never seed them on
        // users created by non-root actors without the rate permission
        // (MCP path may pass them).
        $set = ['cost_rate' => null, 'bill_rate' => null, 'payout_rate' => null];
        if ($actorIsRoot || $this->hasRateManagePerm($actor)) {
            foreach (['cost_rate', 'bill_rate', 'payout_rate'] as $field) {
                if (array_key_exists($field, $input)) {
                    $val = $input[$field];
                    $set[$field] = ($val === null || $val === '' || $val === false) ? null : ((float)$val);
                }
            }
        }

        $userId = $this->users->create([
            'public_id' => $publicId,
            'login' => trim((string)$input['login']),
            'email' => trim((string)($input['email'] ?? '')),
            'password_hash' => $this->hasher->hash((string)$input['password']),
            'auth_token_hash' => !empty($input['token']) ? hash('sha256', (string)$input['token']) : '',
            'full_name' => trim((string)($input['full_name'] ?? '')),
            'locale' => trim((string)($input['locale'] ?? 'en-gb')),
            'is_active' => 1,
            'is_root' => $requestedRoot ? 1 : 0,
            'created_by_user_id' => $actorId,
            'created_at' => $now,
            'updated_at' => $now,
            'cost_rate' => $set['cost_rate'],
            'bill_rate' => $set['bill_rate'],
            'payout_rate' => $set['payout_rate'],
        ]);

        if ($roles !== []) {
            $this->users->replaceRoles($userId, $rolePublicIds);
        } elseif (!$requestedRoot) {
            // INST-3 fix: assign a default non-system role so the user isn't
            // left with zero permissions (which means FORBIDDEN on every route).
            $defaultRole = $this->roles->findDefaultRole();
            if ($defaultRole !== null) {
                $this->users->replaceRoles($userId, [$defaultRole['public_id']]);
            }
        }

        $this->assignToTeam($publicId, $input['team_public_id'] ?? null);

        $this->logger->audit([
            'action' => 'user_create',
            'actor_public_id' => $actor['public_id'],
            'entity_type' => 'user',
            'entity_public_id' => $publicId,
        ]);

        $created = $this->users->findById($userId);

        return ['ok' => true, 'user' => $created ?: ['public_id' => $publicId]];
    }

    public function update(string $publicId, array $input, array $actor): array
    {
        $target = $this->users->findByPublicId($publicId);
        if (!$target) {
            return ['ok' => false, 'code' => 'USER_NOT_FOUND'];
        }

        if (!$this->policy->canManageUser($actor, $target)) {
            return ['ok' => false, 'code' => 'FORBIDDEN_HIERARCHY'];
        }

        $actorIsRoot = (int)$actor['is_root'] === 1;
        $targetIsRoot = (int)$target['is_root'] === 1;

        if ($targetIsRoot && !$actorIsRoot) {
            return ['ok' => false, 'code' => 'FORBIDDEN_ROOT_PROTECTED'];
        }

        // SEC-002: financial rates guarded at service layer so both REST and
        // MCP paths cannot mutate them for actors without finance.rate.manage.
        if (!$actorIsRoot && !$this->hasRateManagePerm($actor)) {
            unset($input['cost_rate'], $input['bill_rate'], $input['payout_rate']);
        }

        $set = [];
        foreach (['email', 'full_name', 'locale'] as $field) {
            if (array_key_exists($field, $input)) {
                $set[$field] = trim((string)$input[$field]);
            }
        }

        foreach (['cost_rate', 'bill_rate', 'payout_rate'] as $field) {
            if (array_key_exists($field, $input)) {
                $val = $input[$field];
                $set[$field] = ($val === null || $val === '' || $val === false) ? null : ((float)$val);
            }
        }

        if (array_key_exists('is_active', $input)) {
            $set['is_active'] = (int)((string)$input['is_active'] === '1');
        }

        // External guest role (client portal): observer (read-only) or executor
        // (freelancer, may log time). Only meaningful for is_external users; the
        // value is validated upstream (UserController) to one of the two roles,
        // and App::run()'s external gate keys off this column, so it must never
        // accept arbitrary strings here.
        $wasExecutor = ($target['external_role'] ?? '') === 'executor';
        if (array_key_exists('external_role', $input)) {
            $extRole = strtolower(trim((string)$input['external_role']));
            if (in_array($extRole, ['observer', 'executor'], true)) {
                $set['external_role'] = $extRole;
                // M-1: downgrading executor → observer must revoke all project
                // grants so stale access doesn't silently revive on re-upgrade.
                if ($wasExecutor && $extRole === 'observer') {
                    $pdo = $this->users->getPdo();
                    $pdo->prepare('DELETE FROM external_user_project_access WHERE user_id = :uid')
                        ->execute([':uid' => (int)$target['id']]);
                    $this->logger->audit([
                        'action' => 'executor_demoted_to_observer',
                        'actor_public_id' => $actor['public_id'] ?? null,
                        'entity_type' => 'user',
                        'entity_public_id' => $publicId,
                        'detail' => 'project_grants_revoked',
                    ]);
                }
            }
        }

        if (array_key_exists('password', $input) && trim((string)$input['password']) !== '') {
            $set['password_hash'] = $this->hasher->hash((string)$input['password']);
        }

        if (array_key_exists('token', $input) && trim((string)$input['token']) !== '') {
            $set['auth_token_hash'] = hash('sha256', (string)$input['token']);
        }

        if (array_key_exists('is_root', $input)) {
            $requestedRoot = (int)((string)$input['is_root'] === '1');
            if (!$actorIsRoot) {
                return ['ok' => false, 'code' => 'FORBIDDEN_ROOT_ASSIGN'];
            }
            // L-12 fix: root cannot de-root themselves — this would lock out
            // the only user who can promote others, creating a deadlock.
            if ($requestedRoot === 0 && (int)($actor['id'] ?? 0) === (int)($target['id'] ?? 0)) {
                return ['ok' => false, 'code' => 'FORBIDDEN_SELF_DEROOT'];
            }
            $set['is_root'] = $requestedRoot;
        }

        if (array_key_exists('role_public_ids', $input) && is_array($input['role_public_ids'])) {
            $rolePublicIds = array_values(array_filter($input['role_public_ids'], 'is_string'));
            foreach ($rolePublicIds as $rolePublicId) {
                $role = $this->roles->findByPublicId($rolePublicId);
                if (!$role) {
                    return ['ok' => false, 'code' => 'ROLE_NOT_FOUND'];
                }
                if ((string)$role['code'] === 'root' && !$actorIsRoot) {
                    return ['ok' => false, 'code' => 'FORBIDDEN_ROOT_ASSIGN'];
                }
            }

            $this->users->replaceRoles((int)$target['id'], $rolePublicIds);
        }

        $this->assignToTeam($publicId, $input['team_public_id'] ?? null);

        $set['updated_at'] = gmdate('Y-m-d H:i:s');
        $this->users->updateByPublicId($publicId, $set);

        $this->logger->audit([
            'action' => 'user_update',
            'actor_public_id' => $actor['public_id'],
            'entity_type' => 'user',
            'entity_public_id' => $publicId,
        ]);

        return ['ok' => true, 'user' => $this->users->findByPublicId($publicId)];
    }

    public function delete(string $publicId, array $actor): array
    {
        $target = $this->users->findByPublicId($publicId);
        if (!$target) {
            return ['ok' => false, 'code' => 'USER_NOT_FOUND'];
        }

        if ((int)$target['is_root'] === 1) {
            return ['ok' => false, 'code' => 'FORBIDDEN_ROOT_PROTECTED'];
        }

        if (!$this->policy->canManageUser($actor, $target)) {
            return ['ok' => false, 'code' => 'FORBIDDEN_HIERARCHY'];
        }

        $ok = $this->users->softDelete($publicId, gmdate('Y-m-d H:i:s'));

        if ($ok) {
            // SEC-005: Revoke all active sessions on user deletion
            $this->sessions->revokeAllByUserId((int)$target['id'], gmdate('Y-m-d H:i:s'));

            $this->logger->audit([
                'action' => 'user_delete',
                'actor_public_id' => $actor['public_id'],
                'entity_type' => 'user',
                'entity_public_id' => $publicId,
            ]);
        }

        return ['ok' => $ok, 'code' => $ok ? 'OK' : 'USER_NOT_FOUND'];
    }

    public function tokenInfo(string $publicId, array $actor): array
    {
        $target = $this->users->findByPublicId($publicId);
        if (!$target) {
            return ['ok' => false, 'code' => 'USER_NOT_FOUND'];
        }

        if (!$this->canReadSensitive($actor, $target)) {
            return ['ok' => false, 'code' => 'FORBIDDEN_HIERARCHY'];
        }

        return [
            'ok' => true,
            'token' => [
                'has_token_factor' => trim((string)($target['auth_token_hash'] ?? '')) !== '',
            ],
        ];
    }

    public function rotateToken(string $publicId, array $input, array $actor): array
    {
        $target = $this->users->findByPublicId($publicId);
        if (!$target) {
            return ['ok' => false, 'code' => 'USER_NOT_FOUND'];
        }

        if (!$this->canManageSensitive($actor, $target)) {
            return ['ok' => false, 'code' => 'FORBIDDEN_HIERARCHY'];
        }

        $plainToken = trim((string)($input['token'] ?? ''));
        if ($plainToken === '') {
            $plainToken = 'tok_' . bin2hex(random_bytes(16));
        }

        $this->users->updateByPublicId($publicId, [
            'auth_token_hash' => hash('sha256', $plainToken),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->logger->security([
            'event_type' => 'user_token_rotated',
            'actor_public_id' => $actor['public_id'] ?? null,
            'target_user_public_id' => $publicId,
        ]);

        return [
            'ok' => true,
            'plain_token' => $plainToken,
        ];
    }

    public function revokeToken(string $publicId, array $actor): array
    {
        $target = $this->users->findByPublicId($publicId);
        if (!$target) {
            return ['ok' => false, 'code' => 'USER_NOT_FOUND'];
        }

        if (!$this->canManageSensitive($actor, $target)) {
            return ['ok' => false, 'code' => 'FORBIDDEN_HIERARCHY'];
        }

        $this->users->updateByPublicId($publicId, [
            'auth_token_hash' => '',
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->logger->security([
            'event_type' => 'user_token_revoked',
            'actor_public_id' => $actor['public_id'] ?? null,
            'target_user_public_id' => $publicId,
        ]);

        return ['ok' => true];
    }

    public function activity(string $publicId, array $filters, array $actor): array
    {
        $target = $this->users->findByPublicId($publicId);
        if (!$target) {
            return ['ok' => false, 'code' => 'USER_NOT_FOUND'];
        }

        if (!$this->canReadSensitive($actor, $target)) {
            return ['ok' => false, 'code' => 'FORBIDDEN_HIERARCHY'];
        }

        $request = $this->logs->requestList(array_merge($filters, ['user_public_id' => $publicId]));
        $security = $this->logs->securityList(array_merge($filters, ['actor_public_id' => $publicId]));
        $audit = $this->logs->auditList(array_merge($filters, ['actor_public_id' => $publicId]));

        return [
            'ok' => true,
            'request' => $request,
            'security' => $security,
            'audit' => $audit,
        ];
    }

    private function canManageSensitive(array $actor, array $target): bool
    {
        $actorIsRoot = (bool)($actor['is_root'] ?? false);
        $targetIsRoot = (bool)($target['is_root'] ?? false);
        if ($targetIsRoot && !$actorIsRoot) {
            return false;
        }

        return $this->policy->canManageUser($actor, $target);
    }

    private function canReadSensitive(array $actor, array $target): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        if ((int)($actor['id'] ?? 0) === (int)($target['id'] ?? 0)) {
            return true;
        }

        return $this->policy->canManageUser($actor, $target);
    }

    private function assignToTeam(string $userPublicId, ?string $teamPublicId): void
    {
        if ($teamPublicId === null || $teamPublicId === '') {
            return;
        }

        $team = $this->teams->findByPublicId($teamPublicId);
        if (!$team) {
            return;
        }

        // member_user_ids stores integer user IDs, not public IDs
        $memberIds = json_decode((string)($team['member_user_ids'] ?? '[]'), true) ?: [];

        // Find the user's integer ID
        $user = $this->users->findByPublicId($userPublicId);
        if (!$user) {
            return;
        }
        $userIdInt = (int)$user['id'];

        if (!in_array($userIdInt, $memberIds, true)) {
            $memberIds[] = $userIdInt;
            $this->teams->updateByPublicId($teamPublicId, [
                'member_user_ids' => json_encode($memberIds),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Check whether the actor has the finance.rate.manage permission.
     *
     * This is used instead of is_root to allow managers with the
     * finance.rate.manage permission to set cost/bill/payout rates
     * on users within their visibility (visibility is enforced by
     * canManageUser() in update() and by the actor creating users
     * under their own hierarchy in create()).
     */
    private function hasRateManagePerm(array $actor): bool
    {
        $codes = (array)($actor['permission_codes'] ?? []);
        return in_array('*', $codes, true) || in_array('finance.rate.manage', $codes, true);
    }
}
