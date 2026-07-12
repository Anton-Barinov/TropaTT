<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Activity\ActivityRepository;

final class ActivityService
{
    public function __construct(private readonly ActivityRepository $activity)
    {
    }

    public function feed(array $filters, array $actor): array
    {
        [$items, $total, $page, $limit, $hasMore] = $this->activity->feed(
            $filters,
            (string)($actor['public_id'] ?? ''),
            (bool)($actor['is_root'] ?? false)
        );

        foreach ($items as &$item) {
            $raw = (string)($item['details_json'] ?? '');
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $item['details'] = $decoded;
            } else {
                $item['details'] = $raw;
            }
            unset($item['details_json']);
        }
        unset($item);

        return [
            'items' => $items,
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => $total === null ? null : (int)ceil($total / max(1, $limit)),
                    'has_more' => $hasMore,
                ],
            ],
        ];
    }

    public function entityHistory(string $entityType, string $publicId, array $filters, array $actor): array
    {
        $filters['entity_type'] = $entityType;
        $filters['entity_public_id'] = $publicId;
        $filters['channel'] = 'audit';

        return $this->feed($filters, $actor);
    }
}
