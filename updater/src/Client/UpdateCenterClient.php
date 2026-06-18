<?php
declare(strict_types=1);

namespace Updater\Client;

final class UpdateCenterClient
{
    public function __construct(private readonly array $config)
    {
    }

    public function health(): array
    {
        return $this->getJson($this->url('/api/v1/health'));
    }

    public function product(): array
    {
        return $this->getJson($this->url('/api/v1/products/' . rawurlencode((string)$this->config['product'])));
    }

    public function channel(): array
    {
        return $this->getJson($this->url('/api/v1/products/' . rawurlencode((string)$this->config['product']) . '/channels/' . rawurlencode((string)$this->config['channel'])));
    }

    public function updatePlan(string $currentBuild): array
    {
        return $this->getJson($this->url('/api/v1/products/' . rawurlencode((string)$this->config['product']) . '/update-plan?current_build=' . rawurlencode($currentBuild) . '&channel=' . rawurlencode((string)$this->config['channel'])));
    }

    public function changes(?string $from, string $to): array
    {
        return $this->getJson($this->url('/api/v1/products/' . rawurlencode((string)$this->config['product']) . '/changes?from=' . rawurlencode((string)$from) . '&to=' . rawurlencode($to)));
    }

    public function getJson(string $url): array
    {
        $context = stream_context_create(['http' => ['timeout' => (int)($this->config['timeouts']['check'] ?? 10), 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new \RuntimeException('Unable to reach update center: ' . $url);
        }
        $status = $this->statusCode($http_response_header ?? []);
        if ($status >= 400) {
            throw new \RuntimeException('Update center returned HTTP ' . $status . ' for ' . $url);
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Update center returned invalid JSON for ' . $url);
        }
        return $data;
    }

    private function url(string $path): string
    {
        return rtrim((string)$this->config['update_center_url'], '/') . $path;
    }

    private function statusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string)$header, $m)) {
                return (int)$m[1];
            }
        }
        return 200;
    }
}
