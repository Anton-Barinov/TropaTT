<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

/**
 * Parses and validates the outbound proxy URL of an AI provider connection.
 *
 * The value is stored in `ai_providers.provider_payload.proxy_url` and applied to
 * every outbound provider request (models list, connection test, completions).
 * Only ai.admin can set it, but it still travels through logs and API responses,
 * so it is parsed strictly and normalized before use: credentials are split into
 * CURLOPT_PROXYUSERPWD and never end up in the CURLOPT_PROXY string.
 */
final class AiProxyUrl
{
    public const MAX_LENGTH = 512;
    public const CODE_INVALID = 'AI_PROVIDER_PROXY_URL_INVALID';
    public const CODE_SCHEME_NOT_ALLOWED = 'AI_PROVIDER_PROXY_URL_SCHEME_NOT_ALLOWED';

    /** @var list<string> */
    public const ALLOWED_SCHEMES = ['http', 'https', 'socks5', 'socks5h'];

    /**
     * @return array{ok:bool,code?:string,proxy?:string,userpwd?:string}
     */
    public static function parse(string $url): array
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return ['ok' => false, 'code' => self::CODE_INVALID];
        }

        $raw = trim($url);
        if ($raw === '') {
            return ['ok' => true, 'proxy' => '', 'userpwd' => ''];
        }
        if (strlen($raw) > self::MAX_LENGTH) {
            return ['ok' => false, 'code' => self::CODE_INVALID];
        }

        $parts = parse_url($raw);
        if (!is_array($parts)) {
            return ['ok' => false, 'code' => self::CODE_INVALID];
        }

        $scheme = strtolower(trim((string)($parts['scheme'] ?? '')));
        if ($scheme === '') {
            return ['ok' => false, 'code' => self::CODE_INVALID];
        }
        if (!in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            return ['ok' => false, 'code' => self::CODE_SCHEME_NOT_ALLOWED];
        }

        $host = trim((string)($parts['host'] ?? ''));
        if ($host === '') {
            return ['ok' => false, 'code' => self::CODE_INVALID];
        }
        if (trim((string)($parts['path'] ?? ''), '/') !== '') {
            return ['ok' => false, 'code' => self::CODE_INVALID];
        }
        if (isset($parts['query']) || isset($parts['fragment'])) {
            return ['ok' => false, 'code' => self::CODE_INVALID];
        }

        $port = isset($parts['port']) ? (int)$parts['port'] : 0;
        if ($port < 0 || $port > 65535) {
            return ['ok' => false, 'code' => self::CODE_INVALID];
        }

        $user = rawurldecode((string)($parts['user'] ?? ''));
        $pass = rawurldecode((string)($parts['pass'] ?? ''));

        return [
            'ok' => true,
            'proxy' => $scheme . '://' . self::formatHost($host) . ($port > 0 ? ':' . $port : ''),
            'userpwd' => $user !== '' ? $user . ':' . $pass : '',
        ];
    }

    /**
     * curl option map for CURLOPT_PROXY / CURLOPT_PROXYUSERPWD.
     *
     * @return array{proxy?:string,userpwd?:string}
     */
    public static function curlOptions(string $url): array
    {
        $parsed = self::parse($url);
        if (!(bool)($parsed['ok'] ?? false) || trim((string)($parsed['proxy'] ?? '')) === '') {
            return [];
        }

        $options = ['proxy' => (string)$parsed['proxy']];
        if (trim((string)($parsed['userpwd'] ?? '')) !== '') {
            $options['userpwd'] = (string)$parsed['userpwd'];
        }

        return $options;
    }

    /**
     * Applies the parsed proxy of a provider payload to an open curl handle.
     *
     * @param mixed $ch CurlHandle|resource
     * @param array<string,mixed> $payload
     */
    public static function applyToCurl(mixed $ch, array $payload): void
    {
        if (!function_exists('curl_setopt')) {
            return;
        }
        $url = trim((string)($payload['proxy_url'] ?? ''));
        if ($url === '') {
            return;
        }
        $options = self::curlOptions($url);
        if (($options['proxy'] ?? '') !== '') {
            curl_setopt($ch, CURLOPT_PROXY, (string)$options['proxy']);
        }
        if (($options['userpwd'] ?? '') !== '') {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, (string)$options['userpwd']);
        }
    }

    /**
     * Logs and API payloads must never carry proxy credentials.
     */
    public static function redact(string $url): string
    {
        $parsed = self::parse($url);
        if (!(bool)($parsed['ok'] ?? false)) {
            return '***';
        }

        return (string)$parsed['proxy'];
    }

    private static function formatHost(string $host): string
    {
        if (str_contains($host, ':') && !str_starts_with($host, '[')) {
            return '[' . $host . ']';
        }

        return $host;
    }
}
