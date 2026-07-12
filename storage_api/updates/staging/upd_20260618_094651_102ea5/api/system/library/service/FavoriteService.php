<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Favorite\FavoriteRepository;

final class FavoriteService
{
    public function __construct(
        private readonly FavoriteRepository $favorites,
        private readonly EntityAccessService $entities
    ) {
    }

    public function list(array $filters, array $actor): array|string
    {
        if (!empty($filters['entity_type']) && !empty($filters['entity_public_id'])) {
            if (!$this->entities->canAccess((string)$filters['entity_type'], (string)$filters['entity_public_id'], $actor)) {
                return 'FORBIDDEN';
            }
        }

        [$items, $total, $page, $limit] = $this->favorites->list(
            $filters,
            (int)($actor['id'] ?? 0),
            (bool)($actor['is_root'] ?? false)
        );

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

    public function create(array $input, array $actor): array|string
    {
        $entityType = strtolower(trim((string)($input['entity_type'] ?? '')));
        $entityPublicId = trim((string)($input['entity_public_id'] ?? ''));

        if (!$this->entities->canAccess($entityType, $entityPublicId, $actor)) {
            return 'FORBIDDEN';
        }

        return $this->favorites->create($entityType, $entityPublicId, (int)($actor['id'] ?? 0));
    }

    public function delete(string $publicId, array $actor): bool|string
    {
        $item = $this->favorites->findByPublicId($publicId);
        if (!$item) {
            return false;
        }

        $isRoot = (bool)($actor['is_root'] ?? false);
        if (!$isRoot && (int)($item['user_id'] ?? 0) !== (int)($actor['id'] ?? 0)) {
            return 'FORBIDDEN';
        }

        return $this->favorites->deleteByPublicId($publicId);
    }
}
