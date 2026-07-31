<?php
declare(strict_types=1);

namespace Updater\Http;

final class Response
{
    public static function text(string $body, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        echo $body;
    }
}
