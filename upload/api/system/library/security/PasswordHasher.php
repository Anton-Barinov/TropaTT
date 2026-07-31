<?php
declare(strict_types=1);

namespace Api\System\Library\Security;

final class PasswordHasher
{
    public function __construct(private readonly string|int|null $algo = PASSWORD_ARGON2ID)
    {
    }

    public function hash(string $password): string
    {
        return password_hash($password, $this->algo);
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
