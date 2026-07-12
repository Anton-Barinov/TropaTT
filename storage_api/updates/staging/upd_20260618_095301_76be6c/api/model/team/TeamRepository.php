<?php
declare(strict_types=1);

namespace Api\Model\Team;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class TeamRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(array $filters, ?int $actorUserId = null, bool $actorIsRoot = false): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $rows = $this->buildListQuery($filters)
            ->select([
                't.public_id',
                't.title',
                't.team_type',
                't.parent_id',
                't.code',
                't.manager_user_id',
                't.created_by_user_id',
                't.member_user_ids',
                't.created_at',
                't.updated_at',
                'u.public_id AS manager_user_public_id',
                'u.full_name AS manager_name',
                'u.login AS manager_login',
                'cu.public_id AS creator_user_public_id',
                'cu.full_name AS creator_name',
                'cu.login AS creator_login',
                'pt.public_id AS parent_team_public_id',
                'pt.title AS parent_team_title',
            ])
            ->orderBy('t.created_at', 'DESC')
            ->get();

        $items = array_map(fn(array $row): array => $this->hydrateTeamRow($row), $rows);

        if (!$actorIsRoot && $actorUserId !== null && $actorUserId > 0) {
            $items = array_values(array_filter($items, fn(array $item): bool => $this->userHasAccessToTeam($item, $actorUserId)));
        }

        $total = count($items);
        $offset = ($page - 1) * $limit;
        $items = array_slice($items, $offset, $limit);

        return [$items, $total, $page, $limit];
    }

    private function buildListQuery(array $filters): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('teams t')
            ->leftJoin('users u', 'u.id', '=', 't.manager_user_id')
            ->leftJoin('users cu', 'cu.id', '=', 't.created_by_user_id')
            ->leftJoin('teams pt', 'pt.id', '=', 't.parent_id');

        if (!empty($filters['search'])) {
            $query->where('t.title', 'LIKE', '%' . (string)$filters['search'] . '%');
        }

        if (!empty($filters['team_type'])) {
            $query->where('t.team_type', '=', (string)$filters['team_type']);
        }

        if (!empty($filters['parent_public_id'])) {
            $query->where('pt.public_id', '=', (string)$filters['parent_public_id']);
        }

        return $query;
    }

    public function findByPublicId(string $publicId): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('teams t')
            ->leftJoin('users u', 'u.id', '=', 't.manager_user_id')
            ->leftJoin('users cu', 'cu.id', '=', 't.created_by_user_id')
            ->leftJoin('teams pt', 'pt.id', '=', 't.parent_id')
            ->select([
                't.*',
                'u.public_id AS manager_user_public_id',
                'u.full_name AS manager_name',
                'u.login AS manager_login',
                'cu.public_id AS creator_user_public_id',
                'cu.full_name AS creator_name',
                'cu.login AS creator_login',
                'pt.public_id AS parent_team_public_id',
                'pt.title AS parent_team_title',
            ])
            ->where('t.public_id', '=', $publicId)
            ->first();

        return $row ? $this->hydrateTeamRow($row) : null;
    }

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('teams')
            ->insert($payload);
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('teams')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function deleteByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('teams')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }

    /** @return string[] */
    public function listAccessiblePublicIdsForUser(int $actorUserId): array
    {
        if ($actorUserId <= 0) {
            return [];
        }

        $rows = (new QueryBuilder($this->pdo))
            ->from('teams')
            ->select(['public_id', 'manager_user_id', 'member_user_ids'])
            ->get();

        $result = [];
        foreach ($rows as $row) {
            if ($this->userHasAccessToTeam($row, $actorUserId)) {
                $publicId = trim((string)($row['public_id'] ?? ''));
                if ($publicId !== '') {
                    $result[] = $publicId;
                }
            }
        }

        return array_values(array_unique($result));
    }

    public function teamExists(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('teams')
            ->where('public_id', '=', $publicId)
            ->value('public_id') !== false;
    }

    public function userHasAccessToTeam(array $team, int $actorUserId): bool
    {
        if ($actorUserId <= 0) {
            return false;
        }

        if ((int)($team['manager_user_id'] ?? 0) === $actorUserId) {
            return true;
        }

        if ((int)($team['created_by_user_id'] ?? 0) === $actorUserId) {
            return true;
        }

        return in_array($actorUserId, $this->decodeMemberIds($team['member_user_ids'] ?? null), true);
    }

    public function userCanManageTeam(array $team, int $actorUserId, bool $actorIsRoot = false): bool
    {
        if ($actorIsRoot) {
            return true;
        }

        if ($actorUserId <= 0) {
            return false;
        }

        return (int)($team['created_by_user_id'] ?? 0) === $actorUserId;
    }

    public function userIdByPublicId(string $publicId): ?int
    {
        $id = (new QueryBuilder($this->pdo))
            ->from('users')
            ->where('public_id', '=', $publicId)
            ->whereNull('deleted_at')
            ->value('id');

        return $id !== false ? (int)$id : null;
    }

    /** @param string[] $publicIds @return int[] */
    public function userIdsByPublicIds(array $publicIds): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(static fn($v): string => trim((string)$v), $publicIds), static fn(string $v): bool => $v !== '')));
        if ($normalized === []) {
            return [];
        }

        $rows = (new QueryBuilder($this->pdo))
            ->from('users')
            ->select(['id'])
            ->whereIn('public_id', $normalized)
            ->whereNull('deleted_at')
            ->get();

        return array_values(array_map(static fn(array $row): int => (int)$row['id'], $rows));
    }

    /** @param int[] $userIds @return array<int,array{public_id:string,full_name:string,login:string}> */
    public function usersByIds(array $userIds): array
    {
        $normalized = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $v): bool => $v > 0)));
        if ($normalized === []) {
            return [];
        }

        $rows = (new QueryBuilder($this->pdo))
            ->from('users')
            ->select(['id', 'public_id', 'full_name', 'login'])
            ->whereIn('id', $normalized)
            ->whereNull('deleted_at')
            ->get();

        $mapped = [];
        foreach ($rows as $row) {
            $mapped[(int)$row['id']] = [
                'public_id' => (string)($row['public_id'] ?? ''),
                'full_name' => (string)($row['full_name'] ?? ''),
                'login' => (string)($row['login'] ?? ''),
            ];
        }

        $result = [];
        foreach ($normalized as $userId) {
            if (isset($mapped[$userId])) {
                $result[] = $mapped[$userId];
            }
        }

        return $result;
    }

    private function hydrateTeamRow(array $row): array
    {
        $memberIds = $this->decodeMemberIds($row['member_user_ids'] ?? null);
        $memberUsers = $this->usersByIds($memberIds);

        $row['member_user_public_ids'] = array_values(array_map(static fn(array $item): string => (string)$item['public_id'], $memberUsers));
        $row['member_users'] = $memberUsers;

        if (!array_key_exists('manager_user_public_id', $row)) {
            $row['manager_user_public_id'] = null;
        }

        if (!array_key_exists('creator_user_public_id', $row)) {
            $row['creator_user_public_id'] = null;
        }

        return $row;
    }

    /** @return int[] */
    private function decodeMemberIds(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $decoded), static fn(int $value): bool => $value > 0)));
    }
}
