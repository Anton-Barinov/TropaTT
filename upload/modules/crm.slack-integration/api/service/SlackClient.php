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
        if (!preg_match('#^https://#i', $url)) {
            return false;
        }
        $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));
        if ($host === '') {
            return false;
        }
        foreach ($allowedHosts as $allowed) {
            $allowed = strtolower(trim($allowed));
            if ($allowed === '') {
                continue;
            }
            if ($host === $allowed || str_ends_with($host, '.' . ltrim($allowed, '.'))) {
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
