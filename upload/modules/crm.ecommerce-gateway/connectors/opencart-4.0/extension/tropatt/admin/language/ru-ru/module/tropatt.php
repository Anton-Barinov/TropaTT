<?php
// Heading
$_['heading_title']         = 'TropaTT CRM — Шлюз синхронизации';

// Text
$_['text_extension']         = 'Расширения';
$_['text_success']           = 'Настройки модуля TropaTT CRM успешно сохранены!';
$_['text_edit']              = 'Редактирование модуля TropaTT CRM';
$_['text_connection_ok']     = 'Соединение успешно установлено! Шлюз TropaTT отвечает корректно (PONG).';
$_['text_connection_fail']   = 'Ошибка подключения: проверьте Gateway URL, ключ и секрет.';

// Tabs
$_['tab_general']            = 'Основные настройки';
$_['tab_status_mapping']     = 'Маппинг статусов';
$_['tab_order_sources']      = 'Источники заявок';
$_['tab_logs']               = 'Журнал и отладка';

// Entry
$_['entry_status']           = 'Статус модуля';
$_['entry_gateway_url']      = 'URL шлюза TropaTT';
$_['entry_store_key']        = 'Публичный ключ витрины (Store Key)';
$_['entry_store_secret']     = 'Секретный ключ (Store Secret)';
$_['entry_webhook_url']      = 'URL входящих вебхуков';
$_['entry_send_quick_order'] = 'Отправлять быстрые заказы (1 клик)';
$_['entry_send_callbacks']   = 'Отправлять обратные звонки';
$_['entry_debug']            = 'Режим отладки (логирование)';

// Help
$_['help_gateway_url']       = 'Полный URL до Ingestion API TropaTT CRM, например: https://crm.example.com/api/index.php?route=/_module/crm.ecommerce-gateway/v1';
$_['help_store_key']         = 'Ключ витрины stk_..., созданный в панели управления TropaTT CRM.';
$_['help_store_secret']      = 'Секрет витрины, полученный при регистрации в TropaTT CRM.';
$_['help_webhook_url']       = 'Скопируйте этот URL и укажите его в настройках витрины в панели TropaTT CRM.';

// Buttons
$_['button_test_connection'] = 'Проверить соединение';

// Error
$_['error_permission']       = 'У вас нет прав для управления модулем TropaTT CRM!';
$_['error_gateway_url']      = 'URL шлюза обязателен для заполнения!';
$_['error_store_key']        = 'Ключ витрины обязателен!';
$_['error_store_secret']     = 'Секретный ключ обязателен!';
