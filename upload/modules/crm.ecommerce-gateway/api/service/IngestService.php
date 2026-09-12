<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Service;

use Api\System\Library\Service\TaskService;
use Module\Crm\EcommerceGateway\Repository\IngestRepository;
use Module\Crm\EcommerceGateway\Repository\StoreRepository;

/**
 * Orchestrates one store → CRM ingestion (E-COM-01 §5, §8, §9).
 *
 * Order of operations: JSON parse → schema validation → idempotency claim →
 * contact resolution → intake creation → optional task → duplicate linking →
 * result snapshot → journal. Every step that fails is recorded in
 * `ecommerce_ingest_events`, so a store developer can always answer "what did
 * the CRM actually receive?".
 *
 * The core intake/task services are optional: when they are absent (a stripped
 * container, a unit test) the request is validated, journaled and rejected with
 * a server error rather than crashing the API.
 *
 * @phpstan-type IngestResult array{
 *     ok: bool,
 *     code: string,
 *     http_status: int,
 *     errors: array<string,mixed>,
 *     data: array<string,mixed>,
 *     idempotency_key: string
 * }
 */
final class IngestService
{
    public const DEFAULT_DUPLICATE_WINDOW_SECONDS = 600;
    private const MAX_JOURNAL_PAYLOAD_BYTES = 262144;

    public function __construct(
        private readonly StoreRepository $storeRepository,
        private readonly IngestRepository $ingestRepository,
        private readonly PayloadValidator $validator,
        private readonly IdempotencyService $idempotency,
        private readonly ContactResolver $contactResolver,
        private readonly IntakeComposer $composer,
        private readonly ?IntakeWriterInterface $intakeWriter = null,
        private readonly ?TaskService $taskService = null,
        private readonly array $config = [],
    ) {
    }

    /**
     * @param array<string,mixed> $store authenticated store row
     * @return IngestResult
     */
    public function ingest(
        array $store,
        string $type,
        string $rawBody,
        string $requestId,
        string $ip,
        string $userAgent,
        ?string $idempotencyHeader = null
    ): array {
        $storeId = (int)$store['id'];
        $started = microtime(true);
        $settings = EcommerceStoreService::settingsFromStore($store);
        $onDuplicate = (string)($settings['on_duplicate'] ?? 'merge');
        $retentionDays = (int)($settings['retention_days'] ?? ($this->config['ingest_events_retention_days'] ?? 90));

        // ── 1. JSON body ───────────────────────────────────────────────
        $raw = [];
        if (trim($rawBody) !== '') {
            $decoded = json_decode($rawBody, true);
            if (!is_array($decoded) || array_is_list($decoded)) {
                return $this->fail(
                    'INGESTION_PAYLOAD_INVALID',
                    400,
                    ['payload' => ['The request body must be a JSON object']],
                    $storeId,
                    $type,
                    null,
                    $rawBody,
                    $requestId,
                    $ip,
                    $userAgent,
                    $started
                );
            }
            $raw = $decoded;
        }

        // ── 2. Schema validation ───────────────────────────────────────
        $validated = $this->validator->validate($type, $raw);
        if (!$validated['ok']) {
            return $this->fail(
                (string)$validated['code'],
                (int)$validated['status'],
                (array)$validated['errors'],
                $storeId,
                $type,
                null,
                $rawBody,
                $requestId,
                $ip,
                $userAgent,
                $started
            );
        }

        /** @var array<string,mixed> $data */
        $data = $validated['data'];
        $externalId = (string)$data['external_id'];
        $requestHash = hash('sha256', $rawBody);

        // ── 3. Idempotency claim (E-COM-01 §8) ─────────────────────────
        $decision = $this->idempotency->decide(
            $storeId,
            $type,
            $externalId,
            $requestHash,
            $idempotencyHeader,
            $onDuplicate,
            $retentionDays
        );
        $key = (string)$decision['key'];

        if ($decision['state'] === IdempotencyService::STATE_CONFLICT) {
            return $this->fail(
                'INGESTION_EXTERNAL_ID_CONFLICT',
                409,
                ['external_id' => ['This idempotency key is already bound to another external_id']],
                $storeId,
                $type,
                $externalId,
                $rawBody,
                $requestId,
                $ip,
                $userAgent,
                $started,
                $key
            );
        }

        if ($decision['state'] === IdempotencyService::STATE_DUPLICATE) {
            /** @var array<string,mixed> $record */
            $record = $decision['record'];
            $response = IdempotencyService::replayResponse($record, $requestId, $type, $externalId);
            $this->journal($storeId, $type, $externalId, $response['intake_item_public_id'] ?? null, 'duplicate', 200, null, $rawBody, $requestId, $ip, $userAgent, $started);

            return $this->ok('INGESTION_DUPLICATE', 200, $response, $key);
        }

        if ($decision['state'] === IdempotencyService::STATE_MERGED) {
            /** @var array<string,mixed> $record */
            $record = $decision['record'];
            $existingPublicId = trim((string)($record['entity_public_id'] ?? ''));
            if ($existingPublicId === '') {
                // The previous attempt reserved the key and crashed before the
                // intake item existed; fall through to a normal creation.
                return $this->create(
                    $store, $type, $data, $rawBody, $requestId, $ip, $userAgent, $key, $storeId, $settings, $started
                );
            }

            $merged = $this->mergeExisting($existingPublicId, $store, $type, $data);
            $snapshot = [
                'intake_item_public_id' => $existingPublicId,
                'task_public_id' => null,
                'contact_public_id' => $merged['contact_public_id'],
                'counterparty_public_id' => $merged['counterparty_public_id'],
                'duplicate' => true,
                'received_at' => gmdate('c'),
            ];
            $this->ingestRepository->completeIdempotency($storeId, $key, 'intake_item', $existingPublicId, $snapshot);
            $this->journal($storeId, $type, $externalId, $existingPublicId, 'merged', 200, null, $rawBody, $requestId, $ip, $userAgent, $started);
            $this->storeRepository->audit('store.ingest_merged', $storeId, 'store', (string)($store['api_key'] ?? ''), [
                'type' => $type,
                'external_id' => $externalId,
                'intake_item_public_id' => $existingPublicId,
            ], $ip, $userAgent);

            $snapshot['request_id'] = $requestId;
            $snapshot['type'] = $type;
            $snapshot['external_id'] = $externalId;

            return $this->ok('INGESTION_DUPLICATE', 200, $snapshot, $key);
        }

        // ── 4. Create ──────────────────────────────────────────────────
        return $this->create(
            $store, $type, $data, $rawBody, $requestId, $ip, $userAgent, $key, $storeId, $settings, $started
        );
    }

