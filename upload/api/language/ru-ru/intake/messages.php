<?php
declare(strict_types=1);

return [
    // Page
    'page_title' => 'TropaTT — Входящие',
    'subtitle' => 'Сбор и обработка входящих заявок: фиксация, triage, принятие в задачу или отклонение.',

    // Filters
    'filter_all_statuses' => 'Все статусы',
    'filter_all_sources' => 'Все источники',

    // Statuses
    'status_pending' => 'Ожидает',
    'status_snoozed' => 'Отложено',
    'status_accepted' => 'Принято',
    'status_rejected' => 'Отклонено',
    'status_duplicate' => 'Дубликат',

    // Priorities
    'priority_low' => 'Низкий',
    'priority_normal' => 'Средний',
    'priority_high' => 'Высокий',
    'priority_urgent' => 'Срочный',

    // Sources
    'source_manual' => 'Вручную',
    'source_email' => 'Email',
    'source_api' => 'API',
    'source_webhook' => 'Webhook',
    'source_ai' => 'AI',
    'source_import' => 'Импорт',
    'source_system' => 'Система',

    // Buttons
    'create_btn' => 'Создать заявку',
    'accept_btn' => 'Принять',
    'reject_btn' => 'Отклонить',
    'snooze_btn' => 'Отложить',
    'duplicate_btn' => 'Дубликат',
    'reopen_btn' => 'Восстановить',
    'activities_btn' => 'История',

    // Section
    'section_list_title' => 'Заявки',

    // Table headers
    'th_id' => 'ID',
    'th_title' => 'Название',
    'th_status' => 'Статус',
    'th_priority' => 'Приор.',
    'th_source' => 'Источник',
    'th_project' => 'Проект',
    'th_client' => 'Клиент',
    'th_assignee' => 'Ответственный',
    'th_due' => 'Срок',
    'th_created' => 'Создана',

    // Modals - Create/Edit
    'modal_create_title' => 'Создать заявку',
    'modal_edit_title' => 'Редактировать заявку',
    'field_title' => 'Название',
    'field_title_placeholder' => 'Краткое описание заявки',
    'field_description' => 'Описание',
    'field_priority' => 'Приоритет',
    'field_source' => 'Источник',
    'field_assignee' => 'Ответственный',
    'field_project' => 'Проект',
    'field_client' => 'Клиент',
    'field_due_date' => 'Срок',
    'field_source_ref' => 'Ссылка источника',
    'field_source_ref_placeholder' => 'Email или внешняя ссылка',
    'option_no_assignee' => 'Не назначен',
    'option_no_project' => 'Не выбран',
    'option_no_client' => 'Не выбран',

    // Modals - Accept
    'accept_title' => 'Принять заявку',
    'accept_field_project' => 'Проект',
    'accept_confirm_btn' => 'Принять и создать задачу',

    // Modals - Reject
    'reject_title' => 'Отклонить заявку',
    'reject_field_reason' => 'Причина отклонения',
    'reject_confirm_btn' => 'Отклонить',

    // Modals - Snooze
    'snooze_title' => 'Отложить заявку',
    'snooze_field_date' => 'Отложить до',
    'snooze_confirm_btn' => 'Отложить',

    // Modals - Duplicate
    'duplicate_title' => 'Отметить как дубликат',
    'duplicate_field_target' => 'ID заявки-дубликата',
    'duplicate_field_target_placeholder' => 'Введите public_id заявки',
    'duplicate_field_target_hint' => 'Укажите public_id существующей заявки, дубликатом которой является текущая',
    'duplicate_confirm_btn' => 'Отметить дубликатом',

    // Modals - Reopen
    'reopen_title' => 'Восстановить заявку',
    'reopen_confirm' => 'Вернуть эту заявку в статус «Ожидает» для повторного рассмотрения?',
    'reopen_confirm_btn' => 'Восстановить',

    // Modals - Activities
    'activities_title' => 'История заявки',

    // Modals - Delete
    'delete_title' => 'Удалить заявку',
    'delete_confirm' => 'Удалить эту заявку? Это действие необратимо.',
    'delete_confirm_btn' => 'Удалить',

    // Messages
    'load_error' => 'Ошибка загрузки заявок',
    'no_items' => 'Нет заявок. Создайте первую заявку.',
    'created' => 'Заявка создана',
    'updated' => 'Заявка обновлена',
    'deleted' => 'Заявка удалена',
    'save_error' => 'Ошибка сохранения заявки',
    'delete_error' => 'Ошибка удаления',
    'accepted' => 'Заявка принята, задача создана',
    'accept_error' => 'Ошибка при принятии заявки',
    'rejected' => 'Заявка отклонена',
    'reject_error' => 'Ошибка при отклонении',
    'snoozed' => 'Заявка отложена',
    'snooze_error' => 'Ошибка',
    'marked_duplicate' => 'Заявка отмечена как дубликат',
    'duplicate_error' => 'Ошибка',
    'reopened' => 'Заявка восстановлена',
    'reopen_error' => 'Ошибка',

    // Validation
    'error_title_required' => 'Введите название заявки',
    'error_accept_project_required' => 'Выберите проект',
    'error_reject_reason_required' => 'Укажите причину отклонения',
    'error_snooze_date_required' => 'Укажите дату',
    'error_duplicate_target_required' => 'Укажите ID заявки-дубликата',

    // Activities
    'activities_empty' => 'История пуста',
    'activities_error' => 'Ошибка загрузки истории',
    'unknown_user' => 'Неизвестно',
    'event_created' => 'создал(а) заявку',
    'event_updated' => 'изменил(а)',
    'event_accepted' => 'принял(а) заявку',
    'event_rejected' => 'отклонил(а)',
    'event_snoozed' => 'отложил(а)',
    'event_reopened' => 'восстановил(а)',
    'event_marked_duplicate' => 'отметил(а) дубликатом',

    // Misc
    'view_task_btn' => 'Перейти к задаче',
    'field_too_long' => 'Описание превышает 65535 символов',
    'extra_json_invalid' => 'Дополнительные данные превышают 65535 символов или некорректны',

    // Bulk
    'bulk_done' => 'Массовая операция выполнена',
    'bulk_accepted' => 'Заявки приняты',
    'bulk_rejected' => 'Заявки отклонены',
    'bulk_assigned' => 'Заявки назначены',
    'bulk_snoozed' => 'Заявки отложены',
    'bulk_reopened' => 'Заявки восстановлены',
    'bulk_deleted' => 'Заявки удалены',
];
