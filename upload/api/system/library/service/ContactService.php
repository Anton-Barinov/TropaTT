<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Auth\AuthRepository;
use Api\Model\Common\UserRepository;
use Api\Model\Contact\ContactRepository;
use Api\Model\Counterparty\CounterpartyRepository;
use Api\Model\User\UserManagementRepository;
use Api\System\Library\Policy\HierarchyPolicy;
use Api\System\Library\Support\Ulid;

final class ContactService
{
    public function __construct(
        private readonly ContactRepository $contacts,
        private readonly CounterpartyRepository $counterparties,
        private readonly UserManagementRepository $users,
        private readonly HierarchyPolicy $hierarchy,
        private readonly UserRepository $userAccounts,
        private readonly AuthRepository $auth,
        private readonly ?AiSemanticIndexService $semanticIndex = null
    ) {
    }

    public function list(array $filters, array $actor): array
    {
        $scope = $this->accessScope($actor);
        if ($scope['limit_to_creator_ids'] !== null) {
            $filters['created_by_user_ids'] = $scope['limit_to_creator_ids'];
        }

        [$items, $total, $page, $limit] = $this->contacts->list($filters);

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
        $item = $this->contacts->findByPublicId($publicId);
        if (!$item || !$this->canAccess($item, $actor)) {
            return null;
        }

        return $item;
    }

    public function create(array $input, array $actor): array
    {
        $publicId = Ulid::generate('cnt');
        $now = gmdate('Y-m-d H:i:s');
        $counterpartyId = $this->resolveCounterpartyId($input, $actor);

        $this->contacts->create([
            'public_id' => $publicId,
            'counterparty_id' => $counterpartyId,
            'full_name' => trim((string)$input['full_name']),
            'email' => trim((string)($input['email'] ?? '')),
            'phone' => trim((string)($input['phone'] ?? '')),
            'role' => trim((string)($input['role'] ?? '')) ?: null,
            'is_primary' => !empty($input['is_primary']) ? 1 : 0,
            'created_by_user_id' => (int)($actor['id'] ?? 0) ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->contacts->findByPublicId($publicId) ?: ['public_id' => $publicId];
    }

    public function update(string $publicId, array $input, array $actor): ?array
    {
        $item = $this->contacts->findByPublicId($publicId);
        if (!$item || !$this->canAccess($item, $actor)) {
            return null;
        }

        $set = [];
        foreach (['full_name', 'email', 'phone', 'role'] as $field) {
            if (array_key_exists($field, $input)) {
                $set[$field] = trim((string)$input[$field]);
            }
        }
        if (array_key_exists('is_primary', $input)) {
            $set['is_primary'] = !empty($input['is_primary']) ? 1 : 0;
        }

        if (array_key_exists('counterparty_public_id', $input)
            || array_key_exists('company_public_id', $input)
            || array_key_exists('client_public_id', $input)) {
            $counterpartyId = $this->resolveCounterpartyId($input, $actor, true);
            $set['counterparty_id'] = $counterpartyId;
            if ((int)($item['counterparty_id'] ?? 0) !== (int)($counterpartyId ?? 0)) {
                // Moving a linked contact between counterparties must not move an
                // already-active guest session into the new tenant scope. Revoke
                // the guest before changing the relationship; a fresh invite is
                // required for the new counterparty.
                $this->revokeLinkedExternalUser((int)($item['user_id'] ?? 0));
            }
        }

        $set['updated_at'] = gmdate('Y-m-d H:i:s');
        $this->contacts->updateByPublicId($publicId, $set);
        $this->semanticIndex?->removeEntityDocument('contact', $publicId);

        return $this->contacts->findByPublicId($publicId);
    }

    public function delete(string $publicId, array $actor): bool
    {
        $item = $this->contacts->findByPublicId($publicId);
        if (!$item || !$this->canAccess($item, $actor)) {
            return false;
        }

        // Deleting the contact must also revoke a linked guest. Otherwise the
        // account remains active with a valid session, even though its data scope
        // becomes empty/orphaned after the contact disappears.
        $this->revokeLinkedExternalUser((int)($item['user_id'] ?? 0));
        $deleted = $this->contacts->deleteByPublicId($publicId);
        if ($deleted) {
            $this->semanticIndex?->removeEntityDocument('contact', $publicId);
        }

        return $deleted;
    }

    /**
     * Резолвит counterparty_id из основного или legacy-полей.
     * Приоритет: counterparty_public_id > company_public_id > client_public_id
     */
    private function resolveCounterpartyId(array $input, array $actor, bool $allowNull = false): ?int
    {
        // Primary: counterparty_public_id
        if (array_key_exists('counterparty_public_id', $input)) {
            $publicId = (string)($input['counterparty_public_id'] ?? '');
            if ($publicId === '') {
                return $allowNull ? null : throw new \RuntimeException('COUNTERPARTY_NOT_FOUND');
            }
            $cp = $this->counterparties->findByPublicId($publicId);
            if (!$cp || !$this->canAccess($cp, $actor)) {
                throw new \RuntimeException('COUNTERPARTY_NOT_FOUND');
            }
            return (int)$cp['id'];
        }

        // Legacy: company_public_id → counterparty
        if (array_key_exists('company_public_id', $input)) {
            $publicId = (string)($input['company_public_id'] ?? '');
            if ($publicId === '') {
                return $allowNull ? null : throw new \RuntimeException('COMPANY_NOT_FOUND');
            }
            $cp = $this->counterparties->findByPublicId($publicId);
            if (!$cp || !$this->canAccess($cp, $actor)) {
                throw new \RuntimeException('COMPANY_NOT_FOUND');
            }
            return (int)$cp['id'];
        }

        // Legacy: client_public_id → counterparty
        if (array_key_exists('client_public_id', $input)) {
            $publicId = (string)($input['client_public_id'] ?? '');
            if ($publicId === '') {
                return $allowNull ? null : throw new \RuntimeException('CLIENT_NOT_FOUND');
            }
            $cp = $this->counterparties->findByPublicId($publicId);
            if (!$cp || !$this->canAccess($cp, $actor)) {
                throw new \RuntimeException('CLIENT_NOT_FOUND');
            }
            return (int)$cp['id'];
        }

        return null;
    }

    /**
     * Revoke every external account linked to a counterparty before the
     * counterparty is removed. This prevents an orphaned portal session from
     * surviving the deletion of its tenant relationship.
     */
    public function revokeExternalUsersForCounterparty(int $counterpartyId): void
    {
        foreach ($this->contacts->findByCounterpartyId($counterpartyId) as $contact) {
            $this->revokeLinkedExternalUser((int)($contact['user_id'] ?? 0));
        }
    }

    private function revokeLinkedExternalUser(int $linkedUserId): void
    {
        if ($linkedUserId <= 0) {
            return;
        }

        $user = $this->userAccounts->findById($linkedUserId);
        if (!$user || (int)($user['is_external'] ?? 0) !== 1 || ($user['deleted_at'] ?? null) !== null) {
            return;
        }

        $now = gmdate('Y-m-d H:i:s');
        $this->auth->revokeAllByUserId($linkedUserId, $now);
        $this->userAccounts->updateById($linkedUserId, [
            'is_active' => 0,
            'auth_token_hash' => null,
            'external_invitation_expires_at' => null,
            'updated_at' => $now,
        ]);
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
}
