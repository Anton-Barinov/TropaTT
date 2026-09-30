<?php
declare(strict_types=1);

namespace Web\System\I18n;

final class I18n
{
    /** @var array<string, mixed> */
    private array $messages;

    public function __construct(
        private readonly string $baseDir,
        private readonly string $locale,
        array $messages
    ) {
        $this->messages = $messages;
    }

    public static function fromRequest(string $baseDir): self
    {
        $locale = self::resolveLocale($baseDir);

        // Use one deterministic fallback language for each locale family. The
        // previous implementation always merged Russian first, so an
        // incomplete translation silently produced mixed Russian/English/
        // target-language screens. Russian remains the native fallback for
        // Russian; every other locale falls back consistently to English.
        $fallbackLocale = $locale === 'ru-ru' ? 'ru-ru' : 'en-gb';
        $fallback = self::loadLocaleFile($baseDir, $fallbackLocale);
        $current = self::loadLocaleFile($baseDir, $locale);
        $messages = self::mergeRecursive($fallback, $current);

        // Supplemental packs keep late translation fixes separate from the
        // generated/large locale files. They are merged last so every runtime
        // label is resolved in the requested locale instead of leaking the
        // Russian fallback into another language.
        $supplementalPath = $baseDir . '/language/overrides.php';
        if (is_file($supplementalPath)) {
            $supplemental = require $supplementalPath;
            if (is_array($supplemental)) {
                if (is_array($supplemental[$fallbackLocale] ?? null)) {
                    /** @var array<string, mixed> $fallbackOverrides */
                    $fallbackOverrides = $supplemental[$fallbackLocale];
                    $messages = self::mergeRecursive($messages, $fallbackOverrides);
                }
                if ($locale !== 'ru-ru' && is_array($supplemental[$locale] ?? null)) {
                    /** @var array<string, mixed> $localeOverrides */
                    $localeOverrides = $supplemental[$locale];
                    $messages = self::mergeRecursive($messages, $localeOverrides);
                }
            }
        }

        $parityPath = $baseDir . '/language/supplemental/locale_parity.php';
        if (is_file($parityPath)) {
            $parity = require $parityPath;
            if (is_array($parity)) {
                if (is_array($parity[$fallbackLocale] ?? null)) {
                    $messages = self::mergeRecursive($messages, $parity[$fallbackLocale]);
                }
                if ($locale !== 'ru-ru' && is_array($parity[$locale] ?? null)) {
                    $messages = self::mergeRecursive($messages, $parity[$locale]);
                }
            }
        }

        $jsSupplementalPath = $baseDir . '/language/js_overrides.php';
        if (is_file($jsSupplementalPath)) {
            $jsSupplemental = require $jsSupplementalPath;
            if (is_array($jsSupplemental)) {
                if (is_array($jsSupplemental[$fallbackLocale] ?? null)) {
                    /** @var array<string, mixed> $fallbackJsOverrides */
                    $fallbackJsOverrides = $jsSupplemental[$fallbackLocale];
                    $messages = self::mergeRecursive($messages, $fallbackJsOverrides);
                }
                if ($locale !== 'ru-ru' && is_array($jsSupplemental[$locale] ?? null)) {
                    /** @var array<string, mixed> $localeJsOverrides */
                    $localeJsOverrides = $jsSupplemental[$locale];
                    $messages = self::mergeRecursive($messages, $localeJsOverrides);
                }
            }
        }

        return new self($baseDir, $locale, $messages);
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function direction(): string
    {
        return self::resolveDirection($this->locale, $this->baseDir);
    }

    public static function resolveDirection(string $locale, string $baseDir = ''): string
    {
        $enabled = self::getEnabledLocales($baseDir);
        foreach ($enabled as $item) {
            if ($item['code'] === $locale) {
                return (string)($item['direction'] ?? 'ltr');
            }
        }
        $prefix = explode('-', $locale)[0];
        if (in_array($prefix, ['ar', 'he', 'fa', 'ur', 'yi', 'ps', 'sd'], true)) {
            return 'rtl';
        }
        return 'ltr';
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->messages;
    }

    public function t(string $key, string $default = ''): string
    {
        $value = $this->messages;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default !== '' ? $default : $key;
            }
            $value = $value[$segment];
        }

