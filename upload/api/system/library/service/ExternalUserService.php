<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Auth\AuthRepository;
use Api\Model\Common\UserRepository;
use Api\Model\Project\ProjectRepository;
use Api\Model\Role\RoleRepository;
use Api\Model\Contact\ContactRepository;
use Api\System\Library\Config;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Security\PasswordHasher;
use Api\System\Library\Security\TokenManager;
use Api\System\Library\Support\Ulid;

/**
 * Service for managing external guest users (client portal).
 *
 * External users are invited from a Contact record linked to a Counterparty.
 *
 * Two roles, chosen at invite time (users.external_role):
 *   - 'observer' (default) — a client contact. Scoped by RLS to every
 *     project/task belonging to their own counterparty (client_public_id).
 *     This is the original, unchanged behaviour.
 *   - 'executor' — a freelancer/contractor. NOT scoped by counterparty at
 *     all (a freelancer may work for many different counterparties at once).
 *     Instead scoped to an explicit, per-project allowlist stored in
 *     external_user_project_access — narrow, auditable, independently
 *     revocable grants. See getExecutorProjectPublicIds()/grantProjectAccess().
 */
final class ExternalUserService
{
    public const ROLE_OBSERVER = 'observer';
    public const ROLE_EXECUTOR = 'executor';

    public function __construct(
        private readonly UserRepository $users,
        private readonly RoleRepository $roles,
        private readonly ContactRepository $contacts,
        private readonly ContactService $contactService,
        private readonly PasswordHasher $hasher,
        private readonly TokenManager $tokens,
        private readonly JsonLogger $logger,
        private readonly Config $config,
        private readonly AuthRepository $auth,
        // Deliberately ProjectRepository, not ProjectService: ProjectService
        // itself depends on ExternalUserService (for RLS), and the container
        // caches an entry only after its factory returns — injecting the
        // service here would recurse forever the first time either is
        // resolved. The repository has no such dependency, so the minimal
        // object-level ownership check below is reimplemented inline instead
        // of delegating to ProjectService::canAccess()/get().
        private readonly ?ProjectRepository $projectRepo = null,
    ) {
    }

