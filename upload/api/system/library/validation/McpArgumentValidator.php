<?php
declare(strict_types=1);

namespace Api\System\Library\Validation;

final class McpArgumentValidator
{
    private int $remaining = 10000;
    private bool $exhausted = false;
    private bool $invalidSchema = false;

    public function validate(mixed $value, array $schema, bool $nativeArrays = false): ?string
    {
        $this->remaining = 10000;
        $this->exhausted = false;
        $this->invalidSchema = false;
        $pending = [[$value, 0]];
        $nodes = 0;
        $scheduled = 1;
        while ($pending !== []) {
            [$item, $depth] = array_pop($pending);
            if (++$nodes > 10000 || $depth > 64) {
                return 'Arguments exceed the supported complexity limit.';
            }
            if ((is_float($item) && !is_finite($item))
                || (is_string($item) && !mb_check_encoding($item, 'UTF-8'))
                || is_resource($item) || (is_object($item) && !$item instanceof \stdClass)) {
                return 'Arguments contain a value that cannot be represented as JSON.';
            }
            if (is_array($item) || $item instanceof \stdClass) {
                foreach ($item as $child) {
                    if (++$scheduled > 10000) {
                        return 'Arguments exceed the supported complexity limit.';
                    }
                    $pending[] = [$child, $depth + 1];
                }
            }
        }
        try {
            $error = $this->check($value, $schema, $nativeArrays, 'arguments', 0);
        } catch (\TypeError|\ValueError) {
            $this->invalidSchema = true;
            $error = null;
        }
        if ($this->invalidSchema) {
            return 'Tool input schema contains unsupported or invalid assertions.';
        }
        return $this->exhausted ? 'Arguments exceed the supported complexity limit.' : $error;
    }

