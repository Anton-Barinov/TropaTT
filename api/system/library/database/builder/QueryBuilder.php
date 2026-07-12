<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Builder;

use PDO;

final class QueryBuilder
{
    private string $table = '';
    /** @var array<int,string> */
    private array $columns = ['*'];
    /** @var array<int,string> */
    private array $joins = [];
    /** @var array<int,array{boolean:string,sql:string}> */
    private array $wheres = [];
    /** @var array<int,string> */
    private array $orders = [];
    /** @var array<int,string> */
    private array $groups = [];
    private ?int $limit = null;
    private ?int $offset = null;
    /** @var array<string,mixed> */
    private array $bindings = [];
    private int $paramCounter = 0;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function select(array $columns = ['*']): self
    {
        $this->columns = $columns !== [] ? $columns : ['*'];
        return $this;
    }

    public function from(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    public function join(string $table, string $left, string $operator, string $right, string $type = 'INNER'): self
    {
        $type = strtoupper(trim($type));
        $this->joins[] = sprintf('%s JOIN %s ON %s %s %s', $type, $table, $left, $operator, $right);
        return $this;
    }

    public function leftJoin(string $table, string $left, string $operator, string $right): self
    {
        return $this->join($table, $left, $operator, $right, 'LEFT');
    }

    public function where(string $column, string $operator, mixed $value): self
    {
        return $this->addWhere('AND', $column, $operator, $value);
    }

    public function orWhere(string $column, string $operator, mixed $value): self
    {
        return $this->addWhere('OR', $column, $operator, $value);
    }

    public function whereIn(string $column, array $values): self
    {
        if ($values === []) {
            $this->wheres[] = ['boolean' => 'AND', 'sql' => '1 = 0'];
            return $this;
        }

        $placeholders = [];
        foreach ($values as $value) {
            $placeholder = $this->nextPlaceholder();
            $this->bindings[$placeholder] = $value;
            $placeholders[] = $placeholder;
        }

        $this->wheres[] = [
            'boolean' => 'AND',
            'sql' => sprintf('%s IN (%s)', $column, implode(', ', $placeholders)),
        ];

        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->wheres[] = ['boolean' => 'AND', 'sql' => $column . ' IS NULL'];
        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->wheres[] = ['boolean' => 'AND', 'sql' => $column . ' IS NOT NULL'];
        return $this;
    }

    public function whereRaw(string $expression, array $bindings = [], string $boolean = 'AND'): self
    {
        $sql = $expression;
        if ($bindings !== []) {
            foreach ($bindings as $value) {
                $placeholder = $this->nextPlaceholder();
                $this->bindings[$placeholder] = $value;
                $sql = preg_replace('/\?/', $placeholder, $sql, 1) ?? $sql;
            }
        }

        $this->wheres[] = ['boolean' => strtoupper($boolean) === 'OR' ? 'OR' : 'AND', 'sql' => $sql];
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        // SEC-013: Validate column name to prevent SQL injection
        if (!preg_match('/^[a-zA-Z0-9_.`]+$/', $column)) {
            throw new \InvalidArgumentException('Invalid column name in ORDER BY: ' . $column);
        }
        $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = $column . ' ' . $dir;
        return $this;
    }

    public function orderByRaw(string $expression): self
    {
        $this->orders[] = $expression;
        return $this;
    }

    /**
     * @param string|array<int,string> $columns
     */
    public function groupBy(string|array $columns): self
    {
        $items = is_array($columns) ? $columns : [$columns];
        foreach ($items as $column) {
            $trimmed = trim($column);
            if ($trimmed !== '') {
                $this->groups[] = $trimmed;
            }
        }

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(1, $limit);
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);
        return $this;
    }

    /** @return array<string,mixed> */
    public function first(): ?array
    {
        $this->limit(1);
        $rows = $this->get();
        return $rows[0] ?? null;
    }

    /** @return array<int,array<string,mixed>> */
    public function get(): array
    {
        $stmt = $this->pdo->prepare($this->toSql());
        foreach ($this->bindings as $name => $value) {
            $stmt->bindValue($name, $value);
        }
        $stmt->execute();
        /** @var array<int,array<string,mixed>> $rows */
        $rows = $stmt->fetchAll() ?: [];
        return $rows;
    }

