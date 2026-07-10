<?php

if (PHP_SAPI !== "cli") { http_response_code(404); exit; }
declare(strict_types=1);

/**
 * Seed CRM implementation tasks directly via PDO.
 *
 * Requires DB credentials:
 *   CRM_SEED_DB_DSN (default: mysql:host=localhost;port=3306;dbname=local;charset=utf8mb4)
 *   CRM_SEED_DB_USER (default: local)
 *   CRM_SEED_DB_PASSWORD (default: '')
 *
 * Usage:
 *   php api/scripts/seed_crm_implementation_tasks.php
 *
 * Idempotent: checks task count before inserting.
 */

$dsn = (string)(getenv('CRM_SEED_DB_DSN') ?: getenv('DB_DSN') ?: 'mysql:host=localhost;port=3306;dbname=local;charset=utf8mb4');
$user = (string)(getenv('CRM_SEED_DB_USER') ?: getenv('DB_USER') ?: 'local');
$pass = (string)(getenv('CRM_SEED_DB_PASSWORD') ?: getenv('DB_PASSWORD') ?: '');

$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

echo "Connected to database.\n";

// ── Helper: generate public_id ──
$generatePublicId = function (string $prefix = 'tsk_'): string {
    return $prefix . bin2hex(random_bytes(16));
};

// ── Helper: find or create project ──
$stmt = $pdo->prepare("SELECT id, public_id, title FROM projects WHERE title LIKE :title LIMIT 1");
$stmt->execute([':title' => '%Внедрение CRM%']);
$project = $stmt->fetch();

if ($project) {
    $projectId = (int)$project['id'];
    $projectPublicId = $project['public_id'];
    echo "Found project: {$project['title']} (id={$projectId})\n";
} else {
    $projectPublicId = $generatePublicId('prj_');
    $now = date('Y-m-d H:i:s');
    $pdo->prepare("INSERT INTO projects (public_id, title, description, status_code, priority_code, created_at, updated_at) VALUES (?, ?, ?, 'active', 'high', ?, ?)")
       ->execute([$projectPublicId, 'Внедрение CRM-системы для TravelCorp', 'Полный цикл внедрения корпоративной CRM: от сбора требований до перехода в промышленную эксплуатацию.', $now, $now]);
    $projectId = (int)$pdo->lastInsertId();
    echo "Created project id={$projectId} ({$projectPublicId})\n";
}

// ── Find or create users ──
$neededUsers = [
    ['login' => 'crm.lead@aurora-digital.ru', 'full_name' => 'Алексей Соболев'],
    ['login' => 'crm.analyst@aurora-digital.ru', 'full_name' => 'Елена Крылова'],
    ['login' => 'crm.dev@aurora-digital.ru', 'full_name' => 'Дмитрий Орлов'],
    ['login' => 'crm.integrator@aurora-digital.ru', 'full_name' => 'Павел Сомов'],
    ['login' => 'crm.trainer@aurora-digital.ru', 'full_name' => 'Анна Белова'],
    ['login' => 'crm.support@aurora-digital.ru', 'full_name' => 'Иван Громов'],
];

$userIds = []; // login => id
$userPublicIds = []; // login => public_id

$userStmt = $pdo->prepare("SELECT id, public_id, full_name FROM users WHERE login = ? LIMIT 1");
$createUserStmt = $pdo->prepare("INSERT INTO users (public_id, login, email, full_name, locale, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, 'ru-ru', ?, ?, ?)");

