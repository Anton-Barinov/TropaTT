<?php
declare(strict_types=1);

namespace Updater\Http;

final class JsonResponse
{
    public function __construct(public readonly array $payload, public readonly int $status = 200)
    {
    }

    public static function success(array $data = [], int $status = 200): self
    {
        return new self(['success' => true, 'data' => $data, 'meta' => ['timestamp' => gmdate('c')]], $status);
    }

    public static function error(string $code, string $message, int $status = 400): self
    {
        return new self(['success' => false, 'code' => $code, 'message' => $message, 'meta' => ['timestamp' => gmdate('c')]], $status);
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
