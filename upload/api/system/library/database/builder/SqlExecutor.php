<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Builder;

use PDO;
use PDOStatement;

final class SqlExecutor
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string,mixed> $params */
    public function fetchValue(string $sql, array $params = []): mixed
    {
        $stmt = $this->prepareAndExecute($sql, $params);
        return $stmt->fetchColumn();
    }

    /**
     * @param array<string,mixed> $params
     * @return array<int,array<string,mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->prepareAndExecute($sql, $params);
        /** @var array<int,array<string,mixed>> $rows */
        $rows = $stmt->fetchAll() ?: [];
        return $rows;
    }

    /** @param array<string,mixed> $params */
    private function prepareAndExecute(string $sql, array $params): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $name => $value) {
            $placeholder = str_starts_with($name, ':') ? $name : ':' . $name;
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $stmt->bindValue($placeholder, $value, $type);
        }
        $stmt->execute();

        return $stmt;
    }
}
