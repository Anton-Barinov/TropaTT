<?php
declare(strict_types=1);

namespace Updater\Package;

final class FileHasher
{
    public function sha256(string $file): string
    {
        return hash_file('sha256', $file) ?: '';
    }
}