foreach ($neededUsers as $nu) {
    $userStmt->execute([$nu['login']]);
    $row = $userStmt->fetch();
    if ($row) {
        $userIds[$nu['login']] = (int)$row['id'];
        $userPublicIds[$nu['login']] = $row['public_id'];
        echo "Found user: {$row['full_name']} (id={$row['id']})\n";
    } else {
        $uid = $generatePublicId('usr_');
        $now = date('Y-m-d H:i:s');
        // Password hash for 'Demo12345!' — using password_hash for portability
        $hash = password_hash('Demo12345!', PASSWORD_BCRYPT);
        $createUserStmt->execute([$uid, $nu['login'], $nu['login'], $nu['full_name'], $hash, $now, $now]);
        $id = (int)$pdo->lastInsertId();
        $userIds[$nu['login']] = $id;
        $userPublicIds[$nu['login']] = $uid;
        echo "Created user: {$nu['full_name']} (id={$id})\n";
    }
}

// ── Find root user as creator ──
$rootStmt = $pdo->prepare("SELECT id FROM users WHERE login = 'root' OR login = 'test.root' LIMIT 1");
$rootStmt->execute();
$rootRow = $rootStmt->fetch();
$creatorUserId = $rootRow ? (int)$rootRow['id'] : 1;

// ── Task definitions ──
$today = new DateTimeImmutable('2026-05-22 12:00:00');

