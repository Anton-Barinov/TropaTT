<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Ai\AiJsonSchemaRepository;
use Api\System\Library\Logger\JsonLogger;

final class AiJsonSchemaService
{
    public function __construct(
        private readonly AiJsonSchemaRepository $schemas,
        private readonly JsonLogger $logger
    ) {
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{items:array<int,array<string,mixed>>}
     */
    public function list(array $filters): array
    {
        $items = $this->schemas->list($filters);
        return ['items' => array_map(fn(array $item): array => $this->normalizeSchema($item), $items)];
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array{ok:bool,code?:string,schema?:array<string,mixed>}
     */
    public function create(array $input, array $actor): array
    {
        $intentCode = trim((string)($input['intent_code'] ?? ''));
        $schemaVersion = trim((string)($input['schema_version'] ?? 'v1'));
        $schemaInput = $input['schema_json'] ?? null;
        $isActive = $this->toBool($input['is_active'] ?? true);

        if ($intentCode === '' || $schemaInput === null) {
            return ['ok' => false, 'code' => 'AI_SCHEMA_VALIDATION_FAILED'];
        }
        if (mb_strlen($intentCode) > 128 || mb_strlen($schemaVersion) > 32) {
            return ['ok' => false, 'code' => 'AI_SCHEMA_VALIDATION_FAILED'];
        }

        $schema = $this->normalizeSchemaInput($schemaInput);
        if (!$this->isSchemaDefinitionValid($schema)) {
            return ['ok' => false, 'code' => 'AI_SCHEMA_VALIDATION_FAILED'];
        }

        $encoded = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || strlen($encoded) > 64000) {
            return ['ok' => false, 'code' => 'AI_SCHEMA_VALIDATION_FAILED'];
        }

        if ($isActive) {
            $this->schemas->deactivateForIntent($intentCode);
        }

        $now = gmdate('Y-m-d H:i:s');
        $actorId = (int)($actor['id'] ?? 0);
        $publicId = $this->schemas->create([
            'intent_code' => $intentCode,
            'schema_version' => $schemaVersion,
            'schema_json' => $encoded,
            'is_active' => $isActive ? 1 : 0,
            'created_by_user_id' => $actorId > 0 ? $actorId : null,
            'updated_by_user_id' => $actorId > 0 ? $actorId : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $row = $this->schemas->findByPublicId($publicId);
        if (!$row) {
            return ['ok' => false, 'code' => 'AI_SCHEMA_CREATE_FAILED'];
        }

        $this->logger->audit([
            'action' => 'ai_json_schema_created',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'ai_json_schema',
            'entity_public_id' => $publicId,
            'intent_code' => $intentCode,
            'meta' => ['schema_version' => $schemaVersion, 'is_active' => $isActive],
        ]);

        return ['ok' => true, 'schema' => $this->normalizeSchema($row)];
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array{ok:bool,code?:string,schema?:array<string,mixed>}
     */
    public function update(string $publicId, array $input, array $actor): array
    {
        $row = $this->schemas->findByPublicId($publicId);
        if (!$row) {
            return ['ok' => false, 'code' => 'AI_SCHEMA_NOT_FOUND'];
        }

        $set = [];
        if (array_key_exists('schema_version', $input)) {
            $schemaVersion = trim((string)$input['schema_version']);
            if ($schemaVersion === '' || mb_strlen($schemaVersion) > 32) {
                return ['ok' => false, 'code' => 'AI_SCHEMA_VALIDATION_FAILED'];
            }
            $set['schema_version'] = $schemaVersion;
        }
        if (array_key_exists('schema_json', $input)) {
            $schema = $this->normalizeSchemaInput($input['schema_json']);
            if (!$this->isSchemaDefinitionValid($schema)) {
                return ['ok' => false, 'code' => 'AI_SCHEMA_VALIDATION_FAILED'];
            }
            $encoded = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($encoded) || strlen($encoded) > 64000) {
                return ['ok' => false, 'code' => 'AI_SCHEMA_VALIDATION_FAILED'];
            }
            $set['schema_json'] = $encoded;
        }
        if (array_key_exists('is_active', $input)) {
            $isActive = $this->toBool($input['is_active']);
            $set['is_active'] = $isActive ? 1 : 0;
            if ($isActive) {
                $this->schemas->deactivateForIntent((string)$row['intent_code']);
            }
        }

        if ($set === []) {
            return ['ok' => false, 'code' => 'AI_SCHEMA_NO_CHANGES'];
        }

        $actorId = (int)($actor['id'] ?? 0);
        $set['updated_at'] = gmdate('Y-m-d H:i:s');
        $set['updated_by_user_id'] = $actorId > 0 ? $actorId : null;
        $this->schemas->updateByPublicId($publicId, $set);
        $updated = $this->schemas->findByPublicId($publicId);
        if (!$updated) {
            return ['ok' => false, 'code' => 'AI_SCHEMA_UPDATE_FAILED'];
        }

        $this->logger->audit([
            'action' => 'ai_json_schema_updated',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'ai_json_schema',
            'entity_public_id' => $publicId,
            'intent_code' => (string)($updated['intent_code'] ?? ''),
        ]);

        return ['ok' => true, 'schema' => $this->normalizeSchema($updated)];
    }

    /** @return array<string,mixed>|null */
    public function resolveActive(string $intentCode): ?array
    {
        $row = $this->schemas->findActiveForIntent($intentCode);
        return $row ? $this->normalizeSchema($row) : null;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{ok:bool,code?:string}
     */
    public function validatePayloadBySchema(string $intentCode, array $payload): array
    {
        $schema = $this->resolveActive($intentCode);
        if (!$schema || !is_array($schema['schema_json'] ?? null)) {
            return ['ok' => true];
        }

        $definition = (array)$schema['schema_json'];
        if (!$this->isSchemaDefinitionValid($definition)) {
            return ['ok' => false, 'code' => 'AI_SCHEMA_VALIDATION_FAILED'];
        }

        return $this->validateByDefinition($payload, $definition)
            ? ['ok' => true]
            : ['ok' => false, 'code' => 'AI_SCHEMA_VALIDATION_FAILED'];
    }

    /** @param array<string,mixed> $payload @param array<string,mixed> $definition */
    private function validateByDefinition(array $payload, array $definition): bool
    {
        $required = is_array($definition['required'] ?? null) ? $definition['required'] : [];
        foreach ($required as $requiredKey) {
            $name = trim((string)$requiredKey);
            if ($name === '' || !array_key_exists($name, $payload)) {
                return false;
            }
        }

        $properties = is_array($definition['properties'] ?? null) ? $definition['properties'] : [];
        foreach ($properties as $name => $rule) {
            if (!is_string($name) || !array_key_exists($name, $payload) || !is_array($rule)) {
                continue;
            }
            $type = strtolower(trim((string)($rule['type'] ?? '')));
            if ($type === '') {
                continue;
            }
            if (!$this->matchesType($payload[$name], $type)) {
                return false;
            }
        }

        return true;
    }

    private function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'array' => is_array($value) && array_is_list($value),
            'object' => is_array($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'null' => $value === null,
            default => true,
        };
    }

    /** @param array<string,mixed> $definition */
    private function isSchemaDefinitionValid(array $definition): bool
    {
        $type = strtolower(trim((string)($definition['type'] ?? '')));
        if ($type !== '' && $type !== 'object') {
            return false;
        }

        $required = $definition['required'] ?? [];
        if ($required !== [] && !is_array($required)) {
            return false;
        }
        $properties = $definition['properties'] ?? [];
        if ($properties !== [] && !is_array($properties)) {
            return false;
        }

        foreach ((array)$properties as $name => $rule) {
            if (!is_string($name) || !is_array($rule)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function normalizeSchema(array $row): array
    {
        $decoded = [];
        $raw = (string)($row['schema_json'] ?? '');
        if ($raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $decoded = $json;
            }
        }

        return [
            'public_id' => (string)($row['public_id'] ?? ''),
            'intent_code' => (string)($row['intent_code'] ?? ''),
            'schema_version' => (string)($row['schema_version'] ?? ''),
            'schema_json' => $decoded,
            'is_active' => (bool)($row['is_active'] ?? false),
            'created_at' => (string)($row['created_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ];
    }

    /** @return array<string,mixed> */
    private function normalizeSchemaInput(mixed $schemaInput): array
    {
        if (is_array($schemaInput)) {
            return $schemaInput;
        }
        $raw = trim((string)$schemaInput);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}
