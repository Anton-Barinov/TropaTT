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
$currentRoute = trim((string)($route ?? ($_GET['route'] ?? '')), '/');
$skipAiClient = in_array($currentRoute, [
  'approvals',
  'chat',
  'gantt',
  'kanban',
  'notifications',
  'profile',
  'recurring',
  'teams',
], true);
$needsAiClient = !$skipAiClient;
$needsSortable = in_array($currentRoute, [
  'kanban',
], true);
$needsRichText = in_array($currentRoute, [
  'ideas',
  'idea-detail',
  'tasks',
  'task-detail',
  'projects',
  'project-detail',
  'calendar',
  'notifications',
], true);
$needsNotificationsPush = in_array($currentRoute, [
  'notifications',
  'profile',
  'admin',
  'admin-settings',
], true);
$needsTaskActivity = in_array($currentRoute, [
  'task-detail',
], true);
$needsStickyNotes = in_array($currentRoute, [
  'dashboard',
  '',
  'index',
], true);
$needsAdminEstimates = in_array($currentRoute, [
  'admin-estimates',
], true);
$needsTaskEstimates = in_array($currentRoute, [
  'task-detail',
], true);
$needsSavedViews = in_array($currentRoute, [
  'tasks',
], true);
$needsIntake = in_array($currentRoute, [
  'intake',
], true);
$needsProjectModules = in_array($currentRoute, [
  'project-modules',
], true);
$needsPageApiBindings = !in_array($currentRoute, [
  'login',
  'password-reset-request',
  'password-reset-confirm',
  'invitation-accept',
  'chat',
  'ideas',
  'idea-detail',
  'docs',
  'admin-modules',
  'admin-module-detail',
  'admin-modules-install',
  'module-wip-limit',
], true);
?>
<script defer src="assets/vendor/bootstrap/bootstrap.bundle.min.js?v=<?= urlencode($assetsVersion) ?>"></script>
<?php if ($needsSortable): ?>
<script defer src="assets/vendor/sortable/Sortable.min.js?v=<?= urlencode($assetsVersion) ?>"></script>
<?php endif; ?>
<script defer src="assets/js/api.js?v=<?= urlencode($assetsVersion) ?>"></script>
<?php if ($needsAiClient): ?>
<script defer src="assets/js/ai.js?v=<?= urlencode($assetsVersion) ?>"></script>
<?php endif; ?>
<script defer src="assets/js/i18n.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script defer src="assets/js/tab-leader.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script defer src="assets/js/navigation.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script defer src="assets/js/ui.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script defer src="assets/js/modals.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script defer src="assets/js/drawers.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script defer src="assets/js/tabs.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script defer src="assets/js/filters.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script defer src="assets/js/tables.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script defer src="assets/js/text-utils.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script defer src="assets/js/error-utils.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script defer src="assets/js/list-utils.js?v=<?= urlencode($assetsVersion) ?>"></script>
<script defer src="assets/js/notifications.js?v=<?= urlencode($assetsVersion) ?>"></script>
<?php if ($needsRichText): ?>
<script defer src="assets/js/richtext.js?v=<?= urlencode($assetsVersion) ?>"></script>
<?php endif; ?>
<script defer src="assets/js/br1.js?v=<?= urlencode($assetsVersion) ?>"></script><?php if ($needsPageApiBindings):
?><script defer src="assets/js/page-api-bindings.js?v=<?= urlencode($assetsVersion) ?>"></script>
<?php endif;
?><?php if ($needsTaskActivity):
?><script defer src="assets/js/task/task-activity.js?v=<?= urlencode($assetsVersion) ?>"></script>
<?php endif;
?>
<?php if ($needsNotificationsPush): ?>
<script defer src="assets/js/notifications-push.js?v=<?= urlencode($assetsVersion) ?>"></script>
<?php endif; ?>
<?php if ($needsStickyNotes): ?>
<script defer src="assets/js/sticky-notes.js?v=<?= urlencode($assetsVersion) ?>"></script>
<?php endif; ?>
<?php if ($needsAdminEstimates): ?>
<script defer src="assets/js/admin-estimates.js?v=<?= urlencode($assetsVersion) ?>"></script>
<?php endif; ?>
<?php if ($needsTaskEstimates): ?>
<script defer src="assets/js/task-estimates.js?v=<?= urlencode($assetsVersion) ?>"></script>
<?php endif; ?>
<?php if ($needsSavedViews): ?>
<script defer src="assets/js/saved-views.js?v=<?= urlencode($assetsVersion) ?>"></script>
<?php endif; ?>
<?php if ($needsIntake): ?>
<script defer src="assets/js/intake.js?v=<?= urlencode($assetsVersion) ?>"></script>
<?php endif; ?>
<?php if ($needsProjectModules): ?>
<script defer src="assets/js/project-modules.js?v=<?= urlencode($assetsVersion) ?>"></script>
<?php endif; ?>
<script defer src="assets/js/app.js?v=<?= urlencode($assetsVersion) ?>"></script>
<?php foreach (($module_js_files ?? []) as $jsFile): ?>
<script defer src="/<?= htmlspecialchars($jsFile, ENT_QUOTES, 'UTF-8') ?>?v=<?= urlencode($assetsVersion) ?>"></script>
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
        <h5 class="modal-title" id="crmConfirmTitle" data-i18n="common.confirm_title"><?= htmlspecialchars($t('common.confirm_title', 'Подтвердите действие'), ENT_QUOTES, 'UTF-8') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t('common.close_aria', 'Закрыть'), ENT_QUOTES, 'UTF-8') ?>" data-i18n-aria-label="common.close_aria"></button>
      </div>
      <div class="modal-body" id="crmConfirmBody">
        <p data-i18n="common.confirm_body"><?= htmlspecialchars($t('common.confirm_body', 'Вы уверены?'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn crm-btn-secondary" data-bs-dismiss="modal" data-i18n="common.cancel_btn"><?= htmlspecialchars($t('common.cancel_btn', 'Отмена'), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn crm-btn-danger-soft" id="crmConfirmActionBtn" data-i18n="common.confirm_btn"><?= htmlspecialchars($t('common.confirm_btn', 'Подтвердить'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
</div>
</body>
</html>
