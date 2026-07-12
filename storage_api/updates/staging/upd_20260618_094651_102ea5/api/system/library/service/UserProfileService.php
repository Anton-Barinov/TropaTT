<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Security\SessionRepository;
use Api\Model\User\UserManagementRepository;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Security\PasswordHasher;

final class UserProfileService
{
    public function __construct(
        private readonly UserManagementRepository $users,
        private readonly SessionRepository $sessions,
        private readonly SettingService $settings,
        private readonly PasswordHasher $hasher,
        private readonly JsonLogger $logger
    ) {
    }

    public function me(array $actor): ?array
    {
        $user = $this->users->findById((int)($actor['id'] ?? 0));
        if (!$user) {
            return null;
        }

        return $this->sanitizeUser($user);
    }

    public function updateMe(array $actor, array $input): array
    {
        $user = $this->users->findById((int)($actor['id'] ?? 0));
        if (!$user) {
            return ['ok' => false, 'code' => 'USER_NOT_FOUND'];
        }

        if (array_key_exists('email', $input)) {
            $nextEmail = trim((string)$input['email']);
            $currentEmail = trim((string)($user['email'] ?? ''));
            if ($nextEmail !== '' && mb_strtolower($nextEmail) !== mb_strtolower($currentEmail)) {
                return ['ok' => false, 'code' => 'EMAIL_CHANGE_REQUIRES_VERIFICATION'];
            }
        }

        $set = [];
        foreach (['full_name', 'locale'] as $field) {
            if (array_key_exists($field, $input)) {
                $set[$field] = trim((string)$input[$field]);
            }
        }
        if ($set !== []) {
            $set['updated_at'] = gmdate('Y-m-d H:i:s');
            $this->users->updateByPublicId((string)$user['public_id'], $set);
        }

        $timezone = trim((string)($input['timezone'] ?? ''));
        if ($timezone !== '') {
            $this->settings->set('user:' . (string)$user['public_id'], 'timezone', $timezone);
        }

        $this->logger->audit([
            'action' => 'profile_updated',
            'actor_public_id' => $user['public_id'] ?? null,
            'entity_type' => 'user',
            'entity_public_id' => $user['public_id'] ?? null,
        ]);

        $updated = $this->users->findById((int)$user['id']);

        if (!$updated) {
            return ['ok' => false, 'code' => 'USER_NOT_FOUND'];
        }

        return ['ok' => true, 'user' => $this->sanitizeUser($updated)];
    }

    public function preferences(array $actor): array
    {
        $scope = 'user:' . (string)($actor['public_id'] ?? '');
        $list = $this->settings->list([
            'scope' => $scope,
            'page' => 1,
            'limit' => 200,
        ]);

        $preferences = [];
        foreach ((array)($list['items'] ?? []) as $item) {
            $name = (string)($item['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $preferences[$name] = $item['value'] ?? null;
        }

        return $preferences;
    }

    public function setPreferences(array $actor, array $preferences): array
    {
        $scope = 'user:' . (string)($actor['public_id'] ?? '');
        $updated = [];
        foreach ($preferences as $name => $value) {
            $key = trim((string)$name);
            if ($key === '') {
                continue;
            }
            $item = $this->settings->set($scope, $key, $value);
            $updated[$key] = $item['value'] ?? null;
        }

        $this->logger->audit([
            'action' => 'profile_preferences_updated',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'user',
            'entity_public_id' => $actor['public_id'] ?? null,
        ]);

        return $updated;
    }

    public function changePassword(array $actor, string $currentPassword, string $newPassword, ?string $currentSessionPublicId = null): array
    {
        $user = $this->users->findById((int)($actor['id'] ?? 0));
        if (!$user) {
            return ['ok' => false, 'code' => 'USER_NOT_FOUND'];
        }

        if (!$this->hasher->verify($currentPassword, (string)($user['password_hash'] ?? ''))) {
            $this->logger->security([
                'event_type' => 'profile_change_password_failed',
                'actor_public_id' => $user['public_id'] ?? null,
                'reason' => 'invalid_current_password',
            ]);

            return ['ok' => false, 'code' => 'INVALID_CURRENT_PASSWORD'];
        }

        $this->users->updateByPublicId((string)$user['public_id'], [
            'password_hash' => $this->hasher->hash($newPassword),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $revokedCount = $this->sessions->revokeAllByUserId(
            (int)$user['id'],
            gmdate('Y-m-d H:i:s'),
            $currentSessionPublicId
        );

        $this->logger->security([
            'event_type' => 'profile_change_password_success',
            'actor_public_id' => $user['public_id'] ?? null,
            'revoked_sessions' => $revokedCount,
        ]);

        return ['ok' => true];
    }

    private function sanitizeUser(array $user): array
    {
        $unsafe = ['password_hash', 'auth_token_hash', 'deleted_at', 'id', 'created_by_user_id'];
        foreach ($unsafe as $key) {
            unset($user[$key]);
        }

        return $user;
    }
}
