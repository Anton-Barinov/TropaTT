<?php
/**
 * Module settings: Extensions → TropaTT CRM, or /bitrix/admin/settings.php?lang=ru&mid=tropatt.crm
 */

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm('Access denied');
}

Loc::loadMessages(__FILE__);
Loader::includeModule('tropatt.crm');

$module_id = 'tropatt.crm';
$tropattCrmRights = $APPLICATION->GetGroupRight($module_id);
if ($tropattCrmRights < 'R') {
    $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED'));
}

$tropattCrmDefaults = array(
    'enabled' => 'N',
    'gateway_url' => '',
    'store_key' => '',
    'store_secret' => '',
    'debug' => 'N',
    'max_attempts' => '8',
    'status_mapping' => "N=new\nF=in_progress\nP=done\nC=canceled",
);

$tropattCrmPing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    foreach ($tropattCrmDefaults as $tropattCrmKey => $tropattCrmDefault) {
        if ($tropattCrmKey === 'status_mapping') {
            $tropattCrmMapping = array();
            foreach ((array)($_POST['status_mapping_status'] ?? array()) as $tropattCrmIndex => $tropattCrmStatusId) {
                $tropattCrmStage = trim((string)($_POST['status_mapping_stage'][$tropattCrmIndex] ?? ''));
                $tropattCrmStatusId = trim((string)$tropattCrmStatusId);
                if ($tropattCrmStatusId !== '' && $tropattCrmStage !== '') {
                    $tropattCrmMapping[$tropattCrmStatusId] = $tropattCrmStage;
                }
            }
            Option::set($module_id, 'status_mapping', \Tropatt\Crm\StatusMapper::encode($tropattCrmMapping));
            continue;
        }

        Option::set($module_id, $tropattCrmKey, (string)($_POST[$tropattCrmKey] ?? $tropattCrmDefault));
    }

    if (isset($_POST['tropatt_ping'])) {
        $tropattCrmPing = (new \Tropatt\Crm\Client())->ping();
    }
}

$tropattCrmValues = array();
foreach ($tropattCrmDefaults as $tropattCrmKey => $tropattCrmDefault) {
    $tropattCrmValues[$tropattCrmKey] = Option::get($module_id, $tropattCrmKey, $tropattCrmDefault);
}