        return is_string($value) ? $value : ($default !== '' ? $default : $key);
    }

    /** @return array<string, mixed> */
    private static function loadLocaleFile(string $baseDir, string $locale): array
    {
        $file = $baseDir . '/language/' . $locale . '.php';
        if (!is_file($file)) {
            return [];
        }

        $data = require $file;
        return is_array($data) ? $data : [];
    }

    /**
     * Load translations from a module language file.
     * Keys are prefixed with module.{vendor}.{name}. for isolation.
     */
    public function loadModuleTranslations(string $vendor, string $name): void
    {
        $modulesDir = dirname($this->baseDir) . '/modules';
        $current = $this->locale();
        $locales = [$current];
        $fallbackLocale = $current === 'ru-ru' ? 'ru-ru' : 'en-gb';
        if ($current !== $fallbackLocale) {
            $locales[] = $fallbackLocale;
        }

        foreach ($locales as $locale) {
            $file = $modulesDir . '/' . $vendor . '.' . $name . '/web/language/' . $locale . '.php';
            if (!is_file($file)) {
                continue;
            }

            $data = require $file;
            if (!is_array($data)) {
                continue;
            }

            $this->messages = self::mergeRecursive($this->messages, $data);
        }
    }

    private static function resolveLocale(string $baseDir = ''): string
    {
        $candidate = '';
        if (isset($_GET['lang'])) {
            $candidate = strtolower(trim((string)$_GET['lang']));
        }

        if ($candidate === '' && isset($_COOKIE['crm_locale'])) {
            $candidate = strtolower(trim((string)$_COOKIE['crm_locale']));
        }

        $candidate = self::normalizeLocaleCode($candidate);

        $enabled = self::getEnabledLocaleCodes($baseDir);
        $default = self::getDefaultLocaleCode($baseDir);

        if (!in_array($candidate, $enabled, true)) {
            $candidate = in_array($default, $enabled, true) ? $default : 'ru-ru';
        }

        return $candidate;
    }

    /**
     * @return array<int, string>
     */
    public static function getEnabledLocaleCodes(string $baseDir = ''): array
    {
        $data = self::readLanguageCache($baseDir);
        if (isset($data['enabled']) && is_array($data['enabled']) && !empty($data['enabled'])) {
            $enabled = array_values(array_unique(array_map('strval', $data['enabled'])));
            $coreLocales = ['ru-ru', 'en-gb', 'zh-cn'];
            $enabled = array_values(array_filter($enabled, static fn (string $code): bool => in_array($code, $coreLocales, true) || is_file($baseDir . '/language/' . $code . '.php')));
            // Bundled locale packs shipped with the web application must remain
            // selectable immediately after deployment, even when an older
            // languages.json cache predates the pack (for example he-il).
            foreach ([] as $bundledLocale) {
                if (!in_array($bundledLocale, $enabled, true)
                    && is_file($baseDir . '/language/' . $bundledLocale . '.php')) {
                    $enabled[] = $bundledLocale;
                }
            }
            return $enabled;
        }

        return ['ru-ru', 'en-gb', 'zh-cn'];
    }

    public static function getDefaultLocaleCode(string $baseDir = ''): string
    {
        $data = self::readLanguageCache($baseDir);
        if (isset($data['default']) && is_string($data['default']) && $data['default'] !== '') {
            return $data['default'];
        }

        return 'ru-ru';
    }

    /**
     * @return array<int, array{code: string, name: string, native_name: string, direction: string}>
     */
    public static function getEnabledLocales(string $baseDir = ''): array
    {
        $data = self::readLanguageCache($baseDir);
        if (isset($data['all']) && is_array($data['all']) && !empty($data['all'])) {
            $filtered = [];
            foreach ($data['all'] as $item) {
                if (!empty($item['is_enabled'])) {
                    $filtered[] = [
                        'code' => (string)($item['code'] ?? ''),
                        'name' => (string)($item['name'] ?? ''),
                        'native_name' => (string)($item['native_name'] ?? $item['name'] ?? ''),
                        'direction' => (string)($item['direction'] ?? 'ltr'),
                    ];
                }
            }
            if (!empty($filtered)) {
                $known = array_column($filtered, 'code');
                return $filtered;
            }
        }

        return [
            ['code' => 'ru-ru', 'name' => 'Russian', 'native_name' => 'Русский', 'direction' => 'ltr'],
            ['code' => 'en-gb', 'name' => 'English', 'native_name' => 'English', 'direction' => 'ltr'],
            ['code' => 'zh-cn', 'name' => 'Chinese (Simplified)', 'native_name' => '中文', 'direction' => 'ltr'],
        ];
    }

    private static function readLanguageCache(string $baseDir = ''): ?array
    {
        if ($baseDir === '') {
            $baseDir = dirname(__DIR__, 2);
        }
        $candidates = [
            dirname($baseDir) . '/storage/cache/languages.json',
            dirname($baseDir) . '/storage_api/cache/languages.json',
        ];
        foreach ($candidates as $file) {
            if (is_file($file)) {
                $raw = @file_get_contents($file);
                if (is_string($raw) && $raw !== '') {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        return $decoded;
                    }
                }
            }
        }
        return null;
    }

    private static function normalizeLocaleCode(string $locale): string
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
            'he', 'iw' => 'he-il',
            default => $value,
        };
    }

    /** @param array<string, mixed> $fallback @param array<string, mixed> $current */
    private static function mergeRecursive(array $fallback, array $current): array
    {
        $result = $fallback;
        foreach ($current as $key => $value) {
            if (is_array($value) && isset($result[$key]) && is_array($result[$key])) {
                /** @var array<string, mixed> $nested */
                $nested = self::mergeRecursive($result[$key], $value);
                $result[$key] = $nested;
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
