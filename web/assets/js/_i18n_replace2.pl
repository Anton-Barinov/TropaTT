#!/usr/bin/perl
use strict;
use warnings;
use utf8;
use open ':std', ':encoding(UTF-8)';

# Read entire file from arguments
local $/;
my $content = do { local $/; <ARGV> };

my @replacements;

# Template: [search_exact, replacement_i18n_key, english_fallback]
# These are for standalone string literals only (already handled by first script, but for re-runs)

# Extended replacements with HTML context
# Format: [rusian_substring, i18n_key, english_fallback]
# These handle Russian text embedded inside HTML strings
my @html = (
  # Line 6600
  ['Открыть центр', 'js.pab.open_center', 'Open center'],
  # Line 6670
  ['Все категории', 'js.pab.all_categories', 'All categories'],
  # Lines 6708-6711 - summary parts
  ['Показано ', 'js.pab.shown', 'Showing '],
  [' из ', 'js.pab.of', ' of '],
  ['все категории', 'js.pab.all_categories_short', 'all categories'],
  ['только непрочитанные', 'js.pab.unread_only', 'unread only'],
  ['только прочитанные', 'js.pab.read_only', 'read only'],
  ['все состояния', 'js.pab.all_states', 'all states'],
  # Lines 6722-6724
  ['Уведомления не найдены', 'js.pab.notifications_not_found', 'Notifications not found'],
  ['Для выбранной категории пока нет событий. Попробуйте снять фильтр или дождитесь новых уведомлений.', 'js.pab.notifications_empty_desc', 'For the selected category, there are no events yet. Try clearing the filter or wait for new notifications.'],
  # Confirmation dialogs
  ['Отметить все прочитанным?', 'js.pab.mark_all_read_title', 'Mark all as read?'],
  ['Будет отмечено прочитанным ', 'js.pab.mark_all_body_start', 'Will be marked as read '],
  [' уведомлений. Это действие можно частично отменить вручную у отдельных уведомлений.', 'js.pab.mark_all_body_end', ' notifications. This action can be partially undone manually for individual notifications.'],
  ['Отметить', 'js.pab.mark', 'Mark'],
  ['Все уведомления отмечены как прочитанные', 'js.pab.all_marked_read', 'All notifications marked as read'],
  ['Не удалось обновить уведомления', 'js.pab.failed_update_notifications', 'Failed to update notifications'],
  ['Не удалось отметить уведомление', 'js.pab.failed_mark_notification', 'Failed to mark notification'],
  ['Не удалось вернуть уведомление в непрочитанные', 'js.pab.failed_mark_unread', 'Failed to mark notification as unread'],
  # Push
  ['Статус разрешения: ', 'js.pab.permission_status', 'Permission status: '],
  ['Активные устройства не найдены', 'js.pab.no_active_devices', 'No active devices found'],
  ['Статус подписки: не подписан', 'js.pab.subscription_status_not_subscribed', 'Subscription status: not subscribed'],
  ['Устройство', 'js.pab.device', 'Device'],
  ['Последняя активность: ', 'js.pab.last_activity', 'Last activity: '],
  ['Активна', 'js.pab.active_f', 'Active'],
  ['Неактивна', 'js.pab.inactive_f', 'Inactive'],
  ['Отключить', 'js.pab.disable', 'Disable'],
  ['Статус подписки: подключено устройств — ', 'js.pab.subscription_status_connected', 'Subscription status: devices connected — '],
  ['Обновляем список устройств...', 'js.pab.refreshing_devices', 'Refreshing device list...'],
  ['Не удалось загрузить устройства', 'js.pab.failed_load_devices', 'Failed to load devices'],
  ['Статус подписки: ошибка получения списка', 'js.pab.subscription_status_error', 'Subscription status: error retrieving list'],
  ['Push-уведомления включены', 'js.pab.push_enabled', 'Push notifications enabled'],
  ['Push-уведомления выключены', 'js.pab.push_disabled', 'Push notifications disabled'],
  ['Push недоступен в текущем браузере', 'js.pab.push_unavailable', 'Push unavailable in current browser'],
  ['Запрос разрешения push выполнен', 'js.pab.push_permission_requested', 'Push permission request completed'],
  ['Не удалось отправить push-test', 'js.pab.failed_push_test', 'Failed to send push test'],
  ['Отключить push-устройство?', 'js.pab.disable_push_device_title', 'Disable push device?'],
  ['Устройство перестанет получать push-уведомления. Подключить его снова можно будет через запрос разрешения в браузере.', 'js.pab.push_device_disable_msg', 'The device will stop receiving push notifications. It can be reconnected through a browser permission request.'],
  ['Устройство отключено от push', 'js.pab.device_disabled_push', 'Device disconnected from push'],
  ['Не удалось отключить устройство', 'js.pab.failed_disable_device', 'Failed to disable device'],
  # Sound
  ['Статус звука: недоступно', 'js.pab.sound_status_unavailable', 'Sound status: unavailable'],
  ['Статус звука: ', 'js.pab.sound_status', 'Sound status: '],
  ['включен', 'js.pab.sound_enabled', 'enabled'],
  ['выключен', 'js.pab.sound_disabled', 'disabled'],
  ['Настройки звука уведомлений сохранены', 'js.pab.sound_settings_saved', 'Notification sound settings saved'],
  ['Не удалось сохранить настройки звука', 'js.pab.failed_save_sound_settings', 'Failed to save sound settings'],
  ['Тест звука недоступен', 'js.pab.sound_test_unavailable', 'Sound test unavailable'],
  ['Тест звука воспроизведен', 'js.pab.sound_test_played', 'Sound test played'],
  ['Звук не воспроизведен. Разрешите аудио (клик по странице) или проверьте quiet hours.', 'js.pab.sound_not_played', 'Sound not played. Allow audio (click on page) or check quiet hours.'],
  # Channel matrix
  ['Нельзя отключить', 'js.pab.cannot_disable', 'Cannot disable'],
  ['Матрица уведомлений сохранена', 'js.pab.matrix_saved', 'Notification matrix saved'],
  ['Не удалось сохранить матрицу уведомлений', 'js.pab.failed_save_matrix', 'Failed to save notification matrix'],
  ['Матрица сброшена к значениям по умолчанию', 'js.pab.matrix_reset', 'Matrix reset to defaults'],
  ['Задачи', 'js.pab.tasks_label', 'Tasks'],
  ['Проекты', 'js.pab.projects_label', 'Projects'],
  ['Комментарии', 'js.pab.comments_label', 'Comments'],
  ['Упоминания', 'js.pab.mentions_label', 'Mentions'],
  ['Согласования', 'js.pab.approvals_label', 'Approvals'],
  ['Напоминания', 'js.pab.reminders_label', 'Reminders'],
  ['Система', 'js.pab.system_label', 'System'],
  ['Физлицо', 'js.pab.client_type_individual', 'Individual'],
  ['ИП', 'js.pab.client_type_sole_proprietor', 'Sole proprietor'],
  ['Юрлицо', 'js.pab.client_type_legal_entity', 'Legal entity'],
  ['Активен', 'js.pab.status_active', 'Active'],
  ['Неактивен', 'js.pab.status_inactive', 'Inactive'],
  ['Архив', 'js.pab.status_archived', 'Archived'],
  # Clients
  ['Клиент без названия', 'js.pab.client_unnamed', 'Client without name'],
  ['Укажите название клиента', 'js.pab.validate_client_name', 'Specify client name'],
  ['Сайт должен быть валидным URL (http/https)', 'js.pab.validate_website', 'Website must be a valid URL (http/https)'],
  ['Укажите юридическое наименование / ФИО ИП', 'js.pab.validate_legal_name', 'Specify legal name / full name of sole proprietor'],
  ['ИНН ИП должен содержать 12 цифр', 'js.pab.validate_inn_ip', 'Sole proprietor INN must contain 12 digits'],
  ['ОГРНИП должен содержать 15 цифр', 'js.pab.validate_ogrnip', 'OGRNIP must contain 15 digits'],
  ['ИНН юрлица должен содержать 10 цифр', 'js.pab.validate_inn_legal', 'Legal entity INN must contain 10 digits'],
  ['КПП должен содержать 9 цифр', 'js.pab.validate_kpp', 'KPP must contain 9 digits'],
  ['ОГРН должен содержать 13 цифр', 'js.pab.validate_ogrn', 'OGRN must contain 13 digits'],
  ['Поле должно содержать 20 цифр', 'js.pab.validate_20_digits', 'Field must contain 20 digits'],
  ['БИК должен содержать 9 цифр', 'js.pab.validate_bik', 'BIK must contain 9 digits'],
  ['Клиенты не найдены.', 'js.pab.clients_not_found', 'Clients not found.'],
  ['Клиенты не найдены', 'js.pab.clients_not_found_title', 'Clients not found'],
  ['Удалить клиента', 'js.pab.delete_client', 'Delete client'],
  ['Выберите хотя бы одного клиента', 'js.pab.select_at_least_one_client', 'Select at least one client'],
  ['Статус клиентов обновлен: ', 'js.pab.clients_status_updated', 'Client status updated: '],
  [', пропущено ', 'js.pab.skipped', ', skipped '],
  ['Удалено клиентов: ', 'js.pab.clients_deleted', 'Clients deleted: '],
  ['Обычный вид', 'js.pab.normal_view', 'Normal view'],
  ['Компактный вид', 'js.pab.compact_view', 'Compact view'],
  ['Вид по умолчанию', 'js.pab.default_view', 'Default view'],
  ['Название вида клиентов', 'js.pab.client_view_name', 'Client view name'],
  ['Название вида не может быть пустым', 'js.pab.view_name_required', 'View name cannot be empty'],
  ['Вид клиентов сохранен', 'js.pab.client_view_saved', 'Client view saved'],
  ['Не удалось сохранить вид клиентов', 'js.pab.failed_save_client_view', 'Failed to save client view'],
  ['Выберите сохраненный вид', 'js.pab.select_saved_view_title', 'Select a saved view'],
  ['Удалить выбранный сохраненный вид клиентов?', 'js.pab.delete_selected_client_view', 'Delete selected saved client view?'],
  ['Сохраненный вид удален', 'js.pab.saved_view_deleted', 'Saved view deleted'],
  ['Не удалось удалить вид клиентов', 'js.pab.failed_delete_client_view', 'Failed to delete client view'],
  ['Клиент удалён', 'js.pab.client_deleted', 'Client deleted'],
  ['Не удалось удалить клиента', 'js.pab.failed_delete_client', 'Failed to delete client'],
  ['Поле extra_attributes должно быть валидным JSON', 'js.pab.invalid_extra_attributes', 'Extra attributes field must be valid JSON'],
  ['Поле extra_attributes должно содержать валидный JSON-объект или массив', 'js.pab.invalid_extra_attributes_obj', 'Extra attributes field must be a valid JSON object or array'],
  ['Исправьте ошибки формы', 'js.pab.fix_form_errors', 'Fix form errors'],
  ['Клиент добавлен', 'js.pab.client_added', 'Client added'],
  ['Не удалось добавить клиента', 'js.pab.failed_add_client', 'Failed to add client'],
  ['Сначала выберите клиента', 'js.pab.select_client_first', 'Select a client first'],
  ['Клиент обновлён', 'js.pab.client_updated', 'Client updated'],
  ['Не удалось обновить клиента', 'js.pab.failed_update_client', 'Failed to update client'],
  ['Клиент для редактирования не найден в текущем списке', 'js.pab.client_edit_not_found', 'Client for editing not found in current list'],
  # Counterparties
  ['Организация', 'js.pab.cp_org', 'Organization'],
  ['Контрагент без названия', 'js.pab.cp_unnamed', 'Counterparty without name'],
  ['Укажите название контрагента', 'js.pab.cp_validate_name', 'Specify counterparty name'],
  ['Контрагенты не найдены.', 'js.pab.cp_not_found', 'Counterparties not found.'],
  ['Редактировать контрагента', 'js.pab.cp_edit', 'Edit counterparty'],
  ['Удалить контрагента', 'js.pab.cp_delete', 'Delete counterparty'],
  ['Контрагенты не найдены', 'js.pab.cp_not_found_title', 'Counterparties not found'],
  ['Измените фильтры или создайте нового контрагента.', 'js.pab.cp_empty_suggestion', 'Change filters or create a new counterparty.'],
  ['ИНН', 'js.pab.field_inn', 'INN'],
  ['Email', 'js.pab.field_email', 'Email'],
  ['Телефон', 'js.pab.field_phone', 'Phone'],
  ['Обновлен', 'js.pab.field_updated', 'Updated'],
  ['Редактировать', 'js.pab.edit', 'Edit'],
  ['Удалить', 'js.pab.delete', 'Delete'],
  ['Выберите хотя бы одного контрагента', 'js.pab.cp_select_at_least_one', 'Select at least one counterparty'],
  ['Статус контрагентов обновлен: ', 'js.pab.cp_status_updated', 'Counterparty status updated: '],
  ['Удалено контрагентов: ', 'js.pab.cp_deleted_count', 'Counterparties deleted: '],
  ['Вид контрагентов сохранен', 'js.pab.cp_view_saved', 'Counterparty view saved'],
  ['Не удалось сохранить вид контрагентов', 'js.pab.cp_failed_save_view', 'Failed to save counterparty view'],
  ['Удалить сохраненный вид?', 'js.pab.cp_delete_view_confirm', 'Delete saved view?'],
  ['Вид "', 'js.pab.view_prefix', 'View "'],
  ['" будет удален. Данные контрагентов не изменятся.', 'js.pab.cp_delete_view_body', '" will be deleted. Counterparty data will not change.'],
  ['Не удалось удалить вид контрагентов', 'js.pab.cp_failed_delete_view', 'Failed to delete counterparty view'],
  ['Контрагент удалён', 'js.pab.cp_deleted', 'Counterparty deleted'],
  ['Не удалось удалить контрагента', 'js.pab.cp_failed_delete', 'Failed to delete counterparty'],
  ['Контрагент добавлен', 'js.pab.cp_added', 'Counterparty added'],
  ['Не удалось добавить контрагента', 'js.pab.cp_failed_add', 'Failed to add counterparty'],
  ['Сначала выберите контрагента', 'js.pab.cp_select_first', 'Select a counterparty first'],
  ['Контрагент обновлён', 'js.pab.cp_updated', 'Counterparty updated'],
  ['Не удалось обновить контрагента', 'js.pab.cp_failed_update', 'Failed to update counterparty'],
  ['Контрагент для редактирования не найден в текущем списке', 'js.pab.cp_edit_not_found', 'Counterparty for editing not found in current list'],
  ['Вид по умолчанию контрагентов', 'js.pab.cp_default_view', 'Default counterparty view'],
  # Client detail
  ['Клиент не выбран', 'js.pab.client_not_selected', 'Client not selected'],
  ['Не удалось загрузить карточку клиента', 'js.pab.client_failed_load', 'Failed to load client card'],
  ['Клиент не найден', 'js.pab.client_not_found_detail', 'Client not found'],
  ['Тип клиента: ', 'js.pab.client_type_prefix', 'Client type: '],
  # Profile fields
  ['Юридическое наименование', 'js.pab.field_legal_name', 'Legal name'],
  ['КПП', 'js.pab.field_kpp', 'KPP'],
  ['ОГРН', 'js.pab.field_ogrn', 'OGRN'],
  ['ОГРНИП', 'js.pab.field_ogrnip', 'OGRNIP'],
  ['Расчетный счет', 'js.pab.field_bank_account', 'Bank account'],
  ['БИК', 'js.pab.field_bik', 'BIK'],
  ['Корр. счет', 'js.pab.field_corr_account', 'Correspondent account'],
  ['Банк', 'js.pab.field_bank', 'Bank'],
  ['Сайт', 'js.pab.field_website', 'Website'],
  ['Мессенджер', 'js.pab.field_messenger', 'Messenger'],
  ['Юридический адрес', 'js.pab.field_legal_address', 'Legal address'],
  ['Почтовый адрес', 'js.pab.field_postal_address', 'Postal address'],
  ['Статус', 'js.pab.field_status', 'Status'],
  ['Комментарий', 'js.pab.field_notes', 'Notes'],
  ['В профиле клиента пока нет заполненных полей.', 'js.pab.client_profile_empty', 'There are no filled fields in the client profile yet.'],
  ['Всего задач', 'js.pab.total_tasks', 'Total tasks'],
  ['Завершено', 'js.pab.completed', 'Completed'],
  ['Да', 'js.pab.yes', 'Yes'],
  ['Нет', 'js.pab.no', 'No'],
  ['Задача без названия', 'js.pab.task_unnamed', 'Task without name'],
  ['Проект не указан', 'js.pab.project_not_specified', 'Project not specified'],
  ['По клиенту пока нет задач.', 'js.pab.client_no_tasks', 'There are no tasks for this client yet.'],
  ['Нет дополнительных атрибутов', 'js.pab.no_extra_attributes', 'No additional attributes'],
  # AI
  ['Не удалось выполнить AI-запрос', 'js.pab.ai_request_failed', 'Failed to execute AI request'],
  ['Подготовка к встрече', 'js.pab.ai_meeting_prep', 'Meeting preparation'],
  ['Качество данных клиента', 'js.pab.ai_data_quality', 'Client data quality'],
  ['Client-safe отчет', 'js.pab.ai_safe_report', 'Client-safe report'],
  ['Сводка клиента', 'js.pab.ai_client_summary', 'Client summary'],
  ['Состояние: hidden', 'js.pab.state_hidden', 'State: hidden'],
  ['Факты отсутствуют.', 'js.pab.facts_missing', 'Facts missing.'],
  ['AI-сводка клиента не сформирована.', 'js.pab.ai_summary_not_ready', 'AI client summary not generated.'],
  ['Нажмите «AI-сводка», чтобы получить AI-предложение.', 'js.pab.ai_summary_prompt', 'Click "AI Summary" to get an AI suggestion.'],
  ['Факты из CRM отсутствуют.', 'js.pab.ai_crm_facts_missing', 'CRM facts missing.'],
  ['AI-инференсы пока не сформированы.', 'js.pab.ai_inferences_missing', 'AI inferences not yet generated.'],
  ['AI-предложение сформировано.', 'js.pab.ai_suggestion_ready', 'AI suggestion generated.'],
  ['Факты из CRM не указаны.', 'js.pab.ai_crm_facts_not_specified', 'CRM facts not specified.'],
  ['AI-инференсы отсутствуют.', 'js.pab.ai_inferences_empty', 'AI inferences absent.'],
  ['AI-сводка клиента временно недоступна.', 'js.pab.ai_summary_temporarily_unavailable', 'AI client summary temporarily unavailable.'],
  ['Не удалось выполнить AI-действие', 'js.pab.ai_action_failed', 'Failed to execute AI action'],
  ['Формируем AI-предложение...', 'js.pab.ai_generating', 'Generating AI suggestion...'],
  ['AI-сводка клиента сформирована', 'js.pab.ai_summary_generated', 'AI client summary generated'],
  ['Не удалось сформировать AI-сводку клиента', 'js.pab.ai_summary_failed', 'Failed to generate AI client summary'],
  ['Формируем AI-сводку клиента...', 'js.pab.ai_summary_generating', 'Generating AI client summary...'],
  ['AI-подготовка к встрече сформирована', 'js.pab.ai_meeting_prep_generated', 'AI meeting preparation generated'],
  ['Не удалось сформировать подготовку к встрече', 'js.pab.ai_meeting_prep_failed', 'Failed to generate meeting preparation'],
  ['Формируем подготовку к встрече...', 'js.pab.ai_meeting_prep_generating', 'Generating meeting preparation...'],
  ['AI-проверка качества данных сформирована', 'js.pab.ai_data_quality_generated', 'AI data quality check generated'],
  ['Не удалось сформировать AI-проверку качества данных', 'js.pab.ai_data_quality_failed', 'Failed to generate AI data quality check'],
  ['Формируем AI-проверку качества данных...', 'js.pab.ai_data_quality_generating', 'Generating AI data quality check...'],
  ['Client-safe AI-отчет сформирован', 'js.pab.ai_safe_report_generated', 'Client-safe AI report generated'],
  ['Не удалось сформировать client-safe AI-отчет', 'js.pab.ai_safe_report_failed', 'Failed to generate client-safe AI report'],
  ['Формируем client-safe AI-отчет...', 'js.pab.ai_safe_report_generating', 'Generating client-safe AI report...'],
  ['Сначала сформируйте AI-сводку клиента', 'js.pab.ai_summary_first', 'First generate an AI client summary'],
  ['Предпросмотр временно недоступен. Обновите AI-результат.', 'js.pab.ai_preview_unavailable', 'Preview temporarily unavailable. Refresh AI result.'],
  ['Подготавливаем предпросмотр AI-сводки клиента...', 'js.pab.ai_preview_generating', 'Preparing AI summary preview...'],
  ['Для карточки клиента действует только предпросмотр. Прямое авто-применение отключено.', 'js.pab.ai_client_preview_only', 'Only preview is available for client card. Direct auto-apply is disabled.'],
  ['Не удалось открыть предпросмотр AI-сводки клиента', 'js.pab.ai_preview_failed', 'Failed to open AI summary preview'],
  ['AI-сводка клиента не выбрана', 'js.pab.ai_summary_not_selected', 'AI client summary not selected'],
  ['Отклоняем AI-сводку клиента...', 'js.pab.ai_dismissing', 'Dismissing AI client summary...'],
  ['Не удалось отклонить AI-сводку клиента', 'js.pab.ai_dismiss_failed', 'Failed to dismiss AI client summary'],
  ['AI-сводка клиента отклонена.', 'js.pab.ai_summary_dismissed', 'AI client summary dismissed.'],
  ['AI-сводка клиента отклонена', 'js.pab.ai_summary_dismissed_notify', 'AI client summary dismissed'],
  # Client cabinet
  ['Клиент', 'js.pab.client', 'Client'],
  ['Клиентский кабинет: ', 'js.pab.client_cabinet_prefix', 'Client cabinet: '],
  ['Клиентский кабинет', 'js.pab.client_cabinet', 'Client cabinet'],
  ['Открыть кабинет', 'js.pab.open_cabinet', 'Open cabinet'],
  ['Выбран', 'js.pab.selected', 'Selected'],
  ['Выбрать', 'js.pab.select', 'Select'],
  ['Редактирование клиента: ', 'js.pab.editing_client', 'Editing client: '],
  ['Выберите клиента в таблице слева.', 'js.pab.select_client_left', 'Select a client in the table on the left.'],
  ['Клиент не выбран.', 'js.pab.client_not_selected_short', 'Client not selected.'],
  ['Юр. наименование', 'js.pab.field_legal_name_short', 'Legal name'],
  ['ФИО', 'js.pab.field_full_name', 'Full name'],
  ['По выбранному клиенту задач пока нет.', 'js.pab.client_cabinet_no_tasks', 'No tasks for the selected client yet.'],
  ['Завершено задач: ', 'js.pab.tasks_completed_prefix', 'Tasks completed: '],
  ['Нет данных по задачам клиента.', 'js.pab.client_no_task_data', 'No task data for client.'],
  ['Событий по выбранному клиенту пока нет.', 'js.pab.client_no_events', 'No events for the selected client yet.'],
  ['Комментариев по выбранному клиенту пока нет.', 'js.pab.client_no_comments', 'No comments for the selected client yet.'],
  # Profile
  ['Загрузка...', 'js.pab.loading', 'Loading...'],
  ['Сохранить изменения', 'js.pab.save_changes', 'Save changes'],
  ['Последний вход: ', 'js.pab.last_login', 'Last login: '],
  ['Активные устройства: ', 'js.pab.active_devices', 'Active devices: '],
  ['2FA: выключена', 'js.pab.two_factor_disabled', '2FA: disabled'],
  ['Не удалось открыть окно подтверждения', 'js.pab.failed_open_confirm', 'Failed to open confirmation dialog'],
  ['Подтвердите действие', 'js.pab.confirm_action', 'Confirm action'],
  ['Продолжить?', 'js.pab.continue_question', 'Continue?'],
  ['Подтвердить', 'js.pab.confirm', 'Confirm'],
  ['Профиль обновлен', 'js.pab.profile_updated', 'Profile updated'],
  ['Не удалось сохранить профиль', 'js.pab.failed_save_profile', 'Failed to save profile'],
  ['Завершить другие сессии?', 'js.pab.revoke_sessions_title', 'End other sessions?'],
  ['Все устройства кроме текущего будут выведены из аккаунта.', 'js.pab.revoke_sessions_body', 'All devices except the current one will be logged out.'],
  ['Завершить', 'js.pab.end', 'End'],
  ['Другие сессии завершены', 'js.pab.sessions_revoked', 'Other sessions ended'],
  ['Не удалось завершить сессии', 'js.pab.failed_revoke_sessions', 'Failed to end sessions'],
  ['Новый пароль должен содержать минимум 8 символов.', 'js.pab.password_min_length', 'New password must be at least 8 characters.'],
  ['Пароли не совпадают.', 'js.pab.passwords_dont_match', 'Passwords do not match.'],
  ['Пароль успешно изменен', 'js.pab.password_changed', 'Password successfully changed'],
  ['Не удалось изменить пароль', 'js.pab.failed_change_password', 'Failed to change password'],
  ['Отключить 2FA', 'js.pab.disable_2fa', 'Disable 2FA'],
  ['Включить 2FA', 'js.pab.enable_2fa', 'Enable 2FA'],
  ['Введите текущий пароль, чтобы отключить двухфакторную защиту.', 'js.pab.disable_2fa_note', 'Enter the current password to disable two-factor authentication.'],
  ['Введите текущий пароль, чтобы включить двухфакторную защиту. После включения сохраните резервные коды.', 'js.pab.enable_2fa_note', 'Enter the current password to enable two-factor authentication. Save backup codes after enabling.'],
  ['Текущий пароль обязателен.', 'js.pab.current_password_required', 'Current password is required.'],
  ['2FA отключена', 'js.pab.two_factor_disabled_notify', '2FA disabled'],
  ['2FA включена', 'js.pab.two_factor_enabled_notify', '2FA enabled'],
  ['Не удалось изменить состояние 2FA', 'js.pab.failed_change_2fa', 'Failed to change 2FA state'],
  ['Сохраните данные настройки и резервные коды до закрытия окна.', 'js.pab.save_backup_codes', 'Save these settings and backup codes before closing the window.'],
  ['Код настройки:', 'js.pab.setup_code_label', 'Setup code:'],
  ['Резервные коды:', 'js.pab.backup_codes_label', 'Backup codes:'],
  ['не получен', 'js.pab.not_received', 'not received'],
  ['2FA: включена (резервных кодов: ', 'js.pab.two_factor_enabled_with', '2FA: enabled (backup codes: '],
  # Teams
  ['Отдел', 'js.pab.department', 'Department'],
  ['Команда', 'js.pab.team_label', 'Team'],
  ['Команды не найдены.', 'js.pab.teams_not_found', 'Teams not found.'],
  ['Свернуть', 'js.pab.collapse', 'Collapse'],
  ['Развернуть', 'js.pab.expand', 'Expand'],
  ['Редактировать команду', 'js.pab.edit_team', 'Edit team'],
  ['Удалить команду', 'js.pab.delete_team', 'Delete team'],
  ['Команда удалена', 'js.pab.team_deleted', 'Team deleted'],
  ['Не удалось удалить команду', 'js.pab.failed_delete_team', 'Failed to delete team'],
  ['Без родителя', 'js.pab.no_parent', 'No parent'],
  ['По умолчанию текущий пользователь', 'js.pab.default_current_user', 'Default current user'],
  ['Не назначен', 'js.pab.not_assigned', 'Not assigned'],
  ['Укажите название', 'js.pab.enter_title', 'Enter a title'],
  ['Создание...', 'js.pab.creating', 'Creating...'],
  ['Команда создана', 'js.pab.team_created', 'Team created'],
  ['Не удалось создать команду', 'js.pab.failed_create_team', 'Failed to create team'],
  ['Создать', 'js.pab.create', 'Create'],
  ['Сохранение...', 'js.pab.saving', 'Saving...'],
  ['Команда обновлена', 'js.pab.team_updated', 'Team updated'],
  ['Не удалось обновить команду', 'js.pab.failed_update_team', 'Failed to update team'],
  ['Сохранить', 'js.pab.save', 'Save'],
  ['Есть несохраненные изменения. Закрыть без сохранения?', 'js.pab.unsaved_changes_confirm', 'There are unsaved changes. Close without saving?'],
  ['Сначала выберите команду', 'js.pab.select_team_first', 'Select a team first'],
  ['Добавлен', 'js.pab.added', 'Added'],
  ['Менеджер', 'js.pab.manager_badge', 'Manager'],
  # Admin Roles
  ['Не удалось получить список доступов.', 'js.pab.failed_load_permissions', 'Failed to load permissions list.'],
  ['Нет доступов', 'js.pab.no_permissions', 'No permissions'],
  ['Кастомная', 'js.pab.custom_role', 'Custom'],
  ['Системная', 'js.pab.system_role', 'System'],
  ['Укажите код и название роли', 'js.pab.role_code_title_required', 'Specify code and title of the role'],
  ['Роль успешно добавлена', 'js.pab.role_added', 'Role successfully added'],
  ['Не удалось добавить роль', 'js.pab.failed_add_role', 'Failed to add role'],
  ['Системная роль: изменяйте осторожно', 'js.pab.system_role_warning', 'System role: modify with caution'],
  ['Не выбран объект роли для редактирования', 'js.pab.no_role_selected', 'No role selected for editing'],
  ['Роль обновлена', 'js.pab.role_updated', 'Role updated'],
  ['Не удалось обновить роль', 'js.pab.failed_update_role', 'Failed to update role'],
  ['Роль будет удалена без возможности восстановления.', 'js.pab.role_delete_irreversible', 'The role will be deleted without possibility of recovery.'],
  ['Роль удалена', 'js.pab.role_deleted', 'Role deleted'],
  ['Не удалось удалить роль', 'js.pab.failed_delete_role', 'Failed to delete role'],
  # Admin Statuses
  ['Не удалось загрузить статусы: ', 'js.pab.failed_load_statuses', 'Failed to load statuses: '],
  ['Статусы не найдены.', 'js.pab.statuses_not_found', 'Statuses not found.'],
  ['Заполните обязательные поля статуса', 'js.pab.status_required_fields', 'Fill in the required status fields'],
  ['Статус создан', 'js.pab.status_created', 'Status created'],
  ['Не удалось создать статус', 'js.pab.failed_create_status', 'Failed to create status'],
  ['Статус обновлен', 'js.pab.status_updated', 'Status updated'],
  ['Не удалось обновить статус', 'js.pab.failed_update_status', 'Failed to update status'],
  ['Статус удален', 'js.pab.status_deleted', 'Status deleted'],
  ['Не удалось удалить статус', 'js.pab.failed_delete_status', 'Failed to delete status'],
  ['Без remap', 'js.pab.no_remap', 'No remap'],
  # Admin API Clients
  ['API-клиенты не найдены.', 'js.pab.apc_not_found', 'API clients not found.'],
  ['Без отдельных прав', 'js.pab.no_specific_scopes', 'No specific scopes'],
  ['Не активен', 'js.pab.inactive_status', 'Inactive'],
  ['Укажите название API-клиента', 'js.pab.apc_name_required', 'Specify API client name'],
  ['API-клиент создан', 'js.pab.apc_created', 'API client created'],
  ['Не удалось создать API-клиент', 'js.pab.failed_create_apc', 'Failed to create API client'],
  ['API-клиент обновлён', 'js.pab.apc_updated', 'API client updated'],
  ['Не удалось обновить API-клиент', 'js.pab.failed_update_apc', 'Failed to update API client'],
  ['API-клиент удалён', 'js.pab.apc_deleted', 'API client deleted'],
  ['Не удалось удалить API-клиент', 'js.pab.failed_delete_apc', 'Failed to delete API client'],
  ['Выберите клиента в таблице слева.', 'js.pab.select_client_left_view', 'Select a client in the table on the left.'],
  ['Выбран клиент: ', 'js.pab.selected_client', 'Selected client: '],
  # Webhooks
  ['Вебхуки не найдены.', 'js.pab.webhooks_not_found', 'Webhooks not found.'],
  ['События не заданы', 'js.pab.webhook_no_events', 'Events not specified'],
  ['Укажите title и endpoint webhook', 'js.pab.webhook_title_endpoint_required', 'Specify webhook title and endpoint'],
  ['Webhook создан', 'js.pab.webhook_created', 'Webhook created'],
  ['Не удалось создать webhook', 'js.pab.failed_create_webhook', 'Failed to create webhook'],
  ['Webhook обновлён', 'js.pab.webhook_updated', 'Webhook updated'],
  ['Не удалось обновить webhook', 'js.pab.failed_update_webhook', 'Failed to update webhook'],
  ['Webhook удалён', 'js.pab.webhook_deleted', 'Webhook deleted'],
  ['Не удалось удалить webhook', 'js.pab.failed_delete_webhook', 'Failed to delete webhook'],
  ['Test webhook отправлен: ', 'js.pab.webhook_test_sent', 'Test webhook sent: '],
  ['Не удалось отправить test webhook', 'js.pab.failed_send_test_webhook', 'Failed to send test webhook'],
  ['Выберите webhook, чтобы увидеть доставки.', 'js.pab.select_webhook_for_deliveries', 'Select a webhook to see deliveries.'],
  ['Доставок пока нет.', 'js.pab.no_deliveries_yet', 'No deliveries yet.'],
  ['Выбран webhook: ', 'js.pab.selected_webhook', 'Selected webhook: '],
  ['Выберите webhook в таблице слева.', 'js.pab.select_webhook_left', 'Select a webhook in the table on the left.'],
  # API Keys
  ['Ключи клиента: ', 'js.pab.client_keys_title', 'Client keys: '],
  ['Сначала выберите API-клиент.', 'js.pab.select_api_client_first', 'Select an API client first.'],
  ['У выбранного API-клиента пока нет ключей.', 'js.pab.apc_no_keys', 'The selected API client has no keys yet.'],
  ['Права клиента', 'js.pab.client_scopes', 'Client scopes'],
  ['Использование', 'js.pab.usage', 'Usage'],
  ['Сначала выберите API-клиент', 'js.pab.select_apc_first', 'Select an API client first'],
  ['API-ключ выпущен', 'js.pab.api_key_issued', 'API key issued'],
  ['Не удалось выпустить ключ', 'js.pab.failed_issue_key', 'Failed to issue key'],
  ['Audit-логи не найдены.', 'js.pab.audit_logs_not_found', 'Audit logs not found.'],
  ['Security-логи не найдены.', 'js.pab.security_logs_not_found', 'Security logs not found.'],
  ['Сначала выберите ключ', 'js.pab.select_key_first', 'Select a key first'],
  ['Ключ ротирован', 'js.pab.key_rotated', 'Key rotated'],
  ['Не удалось выполнить rotate ключа', 'js.pab.failed_rotate_key', 'Failed to rotate key'],
  ['Ключ отозван', 'js.pab.key_revoked', 'Key revoked'],
  ['Не удалось отозвать ключ', 'js.pab.failed_revoke_key', 'Failed to revoke key'],
  ['Выберите ключ, чтобы увидеть usage (audit).', 'js.pab.select_key_for_audit', 'Select a key to see usage (audit).'],
  ['Выберите ключ, чтобы увидеть usage (security).', 'js.pab.select_key_for_security', 'Select a key to see usage (security).'],
  ['Новый ключ (показывается один раз):', 'js.pab.new_key_once', 'New key (shown once):'],
  ['Новый ключ после rotate (показывается один раз):', 'js.pab.key_rotated_once', 'New key after rotate (shown once):'],
  # Admin Logs
  ['По выбранным фильтрам логи не найдены.', 'js.pab.logs_not_found', 'No logs found for the selected filters.'],
  ['Открыть', 'js.pab.open_log', 'Open'],
  ['Пользователь', 'js.pab.user_label', 'User'],
  ['Событие', 'js.pab.event_label', 'Event'],
  ['Объект / маршрут', 'js.pab.object_route', 'Object / route'],
  ['Время', 'js.pab.time_label', 'Time'],
  ['Технические детали', 'js.pab.technical_details', 'Technical details'],
  ['Введите user_public_id, чтобы увидеть request/security/audit активность пользователя.', 'js.pab.user_activity_hint', 'Enter user_public_id to see request/security/audit activity.'],
  # Admin Main
  ['Онлайн', 'js.pab.online', 'Online'],
  ['Проблемы', 'js.pab.issues', 'Issues'],
  ['Есть ошибки', 'js.pab.has_errors', 'Has errors'],
  ['Без ошибок', 'js.pab.no_errors', 'No errors'],
  ['Активно', 'js.pab.active_badge', 'Active'],
  ['Нет событий аудита', 'js.pab.no_audit_events', 'No audit events'],
  ['Все системы работают', 'js.pab.all_systems_operational', 'All systems operational'],
  ['Соединение активно', 'js.pab.connection_active', 'Connection active'],
  # Admin Users
  ['Неизвестная', 'js.pab.unknown_role', 'Unknown'],
  ['Без ролей', 'js.pab.no_roles', 'No roles'],
  ['Без команды', 'js.pab.no_team', 'No team'],
  ['Изменить', 'js.pab.change', 'Change'],
  ['Войти как пользователь', 'js.pab.impersonate', 'Login as user'],
  ['Название сохраненного вида', 'js.pab.saved_view_name_prompt', 'Saved view name'],
  ['Пользователи', 'js.pab.users', 'Users'],
  ['Вид сохранен', 'js.pab.view_saved', 'View saved'],
  ['Выберите сохраненный вид для удаления', 'js.pab.select_view_to_delete', 'Select a saved view to delete'],
  ['Удалить сохраненный вид?', 'js.pab.confirm_delete_view', 'Delete saved view?'],
  ['Сохраненные виды', 'js.pab.saved_views', 'Saved views'],
  ['Без названия', 'js.pab.untitled', 'Untitled'],
  ['Без роли', 'js.pab.no_role_option', 'No role'],
  ['Пользователь создан', 'js.pab.user_created', 'User created'],
  ['Пользователь обновлен', 'js.pab.user_updated', 'User updated'],
  ['Ставка себестоимости (в час)', 'js.pab.cost_rate', 'Cost rate (per hour)'],
  ['Ставка продажи (в час)', 'js.pab.bill_rate', 'Bill rate (per hour)'],
  ['Непрочитанных уведомлений нет', 'js.pab.no_unread_notifications', 'No unread notifications'],
  ['У вас пока нет уведомлений.', 'js.pab.notifications_empty', 'You have no notifications yet.'],
  ['Очистить', 'js.pab.clear', 'Clear'],
  ['Не удалось загрузить уведомления', 'js.pab.failed_load_notifications', 'Failed to load notifications'],
  # Notification counts
  ['Непрочитанных уведомлений: ', 'js.pab.unread_notifications_label', 'Unread notifications: '],
  # Confirm dialogs
  ['Удалить выбранных клиентов (', 'js.pab.confirm_delete_selected_clients', 'Delete selected clients ('],
  ['Удалить контрагента?', 'js.pab.confirm_delete_counterparty', 'Delete counterparty?'],
  ['Контрагент "', 'js.pab.counterparty_prefix', 'Counterparty "'],
  ['" будет удален. Это действие нельзя отменить.', 'js.pab.cp_delete_irreversible', '" will be deleted. This action cannot be undone.'],
  ['Удалить клиента "', 'js.pab.delete_client_prefix', 'Delete client "'],
  ['"?', 'js.pab.question_suffix', '"?'],
  ['Удалить команду? Это действие нельзя отменить.', 'js.pab.delete_team_confirm', 'Delete team? This action cannot be undone.'],
  ['Удалить выбранных контрагентов?', 'js.pab.confirm_delete_selected_cps', 'Delete selected counterparties?'],
  ['Будет удалено контрагентов: ', 'js.pab.cp_bulk_delete_body', 'Counterparties to delete: '],
  ['. Это действие нельзя отменить.', 'js.pab.irreversible_action', '. This action cannot be undone.'],
  ['Отметить', 'js.pab.mark_action', 'Mark'],
  ['Обычный вид', 'js.pab.normal_view_btn', 'Normal view'],
  # Team create/edit
  ['Создана ', 'js.pab.created_at', 'Created '],
  [' участник', 'js.pab.member_suffix', ' member'],
  ['а', 'js.pab.ka_suffix', 'a'],  # for participant plural
  ['ов', 'js.pab.ov_suffix', 's'],
  # Показано
  ['Показано ', 'js.pab.showing', 'Showing '],
);

