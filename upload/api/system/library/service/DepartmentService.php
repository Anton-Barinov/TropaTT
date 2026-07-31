<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Team\TeamRepository;
use Api\System\Library\Support\Ulid;

/**
 * DepartmentService — прокси на TeamService для обратной совместимости.
 * Работает с teams где team_type = 'department'.
 */
final class DepartmentService
{
    private const DEPARTMENT_TYPE = 'department';

    public function __construct(
        private readonly TeamRepository $teams,
        private readonly ?NotificationService $notifications = null
    ) {
    }

    public function list(array $filters, array $actor): array
    {
        $filters['team_type'] = self::DEPARTMENT_TYPE;

        [$items, $total, $page, $limit] = $this->teams->list($filters, (int)($actor['id'] ?? 0), (bool)($actor['is_root'] ?? false));

        return [
            'items' => $items,
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int)ceil($total / max(1, $limit)),
                ],
            ],
        ];
    }

    public function get(string $publicId, array $actor): ?array
    {
        $item = $this->teams->findByPublicId($publicId);
        if (!$item || !$this->canAccess($item, $actor)) {
            return null;
        }

        if (($item['team_type'] ?? '') !== self::DEPARTMENT_TYPE) {
            return null;
        }

        return $item;
    }

    public function create(array $input, array $actor): array
    {
        $actorId = (int)($actor['id'] ?? 0);
        $managerId = (bool)($actor['is_root'] ?? false)
            ? (isset($input['manager_user_id']) ? (int)$input['manager_user_id'] : $actorId)
            : $actorId;

        $publicId = Ulid::generate('dep');
        $now = gmdate('Y-m-d H:i:s');

        $this->teams->create([
            'public_id' => $publicId,
            'title' => trim((string)$input['title']),
            'team_type' => self::DEPARTMENT_TYPE,
            'parent_id' => null,
            'code' => trim((string)($input['code'] ?? '')) ?: null,
            'manager_user_id' => $managerId,
            'created_by_user_id' => $actorId > 0 ? $actorId : null,
            'member_user_ids' => json_encode([], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->teams->findByPublicId($publicId) ?: ['public_id' => $publicId];
    }

    public function update(string $publicId, array $input, array $actor): ?array
    {
        $item = $this->teams->findByPublicId($publicId);
        if (!$item || !$this->canAccess($item, $actor)) {
            return null;
        }

        if (($item['team_type'] ?? '') !== self::DEPARTMENT_TYPE) {
            return null;
        }

        $set = [];
        if (array_key_exists('title', $input)) {
            $set['title'] = trim((string)$input['title']);
        }
        if (array_key_exists('code', $input)) {
            $set['code'] = trim((string)$input['code']) ?: null;
        }
        if ((bool)($actor['is_root'] ?? false) && array_key_exists('manager_user_id', $input)) {
            $set['manager_user_id'] = (int)$input['manager_user_id'];
        }

        $set['updated_at'] = gmdate('Y-m-d H:i:s');
        $this->teams->updateByPublicId($publicId, $set);

        return $this->teams->findByPublicId($publicId);
    }

    public function delete(string $publicId, array $actor): bool
    {
        $item = $this->teams->findByPublicId($publicId);
        if (!$item || !$this->canAccess($item, $actor)) {
            return false;
        }

        if (($item['team_type'] ?? '') !== self::DEPARTMENT_TYPE) {
            return false;
        }

        return $this->teams->deleteByPublicId($publicId);
    }

    private function canAccess(array $item, array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        return $this->teams->userHasAccessToTeam($item, (int)($actor['id'] ?? 0));
    }
}
