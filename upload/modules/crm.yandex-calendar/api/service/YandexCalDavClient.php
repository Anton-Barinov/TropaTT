<?php
declare(strict_types=1);

namespace Module\Crm\YandexCalendar\Service;

use RuntimeException;

final class YandexCalDavClient
{
    private const ROOT = 'https://caldav.yandex.ru';

    /** @param array<string,mixed> $config */
    public function __construct(private readonly array $config = [])
    {
    }

    public function discoverCalendars(string $username, string $password): array
    {
        $url = self::ROOT . '/calendars/' . rawurlencode($username) . '/';
        [$status, $body] = $this->request($url, 'PROPFIND', $username, $password, '<?xml version="1.0"?><d:propfind xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav" xmlns:cs="http://calendarserver.org/ns/"><d:prop><d:displayname/><d:resourcetype/><c:calendar-description/><c:calendar-timezone/><cs:getctag/></d:prop></d:propfind>', ['Depth: 1', 'Content-Type: application/xml; charset=utf-8'], true);
        if ($status === 401 || $status === 403) throw new RuntimeException('YANDEX_AUTH_FAILED', $status);
        if ($status < 200 || $status >= 300) throw new RuntimeException('YANDEX_CALDAV_DISCOVERY_FAILED', $status);
        $items = [];
        foreach ($this->responses($body) as $row) {
            if (!$row['is_calendar'] || $row['href'] === $url) continue;
            $items[] = ['href' => $row['href'], 'display_name' => $row['display_name'] ?: basename(rtrim((string)$row['href'], '/')), 'timezone' => $row['timezone'], 'ctag' => $row['ctag']];
        }
        if ($items === []) throw new RuntimeException('YANDEX_CALENDARS_NOT_FOUND');
        return $items;
    }

    /** @return array<int,array<string,mixed>> */
    public function events(string $username, string $password, string $calendarHref, string $from, string $to): array
    {
        $body = '<?xml version="1.0"?><c:calendar-query xmlns:c="urn:ietf:params:xml:ns:caldav" xmlns:d="DAV:"><d:prop><d:getetag/><c:calendar-data/></d:prop><c:filter><c:comp-filter name="VCALENDAR"><c:comp-filter name="VEVENT"><c:time-range start="' . $this->caldavDate($from) . '" end="' . $this->caldavDate($to) . '"/></c:comp-filter></c:comp-filter></c:filter></c:calendar-query>';
        [$status, $response] = $this->request($calendarHref, 'REPORT', $username, $password, $body, ['Depth: 1', 'Content-Type: application/xml; charset=utf-8'], true);
        if ($status === 401 || $status === 403) throw new RuntimeException('YANDEX_AUTH_FAILED', $status);
        if ($status < 200 || $status >= 300) throw new RuntimeException('YANDEX_EVENT_QUERY_FAILED', $status);
        $events = [];
        foreach ($this->responses($response) as $row) {
            if ($row['calendar_data'] !== '') $events[] = ['href' => $row['href'], 'etag' => $row['etag'], 'ics' => $row['calendar_data']];
        }
        return $events;
    }

    public function put(string $username, string $password, string $calendarHref, string $uid, string $ics, ?string $href = null, ?string $etag = null): array
    {
        $eventHref = $href ?: rtrim($calendarHref, '/') . '/' . rawurlencode($uid) . '.ics';
        $headers = ['Content-Type: text/calendar; charset=utf-8'];
        if ($etag !== null && $etag !== '') $headers[] = 'If-Match: ' . $etag;
        else $headers[] = 'If-None-Match: *';
        [$status, $body, $responseHeaders] = $this->request($eventHref, 'PUT', $username, $password, $ics, $headers, true);
        if ($status === 401 || $status === 403) throw new RuntimeException('YANDEX_AUTH_FAILED', $status);
        if ($status === 412) throw new RuntimeException('YANDEX_EVENT_CONFLICT', $status);
        if ($status < 200 || $status >= 300) throw new RuntimeException('YANDEX_EVENT_WRITE_FAILED', $status);
        return ['href' => $eventHref, 'etag' => $responseHeaders['etag'] ?? null];
    }

    public function delete(string $username, string $password, string $href, ?string $etag = null): void
    {
        $headers = [];
        if ($etag !== null && $etag !== '') $headers[] = 'If-Match: ' . $etag;
        [$status] = $this->request($href, 'DELETE', $username, $password, '', $headers, true);
        if ($status === 404 || $status === 410) return;
        if ($status === 401 || $status === 403) throw new RuntimeException('YANDEX_AUTH_FAILED', $status);
        if ($status === 412) throw new RuntimeException('YANDEX_EVENT_CONFLICT', $status);
        if ($status < 200 || $status >= 300) throw new RuntimeException('YANDEX_EVENT_DELETE_FAILED', $status);
    }

