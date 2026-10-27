<?php
/**
 * Plugin settings (Shop-Script renders this schema in the plugin settings page).
 */

return array(
    'settings' => array(
        array(
            'name' => 'enabled',
            'title' => 'Модуль включён',
            'control_type' => waHtmlControl::CHECKBOX,
            'value' => 0,
        ),
        array(
            'name' => 'gateway_url',
            'title' => 'URL шлюза TropaTT',
            'control_type' => waHtmlControl::INPUT,
            'value' => '',
            'description' => 'Префикс Ingestion API: https://crm.example.com/api/index.php?route=/_module/crm.ecommerce-gateway/v1',
        ),
        array(
            'name' => 'store_key',
            'title' => 'Публичный ключ витрины (stk_...)',
            'control_type' => waHtmlControl::INPUT,
            'value' => '',
        ),
        array(
            'name' => 'store_secret',
            'title' => 'Секретный ключ витрины',
            'control_type' => waHtmlControl::INPUT,
            'value' => '',
        ),
        array(
            'name' => 'webhook_secret',
            'title' => 'Секрет вебхука (для CRM)',
            'control_type' => waHtmlControl::INPUT,
            'value' => '',
        ),
        array(
            'name' => 'default_stage',
            'title' => 'Стадия CRM по умолчанию',
            'control_type' => waHtmlControl::INPUT,
            'value' => 'new',
        ),
        array(
            'name' => 'status_mapping',
            'title' => 'Маппинг: состояние Shop-Script = стадия CRM (по одному в строке)',
            'control_type' => waHtmlControl::TEXTAREA,
            'value' => "new=new\nprocessing=in_progress\nshipped=review\ncompleted=done\ncanceled=canceled",
        ),
        array(
            'name' => 'debug',
            'title' => 'Режим отладки (журнал)',
            'control_type' => waHtmlControl::CHECKBOX,
            'value' => 0,
        ),
    ),
);