    /**
     * @param array<string,mixed> $store
     * @param array<string,mixed> $data
     * @param array<string,mixed> $settings
     * @return IngestResult
     */
    private function create(
        array $store,
        string $type,
        array $data,
        string $rawBody,
        string $requestId,
        string $ip,
        string $userAgent,
        string $key,
        int $storeId,
        array $settings,
        float $started
    ): array {
        $externalId = (string)$data['external_id'];

        if ($this->intakeWriter === null) {
            return $this->fail(
                'INGESTION_INTERNAL_ERROR',
                500,
                [],
                $storeId,
                $type,
                $externalId,
                $rawBody,
                $requestId,
                $ip,
                $userAgent,
                $started,
                $key
            );
        }

        $contact = $this->contactResolver->resolve(is_array($data['contact'] ?? null) ? $data['contact'] : []);
        $composed = $this->composer->compose($type, $data, $store);

        $intakeInput = [
            'title' => $composed['title'],
            'description' => $composed['description'],
            'source_type' => $composed['source_type'],
            'source_ref' => $composed['source_ref'],
            'source_email' => $composed['source_email'],
            'external_source' => (string)($store['api_key'] ?? ''),
            'external_id' => $externalId,
            'extra' => $composed['extra'],
        ];
        if (trim((string)($settings['default_priority_code'] ?? '')) !== '') {
            $intakeInput['priority_code'] = (string)$settings['default_priority_code'];
        }
        if ($contact['contact_public_id'] !== null) {
            $intakeInput['contact_public_id'] = $contact['contact_public_id'];
        }
        $projectPublicId = $this->resolveProjectPublicId($settings);
        if ($projectPublicId !== null) {
            $intakeInput['project_public_id'] = $projectPublicId;
        }
        $assigneeUserId = (int)($settings['default_assignee_id'] ?? 0);
        if ($assigneeUserId > 0) {
            $intakeInput['assignee_user_id'] = $assigneeUserId;
        }

        // The store is not a CRM user: the intake item is created by the system
        // actor (id 0) exactly like an imported record.
        $intake = $this->intakeWriter->create($intakeInput, ['id' => 0, 'full_name' => 'E-Commerce Gateway']);
        if (!is_array($intake)) {
            return $this->fail(
                'INGESTION_INTERNAL_ERROR',
                500,
                [],
                $storeId,
                $type,
                $externalId,
                $rawBody,
                $requestId,
                $ip,
                $userAgent,
                $started,
                $key
            );
        }

        $intakePublicId = (string)($intake['public_id'] ?? '');
        $intakeId = (int)($intake['id'] ?? 0);

        // CRM-level duplicate detection (E-COM-01 §8.5): a fresh twin from the
        // same contact is linked, not silently duplicated.
        if ($contact['contact_id'] !== null) {
            $duplicate = $this->ingestRepository->findRecentDuplicateIntake(
                (int)$contact['contact_id'],
                (string)$composed['title'],
                $intakeId,
                self::DEFAULT_DUPLICATE_WINDOW_SECONDS
            );
            if ($duplicate !== null) {
                $this->ingestRepository->linkDuplicateIntake($intakePublicId, (int)$duplicate['id']);
            }
        }

        $task = null;
        $createTaskFor = is_array($settings['create_task_for'] ?? null) ? $settings['create_task_for'] : [];
        if (in_array($type, $createTaskFor, true)) {
            $task = $this->createTask($type, $store, $data, $composed, $settings, $intakePublicId);
        }

        $snapshot = [
            'intake_item_public_id' => $intakePublicId !== '' ? $intakePublicId : null,
            'task_public_id' => is_array($task) ? (string)($task['public_id'] ?? '') ?: null : null,
            'contact_public_id' => $contact['contact_public_id'],
            'counterparty_public_id' => $contact['counterparty_public_id'],
            'duplicate' => false,
            'received_at' => gmdate('c'),
        ];
        $snapshot['request_id'] = $requestId;
        $snapshot['type'] = $type;
        $snapshot['external_id'] = $externalId;

        $this->ingestRepository->completeIdempotency($storeId, $key, 'intake_item', $intakePublicId !== '' ? $intakePublicId : null, $snapshot);

        $this->journal($storeId, $type, $externalId, $intakePublicId !== '' ? $intakePublicId : null, 'accepted', 201, null, $rawBody, $requestId, $ip, $userAgent, $started);
        $this->storeRepository->audit('store.ingested', $storeId, 'store', (string)($store['api_key'] ?? ''), [
            'type' => $type,
            'external_id' => $externalId,
            'intake_item_public_id' => $intakePublicId,
            'contact_created' => $contact['created'],
        ], $ip, $userAgent);
        $this->markStoreIngest((string)($store['public_id'] ?? ''));

        return $this->ok('INGESTION_ACCEPTED', 201, $snapshot, $key);
    }

