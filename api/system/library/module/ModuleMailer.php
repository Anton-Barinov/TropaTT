<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleMailer
{
    /** @var array<string, int> */
    private array $sendCounts = [];

    /** @var array<string, int> */
    private array $rateLimitReset = [];

    private int $maxPerHour = 100;

    public function send(string $moduleName, string|array $to, string $subject, string $body, array $attachments = []): bool
    {
        if (!$this->checkRateLimit($moduleName)) {
            error_log("[ModuleMailer] Rate limit exceeded for {$moduleName}");
            return false;
        }

        $toAddr = is_array($to) ? implode(', ', $to) : $to;
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'MIME-Version: 1.0',
        ];

        if ($attachments !== []) {
            $boundary = md5(uniqid((string)time()));
            $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
            $body = $this->buildMultipartBody($body, $attachments, $boundary);
        }

        $sent = mail($toAddr, $subject, $body, implode("\r\n", $headers));

        if ($sent) {
            $this->sendCounts[$moduleName] = ($this->sendCounts[$moduleName] ?? 0) + 1;
        }

        return $sent;
    }

    public function sendTemplate(string $moduleName, string|array $to, string $templateName, array $data): bool
    {
        $subject = $data['subject'] ?? 'Notification';
        $body = $this->renderTemplate($templateName, $data);
        return $this->send($moduleName, $to, $subject, $body);
    }

    private function renderTemplate(string $templateName, array $data): string
    {
        // SEC: Use explicit variable creation instead of extract() to prevent variable injection
        // Preserve EXTR_SKIP behavior: don't overwrite existing variables or superglobals
        $skip = ['templateName', 'data', 'key', 'value', 'skip',
            '_GET', '_POST', '_REQUEST', '_SERVER', '_SESSION', '_COOKIE', '_FILES', '_ENV'];
        foreach ($data as $key => $value) {
            if (is_string($key) && !in_array($key, $skip, true) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
                $$key = $value;
            }
        }
        ob_start();
        // Template file should be included here (e.g., include $templateFile)
        $html = ob_get_clean();
        return $html !== false ? $html : '';
    }

    /**
     * @param array<int, array{path: string, name: string}> $attachments
     */
    private function buildMultipartBody(string $htmlBody, array $attachments, string $boundary): string
    {
        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $body .= $htmlBody . "\r\n";

        foreach ($attachments as $attachment) {
            $content = is_file($attachment['path']) ? file_get_contents($attachment['path']) : '';
            if ($content === false) {
                continue;
            }
            $encoded = chunk_split(base64_encode($content));
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: application/octet-stream; name=\"{$attachment['name']}\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "Content-Disposition: attachment; filename=\"{$attachment['name']}\"\r\n\r\n";
            $body .= $encoded . "\r\n";
        }

        $body .= "--{$boundary}--";
        return $body;
    }

    private function checkRateLimit(string $moduleName): bool
    {
        $now = time();
        $resetTime = $this->rateLimitReset[$moduleName] ?? 0;

        if ($now >= $resetTime) {
            $this->sendCounts[$moduleName] = 0;
            $this->rateLimitReset[$moduleName] = $now + 3600;
        }

        return ($this->sendCounts[$moduleName] ?? 0) < $this->maxPerHour;
    }
}
