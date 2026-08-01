<?php
declare(strict_types=1);

namespace Web\System\Core;

use Web\System\I18n\I18n;

abstract class Controller
{
    /**
     * Translation namespaces used by the shared client-side scripts.
     *
     * Page templates are already rendered in the selected locale, so sending the
     * complete server dictionary to every browser only duplicates static text.
     * Keep the dynamic UI namespaces available while retaining server-rendered
     * text as the fallback for page-specific markup.
     *
     * @var array<int, string>
     */
    private const CLIENT_MESSAGE_NAMESPACES = [
        'js',
        'nav',
        'topbar',
        'priority',
        'page',
        'common',
        'richtext',
        'dashboard',
        'tasks',
        'task_detail',
        'notifications',
        'footer',
        'admin_estimates',
        'project_modules',
        'cycles',
        'intake',
        'task_activity',
        'visual_editor',
        'chat',
    ];

    /** @var array<int, string>|null */
    private static ?array $moduleCssFiles = null;

    /** @var array<int, string>|null */
    private static ?array $moduleJsFiles = null;

    /** @var array<string, string>|null */
    private static ?array $moduleJsRoutes = null;

    /** @var \Web\System\Hook\HookManager|null */
    private static ?\Web\System\Hook\HookManager $webHookManager = null;

    public function __construct(protected readonly string $baseDir)
    {
    }

    /**
     * @param array<int, string> $cssFiles
     * @param array<int, string> $jsFiles
     * @param array<string, string> $jsRoutes
     */
    public static function setModuleAssets(array $cssFiles, array $jsFiles, array $jsRoutes): void
    {
        self::$moduleCssFiles = $cssFiles;
        self::$moduleJsFiles = $jsFiles;
        self::$moduleJsRoutes = $jsRoutes;
    }

    public static function setWebHookManager(\Web\System\Hook\HookManager $hm): void
    {
        self::$webHookManager = $hm;
    }

    public static function resetModuleAssets(): void
    {
        self::$moduleCssFiles = null;
        self::$moduleJsFiles = null;
        self::$moduleJsRoutes = null;
    }

    /** @param array<string, mixed> $data */
    protected function render(string $template, array $data = [], int $statusCode = 200): void
    {
        http_response_code($statusCode);

        if (str_starts_with($template, '/') || str_contains($template, 'modules/')) {
            $viewFile = $template;
        } else {
            $viewFile = $this->baseDir . '/view/template/' . $template . '.php';
        }
        $i18n = I18n::fromRequest($this->baseDir);

        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'Template not found: ' . htmlspecialchars($template, ENT_QUOTES, 'UTF-8');
            return;
        }

        $data['route'] = $data['route'] ?? '';
        $routeKey = str_replace('-', '_', (string)$data['route']);
        $data['base_path'] = $data['base_path'] ?? rtrim(str_replace('index.php', '', $_SERVER['SCRIPT_NAME'] ?? '/index.php'), '/');
        $data['locale'] = $data['locale'] ?? $i18n->locale();
        $data['i18n'] = $i18n;
        $data['module_css_files'] = self::$moduleCssFiles ?? [];
        $data['module_js_files'] = self::$moduleJsFiles ?? [];
        $data['module_js_routes'] = self::$moduleJsRoutes ?? [];
        // SEC-004: Expose the per-request CSP nonce (set by web/index.php) so
        // templates can attach it to inline <script nonce="..."> and
        // <style nonce="..."> tags. Empty string when no nonce is in scope
        // (e.g., when Controller is rendered outside the web bootstrap, such
        // as a CLI test or cron context).
        $data['csp_nonce'] = (string)($GLOBALS['crm_csp_nonce'] ?? '');

        if (self::$webHookManager !== null) {
            $moduleNames = [];
            foreach (self::$moduleCssFiles ?? [] as $css) {
                if (preg_match('#^modules/([a-z0-9]+\.[a-z0-9\-]+)/#', $css, $m)) {
                    $moduleNames[$m[1]] = true;
                }
            }
            foreach ($moduleNames as $modName => $_) {
                $parts = explode('.', $modName, 2);
                if (count($parts) === 2) {
                    $i18n->loadModuleTranslations($parts[0], $parts[1]);
                }
            }
        }

        $titleKey = str_starts_with($routeKey, 'module_') ? substr($routeKey, 7) : $routeKey;
        $routeTitle = $titleKey !== '' ? $i18n->t($titleKey . '.title', '') : '';
        $data['title'] = $routeTitle !== '' ? $routeTitle : ($data['title'] ?? $i18n->t('app.default_title', 'CRM'));
        $data['lang_messages'] = $this->clientMessages($i18n->all());
        $t = static fn(string $key, string $default = ''): string => $i18n->t($key, $default);

        // SEC: Replace extract() with explicit variable creation to prevent variable injection.
        // Preserve EXTR_SKIP semantics: do not overwrite existing locals or superglobals.
        $reservedSkip = ['_GET', '_POST', '_REQUEST', '_SERVER', '_SESSION', '_COOKIE', '_FILES', '_ENV', 'GLOBALS',
            'data', 'template', 'viewFile', 'i18n', 't', 'routeKey', 'routeTitle', 'statusCode', 'baseDir',
            'hookContext', 'this'];
        foreach ($data as $key => $value) {
            if (!is_string($key) || in_array($key, $reservedSkip, true)) {
                continue;
            }
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
                continue;
            }
            if (isset(${$key})) {
                continue; // EXTR_SKIP behavior — keep existing local
            }
            ${$key} = $value;
        }

        if (self::$webHookManager !== null) {
            $hookContext = ['template' => $template, 'data' => &$data];
            self::$webHookManager->dispatch('render.before', $hookContext);
            $data = $hookContext['data'] ?? $data;
        }

        require $this->baseDir . '/view/template/common/header.php';
        require_once $this->baseDir . '/view/template/common/page_head.php';
        require $viewFile;
        require $this->baseDir . '/view/template/common/footer.php';

        if (self::$webHookManager !== null) {
            $hookContext = ['template' => $template, 'html' => ''];
            self::$webHookManager->dispatch('render.after', $hookContext);
        }
    }

    /** @param array<string, mixed> $messages
     *  @return array<string, mixed>
     */
    private function clientMessages(array $messages): array
    {
        $selected = [];
        foreach (self::CLIENT_MESSAGE_NAMESPACES as $namespace) {
            if (array_key_exists($namespace, $messages)) {
                $selected[$namespace] = $messages[$namespace];
            }
        }

        // Module translations use their own top-level namespace. Preserve every
        // loaded module namespace without re-sending unrelated page dictionaries.
        foreach (['jira_migration', 'confluence_migration'] as $namespace) {
            if (array_key_exists($namespace, $messages)) {
                $selected[$namespace] = $messages[$namespace];
            }
        }

        return $selected;
    }
}
