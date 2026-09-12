<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Service;

use Module\Crm\EcommerceGateway\Repository\IngestRepository;

/**
 * Idempotency and duplicate policy for store ingestion (E-COM-01 §8).
 *
 * The key is the caller-supplied `X-TropaTT-Idempotency-Key` when present, and
 * `{store}:{type}:{external_id}` otherwise (§8.1). When the same external id
 * arrives with a *different* body the behaviour is driven by the store setting
 * `on_duplicate`:
 *
 *   - `merge`  — the existing intake item is extended (`state = merged`);
 *   - `reject` — HTTP 409 conflict (`state = conflict`);
 *   - `create` — a new record keyed by the request hash (`state = reserved`).
 *
 * @phpstan-type Decision array{state:string, key:string, record:array<string,mixed>|null}
 */
final class IdempotencyService
{
    public const STATE_RESERVED = 'reserved';
    public const STATE_DUPLICATE = 'duplicate';
    public const STATE_MERGED = 'merged';
    public const STATE_CONFLICT = 'conflict';

    public function __construct(private readonly IngestRepository $repository)
    {
    }

    /**
     * @return Decision
     */
    public function decide(
        int $storeId,
        string $type,
        string $externalId,
        string $requestHash,
        ?string $headerKey,
        string $onDuplicate,
        int $retentionDays = 90
    ): array {
        $baseKey = trim((string)$headerKey);
        if ($baseKey === '') {
            $baseKey = $storeId . ':' . $type . ':' . $externalId;
        }
        $baseKey = mb_substr($baseKey, 0, 191);

        $reservation = $this->repository->reserveIdempotency($storeId, $baseKey, $type, $externalId, $requestHash, $retentionDays);
        if ($reservation['inserted']) {
            return ['state' => self::STATE_RESERVED, 'key' => $baseKey, 'record' => null];
        }

        $record = $reservation['record'];
        if ($record === null) {
            // The competing request vanished between INSERT and SELECT: treat
            // the key as claimed and let the caller process the request.
            return ['state' => self::STATE_RESERVED, 'key' => $baseKey, 'record' => null];
        }

        // A key already bound to another external id must never be reused
        // silently: it usually means the store mixed up order numbers.
        if ((string)($record['external_id'] ?? '') !== $externalId) {
            return ['state' => self::STATE_CONFLICT, 'key' => $baseKey, 'record' => $record];
        }

        $sameBody = hash_equals((string)($record['request_hash'] ?? ''), $requestHash);
        $hasEntity = trim((string)($record['entity_public_id'] ?? '')) !== '';

        if ($sameBody) {
            if (!$hasEntity) {
                // A previous attempt reserved the key and crashed before the
                // entity was written. Re-processing is the safe recovery.
                return ['state' => self::STATE_RESERVED, 'key' => $baseKey, 'record' => $record];
            }

            return ['state' => self::STATE_DUPLICATE, 'key' => $baseKey, 'record' => $record];
        }

        return match ($onDuplicate) {
            'reject' => ['state' => self::STATE_CONFLICT, 'key' => $baseKey, 'record' => $record],
            'create' => $this->reserveDerived($storeId, $baseKey, $type, $externalId, $requestHash, $retentionDays),
            default => ['state' => self::STATE_MERGED, 'key' => $baseKey, 'record' => $record],
        };
    }

    /**
     * `on_duplicate = create`: a changed order becomes a new record, keyed by
     * the body hash so an exact repeat is still deduplicated.
     *
     * @return Decision
     */
    private function reserveDerived(int $storeId, string $baseKey, string $type, string $externalId, string $requestHash, int $retentionDays): array
    {
        $derivedKey = mb_substr($baseKey . ':' . substr($requestHash, 0, 16), 0, 191);
        $reservation = $this->repository->reserveIdempotency($storeId, $derivedKey, $type, $externalId, $requestHash, $retentionDays);
        if ($reservation['inserted']) {
            return ['state' => self::STATE_RESERVED, 'key' => $derivedKey, 'record' => null];
        }

        $record = $reservation['record'];
        if ($record !== null && hash_equals((string)($record['request_hash'] ?? ''), $requestHash)
            && trim((string)($record['entity_public_id'] ?? '')) !== ''
        ) {
            return ['state' => self::STATE_DUPLICATE, 'key' => $derivedKey, 'record' => $record];
        }

        return ['state' => self::STATE_RESERVED, 'key' => $derivedKey, 'record' => $record];
    }

    /**
     * Replays the ingestion result recorded on the first request (§8.2).
     *
     * @param array<string,mixed> $record
     * @return array<string,mixed>
     */
    public static function replayResponse(array $record, string $requestId, string $type, string $externalId): array
    {
        $snapshot = self::decodeSnapshot((string)($record['response_json'] ?? ''));
        $snapshot['request_id'] = $requestId;
        $snapshot['type'] = $type;
        $snapshot['external_id'] = $externalId;
        $snapshot['duplicate'] = true;
        $snapshot['received_at'] = $snapshot['received_at'] ?? gmdate('c');

        return $snapshot;
    }

    /**
     * @return array<string,mixed>
     */
    public static function decodeSnapshot(string $json): array
    {
        if (trim($json) === '') {
            return [
                'intake_item_public_id' => null,
                'task_public_id' => null,
                'contact_public_id' => null,
                'counterparty_public_id' => null,
            ];
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [
            'intake_item_public_id' => null,
            'task_public_id' => null,
            'contact_public_id' => null,
            'counterparty_public_id' => null,
        ];
    }
}
