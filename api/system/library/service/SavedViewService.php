<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\View\SavedViewRepository;

final class SavedViewService
{
    public function __construct(private readonly SavedViewRepository $views)
    {
    }

    public function list(array $filters, array $actor): array
    {
        [$items, $total, $page, $limit] = $this->views->list(
            $filters,
            (int)($actor['id'] ?? 0),
            (bool)($actor['is_root'] ?? false)
        );

        foreach ($items as &$item) {
            $item['filters'] = $this->decodeFilters((string)($item['filters'] ?? '{}'));
        }
        unset($item);

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

    public function create(array $input, array $actor): array
    {
        $entityType = strtolower(trim((string)($input['entity_type'] ?? 'task')));
        $title = trim((string)($input['title'] ?? ''));
        $filters = $input['filters'] ?? [];

        $json = json_encode($filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            $json = '{}';
        }

        $item = $this->views->create((int)($actor['id'] ?? 0), $entityType, $title, $json);
        $item['filters'] = $this->decodeFilters((string)($item['filters'] ?? '{}'));

        return $item;
    }

    public function update(string $publicId, array $input, array $actor): array|string|null
    {
        $existing = $this->views->findByPublicId($publicId);
        if (!$existing) {
            return null;
        }

        $isRoot = (bool)($actor['is_root'] ?? false);
        if (!$isRoot && (int)($existing['user_id'] ?? 0) !== (int)($actor['id'] ?? 0)) {
            return 'FORBIDDEN';
        }

        $set = [];
        if (array_key_exists('title', $input)) {
            $set['title'] = trim((string)$input['title']);
        }
        if (array_key_exists('entity_type', $input)) {
            $set['entity_type'] = strtolower(trim((string)$input['entity_type']));
        }
        if (array_key_exists('filters', $input)) {
            $json = json_encode($input['filters'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($json)) {
                $json = '{}';
            }
            $set['filters'] = $json;
        }

        if ($set !== []) {
            $this->views->updateByPublicId($publicId, $set);
        }

        $item = $this->views->findByPublicId($publicId);
        if (!$item) {
            return null;
        }
        $item['filters'] = $this->decodeFilters((string)($item['filters'] ?? '{}'));

        return $item;
    }

    public function delete(string $publicId, array $actor): bool|string
    {
        $existing = $this->views->findByPublicId($publicId);
        if (!$existing) {
            return false;
        }

        $isRoot = (bool)($actor['is_root'] ?? false);
        if (!$isRoot && (int)($existing['user_id'] ?? 0) !== (int)($actor['id'] ?? 0)) {
            return 'FORBIDDEN';
        }

        return $this->views->deleteByPublicId($publicId);
    }

    private function decodeFilters(string $raw): mixed
    {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $raw;
    }
}
