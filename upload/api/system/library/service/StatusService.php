<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Status\StatusRepository;
use Api\System\Library\Support\Ulid;

final class StatusService
{
    public function __construct(private readonly StatusRepository $statuses)
    {
    }

    /**
     * Normalise a boolean-ish input flag. Accepts true/1/'1'/'true'/'on'.
     * Used for is_active / is_closed so both form posts and JSON behave the same.
     */
    private static function flag(array $input, string $key, bool $default): int
    {
        if (!array_key_exists($key, $input)) {
            return $default ? 1 : 0;
        }

        $value = $input[$key];
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'on', 'yes'], true) ? 1 : 0;
    }

    public function list(array $filters): array
    {
        [$items, $total, $page, $limit] = $this->statuses->list($filters);

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

    public function get(string $publicId, ?int $organizationId = null): ?array
    {
        return $this->statuses->findByPublicId($publicId, $organizationId);
    }

    public function create(array $input, ?int $organizationId = null)
    {
        $scope = trim((string)$input['scope']);
        $code = trim((string)$input['code']);
        if ($this->statuses->findByScopeAndCode($scope, $code, $organizationId)) {
            return 'STATUS_CODE_EXISTS';
        }

        $publicId = Ulid::generate('sts');
        $now = gmdate('Y-m-d H:i:s');

        $payload = [
            'public_id' => $publicId,
            'scope' => $scope,
            'code' => $code,
            'title' => trim((string)$input['title']),
            'color' => (string)($input['color'] ?? '#64748b'),
            'sort_order' => isset($input['sort_order']) ? (int)$input['sort_order'] : 100,
            'is_active' => isset($input['is_active']) ? (int)((string)$input['is_active'] === '1' || $input['is_active'] === true) : 1,
            'is_closed' => self::flag($input, 'is_closed', false),
            'created_at' => $now,
            'updated_at' => $now,
        ];
        if ($organizationId !== null && $organizationId > 0) $payload['organization_id'] = $organizationId;
        $this->statuses->create($payload);

        return $this->statuses->findByPublicId($publicId, $organizationId) ?: ['public_id' => $publicId];
    }

    public function update(string $publicId, array $input, ?int $organizationId = null)
    {
        $current = $this->statuses->findByPublicId($publicId, $organizationId);
        if (!$current) {
            return null;
        }

        $set = [];
        $newScope = (string)$current['scope'];
        $newCode = (string)$current['code'];
        if (array_key_exists('scope', $input)) {
            $newScope = trim((string)$input['scope']);
            $set['scope'] = $newScope;
        }
        if (array_key_exists('code', $input)) {
            $newCode = trim((string)$input['code']);
            $set['code'] = $newCode;
        }

        if (($newScope !== (string)$current['scope'] || $newCode !== (string)$current['code'])
            && $this->statuses->findByScopeAndCode($newScope, $newCode, $organizationId)
        ) {
            return 'STATUS_CODE_EXISTS';
        }

        if (array_key_exists('title', $input)) {
            $set['title'] = trim((string)$input['title']);
        }
        if (array_key_exists('color', $input)) {
            $set['color'] = (string)$input['color'];
        }
        if (array_key_exists('sort_order', $input)) {
            $set['sort_order'] = (int)$input['sort_order'];
        }
        if (array_key_exists('is_active', $input)) {
            $set['is_active'] = (int)((string)$input['is_active'] === '1' || $input['is_active'] === true);
        }
        if (array_key_exists('is_closed', $input)) {
            $set['is_closed'] = self::flag($input, 'is_closed', false);
        }
        $set['updated_at'] = gmdate('Y-m-d H:i:s');

        $this->statuses->updateByPublicId($publicId, $set, $organizationId);

        return $this->statuses->findByPublicId($publicId, $organizationId);
    }

    public function delete(string $publicId, ?string $remapToPublicId = null, ?int $organizationId = null): array
    {
        $current = $this->statuses->findByPublicId($publicId, $organizationId);
        if (!$current) {
            return ['ok' => false, 'code' => 'STATUS_NOT_FOUND'];
        }

        $scope = (string)($current['scope'] ?? '');
        $code = (string)($current['code'] ?? '');
        $usage = $this->statuses->usageCount($scope, $code, $organizationId);

        if ($usage > 0 && $remapToPublicId === null) {
            return [
                'ok' => false,
                'code' => 'STATUS_IN_USE',
                'usage_count' => $usage,
            ];
        }

        if ($usage > 0) {
            $target = $this->statuses->findByPublicId($remapToPublicId, $organizationId);
            if (!$target) {
                return ['ok' => false, 'code' => 'REMAP_STATUS_NOT_FOUND'];
            }

            if ((string)$target['public_id'] === $publicId) {
                return ['ok' => false, 'code' => 'REMAP_STATUS_SAME'];
            }

            if ((string)($target['scope'] ?? '') !== $scope) {
                return ['ok' => false, 'code' => 'REMAP_SCOPE_MISMATCH'];
            }

            $this->statuses->remapUsage($scope, $code, (string)$target['code'], $organizationId);
        }

        $ok = $this->statuses->deleteByPublicId($publicId, $organizationId);
        if (!$ok) {
            return ['ok' => false, 'code' => 'STATUS_NOT_FOUND'];
        }

        return [
            'ok' => true,
            'code' => 'STATUS_DELETED',
            'remapped' => $usage > 0,
            'usage_count' => $usage,
        ];
    }
}
