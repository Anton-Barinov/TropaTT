<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Common\IdempotencyRepository;
use Api\System\Library\Http\JsonResponse;
use Api\System\Library\Http\Request;

final class IdempotencyService
{
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

    public function replay(Request $request, ?array $actor): ?JsonResponse
    {
        $key = $this->extractKey($request);
        if ($key === null) {
            return null;
        }

        $hash = $this->buildHash($key, $request, $actor);
        $row = $this->repository->findByKeyHash($hash);
        if (!$row) {
            return null;
        }

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

    public function remember(Request $request, ?array $actor, JsonResponse $response): void
    {
        $key = $this->extractKey($request);
        if ($key === null) {
            return;
        }

        $hash = $this->buildHash($key, $request, $actor);
        if ($this->repository->findByKeyHash($hash)) {
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

        $logicalRoute = trim((string)($request->query['route'] ?? ''));
        $route = strtoupper($request->method) . ' ' . ($logicalRoute !== '' ? $logicalRoute : $request->path);
        $this->repository->save($hash, $route, $encoded, gmdate('Y-m-d H:i:s'));
    }

    private function buildHash(string $idempotencyKey, Request $request, ?array $actor): string
    {
        $actorPublicId = (string)($actor['public_id'] ?? 'guest');
        $logicalRoute = trim((string)($request->query['route'] ?? ''));
        if ($logicalRoute !== '') {
            $route = strtoupper($request->method) . ' ' . $logicalRoute;
        } else {
            $route = strtoupper($request->method) . ' ' . $request->path;
        }

        return hash('sha256', $actorPublicId . '|' . $route . '|' . $idempotencyKey);
    }
}
