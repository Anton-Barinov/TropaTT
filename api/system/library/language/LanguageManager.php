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
        if ($normalized === '' || !is_dir($this->basePath . '/' . $normalized)) {
            $this->locale = $this->fallbackLocale;
            return;
        }

        $this->locale = $normalized;
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
            default => $value,
        };
    }

    public function load(string $group): void
    {
        $group = trim($group, '/');
        foreach ([$this->fallbackLocale, $this->locale] as $locale) {
            $path = $this->basePath . '/' . $locale . '/' . $group . '.php';
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
