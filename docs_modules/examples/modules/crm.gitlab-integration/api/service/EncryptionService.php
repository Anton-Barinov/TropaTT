<?php
declare(strict_types=1);

namespace Module\Crm\GitlabIntegration\Service;

use RuntimeException;

final class EncryptionService
{
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
        return hash_hkdf('sha256', $key, 32, 'gitlab-integration');
    }

    public static function encrypt(string $plaintext): string
    {
        $key = self::secretKey();
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if (!is_string($cipher) || $cipher === '') {
            throw new RuntimeException('Failed to encrypt secret');
        }
        return 'v1:' . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $encrypted): ?string
    {
        if (!str_starts_with($encrypted, 'v1:')) {
            return null;
        }
        $blob = base64_decode(substr($encrypted, 3), true);
        if (!is_string($blob) || strlen($blob) < 29) {
            return null;
        }
        $iv = substr($blob, 0, 12);
        $tag = substr($blob, 12, 16);
        $ciphertext = substr($blob, 28);
        try {
            $key = self::secretKey();
        } catch (\Throwable $e) {
            error_log('[GitlabEncryptionService::decrypt] Failed to get secret key: ' . $e->getMessage());
            return null;
        }
        $plain = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return is_string($plain) && $plain !== '' ? $plain : null;
    }

    public static function mask(string $value): string
    {
        $len = mb_strlen($value);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }
        return str_repeat('*', $len - 4) . mb_substr($value, -4);
    }
}
