<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Common\IdempotencyRepository;
use Api\System\Library\Http\JsonResponse;
use Api\System\Library\Http\Request;

final class IdempotencyService
{
    private const STALE_CLAIM_SECONDS = 120;
    private const RECORD_TTL_SECONDS = 604800;
    private const PURGE_PROBABILITY = 64;
    private const PURGE_BATCH = 100;

    private ?string $claimPublicId = null;

    public function __construct(private readonly IdempotencyRepository $repository)
    {
    }

    public function extractKey(Request $request): ?string
    {
        $key = trim((string)$request->header('X-Idempotency-Key', ''));
        if ($key === '') {
            return null;
        }

        if (strlen($key) > 255) {
            $key = substr($key, 0, 255);
        }

        return $key;
    }

    /**
     * Reserve the idempotency key before the endpoint body runs.
     * A returned JsonResponse short-circuits the request (stored replay or
     * a409 conflict); null means the caller may proceed and must call complete().
     */
    public function begin(Request $request, ?array $actor): ?JsonResponse
    {
        $this->claimPublicId = null;

        $key = $this->extractKey($request);
        if ($key === null) {
            return null;
        }

        $keyHash = $this->buildHash($key, $request, $actor);
        $requestHash = $this->buildRequestHash($request);
        $organizationPublicId = $this->organizationPublicId($actor);
        $now = gmdate('Y-m-d H:i:s');

        $claimed = $this->repository->tryClaim(
            $keyHash,
            $this->route($request),
            $actor['public_id'] ?? null,
            $organizationPublicId,
            $requestHash,
            $now
        );
        if ($claimed !== null) {
            $this->claimPublicId = (string)$claimed['public_id'];
            return null;
        }

        $row = $this->repository->findByKeyHash($keyHash);
        if ($row === null) {
            return null;
        }

        $status = (string)($row['status'] ?? 'completed');
        if ($status !== 'completed') {
            $staleBefore = gmdate('Y-m-d H:i:s', time() - self::STALE_CLAIM_SECONDS);
            if ((string)($row['created_at'] ?? '') < $staleBefore) {
                $tookOver = $this->repository->takeOverStaleClaim(
                    $keyHash,
                    $actor['public_id'] ?? null,
                    $organizationPublicId,
                    $requestHash,
                    $staleBefore,
                    $now
                );
                if ($tookOver) {
                    $this->claimPublicId = (string)$row['public_id'];
                    return null;
                }
            }

            return $this->conflictResponse(
                $request,
                'IDEMPOTENCY_IN_PROGRESS',
                'The same idempotency key is being processed; retry shortly.'
            );
        }

        if ((string)($row['organization_public_id'] ?? '') !== (string)$organizationPublicId) {
            return $this->conflictResponse(
                $request,
                'IDEMPOTENCY_ORG_MISMATCH',
                'The idempotency key was issued in another workspace.'
            );
        }

        $storedRequestHash = $row['request_hash'] ?? null;
        if (!is_string($storedRequestHash) || $storedRequestHash === '' || !hash_equals($storedRequestHash, $requestHash)) {
            return $this->conflictResponse(
                $request,
                'IDEMPOTENCY_PAYLOAD_MISMATCH',
                'The idempotency key was used with a different request payload.'
            );
        }

        return $this->replayResponse($row, $request);
    }

    /** Publish the outcome of a claimed key so later identical requests replay it. */
    public function complete(JsonResponse $response): void
    {
        if ($this->claimPublicId === null) {
            return;
        }

        $payload = [
            'status' => $response->status(),
            'payload' => $response->payload(),
        ];

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || $encoded === '') {
            return;
        }

        $completed = $this->repository->completeClaim($this->claimPublicId, $encoded, gmdate('Y-m-d H:i:s'));
        $this->claimPublicId = null;

        if ($completed) {
            $this->maybePurge();
        }
    }

    private function maybePurge(): void
    {
        if (random_int(1, self::PURGE_PROBABILITY) !== 1) {
            return;
        }

        $this->repository->purgeExpired(
            gmdate('Y-m-d H:i:s', time() - self::RECORD_TTL_SECONDS),
            gmdate('Y-m-d H:i:s', time() - self::STALE_CLAIM_SECONDS * 5),
            self::PURGE_BATCH
        );
    }

    /** @param array<string,mixed> $row */
    private function replayResponse(array $row, Request $request): ?JsonResponse
    {
        $decoded = json_decode((string)($row['response_payload'] ?? ''), true);
        if (!is_array($decoded)) {
            return null;
        }

        $status = (int)($decoded['status'] ?? 200);
        $payload = $decoded['payload'] ?? null;
        if (!is_array($payload)) {
            return null;
        }

        if ((bool)($payload['success'] ?? false) === true) {
            return JsonResponse::success(
                code: (string)($payload['code'] ?? 'OK'),
                message: (string)($payload['message'] ?? 'OK'),
                data: (array)($payload['data'] ?? []),
                status: $status,
                requestId: $request->requestId,
                correlationId: $request->correlationId,
                meta: array_merge((array)($payload['meta'] ?? []), [
                    'idempotency_replayed' => true,
                ])
            );
        }

        return JsonResponse::error(
            code: (string)($payload['code'] ?? 'ERROR'),
            message: (string)($payload['message'] ?? 'Error'),
            status: $status,
            errors: (array)($payload['errors'] ?? []),
            requestId: $request->requestId,
            correlationId: $request->correlationId,
            meta: array_merge((array)($payload['meta'] ?? []), [
                'idempotency_replayed' => true,
            ])
        );
    }

    private function conflictResponse(Request $request, string $code, string $message): JsonResponse
    {
        return JsonResponse::error(
            code: $code,
            message: $message,
            status: 409,
            errors: [],
            requestId: $request->requestId,
            correlationId: $request->correlationId,
            meta: [
                'idempotency_conflict' => true,
                'retry_after' => self::STALE_CLAIM_SECONDS,
            ]
        );
    }

    private function buildHash(string $idempotencyKey, Request $request, ?array $actor): string
    {
        return hash('sha256', ($actor['public_id'] ?? 'guest')
            . '|' . $this->organizationPublicId($actor)
            . '|' . $this->route($request)
            . '|' . $idempotencyKey);
    }

    private function buildRequestHash(Request $request): string
    {
        return hash('sha256', $request->rawBody);
    }

    private function organizationPublicId(?array $actor): string
    {
        return (string)($actor['organization_public_id'] ?? '');
    }

    private function route(Request $request): string
    {
        $logicalRoute = trim((string)($request->query['route'] ?? ''));

        return strtoupper($request->method) . ' ' . ($logicalRoute !== '' ? $logicalRoute : $request->path);
    }
}
