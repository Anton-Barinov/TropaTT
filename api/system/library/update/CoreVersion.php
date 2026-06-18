<?php
declare(strict_types=1);

namespace Api\System\Library\Update;

final class CoreVersion
{
    public function __construct(private readonly string $storageDir, private readonly string $basePath)
    {
    }

    public function current(): array
    {
        $file = $this->storageDir . '/installed-core.json';
        if (is_file($file)) {
            $data = json_decode((string)file_get_contents($file), true);
            if (is_array($data)) {
                return array_merge(['state' => 'installed'], $data);
            }
        }
        return [
            'state' => 'unknown_local_core',
            'product' => 'tropatt-core',
            'core_version' => $this->fallbackVersion(),
            'core_build' => null,
            'source_sha' => null,
            'adopted' => false,
        ];
    }

    private function fallbackVersion(): string
    {
        $versionFile = $this->basePath . '/VERSION';
        return is_file($versionFile) ? trim((string)file_get_contents($versionFile)) : '0.1.0';
    }
}
