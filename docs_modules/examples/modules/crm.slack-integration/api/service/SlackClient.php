<?php
declare(strict_types=1);

namespace Module\Crm\SlackIntegration\Service;

use RuntimeException;

final class SlackClient
{
    private int $timeout;

    public function __construct(int $timeout = 10)
    {
        $this->timeout = $timeout;
    }

    /**
     * Validate that a webhook URL is an official Slack incoming webhook.
     *
     * @param array<int, string> $allowedHosts
     */
    public static function validateWebhookUrl(string $url, array $allowedHosts): bool
    {
        $url = trim($url);
        $parts = parse_url($url);
        if ($url === '' || !is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https') {
            return false;
        }
        $host = strtolower(rtrim((string)($parts['host'] ?? ''), '.'));
        $path = (string)($parts['path'] ?? '');
        if ($host === '' || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || (isset($parts['port']) && (int)$parts['port'] !== 443)) {
            return false;
        }
        foreach ($allowedHosts as $allowed) {
            $allowed = strtolower(rtrim(trim($allowed), '.'));
            if ($allowed !== '' && hash_equals($allowed, $host)
                && (str_starts_with($path, '/services/') || str_starts_with($path, '/workflows/'))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Send a Slack message to an incoming webhook.
     *
     * @param array<string, mixed> $message
     * @return array{success: bool, response_code: int, message: string}
     */
    public function send(string $webhookUrl, array $message): array
    {
        if (!self::validateWebhookUrl($webhookUrl, ['hooks.slack.com'])) {
            return ['success' => false, 'response_code' => 0, 'message' => 'Invalid Slack webhook URL'];
        }
        $body = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return ['success' => false, 'response_code' => 0, 'message' => 'Failed to encode message'];
        }

        $responseCode = 0;
        if (function_exists('curl_init')) {
            $ch = curl_init($webhookUrl);
            if ($ch !== false) {
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $body,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_TIMEOUT => $this->timeout,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                ]);
                curl_exec($ch);
                $responseCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => $body,
                    'timeout' => $this->timeout,
                    'ignore_errors' => true,
                    'follow_location' => 0,
                    'max_redirects' => 0,
                ],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            @file_get_contents($webhookUrl, false, $context);
            $responseCode = 0;
        }

        if ($responseCode >= 200 && $responseCode < 300) {
            return ['success' => true, 'response_code' => $responseCode, 'message' => 'Sent'];
        }

        return ['success' => false, 'response_code' => $responseCode, 'message' => 'Slack rejected the message (HTTP ' . $responseCode . ')'];
    }
}
