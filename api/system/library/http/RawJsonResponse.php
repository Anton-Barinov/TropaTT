<?php
declare(strict_types=1);

namespace Api\System\Library\Http;

final class RawJsonResponse
{
    /** @param array<string,string> $headers */
    public function __construct(
        private readonly mixed $payload,
        private readonly int $status = 200,
        private readonly array $headers = []
    ) {
    }

    public function status(): int
    {
        return $this->status;
    }

    public function payload(): mixed
    {
        return $this->payload;
    }

    public function send(): void
    {
        if (headers_sent($file, $line)) {
            error_log('RawJsonResponse::send skipped headers in ' . $file . ':' . $line);
            echo json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        if ($this->payload !== null) {
            echo json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
}
