<?php
declare(strict_types=1);

namespace Api\System\Library\Http;

final class JsonResponse
{
    /** @param array<string,mixed>|null $data */
    /** @param array<string,mixed>|array<int,mixed> $errors */
    /** @param array<string,mixed> $meta */
    public function __construct(
        private readonly int $status,
        private readonly bool $success,
        private readonly string $code,
        private readonly string $message,
        private readonly array|null $data,
        private readonly array $errors,
        private readonly array $meta
    ) {
    }

    public static function success(
        string $code,
        string $message,
        array $data = [],
        int $status = 200,
        string $requestId = '',
        string $correlationId = '',
        array $meta = []
    ): self {
        return new self(
            status: $status,
            success: true,
            code: $code,
            message: $message,
            data: $data,
            errors: [],
            meta: array_merge([
                'request_id' => $requestId,
                'timestamp' => gmdate('c'),
                'version' => 'v1',
                'correlation_id' => $correlationId ?: $requestId,
            ], $meta)
        );
    }

    public static function error(
        string $code,
        string $message,
        int $status = 400,
        array $errors = [],
        string $requestId = '',
        string $correlationId = '',
        array $meta = []
    ): self {
        return new self(
            status: $status,
            success: false,
            code: $code,
            message: $message,
            data: null,
            errors: self::sanitizeErrors($errors),
            meta: array_merge([
                'request_id' => $requestId,
                'timestamp' => gmdate('c'),
                'version' => 'v1',
                'correlation_id' => $correlationId ?: $requestId,
            ], $meta)
        );
    }

    /** @param array<string,mixed>|array<int,mixed> $errors */
    private static function sanitizeErrors(array $errors): array
    {
        if (array_is_list($errors)) {
            $items = [];
            foreach ($errors as $item) {
                if (is_array($item)) {
                    $items[] = self::sanitizeErrors($item);
                    continue;
                }

                $items[] = is_string($item) ? self::maskString($item) : $item;
            }

            return $items;
        }

        $result = [];
        foreach ($errors as $key => $value) {
            if (is_array($value)) {
                $result[$key] = self::sanitizeErrors($value);
                continue;
            }

            $result[$key] = is_string($value) ? self::maskString($value) : $value;
        }

        return $result;
    }

    private static function maskString(string $value): string
    {
        $masked = $value;
        $patterns = [
            '/(bearer\s+)[A-Za-z0-9\.\-_]+/i' => '$1***',
            '/((?:password|token|secret|api[_-]?key|authorization)\s*[=:]\s*)[^,\s;"]+/i' => '$1***',
            '/("(?:password|token|secret|api[_-]?key|authorization)"\s*:\s*")[^"]*(")/i' => '$1***$2',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $next = preg_replace($pattern, $replacement, $masked);
            if (is_string($next)) {
                $masked = $next;
            }
        }

        return $masked;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function payload(): array
    {
        return [
            'success' => $this->success,
            'code' => $this->code,
            'message' => $this->message,
            'data' => $this->data,
            'errors' => $this->errors,
            'meta' => $this->meta,
        ];
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->payload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
