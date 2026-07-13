<?php

declare(strict_types=1);
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }


final class Seeder
{
    private const string EMAIL_DOMAIN = '@aurora-digital.ru';
    private string $baseUrl = 'https://localhost/api/index.php?route=';
    private string $token = '';

    /** @var array<string,array<string,mixed>> */
    private array $roles = [];
    /** @var array<string,array<string,mixed>> */
    private array $users = [];
    /** @var array<string,array<string,mixed>> */
    private array $companies = [];
    /** @var array<string,array<string,mixed>> */
    private array $clients = [];
    /** @var array<string,array<string,mixed>> */
    private array $tags = [];
    /** @var array<string,array<string,mixed>> */
    private array $projects = [];
    /** @var array<string,array<string,mixed>> */
    private array $tasks = [];

    public function run(): void
    {
        $baseUrl = trim((string)getenv('CRM_SEED_API_BASE'));
        if ($baseUrl !== '') {
            $this->baseUrl = rtrim($baseUrl, '?&') . (str_contains($baseUrl, '?route=') ? '' : '?route=');
        }

        $rootLogin = trim((string)(getenv('CRM_SEED_ROOT_LOGIN') ?: getenv('CRM_TEST_ROOT_LOGIN') ?: 'root'));
        $rootPassword = trim((string)(getenv('CRM_SEED_ROOT_PASSWORD') ?: getenv('CRM_TEST_ROOT_PASSWORD') ?: ''));
        $rootToken = trim((string)(getenv('CRM_SEED_ROOT_TOKEN') ?: getenv('CRM_TEST_ROOT_TOKEN') ?: ''));
        if ($rootPassword === '') {
            throw new RuntimeException('Set CRM_SEED_ROOT_PASSWORD or CRM_TEST_ROOT_PASSWORD before running reseed');
        }

        $this->login($rootLogin, $rootPassword, $rootToken);
        $this->seedPermissionsRegistry();
        $this->seedRoles();
        $this->seedUsers();
        $this->seedDepartmentsAndTeams();
        $this->seedReferenceData();
        $this->seedCompaniesClientsContacts();
        $this->seedProjects();
        $this->seedTasksAndTaskContent();
        $this->seedApiClientAndWebhook();
        $this->printSummary();
    }