# Sort by length descending
my @sorted = sort { length($b->[0]) <=> length($a->[0]) } @html;

for my $entry (@sorted) {
  my ($ru, $key, $en) = @$entry;
  my $q_ru = quotemeta($ru);
  my $q_en = $en;
  $q_en =~ s/'/\\'/g;
  
  # Replace Russian text where it appears inside single-quoted strings
  # Pattern: '...RU_TEXT...' -> '...' + t() + '...'
  # But only when the Russian text is inside quotes
  
  # Strategy: Find Russian text in the content and wrap it with t() call
  # We need to handle the case where the Russian text is inside a larger string literal
  
  # Simple approach: Find 'someHTML + RU + moreHTML' and split around RU
  
  # More robust: for each occurrence in the file, check if it's inside a string literal
  # We'll use a regex that matches RU text when it's part of a JS string
  
  # Actually, let's try a simpler approach: replace the Russian text everywhere
  # But only if it's not already inside a window.CRM.i18n.t() call
  
  # We'll match RU_TEXT when it appears:
  # 1. As a complete single-quoted string: 'RU_TEXT' 
  # 2. Inside HTML strings: '...>RU_TEXT<...' or "...>RU_TEXT<..."
  # 3. Inside template literals: `...RU_TEXT...`
  
  # For case 1, we replace 'RU_TEXT' with t('key', 'en')
  # For cases 2 and 3, we need to split the string
  
  # Let's use a loop with pos() to find all occurrences
  my $pos = 0;
  while ($pos < length($content)) {
    my $start = index($content, $ru, $pos);
    last if $start < 0;
    
    # Check if this RU text is already inside a t() call
    my $before = substr($content, $start - 30 > 0 ? $start - 30 : 0, 30);
    if ($before =~ /window\.CRM\.i18n\.t\s*\([^)]*\z/ || $before =~ /window\.CRM\.i18n\.t\s*\(\s*'[^']*'\s*,\s*'[^']*\z/) {
      $pos = $start + 1;
      next;
    }
    
    # Check context: is this inside a string literal?
    # Look backwards for opening quote
    my $ctx_before = substr($content, 0, $start);
    my $in_single = ($ctx_before =~ tr/'//) % 2 == 1;
    my $in_double = ($ctx_before =~ tr/"//) % 2 == 1;
    my $in_template = 0;
    # Count backtick templates (simplified)
    my @ticks = $ctx_before =~ /`/g;
    $in_template = scalar(@ticks) % 2 == 1;
    
    if ($in_single && !$in_double && !$in_template) {
      # Inside a single-quoted string. We need to split it.
      # Find the end of the string
      my $after = substr($content, $start + length($ru));
      my $end_quote = index($after, "'");
      if ($end_quote >= 0) {
        # The RU text is inside a single-quoted string that continues after
        # Replace: ...'prefix RU_TEXT suffix'...
        # With:    ...'prefix ' + t('key', 'en') + ' suffix'...
        
        # Find the opening quote
        my $open_q = rindex(substr($content, 0, $start), "'");
        
        # Split the string content around RU_TEXT
        my $prefix = '';
        my $suffix = '';
        if ($open_q >= 0) {
          $prefix = substr($content, $open_q + 1, $start - $open_q - 1);
        }
        if ($end_quote >= 0) {
          $suffix = substr($after, 0, $end_quote);
        }
        
        # Reconstruct
        my $replacement = "'" . $prefix . "' + window.CRM.i18n.t('$key', '$q_en') + '" . $suffix . "'";
        my $len = $start - $open_q + length($ru) + $end_quote + 2;
        if ($open_q >= 0) {
          substr($content, $open_q, $len) = $replacement;
          $pos = $open_q + length($replacement);
          next;
        }
      }
    } elsif ($in_double && !$in_single && !$in_template) {
      # Inside a double-quoted string
      my $after = substr($content, $start + length($ru));
      my $end_quote = index($after, '"');
      if ($end_quote >= 0) {
        my $open_q = rindex(substr($content, 0, $start), '"');
        my $prefix = '';
        my $suffix = '';
        if ($open_q >= 0) {
          $prefix = substr($content, $open_q + 1, $start - $open_q - 1);
        }
        if ($end_quote >= 0) {
          $suffix = substr($after, 0, $end_quote);
        }
        my $replacement = '"' . $prefix . '" + window.CRM.i18n.t(\'$key\', \'$q_en\') + "' . $suffix . '\"';
        my $len = $start - $open_q + length($ru) + $end_quote + 2;
        if ($open_q >= 0) {
          substr($content, $open_q, $len) = $replacement;
          $pos = $open_q + length($replacement);
          next;
        }
      }
    }
    
    $pos = $start + 1;
  }
}

print $content;
