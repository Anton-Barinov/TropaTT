<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Repository;

use PDO;
use PDOException;

/**
 * Ingestion persistence that the module owns directly (E-COM-01 §8, §9.2).
 *
 * Idempotency keys live in `ecommerce_idempotency`; contacts are reused from
 * the core `contacts` table so a store order lands on the same contact a
 * manager already works with. Everything is parameterized: no user input ever
 * reaches SQL as text.
 */
final class IngestRepository
{
    private const IDEMPOTENCY_COLUMNS = 'id, store_id, idempotency_key, ingest_type, external_id, '
        . 'request_hash, entity_type, entity_public_id, response_json, created_at, expires_at';

    public function __construct(private readonly PDO $pdo)
    {
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    // ── Idempotency ────────────────────────────────────────────────────

    /**
     * @return array<string,mixed>|null
     */
    public function findIdempotency(int $storeId, string $key): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::IDEMPOTENCY_COLUMNS
            . ' FROM ecommerce_idempotency WHERE store_id = :store_id AND idempotency_key = :idempotency_key LIMIT 1'
        );
        $stmt->execute(['store_id' => $storeId, 'idempotency_key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Atomically claims an idempotency key.
     *
     * The unique index on (store_id, idempotency_key) is the serialization
     * point: the first writer wins and every parallel request observes either
     * the freshly inserted row or the original one.
     *
     * @return array{inserted:bool, record:array<string,mixed>|null}
     */
    public function reserveIdempotency(int $storeId, string $key, string $type, string $externalId, string $requestHash, int $retentionDays = 90): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $expiresAt = gmdate('Y-m-d H:i:s', time() + max(1, $retentionDays) * 86400);

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO ecommerce_idempotency ('
                . 'store_id, idempotency_key, ingest_type, external_id, request_hash, created_at, updated_at, expires_at'
                . ') VALUES (:store_id, :idempotency_key, :ingest_type, :external_id, :request_hash, :created_at, :updated_at, :expires_at)'
            );
            $stmt->execute([
                'store_id' => $storeId,
                'idempotency_key' => $key,
                'ingest_type' => $type,
                'external_id' => $externalId,
                'request_hash' => $requestHash,
                'created_at' => $now,
                'updated_at' => $now,
                'expires_at' => $expiresAt,
            ]);

            return ['inserted' => true, 'record' => null];
        } catch (PDOException $e) {
            // SQLSTATE 23000 = integrity constraint violation on the unique key.
            if ((string)$e->getCode() !== '23000') {
                throw $e;
            }

            return ['inserted' => false, 'record' => $this->findIdempotency($storeId, $key)];
        }
    }

