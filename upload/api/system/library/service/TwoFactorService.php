<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Common\UserRepository;
use Api\Model\Security\TwoFactorRepository;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Security\PasswordHasher;
use Api\System\Library\Security\TokenManager;
use Api\System\Library\Support\Ulid;

final class TwoFactorService
{
    private const TOTP_TIME_STEP = 30;
    private const TOTP_DIGITS = 6;
    private const TOTP_WINDOW = 1; // +/- windows to check for clock skew
    private const CIPHER = 'aes-256-gcm';
    private const CIPHER_TAG_LENGTH = 16;

    public function __construct(
        private readonly TwoFactorRepository $twoFactor,
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly TokenManager $tokens,
        private readonly JsonLogger $logger,
        private readonly string $encryptionKey = ''
    ) {
    }

    public function status(array $actor): array
    {
        $userId = (int)($actor['id'] ?? 0);
        $record = $this->twoFactor->findByUserId($userId);

        return [
            'enabled' => $record !== null,
            'two_factor' => $record ? $this->normalize($record) : [
                'enabled' => false,
                'public_id' => '',
                'created_at' => '',
                'updated_at' => '',
                'backup_codes_remaining' => 0,
            ],
        ];
    }

    public function enable(array $actor, string $currentPassword): array
    {
        $user = $this->users->findById((int)($actor['id'] ?? 0));
        if (!$user || (int)($user['is_active'] ?? 0) !== 1) {
            return ['ok' => false, 'code' => 'USER_NOT_FOUND'];
        }

        if (!$this->hasher->verify($currentPassword, (string)($user['password_hash'] ?? ''))) {
            return ['ok' => false, 'code' => 'INVALID_CURRENT_PASSWORD'];
        }

        if ($this->twoFactor->findByUserId((int)$user['id'])) {
            return ['ok' => false, 'code' => 'TWO_FACTOR_ALREADY_ENABLED'];
        }

        // A second factor must never be persisted in plaintext. Shared hosting
        // still supports this through APP_KEY; if it is absent, fail safely.
        if ($this->getEncryptionKey() === '' || !function_exists('openssl_encrypt')) {
            return ['ok' => false, 'code' => 'TWO_FACTOR_ENCRYPTION_UNAVAILABLE'];
        }

        // Generate a TOTP-compatible secret (160-bit random, base32 encoded)
        $secret = $this->generateTotpSecret();
        $encryptedSecret = $this->encryptSecret($secret);
        if ($encryptedSecret === '') {
            return ['ok' => false, 'code' => 'TWO_FACTOR_ENCRYPTION_UNAVAILABLE'];
        }
        $backupCodesPlain = $this->generateBackupCodes(8);
        $backupCodesHashed = array_map([$this->hasher, 'hash'], $backupCodesPlain);
        $now = gmdate('Y-m-d H:i:s');
        $publicId = Ulid::generate('tfa');

        $this->twoFactor->createOrReplace([
            'public_id' => $publicId,
            'user_id' => (int)$user['id'],
            'secret_hash' => $encryptedSecret,
            'backup_codes' => json_encode($backupCodesHashed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $record = $this->twoFactor->findByUserId((int)$user['id']);
        $this->logger->security([
            'event_type' => 'two_factor_enabled',
            'actor_public_id' => $user['public_id'] ?? null,
            'two_factor_public_id' => $publicId,
        ]);

        return [
            'ok' => true,
            'two_factor' => $record ? $this->normalize($record) : ['enabled' => true, 'public_id' => $publicId],
            'setup_secret' => $secret,
            'backup_codes' => $backupCodesPlain,
        ];
    }

    public function disable(array $actor, string $currentPassword): array
    {
        $user = $this->users->findById((int)($actor['id'] ?? 0));
        if (!$user || (int)($user['is_active'] ?? 0) !== 1) {
            return ['ok' => false, 'code' => 'USER_NOT_FOUND'];
        }

        if (!$this->hasher->verify($currentPassword, (string)($user['password_hash'] ?? ''))) {
            return ['ok' => false, 'code' => 'INVALID_CURRENT_PASSWORD'];
        }

        $record = $this->twoFactor->findByUserId((int)$user['id']);
        if (!$record) {
            return ['ok' => false, 'code' => 'TWO_FACTOR_NOT_ENABLED'];
        }

        $ok = $this->twoFactor->deleteByUserId((int)$user['id']);
        if ($ok) {
            $this->logger->security([
                'event_type' => 'two_factor_disabled',
                'actor_public_id' => $user['public_id'] ?? null,
                'two_factor_public_id' => (string)($record['public_id'] ?? ''),
            ]);
        }

        return ['ok' => $ok];
    }

    /**
     * Check if 2FA is required for a user during login.
     * Returns array with 'required' flag and session identifier if 2FA step is needed.
     */
    public function requiresTwoFactor(int $userId): bool
    {
        return $this->twoFactor->findByUserId($userId) !== null;
    }

    /**
     * Verify a TOTP code against the user's 2FA secret.
     */
    public function verifyTotp(array $actor, string $code): array
    {
        $userId = (int)($actor['id'] ?? 0);
        $record = $this->twoFactor->findByUserId($userId);
        if (!$record) {
            return ['ok' => false, 'code' => 'TWO_FACTOR_NOT_ENABLED'];
        }

        $encryptedSecret = (string)($record['secret_hash'] ?? '');
        $secret = $this->decryptSecret($encryptedSecret);
        if ($secret === '') {
            return ['ok' => false, 'code' => 'TWO_FACTOR_SECRET_INVALID'];
        }

        $userCode = trim($code);
        $counter = (int)floor(time() / self::TOTP_TIME_STEP);
        $lastStep = (int)($record['last_totp_step'] ?? 0);

        // Check current window and +/- windows for clock skew
        for ($i = -self::TOTP_WINDOW; $i <= self::TOTP_WINDOW; $i++) {
            $step = $counter + $i;
            $expected = $this->generateTotpCode($secret, $step);
            if (hash_equals($expected, $userCode)) {
                // M-4: Prevent TOTP code replay within validity window.
                if ($step <= $lastStep) {
                    $this->logger->security([
                        'event_type' => 'two_factor_replay_rejected',
                        'actor_public_id' => $actor['public_id'] ?? null,
                        'step' => $step,
                        'last_step' => $lastStep,
                    ]);

                    return ['ok' => false, 'code' => 'TWO_FACTOR_ALREADY_USED'];
                }

                // Record the used step.
                $this->twoFactor->updateLastTotpStep($userId, $step);

                $this->logger->security([
                    'event_type' => 'two_factor_verified',
                    'actor_public_id' => $actor['public_id'] ?? null,
                ]);

                return ['ok' => true];
            }
        }

        return ['ok' => false, 'code' => 'TWO_FACTOR_INVALID_CODE'];
    }

    /**
     * Verify a backup code and consume it if valid.
     */
    public function verifyBackupCode(array $actor, string $code): array
    {
        $userId = (int)($actor['id'] ?? 0);
        $record = $this->twoFactor->findByUserId($userId);
        if (!$record) {
            return ['ok' => false, 'code' => 'TWO_FACTOR_NOT_ENABLED'];
        }

        $backupCodes = json_decode((string)($record['backup_codes'] ?? '[]'), true);
        if (!is_array($backupCodes) || $backupCodes === []) {
            return ['ok' => false, 'code' => 'TWO_FACTOR_NO_BACKUP_CODES'];
        }

        $userCode = trim(strtoupper($code));
        $remaining = [];
        $found = false;

        foreach ($backupCodes as $hashedCode) {
            if (!is_string($hashedCode)) {
                continue;
            }

            if (!$found && $this->hasher->verify($userCode, $hashedCode)) {
                $found = true;
                continue;
            }

            $remaining[] = $hashedCode;
        }

        if (!$found) {
            return ['ok' => false, 'code' => 'TWO_FACTOR_INVALID_BACKUP_CODE'];
        }

        // Update backup codes list — remove used one
        $now = gmdate('Y-m-d H:i:s');
        $this->twoFactor->updateBackupCodes((int)($actor['id'] ?? 0), json_encode($remaining, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $now);

        $this->logger->security([
            'event_type' => 'two_factor_backup_code_used',
            'actor_public_id' => $actor['public_id'] ?? null,
            'remaining_codes' => count($remaining),
        ]);

        return ['ok' => true, 'remaining_codes' => count($remaining)];
    }

    /**
     * Check if 2FA is enabled for a user.
     */
    public function isEnabledForUser(int $userId): bool
    {
        return $this->twoFactor->findByUserId($userId) !== null;
    }

    /**
     * M-5: Check whether a login_token nonce has already been consumed.
     * Returns true if the nonce was previously used.
     */
    public function isLoginNonceConsumed(int $userId, string $nonce): bool
    {
        $stored = $this->twoFactor->findLoginNonceHash($userId);
        if ($stored === null || $stored === '') {
            return false;
        }

        return hash_equals($stored, hash_hmac('sha256', $nonce, $this->encryptionKey ?: '2fa'));
    }

    /**
     * M-5: Mark a login_token nonce as consumed so it cannot be reused.
     */
    public function consumeLoginNonce(int $userId, string $nonce): void
    {
        $nonceHash = hash_hmac('sha256', $nonce, $this->encryptionKey ?: '2fa');
        $this->twoFactor->consumeLoginNonce($userId, $nonceHash);
    }

    private function normalize(array $record): array
    {
        $codes = json_decode((string)($record['backup_codes'] ?? '[]'), true);
        $backupCodes = is_array($codes) ? $codes : [];

        return [
            'enabled' => true,
            'public_id' => (string)($record['public_id'] ?? ''),
            'created_at' => (string)($record['created_at'] ?? ''),
            'updated_at' => (string)($record['updated_at'] ?? ''),
            'backup_codes_remaining' => count($backupCodes),
        ];
    }

    /** @return array<int,string> */
    private function generateBackupCodes(int $count): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            // 5 bytes = 40 bits = 10 hex chars (up from 4/32/8).
            $codes[] = strtoupper(bin2hex(random_bytes(5)));
        }

        return $codes;
    }

    /**
     * Generate a TOTP-compatible secret (base32 encoded, 160-bit).
     */
    private function generateTotpSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    /**
     * Generate a TOTP code for a given secret and counter.
     * RFC 6238 / RFC 4226 compliant.
     */
    private function generateTotpCode(string $secret, int $counter): string
    {
        $decoded = $this->base32Decode($secret);
        if ($decoded === '') {
            return '';
        }

        // Pack counter as 8-byte big-endian
        $msg = pack('J', $counter);

        // HMAC-SHA1
        $hash = hash_hmac('sha1', $msg, $decoded, true);

        // Dynamic truncation (RFC 4226 section 5.3)
        $offset = ord($hash[19]) & 0x0f;
        $binary = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        );

        $otp = $binary % (10 ** self::TOTP_DIGITS);

        return str_pad((string)$otp, self::TOTP_DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Encrypt the TOTP secret using AES-256-GCM with the APP_KEY.
     */
    private function encryptSecret(string $plaintext): string
    {
        $key = $this->getEncryptionKey();
        $iv = random_bytes(12); // 96-bit IV for GCM
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::CIPHER_TAG_LENGTH
        );

        if ($ciphertext === false) {
            return '';
        }

        // Store as: base64(iv + tag + ciphertext)
        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt the TOTP secret.
     */
    private function decryptSecret(string $encrypted): string
    {
        // Handle legacy SHA256-hashed secrets (cannot decrypt)
        if (strlen($encrypted) === 64 && ctype_xdigit($encrypted)) {
            return '';
        }

        $key = $this->getEncryptionKey();
        if ($key === '') {
            return '';
        }

        $decoded = base64_decode($encrypted, true);
        if ($decoded === false || strlen($decoded) < 28) {
            return '';
        }

        $iv = substr($decoded, 0, 12);
        $tag = substr($decoded, 12, 16);
        $ciphertext = substr($decoded, 28);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return is_string($plaintext) ? $plaintext : '';
    }

    /**
     * Get the encryption key (first 32 bytes of APP_KEY).
     */
    private function getEncryptionKey(): string
    {
        if ($this->encryptionKey === '') {
            return '';
        }

        // APP_KEY is 64 hex chars (32 bytes) — convert to raw binary
        if (strlen($this->encryptionKey) === 64 && ctype_xdigit($this->encryptionKey)) {
            return hex2bin($this->encryptionKey);
        }

        // If already binary or shorter, hash to get exactly 32 bytes
        return hash('sha256', $this->encryptionKey, true);
    }

    /**
     * Base32 encode (RFC 4648, no padding).
     */
    private function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        $len = strlen($data);

        for ($i = 0; $i < $len; $i++) {
            $binary .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        }

        $result = '';
        for ($i = 0; $i < strlen($binary); $i += 5) {
            $chunk = substr($binary, $i, 5);
            $result .= $alphabet[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $result;
    }

    /**
     * Base32 decode (RFC 4648).
     */
    private function base32Decode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $data = strtoupper(str_replace(['=', '-', ' '], '', $data));
        $binary = '';
        $len = strlen($data);

        for ($i = 0; $i < $len; $i++) {
            $pos = strpos($alphabet, $data[$i]);
            if ($pos === false) {
                continue;
            }
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $result = '';
        for ($i = 0; $i + 7 < strlen($binary); $i += 8) {
            $byte = substr($binary, $i, 8);
            $result .= chr(bindec($byte));
        }

        return $result;
    }
}
