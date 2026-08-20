<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Auth\AuthRepository;
use Api\Model\Common\UserRepository;
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
 * They can only access projects/tasks/chats associated with their counterparty.
 */
final class ExternalUserService
{
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
    ) {
    }

    /**
     * Invite by contact public_id (resolves to internal id).
     */
    public function inviteByPublicId(string $contactPublicId, array $actor): array
    {
        // Resolve through ContactService, not the repository, so the same
        // hierarchy/object-level access policy as the contacts UI is enforced.
        // A caller must not be able to invite a contact from another user's
        // scope by guessing its public_id.
        $contact = $this->contactService->get($contactPublicId, $actor);
        if (!$contact) {
            return ['ok' => false, 'error' => 'contact_not_found'];
        }
        return $this->invite((int)$contact['id'], $actor);
    }

    /**
     * Invite an external user from a contact record.
     *
     * Creates a pending user account and returns an invitation token
     * that can be sent via email.
     *
     * @return array{ok:bool, token?:string, user_public_id?:string, error?:string}
     */
    public function invite(int $contactId, array $actor): array
    {
        $userId = (int)($actor['id'] ?? 0);
        if ($userId <= 0) {
            return ['ok' => false, 'error' => 'unauthorized'];
        }

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

            if ($existingUser) {
            $createdUserId = (int)$existingUser['id'];
            $userPublicId = (string)$existingUser['public_id'];
            $login = (string)$existingUser['login'];
            // A previously active session must not be resurrected when a
            // revoked account is invited again and activated later.
            $this->auth->revokeAllByUserId($createdUserId, $now);
            $this->users->updateById($createdUserId, [
                'email' => $email,
                'full_name' => $fullName,
                'password_hash' => $this->hasher->hash('__pending__'),
                'auth_token_hash' => $tokenHash,
                'external_invitation_expires_at' => $expiresAt,
                'is_active' => 0,
                'updated_at' => $now,
            ]);
            } else {
                $userPublicId = Ulid::generate('usr');
            $login = 'ext_' . substr($userPublicId, 4, 12);
            $createdUserId = $this->users->create([
                'public_id' => $userPublicId,
                'login' => $login,
                'email' => $email,
                'password_hash' => $this->hasher->hash('__pending__'),
                'auth_token_hash' => $tokenHash,
                'external_invitation_expires_at' => $expiresAt,
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
        ]);

        return [
            'ok' => true,
            'token' => $token,
            'user_public_id' => $userPublicId,
            'login' => $login,
            'email' => $email,
            'invitation_expires_at' => $expiresAt,
        ];
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
                    u.external_invitation_expires_at, u.created_at,
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

        return [
            'items' => $items,
            'total' => count($items),
        ];
    }
}
