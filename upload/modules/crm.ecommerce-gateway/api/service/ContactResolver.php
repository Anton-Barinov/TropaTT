<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Service;

use Api\System\Library\Support\Ulid;
use Module\Crm\EcommerceGateway\Repository\IngestRepository;

/**
 * Resolves an incoming contact to a CRM contact (E-COM-01 §9.2).
 *
 * Matching order: phone+email pair, then phone, then email. When nothing
 * matches a minimal contact is created (name + channel), marked with the
 * gateway as its source. Contact merging stays a core-CRM task; the module
 * only reuses or creates.
 *
 * @phpstan-type ResolvedContact array{
 *     ok: bool,
 *     contact_id: int|null,
 *     contact_public_id: string|null,
 *     counterparty_public_id: string|null,
 *     created: bool,
 *     full_name: string
 * }
 */
final class ContactResolver
{
    public function __construct(private readonly IngestRepository $repository)
    {
    }

    /**
     * @param array<string,mixed> $contact normalized contact from PayloadValidator
     * @return ResolvedContact
     */
    public function resolve(array $contact): array
    {
        $phone = trim((string)($contact['phone'] ?? ''));
        $email = trim((string)($contact['email'] ?? ''));

        $existing = $this->findExisting($phone, $email);
        if ($existing === null) {
            return $this->create($contact, $phone, $email);
        }

        $contactId = (int)($existing['id'] ?? 0);
        $publicId = (string)($existing['public_id'] ?? '');

        return [
            'ok' => $publicId !== '',
            'contact_id' => $contactId > 0 ? $contactId : null,
            'contact_public_id' => $publicId !== '' ? $publicId : null,
            'counterparty_public_id' => $contactId > 0 ? $this->repository->counterpartyPublicIdByContactId($contactId) : null,
            'created' => false,
            'full_name' => trim((string)($existing['full_name'] ?? '')),
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findExisting(string $phone, string $email): ?array
    {
        if ($phone !== '') {
            $byPhone = $this->repository->findContactByPhone($phone);
            if ($byPhone !== null) {
                return $byPhone;
            }
        }
        if ($email !== '') {
            return $this->repository->findContactByEmail($email);
        }

        return null;
    }

    /**
     * @param array<string,mixed> $contact
     * @return ResolvedContact
     */
    private function create(array $contact, string $phone, string $email): array
    {
        $fullName = trim((string)($contact['full_name'] ?? ''));
        if ($fullName === '') {
            // A messenger-only submit has no name yet; the handle keeps the
            // card identifiable until a manager fills in the details.
            $messenger = $contact['messenger'] ?? null;
            if (is_array($messenger) && trim((string)($messenger['handle'] ?? '')) !== '') {
                $fullName = trim((string)$messenger['channel'] . ': ' . (string)$messenger['handle']);
            }
        }
        if ($fullName === '') {
            $companyName = trim((string)($contact['company_name'] ?? ''));
            $fullName = $companyName !== '' ? $companyName : ($phone !== '' ? $phone : $email);
        }

        $created = $this->repository->createContact([
            'public_id' => Ulid::generate('cnt'),
            'full_name' => $fullName,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
            'role' => trim((string)($contact['contact_type'] ?? '')) === 'company' ? 'company' : null,
        ]);

        if ($created === null) {
            return [
                'ok' => false,
                'contact_id' => null,
                'contact_public_id' => null,
                'counterparty_public_id' => null,
                'created' => false,
                'full_name' => $fullName,
            ];
        }

        $contactId = (int)($created['id'] ?? 0);

        return [
            'ok' => true,
            'contact_id' => $contactId > 0 ? $contactId : null,
            'contact_public_id' => (string)($created['public_id'] ?? '') ?: null,
            'counterparty_public_id' => null,
            'created' => true,
            'full_name' => trim((string)($created['full_name'] ?? $fullName)),
        ];
    }
}
