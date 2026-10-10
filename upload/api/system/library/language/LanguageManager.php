<?php
declare(strict_types=1);

namespace Api\System\Library\Language;

final class LanguageManager
{
    private string $locale = 'en-gb';

    /** @var array<string,array<string,string>> */
    private array $catalog = [];

    public function __construct(
        private readonly string $basePath,
        private readonly string $fallbackLocale = 'en-gb'
    ) {
    }

    public function setLocale(string $locale): void
    {
        $normalized = $this->normalizeLocaleCode($locale);
        // SEC (audit 2026-10, findings #1/#6): the X-Locale header reaches this
        // method verbatim from the request. Without an allowlist a value like
        // "../../web/language" passed the is_dir() check and load() then
        // require()d a file outside api/language. A locale code can only be a
        // short language tag — the same shape the language-pack manifest
        // validator enforces — so anything with dots, slashes or long segments
        // is rejected before it can touch the filesystem.
        if (!$this->isAllowedLocaleCode($normalized) || !is_dir($this->basePath . '/' . $normalized)) {
            $this->locale = $this->fallbackLocale;
            return;
        }

        // SEC: this reads the registry-written cache. LanguageRegistryService
        // writes it to <project>/storage_api/cache and <project>/storage/cache
        // (basePath + "/../storage_api/cache"); this manager lives one level
        // deeper (upload/api/language), so the previous dirname(...) path
        // pointed at upload/api/storage_api/cache — a directory nothing ever
        // wrote. The enabled-locale gate below was therefore dead code and any
        // well-formed directory name was accepted. Both readers now resolve to
        // the directory the registry actually writes.
        $cacheFile = dirname($this->basePath, 2) . '/storage_api/cache/languages.json';
        if (!is_file($cacheFile)) {
            $cacheFile = dirname($this->basePath, 2) . '/storage/cache/languages.json';
        }
        if (is_file($cacheFile)) {
            $raw = @file_get_contents($cacheFile);
            if (is_string($raw) && $raw !== '') {
                $data = json_decode($raw, true);
                if (is_array($data) && isset($data['enabled']) && is_array($data['enabled'])) {
                    if (!in_array($normalized, $data['enabled'], true)) {
                        $default = (string)($data['default'] ?? $this->fallbackLocale);
                        $this->locale = is_dir($this->basePath . '/' . $default) ? $default : $this->fallbackLocale;
                        return;
                    }
                }
            }
        }

        $this->locale = $normalized;
    }

    /**
     * Return the validated locale that is currently in effect.
     */
    public function locale(): string
    {
        return $this->locale;
    }

    private function normalizeLocaleCode(string $locale): string
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

    /**
     * SEC: structural allowlist for locale codes used as path segments.
     * Mirrors LanguagePackInstaller's manifest "code" rule: 2-3 letter language
     * tag with an optional -xxx region/variant segment. Dots, slashes, ".." and
     * any other traversal shape cannot match.
     */
    private function isAllowedLocaleCode(string $code): bool
    {
        return preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,4})?$/', $code) === 1;
    }

    public function load(string $group): void
    {
        $group = trim($group, '/');
        // SEC: the group is a path segment under the locale directory. Keys are
        // normally compile-time literals, but several call sites interpolate
        // request-derived fragments, so ".." and backslashes are refused here
        // as defense in depth (locale is already allowlisted by setLocale()).
        if ($group === '' || str_contains($group, '..') || str_contains($group, "\\") || preg_match('/[\x00-\x1F\x7F]/', $group)) {
            return;
        }
        foreach ([$this->fallbackLocale, $this->locale] as $locale) {
            $path = $this->basePath . '/' . $locale . '/' . $group . '.php';
            if (!is_file($path)) {
                $path = $this->basePath . '/' . $locale . '/' . $group . '/messages.php';
            }
            if (!is_file($path)) {
                continue;
            }

            $data = require $path;
            if (is_array($data)) {
                $this->catalog[$group] = array_replace($this->catalog[$group] ?? [], $data);
            }
        }
    }

    public function get(string $key, string $default = ''): string
    {
        [$group, $name] = array_pad(explode('.', $key, 2), 2, '');
        if ($group !== '' && !array_key_exists($group, $this->catalog)) {
            $this->load($group);
        }

        if ($group !== '' && isset($this->catalog[$group][$name])) {
            return (string)$this->catalog[$group][$name];
        }

        return $default !== '' ? $default : $key;
    }

    /**
     * Load translations from a module language file.
     * @param string $vendor Module vendor (e.g. "crm")
     * @param string $name Module name (e.g. "example-hello")
     */
    public function loadModuleTranslations(string $vendor, string $name): void
    {
        $locale = $this->locale;
        $modulePath = $this->basePath . '/../../modules/' . $vendor . '.' . $name . '/api/language/' . $locale . '/module/messages.php';

        if (!is_file($modulePath)) {
            $modulePath = $this->basePath . '/../../modules/' . $vendor . '.' . $name . '/api/language/' . $this->fallbackLocale . '/module/messages.php';
        }

        if (!is_file($modulePath)) {
            return;
        }

        $data = require $modulePath;
        if (!is_array($data)) {
            return;
        }

        $prefix = 'module.' . $vendor . '.' . $name . '.';

        foreach ($data as $key => $value) {
            $fullKey = $prefix . $key;
            [$group, $name] = array_pad(explode('.', $fullKey, 2), 2, '');
            if ($group !== '') {
                $this->catalog[$group][$name] = (string)$value;
            }
        }
    }
}