    private function login(string $login, string $password, string $tokenFactor = ''): void
    {
        $payload = [
            'login' => $login,
            'password' => $password,
        ];
        if ($tokenFactor !== '') {
            $payload['token'] = $tokenFactor;
        }

        $res = $this->request('POST', 'api/v1/auth/login', $payload, false);

        $token = (string)($res['data']['access_token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('Не удалось получить access_token');
        }
        $this->token = $token;
    }

    private function seedPermissionsRegistry(): void
    {
        $this->request('GET', 'api/v1/permissions');
    }

    private function seedRoles(): void
    {
        $permissionSets = [
            'admin_ops' => [
                'title' => 'Администратор операций',
                'permissions' => [
                    'user.view', 'user.manage', 'role.view', 'role.manage', 'project.manage', 'task.manage',
                    'team.manage', 'department.manage', 'company.manage', 'client.manage', 'contact.manage',
                    'logs.view', 'settings.manage', 'approval.manage', 'recycle_bin.manage',
                    'import.manage', 'export.manage', 'api_client.view', 'api_client.manage',
                    'webhook.manage', 'feature_flag.manage', 'organization.manage'
                ],
            ],
            'account_manager' => [
                'title' => 'Аккаунт-менеджер',
                'permissions' => ['project.manage', 'task.manage', 'client.manage', 'contact.manage', 'company.manage'],
            ],
            'project_manager' => [
                'title' => 'Проектный менеджер',
                'permissions' => ['project.manage', 'task.manage', 'team.manage', 'department.manage'],
            ],
            'teamlead_dev' => [
                'title' => 'Тимлид разработки',
                'permissions' => ['project.manage', 'task.manage', 'team.manage'],
            ],
            'developer' => [
                'title' => 'Разработчик',
                'permissions' => ['task.manage'],
            ],
            'qa_engineer' => [
                'title' => 'QA инженер',
                'permissions' => ['task.manage'],
            ],
            'designer' => [
                'title' => 'UI/UX дизайнер',
                'permissions' => ['task.manage', 'project.manage'],
            ],
            'support_engineer' => [
                'title' => 'Инженер поддержки',
                'permissions' => ['task.manage', 'client.manage'],
            ],
        ];

        foreach ($permissionSets as $code => $meta) {
            $created = $this->request('POST', 'api/v1/roles', [
                'code' => $code,
                'title' => $meta['title'],
            ]);

            $role = $created['data']['role'] ?? null;
            if (!is_array($role) || empty($role['public_id'])) {
                throw new RuntimeException('Не удалось создать роль: ' . $code);
            }
            $this->roles[$code] = $role;

            $this->request('PATCH', 'api/v1/roles/' . $role['public_id'] . '/permissions', [
                'permission_codes' => $meta['permissions'],
            ]);
        }
    }

    private function seedUsers(): void
    {
        $users = [
            ['key' => 'ceo', 'login' => 'olga.kuznetsova' . self::EMAIL_DOMAIN, 'full_name' => 'Ольга Кузнецова', 'role' => 'admin_ops'],
            ['key' => 'account_anna', 'login' => 'anna.smirnova' . self::EMAIL_DOMAIN, 'full_name' => 'Анна Смирнова', 'role' => 'account_manager'],
            ['key' => 'account_pavel', 'login' => 'pavel.lebedev' . self::EMAIL_DOMAIN, 'full_name' => 'Павел Лебедев', 'role' => 'account_manager'],
            ['key' => 'pm_irina', 'login' => 'irina.morozova' . self::EMAIL_DOMAIN, 'full_name' => 'Ирина Морозова', 'role' => 'project_manager'],
            ['key' => 'pm_dmitry', 'login' => 'dmitry.belov' . self::EMAIL_DOMAIN, 'full_name' => 'Дмитрий Белов', 'role' => 'project_manager'],
            ['key' => 'lead_nikita', 'login' => 'nikita.frolov' . self::EMAIL_DOMAIN, 'full_name' => 'Никита Фролов', 'role' => 'teamlead_dev'],
            ['key' => 'dev_alexey', 'login' => 'alexey.popov' . self::EMAIL_DOMAIN, 'full_name' => 'Алексей Попов', 'role' => 'developer'],
            ['key' => 'dev_maria', 'login' => 'maria.kiseleva' . self::EMAIL_DOMAIN, 'full_name' => 'Мария Киселева', 'role' => 'developer'],
            ['key' => 'dev_roman', 'login' => 'roman.egorov' . self::EMAIL_DOMAIN, 'full_name' => 'Роман Егоров', 'role' => 'developer'],
            ['key' => 'qa_evgenia', 'login' => 'evgenia.sokolova' . self::EMAIL_DOMAIN, 'full_name' => 'Евгения Соколова', 'role' => 'qa_engineer'],
            ['key' => 'designer_ilia', 'login' => 'ilia.zhdanov' . self::EMAIL_DOMAIN, 'full_name' => 'Илья Жданов', 'role' => 'designer'],
            ['key' => 'support_ksenia', 'login' => 'ksenia.volkova' . self::EMAIL_DOMAIN, 'full_name' => 'Ксения Волкова', 'role' => 'support_engineer'],
        ];

        foreach ($users as $user) {
            $rolePublicId = (string)($this->roles[$user['role']]['public_id'] ?? '');
            if ($rolePublicId === '') {
                throw new RuntimeException('Роль не найдена для пользователя: ' . $user['login']);
            }

            $created = $this->request('POST', 'api/v1/users', [
                'login' => $user['login'],
                'email' => $user['login'],
                'password' => 'Demo12345!',
                'full_name' => $user['full_name'],
                'locale' => 'ru-ru',
                'role_public_ids' => [$rolePublicId],
            ]);

            $item = $created['data']['user'] ?? null;
            if (!is_array($item) || empty($item['public_id'])) {
                throw new RuntimeException('Не удалось создать пользователя: ' . $user['login']);
            }
            $this->users[$user['key']] = $item;
        }
    }

    private function seedDepartmentsAndTeams(): void
    {
        $departments = [
            ['title' => 'Отдел разработки'],
            ['title' => 'Отдел управления проектами'],
            ['title' => 'Отдел клиентского сервиса'],
            ['title' => 'Отдел качества и поддержки'],
        ];

        foreach ($departments as $dep) {
            $this->request('POST', 'api/v1/departments', ['title' => $dep['title']]);
        }

        $teams = [
            ['title' => 'Frontend-команда'],
            ['title' => 'Backend-команда'],
            ['title' => 'Команда сопровождения'],
            ['title' => 'Команда QA'],
        ];

        foreach ($teams as $team) {
            $this->request('POST', 'api/v1/teams', ['title' => $team['title']]);
        }
    }

    private function seedReferenceData(): void
    {
        $taskStatuses = [
            ['code' => 'review', 'title' => 'На ревью', 'color' => '#0ea5e9', 'sort_order' => 50],
            ['code' => 'qa_testing', 'title' => 'Тестирование QA', 'color' => '#8b5cf6', 'sort_order' => 60],
            ['code' => 'ready_release', 'title' => 'Готово к релизу', 'color' => '#14b8a6', 'sort_order' => 70],
        ];

        foreach ($taskStatuses as $status) {
            $this->request('POST', 'api/v1/statuses', [
                'scope' => 'task',
                'code' => $status['code'],
                'title' => $status['title'],
                'color' => $status['color'],
                'sort_order' => $status['sort_order'],
                'is_active' => 1,
            ]);
        }

        $tags = [
            ['key' => 'seo', 'code' => 'seo', 'title' => 'SEO', 'color' => '#22c55e'],
            ['key' => 'frontend', 'code' => 'frontend', 'title' => 'Frontend', 'color' => '#3b82f6'],
            ['key' => 'backend', 'code' => 'backend', 'title' => 'Backend', 'color' => '#6366f1'],
            ['key' => 'design', 'code' => 'design', 'title' => 'Дизайн', 'color' => '#f97316'],
            ['key' => 'analytics', 'code' => 'analytics', 'title' => 'Аналитика', 'color' => '#14b8a6'],
            ['key' => 'support', 'code' => 'support', 'title' => 'Поддержка', 'color' => '#a855f7'],
            ['key' => 'integration', 'code' => 'integration', 'title' => 'Интеграции', 'color' => '#eab308'],
            ['key' => 'urgent', 'code' => 'urgent', 'title' => 'Срочно', 'color' => '#ef4444'],
        ];

        foreach ($tags as $tag) {
            $created = $this->request('POST', 'api/v1/tags', [
                'code' => $tag['code'],
                'title' => $tag['title'],
                'color' => $tag['color'],
            ]);
            $item = $created['data']['tag'] ?? null;
            if (is_array($item) && !empty($item['public_id'])) {
                $this->tags[$tag['key']] = $item;
            }
        }
    }

    private function seedCompaniesClientsContacts(): void
    {
        $companies = [
            ['key' => 'verona', 'title' => 'ООО «Верона Трэвел»'],
            ['key' => 'alpina', 'title' => 'ООО «Альпина Хоум»'],
            ['key' => 'tehnograd', 'title' => 'АО «Техноград Логистик»'],
        ];

        foreach ($companies as $company) {
            $created = $this->request('POST', 'api/v1/companies', [
                'title' => $company['title'],
            ]);
            $item = $created['data']['company'] ?? null;
            if (is_array($item) && !empty($item['public_id'])) {
                $this->companies[$company['key']] = $item;
            }
        }

        $clients = [
            ['key' => 'verona_main', 'company' => 'verona', 'title' => 'Верона Трэвел (маркетинг)', 'email' => 'marketing@verona-travel.ru', 'phone' => '+7 495 101-10-10', 'status' => 'active'],
            ['key' => 'alpina_main', 'company' => 'alpina', 'title' => 'Альпина Хоум (digital)', 'email' => 'digital@alpina-home.ru', 'phone' => '+7 495 202-20-20', 'status' => 'active'],
            ['key' => 'tehnograd_main', 'company' => 'tehnograd', 'title' => 'Техноград Логистик (web)', 'email' => 'web@tehnograd.ru', 'phone' => '+7 495 303-30-30', 'status' => 'active'],
        ];

        foreach ($clients as $client) {
            $created = $this->request('POST', 'api/v1/clients', [
                'company_public_id' => (string)$this->companies[$client['company']]['public_id'],
                'title' => $client['title'],
                'email' => $client['email'],
                'phone' => $client['phone'],
                'status' => $client['status'],
            ]);
            $item = $created['data']['client'] ?? null;
            if (is_array($item) && !empty($item['public_id'])) {
                $this->clients[$client['key']] = $item;
            }
        }

        $contacts = [
            ['full_name' => 'Екатерина Романова', 'client' => 'verona_main', 'company' => 'verona', 'email' => 'k.romanova@verona-travel.ru', 'phone' => '+7 903 111-22-33'],
            ['full_name' => 'Максим Виноградов', 'client' => 'alpina_main', 'company' => 'alpina', 'email' => 'm.vinogradov@alpina-home.ru', 'phone' => '+7 903 222-33-44'],
            ['full_name' => 'Сергей Филиппов', 'client' => 'tehnograd_main', 'company' => 'tehnograd', 'email' => 's.filippov@tehnograd.ru', 'phone' => '+7 903 333-44-55'],
        ];

        foreach ($contacts as $contact) {
            $this->request('POST', 'api/v1/contacts', [
                'company_public_id' => (string)$this->companies[$contact['company']]['public_id'],
                'client_public_id' => (string)$this->clients[$contact['client']]['public_id'],
                'full_name' => $contact['full_name'],
                'email' => $contact['email'],
                'phone' => $contact['phone'],
            ]);
        }
    }

    private function seedProjects(): void
    {
        $projects = [
            [
                'key' => 'verona_redesign',
                'title' => 'Редизайн и SEO-рост сайта Verona Travel',
                'description' => 'Редизайн корпоративного сайта, ускорение загрузки, внедрение SEO-структуры и аналитики.',
                'client' => 'verona_main',
                'manager' => 'pm_irina',
                'priority' => 'high',
                'status' => 'active',
            ],
            [
                'key' => 'alpina_support',
                'title' => 'Техподдержка и развитие интернет-магазина Alpina Home',
                'description' => 'Сопровождение каталога, интеграция CRM-форм, еженедельные релизные окна.',
                'client' => 'alpina_main',
                'manager' => 'pm_dmitry',
                'priority' => 'normal',
                'status' => 'active',
            ],
            [
                'key' => 'tehnograd_portal',
                'title' => 'B2B-портал заказов для Техноград Логистик',
                'description' => 'Личный кабинет дилеров, интеграция с ERP, дашборды KPI.',
                'client' => 'tehnograd_main',
                'manager' => 'pm_irina',
                'priority' => 'urgent',
                'status' => 'active',
            ],
            [
                'key' => 'internal_marketing',
                'title' => 'Внутренний маркетинговый сайт агентства',
                'description' => 'Пересборка лендингов услуг, кейсы, лид-магниты, отслеживание конверсий.',
                'client' => 'verona_main',
                'manager' => 'account_anna',
                'priority' => 'normal',
                'status' => 'on_hold',
            ],
            [
                'key' => 'support_automation',
                'title' => 'Автоматизация сервис-деска и SLA-эскалаций',
                'description' => 'Сквозной цикл тикетов поддержки, уведомления и отчеты SLA.',
                'client' => 'alpina_main',
                'manager' => 'support_ksenia',
                'priority' => 'high',
                'status' => 'active',
            ],
        ];

        foreach ($projects as $project) {
            $created = $this->request('POST', 'api/v1/projects', [
                'title' => $project['title'],
                'description' => $project['description'],
                'status' => $project['status'],
                'priority' => $project['priority'],
                'client_public_id' => (string)$this->clients[$project['client']]['public_id'],
            ]);

            $item = $created['data']['project'] ?? null;
            if (!is_array($item) || empty($item['public_id'])) {
                throw new RuntimeException('Не удалось создать проект: ' . $project['title']);
            }

            $patched = $this->request('PATCH', 'api/v1/projects/' . $item['public_id'], [
                'manager_user_public_id' => (string)$this->users[$project['manager']]['public_id'],
            ]);
            $patchedItem = $patched['data']['project'] ?? null;
            if (is_array($patchedItem) && !empty($patchedItem['public_id'])) {
                $item = $patchedItem;
            }

            $this->projects[$project['key']] = $item;
        }
    }

    private function seedTasksAndTaskContent(): void
    {
        $taskRows = [
            ['key' => 't1', 'project' => 'verona_redesign', 'title' => 'Подготовить карту редиректов для SEO', 'status' => 'in_progress', 'priority' => 'high', 'assignee' => 'dev_alexey', 'tags' => ['seo', 'backend']],
            ['key' => 't2', 'project' => 'verona_redesign', 'title' => 'Собрать UI-кит для новых страниц', 'status' => 'review', 'priority' => 'normal', 'assignee' => 'designer_ilia', 'tags' => ['design', 'frontend']],
            ['key' => 't3', 'project' => 'verona_redesign', 'title' => 'Внедрить Schema.org разметку туров', 'status' => 'qa_testing', 'priority' => 'high', 'assignee' => 'dev_maria', 'tags' => ['seo', 'frontend']],
            ['key' => 't4', 'project' => 'verona_redesign', 'title' => 'Настроить цели в аналитике и GA4', 'status' => 'new', 'priority' => 'normal', 'assignee' => 'account_anna', 'tags' => ['analytics']],

            ['key' => 't5', 'project' => 'alpina_support', 'title' => 'Исправить ошибки корзины на мобильных', 'status' => 'in_progress', 'priority' => 'urgent', 'assignee' => 'dev_roman', 'tags' => ['frontend', 'urgent']],
            ['key' => 't6', 'project' => 'alpina_support', 'title' => 'Обновить импорт каталога из ERP', 'status' => 'review', 'priority' => 'high', 'assignee' => 'dev_alexey', 'tags' => ['backend', 'integration']],
            ['key' => 't7', 'project' => 'alpina_support', 'title' => 'Проверить релизный чек-лист витрины', 'status' => 'qa_testing', 'priority' => 'normal', 'assignee' => 'qa_evgenia', 'tags' => ['support']],
            ['key' => 't8', 'project' => 'alpina_support', 'title' => 'Подготовить ежемесячный отчет по SLA', 'status' => 'new', 'priority' => 'normal', 'assignee' => 'support_ksenia', 'tags' => ['analytics', 'support']],

            ['key' => 't9', 'project' => 'tehnograd_portal', 'title' => 'Реализовать авторизацию дилеров через SSO', 'status' => 'in_progress', 'priority' => 'urgent', 'assignee' => 'lead_nikita', 'tags' => ['backend', 'integration', 'urgent']],
            ['key' => 't10', 'project' => 'tehnograd_portal', 'title' => 'Собрать раздел мониторинга поставок', 'status' => 'review', 'priority' => 'high', 'assignee' => 'dev_maria', 'tags' => ['frontend', 'analytics']],
            ['key' => 't11', 'project' => 'tehnograd_portal', 'title' => 'Покрыть автотестами API заказов', 'status' => 'qa_testing', 'priority' => 'high', 'assignee' => 'qa_evgenia', 'tags' => ['backend']],
            ['key' => 't12', 'project' => 'tehnograd_portal', 'title' => 'Подготовить релизную документацию', 'status' => 'ready_release', 'priority' => 'normal', 'assignee' => 'pm_irina', 'tags' => ['support']],

            ['key' => 't13', 'project' => 'internal_marketing', 'title' => 'Переписать страницу “О нас” под новый tone-of-voice', 'status' => 'new', 'priority' => 'normal', 'assignee' => 'account_pavel', 'tags' => ['design']],
            ['key' => 't14', 'project' => 'internal_marketing', 'title' => 'Сверстать блок кейсов агентства', 'status' => 'in_progress', 'priority' => 'normal', 'assignee' => 'designer_ilia', 'tags' => ['frontend', 'design']],
            ['key' => 't15', 'project' => 'internal_marketing', 'title' => 'Настроить лид-форму с UTM метками', 'status' => 'blocked', 'priority' => 'high', 'assignee' => 'dev_roman', 'tags' => ['integration']],

            ['key' => 't16', 'project' => 'support_automation', 'title' => 'Настроить маршрутизацию тикетов по приоритетам', 'status' => 'in_progress', 'priority' => 'high', 'assignee' => 'support_ksenia', 'tags' => ['support', 'urgent']],
            ['key' => 't17', 'project' => 'support_automation', 'title' => 'Интегрировать уведомления в Telegram-канал команды', 'status' => 'review', 'priority' => 'normal', 'assignee' => 'dev_alexey', 'tags' => ['integration']],
            ['key' => 't18', 'project' => 'support_automation', 'title' => 'Собрать дашборд соблюдения SLA', 'status' => 'qa_testing', 'priority' => 'normal', 'assignee' => 'account_anna', 'tags' => ['analytics', 'support']],
        ];

        $dueBase = new DateTimeImmutable('2026-04-21 12:00:00');

        foreach ($taskRows as $idx => $row) {
            $dueAt = $dueBase->modify('+' . ($idx + 1) . ' day')->format('Y-m-d H:i:s');
            $projectPublicId = (string)$this->projects[$row['project']]['public_id'];

            $created = $this->request('POST', 'api/v1/tasks', [
                'title' => $row['title'],
                'description' => "Задача по проекту «{$this->projects[$row['project']]['title']}». Контекст: реальный поток работ агентства по веб-разработке и сопровождению.",
                'project_public_id' => $projectPublicId,
                'status' => $row['status'],
                'priority' => $row['priority'],
                'due_at' => $dueAt,
            ]);

            $task = $created['data']['task'] ?? null;
            if (!is_array($task) || empty($task['public_id'])) {
                throw new RuntimeException('Не удалось создать задачу: ' . $row['title']);
            }
            $this->tasks[$row['key']] = $task;

            $tagPublicIds = [];
            foreach ($row['tags'] as $tagKey) {
                if (!empty($this->tags[$tagKey]['public_id'])) {
                    $tagPublicIds[] = (string)$this->tags[$tagKey]['public_id'];
                }
            }

            $changes = [
                'assignee_user_public_id' => (string)$this->users[$row['assignee']]['public_id'],
            ];
            if ($tagPublicIds !== []) {
                $changes['add_tag_public_ids'] = $tagPublicIds;
            }
            $this->request('POST', 'api/v1/tasks/bulk', [
                'task_public_ids' => [(string)$task['public_id']],
                'changes' => $changes,
            ]);
        }

        $this->seedTaskDetails();
    }

    private function seedTaskDetails(): void
    {
        $detailRows = [
            ['task' => 't1', 'comment' => 'Собрали список текущих URL, на очереди согласование с SEO-специалистом.', 'minutes' => 90],
            ['task' => 't5', 'comment' => 'Исправили ошибку в обработке промокода, отправили в QA.', 'minutes' => 120],
            ['task' => 't9', 'comment' => 'SSO успешно поднимается в staging, осталось провести нагрузочный тест.', 'minutes' => 160],
            ['task' => 't16', 'comment' => 'Согласовали матрицу SLA и приоритетов с отделом поддержки.', 'minutes' => 75],
        ];

        foreach ($detailRows as $detail) {
            $taskPublicId = (string)$this->tasks[$detail['task']]['public_id'];

            $this->request('POST', 'api/v1/tasks/' . $taskPublicId . '/comments', [
                'body' => $detail['comment'],
            ]);

            $subtask = $this->request('POST', 'api/v1/tasks/' . $taskPublicId . '/subtasks', [
                'title' => 'Подготовить и согласовать промежуточный результат',
                'status' => 'in_progress',
                'assignee_user_public_id' => (string)$this->users['pm_irina']['public_id'],
            ]);

            $subtask2 = $this->request('POST', 'api/v1/tasks/' . $taskPublicId . '/subtasks', [
                'title' => 'Передать задачу в QA для финальной проверки',
                'status' => 'new',
                'assignee_user_public_id' => (string)$this->users['qa_evgenia']['public_id'],
            ]);

            $checklist = $this->request('POST', 'api/v1/tasks/' . $taskPublicId . '/checklists', [
                'title' => 'Definition of Done',
            ]);

            $checklistPublicId = (string)($checklist['data']['checklist']['public_id'] ?? '');
            if ($checklistPublicId !== '') {
                $this->request('POST', 'api/v1/checklists/' . $checklistPublicId . '/items', [
                    'title' => 'Код и изменения проверены ревьюером',
                    'is_done' => 1,
                ]);
                $this->request('POST', 'api/v1/checklists/' . $checklistPublicId . '/items', [
                    'title' => 'Обновлена документация по задаче',
                    'is_done' => 0,
                ]);
                $this->request('POST', 'api/v1/checklists/' . $checklistPublicId . '/items', [
                    'title' => 'QA подтвердил прохождение тест-кейсов',
                    'is_done' => 0,
                ]);
            }

            $loggedAt = (new DateTimeImmutable('2026-04-20 10:00:00'))->modify('+' . random_int(1, 8) . ' hour')->format('Y-m-d H:i:s');
            $this->request('POST', 'api/v1/worklogs', [
                'task_public_id' => $taskPublicId,
                'minutes_spent' => (int)$detail['minutes'],
                'note' => 'Фактические трудозатраты по этапу выполнения.',
                'logged_at' => $loggedAt,
            ]);

            // Переведем вторую подзадачу для «живости» процесса
            $subtaskPublicId = (string)($subtask2['data']['subtask']['public_id'] ?? '');
            if ($subtaskPublicId !== '') {
                $this->request('PATCH', 'api/v1/subtasks/' . $subtaskPublicId, [
                    'status' => 'blocked',
                ]);
            }
        }
    }

    private function seedApiClientAndWebhook(): void
    {
        $apiClient = $this->request('POST', 'api/v1/api-clients', [
            'title' => 'Интеграция BI-дашборда агентства',
            'scopes' => ['read:projects', 'read:tasks', 'read:analytics'],
            'is_active' => 1,
        ]);

        $apiClientPublicId = (string)($apiClient['data']['api_client']['public_id'] ?? '');
        if ($apiClientPublicId !== '') {
            $this->request('POST', 'api/v1/api-clients/' . $apiClientPublicId . '/keys', [
                'scopes' => ['read:projects', 'read:tasks'],
                'expires_at' => '2027-01-01 00:00:00',
            ]);
        }

        $this->request('POST', 'api/v1/webhooks', [
            'title' => 'Webhook уведомлений релизов',
            'endpoint' => 'https://example.org/hooks/aurora-release',
            'secret' => 'demo-secret-aurora',
            'events' => ['task.updated', 'project.updated', 'comment.created'],
            'is_active' => 1,
        ]);
    }

    private function printSummary(): void
    {
        $users = $this->request('GET', 'api/v1/users');
        $roles = $this->request('GET', 'api/v1/roles');
        $projects = $this->request('GET', 'api/v1/projects');
        $tasks = $this->request('GET', 'api/v1/tasks');
        $companies = $this->request('GET', 'api/v1/companies');
        $clients = $this->request('GET', 'api/v1/clients');
        $teams = $this->request('GET', 'api/v1/teams');

        $summary = [
            'users' => count((array)($users['data']['items'] ?? [])),
            'roles' => count((array)($roles['data']['items'] ?? [])),
            'projects' => count((array)($projects['data']['items'] ?? [])),
            'tasks' => count((array)($tasks['data']['items'] ?? [])),
            'companies' => count((array)($companies['data']['items'] ?? [])),
            'clients' => count((array)($clients['data']['items'] ?? [])),
            'teams' => count((array)($teams['data']['items'] ?? [])),
        ];

        echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    }

    /**
     * @param array<string,mixed>|null $body
     * @return array<string,mixed>
     */
    private function request(string $method, string $route, ?array $body = null, bool $withAuth = true): array
    {
        $ch = curl_init();
        if ($ch === false) {
            throw new RuntimeException('curl_init failed');
        }

        $headers = ['Content-Type: application/json'];
        if ($withAuth && $this->token !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        $url = $this->baseUrl . rawurlencode($route);
        // route должен быть без rawurlencode слешей
        $url = $this->baseUrl . str_replace('%2F', '/', rawurlencode($route));
        $url = str_replace('%3F', '?', str_replace('%3D', '=', str_replace('%26', '&', $url)));

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30,
        ]);

        if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $raw = curl_exec($ch);
        if (!is_string($raw)) {
            $err = curl_error($ch);
            throw new RuntimeException('curl_exec failed: ' . $err);
        }

        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Некорректный JSON от API: HTTP ' . $http . '; body=' . substr($raw, 0, 500));
        }

        $ok = (bool)($decoded['success'] ?? false);
        if (!$ok) {
            $code = (string)($decoded['code'] ?? 'UNKNOWN');
            $message = (string)($decoded['message'] ?? '');
            throw new RuntimeException("API error {$http} {$code}: {$message}");
        }

        return $decoded;
    }
}

$seeder = new Seeder();
$seeder->run();
