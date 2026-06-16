<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\View\SavedViewRepository;

final class SavedViewService
{
    private const VALID_ENTITY_TYPES = ['task', 'project', 'client', 'knowledge'];
    private const VALID_ACCESS_LEVELS = ['private', 'public', 'system'];
    private const VALID_LAYOUTS = ['list', 'table', 'board', 'calendar', 'gantt'];
    private const VALID_GROUP_BY = ['none', 'status', 'priority', 'assignee', 'project', 'due_date', 'tag'];
    private const VALID_ORDER_BY = ['created_at', 'updated_at', 'due_at', 'start_at', 'end_at', 'title', 'priority_code', 'status_code', 'task_key'];
    private const VALID_ORDER_DIRS = ['asc', 'desc'];
    private const DEFAULT_DISPLAY_PROPERTIES = '{"task_key":true,"title":true,"status":true,"priority":true,"assignee":true,"project":true,"tags":true,"due_at":true,"updated_at":true}';
    private const MAX_TITLE_LENGTH = 255;
    private const MAX_DESCRIPTION_LENGTH = 2000;
    private const MAX_JSON_SIZE = 65535;

    public function __construct(private readonly SavedViewRepository $views)
    {
    }

    /**
     * List saved views (v2).
     */
    public function list(array $filters, array $actor): array
    {
        $result = $this->views->list(
            $filters,
            (int)($actor['id'] ?? 0),
            (bool)($actor['is_root'] ?? false)
        );

        $items = $result['items'];
        foreach ($items as &$item) {
            $item['filters'] = $this->decodeJsonField((string)($item['filters'] ?? '{}'));
            $item['display_filters'] = $this->decodeJsonField((string)($item['display_filters'] ?? 'null'));
            $item['display_properties'] = $this->decodeJsonField((string)($item['display_properties'] ?? 'null'));
            $item['rich_filters'] = $this->decodeJsonField((string)($item['rich_filters'] ?? 'null'));
            $item['is_pinned'] = (bool)($item['is_pinned'] ?? false);
        }
        unset($item);

        return [
            'items' => $items,
            'meta' => [
                'pagination' => [
                    'page' => $result['page'],
                    'limit' => $result['limit'],
                    'total' => $result['total'],
                    'pages' => (int)ceil($result['total'] / max(1, $result['limit'])),
                ],
            ],
        ];
    }

    /**
     * Get a single saved view (v2).
     *
     * @return array|string|null
     */
    public function get(string $publicId, array $actor): array|string|null
    {
        $item = $this->views->findByPublicId($publicId);
        if (!$item) {
            return null;
        }

        // Check access
        $accessCheck = $this->checkViewAccess($item, $actor);
        if ($accessCheck !== true) {
            return $accessCheck;
        }

        return $this->normalizeItem($item);
    }

