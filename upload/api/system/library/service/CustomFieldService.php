<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Custom_field\CustomFieldRepository;
use Api\System\Library\Support\Ulid;

final class CustomFieldService
{
    /**
     * Per entity_type ownership/access checkers. Each entry is
     * callable(string $entityPublicId, array $actor): ?array, mirroring the
     * corresponding domain service's get() method (returns null when the
     * actor's organization/access does not include that entity).
     *
     * @var array<string, callable(string, array): ?array>
     */
    private readonly array $entityAccessors;

    /**
     * @param array<string, callable(string, array): ?array> $entityAccessors
     */
    public function __construct(
        private readonly CustomFieldRepository $fields,
        array $entityAccessors = []
    ) {
        $this->entityAccessors = $entityAccessors;
    }

    /**
     * Verify the actor actually has access to the given entity before its
     * custom field values are read or written. Returns false when the
     * entity_type has no registered accessor (unsupported/unknown type) or
     * when the accessor cannot resolve the entity for this actor (wrong
     * organization, deleted, or otherwise inaccessible).
     */
    private function actorCanAccessEntity(string $entityType, string $entityPublicId, array $actor): bool
    {
        $accessor = $this->entityAccessors[$entityType] ?? null;
        if ($accessor === null) {
            return false;
        }

        return $accessor($entityPublicId, $actor) !== null;
    }

