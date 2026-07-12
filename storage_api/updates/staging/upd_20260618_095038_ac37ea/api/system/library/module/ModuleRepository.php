<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleRepository
{
    private string $repoUrl;

    /** @var array<string, array{versions: array<int, string>, info: array<string, mixed>}> */
    private array $cache = [];

    public function __construct(string $repoUrl = '')
    {
        $this->repoUrl = $repoUrl;
    }

    /** @return array<int, array{name: string, version: string, title: string}> */
    public function search(string $query, array $filters = []): array
    {
        return [];
    }

    /** @return array<string, mixed>|null */
    public function getInfo(string $name): ?array
    {
        return $this->cache[$name]['info'] ?? null;
    }

    public function download(string $name, string $version): string
    {
        $url = rtrim($this->repoUrl, '/') . '/' . $name . '-' . $version . '.zip';
        $tmp = sys_get_temp_dir() . '/crm_mod_' . bin2hex(random_bytes(4)) . '.zip';

        $content = file_get_contents($url);
        if ($content === false) {
            throw new \RuntimeException("Cannot download module: {$name} v{$version}");
        }

        file_put_contents($tmp, $content);
        return $tmp;
    }

    /** @return array<int, array{name: string, current: string, latest: string}> */
    public function checkUpdates(): array
    {
        return [];
    }

    public function publish(string $packagePath): bool
    {
        if ($this->repoUrl === '') {
            throw new \RuntimeException("Repository URL not configured");
        }
        return true;
    }
}
