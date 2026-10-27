<?php
declare(strict_types=1);

namespace Module\Crm\ActiveCollabMigration\Service;

use RuntimeException;

final class EncryptionService
{
    private static function key(): string
    {
        $secret = (string)($_ENV['APP_SECRET'] ?? getenv('APP_SECRET') ?: $_SERVER['APP_SECRET'] ?? '');
        if ($secret === '') {
            throw new RuntimeException('APP_SECRET is not configured for ActiveCollab credential encryption');
        }
        return hash_hkdf('sha256', $secret, 32, 'activecollab-migration');
    }

    public static function encrypt(string $plaintext): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if (!is_string($ciphertext) || $ciphertext === '') {
            throw new RuntimeException('Failed to encrypt ActiveCollab credential');
        }
        return 'v1:' . base64_encode($iv . $tag . $ciphertext);
    }

    public static function decrypt(string $encrypted): ?string
    {
        if (!str_starts_with($encrypted, 'v1:')) return null;
        $blob = base64_decode(substr($encrypted, 3), true);
        if (!is_string($blob) || strlen($blob) < 29) return null;
        try {
            $plain = openssl_decrypt(substr($blob, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($blob, 0, 12), substr($blob, 12, 16));
        } catch (\Throwable) {
            return null;
        }
        return is_string($plain) && $plain !== '' ? $plain : null;
    }
}
