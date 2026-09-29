<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use InvalidArgumentException;

final class LanguageRegistryService
{
    public const SCOPE = 'localization';
    public const KEY_ENABLED = 'enabled_locales';
    public const KEY_DEFAULT = 'default_locale';

    /**
     * Built-in locales installed in core.
     * @var array<string, array{code: string, name: string, native_name: string, direction: string, is_builtin: bool}>
     */
    private const BUILTIN_LOCALES = [
        'ru-ru' => [
            'code' => 'ru-ru',
            'name' => 'Russian',
            'native_name' => 'Русский',
            'direction' => 'ltr',
            'is_builtin' => true,
        ],
        'en-gb' => [
            'code' => 'en-gb',
            'name' => 'English',
            'native_name' => 'English',
            'direction' => 'ltr',
            'is_builtin' => true,
        ],
        'zh-cn' => [
            'code' => 'zh-cn',
            'name' => 'Chinese (Simplified)',
            'native_name' => '中文',
            'direction' => 'ltr',
            'is_builtin' => true,
        ],
        'es-es' => [
            'code' => 'es-es',
            'name' => 'Spanish',
            'native_name' => 'Español',
            'direction' => 'ltr',
            'is_builtin' => true,
        ],
        'fr-fr' => [
            'code' => 'fr-fr',
            'name' => 'French',
            'native_name' => 'Français',
            'direction' => 'ltr',
            'is_builtin' => true,
        ],
        'pt-br' => [
            'code' => 'pt-br',
            'name' => 'Portuguese (Brazil)',
            'native_name' => 'Português (Brasil)',
            'direction' => 'ltr',
            'is_builtin' => true,
        ],
        'de-de' => [
            'code' => 'de-de',
            'name' => 'German',
            'native_name' => 'Deutsch',
            'direction' => 'ltr',
            'is_builtin' => true,
        ],
    ];

    public function __construct(
        private readonly SettingService $settings,
        private readonly string $basePath
    ) {
    }

    /**
     * Get all installed locales with their status.
     * @return array<int, array<string, mixed>>
     */
    public function listAll(): array
    {
        $installed = $this->discoverInstalledLocales();
        $enabled = $this->getEnabledCodes();
        $default = $this->getDefaultLocale();

        $result = [];
        foreach ($installed as $code => $meta) {
            $isEnabled = in_array($code, $enabled, true) || $code === $default;
            $result[] = array_merge($meta, [
                'is_enabled' => $isEnabled,
                'is_default' => $code === $default,
            ]);
        }

        return $result;
    }

    /**
     * Get only enabled locales.
     * @return array<int, array<string, mixed>>
     */
    public function listEnabled(): array
    {
        return array_values(array_filter(
            $this->listAll(),
            static fn(array $item): bool => (bool)$item['is_enabled']
        ));
    }

    /**
     * Get the code of the default system locale.
     */
    public function getDefaultLocale(): string
    {
        try {
            $setting = $this->settings->get(self::SCOPE, self::KEY_DEFAULT);
            $val = is_array($setting) ? ($setting['value'] ?? null) : null;
            if (is_string($val) && $val !== '') {
                $normalized = $this->normalizeLocaleCode($val);
                if ($normalized !== '') {
                    return $normalized;
                }
            }
        } catch (\Throwable) {
            // DB not available or settings missing
        }

        return 'ru-ru';
    }

    /**
     * Set the system default locale.
     * @return array{ok: bool, default_locale: string}
     */
    public function setDefaultLocale(string $code): array
    {
        $normalized = $this->normalizeLocaleCode($code);
        $installed = $this->discoverInstalledLocales();

        if (!isset($installed[$normalized])) {
            throw new InvalidArgumentException(sprintf('Locale "%s" is not installed.', $code));
        }

        // Auto-enable if disabled
        $enabled = $this->getEnabledCodes();
        if (!in_array($normalized, $enabled, true)) {
            $enabled[] = $normalized;
            $this->saveEnabledCodes($enabled);
        }

        $this->settings->set(self::SCOPE, self::KEY_DEFAULT, $normalized);
        $this->syncStorageCache();

        return [
            'ok' => true,
            'default_locale' => $normalized,
        ];
    }

    /**
     * Toggle enabled state for a locale.
     * @return array{ok: bool, code: string, is_enabled: bool}
     */
    public function toggle(string $code, bool $enabled): array
    {
        $normalized = $this->normalizeLocaleCode($code);
        $installed = $this->discoverInstalledLocales();

        if (!isset($installed[$normalized])) {
            throw new InvalidArgumentException(sprintf('Locale "%s" is not installed.', $code));
        }

        $default = $this->getDefaultLocale();
        if (!$enabled && $normalized === $default) {
            throw new InvalidArgumentException('Cannot disable the default system language.');
        }

        $currentEnabled = $this->getEnabledCodes();
        if ($enabled) {
            if (!in_array($normalized, $currentEnabled, true)) {
                $currentEnabled[] = $normalized;
            }
        } else {
            $currentEnabled = array_values(array_filter(
                $currentEnabled,
                static fn(string $c): bool => $c !== $normalized
            ));
        }

        $this->saveEnabledCodes($currentEnabled);
        $this->syncStorageCache();

        return [
            'ok' => true,
            'code' => $normalized,
            'is_enabled' => $enabled,
        ];
    }