    public function count(string $column = '*'): int
    {
        $stmt = $this->pdo->prepare($this->toCountSql($column));
        foreach ($this->bindings as $name => $value) {
            $stmt->bindValue($name, $value);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function insert(array $values): bool
    {
        if ($this->table === '' || $values === []) {
            return false;
        }

        $columns = array_keys($values);
        $placeholders = [];
        $bindings = [];
        foreach ($values as $column => $value) {
            $placeholder = $this->nextPlaceholder();
            $placeholders[] = $placeholder;
            $bindings[$placeholder] = $value;
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->pdo->prepare($sql);
        foreach ($bindings as $name => $value) {
            $stmt->bindValue($name, $value);
        }

        return $stmt->execute();
    }

    public function insertGetId(array $values): int
    {
        $ok = $this->insert($values);
        if (!$ok) {
            return 0;
        }

        return (int)$this->pdo->lastInsertId();
    }

    public function update(array $values): int
    {
        if ($this->table === '' || $values === []) {
            return 0;
        }

        $assignments = [];
        $bindings = $this->bindings;
        foreach ($values as $column => $value) {
            if ($value instanceof Expression) {
                $assignments[] = $column . ' = ' . $value->toSql();
                continue;
            }

            $placeholder = $this->nextPlaceholder();
            $assignments[] = $column . ' = ' . $placeholder;
            $bindings[$placeholder] = $value;
        }

        $sql = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $assignments) . $this->compileWhere();
        $stmt = $this->pdo->prepare($sql);
        foreach ($bindings as $name => $value) {
            $stmt->bindValue($name, $value);
        }
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function delete(): int
    {
        if ($this->table === '') {
            return 0;
        }

        $sql = 'DELETE FROM ' . $this->table . $this->compileWhere();
        $stmt = $this->pdo->prepare($sql);
        foreach ($this->bindings as $name => $value) {
            $stmt->bindValue($name, $value);
        }
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function value(string $column): mixed
    {
        $row = $this->select([$column])->first();
        if ($row === null) {
            return null;
        }

        $alias = $column;
        if (stripos($column, ' AS ') !== false) {
            $parts = preg_split('/\s+AS\s+/i', $column);
            $alias = $parts[1] ?? $column;
        }

        return $row[$alias] ?? array_values($row)[0] ?? null;
    }

    public function exists(): bool
    {
        return $this->first() !== null;
    }

    /** @return array<string,mixed> */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function toSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->columns) . ' FROM ' . $this->table;
        if ($this->joins !== []) {
            $sql .= ' ' . implode(' ', $this->joins);
        }
        $sql .= $this->compileWhere();
        if ($this->groups !== []) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groups);
        }
        if ($this->orders !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . (int)$this->limit;
        }
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . (int)$this->offset;
        }

        return $sql;
    }

    public function toCountSql(string $column = '*'): string
    {
        $sql = 'SELECT COUNT(' . $column . ') FROM ' . $this->table;
        if ($this->joins !== []) {
            $sql .= ' ' . implode(' ', $this->joins);
        }
        $sql .= $this->compileWhere();
        return $sql;
    }

    private function addWhere(string $boolean, string $column, string $operator, mixed $value): self
    {
        $normalizedOperator = strtoupper(trim($operator));
        if ($value === null && in_array($normalizedOperator, ['IS', 'IS NOT', '=', '!=', '<>'], true)) {
            $sqlOperator = in_array($normalizedOperator, ['IS NOT', '!=', '<>'], true) ? 'IS NOT' : 'IS';
            $this->wheres[] = [
                'boolean' => $boolean,
                'sql' => sprintf('%s %s NULL', $column, $sqlOperator),
            ];

            return $this;
        }

        $placeholder = $this->nextPlaceholder();
        $this->bindings[$placeholder] = $value;
        $this->wheres[] = [
            'boolean' => $boolean,
            'sql' => sprintf('%s %s %s', $column, $operator, $placeholder),
        ];

        return $this;
    }

    private function compileWhere(): string
    {
        if ($this->wheres === []) {
            return '';
        }

        $parts = [];
        foreach ($this->wheres as $i => $where) {
            $prefix = $i === 0 ? '' : $where['boolean'] . ' ';
            $parts[] = $prefix . '(' . $where['sql'] . ')';
        }

        return ' WHERE ' . implode(' ', $parts);
    }

    private function nextPlaceholder(): string
    {
        $this->paramCounter++;
        return ':p' . $this->paramCounter;
    }
}
