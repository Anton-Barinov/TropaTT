<?php
declare(strict_types=1);

namespace Api\System\Library\Security;

final class TokenManager
{
    public function generate(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
