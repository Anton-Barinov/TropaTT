<?php
declare(strict_types=1);

namespace Updater\State;

final class LocalState
{
    public function __construct(private readonly string $storageDir)
    {
    }

    public function read(): array
    {
        return $this->readJson('installed-core.json') ?: ['state' => 'unknown_local_core', 'core_build' => null];
    }

    public function currentBuild(): ?string
    {
        $state = $this->read();
        return isset($state['core_build']) ? (string)$state['core_build'] : null;
    }

    public function readJson(string $file): ?array
    {
        $path = $this->storageDir . '/' . $file;
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string)file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    public function write(array $state): void
    {
        $path = $this->storageDir . '/installed-core.json';
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $state['updated_at'] = gmdate('c');
        file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
