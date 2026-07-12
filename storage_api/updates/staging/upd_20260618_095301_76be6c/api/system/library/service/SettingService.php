<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Setting\SettingRepository;

final class SettingService
{
    public function __construct(private readonly SettingRepository $settings)
    {
    }

    public function list(array $filters): array
    {
        [$items, $total, $page, $limit] = $this->settings->list($filters);
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

    public function get(string $scope, string $name): ?array
    {
        $item = $this->settings->findByScopeAndName($scope, $name);
        if (!$item) {
            return null;
        }

        return $this->normalize($item);
    }

    /** @param mixed $value */
    public function set(string $scope, string $name, mixed $value): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            $encoded = 'null';
        }

        $item = $this->settings->upsert($scope, $name, $encoded, $now);

        return $this->normalize($item);
    }

    private function normalize(array $item): array
    {
        $raw = (string)($item['value'] ?? '');
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $item['value'] = $decoded;
        }

        return $item;
    }
}
