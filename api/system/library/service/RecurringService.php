<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Recurring\RecurringRepository;
use Api\System\Library\Support\Ulid;

final class RecurringService
{
    public function __construct(private readonly RecurringRepository $recurring)
    {
    }

    public function list(array $filters): array
    {
        [$items, $total, $page, $limit] = $this->recurring->list($filters);
        $items = array_map(static function (array $item): array {
            $item['is_active'] = (int)($item['is_active'] ?? 1) === 1;
            return $item;
        }, $items);

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

    public function create(array $input): array
    {
        $publicId = Ulid::generate('rrl');
        $now = gmdate('Y-m-d H:i:s');
        $this->recurring->create([
            'public_id' => $publicId,
            'entity_type' => (string)$input['entity_type'],
            'entity_public_id' => trim((string)$input['entity_public_id']),
            'rrule' => trim((string)$input['rrule']),
            'is_active' => isset($input['is_active']) && (int)$input['is_active'] === 0 ? 0 : 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->get($publicId) ?? ['public_id' => $publicId];
    }

    public function get(string $publicId): ?array
    {
        $item = $this->recurring->findByPublicId($publicId);
        if (!$item) {
            return null;
        }

        $item['is_active'] = (int)($item['is_active'] ?? 1) === 1;
        return $item;
    }

    public function update(string $publicId, array $input): ?array
    {
        $existing = $this->recurring->findByPublicId($publicId);
        if (!$existing) {
            return null;
        }

        $set = ['updated_at' => gmdate('Y-m-d H:i:s')];
        if (array_key_exists('entity_type', $input)) {
            $set['entity_type'] = (string)$input['entity_type'];
        }
        if (array_key_exists('entity_public_id', $input)) {
            $set['entity_public_id'] = trim((string)$input['entity_public_id']);
        }
        if (array_key_exists('rrule', $input)) {
            $set['rrule'] = trim((string)$input['rrule']);
        }
        if (array_key_exists('is_active', $input)) {
            $set['is_active'] = ((int)$input['is_active'] === 0) ? 0 : 1;
        }

        $this->recurring->updateByPublicId($publicId, $set);
        return $this->get($publicId);
    }

    public function pause(string $publicId): ?array
    {
        $ok = $this->recurring->updateByPublicId($publicId, [
            'is_active' => 0,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        if (!$ok) {
            return null;
        }

        return $this->get($publicId);
    }

    public function resume(string $publicId): ?array
    {
        $ok = $this->recurring->updateByPublicId($publicId, [
            'is_active' => 1,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        if (!$ok) {
            return null;
        }

        return $this->get($publicId);
    }

    public function delete(string $publicId): bool
    {
        return $this->recurring->deleteByPublicId($publicId);
    }
}
