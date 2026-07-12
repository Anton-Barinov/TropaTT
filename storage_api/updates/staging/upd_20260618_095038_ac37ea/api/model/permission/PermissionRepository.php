<?php
declare(strict_types=1);

namespace Api\Model\Permission;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class PermissionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(): array
    {
        return (new QueryBuilder($this->pdo))
            ->from('permissions')
            ->select(['public_id', 'code', 'title', 'created_at'])
            ->orderBy('code', 'ASC')
            ->get();
    }

    public function ensureRegistry(array $registry): void
    {
        foreach ($registry as $code => $title) {
            $exists = (new QueryBuilder($this->pdo))
                ->from('permissions')
                ->select(['id'])
                ->where('code', '=', (string)$code)
                ->exists();
            if ($exists) {
                continue;
            }

            (new QueryBuilder($this->pdo))
                ->from('permissions')
                ->insert([
                'public_id' => 'prm_' . strtoupper(bin2hex(random_bytes(8))),
                'code' => (string)$code,
                'title' => (string)$title,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }
    }
}
