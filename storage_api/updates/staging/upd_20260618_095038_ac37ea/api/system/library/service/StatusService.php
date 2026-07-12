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

    public function get(string $publicId): ?array
    {
        return $this->statuses->findByPublicId($publicId);
    }

    public function create(array $input)
    {
        $scope = trim((string)$input['scope']);
        $code = trim((string)$input['code']);
        if ($this->statuses->findByScopeAndCode($scope, $code)) {
            return 'STATUS_CODE_EXISTS';
        }

        $publicId = Ulid::generate('sts');
        $now = gmdate('Y-m-d H:i:s');

        $this->statuses->create([
            'public_id' => $publicId,
            'scope' => $scope,
            'code' => $code,
            'title' => trim((string)$input['title']),
            'color' => (string)($input['color'] ?? '#64748b'),
            'sort_order' => isset($input['sort_order']) ? (int)$input['sort_order'] : 100,
            'is_active' => isset($input['is_active']) ? (int)((string)$input['is_active'] === '1' || $input['is_active'] === true) : 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->statuses->findByPublicId($publicId) ?: ['public_id' => $publicId];
    }

    public function update(string $publicId, array $input)
    {
        $current = $this->statuses->findByPublicId($publicId);
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
            && $this->statuses->findByScopeAndCode($newScope, $newCode)
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
        $set['updated_at'] = gmdate('Y-m-d H:i:s');

        $this->statuses->updateByPublicId($publicId, $set);

        return $this->statuses->findByPublicId($publicId);
    }

    public function delete(string $publicId, ?string $remapToPublicId = null): array
    {
        $current = $this->statuses->findByPublicId($publicId);
        if (!$current) {
            return ['ok' => false, 'code' => 'STATUS_NOT_FOUND'];
        }

        $scope = (string)($current['scope'] ?? '');
        $code = (string)($current['code'] ?? '');
        $usage = $this->statuses->usageCount($scope, $code);

        if ($usage > 0 && $remapToPublicId === null) {
            return [
                'ok' => false,
                'code' => 'STATUS_IN_USE',
                'usage_count' => $usage,
            ];
        }

        if ($usage > 0) {
            $target = $this->statuses->findByPublicId($remapToPublicId);
            if (!$target) {
                return ['ok' => false, 'code' => 'REMAP_STATUS_NOT_FOUND'];
            }

            if ((string)$target['public_id'] === $publicId) {
                return ['ok' => false, 'code' => 'REMAP_STATUS_SAME'];
            }

            if ((string)($target['scope'] ?? '') !== $scope) {
                return ['ok' => false, 'code' => 'REMAP_SCOPE_MISMATCH'];
            }

            $this->statuses->remapUsage($scope, $code, (string)$target['code']);
        }

        $ok = $this->statuses->deleteByPublicId($publicId);
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
