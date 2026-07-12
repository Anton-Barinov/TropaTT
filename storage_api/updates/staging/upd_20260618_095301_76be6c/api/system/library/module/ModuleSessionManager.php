<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleSessionManager
{
    private string $sessionDir;

    /** @var array<string, array<string, mixed>> */
    private array $sessions = [];

    public function __construct(string $storageBase)
    {
        $this->sessionDir = rtrim($storageBase, '/') . '/sessions/modules';
        if (!is_dir($this->sessionDir)) {
            @mkdir($this->sessionDir, 0755, true);
        }
    }

    public function set(string $moduleName, string $key, mixed $value): void
    {
        if (!isset($this->sessions[$moduleName])) {
            $this->sessions[$moduleName] = $this->loadSession($moduleName);
        }
        $this->sessions[$moduleName][$key] = $value;
        $this->saveSession($moduleName);
    }

    public function get(string $moduleName, string $key, mixed $default = null): mixed
    {
        if (!isset($this->sessions[$moduleName])) {
            $this->sessions[$moduleName] = $this->loadSession($moduleName);
        }
        return $this->sessions[$moduleName][$key] ?? $default;
    }

    public function clear(string $moduleName): void
    {
        unset($this->sessions[$moduleName]);
        $file = $this->sessionDir . '/' . $moduleName . '.json';
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /** @return array<string, mixed> */
    private function loadSession(string $moduleName): array
    {
        $file = $this->sessionDir . '/' . $moduleName . '.json';
        if (!is_file($file)) {
            return [];
        }

        $content = file_get_contents($file);
        if ($content === false || $content === '') {
            return [];
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    private function saveSession(string $moduleName): void
    {
        $file = $this->sessionDir . '/' . $moduleName . '.json';
        file_put_contents($file, json_encode(
            $this->sessions[$moduleName] ?? [],
            JSON_UNESCAPED_UNICODE
        ), LOCK_EX);
    }
}
