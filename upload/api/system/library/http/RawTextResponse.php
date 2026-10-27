<?php
declare(strict_types=1);

namespace Api\System\Library\Http;

/** Exact plain-text response for protocol handshakes (for example Meta webhooks). */
final class RawTextResponse
{
    public function __construct(
        private readonly string $payload,
        private readonly int $status = 200
    ) {
    }

    public function status(): int
    {
        return $this->status;
    }

    public function payload(): string
    {
        return $this->payload;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            header('Content-Type: text/plain; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
        }
        echo $this->payload;
    }
}
