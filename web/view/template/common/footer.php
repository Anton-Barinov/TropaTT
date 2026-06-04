<?php
declare(strict_types=1);
?>
<?php
$assetsVersion = isset($assetsVersion) ? (string)$assetsVersion : '';
if ($assetsVersion === '') {
  $assetsVersion = trim((string)getenv('CRM_WEB_ASSETS_VERSION'));
}
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
?>
<script src="assets/vendor/bootstrap/bootstrap.bundle.min.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script src="assets/vendor/sortable/Sortable.min.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script src="assets/js/api.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script src="assets/js/ai.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script src="assets/js/i18n.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script src="assets/js/navigation.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script src="assets/js/ui.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script src="assets/js/modals.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script src="assets/js/drawers.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script src="assets/js/tabs.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script src="assets/js/filters.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script src="assets/js/tables.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script src="assets/js/text-utils.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script src="assets/js/error-utils.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script src="assets/js/list-utils.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script src="assets/js/notifications.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script src="assets/js/richtext.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script src="assets/js/br1.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script src="assets/js/page-api-bindings.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script src="assets/js/notifications-push.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script src="assets/js/notifications-realtime.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script src="assets/js/app.js?v=<?= urlencode($assetsVersion) ?>"></script>
<?php foreach (($module_js_files ?? []) as $jsFile): ?>
<script src="/<?= htmlspecialchars($jsFile, ENT_QUOTES, 'UTF-8') ?>?v=<?= urlencode($assetsVersion) ?>"></script>
<?php endforeach; ?>
<?php if (isset($module_js_routes) && $module_js_routes !== []): ?>
<script>
window.CRM.modules = window.CRM.modules || {};
window.CRM.modules.pageBindings = <?= json_encode($module_js_routes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<?php endif; ?>

<div class="modal fade" id="crmConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title" id="crmConfirmTitle">Подтвердите действие</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body" id="crmConfirmBody">
        <p>Вы уверены?</p>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal">Отмена</button>
        <button type="button" class="btn crm-btn-danger-soft" id="crmConfirmActionBtn">Подтвердить</button>
      </div>
    </div>
  </div>
</div>
</body>
</html>
