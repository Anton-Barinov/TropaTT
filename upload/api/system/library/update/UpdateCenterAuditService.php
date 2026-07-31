<?php
declare(strict_types=1);

namespace Api\System\Library\Update;

final class UpdateCenterAuditService
{
    public function __construct(private readonly string $storageDir)
    {
    }

    public function read(): ?array
    {
        $file = $this->storageDir . '/update-center-audit.json';
        return is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    }
}
