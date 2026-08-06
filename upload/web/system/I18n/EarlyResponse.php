<?php
declare(strict_types=1);

namespace Web\System\I18n;

final class EarlyResponse
{
    /** @var list<string> */
    private const SUPPORTED_LOCALES = ['ru-ru', 'en-gb', 'zh-cn', 'es-es', 'pt-br', 'de-de', 'fr-fr'];

    /**
     * Render the maintenance page without bootstrapping the full web application.
     */
    public static function maintenancePage(string $baseDir): string
    {
        $locale = self::resolveLocale();
        $messages = self::messages($baseDir, $locale);

        return self::page(
            $locale,
            self::translate($messages, 'early_response.maintenance_title', 'Maintenance'),
            self::translate($messages, 'early_response.maintenance_heading', 'TropaTT maintenance'),
            self::translate($messages, 'early_response.maintenance_body', 'Core update maintenance mode is active. Follow or finish the update from the admin panel: {updates_url}. The updates page stays reachable during maintenance.', [
                'updates_url' => '<code>/web/index.php?route=admin-updates</code>',
            ]) . ' ' . self::translate($messages, 'early_response.recovery_body', 'Emergency recovery is available at {rescue_url} using the recovery key shown once at installation or re-issued from the updates page.', [
                'rescue_url' => '<code>/updater/rescue.php</code>',
            ])
        );
    }

    /**
     * Render a forbidden page without bootstrapping the full web application.
     */
    public static function forbiddenPage(string $baseDir): string
    {
        $locale = self::resolveLocale();
        $messages = self::messages($baseDir, $locale);

        return self::page(
            $locale,
            self::translate($messages, 'early_response.forbidden_title', '403 Forbidden'),
            self::translate($messages, 'early_response.forbidden_heading', '403 Forbidden'),
            self::translate($messages, 'early_response.forbidden_body', 'You do not have permission to access this page.')
        );
    }

    private static function page(string $locale, string $title, string $heading, string $body): string
    {
        $htmlLang = explode('-', $locale, 2)[0];
        return '<!doctype html><html lang="' . self::escape($htmlLang) . '"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'
            . self::escape($title) . '</title></head><body style="font-family:sans-serif;padding:40px"><h1>'
            . self::escape($heading) . '</h1><p>' . $body . '</p></body></html>';
    }

    /** @return array<string, mixed> */
    private static function messages(string $baseDir, string $locale): array
    {
        $fallback = self::load($baseDir, 'ru-ru');
        $current = self::load($baseDir, $locale);
        $messages = array_replace_recursive($fallback, $current);
        $overridesPath = $baseDir . '/language/overrides.php';
        if (is_file($overridesPath)) {
            $overrides = require $overridesPath;
            if (is_array($overrides)) {
                if (is_array($overrides['ru-ru'] ?? null)) {
                    $messages = array_replace_recursive($messages, $overrides['ru-ru']);
                }
                if ($locale !== 'ru-ru' && is_array($overrides[$locale] ?? null)) {
                    $messages = array_replace_recursive($messages, $overrides[$locale]);
                }
            }
        }
        return $messages;
    }

    /** @return array<string, mixed> */
    private static function load(string $baseDir, string $locale): array
    {
        $file = $baseDir . '/language/' . $locale . '.php';
        if (!is_file($file)) {
            return [];
        }
        $data = require $file;
        return is_array($data) ? $data : [];
    }

    /** @param array<string, mixed> $messages @param array<string, string> $replacements */
    private static function translate(array $messages, string $key, string $fallback, array $replacements = []): string
    {
        $value = $messages;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                $value = $fallback;
                break;
            }
            $value = $value[$segment];
        }
        $text = is_string($value) && $value !== '' ? $value : $fallback;
        $text = self::escape($text);
        foreach ($replacements as $placeholder => $replacement) {
            $text = str_replace('{' . $placeholder . '}', $replacement, $text);
        }
        return $text;
    }

    private static function resolveLocale(): string
    {
        $candidates = [
            $_GET['lang'] ?? '',
            $_COOKIE['crm_locale'] ?? '',
            $_SERVER['HTTP_X_LOCALE'] ?? '',
        ];
        $acceptLanguage = (string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        foreach (explode(',', $acceptLanguage) as $part) {
            $candidates[] = explode(';', $part, 2)[0];
        }

        foreach ($candidates as $candidate) {
            $normalized = self::normalize((string)$candidate);
            if (in_array($normalized, self::SUPPORTED_LOCALES, true)) {
                return $normalized;
            }
        }

        return 'ru-ru';
    }

    private static function normalize(string $locale): string
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

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
