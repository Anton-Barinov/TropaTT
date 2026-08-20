<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Counterparty\CounterpartyRepository;
use Api\Model\User\UserManagementRepository;
use Api\System\Library\Policy\HierarchyPolicy;
use Api\System\Library\Support\Ulid;

final class CounterpartyService
{
    private const COUNTERPARTY_FIELDS = [
        'title',
        'counterparty_type',
        'legal_name',
        'person_last_name',
        'person_first_name',
        'person_middle_name',
        'person_birth_date',
        'tax_inn',
        'tax_kpp',
        'tax_ogrn',
        'tax_ogrnip',
        'bank_account',
        'bank_name',
        'bank_bik',
        'bank_corr_account',
        'website',
        'messenger',
        'address_legal',
        'address_postal',
        'address_actual',
        'notes',
        'email',
        'phone',
        'status',
        'extra_attributes',
    ];

    public function __construct(
        private readonly CounterpartyRepository $counterparties,
        private readonly UserManagementRepository $users,
        private readonly HierarchyPolicy $hierarchy,
        private readonly ?AiSemanticIndexService $semanticIndex = null,
        private readonly ?ContactService $contactService = null
    ) {
    }

    public function list(array $filters, array $actor): array
    {
        $scope = $this->accessScope($actor);
        if ($scope['limit_to_creator_ids'] !== null) {
            $filters['created_by_user_ids'] = $scope['limit_to_creator_ids'];
        }

        [$items, $total, $page, $limit] = $this->counterparties->list($filters);

        $normalizedItems = array_map(function ($item) {
            return $this->normalizeCounterparty($item);
        }, $items);

        return [
            'items' => $normalizedItems,
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

        return $this->normalizeCounterparty($item);
    }

    public function create(array $input, array $actor): array
    {
        $publicId = Ulid::generate('cp');
        $now = gmdate('Y-m-d H:i:s');

        $this->counterparties->create([
            'public_id' => $publicId,
            ...$this->extractCounterpartySet($input, true),
            'created_by_user_id' => (int)($actor['id'] ?? 0) ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->get($publicId, $actor) ?? [];
    }

    public function update(string $publicId, array $input, array $actor): array
    {
        $current = $this->get($publicId, $actor);
        if (!$current) {
            throw new \RuntimeException('COUNTERPARTY_NOT_FOUND');
        }

        $set = $this->extractCounterpartySet($input, false);
        if ($set !== []) {
            $set['updated_at'] = gmdate('Y-m-d H:i:s');
            $this->counterparties->updateByPublicId($publicId, $set);
            $this->semanticIndex?->removeEntityDocument('counterparty', $publicId);
        }

        return $this->get($publicId, $actor) ?? [];
    }

    public function delete(string $publicId, array $actor): bool
    {
        $current = $this->get($publicId, $actor);
        if (!$current) {
            throw new \RuntimeException('COUNTERPARTY_NOT_FOUND');
        }

        // A counterparty deletion removes the tenant relationship. Revoke all
        // linked external accounts first so their existing sessions cannot keep
        // access to orphaned projects/tasks after that relationship disappears.
        $this->contactService?->revokeExternalUsersForCounterparty((int)($current['id'] ?? 0));

        $deleted = $this->counterparties->deleteByPublicId($publicId);
        if ($deleted) {
            $this->semanticIndex?->removeEntityDocument('counterparty', $publicId);
        }

        return $deleted;
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

    /**
     * Fail-closed object access: root may access anything; non-root may only
     * access records created by themselves or by their own hierarchy subtree.
     * Records without an owner (created_by_user_id IS NULL) are visible only
     * to root (they belong to nobody), matching the rest of the CRM.
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

        if ($creatorId === $actorId) {
            return true;
        }

        return $this->hierarchy->isAncestor($actorId, $creatorId);
    }

    private function normalizeCounterparty(array $item): array
    {
        if (is_string($item['extra_attributes'] ?? null) && $item['extra_attributes'] !== '') {
            $decoded = json_decode($item['extra_attributes'], true);
            $item['extra_attributes'] = is_array($decoded) ? $decoded : null;
        } elseif (empty($item['extra_attributes'])) {
            $item['extra_attributes'] = null;
        }

        return $item;
    }

    private function extractCounterpartySet(array $input, bool $requireAll): array
    {
        $set = [];
        foreach (self::COUNTERPARTY_FIELDS as $field) {
            if (array_key_exists($field, $input)) {
                $value = $input[$field];
                if ($value === '' || $value === null) {
                    $set[$field] = null;
                } elseif ($field === 'extra_attributes') {
                    $set[$field] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (is_string($value) ? $value : null);
                } else {
                    $set[$field] = $value;
                }
            } elseif ($requireAll) {
                $set[$field] = null;
            }
        }

        return $set;
    }
}