$taskDefs = [
    // Phase 1: Discovery & Planning
    ['title' => 'Провести discovery-сессию с заказчиком',
     'desc' => 'Собрать ключевых стейкхолдеров, зафиксировать текущие бизнес-процессы, выявить боли и узкие места. Результат: протокол встречи, карта процессов As-Is.',
     'status' => 'done', 'priority' => 'high', 'assignee' => 'crm.lead@aurora-digital.ru', 'offset' => '-14 days'],
    ['title' => 'Согласовать и утвердить бизнес-требования (BRD)',
     'desc' => 'Документ с описанием функциональных и нефункциональных требований к CRM. Модули: клиентская база, сделки, задачи, отчёты, интеграции.',
     'status' => 'done', 'priority' => 'high', 'assignee' => 'crm.analyst@aurora-digital.ru', 'offset' => '-11 days'],
    ['title' => 'Составить техническое задание на доработки',
     'desc' => 'На основе BRD сформировать ТЗ: кастомные поля, бизнес-логика воронок, автоматические действия, отчёты и дашборды.',
     'status' => 'done', 'priority' => 'high', 'assignee' => 'crm.lead@aurora-digital.ru', 'offset' => '-9 days'],
    ['title' => 'Утвердить план внедрения и календарный график',
     'desc' => 'Вехи проекта: завершение настройки, миграция данных, UAT, обучение, go-live. Зафиксировать даты и ответственных.',
     'status' => 'done', 'priority' => 'normal', 'assignee' => 'crm.lead@aurora-digital.ru', 'offset' => '-8 days'],

    // Phase 2: System Configuration
    ['title' => 'Настроить структуру справочников системы',
     'desc' => 'Типы сделок, статусы и этапы воронок, категории задач, источники лидов, типы контрагентов. Привести к единой классификации заказчика.',
     'status' => 'done', 'priority' => 'high', 'assignee' => 'crm.dev@aurora-digital.ru', 'offset' => '-5 days'],
    ['title' => 'Настроить ролевую модель и права доступа',
     'desc' => 'Роли: администратор CRM, руководитель отдела, менеджер по продажам, аналитик. Настроить права для каждой роли.',
     'status' => 'done', 'priority' => 'high', 'assignee' => 'crm.dev@aurora-digital.ru', 'offset' => '-4 days'],
    ['title' => 'Настроить пользовательские поля и макеты страниц',
     'desc' => 'Кастомные поля для сделок (источник, тип тура, бюджет), контактов (предпочтения, история), компаний (сегмент).',
     'status' => 'done', 'priority' => 'normal', 'assignee' => 'crm.dev@aurora-digital.ru', 'offset' => '-3 days'],
    ['title' => 'Настроить воронки продаж и этапы сделок',
     'desc' => 'Воронки: B2C-продажи, B2B-продажи, партнёрские заявки. Для каждой определить этапы, вероятности и триггеры перехода.',
     'status' => 'done', 'priority' => 'high', 'assignee' => 'crm.analyst@aurora-digital.ru', 'offset' => '-2 days'],

    // Phase 3: Data Migration
    ['title' => 'Выгрузить и очистить данные из legacy-системы',
     'desc' => 'Выгрузка контактов, компаний, сделок и истории взаимодействий из старой CRM. Очистка дублей, нормализация телефонов и email.',
     'status' => 'done', 'priority' => 'urgent', 'assignee' => 'crm.analyst@aurora-digital.ru', 'offset' => '-3 days'],
    ['title' => 'Разработать карту маппинга полей',
     'desc' => 'Сопоставить поля старой и новой CRM. Учесть различия в форматах дат, типах справочников, структуре составных полей.',
     'status' => 'done', 'priority' => 'high', 'assignee' => 'crm.analyst@aurora-digital.ru', 'offset' => '-2 days'],
    ['title' => 'Выполнить миграцию контактов и компаний',
     'desc' => 'Перенос ~12 000 контактов и ~800 компаний через API. Проверить корректность связей, сохранить историю взаимодействий.',
     'status' => 'done', 'priority' => 'urgent', 'assignee' => 'crm.integrator@aurora-digital.ru', 'offset' => '-1 days'],
    ['title' => 'Выполнить миграцию сделок и проектов',
     'desc' => 'Перенос ~3 500 сделок с историей смены статусов и ~150 проектов. Проверить корректность сумм и валют.',
     'status' => 'in_progress', 'priority' => 'urgent', 'assignee' => 'crm.integrator@aurora-digital.ru', 'offset' => '0 days'],
    ['title' => 'Верифицировать полноту и целостность перенесённых данных',
     'desc' => 'Выборочная сверка 5% контактов, 10% сделок и всех проектов. Сверить суммы с отчётом из legacy-системы.',
     'status' => 'new', 'priority' => 'high', 'assignee' => 'crm.analyst@aurora-digital.ru', 'offset' => '+1 days'],

    // Phase 4: Integrations
    ['title' => 'Настроить интеграцию с сайтом (веб-формы + API)',
     'desc' => 'Веб-формы захвата лидов, автоматическое создание контактов и сделок по заявкам. Внедрить UTM-метки.',
     'status' => 'new', 'priority' => 'high', 'assignee' => 'crm.integrator@aurora-digital.ru', 'offset' => '+2 days'],
    ['title' => 'Настроить интеграцию с email-маркетингом (UniSender)',
     'desc' => 'Двусторонняя синхронизация статусов подписки, сегментов и истории рассылок. Автоматическая загрузка контактов из CRM.',
     'status' => 'new', 'priority' => 'normal', 'assignee' => 'crm.integrator@aurora-digital.ru', 'offset' => '+3 days'],
    ['title' => 'Настроить интеграцию с телефонией (Манго Телеком)',
     'desc' => 'Журнал звонков, автоматическая карточка звонка в CRM, подтягивание информации о клиенте при входящем звонке.',
     'status' => 'new', 'priority' => 'high', 'assignee' => 'crm.integrator@aurora-digital.ru', 'offset' => '+4 days'],

    // Phase 5: Testing
    ['title' => 'Провести UAT (приёмочное тестирование)',
     'desc' => 'Привлечь 5 ключевых пользователей для тестирования типовых сценариев: создание сделки, проведение по этапам, отчёты.',
     'status' => 'new', 'priority' => 'urgent', 'assignee' => 'crm.trainer@aurora-digital.ru', 'offset' => '+5 days'],
    ['title' => 'Исправить критические ошибки по результатам UAT',
     'desc' => 'Обработать замечания: приоритет 1 — до go-live, приоритет 2 — бэклог следующих итераций.',
     'status' => 'new', 'priority' => 'urgent', 'assignee' => 'crm.dev@aurora-digital.ru', 'offset' => '+7 days'],
    ['title' => 'Настроить резервное копирование и процедуру DR',
     'desc' => 'Ежедневный бэкап БД, еженедельный бэкап файлов. Автоматическое уведомление о статусе. Документирование процедуры.',
     'status' => 'new', 'priority' => 'normal', 'assignee' => 'crm.dev@aurora-digital.ru', 'offset' => '+6 days'],
    ['title' => 'Провести нагрузочное тестирование системы',
     'desc' => 'Симуляция: 50 одновременных пользователей, 200 сделок/час, 5 отчётов. Замерить время отклика.',
     'status' => 'new', 'priority' => 'high', 'assignee' => 'crm.integrator@aurora-digital.ru', 'offset' => '+8 days'],

    // Phase 6: Training & Go-Live
    ['title' => 'Провести обучение администраторов системы',
     'desc' => 'Двухдневный воркшоп для 3 администраторов: управление пользователями, настройка прав и полей, воронки, отчёты.',
     'status' => 'new', 'priority' => 'high', 'assignee' => 'crm.trainer@aurora-digital.ru', 'offset' => '+9 days'],
    ['title' => 'Провести обучение менеджеров по продажам',
     'desc' => 'Однодневный воркшоп для 20 менеджеров: клиентская база, сделки, задачи, отчёты. Подготовить памятку пользователя.',
     'status' => 'new', 'priority' => 'high', 'assignee' => 'crm.trainer@aurora-digital.ru', 'offset' => '+10 days'],
    ['title' => 'Запустить систему в промышленную эксплуатацию',
     'desc' => 'Go-Live: отключение legacy-системы, включение интеграций, мониторинг 48 часов. Чек-лист: миграция, интеграции, права, бэкапы.',
     'status' => 'new', 'priority' => 'urgent', 'assignee' => 'crm.lead@aurora-digital.ru', 'offset' => '+12 days'],
    ['title' => 'Пост-релизная поддержка первой недели',
     'desc' => 'Ежедневный мониторинг, помощь пользователям, оперативное исправление инцидентов. Отчёт о первой неделе эксплуатации.',
     'status' => 'new', 'priority' => 'urgent', 'assignee' => 'crm.support@aurora-digital.ru', 'offset' => '+13 days'],
];

