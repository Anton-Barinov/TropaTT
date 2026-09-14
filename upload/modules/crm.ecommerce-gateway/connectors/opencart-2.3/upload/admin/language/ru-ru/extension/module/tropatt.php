<?php
// Heading
$_['heading_title']          = 'TropaTT CRM — Шлюз синхронизации';

// Text
$_['text_extension']          = 'Расширения';
$_['text_success']            = 'Настройки модуля TropaTT CRM успешно сохранены!';
$_['text_edit']               = 'Редактирование модуля TropaTT CRM';
$_['text_connection_ok']      = 'Соединение успешно установлено! Шлюз TropaTT отвечает корректно (INGESTION_PONG).';
$_['text_connection_fail']    = 'Ошибка подключения: проверьте URL шлюза, ключ и секрет.';

// Tabs
$_['tab_general']             = 'Основные настройки';
$_['tab_status_mapping']      = 'Маппинг статусов';

// Entry
$_['entry_status']            = 'Статус модуля';
$_['entry_gateway_url']       = 'URL шлюза TropaTT';
$_['entry_store_key']         = 'Публичный ключ витрины (Store Key)';
$_['entry_store_secret']      = 'Секретный ключ витрины (Store Secret)';
$_['entry_webhook_url']       = 'URL входящих вебхуков';
$_['entry_stock_url']         = 'URL синхронизации остатков';
$_['entry_debug']             = 'Режим отладки (логирование)';
$_['entry_paid_statuses']     = 'Статусы заказа «оплачен»';
$_['entry_status_mapping']    = 'Коды стадий задач CRM для обратной синхронизации статусов: new, in_progress, completed, cancelled.';

// Help
$_['help_gateway_url']        = 'Полный URL до Ingestion API TropaTT CRM, например: https://crm.example.com/api/index.php?route=/_module/crm.ecommerce-gateway/v1';
$_['help_store_key']          = 'Ключ витрины stk_..., созданный в панели управления TropaTT CRM (модуль «Шлюз интернет-магазинов»).';
$_['help_store_secret']       = 'Секрет витрины, возвращаемый один раз при создании витрины в TropaTT CRM.';
$_['help_webhook_url']        = 'Скопируйте этот URL в карточку витрины в TropaTT CRM — по нему CRM сообщает о смене стадии задачи.';
$_['help_stock_url']          = 'Пакетная синхронизация остатков: mode=pull отдаёт постраничный снимок каталога, mode=push применяет партию остатков в одной транзакции с блокировкой строк.';
$_['help_debug']              = 'Записывать обмен с CRM в system/storage/logs/tropatt.log.';
$_['help_paid_statuses']      = 'Статусы, при которых заказ считается оплаченным и уходит в CRM с признаком «оплачен». Выбор нескольких — Ctrl (Cmd) + клик.';
$_['help_status_mapping']     = 'Сопоставьте статусы заказов OpenCart со стадиями задач TropaTT CRM. При смене стадии задачи в CRM модуль автоматически переведёт заказ OpenCart в выбранный статус.';

// Columns
$_['column_order_status']     = 'Статус OpenCart';
$_['column_crm_stage']        = 'Код стадии CRM (например: new, in_progress, completed, cancelled)';

// Buttons
$_['button_test_connection']  = 'Проверить соединение';
$_['button_copy']             = 'Копировать';

// Error
$_['error_permission']        = 'У вас нет прав для управления модулем TropaTT CRM!';
$_['error_fields_required']   = 'Заполните URL шлюза, публичный ключ витрины и секретный ключ!';
$_['error_unexpected_response'] = 'Неожиданный ответ шлюза: %s. Проверьте, что URL указывает на Ingestion API TropaTT CRM.';
