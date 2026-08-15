<?php
declare(strict_types=1);

namespace Web\System\Module;

use Api\System\Library\Module\PluginManager;

final class ModuleAssetManager
{
    /** @var array<int, string> */
    private array $cssFiles = [];

    /** @var array<int, string> */
    private array $jsFiles = [];

    /** @var array<string, string> */
    private array $cssRoutes = [];

    /** @var array<string, string> */
    private array $jsRoutes = [];

    public function __construct(
        private readonly PluginManager $pluginManager,
        private readonly string $projectRoot,
    ) {}

    public function collect(): void
    {
        $modulesDir = $this->pluginManager->getModulesDir();
        $active = $this->pluginManager->getActive();

        foreach ($active as $name => $manifest) {
            $moduleDir = $modulesDir . '/' . $manifest->name;
            $assets = $manifest->assets;

            if (isset($assets['css']) && is_array($assets['css'])) {
                foreach ($assets['css'] as $css) {
                    $cssPath = $moduleDir . '/' . $css;
                    if (is_file($cssPath)) {
                        $this->cssFiles[] = 'modules/' . $manifest->name . '/' . $css;
                    }
                }
            }

            if (isset($assets['js']) && is_array($assets['js'])) {
                foreach ($assets['js'] as $js) {
                    $jsPath = $moduleDir . '/' . $js;
                    if (is_file($jsPath)) {
                        $this->jsFiles[] = 'modules/' . $manifest->name . '/' . $js;
                    }
                }
            }

            if (isset($assets['css_routes']) && is_array($assets['css_routes'])) {
                foreach ($assets['css_routes'] as $route => $css) {
                    $cssPath = $moduleDir . '/' . $css;
                    if (is_file($cssPath)) {
                        $this->cssRoutes[$route] = 'modules/' . $manifest->name . '/' . $css;
                    }
                }
            }

            if (isset($assets['js_routes']) && is_array($assets['js_routes'])) {
                foreach ($assets['js_routes'] as $route => $js) {
                    $jsPath = $moduleDir . '/' . $js;
                    if (is_file($jsPath)) {
                        $this->jsRoutes[$route] = 'modules/' . $manifest->name . '/' . $js;
                    }
                }
            }
        }
    }

    /** @return array<int, string> */
    public function getCssFiles(): array
    {
        return $this->cssFiles;
    }

    /** @return array<int, string> */
    public function getJsFiles(): array
    {
        return $this->jsFiles;
    }

    /** @return array<string, string> */
    public function getCssRoutes(): array
    {
        return $this->cssRoutes;
    }

    /** @return array<string, string> */
    public function getJsRoutes(): array
    {
        return $this->jsRoutes;
    }

    /** @return array<string, array<int, string>> */
    public function getAll(): array
    {
        return [
            'css' => $this->cssFiles,
            'js' => $this->jsFiles,
            'css_routes' => $this->cssRoutes,
            'js_routes' => $this->jsRoutes,
        ];
    }
}