    /**
     * Create a saved view (v2).
     *
     * @return array|string
     */
    public function create(array $input, array $actor): array|string
    {
        $entityType = strtolower(trim((string)($input['entity_type'] ?? 'task')));
        $title = trim((string)($input['title'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $accessLevel = strtolower(trim((string)($input['access_level'] ?? 'private')));
        $layout = strtolower(trim((string)($input['layout'] ?? 'list')));
        $isSystem = (bool)($input['is_system'] ?? false);
        $isLocked = (bool)($input['is_locked'] ?? false);
        $isRoot = (bool)($actor['is_root'] ?? false);

        // Validate entity_type
        if (!in_array($entityType, self::VALID_ENTITY_TYPES, true)) {
            return 'SAVED_VIEW_INVALID_ENTITY_TYPE';
        }

        // Validate title
        if ($title === '') {
            return 'SAVED_VIEW_TITLE_REQUIRED';
        }
        if (mb_strlen($title) > self::MAX_TITLE_LENGTH) {
            return 'SAVED_VIEW_TITLE_TOO_LONG';
        }

        // Validate description
        if (mb_strlen($description) > self::MAX_DESCRIPTION_LENGTH) {
            return 'SAVED_VIEW_INVALID_DESCRIPTION';
        }

        // Validate access_level
        if (!in_array($accessLevel, self::VALID_ACCESS_LEVELS, true)) {
            return 'SAVED_VIEW_INVALID_ACCESS_LEVEL';
        }

        // Only root can create system-level views
        if ($accessLevel === 'system' && !$isRoot) {
            return 'SAVED_VIEW_SYSTEM_ONLY_ROOT';
        }

        // Validate layout
        if (!in_array($layout, self::VALID_LAYOUTS, true)) {
            return 'SAVED_VIEW_INVALID_LAYOUT';
        }

        // Only root can set is_system or is_locked
        if (($isSystem || $isLocked) && !$isRoot) {
            return 'SAVED_VIEW_FORBIDDEN';
        }

        // Validate unique title for private views
        if ($accessLevel === 'private') {
            if ($this->views->titleExistsForUser($title, $entityType, (int)($actor['id'] ?? 0))) {
                return 'SAVED_VIEW_TITLE_ALREADY_EXISTS';
            }
        }

        // Parse filters
        $filtersJson = $this->encodeJsonField($input['filters'] ?? []);
        if ($filtersJson === null || strlen($filtersJson) > self::MAX_JSON_SIZE) {
            return 'SAVED_VIEW_INVALID_FILTERS';
        }

        // Parse display_filters
        $displayFiltersJson = null;
        if (array_key_exists('display_filters', $input)) {
            $encoded = $this->encodeJsonField($input['display_filters']);
            if ($encoded === null || strlen($encoded) > self::MAX_JSON_SIZE) {
                return 'SAVED_VIEW_INVALID_DISPLAY_FILTERS';
            }
            $displayFiltersJson = $encoded;
        }

        // Parse display_properties
        $displayPropertiesJson = null;
        if (array_key_exists('display_properties', $input)) {
            $encoded = $this->encodeJsonField($input['display_properties']);
            if ($encoded === null || strlen($encoded) > self::MAX_JSON_SIZE) {
                return 'SAVED_VIEW_INVALID_DISPLAY_PROPERTIES';
            }
            $displayPropertiesJson = $encoded;
        } else {
            $displayPropertiesJson = self::DEFAULT_DISPLAY_PROPERTIES;
        }

        // Parse rich_filters
        $richFiltersJson = null;
        if (array_key_exists('rich_filters', $input)) {
            $encoded = $this->encodeJsonField($input['rich_filters']);
            if ($encoded === null || strlen($encoded) > self::MAX_JSON_SIZE) {
                return 'SAVED_VIEW_INVALID_RICH_FILTERS';
            }
            $richFiltersJson = $encoded;
        }

        // Validate group_by
        $groupBy = null;
        if (!empty($input['group_by'])) {
            $gb = strtolower(trim((string)$input['group_by']));
            if (!in_array($gb, self::VALID_GROUP_BY, true)) {
                return 'SAVED_VIEW_INVALID_GROUP_BY';
            }
            $groupBy = $gb;
        }

        // Validate order_by
        $orderBy = null;
        if (!empty($input['order_by'])) {
            $ob = strtolower(trim((string)$input['order_by']));
            if (!in_array($ob, self::VALID_ORDER_BY, true)) {
                return 'SAVED_VIEW_INVALID_ORDER_BY';
            }
            $orderBy = $ob;
        }

        // Validate order_dir
        $orderDir = null;
        if (!empty($input['order_dir'])) {
            $od = strtolower(trim((string)$input['order_dir']));
            if (!in_array($od, self::VALID_ORDER_DIRS, true)) {
                return 'SAVED_VIEW_INVALID_ORDER_DIR';
            }
            $orderDir = $od;
        }

        $payload = [
            'user_id' => (int)($actor['id'] ?? 0),
            'entity_type' => $entityType,
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'filters' => $filtersJson,
            'access_level' => $accessLevel,
            'display_filters' => $displayFiltersJson,
            'display_properties' => $displayPropertiesJson,
            'rich_filters' => $richFiltersJson,
            'layout' => $layout,
            'group_by' => $groupBy,
            'order_by' => $orderBy,
            'order_dir' => $orderDir,
            'is_locked' => $isLocked ? 1 : 0,
            'is_system' => $isSystem ? 1 : 0,
            'sort_order' => (int)($input['sort_order'] ?? 65535),
        ];

        $item = $this->views->create($payload);

        return $this->normalizeItem($item);
    }

    /**
     * Update a saved view (v2).
     *
     * @return array|string|null
     */
    public function update(string $publicId, array $input, array $actor): array|string|null
    {
        $existing = $this->views->findByPublicId($publicId);
        if (!$existing) {
            return null;
        }

        // Check permissions
        $accessCheck = $this->checkEditAccess($existing, $actor);
        if ($accessCheck !== true) {
            return $accessCheck;
        }

        // Row version conflict
        if (array_key_exists('row_version', $input)) {
            $currentVersion = (int)($existing['row_version'] ?? 0);
            $inputVersion = (int)$input['row_version'];
            if ($inputVersion !== $currentVersion) {
                return 'ROW_VERSION_CONFLICT';
            }
        }

        $set = [];
        $isRoot = (bool)($actor['is_root'] ?? false);

        // Allowed fields for update
        if (array_key_exists('title', $input)) {
            $title = trim((string)$input['title']);
            if ($title === '') {
                return 'SAVED_VIEW_TITLE_REQUIRED';
            }
            if (mb_strlen($title) > self::MAX_TITLE_LENGTH) {
                return 'SAVED_VIEW_TITLE_TOO_LONG';
            }
            $set['title'] = $title;
        }

        if (array_key_exists('description', $input)) {
            $desc = trim((string)$input['description']);
            if (mb_strlen($desc) > self::MAX_DESCRIPTION_LENGTH) {
                return 'SAVED_VIEW_INVALID_DESCRIPTION';
            }
            $set['description'] = $desc !== '' ? $desc : null;
        }

        if (array_key_exists('access_level', $input)) {
            $accessLevel = strtolower(trim((string)$input['access_level']));
            if (!in_array($accessLevel, self::VALID_ACCESS_LEVELS, true)) {
                return 'SAVED_VIEW_INVALID_ACCESS_LEVEL';
            }
            if ($accessLevel === 'system' && !$isRoot) {
                return 'SAVED_VIEW_SYSTEM_ONLY_ROOT';
            }
            $set['access_level'] = $accessLevel;
        }

        if (array_key_exists('layout', $input)) {
            $layout = strtolower(trim((string)$input['layout']));
            if (!in_array($layout, self::VALID_LAYOUTS, true)) {
                return 'SAVED_VIEW_INVALID_LAYOUT';
            }
            $set['layout'] = $layout;
        }

        if (array_key_exists('group_by', $input)) {
            $gb = strtolower(trim((string)$input['group_by']));
            if ($gb !== '' && !in_array($gb, self::VALID_GROUP_BY, true)) {
                return 'SAVED_VIEW_INVALID_GROUP_BY';
            }
            $set['group_by'] = $gb !== '' ? $gb : null;
        }

        if (array_key_exists('order_by', $input)) {
            $ob = strtolower(trim((string)$input['order_by']));
            if ($ob !== '' && !in_array($ob, self::VALID_ORDER_BY, true)) {
                return 'SAVED_VIEW_INVALID_ORDER_BY';
            }
            $set['order_by'] = $ob !== '' ? $ob : null;
        }

        if (array_key_exists('order_dir', $input)) {
            $od = strtolower(trim((string)$input['order_dir']));
            if ($od !== '' && !in_array($od, self::VALID_ORDER_DIRS, true)) {
                return 'SAVED_VIEW_INVALID_ORDER_DIR';
            }
            $set['order_dir'] = $od !== '' ? $od : null;
        }

        if (array_key_exists('filters', $input)) {
            $json = $this->encodeJsonField($input['filters']);
            if ($json === null || strlen($json) > self::MAX_JSON_SIZE) {
                return 'SAVED_VIEW_INVALID_FILTERS';
            }
            $set['filters'] = $json;
        }

        if (array_key_exists('display_filters', $input)) {
            $json = $this->encodeJsonField($input['display_filters']);
            if ($json === null || strlen($json) > self::MAX_JSON_SIZE) {
                return 'SAVED_VIEW_INVALID_DISPLAY_FILTERS';
            }
            $set['display_filters'] = $json;
        }

        if (array_key_exists('display_properties', $input)) {
            $json = $this->encodeJsonField($input['display_properties']);
            if ($json === null || strlen($json) > self::MAX_JSON_SIZE) {
                return 'SAVED_VIEW_INVALID_DISPLAY_PROPERTIES';
            }
            $set['display_properties'] = $json;
        }

        if (array_key_exists('rich_filters', $input)) {
            $json = $this->encodeJsonField($input['rich_filters']);
            if ($json === null || strlen($json) > self::MAX_JSON_SIZE) {
                return 'SAVED_VIEW_INVALID_RICH_FILTERS';
            }
            $set['rich_filters'] = $json;
        }

        if (array_key_exists('sort_order', $input)) {
            $set['sort_order'] = (int)$input['sort_order'];
        }

        // Only root can change locked/system flags
        if ($isRoot) {
            if (array_key_exists('is_locked', $input)) {
                $set['is_locked'] = (int)((bool)$input['is_locked']);
            }
            if (array_key_exists('is_system', $input)) {
                $set['is_system'] = (int)((bool)$input['is_system']);
            }
        }

        // Track who updated
        $set['updated_by_user_id'] = (int)($actor['id'] ?? 0);

        if ($set !== []) {
            $this->views->updateByPublicId($publicId, $set);
        }

        $item = $this->views->findByPublicId($publicId);

        return $item !== null ? $this->normalizeItem($item) : null;
    }

    /**
     * Archive a saved view (soft delete).
     *
     * @return bool|string
     */
    public function archive(string $publicId, array $actor): bool|string
    {
        $existing = $this->views->findByPublicId($publicId);
        if (!$existing) {
            return false;
        }

        $accessCheck = $this->checkEditAccess($existing, $actor);
        if ($accessCheck !== true) {
            return $accessCheck;
        }

        if ((bool)($existing['is_locked'] ?? false)) {
            $isRoot = (bool)($actor['is_root'] ?? false);
            if (!$isRoot) {
                return 'SAVED_VIEW_LOCKED';
            }
        }

        return $this->views->archiveByPublicId($publicId, gmdate('Y-m-d H:i:s'));
    }

    /**
     * Delete a saved view (physical, backward compatibility).
     *
     * @return bool|string
     */
    public function delete(string $publicId, array $actor): bool|string
    {
        return $this->archive($publicId, $actor);
    }

    /**
     * Duplicate a saved view.
     *
     * @return array|string|null
     */
    public function duplicate(string $publicId, array $input, array $actor): array|string|null
    {
        $existing = $this->views->findByPublicId($publicId);
        if (!$existing) {
            return null;
        }

        // Check read access to the original
        $accessCheck = $this->checkViewAccess($existing, $actor);
        if ($accessCheck !== true) {
            return $accessCheck;
        }

        $title = trim((string)($input['title'] ?? ''));
        if ($title === '') {
            $title = 'Copy: ' . ($existing['title'] ?? '');
        }

        // Validate unique title
        if ($this->views->titleExistsForUser($title, (string)($existing['entity_type'] ?? 'task'), (int)($actor['id'] ?? 0))) {
            return 'SAVED_VIEW_TITLE_ALREADY_EXISTS';
        }

        $payload = [
            'user_id' => (int)($actor['id'] ?? 0),
            'entity_type' => (string)($existing['entity_type'] ?? 'task'),
            'title' => $title,
            'description' => $existing['description'] ?? null,
            'filters' => (string)($existing['filters'] ?? '{}'),
            'access_level' => 'private',
            'display_filters' => (string)($existing['display_filters'] ?? 'null'),
            'display_properties' => (string)($existing['display_properties'] ?? 'null'),
            'rich_filters' => (string)($existing['rich_filters'] ?? 'null'),
            'layout' => (string)($existing['layout'] ?? 'list'),
            'group_by' => $existing['group_by'] ?? null,
            'order_by' => $existing['order_by'] ?? null,
            'order_dir' => $existing['order_dir'] ?? null,
            'is_locked' => 0,
            'is_system' => 0,
            'sort_order' => 65535,
        ];

        $item = $this->views->create($payload);

        return $this->normalizeItem($item);
    }

    /**
     * Pin/unpin a saved view for a user.
     *
     * @return array|string|null
     */
    public function pin(string $publicId, array $input, array $actor): array|string|null
    {
        $existing = $this->views->findByPublicId($publicId);
        if (!$existing) {
            return null;
        }

        // Check read access
        $accessCheck = $this->checkViewAccess($existing, $actor);
        if ($accessCheck !== true) {
            return $accessCheck;
        }

        $savedViewId = (int)($existing['id'] ?? 0);
        $userId = (int)($actor['id'] ?? 0);

        $pref = $this->views->createOrUpdateUserPreference($savedViewId, $userId, [
            'is_pinned' => (int)((bool)($input['is_pinned'] ?? false)),
            'sort_order' => (int)($input['sort_order'] ?? 65535),
        ]);

        return $pref;
    }

    /**
     * Touch last_used_at for a saved view.
     *
     * @return bool|string
     */
    public function touchLastUsed(string $publicId, array $actor): bool|string
    {
        $existing = $this->views->findByPublicId($publicId);
        if (!$existing) {
            return false;
        }

        $accessCheck = $this->checkViewAccess($existing, $actor);
        if ($accessCheck !== true) {
            return $accessCheck;
        }

        $this->views->touchLastUsed(
            (int)($existing['id'] ?? 0),
            (int)($actor['id'] ?? 0),
            gmdate('Y-m-d H:i:s')
        );

        return true;
    }

    /**
     * Build task list filters from a saved view.
     *
     * @return array
     */
    public function buildTaskListFilters(array $view, array $runtimeOverrides = []): array
    {
        $filters = [];

        // Extract basic filters
        $viewFilters = $this->decodeJsonField((string)($view['filters'] ?? '{}'));
        if (is_array($viewFilters)) {
            $whitelist = ['search', 'status', 'priority', 'project_public_id', 'client_public_id',
                          'team_public_id', 'assignee_user_public_id', 'tag_public_id',
                          'due_at', 'due_at_from', 'due_at_to', 'updated_since', 'archived'];
            foreach ($whitelist as $key) {
                if (array_key_exists($key, $viewFilters)) {
                    $filters[$key] = $viewFilters[$key];
                }
            }
        }

        // Map order_by/sort
        if (!empty($view['order_by'])) {
            $filters['sort'] = (string)$view['order_by'];
        }
        if (!empty($view['order_dir'])) {
            $filters['order'] = strtoupper((string)$view['order_dir']);
        }

        // Runtime overrides take precedence
        foreach ($runtimeOverrides as $key => $value) {
            $filters[$key] = $value;
        }

        return $filters;
    }

    /**
     * Get task filters for a saved view (API helper).
     *
     * @return array|string|null
     */
    public function getTaskFilters(string $publicId, array $actor): array|string|null
    {
        $item = $this->views->findByPublicId($publicId);
        if (!$item) {
            return null;
        }

        $accessCheck = $this->checkViewAccess($item, $actor);
        if ($accessCheck !== true) {
            return $accessCheck;
        }

        $filters = $this->buildTaskListFilters($item);

        return [
            'filters' => $filters,
            'layout' => $item['layout'] ?? 'list',
            'group_by' => $item['group_by'] ?? null,
            'display_properties' => $this->decodeJsonField((string)($item['display_properties'] ?? 'null')),
        ];
    }

    /**
     * Check if an actor can view a saved view.
     *
     * @return true|string
     */
    private function checkViewAccess(array $item, array $actor): true|string
    {
        $isRoot = (bool)($actor['is_root'] ?? false);
        $userId = (int)($actor['id'] ?? 0);
        $ownerId = (int)($item['user_id'] ?? 0);
        $accessLevel = (string)($item['access_level'] ?? 'private');
        $isArchived = $item['archived_at'] !== null;

        if ($isRoot) {
            return true;
        }

        // Private: only owner
        if ($accessLevel === 'private' && $userId !== $ownerId) {
            return 'SAVED_VIEW_FORBIDDEN';
        }

        // Public/system: all authenticated users can view
        if (in_array($accessLevel, ['public', 'system'], true)) {
            return true;
        }

        return 'SAVED_VIEW_FORBIDDEN';
    }

    /**
     * Check if an actor can edit a saved view.
     *
     * @return true|string
     */
    private function checkEditAccess(array $item, array $actor): true|string
    {
        $isRoot = (bool)($actor['is_root'] ?? false);
        $userId = (int)($actor['id'] ?? 0);
        $ownerId = (int)($item['user_id'] ?? 0);
        $isLocked = (bool)($item['is_locked'] ?? false);

        if ($isRoot) {
            return true;
        }

        // Locked/system: only root
        if ($isLocked || (bool)($item['is_system'] ?? false)) {
            return 'SAVED_VIEW_LOCKED';
        }

        // Private: only owner
        $accessLevel = (string)($item['access_level'] ?? 'private');
        if ($accessLevel === 'private' && $userId !== $ownerId) {
            return 'SAVED_VIEW_FORBIDDEN';
        }

        // Public: owner or root
        if ($accessLevel === 'public' && $userId !== $ownerId) {
            return 'SAVED_VIEW_FORBIDDEN';
        }

        return true;
    }

    /**
     * Normalize a saved view item for API response.
     */
    private function normalizeItem(array $item): array
    {
        $item['filters'] = $this->decodeJsonField((string)($item['filters'] ?? '{}'));
        $item['display_filters'] = $this->decodeJsonField((string)($item['display_filters'] ?? 'null'));
        $item['display_properties'] = $this->decodeJsonField((string)($item['display_properties'] ?? 'null'));
        $item['rich_filters'] = $this->decodeJsonField((string)($item['rich_filters'] ?? 'null'));

        return $item;
    }

    /**
     * Decode a JSON field safely.
     */
    private function decodeJsonField(string $raw): mixed
    {
        if ($raw === '' || $raw === 'null') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $raw;
    }

    /**
     * Encode a value to JSON safely.
     *
     * @return string|null
     */
    private function encodeJsonField(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return null;
        }

        return $json;
    }


}
