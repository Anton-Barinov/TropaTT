<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Feature_flag\FeatureFlagRepository;
use Api\System\Library\Logger\JsonLogger;

final class FeatureFlagService
{
    /** @param array<string,bool> $defaults */
    public function __construct(
        private readonly FeatureFlagRepository $repo,
        private readonly JsonLogger $logger,
        private readonly array $defaults
    ) {
    }

    /** Once per service instance (== once per request): the defaults loop and schema guard are idempotent. */
    private bool $defaultsEnsured = false;

    public function list(array $filters): array
    {
        $this->ensureDefaults();
        [$items, $total, $page, $limit] = $this->repo->list($filters);
        $normalized = array_map(fn(array $item): array => $this->normalize($item), $items);

        return [
            'items' => $normalized,
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

    public function update(string $publicId, array $input, array $actor): array
    {
        $this->ensureDefaults();
        $flag = $this->repo->findByPublicId($publicId);
        if (!$flag) {
            return ['ok' => false, 'code' => 'FEATURE_FLAG_NOT_FOUND'];
        }

        $set = [];
        if (array_key_exists('is_enabled', $input)) {
            $set['is_enabled'] = (int)((string)$input['is_enabled'] === '1' || $input['is_enabled'] === true);
        }
        if (array_key_exists('payload', $input)) {
            $encoded = json_encode($input['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $set['payload'] = is_string($encoded) ? $encoded : '{}';
        }
        if ($set === []) {
            return ['ok' => false, 'code' => 'FEATURE_FLAG_NO_CHANGES'];
        }

        $set['updated_at'] = gmdate('Y-m-d H:i:s');
        $this->repo->updateByPublicId($publicId, $set);
        $updated = $this->repo->findByPublicId($publicId);

        $this->logger->audit([
            'action' => 'feature_flag_updated',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'feature_flag',
            'entity_public_id' => $publicId,
            'code' => $flag['code'] ?? '',
            'changes' => $set,
        ]);

        return ['ok' => true, 'flag' => $updated ? $this->normalize($updated) : null];
    }

    public function isEnabled(string $code, bool $fallback = false): bool
    {
        $this->ensureDefaults();
        $flag = $this->repo->findByCode($code);
        if (!$flag) {
            return $fallback;
        }

        return (int)($flag['is_enabled'] ?? 0) === 1;
    }

    private function ensureDefaults(): void
    {
        if ($this->defaultsEnsured) {
            return;
        }
        $this->defaultsEnsured = true;

        // Self-healing schema: dedupe legacy duplicate codes and add a UNIQUE
        // index on code so concurrent requests cannot insert the same default
        // twice (per-request registration pattern — see the scheduler fix).
        // ensureSchema() swallows errors internally and never throws.
        $this->repo->ensureSchema();

        $now = gmdate('Y-m-d H:i:s');
        foreach ($this->defaults as $code => $enabled) {
            $existing = $this->repo->findByCode((string)$code);
            if ($existing) {
                $currentValue = (int)($existing['is_enabled'] ?? 0) === 1;
                if ($currentValue !== (bool)$enabled) {
                    $this->repo->updateByPublicId((string)($existing['public_id'] ?? ''), [
                        'is_enabled' => $enabled ? 1 : 0,
                        'updated_at' => $now,
                    ]);
                }
                continue;
            }

            try {
                $this->repo->create((string)$code, (bool)$enabled, [
                    'source' => 'config_default',
                ], $now);
            } catch (\Throwable $e) {
                // Duplicate code under concurrency: another request already
                // created this default; the unique index guarantees one row.
                // Any other error is a real failure and must propagate.
                if (!$this->isDuplicateKeyError($e)) {
                    throw $e;
                }
                error_log('[FeatureFlagService::ensureDefaults] create raced for "' . $code . '": ' . $e->getMessage());
            }
        }
    }

    private function isDuplicateKeyError(\Throwable $e): bool
    {
        $code = (string)$e->getCode();
        // SQLSTATE integrity-constraint violation, Postgres unique_violation,
        // and the MySQL driver code for duplicate entry.
        return in_array($code, ['23000', '23505', '1062'], true);
    }

    private function normalize(array $row): array
    {
        $payloadRaw = (string)($row['payload'] ?? '{}');
        $payload = json_decode($payloadRaw, true);
        if (!is_array($payload)) {
            $payload = [];
        }

        return [
            'public_id' => (string)($row['public_id'] ?? ''),
            'code' => (string)($row['code'] ?? ''),
            'is_enabled' => (int)($row['is_enabled'] ?? 0) === 1,
            'payload' => $payload,
            'created_at' => (string)($row['created_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ];
    }
}
