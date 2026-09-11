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

    /**
     * Drop a cached entry so the next `get()` rebuilds it.
     *
     * A factory result is cached on first use, so an instance built around a
     * resource that later becomes invalid (the PDO handle MySQL closed during a
     * long AI call, see `db.reconnect`) keeps being handed out for the rest of
     * the request. Callers that know a dependency must be rebuilt can evict it
     * here and resolve it again on the live resource.
     */
    public function forget(string $id): void
    {
        unset($this->entries[$id]);
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