    /**
     * Stores the ingestion result so an idempotent repeat can replay it.
     *
     * @param array<string,mixed> $response
     */
    public function completeIdempotency(int $storeId, string $key, ?string $entityType, ?string $entityPublicId, array $response): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE ecommerce_idempotency SET entity_type = :entity_type, entity_public_id = :entity_public_id,'
            . ' response_json = :response_json, updated_at = :updated_at'
            . ' WHERE store_id = :store_id AND idempotency_key = :idempotency_key'
        );
        $stmt->execute([
            'entity_type' => $entityType,
            'entity_public_id' => $entityPublicId,
            'response_json' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => $this->now(),
            'store_id' => $storeId,
            'idempotency_key' => $key,
        ]);
    }

    public function pruneIdempotency(int $retentionDays): int
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - max(1, $retentionDays) * 86400);
        $stmt = $this->pdo->prepare('DELETE FROM ecommerce_idempotency WHERE created_at < :cutoff');
        $stmt->execute(['cutoff' => $cutoff]);

        return $stmt->rowCount();
    }

    // ── Contacts (E-COM-01 §9.2) ───────────────────────────────────────

    /**
     * @return array<string,mixed>|null
     */
    public function findContactByPhone(string $phone): ?array
    {
        return $this->findContact('phone', $phone);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findContactByEmail(string $email): ?array
    {
        return $this->findContact('email', $email);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findContact(string $column, string $value): ?array
    {
        if ($value === '') {
            return null;
        }
        // Column name is chosen by this method, never by a caller.
        $allowed = ['phone' => 'phone', 'email' => 'email'];
        $column = $allowed[$column] ?? null;
        if ($column === null) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, public_id, counterparty_id, full_name, email, phone FROM contacts'
            . ' WHERE `' . $column . '` = :value ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['value' => $value]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>|null
     */
    public function createContact(array $data): ?array
    {
        $publicId = (string)$data['public_id'];
        $now = $this->now();

        $stmt = $this->pdo->prepare(
            'INSERT INTO contacts ('
            . 'public_id, full_name, email, phone, role, is_primary, created_by_user_id, created_at, updated_at'
            . ') VALUES (:public_id, :full_name, :email, :phone, :role, :is_primary, :created_by_user_id, :created_at, :updated_at)'
        );
        $stmt->execute([
            'public_id' => $publicId,
            'full_name' => $data['full_name'] ?? null,
            // contacts.email is VARCHAR(190) in the core schema.
            'email' => self::truncate($data['email'] ?? null, 190),
            'phone' => self::truncate($data['phone'] ?? null, 64),
            'role' => self::truncate($data['role'] ?? null, 64),
            'is_primary' => 0,
            // A store is not a CRM user: the contact is unowned, the same way
            // an imported contact is.
            'created_by_user_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findContactByPublicId($publicId);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findContactByPublicId(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, public_id, counterparty_id, full_name, email, phone FROM contacts WHERE public_id = :public_id LIMIT 1'
        );
        $stmt->execute(['public_id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function counterpartyPublicIdByContactId(int $contactId): ?string
    {
        if ($contactId <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT cp.public_id FROM counterparties cp'
            . ' INNER JOIN contacts c ON c.counterparty_id = cp.id WHERE c.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $contactId]);
        $value = $stmt->fetchColumn();

        return is_string($value) && $value !== '' ? $value : null;
    }

    // ── CRM-level anti-duplication (E-COM-01 §8.5) ─────────────────────

    /**
     * Finds a fresh intake item from the same contact with the same title, so a
     * double submit from the store is linked instead of silently duplicated.
     *
     * @return array<string,mixed>|null
     */
    public function findRecentDuplicateIntake(int $contactId, string $title, int $excludeIntakeId = 0, int $windowSeconds = 600): ?array
    {
        if ($contactId <= 0 || $title === '') {
            return null;
        }
        $cutoff = gmdate('Y-m-d H:i:s', time() - max(1, $windowSeconds));

        $stmt = $this->pdo->prepare(
            'SELECT id, public_id FROM intake_items'
            . ' WHERE contact_id = :contact_id AND title = :title AND created_at >= :cutoff AND deleted_at IS NULL'
            . ' AND id <> :exclude_id'
            . ' ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([
            'contact_id' => $contactId,
            'title' => $title,
            'cutoff' => $cutoff,
            'exclude_id' => $excludeIntakeId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Store settings keep an internal project id; intake creation needs the
     * public id.
     */
    public function projectPublicIdById(int $projectId): ?string
    {
        if ($projectId <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT public_id FROM projects WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $projectId]);
        $value = $stmt->fetchColumn();

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function linkDuplicateIntake(string $publicId, int $duplicateIntakeItemId): void
    {
        if ($publicId === '' || $duplicateIntakeItemId <= 0) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE intake_items SET duplicate_intake_item_id = :duplicate_id, updated_at = :updated_at WHERE public_id = :public_id'
        );
        $stmt->execute([
            'duplicate_id' => $duplicateIntakeItemId,
            'updated_at' => $this->now(),
            'public_id' => $publicId,
        ]);
    }

    private static function truncate(mixed $value, int $length): ?string
    {
        if ($value === null) {
            return null;
        }
        $string = trim((string)$value);

        return $string === '' ? null : mb_substr($string, 0, $length);
    }
}
