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
     * @return string SQL WHERE clause fragment
     */
    public function buildSearchCondition(string $moduleName, string $type, string $query, string $tableAlias = ''): string
    {
        $key = $moduleName . '.' . $type;
        $config = $this->searchableTypes[$key] ?? null;

        if ($config === null) {
            return '1=0';
        }

        $prefix = $tableAlias !== '' ? $tableAlias . '.' : '';
        $conditions = [];

        foreach ($config['fields'] as $field) {
            $conditions[] = "{$prefix}{$field} LIKE '%" . addslashes($query) . "%'";
        }

        return '(' . implode(' OR ', $conditions) . ')';
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
