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

    /**
     * The real installation domain as seen by this request, or '' when it
     * cannot be determined. Server-side HTTP clients reach the update center
     * with the center's own host in HTTP_HOST, so callers must never use the
     * bare host header as proof that the domain is a test stand - the update
     * center applies the same rule (unknown domain => main stream).
     */
    public function installationDomain(): string
    {
        return $this->currentDomain();
    }

    /**
     * Whether the domain belongs to the project's test stands.
     *
     * Any *.tropatt.com host (demo, qa, updtest, test-*, ...) is served from
     * the develop stream by the update center; the bare apex tropatt.com does
     * not match and is served from main.
     */
    public static function isTestDomain(string $domain): bool
    {
        $domain = strtolower(trim($domain, '.'));
        return $domain !== '' && str_ends_with($domain, '.tropatt.com');
    }

    /**
     * Stream policy guard: may this installation accept an update whose
     * resolved stream differs from its configured product?
     *
     * A production installation (any non-*.tropatt.com domain, or an unknown
     * domain) is only ever allowed the stream that matches its configured
     * product (e.g. tropatt-core). Test stands on *.tropatt.com are the only
     * place where the develop stream (tropatt-core-dev) is legitimate. This is
     * the client-side counterpart of the update center's DomainRouter and a
     * hard stop that keeps a production installation from ever applying
     * unreviewed develop-stream code, even if the center were misconfigured or
     * a request were routed to the wrong stream.
     */
    /**
     * Return the product stream allowed for this installation domain.
     *
     * The configured CRM product is the public/base product name. The update
     * center may resolve it to the dev product for test stands, but a stale
     * installed-core.json must not change that policy: production always
     * receives main and test stands always receive develop. This also permits
     * the one-time dev-to-main repair for installations bootstrapped from the
     * wrong stream.
     */
    public static function expectedProductForDomain(string $configuredProduct, string $domain): string
    {
        if ($configuredProduct !== 'tropatt-core') {
            return $configuredProduct;
        }
        return self::isTestDomain($domain) ? 'tropatt-core-dev' : 'tropatt-core';
    }

    /**
     * Stream policy guard for a plan returned by the update center.
     *
     * An empty stream is accepted for backwards-compatible centers that do not
     * expose stream metadata; a present stream must match the domain policy.
     */
    public static function isStreamAllowedForDomain(string $configuredProduct, string $stream, string $domain): bool
    {
        return $stream === '' || $stream === self::expectedProductForDomain($configuredProduct, $domain);
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
