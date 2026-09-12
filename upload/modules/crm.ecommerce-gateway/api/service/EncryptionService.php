<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Service;

use RuntimeException;

/**
 * At-rest encryption for store secrets.
 *
 * Key derivation follows the module convention (see crm.gitlab-integration):
 * APP_SECRET is stretched with HKDF and a module-specific context, so secrets
 * from different modules cannot be decrypted with each other's keys.
 */
final class EncryptionService
{
    private const PREFIX = 'v1:';
    private const CONTEXT = 'ecommerce-gateway';

    private static function secretKey(): string
    {
        $key = (string)($_ENV['APP_SECRET'] ?? '');
        if ($key === '') {
            $key = (string)getenv('APP_SECRET');
        }
        if ($key === '') {
            $key = (string)($_SERVER['APP_SECRET'] ?? '');
        }
        if ($key === '') {
            throw new RuntimeException('APP_SECRET is not configured for encryption');
        }

        return hash_hkdf('sha256', $key, 32, self::CONTEXT);
    }

    public static function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            throw new RuntimeException('Refusing to encrypt an empty secret');
        }

        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', self::secretKey(), OPENSSL_RAW_DATA, $iv, $tag);
        if (!is_string($cipher) || $cipher === '') {
            throw new RuntimeException('Failed to encrypt store secret');
        }

        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $encrypted): ?string
    {
        if (!str_starts_with($encrypted, self::PREFIX)) {
            return null;
        }

        $blob = base64_decode(substr($encrypted, strlen(self::PREFIX)), true);
        if (!is_string($blob) || strlen($blob) < 29) {
            return null;
        }

        $iv = substr($blob, 0, 12);
        $tag = substr($blob, 12, 16);
        $ciphertext = substr($blob, 28);

        try {
            $key = self::secretKey();
        } catch (\Throwable $e) {
            error_log('[EcommerceGateway EncryptionService::decrypt] ' . $e->getMessage());
            return null;
        }

        $plain = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        return is_string($plain) && $plain !== '' ? $plain : null;
    }

    /**
     * Generates a URL-safe store secret (32 bytes of entropy).
     */
    public static function generateSecret(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public static function mask(string $value): string
    {
        $len = mb_strlen($value);
        if ($len <= 4) {
            return str_repeat('*', max(0, $len));
        }

        return str_repeat('*', $len - 4) . mb_substr($value, -4);
    }
}