    /** @return array{0:int,1:string,2:array<string,string>} */
    private function request(string $url, string $method, string $username, string $password, string $body = '', array $headers = [], bool $retry = false): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('CURL_REQUIRED');
        $this->assertAllowedUrl($url);
        $configuredRetries = getenv('YANDEX_MAX_RETRIES');
        $max = $this->boundedInt(
            $configuredRetries !== false && $configuredRetries !== '' ? $configuredRetries : ($this->config['max_retries'] ?? 4),
            1,
            5,
            4,
        );
        $attempt = 0;
        do {
            $responseHeaders = [];
            $ch = curl_init($url);
            $configuredTimeout = getenv('YANDEX_TIMEOUT_SECONDS');
            $timeout = $this->boundedInt(
                $configuredTimeout !== false && $configuredTimeout !== '' ? $configuredTimeout : ($this->config['request_timeout_seconds'] ?? 30),
                5,
                60,
                30,
            );
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_USERPWD => $username . ':' . $password, CURLOPT_HTTPAUTH => CURLAUTH_BASIC, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => min(10, $timeout), CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$responseHeaders): int { $parts = explode(':', $header, 2); if (count($parts) === 2) $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]); return strlen($header); }]);
            if ($body !== '') curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            $raw = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $error = curl_error($ch); curl_close($ch);
            $retryable = $error !== '' || in_array($status, [429, 500, 502, 503, 504], true);
            if (!$retry || !$retryable || $attempt >= $max - 1) {
                if ($error !== '') throw new RuntimeException('YANDEX_NETWORK_ERROR');
                return [$status, is_string($raw) ? $raw : '', $responseHeaders];
            }
            $retryAfter = $this->retryAfterSeconds($responseHeaders['retry-after'] ?? null);
            usleep((int)(min(60, $retryAfter > 0 ? $retryAfter : (2 ** $attempt)) * 1000000));
            $attempt++;
        } while (true);
    }

    private function caldavDate(string $value): string
    {
        try { return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->format('Ymd\THis\Z'); } catch (\Throwable) { throw new RuntimeException('YANDEX_SYNC_DATE_INVALID'); }
    }

    /** @return array<int,array<string,mixed>> */
    private function responses(string $xml): array
    {
        if (!class_exists('DOMDocument')) throw new RuntimeException('XML_EXTENSION_REQUIRED');
        $doc = new \DOMDocument(); $doc->preserveWhiteSpace = false;
        if (@$doc->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING) === false) throw new RuntimeException('YANDEX_XML_INVALID');
        $xpath = new \DOMXPath($doc); $result = [];
        foreach ($xpath->query('//*[local-name()="response"]') ?: [] as $response) {
            $hrefNode = $xpath->query('./*[local-name()="href"]', $response)->item(0);
            $prop = $xpath->query('.//*[local-name()="propstat"]/*[local-name()="prop"]', $response)->item(0);
            if (!$hrefNode || !$prop) continue;
            $statusNode = $xpath->query('.//*[local-name()="propstat"]/*[local-name()="status"]', $response)->item(0);
            if ($statusNode && preg_match('/\s2\d\d\s/', (string)$statusNode->textContent) !== 1) continue;
            $href = $this->absoluteHref(trim((string)$hrefNode->textContent));
            $isCalendar = $xpath->query('./*[local-name()="resourcetype"]/*[local-name()="calendar"]', $prop)->length > 0;
            $display = $xpath->query('./*[local-name()="displayname"]', $prop)->item(0)?->textContent;
            $timezone = $xpath->query('./*[local-name()="calendar-timezone"]', $prop)->item(0)?->textContent;
            $ctag = $xpath->query('./*[local-name()="getctag"]', $prop)->item(0)?->textContent;
            $etag = $xpath->query('./*[local-name()="getetag"]', $prop)->item(0)?->textContent;
            $data = $xpath->query('./*[local-name()="calendar-data"]', $prop)->item(0)?->textContent;
            $result[] = ['href' => $href, 'is_calendar' => $isCalendar, 'display_name' => trim((string)$display), 'timezone' => trim((string)$timezone), 'ctag' => trim((string)$ctag), 'etag' => trim((string)$etag), 'calendar_data' => (string)$data];
        }
        return $result;
    }

    private function absoluteHref(string $href): string
    {
        $resolved = preg_match('#^https?://#i', $href) === 1 ? $href : self::ROOT . '/' . ltrim($href, '/');
        $this->assertAllowedUrl($resolved);
        return $resolved;
    }

    private function assertAllowedUrl(string $url): void
    {
        $parts = parse_url($url);
        $host = strtolower((string)($parts['host'] ?? ''));
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if ($scheme !== 'https' || $host !== 'caldav.yandex.ru' || (isset($parts['port']) && (int)$parts['port'] !== 443)) {
            throw new RuntimeException('YANDEX_CALDAV_URL_INVALID');
        }
    }

    private function boundedInt(mixed $value, int $min, int $max, int $default): int
    {
        if (!is_numeric($value)) return $default;
        return max($min, min($max, (int)$value));
    }

    private function retryAfterSeconds(?string $value): int
    {
        $value = trim((string)$value);
        if ($value === '') return 0;
        if (ctype_digit($value)) return max(0, min(60, (int)$value));
        $timestamp = strtotime($value);
        return $timestamp === false ? 0 : max(0, min(60, $timestamp - time()));
    }
}
