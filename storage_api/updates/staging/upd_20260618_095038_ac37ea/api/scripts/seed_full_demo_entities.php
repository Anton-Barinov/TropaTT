<?php

declare(strict_types=1);

final class FullDemoSeeder
{
    private string $baseUrl = 'https://localhost/api/index.php?route=';
    private string $token = '';
    private PDO $pdo;

    public function __construct()
    {
        $baseUrl = trim((string)getenv('CRM_SEED_API_BASE'));
        if ($baseUrl !== '') {
            $this->baseUrl = rtrim($baseUrl, '?&') . (str_contains($baseUrl, '?route=') ? '' : '?route=');
        }

        $this->pdo = new PDO(
            (string)(getenv('CRM_SEED_DB_DSN') ?: getenv('DB_DSN') ?: 'mysql:host=localhost;port=3306;dbname=local;charset=utf8mb4'),
            (string)(getenv('CRM_SEED_DB_USER') ?: getenv('DB_USER') ?: 'local'),
            (string)(getenv('CRM_SEED_DB_PASSWORD') ?: getenv('DB_PASSWORD') ?: ''),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    public function run(): void
    {
        $rootLogin = trim((string)(getenv('CRM_SEED_ROOT_LOGIN') ?: getenv('CRM_TEST_ROOT_LOGIN') ?: 'root'));
        $rootPassword = trim((string)(getenv('CRM_SEED_ROOT_PASSWORD') ?: getenv('CRM_TEST_ROOT_PASSWORD') ?: ''));
        $rootToken = trim((string)(getenv('CRM_SEED_ROOT_TOKEN') ?: getenv('CRM_TEST_ROOT_TOKEN') ?: ''));
        if ($rootPassword === '') {
            throw new RuntimeException('Set CRM_SEED_ROOT_PASSWORD or CRM_TEST_ROOT_PASSWORD before running full demo seed');
        }

        $this->login($rootLogin, $rootPassword, $rootToken);
        $this->seedAdditionalRolesAndUsers();
        $this->seedRemainingEntities();
        echo "[OK] Full demo entities seeded\n";
    }

    private function login(string $login, string $password, string $tokenFactor): void
    {
        $res = $this->request('POST', 'api/v1/auth/login', [
            'login' => $login,
            'password' => $password,
            'token' => $tokenFactor,
        ], false);

        $token = (string)($res['data']['access_token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('Не удалось получить токен root');
        }
        $this->token = $token;
    }

    private function seedAdditionalRolesAndUsers(): void
    {
        $rolesToCreate = [
            'seo_specialist' => ['title' => 'SEO-специалист', 'permissions' => ['project.manage', 'task.manage', 'client.manage']],
            'ppc_specialist' => ['title' => 'PPC-специалист', 'permissions' => ['project.manage', 'task.manage', 'analytics.view']],
            'content_manager' => ['title' => 'Контент-менеджер', 'permissions' => ['task.manage', 'project.manage']],
            'devops_engineer' => ['title' => 'DevOps инженер', 'permissions' => ['project.manage', 'task.manage']],
            'sales_manager' => ['title' => 'Менеджер по продажам', 'permissions' => ['client.manage', 'company.manage', 'contact.manage']],
            'finance_manager' => ['title' => 'Финансовый менеджер', 'permissions' => ['project.view', 'analytics.view', 'export.manage']],
        ];

        $existingRoles = $this->request('GET', 'api/v1/roles');
        $roleItems = (array)($existingRoles['data']['items'] ?? []);
        $roleByCode = [];
        foreach ($roleItems as $r) {
            if (is_array($r) && !empty($r['code'])) {
                $roleByCode[(string)$r['code']] = $r;
            }
        }

        foreach ($rolesToCreate as $code => $meta) {
            if (!isset($roleByCode[$code])) {
                $created = $this->request('POST', 'api/v1/roles', [
                    'code' => $code,
                    'title' => $meta['title'],
                ]);
                $role = (array)($created['data']['role'] ?? []);
                if (!empty($role['public_id'])) {
                    $this->request('PATCH', 'api/v1/roles/' . $role['public_id'] . '/permissions', [
                        'permission_codes' => $meta['permissions'],
                    ]);
                    $roleByCode[$code] = $role;
                }
            }
        }

        $usersToCreate = [
            ['login' => 'elena.seo@aurora-digital.ru', 'full_name' => 'Елена Орлова', 'role' => 'seo_specialist'],
            ['login' => 'andrey.ppc@aurora-digital.ru', 'full_name' => 'Андрей Захаров', 'role' => 'ppc_specialist'],
            ['login' => 'polina.content@aurora-digital.ru', 'full_name' => 'Полина Громова', 'role' => 'content_manager'],
            ['login' => 'sergey.devops@aurora-digital.ru', 'full_name' => 'Сергей Нестеров', 'role' => 'devops_engineer'],
            ['login' => 'viktor.sales@aurora-digital.ru', 'full_name' => 'Виктор Данилов', 'role' => 'sales_manager'],
            ['login' => 'natalia.finance@aurora-digital.ru', 'full_name' => 'Наталия Ефимова', 'role' => 'finance_manager'],
        ];

        $existingUsers = $this->request('GET', 'api/v1/users');
        $userItems = (array)($existingUsers['data']['items'] ?? []);
        $logins = [];
        foreach ($userItems as $u) {
            if (is_array($u) && !empty($u['login'])) {
                $logins[(string)$u['login']] = true;
            }
        }

        foreach ($usersToCreate as $u) {
            if (!empty($logins[$u['login']])) {
                continue;
            }

            $rolePublicId = (string)($roleByCode[$u['role']]['public_id'] ?? '');
            if ($rolePublicId === '') {
                continue;
            }

            $this->request('POST', 'api/v1/users', [
                'login' => $u['login'],
                'email' => $u['login'],
                'password' => 'Demo12345!',
                'full_name' => $u['full_name'],
                'locale' => 'ru-ru',
                'role_public_ids' => [$rolePublicId],
            ]);
        }
    }

    private function seedRemainingEntities(): void
    {
        $now = gmdate('Y-m-d H:i:s');

        $rootUser = $this->one('SELECT * FROM users WHERE is_root = 1 ORDER BY id ASC LIMIT 1');
        $pmUser = $this->one("SELECT * FROM users WHERE login = 'irina.morozova@aurora-digital.ru' LIMIT 1") ?? $rootUser;
        $devUser = $this->one("SELECT * FROM users WHERE login = 'alexey.popov@aurora-digital.ru' LIMIT 1") ?? $rootUser;

        $project = $this->one('SELECT * FROM projects ORDER BY id ASC LIMIT 1');
        $task = $this->one('SELECT * FROM tasks ORDER BY id ASC LIMIT 1');
        $task2 = $this->one('SELECT * FROM tasks ORDER BY id DESC LIMIT 1') ?? $task;
        $company = $this->one('SELECT * FROM companies ORDER BY id ASC LIMIT 1');
        $client = $this->one('SELECT * FROM clients ORDER BY id ASC LIMIT 1');

        if (!$rootUser || !$project || !$task || !$company || !$client) {
            throw new RuntimeException('Недостаточно базовых данных для расширенного сидирования');
        }

        $this->insertIfEmpty('organizations', [
            'public_id' => $this->pid('org'),
            'title' => 'Aurora Digital Group',
            'slug' => 'aurora-digital-group',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $org = $this->one('SELECT * FROM organizations ORDER BY id ASC LIMIT 1');

        $this->insertIfEmpty('organization_memberships', [
            'public_id' => $this->pid('orgm'),
            'organization_id' => (int)$org['id'],
            'user_id' => (int)$rootUser['id'],
            'role_code' => 'owner',
            'created_at' => $now,
        ]);

        $this->insertIfEmpty('feature_flags', [
            'public_id' => $this->pid('ff'),
            'code' => 'crm.demo_mode_enabled',
            'is_enabled' => 1,
            'payload' => json_encode(['label' => 'Demo mode for agency', 'owner' => 'ops'], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insertIfEmpty('settings', [
            'public_id' => $this->pid('set'),
            'scope' => 'system',
            'name' => 'demo_company_profile',
            'value' => json_encode([
                'company_name' => 'Aurora Digital',
                'specialization' => ['web-development', 'support', 'seo', 'ppc'],
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insertIfEmpty('business_calendars', [
            'public_id' => $this->pid('cal'),
            'title' => 'Производственный календарь агентства',
            'timezone' => 'Europe/Moscow',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $calendar = $this->one('SELECT * FROM business_calendars ORDER BY id ASC LIMIT 1');

        $this->insertIfEmpty('holidays', [
            'public_id' => $this->pid('hol'),
            'calendar_id' => (int)$calendar['id'],
            'holiday_date' => '2026-05-01',
            'title' => 'Праздник Весны и Труда',
            'created_at' => $now,
        ]);

        $this->insertIfEmpty('working_hours', [
            'public_id' => $this->pid('wh'),
            'calendar_id' => (int)$calendar['id'],
            'weekday' => 1,
            'start_time' => '10:00',
            'end_time' => '19:00',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insertIfEmpty('calendar_events', [
            'public_id' => $this->pid('cev'),
            'title' => 'Планерка по SEO/поддержке клиентов',
            'starts_at' => '2026-04-24 10:00:00',
            'ends_at' => '2026-04-24 11:00:00',
            'owner_user_id' => (int)$pmUser['id'],
            'project_id' => (int)$project['id'],
            'task_id' => (int)$task['id'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insertIfEmpty('notifications', [
            'public_id' => $this->pid('not'),
            'user_id' => (int)$pmUser['id'],
            'category' => 'task',
            'title' => 'Новый клиентский релиз',
            'body' => 'Назначен релиз по проекту клиента Verona Travel.',
            'is_read' => 0,
            'created_at' => $now,
            'read_at' => null,
        ]);

        $this->insertIfEmpty('reminders', [
            'public_id' => $this->pid('rem'),
            'user_id' => (int)$pmUser['id'],
            'task_id' => (int)$task['id'],
            'remind_at' => '2026-04-24 09:30:00',
            'status' => 'scheduled',
            'created_at' => $now,
        ]);

        $this->insertIfEmpty('milestones', [
            'public_id' => $this->pid('mil'),
            'project_id' => (int)$project['id'],
            'title' => 'Сдать MVP клиенту',
            'due_at' => '2026-05-10 18:00:00',
            'status' => 'in_progress',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insertIfEmpty('task_dependencies', [
            'public_id' => $this->pid('dep'),
            'task_id' => (int)$task2['id'],
            'depends_on_task_id' => (int)$task['id'],
            'dependency_type' => 'blocks',
            'created_at' => $now,
        ]);

        $this->insertIfEmpty('task_status_history', [
            'public_id' => $this->pid('tsh'),
            'task_id' => (int)$task['id'],
            'old_status' => 'new',
            'new_status' => 'in_progress',
            'changed_by_user_id' => (int)$pmUser['id'],
            'created_at' => $now,
        ]);

        $this->insertIfEmpty('task_assignees', [
            'task_id' => (int)$task['id'],
            'user_id' => (int)$devUser['id'],
            'created_at' => $now,
        ]);

        $this->insertIfEmpty('task_watchers', [
            'task_id' => (int)$task['id'],
            'user_id' => (int)$pmUser['id'],
            'created_at' => $now,
        ]);

        $this->insertIfEmpty('subtasks', [
            'public_id' => $this->pid('sub'),
            'task_id' => (int)$task['id'],
            'title' => 'Подготовить финальный smoke-check перед релизом',
            'status_code' => 'in_progress',
            'assignee_user_id' => (int)$devUser['id'],
            'sort_order' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insertIfEmpty('task_templates', [
            'public_id' => $this->pid('ttpl'),
            'title' => 'Шаблон SEO-аудита страницы',
            'payload' => json_encode(['checklist' => ['Title/H1', 'Meta description', 'Core Web Vitals']], JSON_UNESCAPED_UNICODE),
            'is_active' => 1,
            'created_by_user_id' => (int)$pmUser['id'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insertIfEmpty('project_templates', [
            'public_id' => $this->pid('ptpl'),
            'title' => 'Шаблон сопровождения сайта',
            'payload' => json_encode(['stages' => ['Бриф', 'Аналитика', 'Релиз', 'Поддержка']], JSON_UNESCAPED_UNICODE),
            'is_active' => 1,
            'created_by_user_id' => (int)$pmUser['id'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insertIfEmpty('custom_fields', [
            'public_id' => $this->pid('cfd'),
            'scope' => 'task',
            'code' => 'client_impact_score',
            'title' => 'Влияние на клиента',
            'type' => 'number',
            'options' => json_encode(['min' => 1, 'max' => 10], JSON_UNESCAPED_UNICODE),
            'is_required' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $field = $this->one('SELECT * FROM custom_fields ORDER BY id ASC LIMIT 1');

        $this->insertIfEmpty('custom_field_values', [
            'public_id' => $this->pid('cfv'),
            'field_id' => (int)$field['id'],
            'entity_type' => 'task',
            'entity_public_id' => (string)$task['public_id'],
            'value' => '8',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insertIfEmpty('saved_views', [
            'public_id' => $this->pid('svw'),
            'user_id' => (int)$pmUser['id'],
            'entity_type' => 'task',
            'title' => 'Критичные задачи клиентов',
            'filters' => json_encode(['priority' => 'urgent', 'status' => 'in_progress'], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insertIfEmpty('favorites', [
            'public_id' => $this->pid('fav'),
            'user_id' => (int)$pmUser['id'],
            'entity_type' => 'project',
            'entity_public_id' => (string)$project['public_id'],
            'created_at' => $now,
        ]);

        $comment = $this->one('SELECT * FROM comments ORDER BY id ASC LIMIT 1');
        if ($comment) {
            $this->insertIfEmpty('mentions', [
                'public_id' => $this->pid('men'),
                'entity_type' => 'comment',
                'entity_public_id' => (string)$comment['public_id'],
                'mentioned_user_id' => (int)$devUser['id'],
                'created_at' => $now,
            ]);

            $this->insertIfEmpty('reactions', [
                'public_id' => $this->pid('rea'),
                'entity_type' => 'comment',
                'entity_public_id' => (string)$comment['public_id'],
                'user_id' => (int)$pmUser['id'],
                'reaction' => 'like',
                'created_at' => $now,
            ]);

            $this->insertIfEmpty('subscriptions', [
                'public_id' => $this->pid('subr'),
                'entity_type' => 'comment',
                'entity_public_id' => (string)$comment['public_id'],
                'user_id' => (int)$pmUser['id'],
                'created_at' => $now,
            ]);
        }

        $this->insertIfEmpty('comment_drafts', [
            'public_id' => $this->pid('cd'),
            'user_id' => (int)$pmUser['id'],
            'task_id' => (int)$task['id'],
            'body' => 'Черновик комментария для клиента: согласовать сроки и KPI релиза.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insertIfEmpty('sla_policies', [
            'public_id' => $this->pid('sla'),
            'title' => 'SLA сопровождения e-commerce',
            'response_minutes' => 30,
            'resolve_minutes' => 240,
            'escalation_payload' => json_encode(['escalate_to' => 'support_lead', 'notify' => ['email', 'telegram']], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insertIfEmpty('approval_requests', [
            'public_id' => $this->pid('apr'),
            'entity_type' => 'release',
            'entity_public_id' => (string)$project['public_id'],
            'requester_user_id' => (int)$pmUser['id'],
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $approvalReq = $this->one('SELECT * FROM approval_requests ORDER BY id ASC LIMIT 1');

        $this->insertIfEmpty('approval_steps', [
            'public_id' => $this->pid('aps'),
            'request_id' => (int)$approvalReq['id'],
            'reviewer_user_id' => (int)$rootUser['id'],
            'status' => 'pending',
            'comment' => 'Ожидает согласования бюджета и сроков релиза.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insertIfEmpty('automation_rules', [
            'public_id' => $this->pid('ar'),
            'title' => 'Эскалация просроченных клиентских задач',
            'trigger_code' => 'task.overdue',
            'action_code' => 'notify.manager',
            'payload' => json_encode(['channel' => 'notification', 'severity' => 'high'], JSON_UNESCAPED_UNICODE),
            'is_enabled' => 1,
            'created_by_user_id' => (int)$rootUser['id'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $rule = $this->one('SELECT * FROM automation_rules ORDER BY id ASC LIMIT 1');

        $this->insertIfEmpty('automation_runs', [
            'public_id' => $this->pid('arr'),
            'rule_id' => (int)$rule['id'],
            'status' => 'success',
            'error' => null,
            'created_at' => $now,
        ]);

        $this->insertIfEmpty('recurring_rules', [
            'public_id' => $this->pid('rr'),
            'entity_type' => 'task',
            'entity_public_id' => (string)$task['public_id'],
            'rrule' => 'FREQ=WEEKLY;INTERVAL=1;BYDAY=MO',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $rr = $this->one('SELECT * FROM recurring_rules ORDER BY id ASC LIMIT 1');

        $this->insertIfEmpty('recurring_instances', [
            'public_id' => $this->pid('ri'),
            'rule_id' => (int)$rr['id'],
            'entity_public_id' => (string)$task2['public_id'],
            'generated_at' => $now,
            'created_at' => $now,
        ]);

        $this->insertIfEmpty('import_jobs', [
            'public_id' => $this->pid('imp'),
            'user_id' => (int)$pmUser['id'],
            'type' => 'clients_csv',
            'status' => 'done',
            'payload' => json_encode(['file' => 'clients_demo.csv'], JSON_UNESCAPED_UNICODE),
            'result' => json_encode(['created' => 12, 'updated' => 3], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insertIfEmpty('export_jobs', [
            'public_id' => $this->pid('exp'),
            'user_id' => (int)$pmUser['id'],
            'type' => 'projects_xlsx',
            'status' => 'done',
            'payload' => json_encode(['range' => 'monthly'], JSON_UNESCAPED_UNICODE),
            'result' => json_encode(['file' => 'projects_monthly_demo.xlsx'], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insertIfEmpty('webhook_deliveries', [
            'public_id' => $this->pid('whd'),
            'webhook_id' => (int)$this->one('SELECT * FROM webhook_subscriptions ORDER BY id ASC LIMIT 1')['id'],
            'event_code' => 'task.updated',
            'status' => 'sent',
            'response_code' => 200,
            'created_at' => $now,
        ]);

        $this->insertIfEmpty('idempotency_keys', [
            'public_id' => $this->pid('idm'),
            'key_hash' => hash('sha256', 'demo-idempotency-key-1'),
            'route' => '/api/v1/projects',
            'response_payload' => json_encode(['code' => 'PROJECT_CREATED'], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
        ]);

        $this->insertIfEmpty('sync_state', [
            'public_id' => $this->pid('syn'),
            'user_id' => (int)$pmUser['id'],
            'scope' => 'tasks',
            'cursor_value' => 'cursor_demo_20260423_001',
            'updated_at' => $now,
        ]);

        $this->insertIfEmpty('files', [
            'public_id' => $this->pid('fil'),
            'entity_type' => 'task',
            'entity_public_id' => (string)$task['public_id'],
            'uploader_user_id' => (int)$pmUser['id'],
            'original_name' => 'release-notes-demo.pdf',
            'storage_path' => rtrim((string)(getenv('CRM_STORAGE_BASE') ?: dirname(__DIR__, 2) . '/storage_api'), '/') . '/uploads/demo/release-notes-demo.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 58432,
            'is_deleted' => 0,
            'created_at' => $now,
            'deleted_at' => null,
        ]);

        $this->insertIfEmpty('invitations', [
            'public_id' => $this->pid('inv'),
            'email' => 'candidate.pm@aurora-digital.ru',
            'invited_by_user_id' => (int)$rootUser['id'],
            'token_hash' => hash('sha256', 'invite-demo-token'),
            'expires_at' => '2026-05-01 00:00:00',
            'accepted_at' => null,
            'created_at' => $now,
        ]);

        $this->insertIfEmpty('password_reset_tokens', [
            'public_id' => $this->pid('prt'),
            'user_id' => (int)$pmUser['id'],
            'token_hash' => hash('sha256', 'password-reset-demo-token'),
            'expires_at' => '2026-04-30 23:59:59',
            'used_at' => null,
            'created_at' => $now,
        ]);

        $this->insertIfEmpty('two_factor_secrets', [
            'public_id' => $this->pid('tfs'),
            'user_id' => (int)$pmUser['id'],
            'secret_hash' => hash('sha256', '2fa-secret-demo-value'),
            'backup_codes' => json_encode(['A1B2-C3D4', 'E5F6-G7H8'], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insertIfEmpty('impersonation_audit', [
            'public_id' => $this->pid('impa'),
            'admin_user_id' => (int)$rootUser['id'],
            'target_user_id' => (int)$pmUser['id'],
            'reason' => 'Проверка доступа в демо окружении',
            'started_at' => $now,
            'ended_at' => $now,
        ]);

        $this->insertIfEmpty('activity_feed', [
            'public_id' => $this->pid('act'),
            'entity_type' => 'project',
            'entity_public_id' => (string)$project['public_id'],
            'action' => 'demo_seeded',
            'actor_public_id' => (string)$rootUser['public_id'],
            'payload' => json_encode(['note' => 'Полный демо-набор заполнен'], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
        ]);

        $this->insertIfEmpty('recycle_bin', [
            'public_id' => $this->pid('rbn'),
            'entity_type' => 'task',
            'entity_public_id' => (string)$task2['public_id'],
            'payload' => json_encode(['title' => $task2['title'] ?? 'Task'], JSON_UNESCAPED_UNICODE),
            'deleted_by_user_id' => (int)$rootUser['id'],
            'deleted_at' => $now,
            'restored_at' => null,
        ]);
    }

    private function insertIfEmpty(string $table, array $row): void
    {
        $count = (int)$this->pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        if ($count > 0) {
            return;
        }

        $columns = array_keys($row);
        $placeholders = array_map(static fn(string $c): string => ':' . $c, $columns);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(static fn(string $c): string => "`{$c}`", $columns)),
            implode(', ', $placeholders)
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($row);
    }

    private function one(string $sql): ?array
    {
        $stmt = $this->pdo->query($sql);
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return is_array($row) ? $row : null;
    }

    private function pid(string $prefix): string
    {
        return $prefix . '_' . strtoupper(bin2hex(random_bytes(8)));
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
            throw new RuntimeException('curl_exec failed: ' . curl_error($ch));
        }

        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Некорректный JSON API: HTTP ' . $http . '; body=' . substr($raw, 0, 500));
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

$seeder = new FullDemoSeeder();
$seeder->run();
