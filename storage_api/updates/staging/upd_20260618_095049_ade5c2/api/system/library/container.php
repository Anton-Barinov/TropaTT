<?php
declare(strict_types=1);

namespace Api\System\Library;

use RuntimeException;

final class Container
{
    /** @var array<string,mixed> */
    private array $entries = [];

    /** @var array<string,callable(self):mixed> */
    private array $factories = [];

    public function set(string $id, mixed $value): void
    {
        $this->entries[$id] = $value;
    }

    /** @param callable(self):mixed $factory */
    public function factory(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->entries)) {
            return $this->entries[$id];
        }

        if (array_key_exists($id, $this->factories)) {
            $this->entries[$id] = ($this->factories[$id])($this);
            return $this->entries[$id];
        }

        throw new RuntimeException('Container entry not found: ' . $id);
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries) || array_key_exists($id, $this->factories);
    }
}
