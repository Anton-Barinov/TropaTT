<?php
declare(strict_types=1);

namespace Web\System\Core;

use Web\System\I18n\I18n;

abstract class Controller
{
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
        $data['title'] = $data['title'] ?? $i18n->t('app.default_title', 'CRM');
        $data['base_path'] = $data['base_path'] ?? rtrim(str_replace('index.php', '', $_SERVER['SCRIPT_NAME'] ?? '/index.php'), '/');
        $data['locale'] = $data['locale'] ?? $i18n->locale();
        $data['lang_messages'] = $i18n->all();
        $data['i18n'] = $i18n;
        $data['module_css_files'] = self::$moduleCssFiles ?? [];
        $data['module_js_files'] = self::$moduleJsFiles ?? [];
        $data['module_js_routes'] = self::$moduleJsRoutes ?? [];

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

        $t = static fn(string $key, string $default = ''): string => $i18n->t($key, $default);

        extract($data, EXTR_SKIP);

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
}