    /**
     * Applies the "same external_id, new body" policy for on_duplicate=merge.
     *
     * @param array<string,mixed> $store
     * @param array<string,mixed> $data
     * @return array{contact_public_id:?string, counterparty_public_id:?string}
     */
    private function mergeExisting(string $intakePublicId, array $store, string $type, array $data): array
    {
        $contactPublicId = null;
        $counterpartyPublicId = null;

        if ($this->intakeWriter !== null && $intakePublicId !== '') {
            $existing = $this->intakeWriter->get($intakePublicId, []);
            if (is_array($existing)) {
                $contactPublicId = isset($existing['contact_public_id']) ? (string)$existing['contact_public_id'] : null;
                $contactId = (int)($existing['contact_id'] ?? 0);
                if ($contactId > 0) {
                    $counterpartyPublicId = $this->ingestRepository->counterpartyPublicIdByContactId($contactId);
                }

                $composed = $this->composer->compose($type, $data, $store);
                $note = "\n\n---\n\n**Обновление от витрины (" . gmdate('Y-m-d H:i:s') . " UTC)**\n\n" . $composed['description'];
                $description = trim((string)($existing['description'] ?? ''));
                $newDescription = mb_substr($description . $note, 0, IntakeComposer::MAX_DESCRIPTION);
                $this->intakeWriter->update($intakePublicId, ['description' => $newDescription], ['id' => 0, 'full_name' => 'E-Commerce Gateway']);
            }
        }

        return [
            'contact_public_id' => $contactPublicId !== '' ? $contactPublicId : null,
            'counterparty_public_id' => $counterpartyPublicId !== '' ? $counterpartyPublicId : null,
        ];
    }