    public function list(array $filters): array
    {
        [$items, $total, $page, $limit] = $this->fields->list($filters);
        $items = array_map([$this, 'normalizeField'], $items);

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

    public function create(array $input): array|string
    {
        $scope = trim((string)$input['scope']);
        $code = trim((string)$input['code']);
        if ($this->fields->findByScopeCode($scope, $code)) {
            return 'FIELD_CODE_EXISTS';
        }

        $now = gmdate('Y-m-d H:i:s');
        $publicId = Ulid::generate('cfd');
        $this->fields->create([
            'public_id' => $publicId,
            'scope' => $scope,
            'code' => $code,
            'title' => trim((string)$input['title']),
            'type' => trim((string)$input['type']),
            'options' => json_encode((array)($input['options'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_required' => isset($input['is_required']) && (int)$input['is_required'] === 1 ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->get($publicId) ?? ['public_id' => $publicId];
    }

    public function get(string $publicId): ?array
    {
        $item = $this->fields->findByPublicId($publicId);
        if (!$item) {
            return null;
        }

        return $this->normalizeField($item);
    }

    public function update(string $publicId, array $input): array|string|null
    {
        $existing = $this->fields->findByPublicId($publicId);
        if (!$existing) {
            return null;
        }

        $set = ['updated_at' => gmdate('Y-m-d H:i:s')];
        if (array_key_exists('scope', $input)) {
            $set['scope'] = trim((string)$input['scope']);
        }
        if (array_key_exists('code', $input)) {
            $newCode = trim((string)$input['code']);
            $scope = (string)($set['scope'] ?? $existing['scope']);
            $exists = $this->fields->findByScopeCode($scope, $newCode);
            if ($exists && (string)($exists['public_id'] ?? '') !== $publicId) {
                return 'FIELD_CODE_EXISTS';
            }
            $set['code'] = $newCode;
        }
        if (array_key_exists('title', $input)) {
            $set['title'] = trim((string)$input['title']);
        }
        if (array_key_exists('type', $input)) {
            $set['type'] = trim((string)$input['type']);
        }
        if (array_key_exists('options', $input)) {
            $set['options'] = json_encode((array)$input['options'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (array_key_exists('is_required', $input)) {
            $set['is_required'] = ((int)$input['is_required'] === 1) ? 1 : 0;
        }

        $this->fields->updateByPublicId($publicId, $set);
        return $this->get($publicId);
    }

    public function delete(string $publicId): bool
    {
        $this->fields->deleteValuesByFieldPublicId($publicId);
        return $this->fields->deleteByPublicId($publicId);
    }

    /**
     * @param array<string,mixed> $actor
     * @return array|string 'ENTITY_NOT_FOUND' when the actor has no access to
     *                       the target entity, otherwise the list of values.
     */
    public function values(string $entityType, string $entityPublicId, array $actor): array|string
    {
        if (!$this->actorCanAccessEntity($entityType, $entityPublicId, $actor)) {
            return 'ENTITY_NOT_FOUND';
        }

        return $this->valuesUnchecked($entityType, $entityPublicId);
    }

    /**
     * Internal helper: fetch values without re-checking entity access. Used
     * once access has already been verified by values()/setValues().
     */
    private function valuesUnchecked(string $entityType, string $entityPublicId): array
    {
        $rows = $this->fields->valuesByEntity($entityType, $entityPublicId);
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'public_id' => (string)($row['public_id'] ?? ''),
                'entity_type' => (string)($row['entity_type'] ?? ''),
                'entity_public_id' => (string)($row['entity_public_id'] ?? ''),
                'field' => [
                    'public_id' => (string)($row['field_public_id'] ?? ''),
                    'scope' => (string)($row['scope'] ?? ''),
                    'code' => (string)($row['code'] ?? ''),
                    'title' => (string)($row['title'] ?? ''),
                    'type' => (string)($row['type'] ?? ''),
                ],
                'value' => $this->decodeValue((string)($row['value'] ?? '')),
                'created_at' => (string)($row['created_at'] ?? ''),
                'updated_at' => (string)($row['updated_at'] ?? ''),
            ];
        }

        return $items;
    }

    /**
     * @param array<string,mixed> $actor
     */
    public function setValues(string $entityType, string $entityPublicId, array $values, array $actor): array|string
    {
        if (!$this->actorCanAccessEntity($entityType, $entityPublicId, $actor)) {
            return 'ENTITY_NOT_FOUND';
        }

        $upserted = [];
        $now = gmdate('Y-m-d H:i:s');
        foreach ($values as $fieldPublicId => $value) {
            $field = $this->fields->findByPublicId((string)$fieldPublicId);
            if (!$field) {
                return 'FIELD_NOT_FOUND';
            }

            $validationError = $this->validateFieldValue($field, $value);
            if ($validationError !== null) {
                return $validationError;
            }

            $encodedValue = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $existing = $this->fields->valueByFieldEntity(
                (int)$field['id'],
                $entityType,
                $entityPublicId
            );

            if ($existing) {
                $this->fields->updateValueById((int)$existing['id'], [
                    'value' => $encodedValue,
                    'updated_at' => $now,
                ]);
                $upserted[] = (string)($existing['public_id'] ?? '');
                continue;
            }

            $publicId = Ulid::generate('cfv');
            $this->fields->createValue([
                'public_id' => $publicId,
                'field_id' => (int)$field['id'],
                'entity_type' => $entityType,
                'entity_public_id' => $entityPublicId,
                'value' => $encodedValue,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $upserted[] = $publicId;
        }

        return [
            'upserted' => $upserted,
            'items' => $this->valuesUnchecked($entityType, $entityPublicId),
        ];
    }

    private function validateFieldValue(array $field, mixed $value): ?string
    {
        $type = (string)($field['type'] ?? 'text');
        $options = [];
        if (!empty($field['options']) && is_string($field['options'])) {
            $options = json_decode($field['options'], true) ?: [];
        }

        return match ($type) {
            'number' => (is_numeric($value) || $value === null) ? null : 'FIELD_VALUE_NOT_NUMERIC',
            'boolean' => (is_bool($value) || in_array($value, [0, 1, '0', '1', null], true)) ? null : 'FIELD_VALUE_NOT_BOOLEAN',
            'date' => ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$value) === 1) ? null : 'FIELD_VALUE_INVALID_DATE',
            'datetime' => ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', (string)$value) === 1) ? null : 'FIELD_VALUE_INVALID_DATETIME',
            'url' => ($value === null || filter_var((string)$value, FILTER_VALIDATE_URL) !== false) ? null : 'FIELD_VALUE_INVALID_URL',
            'email' => ($value === null || filter_var((string)$value, FILTER_VALIDATE_EMAIL) !== false) ? null : 'FIELD_VALUE_INVALID_EMAIL',
            'select' => ($value === null || ($options !== [] && in_array($value, $options, true))) ? null : 'FIELD_VALUE_INVALID_OPTION',
            'multiselect' => ($value === null || !is_array($value) || ($options !== [] && !array_diff($value, $options))) ? null : 'FIELD_VALUE_INVALID_OPTIONS',
            default => null,
        };
    }

    /** @param array<string,mixed> $item */
    private function normalizeField(array $item): array
    {
        $item['is_required'] = (int)($item['is_required'] ?? 0) === 1;
        $options = [];
        if (isset($item['options']) && is_string($item['options']) && $item['options'] !== '') {
            $decoded = json_decode($item['options'], true);
            if (is_array($decoded)) {
                $options = $decoded;
            }
        }
        $item['options'] = $options;

        return $item;
    }

    private function decodeValue(string $value): mixed
    {
        if ($value === '') {
            return null;
        }
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $value;
    }
}
