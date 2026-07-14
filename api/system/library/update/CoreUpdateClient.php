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
        return $this->get('/api/v1/products/' . rawurlencode((string)$this->config['product']) . '/channels/' . rawurlencode((string)$this->config['channel']) . '?' . http_build_query($this->installationParams()));
    }

    public function plan(string $currentBuild): array
    {
        return $this->get('/api/v1/products/' . rawurlencode((string)$this->config['product']) . '/update-plan?' . http_build_query(array_merge([
            'current_build' => $currentBuild,
            'channel' => (string)$this->config['channel'],
        ], $this->installationParams())));
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

    private function request(string $url): array
    {
        $body = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => (int)($this->config['timeouts']['check'] ?? 10), 'ignore_errors' => true]]));
        if ($body === false) {
            $lastError = error_get_last();
            return [
                'ok' => false,
                'status' => 0,
                'error' => 'update_center_unavailable',
                'message' => 'Update center is unavailable',
                'url' => $url,
                'detail' => is_array($lastError) ? (string)($lastError['message'] ?? '') : '',
            ];
        }
        $status = 200;
        // file_get_contents() exposes response headers in this scope on all
        // PHP versions supported by the CRM, including shared-hosting PHP 8.1.
        $responseHeaders = $http_response_header;
        foreach ($responseHeaders as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string)$header, $m)) {
                $status = (int)$m[1];
            }
        }
        $json = json_decode($body, true);
        if (!is_array($json)) {
            return [
                'ok' => false,
                'status' => $status,
                'error' => 'invalid_update_center_response',
                'message' => 'Update center returned an invalid response',
                'url' => $url,
                'data' => null,
            ];
        }
        if ($status >= 400) {
            return [
                'ok' => false,
                'status' => $status,
                'error' => 'update_center_http_error',
                'message' => 'Update center returned HTTP ' . $status,
                'url' => $url,
                'data' => $json,
            ];
        }
        return ['ok' => true, 'status' => $status, 'url' => $url, 'data' => $json];
    }
}
