<?php
declare(strict_types=1);

namespace Updater\Http;

final class Request
{
    public static function json(): array
    {
        $json = json_decode((string)file_get_contents('php://input'), true);
        return is_array($json) ? $json : [];
    }
}
