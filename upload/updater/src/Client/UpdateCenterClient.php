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
        return $this->getJson($this->url('/api/v1/products/' . rawurlencode((string)$this->config['product']) . $this->queryString()));
    }

    public function channel(): array
    {
        return $this->getJson($this->url('/api/v1/products/' . rawurlencode((string)$this->config['product']) . '/channels/' . rawurlencode((string)$this->config['channel']) . $this->queryString()));
    }

    public function updatePlan(string $currentBuild): array
    {
        return $this->getJson($this->url('/api/v1/products/' . rawurlencode((string)$this->config['product']) . '/update-plan' . $this->queryString([
            'current_build' => $currentBuild,
            'channel' => (string)$this->config['channel'],
        ])));
    }

    public function changes(?string $from, string $to): array
    {
        return $this->getJson($this->url('/api/v1/products/' . rawurlencode((string)$this->config['product']) . '/changes' . $this->queryString([
            'from' => (string)$from,
            'to' => (string)$to,
        ])));
    }

    /**
     * Serializes the given params plus the real installation domain.
     *
     * The update center routes requests to the develop stream only for test
     * domains (*.tropatt.com) and to the main stream for every other domain.
     * Without an explicit installation_domain it falls back to the HTTP Host
     * header of the request — which for server-side HTTP clients is the update
     * center's own host (update.tropatt.com, itself a *.tropatt.com name), so
     * production installs would be served from the develop stream. Sending the
     * real site domain keeps every call on the stream the installation belongs to.
     */
    private function queryString(array $extra = []): string
    {
        $params = array_merge($extra, $this->installationParams());
        return $params !== [] ? '?' . http_build_query($params) : '';
    }

    private function installationParams(): array
    {
        $domain = $this->currentDomain();
        return $domain !== '' ? ['installation_domain' => $domain] : [];
    }

    private function currentDomain(): string
    {
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
        if ($host === '') {
            $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? ''));
            if ($origin !== '') {
                $host = (string)(parse_url($origin, PHP_URL_HOST) ?: '');
            }
        }
        $host = strtolower($host);
        if (str_starts_with($host, '[')) {
            $end = strpos($host, ']');
            return $end === false ? '' : substr($host, 1, $end - 1);
        }
        if (str_contains($host, ':')) {
            $host = explode(':', $host, 2)[0];
        }
        return preg_match('/^[a-z0-9.-]+$/', $host) === 1 ? trim($host, '.') : '';
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