$tropattCrmMapping = \Tropatt\Crm\StatusMapper::decode($tropattCrmValues['status_mapping']);
$tropattCrmQueue = class_exists('\Tropatt\Crm\Queue') ? \Tropatt\Crm\Queue::stats() : array();
$tropattCrmWebhookUrl = (CMain::IsHTTPS() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/tropatt/v1/webhook';

$tabControl = new CAdminTabControl('tropattCrmTabs', array(
    array('DIV' => 'tropatt_main', 'TAB' => Loc::getMessage('TROPATT_CRM_TAB_MAIN'), 'TITLE' => Loc::getMessage('TROPATT_CRM_TAB_MAIN')),
    array('DIV' => 'tropatt_statuses', 'TAB' => Loc::getMessage('TROPATT_CRM_TAB_STATUSES'), 'TITLE' => Loc::getMessage('TROPATT_CRM_TAB_STATUSES')),
));
?>
<?php if ($tropattCrmPing !== null): ?>
    <div class="adm-info-message-wrap">
        <div class="adm-info-message <?= $tropattCrmPing['success'] ? 'adm-info-message-green' : 'adm-info-message-red' ?>">
            <?= $tropattCrmPing['success']
                ? Loc::getMessage('TROPATT_CRM_PING_OK')
                : htmlspecialcharsbx(Loc::getMessage('TROPATT_CRM_PING_FAIL') . ' ' . (string)$tropattCrmPing['error']) ?>
        </div>
    </div>
<?php endif; ?>

<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= urlencode($module_id) ?>&amp;lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post() ?>
    <?php $tabControl->Begin(); ?>

    <?php $tabControl->BeginNextTab(); ?>
    <tr>
        <td width="40%"><label for="tropatt_enabled"><?= Loc::getMessage('TROPATT_CRM_ENABLED') ?></label></td>
        <td width="60%">
            <select name="enabled" id="tropatt_enabled">
                <option value="Y"<?= $tropattCrmValues['enabled'] === 'Y' ? ' selected' : '' ?>><?= Loc::getMessage('TROPATT_CRM_YES') ?></option>
                <option value="N"<?= $tropattCrmValues['enabled'] !== 'Y' ? ' selected' : '' ?>><?= Loc::getMessage('TROPATT_CRM_NO') ?></option>
            </select>
        </td>
    </tr>
    <tr>
        <td><label for="tropatt_gateway"><?= Loc::getMessage('TROPATT_CRM_GATEWAY') ?></label></td>
        <td>
            <input type="text" size="60" name="gateway_url" id="tropatt_gateway" value="<?= htmlspecialcharsbx($tropattCrmValues['gateway_url']) ?>" placeholder="https://crm.example.com/api/index.php?route=/_module/crm.ecommerce-gateway/v1">
            <div class="adm-info-message"><?= Loc::getMessage('TROPATT_CRM_GATEWAY_HELP') ?></div>
        </td>
    </tr>
    <tr>
        <td><label for="tropatt_store_key"><?= Loc::getMessage('TROPATT_CRM_STORE_KEY') ?></label></td>
        <td><input type="text" size="60" name="store_key" id="tropatt_store_key" value="<?= htmlspecialcharsbx($tropattCrmValues['store_key']) ?>" placeholder="stk_..."></td>
    </tr>
    <tr>
        <td><label for="tropatt_store_secret"><?= Loc::getMessage('TROPATT_CRM_STORE_SECRET') ?></label></td>
        <td><input type="password" size="60" name="store_secret" id="tropatt_store_secret" value="<?= htmlspecialcharsbx($tropattCrmValues['store_secret']) ?>"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('TROPATT_CRM_WEBHOOK') ?></td>
        <td><input type="text" size="60" readonly value="<?= htmlspecialcharsbx($tropattCrmWebhookUrl) ?>">
            <div class="adm-info-message"><?= Loc::getMessage('TROPATT_CRM_WEBHOOK_HELP') ?></div>
        </td>
    </tr>
    <tr>
        <td><label for="tropatt_debug"><?= Loc::getMessage('TROPATT_CRM_DEBUG') ?></label></td>
        <td>
            <select name="debug" id="tropatt_debug">
                <option value="Y"<?= $tropattCrmValues['debug'] === 'Y' ? ' selected' : '' ?>><?= Loc::getMessage('TROPATT_CRM_YES') ?></option>
                <option value="N"<?= $tropattCrmValues['debug'] !== 'Y' ? ' selected' : '' ?>><?= Loc::getMessage('TROPATT_CRM_NO') ?></option>
            </select>
        </td>
    </tr>
    <tr>
        <td><label for="tropatt_attempts"><?= Loc::getMessage('TROPATT_CRM_MAX_ATTEMPTS') ?></label></td>
        <td><input type="number" min="1" max="50" name="max_attempts" id="tropatt_attempts" value="<?= (int)$tropattCrmValues['max_attempts'] ?>"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('TROPATT_CRM_QUEUE') ?></td>
        <td>
            <?= Loc::getMessage('TROPATT_CRM_QUEUE_STATS', array(
                '#PENDING#' => (int)($tropattCrmQueue['PENDING'] ?? 0),
                '#DONE#' => (int)($tropattCrmQueue['DONE'] ?? 0),
                '#ERROR#' => (int)($tropattCrmQueue['ERROR'] ?? 0),
            )) ?>
        </td>
    </tr>

    <?php $tabControl->BeginNextTab(); ?>
    <tr>
        <td colspan="2">
            <div class="adm-info-message"><?= Loc::getMessage('TROPATT_CRM_MAPPING_HELP') ?></div>
            <table class="adm-detail-content-table edit-table">
                <thead>
                <tr>
                    <th><?= Loc::getMessage('TROPATT_CRM_STATUS_ID') ?></th>
                    <th><?= Loc::getMessage('TROPATT_CRM_STAGE_CODE') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php
                $tropattCrmRows = $tropattCrmMapping;
                if ($tropattCrmRows === array()) {
                    $tropattCrmRows = array('' => '');
                }
                foreach ($tropattCrmRows as $tropattCrmStatusId => $tropattCrmStage): ?>
                    <tr>
                        <td><input type="text" name="status_mapping_status[]" value="<?= htmlspecialcharsbx((string)$tropattCrmStatusId) ?>" placeholder="F"></td>
                        <td><input type="text" name="status_mapping_stage[]" value="<?= htmlspecialcharsbx((string)$tropattCrmStage) ?>" placeholder="in_progress"></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td><input type="text" name="status_mapping_status[]" value="" placeholder="F"></td>
                    <td><input type="text" name="status_mapping_stage[]" value="" placeholder="in_progress"></td>
                </tr>
                </tbody>
            </table>
        </td>
    </tr>

    <?php $tabControl->Buttons(); ?>
    <input type="submit" name="save" class="adm-btn-save" value="<?= Loc::getMessage('TROPATT_CRM_SAVE') ?>">
    <input type="submit" name="tropatt_ping" value="<?= Loc::getMessage('TROPATT_CRM_TEST') ?>">
    <?php $tabControl->End(); ?>
</form>
