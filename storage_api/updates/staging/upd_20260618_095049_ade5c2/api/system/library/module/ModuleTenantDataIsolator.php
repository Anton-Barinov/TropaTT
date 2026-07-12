<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleTenantDataIsolator
{
    /**
     * Add tenant_id WHERE clause to a SQL query.
     */
    public function isolateQuery(string $sql, int $tenantId): string
    {
        $upper = strtoupper(trim($sql));

        if (str_starts_with($upper, 'SELECT')) {
            if (stripos($sql, 'WHERE') !== false) {
                return preg_replace('/WHERE/i', 'WHERE tenant_id = ' . (int)$tenantId . ' AND ', $sql, 1) ?? $sql;
            }

            $fromPos = stripos($sql, 'FROM');
            $orderPos = stripos($sql, 'ORDER BY');
            $groupPos = stripos($sql, 'GROUP BY');
            $limitPos = stripos($sql, 'LIMIT');

            $insertPos = $orderPos ?: $groupPos ?: $limitPos ?: strlen($sql);
            if ($insertPos === false) {
                $insertPos = strlen($sql);
            }

            return substr($sql, 0, $insertPos) . ' WHERE tenant_id = ' . (int)$tenantId . ' ' . substr($sql, $insertPos);
        }

        if (str_starts_with($upper, 'UPDATE')) {
            return preg_replace('/SET/i', 'SET tenant_id = ' . (int)$tenantId . ', ', $sql, 1) ?? $sql;
        }

        return $sql;
    }

    public function checkDataAccess(string $moduleName, string $tableName, int $tenantId): bool
    {
        return true;
    }
}
