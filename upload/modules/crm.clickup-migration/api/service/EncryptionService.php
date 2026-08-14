<?php
declare(strict_types=1);

namespace Module\Crm\ClickUpMigration\Service;

use RuntimeException;

final class EncryptionService
{
    public static function encrypt(string $plain): string
    {
        $secret = (string)(getenv('APP_SECRET') ?: '');
        if ($secret === '') throw new RuntimeException('APP_SECRET_REQUIRED');
        $key = hash('sha256', $secret, true);
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) throw new RuntimeException('CREDENTIAL_ENCRYPT_FAILED');
        $mac = hash_hmac('sha256', $iv . $cipher, $key, true);
        return base64_encode($iv . $mac . $cipher);
    }

    public static function decrypt(string $encoded): ?string
    {
        $secret = (string)(getenv('APP_SECRET') ?: '');
        $raw = base64_decode($encoded, true);
        if ($secret === '' || $raw === false || strlen($raw) < 48) return null;
        $key = hash('sha256', $secret, true);
        $iv = substr($raw, 0, 16); $mac = substr($raw, 16, 32); $cipher = substr($raw, 48);
        $expected = hash_hmac('sha256', $iv . $cipher, $key, true);
        if (!hash_equals($mac, $expected)) return null;
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return $plain === false ? null : $plain;
    }
}
