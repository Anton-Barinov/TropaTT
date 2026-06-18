<?php
declare(strict_types=1);

namespace Api\System\Library\Update;

final class CoreUpdateConfig
{
    public static function load(): array
    {
        return require dirname(__DIR__, 3) . '/config/update.php';
    }
}
