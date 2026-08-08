<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Counterparty\CounterpartyRepository;
use Api\Model\User\UserManagementRepository;
use Api\System\Library\Policy\HierarchyPolicy;
use Api\System\Library\Support\Ulid;

/**
 * ClientService — прокси на CounterpartyService для обратной совместимости.
 * Работает с counterparties где counterparty_type IN ('individual','sole_proprietor','legal_entity').
 */
final class ClientService
{
    private const CLIENT_COUNTERPARTY_TYPES = ['individual', 'sole_proprietor', 'legal_entity'];

    private const CLIENT_FIELDS = [
        'title',
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
        private readonly ?AiSemanticIndexService $semanticIndex = null
    ) {
    }

    public function list(array $filters, array $actor): array
    {
        $scope = $this->accessScope($actor);
        if ($scope['limit_to_creator_ids'] !== null) {
            $filters['created_by_user_ids'] = $scope['limit_to_creator_ids'];
        }

        // Support legacy client_type filter from UI (maps to counterparty_type)
        $typeFilter = self::CLIENT_COUNTERPARTY_TYPES;
        $legacyClientType = $filters['client_type'] ?? null;
        if ($legacyClientType !== null && $legacyClientType !== '') {
            $mappedType = $this->clientTypeToCounterpartyType((string)$legacyClientType);
            if (in_array($mappedType, self::CLIENT_COUNTERPARTY_TYPES, true)) {
                $typeFilter = [$mappedType];
            }
        }

        [$items, $total, $page, $limit] = $this->counterparties->list($filters, $typeFilter);

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
        $item = $this->counterparties->findByPublicId($publicId);
        if (!$item || !$this->canAccess($item, $actor)) {
            return null;
        }

        if (!in_array($item['counterparty_type'] ?? '', self::CLIENT_COUNTERPARTY_TYPES, true)) {
            return null;
        }

        return $this->hydrateClient($item);
    }

    public function create(array $input, array $actor): array
    {
        $publicId = Ulid::generate('cli');
        $now = gmdate('Y-m-d H:i:s');

        $clientType = trim((string)($input['client_type'] ?? 'individual')) ?: 'individual';
        $counterpartyType = $this->clientTypeToCounterpartyType($clientType);

        $set = $this->extractClientSet($input, true);
        $set['counterparty_type'] = $counterpartyType;

        $this->counterparties->create([
            'public_id' => $publicId,
            ...$set,
            'created_by_user_id' => (int)($actor['id'] ?? 0) ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $created = $this->counterparties->findByPublicId($publicId);

        return $created ? $this->hydrateClient($created) : ['public_id' => $publicId];
    }

    public function update(string $publicId, array $input, array $actor): ?array
    {
        $item = $this->counterparties->findByPublicId($publicId);
        if (!$item || !$this->canAccess($item, $actor)) {
            return null;
        }

        if (!in_array($item['counterparty_type'] ?? '', self::CLIENT_COUNTERPARTY_TYPES, true)) {
            return null;
        }

        $set = $this->extractClientSet($input, false);

        if (array_key_exists('client_type', $input)) {
            $clientType = trim((string)$input['client_type']) ?: 'individual';
            $set['counterparty_type'] = $this->clientTypeToCounterpartyType($clientType);
        }

        $set['updated_at'] = gmdate('Y-m-d H:i:s');
        $this->counterparties->updateByPublicId($publicId, $set);
        $this->semanticIndex?->removeEntityDocument('client', $publicId);

        $updated = $this->counterparties->findByPublicId($publicId);

        return $updated ? $this->hydrateClient($updated) : null;
    }

    public function delete(string $publicId, array $actor): bool
    {
        $item = $this->counterparties->findByPublicId($publicId);
        if (!$item || !$this->canAccess($item, $actor)) {
            return false;
        }

        if (!in_array($item['counterparty_type'] ?? '', self::CLIENT_COUNTERPARTY_TYPES, true)) {
            return false;
        }

        $deleted = $this->counterparties->deleteByPublicId($publicId);
        if ($deleted) {
            $this->semanticIndex?->removeEntityDocument('client', $publicId);
        }

        return $deleted;
    }

    private function clientTypeToCounterpartyType(string $clientType): string
    {
        return match ($clientType) {
            'individual' => 'individual',
            'sole_proprietor' => 'sole_proprietor',
            'legal_entity' => 'legal_entity',
            default => 'individual',
        };
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

    private function extractClientSet(array $input, bool $forCreate): array
    {
        $set = [];

        foreach (self::CLIENT_FIELDS as $field) {
            // extra_attributes is a JSON object and is encoded separately below;
            // never coerce it through scalar normalization ("Array to string conversion").
            if ($field === 'extra_attributes') {
                continue;
            }
            if (!$forCreate && !array_key_exists($field, $input)) {
                continue;
            }

            $value = $input[$field] ?? null;
            if ($value === null || $value === '') {
                $set[$field] = null;
                continue;
            }

            $set[$field] = trim((string)$value);
        }

        if ($forCreate && !array_key_exists('status', $set)) {
            $set['status'] = 'active';
        }

        if (($forCreate || array_key_exists('person_birth_date', $input)) && !empty($set['person_birth_date'])) {
            $timestamp = strtotime((string)$set['person_birth_date']);
            $set['person_birth_date'] = $timestamp !== false ? gmdate('Y-m-d', $timestamp) : $set['person_birth_date'];
        }

        if ($forCreate || array_key_exists('extra_attributes', $input)) {
            $set['extra_attributes'] = $this->encodeExtraAttributes($input['extra_attributes'] ?? null);
        }

        return $set;
    }

    private function encodeExtraAttributes(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_array($value)) {
            return null;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return null;
        }

        return $encoded;
    }

    private function hydrateClient(array $item): array
    {
        $raw = $item['extra_attributes'] ?? null;
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            $item['extra_attributes'] = is_array($decoded) ? $decoded : null;
        } else {
            $item['extra_attributes'] = null;
        }

        $item['client_type'] = $item['counterparty_type'] ?? 'individual';

        return $item;
    }
}
