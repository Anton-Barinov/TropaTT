<?php
declare(strict_types=1);

namespace Api\Model\Common;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByLogin(string $login): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('users')
            ->where('login', '=', $login)
            ->whereNull('deleted_at')
            ->first();
    }

    public function findById(int $id): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('users')
            ->where('id', '=', $id)
            ->first();
    }

    public function findByEmail(string $email): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('users')
            ->where('email', '=', $email)
            ->whereNull('deleted_at')
            ->first();
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('users')
            ->where('public_id', '=', $publicId)
            ->first();
    }

    public function create(array $payload): int
    {
        return (new QueryBuilder($this->pdo))
            ->from('users')
            ->insertGetId($payload);
    }

    public function findActiveUserIds(): array
    {
        $rows = (new QueryBuilder($this->pdo))
            ->from('users')
            ->where('is_active', '=', 1)
            ->whereNull('deleted_at')
            ->select(['id'])
            ->get();
        return array_map(fn(array $r) => (int)$r['id'], $rows);
    }
}
