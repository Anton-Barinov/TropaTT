<?php
declare(strict_types=1);

namespace Api\System\Library\Update;

final class CoreUpdateClient
{
    public function __construct(private readonly array $config)
    {
    }

    public function health(): array
    {
        return $this->get('/api/v1/health');
    }

    public function product(): array
    {
        return $this->get('/api/v1/products/' . rawurlencode((string)$this->config['product']));
    }

    public function channel(): array
    {
        return $this->get('/api/v1/products/' . rawurlencode((string)$this->config['product']) . '/channels/' . rawurlencode((string)$this->config['channel']));
    }

    public function plan(string $currentBuild): array
    {
        return $this->get('/api/v1/products/' . rawurlencode((string)$this->config['product']) . '/update-plan?current_build=' . rawurlencode($currentBuild) . '&channel=' . rawurlencode((string)$this->config['channel']));
    }

    public function changes(?string $from, string $to): array
    {
        return $this->get('/api/v1/products/' . rawurlencode((string)$this->config['product']) . '/changes?from=' . rawurlencode((string)$from) . '&to=' . rawurlencode($to));
    }

    public function getUrl(string $url): array
    {
        return $this->request($url);
    }

    private function get(string $path): array
    {
        return $this->request(rtrim((string)$this->config['update_center_url'], '/') . $path);
    }

    private function request(string $url): array
    {
        $body = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => (int)($this->config['timeouts']['check'] ?? 10), 'ignore_errors' => true]]));
        if ($body === false) {
            return ['ok' => false, 'error' => 'request_failed', 'url' => $url];
        }
        $status = 200;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string)$header, $m)) {
                $status = (int)$m[1];
            }
        }
        $json = json_decode($body, true);
        return ['ok' => $status < 400 && is_array($json), 'status' => $status, 'url' => $url, 'data' => is_array($json) ? $json : null];
    }
}
