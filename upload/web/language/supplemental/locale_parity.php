<?php
declare(strict_types=1);

/**
 * Runtime parity translations for keys that are shared by several pages.
 * Keep this supplemental layer separate from generated locale catalogs so a
 * catalog regeneration cannot reintroduce mixed-language UI strings.
 */
return [
    'ar-sa' => [
        'page' => ['dashboard' => 'الرئيسية', 'home' => 'الرئيسية', 'refresh' => 'تحديث', 'reset' => 'إعادة ضبط', 'apply' => 'تطبيق'],
        'projects' => ['breadcrumb' => 'المشاريع', 'page_subtitle' => 'قائمة المشاريع مع الحالات والعملاء والفرق والمواعيد النهائية.'],
        'counterparties' => ['page_subtitle' => 'دليل موحد للجهات: كيانات قانونية وأفراد ومنشآت فردية.'],
        'analytics' => ['note_ai_explanation' => 'يشرح الذكاء الاصطناعي مؤشرات الأداء والمخاطر ولا يستبدل المقاييس الرقمية.', 'btn_ai_kpi' => 'شرح المؤشرات', 'ai_summary_empty' => 'اضغط «شرح المؤشرات» للحصول على تفسير للمقاييس الحالية.', 'ai_facts_label' => 'الحقائق / المؤشرات'],
        'profile' => ['field_email' => 'البريد الإلكتروني', 'setting_email_critical' => 'البريد للمهام الحرجة', 'setting_push_comments' => 'إشعارات التعليقات'],
        'notifications' => ['section_push' => 'إشعارات الدفع', 'label_push_enable' => 'تفعيل إشعارات الدفع', 'section_sound' => 'الصوت وساعات الهدوء', 'label_quiet_hours_enable' => 'استخدام ساعات الهدوء', 'hint_security' => 'تبقى إشعارات الأمان داخل التطبيق دائمًا لمنع فقدان الأحداث الحرجة.'],
    ],
    'ru-ru' => [
        'page' => ['dashboard' => 'Главная', 'home' => 'Главная', 'refresh' => 'Обновить', 'reset' => 'Сбросить', 'apply' => 'Применить'],
        'projects' => ['breadcrumb' => 'Проекты', 'page_subtitle' => 'Список проектов со статусами, клиентами, командами и сроками.'],
        'counterparties' => ['page_subtitle' => 'Единый справочник контрагентов: юридические лица, физические лица и ИП.'],
        'analytics' => ['note_ai_explanation' => 'ИИ объясняет KPI и риски, но не заменяет числовые метрики.', 'btn_ai_kpi' => 'Пояснить KPI', 'ai_summary_empty' => 'Нажмите «Пояснить KPI», чтобы получить объяснение текущих метрик.', 'ai_facts_label' => 'Факты / KPI'],
        'profile' => ['field_email' => 'Электронная почта', 'setting_email_critical' => 'Почта о критичных задачах', 'setting_push_comments' => 'Push для комментариев'],
        'notifications' => ['section_push' => 'Push-уведомления', 'label_push_enable' => 'Включить push-уведомления', 'section_sound' => 'Звук и часы тишины', 'label_quiet_hours_enable' => 'Использовать часы тишины', 'hint_security' => 'Уведомления безопасности всегда остаются внутри приложения, чтобы не пропустить критичные события.'],
    ],
    'en-gb' => [
        'page' => ['dashboard' => 'Home', 'home' => 'Home', 'refresh' => 'Refresh', 'reset' => 'Reset', 'apply' => 'Apply'],
        'projects' => ['breadcrumb' => 'Projects', 'page_subtitle' => 'List of projects with statuses, clients, teams and deadlines.'],
        'counterparties' => ['page_subtitle' => 'Unified counterparty directory: legal entities, individuals, sole proprietors.'],
        'analytics' => ['note_ai_explanation' => 'AI explains KPIs and risks but does not replace numeric metrics.', 'btn_ai_kpi' => 'Explain KPIs', 'ai_summary_empty' => 'Click “Explain KPIs” to get an explanation of current metrics.', 'ai_facts_label' => 'Facts / KPIs'],
        'profile' => ['field_email' => 'Email', 'setting_email_critical' => 'Email for critical tasks', 'setting_push_comments' => 'Push for comments'],
        'notifications' => ['section_push' => 'Push notifications', 'label_push_enable' => 'Enable push notifications', 'section_sound' => 'Sound & quiet hours', 'label_quiet_hours_enable' => 'Use quiet hours', 'hint_security' => 'Security notifications always remain in-app to prevent missing critical events.'],
    ],
    'de-de' => [
        'page' => ['dashboard' => 'Startseite', 'home' => 'Startseite', 'refresh' => 'Aktualisieren', 'reset' => 'Zurücksetzen', 'apply' => 'Anwenden'],
        'projects' => ['breadcrumb' => 'Projekte', 'page_subtitle' => 'Liste der Projekte mit Status, Kunden, Teams und Fristen.'],
        'counterparties' => ['page_subtitle' => 'Einheitliches Verzeichnis der Geschäftspartner: Unternehmen, Personen und Einzelunternehmen.'],
        'calendar' => ['btn_day' => 'Tag', 'btn_week' => 'Woche', 'btn_month' => 'Monat', 'btn_create_event' => 'Ereignis erstellen', 'btn_today' => 'Kalender', 'loading_calendar' => 'Kalender wird geladen...', 'loading_events' => 'Ereignisse werden geladen...', 'section_events' => 'Ereignisse des Zeitraums', 'note_events' => 'Kommende Ereignisse im ausgewählten Zeitraum.'],
        'teams' => ['btn_create' => 'Erstellen'], 'organizations' => ['search' => 'Suchen', 'th_actions' => 'Aktionen'],
        'knowledge' => ['page_title' => 'Wissensdatenbank', 'subtitle' => 'Richtlinien, Anleitungen, FAQ und Projektwissen für das Team.'],
        'chat' => ['search_label' => 'Chats durchsuchen'],
        'admin_settings' => ['link_admin' => 'Administration', 'refresh_btn' => 'Aktualisieren', 'tab_all' => 'Alle Einstellungen', 'tab_system' => 'System & Cache', 'tab_tasks' => 'Aufgabenrichtlinien', 'tab_finance' => 'Finanzen', 'tab_retention' => 'Aufbewahrung & Audit', 'tab_sysinfo' => 'Umgebung'],
    ],
    'es-es' => [
        'page' => ['dashboard' => 'Inicio', 'home' => 'Inicio', 'refresh' => 'Actualizar', 'reset' => 'Restablecer', 'apply' => 'Aplicar'],
        'projects' => ['breadcrumb' => 'Proyectos', 'page_subtitle' => 'Lista de proyectos con estados, clientes, equipos y plazos.'],
        'counterparties' => ['page_subtitle' => 'Directorio unificado de contrapartes: personas jurídicas, personas físicas y autónomos.'],
        'calendar' => ['btn_day' => 'Día', 'btn_week' => 'Semana', 'btn_month' => 'Mes', 'btn_create_event' => 'Crear evento', 'btn_today' => 'Calendario', 'loading_calendar' => 'Cargando calendario...', 'loading_events' => 'Cargando eventos...', 'section_events' => 'Eventos del periodo', 'note_events' => 'Próximos eventos del intervalo seleccionado.'],
        'teams' => ['btn_create' => 'Crear'], 'organizations' => ['search' => 'Buscar', 'th_actions' => 'Acciones'],
        'knowledge' => ['page_title' => 'Base de conocimiento', 'subtitle' => 'Políticas, instrucciones, FAQ y conocimiento de proyectos para el equipo.'],
        'chat' => ['search_label' => 'Buscar chats'],
        'admin_settings' => ['link_admin' => 'Administración', 'refresh_btn' => 'Actualizar', 'tab_all' => 'Todos los ajustes', 'tab_system' => 'Sistema y caché', 'tab_tasks' => 'Políticas de tareas', 'tab_finance' => 'Finanzas', 'tab_retention' => 'Retención y auditoría', 'tab_sysinfo' => 'Entorno'],
    ],
    'fr-fr' => [
        'page' => ['dashboard' => 'Accueil', 'home' => 'Accueil', 'refresh' => 'Actualiser', 'reset' => 'Réinitialiser', 'apply' => 'Appliquer'],
        'projects' => ['breadcrumb' => 'Projets', 'page_subtitle' => 'Liste des projets avec statuts, clients, équipes et échéances.'],
        'counterparties' => ['page_subtitle' => 'Répertoire unifié des contreparties : personnes morales, particuliers et entrepreneurs individuels.'],
        'calendar' => ['btn_day' => 'Jour', 'btn_week' => 'Semaine', 'btn_month' => 'Mois', 'btn_create_event' => 'Créer un événement', 'btn_today' => 'Calendrier', 'loading_calendar' => 'Chargement du calendrier...', 'loading_events' => 'Chargement des événements...', 'section_events' => 'Événements de la période', 'note_events' => 'Événements à venir dans la période sélectionnée.'],
        'teams' => ['btn_create' => 'Créer'], 'organizations' => ['search' => 'Rechercher', 'th_actions' => 'Actions'],
        'knowledge' => ['page_title' => 'Base de connaissances', 'subtitle' => 'Politiques, instructions, FAQ et connaissances des projets pour l’équipe.'],
        'chat' => ['search_label' => 'Rechercher dans les discussions'],
        'admin_settings' => ['link_admin' => 'Administration', 'refresh_btn' => 'Actualiser', 'tab_all' => 'Tous les paramètres', 'tab_system' => 'Système et cache', 'tab_tasks' => 'Politiques des tâches', 'tab_finance' => 'Finance', 'tab_retention' => 'Rétention et audit', 'tab_sysinfo' => 'Environnement'],
    ],
    'pt-br' => [
        'page' => ['dashboard' => 'Início', 'home' => 'Início', 'refresh' => 'Atualizar', 'reset' => 'Redefinir', 'apply' => 'Aplicar'],
        'projects' => ['breadcrumb' => 'Projetos', 'page_subtitle' => 'Lista de projetos com status, clientes, equipes e prazos.'],
        'counterparties' => ['page_subtitle' => 'Diretório unificado de contrapartes: pessoas jurídicas, pessoas físicas e empresários individuais.'],
        'calendar' => ['btn_day' => 'Dia', 'btn_week' => 'Semana', 'btn_month' => 'Mês', 'btn_create_event' => 'Criar evento', 'btn_today' => 'Calendário', 'loading_calendar' => 'Carregando calendário...', 'loading_events' => 'Carregando eventos...', 'section_events' => 'Eventos do período', 'note_events' => 'Próximos eventos no intervalo selecionado.'],
        'teams' => ['btn_create' => 'Criar'], 'organizations' => ['search' => 'Buscar', 'th_actions' => 'Ações'],
        'knowledge' => ['page_title' => 'Base de conhecimento', 'subtitle' => 'Políticas, instruções, FAQ e conhecimento dos projetos para a equipe.'],
        'chat' => ['search_label' => 'Pesquisar chats'],
        'admin_settings' => ['link_admin' => 'Administração', 'refresh_btn' => 'Atualizar', 'tab_all' => 'Todas as configurações', 'tab_system' => 'Sistema e cache', 'tab_tasks' => 'Políticas de tarefas', 'tab_finance' => 'Finanças', 'tab_retention' => 'Retenção e auditoria', 'tab_sysinfo' => 'Ambiente'],
    ],
    'zh-cn' => [
        'page' => ['dashboard' => '首页', 'home' => '首页', 'refresh' => '刷新', 'reset' => '重置', 'apply' => '应用'],
        'projects' => ['breadcrumb' => '项目', 'page_subtitle' => '包含状态、客户、团队和截止日期的项目列表。'],
        'counterparties' => ['page_subtitle' => '统一的往来单位目录：法人、个人和个体经营者。'],
        'tasks' => ['filter_cycle' => '周期', 'th_key' => '编号'],
        'calendar' => ['btn_day' => '日', 'btn_week' => '周', 'btn_month' => '月', 'btn_create_event' => '创建事件', 'btn_today' => '日历', 'loading_calendar' => '正在加载日历...', 'section_events' => '期间事件', 'note_events' => '所选时间范围内的即将发生事件。', 'section_ai_plan' => 'AI 日程区块', 'note_ai_plan' => '应用前先手动确认日程预览。', 'ai_plan_empty_title' => '尚未生成计划', 'ai_plan_empty_text' => '点击“生成预览”获取 AI 建议。', 'ai_plan_slots_hint' => '生成后将显示时间段。', 'btn_ai_generate' => '生成预览', 'btn_ai_apply' => '应用所选时间段'],
        'analytics' => ['note_ai_explanation' => 'AI 解读 KPI 和风险，但不能替代数值指标。', 'btn_ai_kpi' => '解读 KPI', 'ai_summary_empty' => '点击“解读 KPI”获取当前指标的解读。', 'ai_facts_label' => '事实 / KPI', 'ai_facts_empty' => '暂无事实。', 'ai_risks_label' => '风险与问题', 'ai_risks_empty' => '暂无风险。'],
        'organizations' => ['search' => '搜索', 'th_actions' => '操作'], 'chat' => ['search_label' => '搜索聊天'],
        'admin_settings' => ['refresh_btn' => '刷新', 'tab_all' => '所有设置', 'tab_system' => '系统和缓存', 'tab_tasks' => '任务策略', 'tab_finance' => '财务', 'tab_retention' => '保留与审计', 'tab_sysinfo' => '环境'],
    ],
];
