<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Template\TemplateRepository;
use Api\Model\User\UserManagementRepository;
use Api\System\Library\Policy\HierarchyPolicy;
use Api\System\Library\Support\Ulid;

final class TemplateService
{
    public function __construct(
        private readonly TemplateRepository $templates,
        private readonly UserManagementRepository $users,
        private readonly HierarchyPolicy $hierarchy
    ) {
    }

    public function list(string $kind, array $filters, array $actor): array
    {
        $scope = $this->accessScope($actor);
        if ($scope['limit_to_creator_ids'] !== null) {
            $filters['created_by_user_ids'] = $scope['limit_to_creator_ids'];
        }

        [$items, $total, $page, $limit] = $this->templates->list($kind, $filters);
        $items = array_map([$this, 'decodeItem'], $items);

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

    public function create(string $kind, array $input, array $actor): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $publicId = Ulid::generate($kind === 'task' ? 'ttm' : 'ptm');
        $this->templates->create($kind, [
            'public_id' => $publicId,
            'title' => trim((string)$input['title']),
            'payload' => json_encode((array)($input['payload'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_active' => isset($input['is_active']) && (int)$input['is_active'] === 0 ? 0 : 1,
            'created_by_user_id' => (int)($actor['id'] ?? 0) ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->get($kind, $publicId, $actor) ?? ['public_id' => $publicId];
    }

    public function get(string $kind, string $publicId, array $actor): ?array
    {
        $item = $this->templates->findByPublicId($kind, $publicId);
        if (!$item || !$this->canAccess($item, $actor)) {
            return null;
        }

        return $this->decodeItem($item);
    }

    public function update(string $kind, string $publicId, array $input, array $actor): ?array
    {
        $current = $this->templates->findByPublicId($kind, $publicId);
        if (!$current || !$this->canAccess($current, $actor)) {
            return null;
        }

        $set = ['updated_at' => gmdate('Y-m-d H:i:s')];
        if (array_key_exists('title', $input)) {
            $set['title'] = trim((string)$input['title']);
        }
        if (array_key_exists('payload', $input)) {
            $set['payload'] = json_encode((array)$input['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (array_key_exists('is_active', $input)) {
            $set['is_active'] = ((int)$input['is_active'] === 0) ? 0 : 1;
        }

        $this->templates->updateByPublicId($kind, $publicId, $set);
        return $this->get($kind, $publicId, $actor);
    }

    public function delete(string $kind, string $publicId, array $actor): bool
    {
        $current = $this->templates->findByPublicId($kind, $publicId);
        if (!$current || !$this->canAccess($current, $actor)) {
            return false;
        }

        return $this->templates->deleteByPublicId($kind, $publicId);
    }

    /**
     * @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    public function apply(string $kind, string $publicId, array $actor): ?array
    {
        $template = $this->templates->findByPublicId($kind, $publicId);
        if (!$template || !$this->canAccess($template, $actor)) {
            return null;
        }

        $payload = $template;
        $payload = $this->decodeItem($payload);
        $templateData = $payload['payload'] ?? [];

        $publicId = ($kind === 'task' ? 'task_' : 'project_') . bin2hex(random_bytes(12));
        $now = date('Y-m-d H:i:s');
        $userId = (int)($actor['id'] ?? 0);

        $insert = [
            'public_id' => $publicId,
            'title' => $templateData['title'] ?? $template['title'] ?? 'From template',
            'description' => $templateData['description'] ?? '',
            'status_code' => $templateData['status_code'] ?? ($kind === 'task' ? 'new' : 'planned'),
            'priority_code' => $templateData['priority_code'] ?? 'normal',
            'created_by_user_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($kind === 'task' && isset($templateData['assignee_user_id'])) {
            $insert['assignee_user_id'] = (int)$templateData['assignee_user_id'];
        }
        if ($kind === 'task' && isset($templateData['project_id'])) {
            $insert['project_id'] = (int)$templateData['project_id'];
        }

        $this->templates->insertEntity($kind, $insert);

        return ['public_id' => $publicId, 'kind' => $kind];
    }

    private function decodeItem(array $item): array
    {
        $payload = [];
        if (isset($item['payload']) && is_string($item['payload']) && $item['payload'] !== '') {
            $decoded = json_decode($item['payload'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $item['payload'] = $payload;
        $item['is_active'] = (int)($item['is_active'] ?? 1) === 1;
        return $item;
    }

    /**
     * Fail-closed object access: root may access anything; non-root may only
     * access records created by themselves or by their own hierarchy subtree.
     * Records without an owner (created_by_user_id IS NULL) belong to nobody
     * and are therefore root-only (see AGENTS.md object-level authorization).
     */
    private function canAccess(array $item, array $actor): bool
    {
        if ((int)($actor['is_root'] ?? 0) === 1) {
            return true;
        }

        $actorId = (int)($actor['id'] ?? 0);
        $creatorId = (int)($item['created_by_user_id'] ?? 0);
        if ($actorId <= 0 || $creatorId <= 0) {
            return false;
        }

        if ($actorId === $creatorId) {
            return true;
        }

        return $this->hierarchy->isAncestor($actorId, $creatorId);
    }

    /** @return array{limit_to_creator_ids:int[]|null} */
    private function accessScope(array $actor): array
    {
        if ((int)($actor['is_root'] ?? 0) === 1) {
            return ['limit_to_creator_ids' => null];
        }

        $actorId = (int)($actor['id'] ?? 0);
        if ($actorId <= 0) {
            return ['limit_to_creator_ids' => [-1]];
        }

        $descendants = $this->users->descendantIds($actorId);
        if ($descendants === []) {
            $descendants = [$actorId];
        }

        return ['limit_to_creator_ids' => $descendants];
    }
}