    /**
     * Minimal re-implementation of ProjectService::canAccess()'s internal
     * (non-external) branch, for the one place ExternalUserService itself
     * needs to check whether an internal actor may reach a given project
     * (granting/revoking an executor's project access). See the constructor
     * docblock for why this doesn't simply call ProjectService.
     */
    private function actorCanReachProject(array $project, array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }
        $actorId = (int)($actor['id'] ?? 0);
        if ($actorId <= 0) {
            return false;
        }
        if ((int)($project['created_by_user_id'] ?? 0) === $actorId
            || (int)($project['manager_user_id'] ?? 0) === $actorId
            || (int)($project['team_manager_user_id'] ?? 0) === $actorId) {
            return true;
        }
        $raw = $project['team_member_user_ids'] ?? null;
        if ($raw === null || $raw === '') {
            return false;
        }
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($decoded)) {
            return false;
        }
        foreach ($decoded as $memberId) {
            if ((int)$memberId === $actorId) {
                return true;
            }
        }
        return false;
    }

    private function normalizeRole(mixed $role): string
    {
        $role = strtolower(trim((string)$role));
        return $role === self::ROLE_EXECUTOR ? self::ROLE_EXECUTOR : self::ROLE_OBSERVER;
    }

    /**
     * Invite by contact public_id (resolves to internal id).
     */
    public function inviteByPublicId(string $contactPublicId, array $actor, string $role = self::ROLE_OBSERVER): array
    {
        // Resolve through ContactService, not the repository, so the same
        // hierarchy/object-level access policy as the contacts UI is enforced.
        // A caller must not be able to invite a contact from another user's
        // scope by guessing its public_id.
        $contact = $this->contactService->get($contactPublicId, $actor);
        if (!$contact) {
            return ['ok' => false, 'error' => 'contact_not_found'];
        }
        return $this->invite((int)$contact['id'], $actor, $role);
    }

    /**
     * Invite an external user from a contact record.
     *
     * Creates a pending user account and returns an invitation token
     * that can be sent via email.
     *
     * @return array{ok:bool, token?:string, user_public_id?:string, error?:string}
     */
    public function invite(int $contactId, array $actor, string $role = self::ROLE_OBSERVER): array
    {
        $userId = (int)($actor['id'] ?? 0);
        if ($userId <= 0) {
            return ['ok' => false, 'error' => 'unauthorized'];
        }
        $role = $this->normalizeRole($role);

        $pdo = $this->users->getPdo();
        $startedTransaction = false;
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTransaction = true;
        }

        try {
            // Lock the contact before checking/creating its linked account. This
            // closes the double-click/concurrent-request race where two invites
            // both observed an unlinked contact and created two guest accounts.
            // The transaction also keeps user creation, role assignment and the
            // contact link atomic.
            $contact = $this->contacts->findByIdForUpdate($contactId);
            if (!$contact || !$this->contactService->get((string)($contact['public_id'] ?? ''), $actor)) {
            return ['ok' => false, 'error' => 'contact_not_found'];
        }

            // Must have a counterparty linked
            $counterpartyId = (int)($contact['counterparty_id'] ?? 0);
        if ($counterpartyId <= 0) {
            return ['ok' => false, 'error' => 'contact_has_no_counterparty'];
        }

            // Contact must have an email
            $email = trim((string)($contact['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'contact_has_no_valid_email'];
        }

            // An inactive external account can be safely re-invited (after a
        // revoke or an expired invitation). Active accounts must be revoked
        // explicitly instead of silently replacing their access.
        $existingUserId = (int)($contact['user_id'] ?? 0);
        $existingUser = $existingUserId > 0 ? $this->users->findById($existingUserId) : null;
        if ($existingUserId > 0 && !$existingUser) {
            return ['ok' => false, 'error' => 'contact_has_linked_user'];
        }
        if ($existingUser && ($existingUser['deleted_at'] ?? null) !== null) {
            // A soft-deleted external account must never be reactivated. Unlink
            // that retired account and create a fresh guest account instead;
            // findByEmail() ignores deleted users, so historical credentials
            // cannot block or revive the new invitation.
            if ((int)($existingUser['is_external'] ?? 0) !== 1) {
                return ['ok' => false, 'error' => 'contact_has_linked_user'];
            }
            $this->contacts->updateById($contactId, ['user_id' => null]);
            $existingUserId = 0;
            $existingUser = null;
        }
        if ($existingUser && (int)($existingUser['is_external'] ?? 0) !== 1) {
            return ['ok' => false, 'error' => 'contact_has_linked_user'];
        }
        if ($existingUser && (int)($existingUser['is_active'] ?? 0) === 1) {
            return ['ok' => false, 'error' => 'contact_already_has_external_user'];
        }

            // Do not attach a contact to another account that happens to use the
        // same email. The one exception is this contact's own inactive guest,
        // which is the account being re-invited.
        $existingByEmail = $this->users->findByEmail($email);
        if ($existingByEmail && (int)($existingByEmail['id'] ?? 0) !== $existingUserId) {
            return ['ok' => false, 'error' => 'email_already_registered'];
        }

            // The guest's login is their email (point 6 of the owner's spec —
        // no more opaque ext_xxxxxxxxxxxx logins). login and email are
        // separate columns, so an internal employee's login could in theory
        // already equal this email string even though findByEmail() above
        // only checked the email column; guard that independently too.
        $loginConflict = $this->users->findByLogin($email);
        if ($loginConflict && (int)($loginConflict['id'] ?? 0) !== $existingUserId) {
            return ['ok' => false, 'error' => 'login_email_conflict'];
        }

            // Find the external_guest role
            $externalRole = $this->roles->findByCode('external_guest');
        if (!$externalRole) {
            return ['ok' => false, 'error' => 'external_guest_role_not_found'];
        }

            $now = gmdate('Y-m-d H:i:s');
            $expiresAt = gmdate('Y-m-d H:i:s', time() + 604800); // 7 days

            // Generate a fresh one-time invitation token on every invite/resend.
            $token = $this->tokens->generate(32);
            $tokenHash = $this->tokens->hash($token);
            $fullName = trim((string)($contact['full_name'] ?? $email));

            $login = $email;
            if ($existingUser) {
            $createdUserId = (int)$existingUser['id'];
            $userPublicId = (string)$existingUser['public_id'];
            // A previously active session must not be resurrected when a
            // revoked account is invited again and activated later.
            $this->auth->revokeAllByUserId($createdUserId, $now);
            $this->users->updateById($createdUserId, [
                'login' => $login,
                'email' => $email,
                'full_name' => $fullName,
                'password_hash' => $this->hasher->hash('__pending__'),
                'auth_token_hash' => $tokenHash,
                'external_invitation_expires_at' => $expiresAt,
                'external_role' => $role,
                'is_active' => 0,
                'updated_at' => $now,
            ]);
            } else {
                $userPublicId = Ulid::generate('usr');
            $createdUserId = $this->users->create([
                'public_id' => $userPublicId,
                'login' => $login,
                'email' => $email,
                'password_hash' => $this->hasher->hash('__pending__'),
                'auth_token_hash' => $tokenHash,
                'external_invitation_expires_at' => $expiresAt,
                'external_role' => $role,
                'full_name' => $fullName,
                'locale' => 'ru-ru',
                'is_active' => 0,
                'is_external' => 1,
                'is_root' => 0,
                'created_by_user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

            // Assign external_guest role
            $this->roles->assignToUser($createdUserId, (int)$externalRole['id']);

            // Link contact → user
            $this->contacts->updateById($contactId, [
                'user_id' => $createdUserId,
            ]);

            // Executor bootstrap grant: give immediate access to every current
            // project of the inviting contact's own counterparty, exactly like
            // an observer would see. This keeps day-one behaviour familiar; any
            // additional projects (other counterparties, point 7 of the spec)
            // are granted explicitly afterwards via grantProjectAccess().
            if ($role === self::ROLE_EXECUTOR) {
                $this->bootstrapExecutorGrants($createdUserId, $counterpartyId, $userId, $now);
            }

            if ($startedTransaction) {
                $pdo->commit();
            }
        } finally {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }

        $this->logger->audit([
            'action' => 'external_user_invited',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'user',
            'entity_public_id' => $userPublicId,
            'contact_id' => $contactId,
            'counterparty_id' => $counterpartyId,
            'external_role' => $role,
        ]);

        return [
            'ok' => true,
            'token' => $token,
            'user_public_id' => $userPublicId,
            'login' => $login,
            'email' => $email,
            'external_role' => $role,
            'invitation_expires_at' => $expiresAt,
        ];
    }

    /**
     * Bootstrap executor project grants for an existing external user, using
     * the counterparty linked via their contact record (same day-one project
     * visibility an observer on that counterparty would have). Used when an
     * admin promotes an observer guest to the executor role through
     * PATCH /users — the external gate keys off users.external_role, but
     * worklog RLS additionally requires explicit project grants, which are
     * normally created at invite time only.
     *
     * Safe to call for any external user; no-op when the user has no contact
     * or is not an executor. Idempotent (INSERT IGNORE / OR IGNORE).
     */
    public function bootstrapGrantsForExternalUser(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        $user = $this->users->findById($userId);
        if (!$user || (int)($user['is_external'] ?? 0) !== 1) {
            return false;
        }
        if ($this->getExternalRole($userId) !== self::ROLE_EXECUTOR) {
            return false;
        }
        $contact = $this->contacts->findByUserId($userId);
        if (!$contact) {
            return false;
        }
        $counterpartyId = (int)($contact['counterparty_id'] ?? 0);
        if ($counterpartyId <= 0) {
            return false;
        }
        $this->bootstrapExecutorGrants($userId, $counterpartyId, $userId, gmdate('Y-m-d H:i:s'));
        return true;
    }

    /**
     * Grant an executor guest access to every non-deleted project currently
     * belonging to a counterparty. Used once, at invite time, so an executor
     * invited "the normal way" (from a contact) starts with the same
     * project visibility an observer on that counterparty would have.
     * Idempotent (INSERT IGNORE / OR IGNORE) — safe to call on resend too.
     */
    private function bootstrapExecutorGrants(int $userId, int $counterpartyId, int $grantedByUserId, string $now): void
    {
        $pdo = $this->users->getPdo();
        $stmt = $pdo->prepare(
            "SELECT p.id FROM projects p
             INNER JOIN counterparties cp ON cp.public_id = p.client_public_id
             WHERE cp.id = :cpid AND p.archived_at IS NULL"
        );
        $stmt->execute([':cpid' => $counterpartyId]);
        $projectIds = array_map(static fn(array $r): int => (int)$r['id'], $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []);

        foreach ($projectIds as $projectId) {
            $this->insertProjectAccessIgnoreDuplicate($userId, $projectId, $grantedByUserId, $now);
        }
    }

    private function insertProjectAccessIgnoreDuplicate(int $userId, int $projectId, int $grantedByUserId, string $now): void
    {
        $pdo = $this->users->getPdo();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? "INSERT OR IGNORE INTO external_user_project_access (user_id, project_id, granted_by_user_id, created_at) VALUES (:uid, :pid, :gid, :now)"
            : "INSERT IGNORE INTO external_user_project_access (user_id, project_id, granted_by_user_id, created_at) VALUES (:uid, :pid, :gid, :now)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':uid' => $userId, ':pid' => $projectId, ':gid' => $grantedByUserId ?: null, ':now' => $now]);
    }

    /**
     * Accept an external user invitation (set password and activate).
     *
     * @return array{ok:bool, user?:array, error?:string}
     */
    public function acceptInvitation(array $input): array
    {
        $token = trim((string)($input['token'] ?? ''));
        $password = (string)($input['password'] ?? '');

        if ($token === '') {
            return ['ok' => false, 'error' => 'token_required'];
        }

        if (mb_strlen($password) < 8) {
            return ['ok' => false, 'error' => 'weak_password'];
        }
        if (mb_strlen($password) > 1024) {
            return ['ok' => false, 'error' => 'password_too_long'];
        }

        $tokenHash = $this->tokens->hash($token);

        // Find user by auth_token_hash
        $user = $this->users->findByAuthTokenHash($tokenHash);
        if (!$user) {
            return ['ok' => false, 'error' => 'invalid_token'];
        }

        // Must be an external user
        if ((int)($user['is_external'] ?? 0) !== 1) {
            return ['ok' => false, 'error' => 'not_external_user'];
        }

        // Must be inactive (pending) and within the server-side invitation
        // lifetime. The token is one-time and its expiry is independent of
        // the user's regular session lifetime.
        if ((int)($user['is_active'] ?? 0) !== 0) {
            return ['ok' => false, 'error' => 'account_already_active'];
        }
        $invitationExpiresAt = strtotime((string)($user['external_invitation_expires_at'] ?? '') . ' UTC');
        if ($invitationExpiresAt === false || $invitationExpiresAt <= time()) {
            return ['ok' => false, 'error' => 'invalid_token'];
        }

        $now = gmdate('Y-m-d H:i:s');
        $passwordHash = $this->hasher->hash($password);

        // Consume the invitation and create the first session atomically. The
        // token predicate makes concurrent accepts one-time: only the request
        // that changes the pending row wins; every other request gets 0 rows.
        $sessionToken = $this->tokens->generate(32);
        $sessionTokenHash = $this->tokens->hash($sessionToken);
        $sessionPublicId = Ulid::generate('ses');
        $expiresAt = gmdate('Y-m-d H:i:s', time() + 259200); // 3 days
        $pdo = $this->users->getPdo();
        $startedTransaction = false;

        try {
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $startedTransaction = true;
            }

            if (!$this->users->activateExternalInvitation(
                (int)$user['id'],
                $tokenHash,
                $passwordHash,
                $now
            )) {
                if ($startedTransaction) {
                    $pdo->rollBack();
                }
                return ['ok' => false, 'error' => 'invalid_token'];
            }

            // The session row is required by AuthService::me(). The invitation
            // hash is cleared by activateExternalInvitation(), so password-only
            // login is available immediately after acceptance.
            $this->auth->createSession([
                'public_id' => $sessionPublicId,
                'user_id' => (int)$user['id'],
                'token_hash' => $sessionTokenHash,
                'ip' => '',
                'user_agent' => '',
                'device_fingerprint' => '',
                'device_name' => 'External portal accept',
                'expires_at' => $expiresAt,
                'created_at' => $now,
            ]);

            if ($startedTransaction) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'error' => 'activation_failed'];
        }

        $this->logger->audit([
            'action' => 'external_user_activated',
            'entity_type' => 'user',
            'entity_public_id' => $user['public_id'] ?? null,
        ]);

        return [
            'ok' => true,
            'user' => [
                'public_id' => $user['public_id'] ?? '',
                'login' => $user['login'] ?? '',
                'full_name' => $user['full_name'] ?? '',
            ],
            'session_token' => $sessionToken,
        ];
    }

    /**
     * Deactivate an external user (revoke access but keep history).
     *
     * @return array{ok:bool, error?:string}
     */
    public function deactivate(string $userPublicId, array $actor): array
    {
        $userId = (int)($actor['id'] ?? 0);
        if ($userId <= 0) {
            return ['ok' => false, 'error' => 'unauthorized'];
        }

        $user = $this->users->findByPublicId($userPublicId);
        if (!$user) {
            return ['ok' => false, 'error' => 'user_not_found'];
        }

        if ((int)($user['is_external'] ?? 0) !== 1 || ($user['deleted_at'] ?? null) !== null) {
            return ['ok' => false, 'error' => 'user_not_found'];
        }

        // Deactivation is object-level protected: contact.manage alone must
        // not allow revoking a guest belonging to another user's hierarchy.
        $contact = $this->contacts->findByUserId((int)$user['id']);
        if (!$contact || !$this->contactService->get((string)($contact['public_id'] ?? ''), $actor)) {
            return ['ok' => false, 'error' => 'user_not_found'];
        }

        $now = gmdate('Y-m-d H:i:s');
        // One write clears both the active session gate and the invitation
        // secret, so a revoked user cannot authenticate or reactivate via an
        // old link.
        $this->auth->revokeAllByUserId((int)$user['id'], $now);
        $this->users->updateById((int)$user['id'], [
            'is_active' => 0,
            'auth_token_hash' => null,
            'external_invitation_expires_at' => null,
            'updated_at' => $now,
        ]);

        $this->logger->audit([
            'action' => 'external_user_deactivated',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'user',
            'entity_public_id' => $userPublicId,
        ]);

        return ['ok' => true];
    }

    /**
     * Get the counterparty_id for an external user.
     * Returns 0 if the user is not external or has no linked contact.
     */
    public function getCounterpartyIdForUser(int $userId): int
    {
        $user = $this->users->findById($userId);
        if (!$user || (int)($user['is_external'] ?? 0) !== 1) {
            return 0;
        }

        // Find the contact linked to this user
        $contact = $this->contacts->findByUserId($userId);
        if (!$contact) {
            return 0;
        }

        return (int)($contact['counterparty_id'] ?? 0);
    }

    /**
     * Get the counterparty public_id for an external user.
     * Returns empty string if user is not external or has no counterparty.
     */
    public function getCounterpartyPublicId(int $userId): string
    {
        $counterpartyId = $this->getCounterpartyIdForUser($userId);
        if ($counterpartyId <= 0) {
            return '';
        }
        $stmt = $this->users->getPdo()->prepare(
            "SELECT public_id FROM counterparties WHERE id = :id"
        );
        $stmt->execute([':id' => $counterpartyId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (string)($row['public_id'] ?? '');
    }

    /**
     * Check if user is an external guest.
     */
    public function isExternalUser(int $userId): bool
    {
        $user = $this->users->findById($userId);
        return $user !== null && (int)($user['is_external'] ?? 0) === 1;
    }

    /**
     * Get project public_ids accessible by an external user.
     * Returns empty array if user is not external (internal users see everything).
     *
     * @return list<string>
     */
    public function getAccessibleProjectIds(int $userId): array
    {
        $counterpartyId = $this->getCounterpartyIdForUser($userId);
        if ($counterpartyId <= 0) {
            return []; // Not external or no counterparty
        }

        // Get counterparty public_id
        $counterpartyPublicId = $this->getCounterpartyPublicId($userId);
        if ($counterpartyPublicId === '') {
            return [];
        }

        // Find projects linked to this counterparty
        $stmt = $this->users->getPdo()->prepare(
            "SELECT public_id FROM projects WHERE client_public_id = :cpid AND archived_at IS NULL"
        );
        $stmt->execute([':cpid' => $counterpartyPublicId]);
        $ids = [];
        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $ids[] = (string)($r['public_id'] ?? '');
        }
        return $ids;
    }

    /**
     * List all external users with their contact/counterparty info.
     *
     * @return array{items:list<array>, total:int}
     */
    public function listExternalUsers(array $actor): array
    {
        $userId = (int)($actor['id'] ?? 0);
        if ($userId <= 0) {
            return ['items' => [], 'total' => 0];
        }

        $pdo = $this->users->getPdo();
        $where = ['u.is_external = 1', 'u.deleted_at IS NULL'];
        $params = [];
        if ((int)($actor['is_root'] ?? 0) !== 1) {
            $contactIds = [];
            $page = 1;
            do {
                $contactResult = $this->contactService->list(['page' => $page, 'limit' => 100], $actor);
                $contactItems = (array)($contactResult['items'] ?? []);
                foreach ($contactItems as $contact) {
                    $contactId = (int)($contact['id'] ?? 0);
                    if ($contactId > 0 && (int)($contact['user_id'] ?? 0) > 0) {
                        $contactIds[] = $contactId;
                    }
                }
                $totalPages = (int)($contactResult['meta']['pagination']['pages'] ?? $page);
                $page++;
            } while ($contactItems !== [] && $page <= $totalPages);

            $contactIds = array_values(array_unique($contactIds));
            if ($contactIds === []) {
                return ['items' => [], 'total' => 0];
            }
            $placeholders = [];
            foreach ($contactIds as $index => $contactId) {
                $key = ':contact_' . $index;
                $placeholders[] = $key;
                $params[$key] = $contactId;
            }
            $where[] = 'c.id IN (' . implode(', ', $placeholders) . ')';
        }

        $stmt = $pdo->prepare(
            "SELECT u.id, u.public_id, u.login, u.email, u.full_name, u.is_active,
                    u.external_invitation_expires_at, u.external_role, u.created_at,
                    c.id AS contact_id, c.full_name AS contact_name,
                    cp.id AS counterparty_id, cp.title AS counterparty_title
             FROM users u
             LEFT JOIN contacts c ON c.user_id = u.id
             LEFT JOIN counterparties cp ON cp.id = c.counterparty_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY u.created_at DESC"
        );
        $stmt->execute($params);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        foreach ($items as &$item) {
            $item['external_role'] = $this->normalizeRole($item['external_role'] ?? self::ROLE_OBSERVER);
        }
        unset($item);

        return [
            'items' => $items,
            'total' => count($items),
        ];
    }

    /**
     * The external user's role. Returns 'observer' for internal actors, users
     * not found, or an unrecognized/empty stored value (fail to the narrower,
     * pre-existing behaviour rather than accidentally widening access).
     */
    public function getExternalRole(int $userId): string
    {
        $user = $this->users->findById($userId);
        if (!$user || (int)($user['is_external'] ?? 0) !== 1) {
            return self::ROLE_OBSERVER;
        }
        return $this->normalizeRole($user['external_role'] ?? self::ROLE_OBSERVER);
    }

    /**
     * Internal project ids explicitly granted to an executor guest. Empty for
     * a non-executor or a user with no grants — callers must treat an empty
     * result as "no access" (fail closed), never as "unscoped".
     *
     * @return list<int>
     */
    public function getExecutorProjectIds(int $userId): array
    {
        if ($this->getExternalRole($userId) !== self::ROLE_EXECUTOR) {
            return [];
        }
        $stmt = $this->users->getPdo()->prepare(
            "SELECT p.id FROM external_user_project_access ea
             INNER JOIN projects p ON p.id = ea.project_id
             WHERE ea.user_id = :uid AND p.deleted_at IS NULL"
        );
        $stmt->execute([':uid' => $userId]);
        return array_map(static fn(array $r): int => (int)$r['id'], $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * Project public_ids explicitly granted to an executor guest. Used for
     * single-item access checks against an already-fetched project/task row
     * (which carries public_id, not the internal integer id).
     *
     * @return list<string>
     */
    public function getExecutorProjectPublicIds(int $userId): array
    {
        if ($this->getExternalRole($userId) !== self::ROLE_EXECUTOR) {
            return [];
        }
        $stmt = $this->users->getPdo()->prepare(
            "SELECT p.public_id FROM external_user_project_access ea
             INNER JOIN projects p ON p.id = ea.project_id
             WHERE ea.user_id = :uid AND p.deleted_at IS NULL"
        );
        $stmt->execute([':uid' => $userId]);
        return array_map(static fn(array $r): string => (string)$r['public_id'], $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []);
    }

    public function hasExecutorProjectAccess(int $userId, int $projectId): bool
    {
        if ($projectId <= 0 || $this->getExternalRole($userId) !== self::ROLE_EXECUTOR) {
            return false;
        }
        $stmt = $this->users->getPdo()->prepare(
            "SELECT 1 FROM external_user_project_access ea
             INNER JOIN projects p ON p.id = ea.project_id
             WHERE ea.user_id = :uid AND ea.project_id = :pid AND p.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([':uid' => $userId, ':pid' => $projectId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Grant an executor guest access to one additional project. Object-level
     * authorization: the granting actor must be able to see the project
     * through the ordinary internal RBAC/RLS rules (ProjectService::get()) —
     * project.manage alone is not enough to reach into a project the actor
     * has no visibility into. The target user must already be an active
     * executor guest; this endpoint never changes role or activates anyone.
     *
     * @return array{ok:bool, error?:string}
     */
    public function grantProjectAccess(string $userPublicId, string $projectPublicId, array $actor): array
    {
        $actorId = (int)($actor['id'] ?? 0);
        if ($actorId <= 0) {
            return ['ok' => false, 'error' => 'unauthorized'];
        }

        $target = $this->users->findByPublicId($userPublicId);
        if (!$target || (int)($target['is_external'] ?? 0) !== 1 || ($target['deleted_at'] ?? null) !== null) {
            return ['ok' => false, 'error' => 'user_not_found'];
        }
        if ($this->normalizeRole($target['external_role'] ?? self::ROLE_OBSERVER) !== self::ROLE_EXECUTOR) {
            return ['ok' => false, 'error' => 'not_executor'];
        }

        if (!$this->projectRepo) {
            return ['ok' => false, 'error' => 'service_unavailable'];
        }
        $project = $this->projectRepo->findByPublicId($projectPublicId);
        if (!$project || ($project['deleted_at'] ?? null) !== null || !$this->actorCanReachProject($project, $actor)) {
            return ['ok' => false, 'error' => 'project_not_found'];
        }

        $now = gmdate('Y-m-d H:i:s');
        $this->insertProjectAccessIgnoreDuplicate((int)$target['id'], (int)$project['id'], $actorId, $now);

        $this->logger->audit([
            'action' => 'external_user_project_access_granted',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'user',
            'entity_public_id' => $userPublicId,
            'project_public_id' => $projectPublicId,
        ]);

        return ['ok' => true];
    }

    /**
     * Revoke one project grant from an executor guest. Same object-level
     * check as grantProjectAccess() — the actor must be able to see the
     * project themselves.
     *
     * @return array{ok:bool, error?:string}
     */
    public function revokeProjectAccess(string $userPublicId, string $projectPublicId, array $actor): array
    {
        $actorId = (int)($actor['id'] ?? 0);
        if ($actorId <= 0) {
            return ['ok' => false, 'error' => 'unauthorized'];
        }

        $target = $this->users->findByPublicId($userPublicId);
        if (!$target || (int)($target['is_external'] ?? 0) !== 1) {
            return ['ok' => false, 'error' => 'user_not_found'];
        }

        if (!$this->projectRepo) {
            return ['ok' => false, 'error' => 'service_unavailable'];
        }
        $project = $this->projectRepo->findByPublicId($projectPublicId);
        if (!$project || !$this->actorCanReachProject($project, $actor)) {
            return ['ok' => false, 'error' => 'project_not_found'];
        }

        $stmt = $this->users->getPdo()->prepare(
            "DELETE FROM external_user_project_access WHERE user_id = :uid AND project_id = :pid"
        );
        $stmt->execute([':uid' => (int)$target['id'], ':pid' => (int)$project['id']]);

        $this->logger->audit([
            'action' => 'external_user_project_access_revoked',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'user',
            'entity_public_id' => $userPublicId,
            'project_public_id' => $projectPublicId,
        ]);

        return ['ok' => true];
    }

    /**
     * List an executor's granted projects (public_id + title) for display in
     * the admin UI. Object-level protected the same way listExternalUsers()
     * and deactivate() are: the actor must be able to reach the target
     * guest's linked contact through their own hierarchy.
     *
     * @return array{ok:bool, items?:list<array{public_id:string,title:string}>, error?:string}
     */
    public function listProjectAccess(string $userPublicId, array $actor): array
    {
        $actorId = (int)($actor['id'] ?? 0);
        if ($actorId <= 0) {
            return ['ok' => false, 'error' => 'unauthorized'];
        }

        $target = $this->users->findByPublicId($userPublicId);
        if (!$target || (int)($target['is_external'] ?? 0) !== 1) {
            return ['ok' => false, 'error' => 'user_not_found'];
        }

        if (!(bool)($actor['is_root'] ?? false)) {
            $contact = $this->contacts->findByUserId((int)$target['id']);
            if (!$contact || !$this->contactService->get((string)($contact['public_id'] ?? ''), $actor)) {
                return ['ok' => false, 'error' => 'user_not_found'];
            }
        }

        $stmt = $this->users->getPdo()->prepare(
            "SELECT p.public_id, p.title FROM external_user_project_access ea
             INNER JOIN projects p ON p.id = ea.project_id
             WHERE ea.user_id = :uid AND p.deleted_at IS NULL
             ORDER BY p.title ASC"
        );
        $stmt->execute([':uid' => (int)$target['id']]);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return ['ok' => true, 'items' => $items];
    }
}