    /**
     * Check if a locale is enabled.
     */
    public function isLocaleEnabled(string $code): bool
    {
        $normalized = $this->normalizeLocaleCode($code);
        if ($normalized === $this->getDefaultLocale()) {
            return true;
        }

        return in_array($normalized, $this->getEnabledCodes(), true);
    }

    /**
     * Return array of enabled locale codes.
     * @return array<int, string>
     */
    public function getEnabledCodes(): array
    {
        try {
            $setting = $this->settings->get(self::SCOPE, self::KEY_ENABLED);
            $val = is_array($setting) ? ($setting['value'] ?? null) : null;
            if (is_array($val) && !empty($val)) {
                $codes = [];
                foreach ($val as $c) {
                    if (is_string($c) && $c !== '') {
                        $norm = $this->normalizeLocaleCode($c);
                        if ($norm !== '') {
                            $codes[] = $norm;
                        }
                    }
                }
                if (!empty($codes)) {
                    $default = $this->getDefaultLocale();
                    if (!in_array($default, $codes, true)) {
                        $codes[] = $default;
                    }
                    return array_values(array_unique($codes));
                }
            }
        } catch (\Throwable) {
            // DB not available or settings missing
        }

        // Default: all installed builtins enabled
        return array_keys(self::BUILTIN_LOCALES);
    }

    /**
     * @param array<int, string> $codes
     */
    private function saveEnabledCodes(array $codes): void
    {
        $default = $this->getDefaultLocale();
        if (!in_array($default, $codes, true)) {
            $codes[] = $default;
        }
        $codes = array_values(array_unique($codes));
        $this->settings->set(self::SCOPE, self::KEY_ENABLED, $codes);
    }

    /**
     * Scan disk for installed locales.
     * @return array<string, array<string, mixed>>
     */
    public function discoverInstalledLocales(): array
    {
        $locales = self::BUILTIN_LOCALES;
        $apiLangDir = $this->basePath . '/language';
        if (is_dir($apiLangDir)) {
            $entries = scandir($apiLangDir) ?: [];
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..' || !is_dir($apiLangDir . '/' . $entry)) {
                    continue;
                }
                $code = $this->normalizeLocaleCode($entry);
                if (isset($locales[$code])) {
                    continue;
                }
                // Check if custom package has manifest
                $manifestPath = $apiLangDir . '/' . $entry . '/manifest.json';
                $manifest = [];
                if (is_file($manifestPath)) {
                    $raw = file_get_contents($manifestPath);
                    $manifest = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
                }

                $direction = (string)($manifest['direction'] ?? ($this->isRtlCode($code) ? 'rtl' : 'ltr'));
                $locales[$code] = [
                    'code' => $code,
                    'name' => (string)($manifest['name'] ?? $code),
                    'native_name' => (string)($manifest['native_name'] ?? $manifest['name'] ?? $code),
                    'direction' => $direction,
                    'is_builtin' => false,
                    'version' => (string)($manifest['version'] ?? '1.0.0'),
                    'author' => (string)($manifest['author'] ?? ''),
                ];
            }
        }

        return $locales;
    }

    public function isRtlCode(string $code): bool
    {
        $prefix = explode('-', $code)[0];
        return in_array($prefix, ['ar', 'he', 'fa', 'ur', 'yi', 'ps', 'sd'], true);
    }

    public function normalizeLocaleCode(string $locale): string
    {
        $value = str_replace('_', '-', strtolower(trim($locale)));
        return match ($value) {
            'ru' => 'ru-ru',
            'en' => 'en-gb',
            'zh', 'cn', 'zh-hans' => 'zh-cn',
            'es' => 'es-es',
            'pt' => 'pt-br',
            'de' => 'de-de',
            'fr' => 'fr-fr',
            default => $value,
        };
    }

    /**
     * Write small cache files to storage_api and storage for instant zero-SQL lookup.
     */
    public function syncStorageCache(): void
    {
        $payload = [
            'enabled' => $this->getEnabledCodes(),
            'default' => $this->getDefaultLocale(),
            'all' => $this->listAll(),
            'synced_at' => gmdate('Y-m-d H:i:s'),
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (!is_string($json)) {
            return;
        }

        $dirs = [
            $this->basePath . '/../storage_api/cache',
            $this->basePath . '/../storage/cache',
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (is_dir($dir)) {
                @file_put_contents($dir . '/languages.json', $json, LOCK_EX);
            }
        }
    }
}
