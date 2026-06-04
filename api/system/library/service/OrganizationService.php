<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Common\UserRepository;
use Api\Model\Organization\OrganizationRepository;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Support\Ulid;

final class OrganizationService
{
    public function __construct(
        private readonly OrganizationRepository $organizations,
        private readonly UserRepository $users,
        private readonly JsonLogger $logger
    )
    {
    }

    public function list(array $filters, array $actor): array
    {
        $actorId = (int)($actor['id'] ?? 0);
        $isRoot = (bool)($actor['is_root'] ?? false);
        $this->ensureRootDefaultWorkspace($actorId, $isRoot);

        [$items, $total, $page, $limit] = $this->organizations->list($filters, $actorId, $isRoot);

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
        $organization = $this->organizations->findByPublicId($publicId);
        if (!$organization || !$this->canAccess($publicId, $actor)) {
            return null;
        }

        return $organization;
    }

    public function create(array $input, array $actor): array
    {
        $publicId = Ulid::generate('org');
        $now = gmdate('Y-m-d H:i:s');
        $title = trim((string)$input['title']);
        $slug = $this->resolveUniqueSlug($input['slug'] ?? $title, null);

        $this->organizations->create([
            'public_id' => $publicId,
            'title' => $title,
            'slug' => $slug,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->organizations->addOrUpdateMember(
            $publicId,
            (string)$actor['public_id'],
            'owner',
            Ulid::generate('orm'),
            $now
        );

        $this->logger->audit([
            'action' => 'organization_created',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'organization',
            'entity_public_id' => $publicId,
        ]);

        return $this->organizations->findByPublicId($publicId) ?: ['public_id' => $publicId];
    }

    public function update(string $publicId, array $input, array $actor): ?array
    {
        $organization = $this->organizations->findByPublicId($publicId);
        if (!$organization || !$this->canManage($publicId, $actor)) {
            return null;
        }

        $set = [];
        if (array_key_exists('title', $input)) {
            $set['title'] = trim((string)$input['title']);
        }
        if (array_key_exists('slug', $input)) {
            $set['slug'] = $this->resolveUniqueSlug((string)$input['slug'], $publicId);
        }

        if ($set === []) {
            return $organization;
        }

        $set['updated_at'] = gmdate('Y-m-d H:i:s');
        $this->organizations->updateByPublicId($publicId, $set);

        $this->logger->audit([
            'action' => 'organization_updated',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'organization',
            'entity_public_id' => $publicId,
            'payload' => $set,
        ]);

        return $this->organizations->findByPublicId($publicId);
    }

    public function delete(string $publicId, array $actor): bool
    {
        if (!$this->canManage($publicId, $actor)) {
            return false;
        }

        $deleted = $this->organizations->deleteByPublicId($publicId);
        if ($deleted) {
            $this->logger->audit([
                'action' => 'organization_deleted',
                'actor_public_id' => $actor['public_id'] ?? null,
                'entity_type' => 'organization',
                'entity_public_id' => $publicId,
            ]);
        }

        return $deleted;
    }

    public function listMembers(string $publicId, array $actor): ?array
    {
        if (!$this->canAccess($publicId, $actor)) {
            return null;
        }

        return $this->organizations->listMembers($publicId);
    }

    public function addMember(string $publicId, string $userPublicId, string $roleCode, array $actor): bool
    {
        if (!$this->canManage($publicId, $actor)) {
            return false;
        }

        $user = $this->users->findByPublicId($userPublicId);
        if (!$user) {
            return false;
        }

        $added = $this->organizations->addOrUpdateMember(
            $publicId,
            $userPublicId,
            $roleCode,
            Ulid::generate('orm'),
            gmdate('Y-m-d H:i:s')
        );

        if ($added) {
            $this->logger->audit([
                'action' => 'organization_member_upserted',
                'actor_public_id' => $actor['public_id'] ?? null,
                'entity_type' => 'organization',
                'entity_public_id' => $publicId,
                'target_user_public_id' => $userPublicId,
                'role_code' => $roleCode,
            ]);
        }

        return $added;
    }

    public function removeMember(string $publicId, string $userPublicId, array $actor): bool
    {
        if (!$this->canManage($publicId, $actor)) {
            return false;
        }

        $target = $this->users->findByPublicId($userPublicId);
        if (!$target) {
            return false;
        }

        $targetId = (int)$target['id'];
        $targetRole = $this->organizations->memberRole($publicId, $targetId);
        if ($targetRole === 'owner' && $this->organizations->countOwners($publicId) <= 1) {
            return false;
        }

        $removed = $this->organizations->removeMember($publicId, $userPublicId);
        if ($removed) {
            $this->logger->audit([
                'action' => 'organization_member_removed',
                'actor_public_id' => $actor['public_id'] ?? null,
                'entity_type' => 'organization',
                'entity_public_id' => $publicId,
                'target_user_public_id' => $userPublicId,
            ]);
        }

        return $removed;
    }

    private function canAccess(string $organizationPublicId, array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        return $this->organizations->isMember($organizationPublicId, (int)($actor['id'] ?? 0));
    }

    private function canManage(string $organizationPublicId, array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        $role = $this->organizations->memberRole($organizationPublicId, (int)($actor['id'] ?? 0));
        return in_array($role, ['owner', 'admin'], true);
    }

    private function resolveUniqueSlug(string $raw, ?string $excludePublicId): string
    {
        $base = $this->slugify($raw);
        if ($base === '') {
            $base = 'workspace';
        }

        $candidate = $base;
        for ($i = 0; $i < 50; $i++) {
            $existing = $this->organizations->findBySlug($candidate);
            if (!$existing) {
                return $candidate;
            }

            if ($excludePublicId !== null && (string)$existing['public_id'] === $excludePublicId) {
                return $candidate;
            }

            $candidate = $base . '-' . ($i + 2);
        }

        return $base . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
    }

    private function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';
        return trim($value, '-');
    }

    private function ensureRootDefaultWorkspace(int $actorId, bool $isRoot): void
    {
        if (!$isRoot || $actorId <= 0) {
            return;
        }

        [$items, $total] = $this->organizations->list(['page' => 1, 'limit' => 1], $actorId, true);
        if ($total > 0 || $items !== []) {
            return;
        }

        $root = $this->users->findById($actorId);
        if (!$root) {
            return;
        }

        $now = gmdate('Y-m-d H:i:s');
        $publicId = Ulid::generate('org');
        $this->organizations->create([
            'public_id' => $publicId,
            'title' => 'Основное рабочее пространство',
            'slug' => 'main-workspace',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->organizations->addOrUpdateMember(
            $publicId,
            (string)$root['public_id'],
            'owner',
            Ulid::generate('orm'),
            $now
        );
    }
}
