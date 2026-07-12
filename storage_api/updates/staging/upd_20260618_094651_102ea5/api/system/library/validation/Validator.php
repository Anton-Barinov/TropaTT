<?php
declare(strict_types=1);

namespace Api\System\Library\Validation;

final class Validator
{
    /** @var array<string,array<int,string>> */
    private array $errors = [];

    public function require(array $data, string $field, string $message): self
    {
        $value = $data[$field] ?? null;
        if ($value === null || (is_string($value) && trim($value) === '')) {
            $this->errors[$field][] = $message;
        }

        return $this;
    }

    public function maxLen(array $data, string $field, int $max, string $message): self
    {
        $value = $data[$field] ?? null;
        if (is_string($value) && mb_strlen($value) > $max) {
            $this->errors[$field][] = $message;
        }

        return $this;
    }

    public function enum(array $data, string $field, array $allowed, string $message): self
    {
        $value = $data[$field] ?? null;
        if ($value !== null && $value !== '' && !in_array((string)$value, $allowed, true)) {
            $this->errors[$field][] = $message;
        }

        return $this;
    }

    public function date(array $data, string $field, string $message): self
    {
        $value = $data[$field] ?? null;
        if ($value === null || $value === '') {
            return $this;
        }

        if (strtotime((string)$value) === false) {
            $this->errors[$field][] = $message;
        }

        return $this;
    }

    public function addError(string $field, string $message): self
    {
        $this->errors[$field][] = $message;

        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string,array<int,string>> */
    public function errors(): array
    {
        return $this->errors;
    }
}
