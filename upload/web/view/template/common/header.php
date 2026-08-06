<?php
declare(strict_types=1);
$currentLocale = strtolower((string)($locale ?? 'ru-ru'));
$htmlLang = str_contains($currentLocale, '-') ? explode('-', $currentLocale, 2)[0] : $currentLocale;
$assetsVersion = trim((string)getenv('CRM_WEB_ASSETS_VERSION'));
$vapidPublicKey = trim((string)getenv('NOTIFICATIONS_PUSH_VAPID_PUBLIC_KEY'));
$realtimeTransport = strtolower(trim((string)getenv('CRM_REALTIME_TRANSPORT')));
// Long-lived SSE connections occupy a PHP-FPM worker. Polling is the safe default
// for ordinary shared hosting; SSE can be enabled explicitly on dedicated hosting.
if ($realtimeTransport !== 'sse') {
  $realtimeTransport = 'poll';
}
if ($assetsVersion === '') {
  $deployHashFile = dirname(__DIR__, 3) . '/DEPLOY_HASH';
  if (is_file($deployHashFile)) {
    $assetsVersion = trim((string)file_get_contents($deployHashFile));
  }
}
// Always fold the newest asset mtime into the version. Assets are served with
// 'Cache-Control: immutable' (see web/.htaccess), so the query string is the
// ONLY thing that busts the browser cache. DEPLOY_HASH is regenerated only by
// deploy.sh, but updates applied through the in-CRM updater (update center) never
// touch it — without the mtime suffix browsers would keep serving the old JS/CSS
// for up to a year after an update.
$assetsRoot = dirname(__DIR__, 3) . '/assets';
$assetsVersionMtime = 0;
foreach ([
  '/css/*.css',
  '/js/*.js',
  '/vendor/bootstrap/*.js',
  '/vendor/fontawesome/css/*.css',
] as $assetsPattern) {
  foreach (glob($assetsRoot . $assetsPattern) ?: [] as $assetsProbe) {
    $mtime = @filemtime($assetsProbe);
    if ($mtime !== false) {
      $assetsVersionMtime = max($assetsVersionMtime, (int)$mtime);
    }
  }
}
if ($assetsVersion !== '') {
  $assetsVersion .= $assetsVersionMtime > 0 ? '-' . (string)$assetsVersionMtime : '';
} else {
  $assetsVersion = $assetsVersionMtime > 0 ? (string)$assetsVersionMtime : '20260505-1';
}
$jsOverridesPath = dirname(__DIR__, 3) . '/language/js_overrides.php';
if (is_file($jsOverridesPath)) {
  $jsOverrides = require $jsOverridesPath;
  if (is_array($jsOverrides)) {
    if (is_array($jsOverrides['ru-ru'] ?? null)) {
      $lang_messages = array_replace_recursive(is_array($lang_messages ?? null) ? $lang_messages : [], $jsOverrides['ru-ru']);
    }
    if ($currentLocale !== 'ru-ru' && is_array($jsOverrides[$currentLocale] ?? null)) {
      $lang_messages = array_replace_recursive($lang_messages, $jsOverrides[$currentLocale]);
    }
  }
}
?><!doctype html>
<html lang="<?= htmlspecialchars($htmlLang, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars((string)($title ?? (($t ?? static fn($k, $d = '') => $d)('app.default_title', 'CRM'))), ENT_QUOTES, 'UTF-8') ?></title>
  <script>
    (function () {
      var key = 'crm_sidebar_collapsed=';
      var parts = String(document.cookie || '').split(';');
      for (var i = 0; i < parts.length; i += 1) {
        var piece = String(parts[i] || '').trim();
        if (piece.indexOf(key) === 0 && piece.slice(key.length) === '1') {
          document.documentElement.classList.add('crm-sidebar-collapsed');
          break;
        }
      }
    })();
  </script>
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
  <link rel="stylesheet" href="assets/css/bootstrap.min.css?v=<?= urlencode($assetsVersion) ?>">
  <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css?v=<?= urlencode($assetsVersion) ?>">
  <link rel="stylesheet" href="assets/css/tokens.css?v=<?= urlencode($assetsVersion) ?>">
  <link rel="stylesheet" href="assets/css/layout.css?v=<?= urlencode($assetsVersion) ?>">
  <link rel="stylesheet" href="assets/css/components.css?v=<?= urlencode($assetsVersion) ?>">
  <link rel="stylesheet" href="assets/css/pages.css?v=<?= urlencode($assetsVersion) ?>">
  <link rel="stylesheet" href="assets/css/animations.css?v=<?= urlencode($assetsVersion) ?>">
  <link rel="stylesheet" href="assets/css/responsive.css?v=<?= urlencode($assetsVersion) ?>">
  <link rel="stylesheet" href="assets/css/ui.css?v=<?= urlencode($assetsVersion) ?>">
  <link rel="stylesheet" href="assets/css/visual-editor.css?v=<?= urlencode($assetsVersion) ?>">
  <?php foreach (($module_css_files ?? []) as $cssFile): ?>
  <link rel="stylesheet" href="/<?= htmlspecialchars($cssFile, ENT_QUOTES, 'UTF-8') ?>?v=<?= urlencode($assetsVersion) ?>">
  <?php endforeach; ?>
  <script>
    window.CRM = window.CRM || {};
    window.CRM.locale = <?= json_encode($currentLocale, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.CRM.messages = <?= json_encode($lang_messages ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.CRM.i18n = window.CRM.i18n || (function () {
      function getByPath(obj, key) {
        var value = obj;
        var parts = String(key || '').split('.');
        for (var i = 0; i < parts.length; i += 1) {
          if (!value || typeof value !== 'object' || !Object.prototype.hasOwnProperty.call(value, parts[i])) {
            return undefined;
          }
          value = value[parts[i]];
        }
        return value;
      }
      function t(key, fallback) {
        var value = getByPath(window.CRM.messages || {}, key);
        if (typeof value === 'string') return value;
        if (typeof fallback === 'string' && fallback !== '') return fallback;
        return String(key || '');
      }
      function applyToDom() {}
      function init() {}
      return { t: t, applyToDom: applyToDom, init: init };
    })();
    window.CRM.config = window.CRM.config || {};
    window.CRM.config.cspNonce = <?= json_encode((string)($csp_nonce ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.CRM.config.assetsVersion = <?= json_encode($assetsVersion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.CRM.config.pushVapidPublicKey = <?= json_encode($vapidPublicKey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.CRM.config.realtimeTransport = <?= json_encode($realtimeTransport, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  </script>
</head>
