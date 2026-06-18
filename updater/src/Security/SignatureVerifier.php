<?php
declare(strict_types=1);

namespace Updater\Security;

final class SignatureVerifier
{
    public function __construct(private readonly string $publicKeyPath)
    {
    }

    public function verify(string $payload, string $signature): bool
    {
        if (!is_file($this->publicKeyPath)) {
            return false;
        }
        $public = openssl_pkey_get_public((string)file_get_contents($this->publicKeyPath));
        if ($public === false) {
            return false;
        }
        $decoded = base64_decode($signature, true);
        if ($decoded === false) {
            return false;
        }
        return openssl_verify($payload, $decoded, $public, OPENSSL_ALGO_SHA256) === 1;
    }
}
