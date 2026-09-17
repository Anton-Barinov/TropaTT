<?php
declare(strict_types=1);

namespace Api\Model\Task;

/** Builds complete hierarchy pages from lightweight, access-scoped task rows. */
final class TaskHierarchyPaginator
{
    /**
     * @param array<int,array<string,mixed>> $matchingRows
     * @param array<int,array<string,mixed>> $scopeRows
     * @return array{public_ids:list<string>,root_ids:list<string>,restricted_root_ids:list<string>,total_roots:int,total_items:int,matched_items:int,page:int,limit:int}
     */
    public static function paginate(array $matchingRows, array $scopeRows, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = $limit === 0 ? 0 : max(1, $limit);
        $parentById = [];
        $scopeOrder = [];
        foreach ($scopeRows as $row) {
            $id = trim((string)($row['public_id'] ?? ''));
            if ($id === '' || isset($parentById[$id])) continue;
            $parentById[$id] = trim((string)($row['parent_task_public_id'] ?? ''));
            $scopeOrder[] = $id;
        }

        $matchingIds = [];
        foreach ($matchingRows as $row) {
            $id = trim((string)($row['public_id'] ?? ''));
            if ($id !== '' && isset($parentById[$id])) $matchingIds[$id] = true;
        }

        $selected = $matchingIds;
        foreach (array_keys($matchingIds) as $id) {
            $cursor = $id;
            $visited = [];
            for ($depth = 0; $depth < 64; $depth++) {
                $parentId = $parentById[$cursor] ?? '';
                if ($parentId === '' || !isset($parentById[$parentId]) || isset($visited[$parentId])) break;
                $selected[$parentId] = true;
                $visited[$parentId] = true;
                $cursor = $parentId;
            }
        }

        $rootIds = [];
        $restrictedRootIds = [];
        foreach ($scopeOrder as $id) {
            if (!isset($selected[$id])) continue;
            $parentId = $parentById[$id] ?? '';
            if ($parentId === '' || !isset($selected[$parentId])) {
                $rootIds[] = $id;
                if ($parentId !== '' && !isset($parentById[$parentId])) $restrictedRootIds[] = $id;
            }
        }
        $pageRootIds = $limit === 0 ? $rootIds : array_slice($rootIds, ($page - 1) * $limit, $limit);
        $pageRoots = array_fill_keys($pageRootIds, true);

        $publicIds = [];
        foreach ($scopeOrder as $id) {
            if (!isset($selected[$id])) continue;
            $cursor = $id;
            $visited = [];
            for ($depth = 0; $depth < 64; $depth++) {
                if (isset($pageRoots[$cursor])) {
                    $publicIds[] = $id;
                    break;
                }
                $parentId = $parentById[$cursor] ?? '';
                if ($parentId === '' || !isset($selected[$parentId]) || isset($visited[$parentId])) break;
                $visited[$parentId] = true;
                $cursor = $parentId;
            }
        }

        return [
            'public_ids' => $publicIds,
            'root_ids' => $pageRootIds,
            'restricted_root_ids' => array_values(array_intersect($pageRootIds, $restrictedRootIds)),
            'total_roots' => count($rootIds),
            'total_items' => count($selected),
            'matched_items' => count($matchingIds),
            'page' => $page,
            'limit' => $limit,
        ];
    }
}
