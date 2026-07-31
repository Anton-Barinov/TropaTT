<?php
declare(strict_types=1);

namespace Updater\Http;

final class JsonResponse
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly array $payload,
        public readonly int $status = 200,
        public readonly array $headers = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public static function success(array $data = [], int $status = 200, array $headers = []): self
    {
        return new self(['success' => true, 'data' => $data, 'meta' => ['timestamp' => gmdate('c')]], $status, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function error(string $code, string $message, int $status = 400, array $headers = []): self
    {
        return new self(['success' => false, 'code' => $code, 'message' => $message, 'meta' => ['timestamp' => gmdate('c')]], $status, $headers);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
