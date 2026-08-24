<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use PDO;

final class ModuleSearchIntegration
{
    /** @var array<string, array{type: string, fields: array<int, string>}> */
    private array $searchableTypes = [];

    public function registerSearchableType(string $moduleName, string $type, array $fields): void
    {
        $this->searchableTypes[$moduleName . '.' . $type] = [
            'type' => $type,
            'fields' => $fields,
        ];
    }

    /**
     * Build a search query adapter for a module's data.
     *
     * @return array{condition: string, params: list<string>}
     *         condition — SQL WHERE clause fragment with ? placeholders
     *         params    — values to bind (one per ?)
     */
    public function buildSearchCondition(string $moduleName, string $type, string $query, string $tableAlias = ''): array
    {
        $key = $moduleName . '.' . $type;
        $config = $this->searchableTypes[$key] ?? null;

        if ($config === null) {
            return ['condition' => '1=0', 'params' => []];
        }

        $prefix = $tableAlias !== '' ? $tableAlias . '.' : '';
        $conditions = [];
        $params = [];

        // C-2 fix: addslashes() was unsafe for SQL LIKE — it did not escape
        // % and _ wildcards, allowing data extraction via crafted input.
        // We now use parameterized placeholders with properly escaped values.
        $escapedQuery = '%' . str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $query
        ) . '%';

        foreach ($config['fields'] as $field) {
            $conditions[] = "{$prefix}{$field} LIKE ?";
            $params[] = $escapedQuery;
        }

        return [
            'condition' => '(' . implode(' OR ', $conditions) . ')',
            'params'    => $params,
        ];
    }

    /** @return array<string, array{type: string, fields: array<int, string>}> */
    public function getSearchableTypes(): array
    {
        return $this->searchableTypes;
    }

    public function removeType(string $moduleName, string $type): void
    {
        unset($this->searchableTypes[$moduleName . '.' . $type]);
    }
}
