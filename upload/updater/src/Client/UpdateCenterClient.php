<?php
declare(strict_types=1);

namespace Updater\Client;

use Updater\Util\HttpClient;

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
        $result = HttpClient::request($url, [
            'timeout' => (int)($this->config['timeouts']['check'] ?? 10),
        ]);
        if (($result['ok'] ?? false) !== true) {
            $error = (string)($result['error'] ?? '');
            throw new \RuntimeException('Unable to reach update center: ' . $url . ($error !== '' ? ' (' . $error . ')' : ''));
        }
        $status = (int)($result['status'] ?? 0);
        if ($status >= 400) {
            throw new \RuntimeException('Update center returned HTTP ' . $status . ' for ' . $url);
        }
        $body = $result['body'] ?? false;
        $data = is_string($body) ? json_decode($body, true) : null;
        if (!is_array($data)) {
            throw new \RuntimeException('Update center returned invalid JSON for ' . $url);
        }
        return $data;
    }

    private function url(string $path): string
    {
        return rtrim((string)$this->config['update_center_url'], '/') . $path;
    }
}