    private function check(mixed $value, array|bool $schema, bool $nativeArrays, string $path, int $depth): ?string
    {
        if (--$this->remaining < 0 || $depth > 64) {
            $this->exhausted = true;
            return 'Arguments exceed the supported complexity limit.';
        }
        if (is_bool($schema)) {
            return $schema ? null : $path . ': value is not allowed.';
        }
        foreach (['multipleOf', 'uniqueItems', 'prefixItems', 'contains', 'patternProperties', 'propertyNames', 'dependentRequired', 'dependentSchemas', 'unevaluatedProperties', 'unevaluatedItems', 'minProperties', 'maxProperties'] as $unsupported) {
            if (array_key_exists($unsupported, $schema)) {
                $this->invalidSchema = true;
                return $path . ': schema assertion is not supported.';
            }
        }
        if (isset($schema['$ref']) || isset($schema['$dynamicRef'])) {
            $this->invalidSchema = true;
            return $path . ': schema references are not supported.';
        }
        if (is_float($value) && !is_finite($value)) {
            return $path . ': number must be finite.';
        }
        if (array_key_exists('type', $schema)) {
            $matches = false;
            if ((!is_string($schema['type']) && !is_array($schema['type'])) || $schema['type'] === []) {
                $this->invalidSchema = true;
                return $path . ': invalid schema type.';
            }
            foreach ((array)$schema['type'] as $type) {
                if (!is_string($type) || !in_array($type, ['string', 'integer', 'number', 'boolean', 'null', 'array', 'object'], true)) {
                    $this->invalidSchema = true;
                    return $path . ': invalid schema type.';
                }
                $matches = $matches || $this->matchesType($value, (string)$type, $nativeArrays);
            }
            if (!$matches) {
                return $path . ': invalid value type.';
            }
        }
        if (array_key_exists('enum', $schema)) {
            $matches = false;
            foreach ((array)$schema['enum'] as $choice) {
                $matches = $matches || $this->equal($value, $choice);
            }
            if (!$matches) {
                return $path . ': value is outside the allowed choices.';
            }
        }
        if (array_key_exists('const', $schema) && !$this->equal($value, $schema['const'])) {
            return $path . ': value does not match the required constant.';
        }
        foreach ((array)($schema['allOf'] ?? []) as $rule) {
            if (($error = $this->check($value, $rule, $nativeArrays, $path, $depth + 1)) !== null) {
                return $error;
            }
        }
        foreach (['anyOf', 'oneOf'] as $combination) {
            if (!isset($schema[$combination])) {
                continue;
            }
            $matches = 0;
            foreach ((array)$schema[$combination] as $rule) {
                if ($this->check($value, $rule, $nativeArrays, $path, $depth + 1) === null) {
                    $matches++;
                }
            }
            if ($matches === 0 || ($combination === 'oneOf' && $matches !== 1)) {
                return $path . ': value does not match the schema alternatives.';
            }
        }
        if (isset($schema['not']) && $this->check($value, $schema['not'], $nativeArrays, $path, $depth + 1) === null) {
            return $path . ': value is excluded by the schema.';
        }
        if (isset($schema['if'])) {
            $branch = $this->check($value, $schema['if'], $nativeArrays, $path, $depth + 1) === null ? 'then' : 'else';
            if (isset($schema[$branch]) && ($error = $this->check($value, $schema[$branch], $nativeArrays, $path, $depth + 1)) !== null) {
                return $error;
            }
        }
        if (is_string($value)) {
            $length = mb_strlen($value, 'UTF-8');
            if ((isset($schema['minLength']) && $length < $schema['minLength'])
                || (isset($schema['maxLength']) && $length > $schema['maxLength'])) {
                return $path . ': string length is outside the allowed range.';
            }
            if (isset($schema['pattern'])) {
                $pattern = (string)$schema['pattern'];
                $match = strlen($pattern) > 2048 ? false : @preg_match('~(*LIMIT_MATCH=100000)(*LIMIT_RECURSION=100)' . str_replace('~', '\\~', $pattern) . '~u', $value);
                if ($match === false) {
                    $this->invalidSchema = true;
                }
                if ($match !== 1) {
                    return $path . ': string does not match the required pattern.';
                }
            }
        }
        if (is_int($value) || is_float($value)) {
            if ((isset($schema['minimum']) && $value < $schema['minimum'])
                || (isset($schema['maximum']) && $value > $schema['maximum'])
                || (isset($schema['exclusiveMinimum']) && $value <= $schema['exclusiveMinimum'])
                || (isset($schema['exclusiveMaximum']) && $value >= $schema['exclusiveMaximum'])) {
                return $path . ': number is outside the allowed range.';
            }
        }
        if (is_array($value) && array_is_list($value)) {
            $count = count($value);
            if ((isset($schema['minItems']) && $count < $schema['minItems'])
                || (isset($schema['maxItems']) && $count > $schema['maxItems'])) {
                return $path . ': array length is outside the allowed range.';
            }
            if (isset($schema['items'])) {
                foreach ($value as $index => $item) {
                    if (($error = $this->check($item, $schema['items'], $nativeArrays, $path . '[' . $index . ']', $depth + 1)) !== null) {
                        return $error;
                    }
                }
            }
        }
        $isObject = $value instanceof \stdClass || ($nativeArrays && is_array($value) && ($value === [] || !array_is_list($value)));
        if ($isObject) {
            $values = (array)$value;
            $properties = (array)($schema['properties'] ?? []);
            foreach ((array)($schema['required'] ?? []) as $required) {
                if (!array_key_exists($required, $values)) {
                    return $path . ': a required argument is missing.';
                }
            }
            foreach ($values as $key => $item) {
                if (array_key_exists($key, $properties)) {
                    $rule = $properties[$key];
                    $itemPath = $path . '.' . (preg_match('/^[a-zA-Z0-9_]{1,96}$/D', (string)$key) === 1 ? $key : 'field');
                } else {
                    $rule = $schema['additionalProperties'] ?? true;
                    $itemPath = $path . '.field';
                    if ($rule === false) {
                        return $path . ': an undeclared argument was supplied.';
                    }
                }
                if (($error = $this->check($item, $rule, $nativeArrays, $itemPath, $depth + 1)) !== null) {
                    return $error;
                }
            }
        }
        return null;
    }

    private function matchesType(mixed $value, string $type, bool $nativeArrays): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'integer' => is_int($value) || (is_float($value) && is_finite($value) && floor($value) === $value && $value >= PHP_INT_MIN && $value < PHP_INT_MAX),
            'number' => (is_int($value) || is_float($value)) && is_finite((float)$value),
            'boolean' => is_bool($value),
            'null' => $value === null,
            'array' => is_array($value) && array_is_list($value),
            'object' => $value instanceof \stdClass || ($nativeArrays && is_array($value) && ($value === [] || !array_is_list($value))),
            default => false,
        };
    }

    private function equal(mixed $left, mixed $right): bool
    {
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            return $left == $right;
        }
        if ($left instanceof \stdClass && $right instanceof \stdClass) {
            $left = (array)$left;
            $right = (array)$right;
        }
        if (is_array($left) && is_array($right)) {
            if (count($left) !== count($right) || array_is_list($left) !== array_is_list($right)) {
                return false;
            }
            foreach ($left as $key => $value) {
                if (!array_key_exists($key, $right) || !$this->equal($value, $right[$key])) {
                    return false;
                }
            }
            return true;
        }
        return $left === $right;
    }
}
