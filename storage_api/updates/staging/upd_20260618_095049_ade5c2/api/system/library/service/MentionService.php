<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Comment\MentionRepository;
use Api\Model\Common\UserRepository;

final class MentionService
{
    public function __construct(
        private readonly MentionRepository $mentions,
        private readonly UserRepository $users,
        private readonly EntityAccessService $entities,
        private readonly ?NotificationService $notifications = null
    ) {
    }

    public function list(array $filters, array $actor): array|string
    {
        if (!(bool)($actor['is_root'] ?? false)
            && !empty($filters['mentioned_user_public_id'])
            && (string)$filters['mentioned_user_public_id'] !== (string)($actor['public_id'] ?? '')
        ) {
            return 'FORBIDDEN';
        }

        if (!empty($filters['entity_type']) && !empty($filters['entity_public_id'])) {
            if (!$this->entities->canAccess((string)$filters['entity_type'], (string)$filters['entity_public_id'], $actor)) {
                return 'FORBIDDEN';
            }
        }

        [$items, $total, $page, $limit] = $this->mentions->list(
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

    public function add(array $input, array $actor): array|string
    {
        $entityType = strtolower(trim((string)($input['entity_type'] ?? '')));
        $entityPublicId = trim((string)($input['entity_public_id'] ?? ''));
        $mentionedUserPublicId = trim((string)($input['mentioned_user_public_id'] ?? ''));

        if (!$this->entities->canAccess($entityType, $entityPublicId, $actor)) {
            return 'FORBIDDEN';
        }

        $mentioned = $this->users->findByPublicId($mentionedUserPublicId);
        if (!$mentioned || (int)($mentioned['is_active'] ?? 0) !== 1) {
            return 'MENTIONED_USER_NOT_FOUND';
        }

        $created = $this->mentions->create($entityType, $entityPublicId, (int)$mentioned['id']);
        if ($created !== []) {
            $this->notifications?->notifyMentionAdded($created, $actor);
        }

        return $created;
    }

    public function delete(string $publicId, array $actor): bool|string
    {
        $mention = $this->mentions->findByPublicId($publicId);
        if (!$mention) {
            return false;
        }

        $isRoot = (bool)($actor['is_root'] ?? false);
        if (!$isRoot && (int)($mention['mentioned_user_id'] ?? 0) !== (int)($actor['id'] ?? 0)) {
            return 'FORBIDDEN';
        }

        return $this->mentions->deleteByPublicId($publicId);
    }
}
