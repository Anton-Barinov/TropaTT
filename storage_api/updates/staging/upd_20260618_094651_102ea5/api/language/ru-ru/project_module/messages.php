<?php
declare(strict_types=1);

return [
    'page_title' => 'Модули проектов',
    'subtitle' => 'Управление функциональными модулями и направлениями внутри проектов.',
    'section_list_title' => 'Модули',
    'create_btn' => 'Создать модуль',
    'filter_project_placeholder' => 'Фильтр по проекту',
    'filter_all_projects' => 'Все проекты',

    // Table headers
    'th_title' => 'Название',
    'th_project' => 'Проект',
    'th_status' => 'Статус',
    'th_lead' => 'Ответственный',
    'th_progress' => 'Прогресс',
    'th_tasks' => 'Задачи',
    'th_target' => 'Срок',

    // Statuses
    'status_backlog' => 'Backlog',
    'status_planned' => 'Запланирован',
    'status_in_progress' => 'В работе',
    'status_paused' => 'Приостановлен',
    'status_completed' => 'Завершён',
    'status_cancelled' => 'Отменён',

    // Form fields
    'field_title' => 'Название',
    'field_title_placeholder' => 'Например: Оплата',
    'field_project' => 'Проект',
    'field_status' => 'Статус',
    'field_lead' => 'Ответственный',
    'field_color' => 'Цвет',
    'field_start_at' => 'Дата начала',
    'field_target_at' => 'Целевая дата',
    'field_description' => 'Описание',

    // Options
    'option_select_project' => 'Выберите проект...',
    'option_no_lead' => 'Не назначен',

    // Modal titles
    'modal_create_title' => 'Создать модуль',
    'modal_edit_title' => 'Редактировать модуль',

    // Messages
    'load_error' => 'Ошибка загрузки модулей',
    'no_modules' => 'Нет модулей. Создайте первый модуль.',
    'no_lead' => '—',
    'no_target' => '—',
    'error_title_required' => 'Введите название модуля',
    'error_project_required' => 'Выберите проект',
    'created' => 'Модуль создан',
    'updated' => 'Модуль обновлён',
    'save_error' => 'Ошибка сохранения модуля',

    // Archive
    'archive_title' => 'Архивировать модуль',
    'archive_confirm' => 'Архивировать этот модуль? Задачи модуля не будут удалены.',
    'archive_btn' => 'Архивировать',
    'archived' => 'Модуль архивирован',
    'archive_error' => 'Ошибка архивирования',

    // API response messages
    'api_list' => 'Модули проектов',
    'api_created' => 'Модуль проекта создан',
    'api_detail' => 'Модуль проекта',
    'api_updated' => 'Модуль проекта обновлён',
    'api_deleted' => 'Модуль проекта удалён',
    'api_archived' => 'Модуль проекта архивирован',
    'api_tasks' => 'Задачи модуля',
    'api_tasks_added' => 'Задачи добавлены в модуль',
    'api_task_removed' => 'Задача удалена из модуля',
    'api_members' => 'Участники модуля',
    'api_members_added' => 'Участники добавлены в модуль',
    'api_member_removed' => 'Участник удалён из модуля',
    'api_links' => 'Ссылки модуля',
    'api_link_added' => 'Ссылка добавлена в модуль',
    'api_link_updated' => 'Ссылка обновлена',
    'api_link_deleted' => 'Ссылка удалена',
    'api_summary' => 'Сводка модуля проекта',
    'api_not_found' => 'Модуль проекта не найден',
    'api_forbidden' => 'Доступ запрещён',
    'api_project_not_found' => 'Проект не найден',
    'api_lead_not_found' => 'Ответственный не найден',
    'api_task_not_found' => 'Задача не найдена',
    'api_link_not_found' => 'Ссылка не найдена',
    'api_task_already_exists' => 'Задача уже добавлена в модуль',
    'api_member_already_exists' => 'Участник уже добавлен в модуль',
    'api_row_version_conflict' => 'Конфликт версий, повторите попытку',
    'api_validation_error' => 'Ошибка валидации',
];
