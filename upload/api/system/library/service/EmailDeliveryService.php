<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\System\Library\Logger\JsonLogger;

final class EmailDeliveryService
{
    private mixed $transport;
    private mixed $fallbackTransport;

    public function __construct(
        private readonly array $config,
        private readonly JsonLogger $logger,
        ?callable $transport = null,
        ?callable $fallbackTransport = null
    ) {
        $this->transport = $transport;
        $this->fallbackTransport = $fallbackTransport;
    }

    public function isEnabled(): bool
    {
        return ($this->config['enabled'] ?? false) === true
            && filter_var((string)($this->config['from'] ?? ''), FILTER_VALIDATE_EMAIL) !== false;
    }

    public function isConfigured(): bool
    {
        return $this->isEnabled()
            && strtolower((string)($this->config['provider'] ?? '')) === 'smtp'
            && trim((string)($this->config['host'] ?? '')) !== ''
            && trim((string)($this->config['username'] ?? '')) !== ''
            && trim((string)($this->config['password'] ?? '')) !== ''
            && in_array(strtolower((string)($this->config['encryption'] ?? '')), ['tls', 'ssl'], true);
    }

    public function send(string $recipient, string $subject, string $body): bool
    {
        $recipient = trim($recipient);
        $subject = trim($subject);
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false || $subject === '' || str_contains($subject, "\r") || str_contains($subject, "\n")) {
            return false;
        }

        if (!$this->isEnabled() && !is_callable($this->transport)) {
            $this->logger->warning('Email delivery is not configured', [
                'event_type' => 'email_delivery_unavailable',
                'recipient_hash' => hash('sha256', strtolower($recipient)),
            ]);
            return false;
        }

        $message = $this->buildMessage($recipient, $subject, $body);
        $sent = false;
        if (is_callable($this->transport)) {
            try {
                $sent = (bool)($this->transport)($this->config, $recipient, $message);
            } catch (\Throwable) {
                $sent = false;
            }
        } elseif ($this->isConfigured()) {
            try {
                $sent = $this->sendSmtp($recipient, $message);
            } catch (\Throwable) {
                $sent = false;
            }
        }

        if (!$sent) {
            $sent = $this->sendPhpMail($recipient, $subject, $body);
        }

        if (!$sent) {
            $this->logger->warning('Email delivery failed', [
                'event_type' => 'email_delivery_failed',
                'recipient_hash' => hash('sha256', strtolower($recipient)),
            ]);
        }

        return $sent;
    }

    private function sendPhpMail(string $recipient, string $subject, string $body): bool
    {
        if (!$this->isEnabled() || !function_exists('mail')) {
            return false;
        }

        $from = (string)$this->config['from'];
        $fromName = trim((string)($this->config['from_name'] ?? 'TropaTT'));
        $encodedName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers = implode("\r\n", [
            'From: ' . $encodedName . ' <' . $from . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ]);
        $normalizedBody = str_replace(["\r\n", "\r"], "\n", $body);
        $encodedBody = chunk_split(base64_encode(str_replace("\n", "\r\n", $normalizedBody)), 76, "\r\n");

        try {
            return is_callable($this->fallbackTransport)
                ? (bool)($this->fallbackTransport)($recipient, $encodedSubject, $headers, rtrim($encodedBody, "\r\n"))
                : @mail($recipient, $encodedSubject, rtrim($encodedBody, "\r\n"), $headers);
        } catch (\Throwable) {
            return false;
        }
    }

    private function buildMessage(string $recipient, string $subject, string $body): string
    {
        $from = (string)$this->config['from'];
        $fromName = trim((string)($this->config['from_name'] ?? 'TropaTT'));
        $domain = substr(strrchr($from, '@') ?: '@localhost', 1);
        $messageId = '<' . bin2hex(random_bytes(16)) . '@' . preg_replace('/[^a-zA-Z0-9.-]/', '', $domain) . '>';
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $normalizedBody = str_replace(["\r\n", "\r"], "\n", $body);
        $encodedBody = chunk_split(base64_encode(str_replace("\n", "\r\n", $normalizedBody)), 76, "\r\n");

        return implode("\r\n", [
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'Message-ID: ' . $messageId,
            'From: ' . $encodedName . ' <' . $from . '>',
            'To: <' . $recipient . '>',
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            rtrim($encodedBody, "\r\n"),
        ]);
    }

    private function sendSmtp(string $recipient, string $message): bool
    {
        $host = trim((string)$this->config['host']);
        $port = max(1, min(65535, (int)($this->config['port'] ?? 587)));
        $encryption = strtolower((string)$this->config['encryption']);
        $timeout = max(2, min(30, (int)($this->config['timeout_sec'] ?? 10)));
        $context = stream_context_create(['ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $host,
            'SNI_enabled' => true,
        ]]);
        $target = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $stream = @stream_socket_client($target, $errno, $error, $timeout, STREAM_CLIENT_CONNECT, $context);
        if (!is_resource($stream)) {
            return false;
        }

        stream_set_timeout($stream, $timeout);
        try {
            if (!$this->expect($stream, [220])) return false;
            $domain = substr(strrchr((string)$this->config['from'], '@') ?: '@localhost', 1);
            if (!$this->command($stream, 'EHLO ' . preg_replace('/[^a-zA-Z0-9.-]/', '', $domain), [250])) return false;
            if ($encryption === 'tls') {
                if (!$this->command($stream, 'STARTTLS', [220])) return false;
                $crypto = @stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($crypto !== true) return false;
                if (!$this->command($stream, 'EHLO ' . preg_replace('/[^a-zA-Z0-9.-]/', '', $domain), [250])) return false;
            }

            $auth = base64_encode("\0" . (string)$this->config['username'] . "\0" . (string)$this->config['password']);
            if (!$this->command($stream, 'AUTH PLAIN ' . $auth, [235])) return false;
            if (!$this->command($stream, 'MAIL FROM:<' . (string)$this->config['from'] . '>', [250])) return false;
            if (!$this->command($stream, 'RCPT TO:<' . $recipient . '>', [250, 251])) return false;
            if (!$this->command($stream, 'DATA', [354])) return false;

            $data = preg_replace('/(?m)^\./', '..', $message) ?? $message;
            if (@fwrite($stream, $data . "\r\n.\r\n") === false || !$this->expect($stream, [250])) return false;

            $this->command($stream, 'QUIT', [221]);
            return true;
        } finally {
            fclose($stream);
        }
    }

    private function command($stream, string $command, array $expected): bool
    {
        if (@fwrite($stream, $command . "\r\n") === false) {
            return false;
        }
        return $this->expect($stream, $expected);
    }

    private function expect($stream, array $expected): bool
    {
        $code = null;
        do {
            $line = @fgets($stream, 2048);
            if (!is_string($line) || $line === '') {
                return false;
            }
            if (preg_match('/^(\d{3})([ -])/', $line, $matches) !== 1) {
                return false;
            }
            $code = (int)$matches[1];
        } while ($matches[2] === '-');

        return in_array($code, $expected, true);
    }
}
