<?php
declare(strict_types=1);

namespace Api\System\Library\Support;

final class Ulid
{
    public static function generate(string $prefix = ''): string
    {
        $time = (int)(microtime(true) * 1000);
        $rand = bin2hex(random_bytes(8));
        $id = strtoupper(base_convert((string)$time, 10, 36) . $rand);
        return $prefix !== '' ? $prefix . '_' . $id : $id;
    }
}