    /**
     * @param array<string,mixed> $store
     * @param array<string,mixed> $data
     * @param array<string,mixed> $composed
     * @param array<string,mixed> $settings
     * @return array<string,mixed>|null
     */
    private function createTask(string $type, array $store, array $data, array $composed, array $settings, string $intakePublicId): ?array
    {
        if ($this->taskService === null) {
            return null;
        }

        try {
            $input = [
                'title' => $composed['task_title'],
                'description' => $composed['description'],
                'priority' => trim((string)($settings['default_priority_code'] ?? '')) ?: 'normal',
                'status' => 'new',
                'source_type' => 'api',
                'source_id' => (string)($data['external_id'] ?? ''),
                'source_url' => trim((string)($store['store_url'] ?? '')),
            ];
            $projectPublicId = $this->resolveProjectPublicId($settings);
            if ($projectPublicId !== null) {
                $input['project_public_id'] = $projectPublicId;
            }
            $assigneeUserId = (int)($settings['default_assignee_id'] ?? 0);
            if ($assigneeUserId > 0) {
                $input['assignee_user_id'] = $assigneeUserId;
            }

            $task = $this->taskService->create($input, ['id' => 0, 'full_name' => 'E-Commerce Gateway']);
            if (is_array($task)) {
                return $task;
            }
        } catch (\Throwable $e) {
            // A task is a convenience on top of the intake item: its failure
            // must never lose the store's request.
            error_log('[EcommerceGateway] task creation failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Store settings keep the internal project id (set by the admin UI); intake
     * creation works with public ids.
     *
     * @param array<string,mixed> $settings
     */
    private function resolveProjectPublicId(array $settings): ?string
    {
        $projectId = (int)($settings['default_project_id'] ?? 0);
        if ($projectId <= 0) {
            return null;
        }

        return $this->ingestRepository->projectPublicIdById($projectId);
    }

    private function markStoreIngest(string $storePublicId): void
    {
        if ($storePublicId === '') {
            return;
        }
        try {
            $this->storeRepository->updateStore($storePublicId, [
                'last_ingest_at' => gmdate('Y-m-d H:i:s'),
                'last_error' => null,
            ]);
        } catch (\Throwable $e) {
            error_log('[EcommerceGateway] last_ingest_at update failed: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string,mixed> $errors
     * @return IngestResult
     */
    private function fail(
        string $code,
        int $status,
        array $errors,
        int $storeId,
        string $type,
        ?string $externalId,
        string $rawBody,
        string $requestId,
        string $ip,
        string $userAgent,
        float $started,
        string $idempotencyKey = ''
    ): array {
        $this->journal($storeId, $type, $externalId, null, 'rejected', $status, $code, $rawBody, $requestId, $ip, $userAgent, $started);

        return [
            'ok' => false,
            'code' => $code,
            'http_status' => $status,
            'errors' => $errors,
            'data' => [],
            'idempotency_key' => $idempotencyKey,
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @return IngestResult
     */
    private function ok(string $code, int $status, array $data, string $idempotencyKey): array
    {
        return [
            'ok' => true,
            'code' => $code,
            'http_status' => $status,
            'errors' => [],
            'data' => $data,
            'idempotency_key' => $idempotencyKey,
        ];
    }

    private function journal(
        int $storeId,
        string $type,
        ?string $externalId,
        ?string $entityPublicId,
        string $status,
        int $httpStatus,
        ?string $errorCode,
        string $rawBody,
        string $requestId,
        string $ip,
        string $userAgent,
        float $started
    ): void {
        try {
            $this->storeRepository->recordIngestEvent([
                'request_id' => $requestId,
                'store_id' => $storeId,
                'ingest_type' => $type,
                'external_id' => $externalId,
                'external_id_synthetic' => str_starts_with((string)$externalId, 'auto_') ? 1 : 0,
                'status' => $status,
                'http_status' => $httpStatus,
                'error_code' => $errorCode,
                'entity_type' => $entityPublicId !== null ? 'intake_item' : null,
                'entity_public_id' => $entityPublicId,
                'payload_json' => $this->journalPayload($rawBody),
                'payload_bytes' => strlen($rawBody),
                'duration_ms' => (int)round((microtime(true) - $started) * 1000),
                'ip' => $ip,
                'user_agent' => $userAgent,
            ]);
        } catch (\Throwable $e) {
            error_log('[EcommerceGateway] ingest journal write failed: ' . $e->getMessage());
        }
    }

    /**
     * The journal keeps the raw request for debugging, but inline base64 file
     * bodies are replaced by a marker: they are large and are never used by the
     * journal.
     */
    private function journalPayload(string $rawBody): ?string
    {
        if ($rawBody === '') {
            return null;
        }

        $masked = $rawBody;
        if (strlen($masked) <= self::MAX_JOURNAL_PAYLOAD_BYTES) {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $decoded = $this->maskInlineFiles($decoded);
                $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (is_string($encoded)) {
                    $masked = $encoded;
                }
            }
        } else {
            $masked = substr($rawBody, 0, self::MAX_JOURNAL_PAYLOAD_BYTES);
        }

        return $masked;
    }

    /**
     * @param array<string,mixed> $value
     * @return array<string,mixed>
     */
    private function maskInlineFiles(array $value): array
    {
        foreach ($value as $key => $item) {
            if ($key === 'content_base64' && is_string($item)) {
                $value[$key] = '[inline base64 omitted, ' . strlen($item) . ' bytes]';
                continue;
            }
            if (is_array($item)) {
                $value[$key] = $this->maskInlineFiles($item);
            }
        }

        return $value;
    }
}
