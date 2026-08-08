<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Counterparty\CounterpartyRepository;
use Api\Model\User\UserManagementRepository;
use Api\System\Library\Policy\HierarchyPolicy;
use Api\System\Library\Support\Ulid;

/**
 * CompanyService — прокси на CounterpartyService для обратной совместимости.
 * Работает с counterparties где counterparty_type = 'organization'.
 */
final class CompanyService
{
    private const COMPANY_COUNTERPARTY_TYPE = 'organization';

    public function __construct(
        private readonly CounterpartyRepository $counterparties,
        private readonly UserManagementRepository $users,
        private readonly HierarchyPolicy $hierarchy,
        private readonly ?AiSemanticIndexService $semanticIndex = null
    ) {
    }

    public function list(array $filters, array $actor): array
    {
        $scope = $this->accessScope($actor);
        if ($scope['limit_to_creator_ids'] !== null) {
            $filters['created_by_user_ids'] = $scope['limit_to_creator_ids'];
        }

        $filters['counterparty_type'] = self::COMPANY_COUNTERPARTY_TYPE;

        [$items, $total, $page, $limit] = $this->counterparties->list($filters);

        return [
            'items' => array_map(fn(array $item): array => $this->publicCompany($item), $items),
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
        $item = $this->counterparties->findByPublicId($publicId);
        if (!$item || !$this->canAccess($item, $actor)) {
            return null;
        }

        if (($item['counterparty_type'] ?? '') !== self::COMPANY_COUNTERPARTY_TYPE) {
            return null;
        }

        return $this->publicCompany($item);
    }

    public function create(array $input, array $actor): array
    {
        $publicId = Ulid::generate('com');
        $now = gmdate('Y-m-d H:i:s');

        $this->counterparties->create([
            'public_id' => $publicId,
            'counterparty_type' => self::COMPANY_COUNTERPARTY_TYPE,
            'title' => trim((string)$input['title']),
            'status' => trim((string)($input['status'] ?? 'active')) ?: 'active',
            'tax_inn' => trim((string)($input['tax_number'] ?? $input['tax_inn'] ?? '')),
            'email' => trim((string)($input['email'] ?? '')),
            'created_by_user_id' => (int)($actor['id'] ?? 0) ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $created = $this->counterparties->findByPublicId($publicId) ?: ['public_id' => $publicId];

        return $this->publicCompany($created);
    }

    public function update(string $publicId, array $input, array $actor): ?array
    {
        $item = $this->counterparties->findByPublicId($publicId);
        if (!$item || !$this->canAccess($item, $actor)) {
            return null;
        }

        if (($item['counterparty_type'] ?? '') !== self::COMPANY_COUNTERPARTY_TYPE) {
            return null;
        }

        $set = [];
        if (array_key_exists('title', $input)) {
            $set['title'] = trim((string)$input['title']);
        }
        if (array_key_exists('status', $input)) {
            $set['status'] = trim((string)$input['status']) ?: 'active';
        }
        if (array_key_exists('tax_number', $input) || array_key_exists('tax_inn', $input)) {
            $set['tax_inn'] = trim((string)($input['tax_number'] ?? $input['tax_inn'] ?? ''));
        }
        if (array_key_exists('email', $input)) {
            $set['email'] = trim((string)$input['email']);
        }
        $set['updated_at'] = gmdate('Y-m-d H:i:s');

        $this->counterparties->updateByPublicId($publicId, $set);
        $this->semanticIndex?->removeEntityDocument('company', $publicId);

        $updated = $this->counterparties->findByPublicId($publicId);

        return $updated === null ? null : $this->publicCompany($updated);
    }

    public function delete(string $publicId, array $actor): bool
    {
        $item = $this->counterparties->findByPublicId($publicId);
        if (!$item || !$this->canAccess($item, $actor)) {
            return false;
        }

        if (($item['counterparty_type'] ?? '') !== self::COMPANY_COUNTERPARTY_TYPE) {
            return false;
        }

        $deleted = $this->counterparties->deleteByPublicId($publicId);
        if ($deleted) {
            $this->semanticIndex?->removeEntityDocument('company', $publicId);
        }

        return $deleted;
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

    /**
     * Keeps the legacy companies API compatible with the counterparty-backed storage.
     */
    private function publicCompany(array $item): array
    {
        if (!array_key_exists('tax_number', $item) || (string)$item['tax_number'] === '') {
            $item['tax_number'] = (string)($item['tax_inn'] ?? '');
        }

        return $item;
    }

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
