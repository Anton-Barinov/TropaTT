<?php
/**
 * Plugin settings page (Moguta admin → Плагины → TropaTT CRM).
 */

if (!defined('MG')) {
    die('Access denied');
}

$tropattFields = array(
    'tropatt_enabled' => array('label' => 'Модуль включён (Y/N)', 'default' => 'N'),
    'tropatt_gateway_url' => array('label' => 'URL шлюза TropaTT', 'default' => ''),
    'tropatt_store_key' => array('label' => 'Публичный ключ витрины (stk_...)', 'default' => ''),
    'tropatt_store_secret' => array('label' => 'Секретный ключ витрины', 'default' => ''),
    'tropatt_webhook_secret' => array('label' => 'Секрет вебхука (для CRM)', 'default' => ''),
    'tropatt_default_stage' => array('label' => 'Стадия CRM по умолчанию', 'default' => 'new'),
    'tropatt_status_mapping' => array('label' => 'Маппинг: статус Moguta = стадия CRM', 'default' => "1=new\n2=in_progress\n3=done"),
    'tropatt_debug' => array('label' => 'Режим отладки (Y/N)', 'default' => 'N'),
);

$tropattSaved = false;

if (isset($_POST['tropatt_save'])) {
    foreach ($tropattFields as $tropattName => $tropattField) {
        $tropattValue = isset($_POST[$tropattName]) ? (string)$_POST[$tropattName] : (string)$tropattField['default'];
        MG::setSetting($tropattName, $tropattValue);
    }
    $tropattSaved = true;
}

$tropattWebhookUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://')
    . (string)$_SERVER['HTTP_HOST'] . '/index.php?ajax=tropattWebhook';
?>
<div class="mg-admin-block">
    <h2>TropaTT CRM</h2>
    <?php if ($tropattSaved): ?>
        <p class="mg-success">Настройки сохранены.</p>
    <?php endif; ?>
    <form method="post">
        <table class="mg-admin-table">
            <?php foreach ($tropattFields as $tropattName => $tropattField): ?>
                <tr>
                    <td><?php echo htmlspecialchars($tropattField['label']); ?></td>
                    <td>
                        <input type="text" name="<?php echo $tropattName; ?>"
                               value="<?php echo htmlspecialchars((string)mgGetSetting($tropattName, $tropattField['default'])); ?>">
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <td>URL входящих вебхуков</td>
                <td><input type="text" readonly value="<?php echo htmlspecialchars($tropattWebhookUrl); ?>"></td>
            </tr>
        </table>
        <button type="submit" name="tropatt_save" value="1">Сохранить</button>
    </form>
</div>
