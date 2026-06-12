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
        $locale = self::resolveLocale();

        $fallback = self::loadLocaleFile($baseDir, 'ru-ru');
        $current = self::loadLocaleFile($baseDir, $locale);
        $messages = self::mergeRecursive($fallback, $current);

        return new self($baseDir, $locale, $messages);
    }

    public function locale(): string
    {
        return $this->locale;
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
        $moduleKey = "module.{$vendor}.{$name}";

        foreach (['ru-ru', 'en-gb', 'pt-br'] as $locale) {
            $file = $this->baseDir . '/modules/' . $vendor . '.' . $name . '/web/language/' . $locale . '.php';
            if (!is_file($file)) {
                continue;
            }

            $data = require $file;
            if (!is_array($data)) {
                continue;
            }

            foreach ($data as $key => $value) {
                if (is_string($value)) {
                    $this->messages[$moduleKey][$key] = $value;
                }
            }
        }
    }

    private static function resolveLocale(): string
    {
        $candidate = '';
        if (isset($_GET['lang'])) {
            $candidate = strtolower(trim((string)$_GET['lang']));
        }

        if ($candidate === '' && isset($_COOKIE['crm_locale'])) {
            $candidate = strtolower(trim((string)$_COOKIE['crm_locale']));
        }

        $candidate = self::normalizeLocaleCode($candidate);

        if (!in_array($candidate, ['ru-ru', 'en-gb', 'zh-cn', 'es-es', 'pt-br', 'de-de'], true)) {
            $candidate = 'ru-ru';
        }

        return $candidate;
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
