<?php
declare(strict_types=1);
$currentLocale = strtolower((string)($locale ?? 'ru-ru'));
$htmlLang = str_contains($currentLocale, '-') ? explode('-', $currentLocale, 2)[0] : $currentLocale;
$assetsVersion = trim((string)getenv('CRM_WEB_ASSETS_VERSION'));
if ($assetsVersion === '') {
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
  $assetsVersion = $assetsVersionMtime > 0 ? (string)$assetsVersionMtime : '20260505-1';
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
  <?php foreach (($module_css_files ?? []) as $cssFile): ?>
  <link rel="stylesheet" href="/<?= htmlspecialchars($cssFile, ENT_QUOTES, 'UTF-8') ?>?v=<?= urlencode($assetsVersion) ?>">
  <?php endforeach; ?>
  <script>
    window.CRM = window.CRM || {};
    window.CRM.locale = <?= json_encode($currentLocale, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.CRM.messages = <?= json_encode($lang_messages ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.CRM.config = window.CRM.config || {};
    window.CRM.config.assetsVersion = <?= json_encode($assetsVersion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  </script>
</head>
