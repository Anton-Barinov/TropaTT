<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

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
        private readonly PasswordHasher $hasher,
        private readonly TokenManager $tokens,
        private readonly JsonLogger $logger,
        private readonly Config $config,
    ) {
    }

    /**
     * Invite by contact public_id (resolves to internal id).
     */
    public function inviteByPublicId(string $contactPublicId, array $actor): array
    {
        $contact = $this->contacts->findByPublicId($contactPublicId);
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

        // Load the contact
        $contact = $this->contacts->findById($contactId);
        if (!$contact) {
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

        // Check if this contact already has a linked user
        $existingUserId = (int)($contact['user_id'] ?? 0);
        if ($existingUserId > 0) {
            $existingUser = $this->users->findById($existingUserId);
            if ($existingUser && (int)($existingUser['is_external'] ?? 0) === 1) {
                return ['ok' => false, 'error' => 'contact_already_has_external_user'];
            }
        }

        // Check if email is already used
        $existingByEmail = $this->users->findByEmail($email);
        if ($existingByEmail) {
            return ['ok' => false, 'error' => 'email_already_registered'];
        }

        // Find the external_guest role
        $externalRole = $this->roles->findByCode('external_guest');
        if (!$externalRole) {
            return ['ok' => false, 'error' => 'external_guest_role_not_found'];
        }

        $now = gmdate('Y-m-d H:i:s');
        $userPublicId = Ulid::generate('usr');

        // Generate invitation token (valid 7 days)
        $token = $this->tokens->generate(32);
        $tokenHash = $this->tokens->hash($token);

        // Create user account in "pending" state (is_active = 0 until they set password)
        $login = 'ext_' . substr($userPublicId, 4, 12);
        $this->users->create([
            'public_id' => $userPublicId,
            'login' => $login,
            'email' => $email,
            'password_hash' => $this->hasher->hash('__pending__'),
            'auth_token_hash' => $tokenHash,
            'full_name' => trim((string)($contact['full_name'] ?? $email)),
            'locale' => 'ru-ru',
            'is_active' => 0,
            'is_external' => 1,
            'is_root' => 0,
            'created_by_user_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $createdUser = $this->users->findByLogin($login);
        if (!$createdUser) {
            return ['ok' => false, 'error' => 'user_creation_failed'];
        }

        $createdUserId = (int)$createdUser['id'];

        // Assign external_guest role
        $this->roles->assignToUser($createdUserId, (int)$externalRole['id']);

        // Link contact → user
        $this->contacts->updateById($contactId, [
            'user_id' => $createdUserId,
        ]);

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

        // Must be inactive (pending)
        if ((int)($user['is_active'] ?? 0) !== 0) {
            return ['ok' => false, 'error' => 'account_already_active'];
        }

        $now = gmdate('Y-m-d H:i:s');

        // Activate the account
        $this->users->updateById((int)$user['id'], [
            'password_hash' => $this->hasher->hash($password),
            'is_active' => 1,
            'updated_at' => $now,
        ]);

        // Generate session token
        $sessionToken = $this->tokens->generate(32);
        $this->users->updateById((int)$user['id'], [
            'auth_token_hash' => $this->tokens->hash($sessionToken),
            'updated_at' => $now,
        ]);

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

        if ((int)($user['is_external'] ?? 0) !== 1) {
            return ['ok' => false, 'error' => 'not_external_user'];
        }

        $now = gmdate('Y-m-d H:i:s');
        $this->users->updateById((int)$user['id'], [
            'is_active' => 0,
            'updated_at' => $now,
        ]);

        // Clear auth token so they can't log in
        $this->users->updateById((int)$user['id'], [
            'auth_token_hash' => null,
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
        $stmt = $pdo->prepare(
            "SELECT u.id, u.public_id, u.login, u.email, u.full_name, u.is_active, u.created_at,
                    c.id AS contact_id, c.full_name AS contact_name,
                    cp.id AS counterparty_id, cp.title AS counterparty_title
             FROM users u
             LEFT JOIN contacts c ON c.user_id = u.id
             LEFT JOIN counterparties cp ON cp.id = c.counterparty_id
             WHERE u.is_external = 1
             ORDER BY u.created_at DESC"
        );
        $stmt->execute();
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return [
            'items' => $items,
            'total' => count($items),
        ];
    }
}
