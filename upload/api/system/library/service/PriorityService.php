<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Priority\PriorityRepository;
use Api\System\Library\Support\Ulid;

final class PriorityService
{
    public function __construct(private readonly PriorityRepository $priorities)
    {
    }

    public function list(array $filters): array
    {
        [$items, $total, $page, $limit] = $this->priorities->list($filters);

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
        return $this->priorities->findByPublicId($publicId);
    }

    public function create(array $input)
    {
        $code = trim((string)$input['code']);
        if ($this->priorities->findByCode($code)) {
            return 'PRIORITY_CODE_EXISTS';
        }

        $publicId = Ulid::generate('pri');
        $now = gmdate('Y-m-d H:i:s');

        $this->priorities->create([
            'public_id' => $publicId,
            'code' => $code,
            'title' => trim((string)$input['title']),
            'weight' => isset($input['weight']) ? (int)$input['weight'] : 100,
            'color' => (string)($input['color'] ?? '#2563eb'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->priorities->findByPublicId($publicId) ?: ['public_id' => $publicId];
    }

    public function update(string $publicId, array $input)
    {
        $current = $this->priorities->findByPublicId($publicId);
        if (!$current) {
            return null;
        }

        $set = [];
        if (array_key_exists('code', $input)) {
            $newCode = trim((string)$input['code']);
            if ($newCode !== (string)$current['code'] && $this->priorities->findByCode($newCode)) {
                return 'PRIORITY_CODE_EXISTS';
            }
            $set['code'] = $newCode;
        }

        if (array_key_exists('title', $input)) {
            $set['title'] = trim((string)$input['title']);
        }
        if (array_key_exists('weight', $input)) {
            $set['weight'] = (int)$input['weight'];
        }
        if (array_key_exists('color', $input)) {
            $set['color'] = (string)$input['color'];
        }

        $set['updated_at'] = gmdate('Y-m-d H:i:s');
        $this->priorities->updateByPublicId($publicId, $set);

        return $this->priorities->findByPublicId($publicId);
    }

    public function delete(string $publicId): bool
    {
        return $this->priorities->deleteByPublicId($publicId);
    }
}