// ── Insert tasks ──
$checkStmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM tasks t JOIN projects p ON p.id = t.project_id WHERE p.title LIKE ?");
$checkStmt->execute(['%Внедрение CRM%']);
$existingCount = (int)$checkStmt->fetch()['cnt'];

if ($existingCount > 0) {
    echo "Project already has {$existingCount} tasks. Skipping.\n";
    echo "Run this to reset: DELETE FROM tasks WHERE project_id = {$projectId}; -- then re-run this script\n";
    exit(0);
}

$insertStmt = $pdo->prepare("INSERT INTO tasks (public_id, project_id, title, description, status_code, priority_code, due_at, creator_user_id, assignee_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$now = date('Y-m-d H:i:s');
$inserted = 0;

foreach ($taskDefs as $def) {
    $dueAt = $today->modify($def['offset'])->format('Y-m-d H:i:s');
    $assigneeId = $userIds[$def['assignee']] ?? null;
    $publicId = $generatePublicId();

    $insertStmt->execute([
        $publicId,
        $projectId,
        $def['title'],
        $def['desc'],
        $def['status'],
        $def['priority'],
        $dueAt,
        $creatorUserId,
        $assigneeId,
        $now,
        $now,
    ]);
    $inserted++;
}

echo "\n=== Done ===\n";
echo "Project: Внедрение CRM-системы для TravelCorp (id={$projectId})\n";
echo "Tasks inserted: {$inserted}\n";
echo "Due date range: {$today->modify('-14 days')->format('Y-m-d')} to {$today->modify('+13 days')->format('Y-m-d')}\n";
echo "\nNow check: http://crm.ru/web/index.php?route=my-day and ?route=my-week\n";
