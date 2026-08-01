<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = dirname(__DIR__, 3);
    $header = (string)file_get_contents($root . '/web/view/template/common/header.php');
    $footer = (string)file_get_contents($root . '/web/view/template/common/footer.php');
    $push = (string)file_get_contents($root . '/web/assets/js/notifications-push.js');
    $docs = (string)file_get_contents($root . '/web/docs/build-deployment.md');

    assertTrue(str_contains($header, 'CRM_WEB_ASSETS_VERSION'), 'Header must use CRM_WEB_ASSETS_VERSION');
    assertTrue(str_contains($footer, 'CRM_WEB_ASSETS_VERSION'), 'Footer must use CRM_WEB_ASSETS_VERSION');
    assertTrue(str_contains($header, 'filemtime'), 'Header must fallback to filemtime');
    assertTrue(str_contains($footer, 'filemtime'), 'Footer must fallback to filemtime');
    assertTrue(str_contains($header, "glob(\$assetsRoot . \$assetsPattern)") || str_contains($header, 'glob($assetsRoot . $assetsPattern)'), 'Header must calculate fallback version from all core assets');
    assertTrue(str_contains($footer, "glob(\$assetsRoot . \$assetsPattern)") || str_contains($footer, 'glob($assetsRoot . $assetsPattern)'), 'Footer must calculate fallback version from all core assets');
    assertTrue(str_contains($header, 'window.CRM.config.assetsVersion'), 'Header must expose assetsVersion to JS');

    foreach ([
        'assets/css/bootstrap.min.css',
        'assets/vendor/fontawesome/css/all.min.css',
        'assets/css/tokens.css',
        'assets/css/layout.css',
        'assets/css/components.css',
        'assets/css/pages.css',
        'assets/css/animations.css',
        'assets/css/responsive.css',
        'assets/css/ui.css',
    ] as $asset) {
        assertTrue(str_contains($header, $asset . '?v=<?= urlencode($assetsVersion) ?>'), 'CSS asset must be versioned: ' . $asset);
    }

    foreach ([
        'assets/js/api.js',
        'assets/js/notifications-push.js',
        'assets/js/notifications-realtime.js',
        'assets/js/page-api-bindings.js',
        'assets/js/app.js',
    ] as $asset) {
        assertTrue(str_contains($footer, $asset . '?v=<?= urlencode($assetsVersion) ?>'), 'JS asset must be versioned: ' . $asset);
    }

    assertTrue(str_contains($push, 'cfg.assetsVersion'), 'Push module must read assetsVersion');
    assertTrue(str_contains($push, "swUrl += '?v=' + encodeURIComponent(version)"), 'Service worker registration must be versioned');
    assertTrue(str_contains($docs, 'CRM_WEB_ASSETS_VERSION'), 'Deployment docs must mention version source');
    assertTrue(str_contains($docs, 'push-sw.js?v='), 'Deployment docs must mention service worker versioning');

    fwrite(STDOUT, "[OK] e04_asset_versioning_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] e04_asset_versioning_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
