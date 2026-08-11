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

    /**
     * Render a recoverable 500 page: the CRM page failed to render (PHP
     * exception, DB hiccup, FPM blip). Instead of a bare error screen the
     * browser gets a lightweight page that retries the navigation a few times
     * on its own (backoff with a visible countdown) and then offers a manual
     * "Refresh" button. Combined with the service worker navigation retry in
     * /web/push-sw.js this hides transient 5xx on any shared host without any
     * server configuration.
     */
    public static function serverErrorPage(string $baseDir): string
    {
        $locale = self::resolveLocale();
        $messages = self::messages($baseDir, $locale);
        $htmlLang = explode('-', $locale, 2)[0];

        $title = self::translate($messages, 'early_response.server_error_title', 'Server error');
        $heading = self::translate($messages, 'early_response.server_error_heading', 'The page could not be loaded');
        $body = self::translate($messages, 'early_response.server_error_body', 'The server returned an error. Try refreshing the page in a few seconds.');
        $button = self::translate($messages, 'early_response.server_error_button', 'Refresh page');
        $retryingRaw = self::translateRaw($messages, 'early_response.server_error_retrying', 'Retrying in {seconds} s…');
        $stopped = self::translate($messages, 'early_response.server_error_stopped', 'Automatic retries exhausted. Use the button below to try again.');

        // Automatic retry with a sessionStorage counter so we never loop
        // forever on a permanently broken page. The counter is keyed per URL
        // so a transient blip on one page never disables the auto-retry on
        // the others, and a manual refresh of the same URL keeps its budget.
        $script = <<<'JS'
(function () {
  var MAX = 3;
  var DELAY = 5000;
  var KEY = 'crm_page_retry_budget_' + (window.location.pathname || '') + (window.location.search || '');
  var budget = 0;
  try { budget = parseInt(window.sessionStorage.getItem(KEY) || '0', 10) || 0; } catch (e) { budget = 0; }
  var countdownEl = document.getElementById('crm-retry-countdown');
  var stoppedEl = document.getElementById('crm-retry-stopped');
  var btn = document.getElementById('crm-retry-btn');
  if (budget >= MAX) {
    if (stoppedEl) stoppedEl.style.display = 'block';
    if (countdownEl) countdownEl.style.display = 'none';
    return;
  }
  budget += 1;
  try { window.sessionStorage.setItem(KEY, String(budget)); } catch (e) {}
  var left = Math.ceil(DELAY / 1000);
  var tick = setInterval(function () {
    left -= 1;
    if (countdownEl) countdownEl.textContent = RETRY_MSG.replace('{seconds}', String(Math.max(0, left)));
    if (left <= 0) {
      clearInterval(tick);
      window.location.reload();
    }
  }, 1000);
  if (btn) btn.addEventListener('click', function () { window.location.reload(); });
})();
JS;
        // $retryingRaw is NOT html-escaped (translateRaw), so json_encode()
        // produces a clean JS string — the countdown shows the real ellipsis
        // instead of a literal &#8230;. The static <div> below escapes it for
        // the HTML context instead (single escape, no double-escaping).
        $script = str_replace('RETRY_MSG', json_encode($retryingRaw), $script);

        return '<!doctype html><html lang="' . self::escape($htmlLang) . '"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'
            . self::escape($title) . '</title><style>'
            . 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f5f6f8;color:#1f2937;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:24px;box-sizing:border-box}'
            . '.crm-card{max-width:460px;width:100%;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:32px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,.06)}'
            . 'h1{font-size:20px;margin:0 0 12px}p{font-size:14px;line-height:1.55;color:#4b5563;margin:0 0 20px}'
            . '#crm-retry-countdown{font-size:13px;color:#6b7280;margin-bottom:16px}'
            . '#crm-retry-stopped{display:none;font-size:13px;color:#b45309;margin-bottom:16px}'
            . 'button{appearance:none;border:0;background:#2563eb;color:#fff;font-size:14px;font-weight:600;padding:10px 22px;border-radius:8px;cursor:pointer}'
            . 'button:hover{background:#1d4ed8}</style></head><body><div class="crm-card"><h1>'
            . self::escape($heading) . '</h1><p>' . $body . '</p>'
            . '<div id="crm-retry-countdown">' . str_replace('{seconds}', '5', self::escape($retryingRaw)) . '</div>'
            . '<div id="crm-retry-stopped">' . self::escape($stopped) . '</div>'
            . '<button id="crm-retry-btn" type="button" style="display:none">' . self::escape($button) . '</button>'
            . '<noscript><style>#crm-retry-btn{display:inline-block!important}#crm-retry-countdown{display:none!important}</style></noscript>'
            . '<script>' . $script . '</script></div></body></html>';
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
        $text = self::translateRaw($messages, $key, $fallback);
        $text = self::escape($text);
        foreach ($replacements as $placeholder => $replacement) {
            $text = str_replace('{' . $placeholder . '}', $replacement, $text);
        }
        return $text;
    }

    /**
     * Like translate() but WITHOUT the htmlspecialchars pass — for values that
     * are consumed in a non-HTML context (e.g. embedded into a JS string with
     * json_encode, where HTML entities would be shown literally).
     *
     * @param array<string, mixed> $messages @param array<string, string> $replacements
     */
    private static function translateRaw(array $messages, string $key, string $fallback): string
    {
        $value = $messages;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                $value = $fallback;
                break;
            }
            $value = $value[$segment];
        }
        return is_string($value) && $value !== '' ? $value : $fallback;
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
