<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Common\UserRepository;
use Api\Model\Role\RoleRepository;
use Api\Model\Security\InvitationRepository;
use Api\Model\User\UserManagementRepository;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Security\PasswordHasher;
use Api\System\Library\Security\RateLimiterInterface;
use Api\System\Library\Security\TokenManager;
use Api\System\Library\Support\Ulid;

final class InvitationService
{
    public function __construct(
        private readonly InvitationRepository $invitations,
        private readonly UserRepository $users,
        private readonly UserManagementRepository $userManagement,
        private readonly RoleRepository $roles,
        private readonly PasswordHasher $hasher,
        private readonly TokenManager $tokens,
        private readonly JsonLogger $logger,
        private readonly RateLimiterInterface $rateLimiter
    ) {
    }

    public function list(array $filters, array $actor): array
    {
        [$items, $total, $page, $limit] = $this->invitations->list(
            $filters,
            (int)($actor['id'] ?? 0),
            (bool)($actor['is_root'] ?? false)
        );

        return [
            'items' => array_map([$this, 'normalizeInvitation'], $items),
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

    public function create(array $input, array $actor): array
    {
        $email = mb_strtolower(trim((string)$input['email']));
        if ($this->users->findByEmail($email)) {
            return ['ok' => false, 'code' => 'INVITATION_EMAIL_EXISTS'];
        }

        $plainToken = $this->tokens->generate(24);
        $publicId = Ulid::generate('inv');
        $expiresAt = gmdate('Y-m-d H:i:s', time() + (86400 * max(1, min(30, (int)($input['expires_in_days'] ?? 7)))));

        $this->invitations->create([
            'public_id' => $publicId,
            'email' => $email,
            'invited_by_user_id' => (int)($actor['id'] ?? 0),
            'token_hash' => $this->tokens->hash($plainToken),
            'expires_at' => $expiresAt,
            'accepted_at' => null,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->logger->audit([
            'action' => 'invitation_create',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'invitation',
            'entity_public_id' => $publicId,
            'email' => $email,
        ]);

        $item = $this->invitations->findByPublicId($publicId);

        return [
            'ok' => true,
            'invitation' => $item ? $this->normalizeInvitation($item) : ['public_id' => $publicId],
            'accept_token' => $plainToken,
        ];
    }

    public function get(string $publicId, array $actor): ?array
    {
        $item = $this->invitations->findByPublicId($publicId);
        if (!$item || !$this->canAccess($item, $actor)) {
            return null;
        }

        return $this->normalizeInvitation($item);
    }

    public function accept(array $input, string $ip = ''): array
    {
        // Rate limit by IP to prevent token brute-force flooding (Task 1.6)
        if ($ip !== '') {
            $rateKey = hash('sha256', 'invitation-accept-ip:' . $ip);
            $check = $this->rateLimiter->check($rateKey);
            if ($check['blocked'] === true) {
                return ['ok' => false, 'code' => 'INVITATION_RATE_LIMITED', 'retry_after' => $check['retry_after']];
            }
            $this->rateLimiter->hit($rateKey);
        }

        $token = trim((string)($input['invitation_token'] ?? ''));
        $invitation = $this->invitations->findActiveByTokenHash($this->tokens->hash($token));
        if (!$invitation) {
            return ['ok' => false, 'code' => 'INVITATION_NOT_FOUND'];
        }

        $expiresAt = strtotime(((string)($invitation['expires_at'] ?? '')) . ' UTC');
        if ($expiresAt !== false && $expiresAt < time()) {
            return ['ok' => false, 'code' => 'INVITATION_EXPIRED'];
        }

        $login = trim((string)($input['login'] ?? ''));
        if ($this->users->findByLogin($login)) {
            return ['ok' => false, 'code' => 'USER_LOGIN_EXISTS'];
        }

        $email = mb_strtolower(trim((string)($invitation['email'] ?? '')));
        if ($this->users->findByEmail($email)) {
            return ['ok' => false, 'code' => 'USER_EMAIL_EXISTS'];
        }

        $authFactor = trim((string)($input['token'] ?? ''));
        if ($authFactor === '') {
            $authFactor = $this->tokens->generate(16);
        }

        $userPublicId = Ulid::generate('usr');
        $now = gmdate('Y-m-d H:i:s');
        $userId = $this->userManagement->create([
            'public_id' => $userPublicId,
            'login' => $login,
            'email' => $email,
            'password_hash' => $this->hasher->hash((string)$input['password']),
            'auth_token_hash' => $this->tokens->hash($authFactor),
            'full_name' => trim((string)($input['full_name'] ?? '')),
            'locale' => (string)($input['locale'] ?? 'en-gb'),
            'is_active' => 1,
            'is_root' => 0,
            'created_by_user_id' => (int)($invitation['invited_by_user_id'] ?? 0) ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $defaultRole = $this->roles->findByCode('user');
        if ($defaultRole) {
            $this->userManagement->replaceRoles($userId, [(string)$defaultRole['public_id']]);
        }

        $this->invitations->markAccepted((string)$invitation['public_id'], $now);

        $this->logger->audit([
            'action' => 'invitation_accept',
            'entity_type' => 'invitation',
            'entity_public_id' => (string)$invitation['public_id'],
            'created_user_public_id' => $userPublicId,
        ]);

        $user = $this->userManagement->findById($userId);

        return [
            'ok' => true,
            'invitation' => $this->normalizeInvitation($this->invitations->findByPublicId((string)$invitation['public_id']) ?: $invitation),
            'user' => [
                'public_id' => (string)($user['public_id'] ?? $userPublicId),
                'login' => (string)($user['login'] ?? $login),
                'email' => (string)($user['email'] ?? $email),
                'full_name' => (string)($user['full_name'] ?? ''),
                'locale' => (string)($user['locale'] ?? 'en-gb'),
            ],
            'user_token' => $authFactor,
        ];
    }

    private function canAccess(array $invitation, array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        return (int)($invitation['invited_by_user_id'] ?? 0) === (int)($actor['id'] ?? 0);
    }

    /** @param array<string,mixed> $invitation */
    private function normalizeInvitation(array $invitation): array
    {
        return [
            'public_id' => (string)($invitation['public_id'] ?? ''),
            'email' => (string)($invitation['email'] ?? ''),
            'invited_by' => [
                'public_id' => (string)($invitation['invited_by_public_id'] ?? ''),
                'login' => (string)($invitation['invited_by_login'] ?? ''),
                'full_name' => (string)($invitation['invited_by_full_name'] ?? ''),
            ],
            'expires_at' => (string)($invitation['expires_at'] ?? ''),
            'accepted_at' => (string)($invitation['accepted_at'] ?? ''),
            'created_at' => (string)($invitation['created_at'] ?? ''),
        ];
    }
}
