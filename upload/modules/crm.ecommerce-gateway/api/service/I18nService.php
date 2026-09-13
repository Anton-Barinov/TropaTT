<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Service;

/**
 * Localization and internationalization service (E-COM-12 §1, §2).
 * Supports ru-ru and en-gb with Accept-Language header resolution and fallback.
 */
final class I18nService
{
    public const DEFAULT_LOCALE = 'ru-ru';
    public const SUPPORTED_LOCALES = ['ru-ru', 'en-gb'];

    /** @var array<string,array<string,mixed>> */
    private array $catalogs = [];

    public function __construct(private readonly string $defaultLocale = self::DEFAULT_LOCALE)
    {
    }

    /**
     * Resolves the effective locale from Accept-Language header, store config and query params.
     */
    public function resolveLocale(?string $acceptLanguageHeader = null, ?string $storeLocale = null, ?string $explicitLocale = null): string
    {
        if ($explicitLocale !== null && $explicitLocale !== '') {
            $norm = self::normalizeLocale($explicitLocale);
            if (in_array($norm, self::SUPPORTED_LOCALES, true)) {
                return $norm;
            }
        }

        if ($acceptLanguageHeader !== null && trim($acceptLanguageHeader) !== '') {
            $parsed = $this->parseAcceptLanguage($acceptLanguageHeader);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        if ($storeLocale !== null && $storeLocale !== '') {
            $norm = self::normalizeLocale($storeLocale);
            if (in_array($norm, self::SUPPORTED_LOCALES, true)) {
                return $norm;
            }
        }

        return $this->defaultLocale;
    }

    public static function normalizeLocale(string $locale): string
    {
        $val = str_replace('_', '-', strtolower(trim($locale)));
        if (str_starts_with($val, 'ru')) {
            return 'ru-ru';
        }
        if (str_starts_with($val, 'en')) {
            return 'en-gb';
        }

        return in_array($val, self::SUPPORTED_LOCALES, true) ? $val : self::DEFAULT_LOCALE;
    }

    /**
     * Parses RFC 2616 / RFC 7231 Accept-Language header and returns best matching supported locale.
     */
    public function parseAcceptLanguage(string $header): ?string
    {
        $parts = explode(',', $header);
        $candidates = [];

        foreach ($parts as $part) {
            $sub = explode(';', trim($part));
            $lang = strtolower(trim($sub[0]));
            $q = 1.0;
            if (isset($sub[1]) && preg_match('/q=\s*([0-9.]+)/i', $sub[1], $m)) {
                $q = (float)$m[1];
            }
            if ($lang !== '') {
                $candidates[$lang] = $q;
            }
        }

        arsort($candidates);

        foreach ($candidates as $lang => $weight) {
            if (str_starts_with($lang, 'ru')) {
                return 'ru-ru';
            }
            if (str_starts_with($lang, 'en')) {
                return 'en-gb';
            }
        }

        return null;
    }

    /**
     * Translates a dotted key (e.g. "error.INGESTION_PAYLOAD_INVALID").
     *
     * @param array<string,string|int|float> $replace
     */
    public function t(string $key, array $replace = [], ?string $locale = null): string
    {
        $loc = $this->resolveLocale(null, null, $locale);
        $catalog = $this->loadCatalog($loc);

        $value = $catalog;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                // Try fallback locale
                if ($loc !== self::DEFAULT_LOCALE) {
                    return $this->t($key, $replace, self::DEFAULT_LOCALE);
                }
                return $key;
            }
            $value = $value[$segment];
        }

        if (!is_string($value)) {
            return $key;
        }

        foreach ($replace as $k => $v) {
            $value = str_replace('{' . $k . '}', (string)$v, $value);
        }

        return $value;
    }

    public function errorMessage(string $code, ?string $locale = null): string
    {
        $translated = $this->t('error.' . $code, [], $locale);
        if ($translated !== 'error.' . $code) {
            return $translated;
        }

        return $this->defaultErrorMessage($code);
    }

    public function statusLabel(string $status, ?string $locale = null): string
    {
        return $this->t('status.' . $status, [], $locale);
    }

    public function directionLabel(string $direction, ?string $locale = null): string
    {
        return $this->t('direction.' . $direction, [], $locale);
    }

    public function typeLabel(string $type, ?string $locale = null): string
    {
        return $this->t('types.' . $type, [], $locale);
    }

    /**
     * @return array<string,mixed>
     */
    private function loadCatalog(string $locale): array
    {
        if (isset($this->catalogs[$locale])) {
            return $this->catalogs[$locale];
        }

        $catalog = [];
        $apiFile = __DIR__ . '/../language/' . $locale . '/gateway.php';
        if (is_file($apiFile)) {
            $data = require $apiFile;
            if (is_array($data)) {
                $catalog = array_replace_recursive($catalog, $data);
            }
        }

        $webFile = __DIR__ . '/../../web/language/' . $locale . '/store.php';
        if (is_file($webFile)) {
            $data = require $webFile;
            if (is_array($data)) {
                $catalog = array_replace_recursive($catalog, $data);
            }
        }

        $this->catalogs[$locale] = $catalog;

        return $catalog;
    }

    private function defaultErrorMessage(string $code): string
    {
        return match ($code) {
            'INGESTION_PAYLOAD_INVALID' => 'The request body could not be parsed',
            'INGESTION_PAYLOAD_TOO_LARGE' => 'The request body exceeds the configured limit',
            'INGESTION_VALIDATION_FAILED' => 'The payload failed validation',
            'INGESTION_CONTACT_REQUIRED' => 'A contact channel (phone, email or messenger) is required',
            'INGESTION_AMOUNT_NOT_INTEGER' => 'Money amounts must be integers in minor units',
            'INGESTION_EXTERNAL_ID_CONFLICT' => 'The idempotency key is bound to another external_id',
            'INGESTION_SPAM_DETECTED' => 'Request blocked by anti-spam system',
            'INGESTION_INTERNAL_ERROR' => 'Internal CRM error',
            default => 'Request processing failed',
        };
    }
}
