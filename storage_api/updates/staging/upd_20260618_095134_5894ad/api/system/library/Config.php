<?php
declare(strict_types=1);

namespace Api\System\Library;

final class Config
{
    /** @var array<string,mixed> */
    private array $data = [];

    public function load(string $file, string $namespace): void
    {
        if (!is_file($file)) {
            return;
        }

        $config = require $file;
        if (is_array($config)) {
            $this->data[$namespace] = $config;
        }
    }

    public function merge(string $namespace, array $data): void
    {
        $current = $this->data[$namespace] ?? [];
        $this->data[$namespace] = array_replace_recursive($current, $data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $current = $this->data;
        foreach ($parts as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return $default;
            }
            $current = $current[$part];
        }
        return $current;
    }

    public function all(): array
    {
        return $this->data;
    }
}
