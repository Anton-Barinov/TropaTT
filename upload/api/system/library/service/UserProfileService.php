<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Security\PasswordResetRepository;
use Api\Model\Security\SessionRepository;
use Api\Model\User\UserManagementRepository;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Security\PasswordHasher;

final class UserProfileService
{
    /** Avatars are capped at 2 MiB and limited to raster formats (no SVG). */
    private const AVATAR_MAX_BYTES = 2_097_152;
    private const AVATAR_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly UserManagementRepository $users,
        private readonly SessionRepository $sessions,
        private readonly SettingService $settings,
        private readonly PasswordHasher $hasher,
        private readonly JsonLogger $logger,
        private readonly ?PasswordResetRepository $passwordResets = null,
        private readonly ?string $avatarDir = null,
    ) {
    }

    /**
     * Store a validated avatar image for the actor.
     *
     * Returns ['ok' => true, 'user' => …] or ['ok' => false, 'code' => …].
     * Only raster images are accepted (getimagesize + a MIME allowlist), which
     * excludes SVG and any polyglot/executable payload; the file is written
     * outside the web root under an application-generated name.
     */
    public function setAvatar(array $actor, ?array $file): array
    {
        $user = $this->users->findById((int)($actor['id'] ?? 0));
        if (!$user) {
            return ['ok' => false, 'code' => 'USER_NOT_FOUND'];
        }

        $dir = $this->resolveAvatarDir();
        if ($dir === null) {
            return ['ok' => false, 'code' => 'AVATAR_STORAGE_UNAVAILABLE'];
        }

        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'code' => 'AVATAR_REQUIRED'];
        }
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0) {
            return ['ok' => false, 'code' => 'AVATAR_REQUIRED'];
        }
        if ($size > self::AVATAR_MAX_BYTES) {
            return ['ok' => false, 'code' => 'AVATAR_TOO_LARGE'];
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'code' => 'AVATAR_REQUIRED'];
        }

        $info = @getimagesize($tmp);
        $mime = is_array($info) ? (string)($info['mime'] ?? '') : '';
        if (!isset(self::AVATAR_TYPES[$mime])) {
            return ['ok' => false, 'code' => 'AVATAR_INVALID_TYPE'];
        }

        $publicId = (string)$user['public_id'];
        $target = $dir . '/' . $publicId . '.' . self::AVATAR_TYPES[$mime];
        if (!move_uploaded_file($tmp, $target)) {
            return ['ok' => false, 'code' => 'AVATAR_STORE_FAILED'];
        }
        @chmod($target, 0640);

        // Drop a stale file with a different extension so the served format
        // cannot disagree with the database row.
        foreach (self::AVATAR_TYPES as $ext) {
            $stale = $dir . '/' . $publicId . '.' . $ext;
            if ($stale !== $target && is_file($stale)) {
                @unlink($stale);
            }
        }

        $now = gmdate('Y-m-d H:i:s');
        $this->users->updateByPublicId($publicId, [
            'avatar_path' => $target,
            'avatar_mime' => $mime,
            'avatar_updated_at' => $now,
            'updated_at' => $now,
        ]);
        $this->logger->audit([
            'action' => 'profile_avatar_updated',
            'actor_public_id' => $publicId,
            'entity_type' => 'user',
            'entity_public_id' => $publicId,
            'details' => ['mime_type' => $mime, 'size_bytes' => $size],
        ]);

        $updated = $this->users->findById((int)$user['id']);
        return ['ok' => true, 'user' => $this->sanitizeUser($this->withAvatarInfo($updated ?? $user))];
    }

    public function clearAvatar(array $actor): array
    {
        $user = $this->users->findById((int)($actor['id'] ?? 0));
        if (!$user) {
            return ['ok' => false, 'code' => 'USER_NOT_FOUND'];
        }

        $publicId = (string)$user['public_id'];
        $path = (string)($user['avatar_path'] ?? '');
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }

        $now = gmdate('Y-m-d H:i:s');
        $this->users->updateByPublicId($publicId, [
            'avatar_path' => null,
            'avatar_mime' => null,
            'avatar_updated_at' => null,
            'updated_at' => $now,
        ]);
        $this->logger->audit([
            'action' => 'profile_avatar_removed',
            'actor_public_id' => $publicId,
            'entity_type' => 'user',
            'entity_public_id' => $publicId,
        ]);

        $updated = $this->users->findById((int)$user['id']);
        return ['ok' => true, 'user' => $this->sanitizeUser($this->withAvatarInfo($updated ?? $user))];
    }

    /**
     * Avatar file info for serving. Any authenticated user may read another
     * user's avatar (they are not sensitive), so this only checks existence.
     *
     * @return array{path:string,mime:string,updated_at:?string}|null
     */
    public function avatarForPublicId(string $publicId): ?array
    {
        $publicId = trim($publicId);
        if ($publicId === '') {
            return null;
        }
        $info = $this->users->avatarInfoByPublicId($publicId);
        $path = (string)($info['avatar_path'] ?? '');
        if ($path === '' || !is_file($path)) {
            return null;
        }

        return [
            'path' => $path,
            'mime' => (string)($info['avatar_mime'] ?? 'image/png'),
            'updated_at' => $info['avatar_updated_at'] ?? null,
        ];
    }

    /**
     * Merge avatar columns into a user row loaded by the generic finder.
     *
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    private function withAvatarInfo(array $user): array
    {
        $info = $this->users->avatarInfoById((int)($user['id'] ?? 0));
        return $info === [] ? $user : array_merge($user, $info);
    }

    public function me(array $actor): ?array
    {
        $user = $this->users->findById((int)($actor['id'] ?? 0));
        if (!$user) {
            return null;
        }

        return $this->sanitizeUser($this->withAvatarInfo($user));
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

        return ['ok' => true, 'user' => $this->sanitizeUser($this->withAvatarInfo($updated))];
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
        $now = gmdate('Y-m-d H:i:s');
        $revokedCount = $this->sessions->revokeAllByUserId(
            (int)$user['id'],
            $now,
            $currentSessionPublicId
        );

        // H-2 fix: invalidate all pending password-reset tokens so an attacker
        // with access to a stale email link cannot use it after the user changed
        // their password.
        if ($this->passwordResets !== null) {
            $this->passwordResets->revokeAllByUserId((int)$user['id'], $now);
        }

        $this->logger->security([
            'event_type' => 'profile_change_password_success',
            'actor_public_id' => $user['public_id'] ?? null,
            'revoked_sessions' => $revokedCount,
        ]);

        return ['ok' => true];
    }

    private function sanitizeUser(array $user): array
    {
        $unsafe = ['password_hash', 'auth_token_hash', 'deleted_at', 'id', 'created_by_user_id',
            'cost_rate', 'bill_rate', 'payout_rate'];
        foreach ($unsafe as $key) {
            unset($user[$key]);
        }

        // Never expose the absolute storage path; publish a cache-busted
        // authenticated URL instead when an avatar exists.
        $avatarPath = (string)($user['avatar_path'] ?? '');
        unset($user['avatar_path'], $user['avatar_mime']);
        if ($avatarPath !== '' && !empty($user['public_id'])) {
            $stamp = (string)($user['avatar_updated_at'] ?? '');
            $user['avatar_url'] = '/api/index.php?route=api/v1/users/' . rawurlencode((string)$user['public_id'])
                . '/avatar' . ($stamp !== '' ? '&v=' . rawurlencode($stamp) : '');
        } else {
            $user['avatar_url'] = '';
        }

        return $user;
    }

    private function resolveAvatarDir(): ?string
    {
        $dir = trim((string)($this->avatarDir ?? ''));
        if ($dir === '') {
            return null;
        }
        $dir = rtrim($dir, '/');
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            return null;
        }
        @chmod($dir, 0750);

        // SEC-001: no directory listing outside the web root, but harmless here.
        $indexFile = $dir . '/index.html';
        if (!is_file($indexFile)) {
            @file_put_contents($indexFile, '');
        }

        return $dir;
    }
}
