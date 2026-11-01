<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Subscription\SubscriptionRepository;
use Api\System\Library\Security\ActorPermission;

final class SubscriptionService
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly EntityAccessService $entities
    ) {
    }

    private function organizationId(array $actor): ?int
    {
        $orgId = (int)($actor['organization_id'] ?? 0);
        if ($orgId > 0) {
            return $orgId;
        }

        if (!class_exists(ActorPermission::class, false)) {
            require_once __DIR__ . '/../security/ActorPermission.php';
        }
        if (ActorPermission::isPrivileged($actor)) {
            return null;
        }

        return -1;
    }

    public function list(array $filters, array $actor): array|string
    {
        if (!empty($filters['entity_type']) && !empty($filters['entity_public_id'])) {
            if (!$this->entities->canAccess((string)$filters['entity_type'], (string)$filters['entity_public_id'], $actor)) {
                return 'FORBIDDEN';
            }
        }

        $orgId = $this->organizationId($actor);
        $filterOrg = $filters;
        if ($orgId !== null) {
            $filterOrg['organization_id'] = $orgId;
        }

        [$items, $total, $page, $limit] = $this->subscriptions->list(
            $filterOrg,
            (int)($actor['id'] ?? 0),
            $orgId === null
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

        $orgId = $this->organizationId($actor);
        if ($orgId === -1) {
            return 'FORBIDDEN';
        }

        return $this->subscriptions->create($entityType, $entityPublicId, (int)($actor['id'] ?? 0), (int)($orgId ?? 0));
    }

    public function delete(string $publicId, array $actor): bool|string
    {
        $item = $this->subscriptions->findByPublicId($publicId);
        if (!$item) {
            return false;
        }

        $orgId = $this->organizationId($actor);
        if ($orgId !== null) {
            $itemOrgId = (int)($item['organization_id'] ?? 0);
            if ($itemOrgId > 0 && $itemOrgId !== $orgId) {
                return 'FORBIDDEN';
            }
        }

        $isPrivileged = ($orgId === null);
        if (!$isPrivileged && (int)($item['user_id'] ?? 0) !== (int)($actor['id'] ?? 0)) {
            return 'FORBIDDEN';
        }

        return $this->subscriptions->deleteByPublicId($publicId);
    }
}
