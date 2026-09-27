<?php
declare(strict_types=1);

namespace Api\System\Library\Security;

final class PasswordHasher
{
    private readonly string|int|null $algo;

    public function __construct(string|int|null $algo = null)
    {
        $this->algo = $algo ?? (defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
    }

    public function hash(string $password): string
    {
        return password_hash($password, $this->algo);
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Check if the stored hash was created with an outdated algorithm or
     * cost parameters and should be upgraded on next login.
     */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, $this->algo);
    }
}
