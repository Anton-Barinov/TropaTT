<?php

declare(strict_types=1);
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }


/**
 * Seed Knowledge Base — наполняет базу знаний реалистичным контентом
 * для вымышленной компании "ТехноПроект" (IT-аутсорсинг и разработка).
 *
 * Использование:
 *   php api/scripts/seed_knowledge.php
 *
 * Перед запуском убедитесь, что .env настроен на MySQL.
 * Скрипт сам запускает миграцию KnowledgeBaseMigration и создаёт таблицы.
 */

require_once __DIR__ . '/../system/library/support/Autoloader.php';

$autoloader = new Api\System\Library\Support\Autoloader(__DIR__ . '/..');
$autoloader->register();

use Api\System\Library\Support\EnvLoader;
use Api\System\Library\Database\Migration\KnowledgeBaseMigration;

EnvLoader::loadFiles([
    dirname(__DIR__, 2) . '/.env',
    __DIR__ . '/../.env',
    dirname(__DIR__, 2) . '/.env.local',
    __DIR__ . '/../.env.local',
]);

// ── 1. Database connection ──
$driver = trim((string)(getenv('DB_CONNECTION') ?: getenv('CRM_DB_DRIVER') ?: 'mysql'));
$host = trim((string)(getenv('DB_HOST') ?: '127.0.0.1'));
$port = (int)(getenv('DB_PORT') ?: 3306);
$database = trim((string)(getenv('DB_DATABASE') ?: 'crm_api'));
$username = trim((string)(getenv('DB_USERNAME') ?: 'root'));
$password = getenv('DB_PASSWORD') ?: trigger_error('DB_PASSWORD environment variable must be set in .env or environment', E_USER_ERROR);

$dsn = "{$driver}:host={$host};port={$port};dbname={$database};charset=utf8mb4";
$pdo = new PDO($dsn, $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

echo "✓ Connected to {$driver}:{$database}\n";

// ── 2. Run migration ──
echo "→ Running KnowledgeBaseMigration...\n";
$migration = new KnowledgeBaseMigration();
$migration->up($pdo, $driver);
echo "✓ Knowledge tables created/verified\n";

// ── 3. Helper functions ──
$now = gmdate('Y-m-d H:i:s');

function publicId(string $prefix): string
{
    return $prefix . '_' . bin2hex(random_bytes(10));
}

function slug(string $value): string
{
    $value = mb_strtolower(trim($value));
    $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? '';
    $value = trim($value, '-');
    return mb_substr($value, 0, 120);
}

function escHtml(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function now(string $modify = ''): string
{
    $ts = time();
    if ($modify !== '') {
        $ts = strtotime($modify, $ts);
    }
    return gmdate('Y-m-d H:i:s', $ts);
}

function ago(int $days): string
{
    return now("-{$days} days");
}

function plus(int $days): string
{
    return now("+{$days} days");
}

function insert(PDO $pdo, string $table, array $data): int
{
    $columns = implode(', ', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));
    $stmt = $pdo->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})");
    $stmt->execute($data);
    return (int)$pdo->lastInsertId();
}

function fetchByPublicId(PDO $pdo, string $table, string $publicId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE public_id = :pid LIMIT 1");
    $stmt->execute(['pid' => $publicId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function exists(PDO $pdo, string $table, string $column, string $value): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = :val");
    $stmt->execute(['val' => $value]);
    return (int)$stmt->fetchColumn() > 0;
}

// ── 4. Create Spaces (Разделы) ──
echo "→ Creating knowledge spaces...\n";

$spaces = [
    [
        'public_id' => publicId('kbs'),
        'title' => 'Общие материалы',
        'slug' => 'general',
        'description' => 'Общекорпоративные инструкции, регламенты и справочные материалы для всех сотрудников.',
        'icon' => 'building',
        'color' => '#0f8f72',
        'visibility' => 'public',
        'default_access_level' => 'view',
        'sort_order' => 10,
        'is_system' => 0,
        'created_at' => ago(60),
        'updated_at' => ago(1),
    ],
    [
        'public_id' => publicId('kbs'),
        'title' => 'Разработка',
        'slug' => 'development',
        'description' => 'Техническая документация, стандарты кодирования, архитектурные решения и runbook для команды разработки.',
        'icon' => 'code',
        'color' => '#2563eb',
        'visibility' => 'public',
        'default_access_level' => 'view',
        'sort_order' => 20,
        'created_at' => ago(55),
        'updated_at' => ago(2),
    ],
    [
        'public_id' => publicId('kbs'),
        'title' => 'Продажи и клиенты',
        'slug' => 'sales',
        'description' => 'Скрипты продаж, регламенты работы с клиентами, шаблоны коммерческих предложений и договоров.',
        'icon' => 'handshake',
        'color' => '#d97706',
        'visibility' => 'public',
        'default_access_level' => 'view',
        'sort_order' => 30,
        'created_at' => ago(50),
        'updated_at' => ago(3),
    ],
    [
        'public_id' => publicId('kbs'),
        'title' => 'HR и Онбординг',
        'slug' => 'hr',
        'description' => 'Кадровые документы, онбординг новых сотрудников, политики компании, обучение.',
        'icon' => 'users',
        'color' => '#7c3aed',
        'visibility' => 'public',
        'default_access_level' => 'view',
        'sort_order' => 40,
        'created_at' => ago(45),
        'updated_at' => ago(5),
    ],
    [
        'public_id' => publicId('kbs'),
        'title' => 'Поддержка и QA',
        'slug' => 'support-qa',
        'description' => 'База знаний службы поддержки: сценарии обработки обращений, FAQ клиентов, баги и известные проблемы.',
        'icon' => 'headset',
        'color' => '#059669',
        'visibility' => 'public',
        'default_access_level' => 'view',
        'sort_order' => 50,
        'created_at' => ago(40),
        'updated_at' => ago(4),
    ],
    [
        'public_id' => publicId('kbs'),
        'title' => 'Проекты и методология',
        'slug' => 'projects',
        'description' => 'Методологии управления проектами, шаблоны документов, регламенты совещаний и отчётности.',
        'icon' => 'diagram-project',
        'color' => '#dc2626',
        'visibility' => 'public',
        'default_access_level' => 'view',
        'sort_order' => 60,
        'created_at' => ago(35),
        'updated_at' => ago(6),
    ],
    [
        'public_id' => publicId('kbs'),
        'title' => 'Безопасность и IT',
        'slug' => 'security',
        'description' => 'Политики информационной безопасности, инструкции по работе с VPN, доступом и паролями.',
        'icon' => 'shield-halved',
        'color' => '#374151',
        'visibility' => 'restricted',
        'default_access_level' => 'view',
        'sort_order' => 70,
        'created_at' => ago(30),
        'updated_at' => ago(7),
    ],
];

$spaceIds = []; // public_id => id
foreach ($spaces as $space) {
    if (exists($pdo, 'knowledge_spaces', 'slug', $space['slug'])) {
        $stmt = $pdo->prepare("SELECT id FROM knowledge_spaces WHERE slug = :slug");
        $stmt->execute(['slug' => $space['slug']]);
        $id = (int)$stmt->fetchColumn();
        $spaceIds[$space['slug']] = $id;
        echo "  • Space '{$space['title']}' already exists (id={$id})\n";
        continue;
    }
    $id = insert($pdo, 'knowledge_spaces', $space);
    $spaceIds[$space['slug']] = $id;
    echo "  ✓ Created space: {$space['title']}\n";
}

// ── 5. Create Pages ──
echo "→ Creating knowledge pages...\n";

interface PageSeed {
    public function title(): string;
    public function slug(): string;
    public function pageType(): string;
    public function spaceSlug(): string;
    public function status(): string;
    public function content(): string;
    public function sortOrder(): int;
    public function parentSlug(): ?string;
}

class SimplePage implements PageSeed {
    public function __construct(
        private string $title,
        private string $pageType,
        private string $spaceSlug,
        private string $content,
        private string $status = 'published',
        private int $sortOrder = 100,
        private ?string $parentSlug = null,
    ) {}

    public function title(): string { return $this->title; }
    public function slug(): string { return slug($this->title); }
    public function pageType(): string { return $this->pageType; }
    public function spaceSlug(): string { return $this->spaceSlug; }
    public function status(): string { return $this->status; }
    public function content(): string { return $this->content; }
    public function sortOrder(): int { return $this->sortOrder; }
    public function parentSlug(): ?string { return $this->parentSlug; }
}

$pages = [];

// === Общие материалы ===
$pages[] = new SimplePage(
    'Корпоративные стандарты работы в CRM', 'regulation', 'general',
    '<h2>Общие положения</h2><p>Настоящий регламент устанавливает единые правила работы в корпоративной CRM-системе для всех сотрудников компании ООО «ТехноПроект».</p><h2>Обязанности пользователей</h2><ul><li>Все задачи должны создаваться только в CRM с указанием проекта и приоритета</li><li>Статус задачи должен отражать её реальное состояние</li><li>Комментарии к задачам пишутся по существу, с указанием фактов и решений</li><li>Файлы загружаются в соответствующие задачи или проекты</li></ul><h2>Ответственность</h2><p>Нарушение регламента влечёт дисциплинарное взыскание в соответствии с трудовым договором.</p>',
    'published', 100
);

$pages[] = new SimplePage(
    'Политика использования корпоративной почты', 'regulation', 'general',
    '<h2>Основные правила</h2><ul><li>Корпоративная почта используется исключительно для рабочих целей</li><li>Запрещена пересылка конфиденциальной информации на личные ящики</li><li>Подпись письма должна содержать ФИО, должность и контакты</li></ul><h2>Безопасность</h2><p>При получении подозрительных писем с вложениями или ссылками немедленно сообщайте в IT-отдел.</p>',
    'published', 200
);

$pages[] = new SimplePage(
    'Часто задаваемые вопросы по CRM', 'faq', 'general',
    '<h2>Как создать задачу?</h2><p>Перейдите в раздел «Задачи», нажмите «Создать», заполните название, описание и выберите проект.</p><h2>Как прикрепить файл к задаче?</h2><p>Откройте задачу, в блоке «Файлы» нажмите «Выбрать файл» и «Загрузить».</p><h2>Почему я не вижу проект в списке?</h2><p>Проверьте права доступа — возможно, проект закрыт для вашей роли. Обратитесь к руководителю проекта.</p><h2>Как восстановить удалённую задачу?</h2><p>Используйте раздел «Корзина» в админ-панели. Администратор может восстановить задачу в течение 30 дней.</p>',
    'published', 300
);

$pages[] = new SimplePage(
    'Чеклист: Проверка аккаунта нового сотрудника', 'checklist', 'general',
    '<h2>Первый день</h2><ul><li><label><input type="checkbox"> Создать учётную запись в CRM</label></li><li><label><input type="checkbox"> Настроить корпоративную почту</label></li><li><label><input type="checkbox"> Выдать доступ к Git-репозиториям</label></li><li><label><input type="checkbox"> Подключить к Slack/Telegram-чатам</label></li></ul><h2>Первая неделя</h2><ul><li><label><input type="checkbox"> Провести онбординг-встречу с командой</label></li><li><label><input type="checkbox"> Выдать тестовую задачу</label></li><li><label><input type="checkbox"> Назначить ментора</label></li></ul>',
    'published', 400
);

// === Разработка ===
$pages[] = new SimplePage(
    'Стандарты кодирования PHP', 'regulation', 'development',
    '<h2>Общие правила</h2><ul><li>strict_types=1 обязателен во всех файлах</li><li>Именование: camelCase для методов, PascalCase для классов</li><li>Максимальная длина строки — 120 символов</li><li>Обязательно типизировать все аргументы и возвращаемые значения</li></ul><h2>Архитектура</h2><ul><li>Контроллеры не должны содержать бизнес-логику</li><li>Используйте сервисный слой для бизнес-операций</li><li>Репозитории работают только с БД</li></ul><h2>Пример</h2><pre><code>declare(strict_types=1);\n\nfinal class UserService\n{\n    public function __construct(private UserRepository $repo) {}\n\n    public function create(array $data): User\n    {\n        // validate\n        return $this->repo->save($data);\n    }\n}</code></pre>',
    'published', 100
);

$pages[] = new SimplePage(
    'Инструкция по деплою на production', 'runbook', 'development',
    '<h2>Подготовка</h2><ol><li>Убедиться, что все тесты проходят: <code>vendor/bin/phpunit</code></li><li>Проверить PHPStan: <code>vendor/bin/phpstan analyse</code></li><li>Обновить версию в CHANGELOG.md</li><li>Создать тег: <code>git tag v1.x.x && git push --tags</code></li></ol><h2>Деплой</h2><ol><li>Подключиться к VPN компании</li><li>SSH на production-сервер: <code>ssh deploy@prod.company.ru</code></li><li>Перейти в директорию: <code>cd /var/www/app</code></li><li>Выполнить: <code>git pull origin main</code></li><li>Обновить зависимости: <code>composer install --no-dev --optimize-autoloader</code></li><li>Применить миграции: <code>php api/scripts/migrate.php</code></li><li>Проверить health-check: <code>curl https://app.company.ru/api/v1/health/status</code></li></ol><h2>Откат</h2><p>Если деплой вызвал проблемы: <code>git revert HEAD && git push && php api/scripts/migrate.php --rollback</code></p>',
    'published', 200
);

$pages[] = new SimplePage(
    'Архитектура микросервисов: принятые решения', 'decision', 'development',
    '<h2>Контекст</h2><p>В связи с ростом нагрузки на монолитное приложение принято решение о переходе на микросервисную архитектуру.</p><h2>Решение</h2><ul><li><strong>API Gateway:</strong> KrakenD</li><li><strong>Очереди:</strong> RabbitMQ</li><li><strong>Кеш:</strong> Redis Cluster</li><li><strong>Базы данных:</strong> MySQL (master-slave) + PostgreSQL для аналитики</li><li><strong>Контейнеризация:</strong> Docker + Kubernetes</li><li><strong>Мониторинг:</strong> Prometheus + Grafana</li></ul><h2>Сроки</h2><p>Первый этап (выделение сервиса авторизации) — Q3 2026.</p>',
    'published', 300
);

$pages[] = new SimplePage(
    'Onboarding frontend-разработчика', 'onboarding', 'development',
    '<h2>День 1: Настройка окружения</h2><ol><li>Установить Node.js v20+, npm/pnpm</li><li>Склонировать репозиторий frontend</li><li>Запустить dev-сервер: <code>npm run dev</code></li><li>Изучить структуру проекта и компоненты</li></ol><h2>День 2-3: Первые задачи</h2><ul><li>Исправить баг в UI (simple fix)</li><li>Создать простой компонент по задаче из беклога</li><li>Провести code-review с наставником</li></ul><h2>Неделя 1: Погружение</h2><ul><li>Изучить архитектуру состояния (Pinia/Redux)</li><li>Написать тесты для компонента</li><li>Провести демо команде</li></ul>',
    'published', 400
);

$pages[] = new SimplePage(
    'Протокол архитектурного комитета #23', 'meeting_note', 'development',
    '<h2>Дата: 10.05.2026</h2><h2>Участники</h2><ul><li>Иван Петров (Tech Lead)</li><li>Анна Смирнова (Senior Backend)</li><li>Павел Козлов (System Architect)</li><li>Ольга Новикова (Team Lead QA)</li></ul><h2>Решения</h2><ul><li>Утверждён переход на PHP 8.4 с 1 июля 2026</li><li>Выбрана библиотека для GraphQL: webonyx/graphql-php</li><li>Отложен переход на event-sourcing до Q1 2027</li></ul><h2>Следующие шаги</h2><ul><li>Ивану: подготовить миграционный план по PHP 8.4 (до 15.06)</li><li>Анне: создать POC GraphQL-эндпоинта (до 01.06)</li></ul>',
    'published', 500
);

// === Продажи и клиенты ===
$pages[] = new SimplePage(
    'Регламент обработки входящих заявок', 'regulation', 'sales',
    '<h2>Время реакции</h2><ul><li>VIP-клиенты: ответ в течение 30 минут</li><li>Корпоративные клиенты: ответ в течение 2 часов</li><li>Массовые обращения: ответ в течение 24 часов</li></ul><h2>Этапы обработки</h2><ol><li>Приём заявки — проверка полноты данных</li><li>Квалификация — определение потребности и бюджета</li><li>Коммерческое предложение — подготовка и отправка</li><li>Согласование — обсуждение условий</li><li>Закрытие сделки — подписание договора</li></ol><h2>CRM-статусы</h2><p>Заявка → Квалификация → КП отправлено → Согласование → Сделка → Архив</p>',
    'published', 100
);

$pages[] = new SimplePage(
    'Скрипт холодного звонка', 'instruction', 'sales',
    '<h2>1. Открытие</h2><blockquote><p>Здравствуйте, {Имя}! Меня зовут {Ваше имя}, я представляю компанию «ТехноПроект». Уделите мне 2 минуты?</p></blockquote><h2>2. Квалификация</h2><blockquote><p>Расскажите, какое у вас сейчас используется программное обеспечение для управления проектами?</p></blockquote><h2>3. Презентация ценности</h2><blockquote><p>Мы помогаем компаниям сократить время на управление проектами на 30% за счёт автоматизации.</p></blockquote><h2>4. Обработка возражений</h2><ul><li><strong>«Нет времени»</strong> — «Я понимаю, давайте я пришлю краткое описание на почту?»</li><li><strong>«Уже есть решение»</strong> — «Отлично! А рассматриваете ли вы альтернативы?»</li></ul>',
    'published', 200
);

$pages[] = new SimplePage(
    'Шаблон коммерческого предложения', 'article', 'sales',
    '<h2>Структура КП</h2><ol><li>Краткое введение и понимание потребности</li><li>Описание решения и его преимущества</li><li>План внедрения и сроки</li><li>Стоимость и варианты оплаты</li><li>Кейсы и отзывы</li><li>Юридическая информация и реквизиты</li></ol><h2>Советы</h2><ul><li>КП не должно быть длиннее 3-5 страниц</li><li>Используйте цифры и конкретные выгоды</li><li>Добавьте ссылки на кейсы реализации</li><li>Отправляйте КП в PDF, чтобы сохранить форматирование</li></ul>',
    'published', 300
);

$pages[] = new SimplePage(
    'Работа с возражениями: база знаний отдела продаж', 'faq', 'sales',
    '<h2>«Это дорого»</h2><p>Сравните стоимость решения с потерями от проблемы, которую оно решает. Покажите ROI.</p><h2>«Мы подумаем»</h2><p>Уточните: «Какая информация вам нужна для принятия решения? Через какое время мне напомнить?»</p><h2>«Работает с конкурентами»</h2><p>«Мы уважаем ваш выбор. Тем не менее, наши клиенты отмечают, что наша поддержка работает быстрее, а стоимость на 15% ниже при том же функционале».</p>',
    'published', 400
);

// === HR и Онбординг ===
$pages[] = new SimplePage(
    'Политика удалённой работы', 'regulation', 'hr',
    '<h2>Общие положения</h2><p>Компания поддерживает гибридный формат работы. Сотрудники могут работать из офиса, удалённо или в смешанном режиме.</p><h2>Требования</h2><ul><li>Наличие стабильного интернета (не менее 50 Мбит/с)</li><li>Рабочее место должно быть оборудовано веб-камерой</li><li>Обязательно участие в daily standup в 10:00 по МСК</li><li>Статус в Slack/Telegram должен быть активным в рабочие часы</li></ul><h2>Компенсация</h2><p>Компания компенсирует расходы на интернет в размере 1500 руб/мес и предоставляет ноутбук.</p>',
    'published', 100
);

$pages[] = new SimplePage(
    'Чеклист онбординга нового сотрудника', 'checklist', 'hr',
    '<h2>За 2 дня до выхода</h2><ul><li><label><input type="checkbox"> Подготовить рабочее место (ноутбук, доступы)</label></li><li><label><input type="checkbox"> Создать план онбординга на первую неделю</label></li><li><label><input type="checkbox"> Назначить бадди (наставника)</label></li></ul><h2>Первый день</h2><ul><li><label><input type="checkbox"> Встреча с HR: документы, политики, регламенты</label></li><li><label><input type="checkbox"> Знакомство с командой</label></li><li><label><input type="checkbox"> Доступ к CRM, почте, мессенджерам</label></li><li><label><input type="checkbox"> Прочитать базу знаний компании</label></li></ul><h2>Первая неделя</h2><ul><li><label><input type="checkbox"> Выполнить тестовое задание</label></li><li><label><input type="checkbox"> Пройти обучение по продукту</label></li><li><label><input type="checkbox"> Первый 1:1 с руководителем</label></li></ul>',
    'published', 200
);

$pages[] = new SimplePage(
    'Программа адаптации: roadmap на 90 дней', 'onboarding', 'hr',
    '<h2>Неделя 1: Погружение</h2><ul><li>Изучение продуктов и услуг компании</li><li>Знакомство с ключевыми процессами</li><li>Встречи с руководителями отделов</li></ul><h2>Недели 2-4: Практика</h2><ul><li>Выполнение задач под руководством наставника</li><li>Участие в командных мероприятиях</li><li>Изучение инструментов (CRM, Jira, Git)</li></ul><h2>Месяц 2-3: Самостоятельность</h2><ul><li>Самостоятельное ведение задач</li><li>Участие в код-ревью</li><li>Проведение первой презентации/демо</li></ul>',
    'published', 300
);

// === Поддержка и QA ===
$pages[] = new SimplePage(
    'Процесс обработки инцидента P1', 'runbook', 'support-qa',
    '<h2>Определение P1</h2><p>Полная недоступность сервиса для всех клиентов или критическая утечка данных.</p><h2>Шаги</h2><ol><li>Зарегистрировать инцидент в CRM с тегом P1</li><li>Уведомить дежурную команду в Telegram-чате #incidents</li><li>Начать расследование в течение 5 минут</li><li>Каждые 30 минут публиковать статус в чате</li><li>После устранения провести postmortem</li></ol><h2>Postmortem</h2><ul><li>Причина инцидента</li><li>Время обнаружения и устранения</li><li>Действия по предотвращению</li></ul>',
    'published', 100
);

$pages[] = new SimplePage(
    'FAQ: Частые вопросы клиентов техподдержки', 'faq', 'support-qa',
    '<h2>Не могу войти в систему</h2><p>Проверьте, правильно ли введён логин и пароль. Используйте кнопку «Забыли пароль» для сброса. Если проблема сохраняется, проверьте, не заблокирован ли аккаунт.</p><h2>Медленно работает интерфейс</h2><p>Попробуйте очистить кеш браузера (Ctrl+Shift+Del). Если не помогает, проверьте скорость интернета (не менее 10 Мбит/с).</p><h2>Где найти отчёт за прошлый месяц?</h2><p>Перейдите в раздел «Аналитика», выберите период и нажмите «Сформировать». Отчёт можно скачать в PDF или Excel.</p>',
    'published', 200
);

$pages[] = new SimplePage(
    'Правила оформления баг-репортов', 'instruction', 'support-qa',
    '<h2>Структура баг-репорта</h2><ol><li>Краткий заголовок: что, где, при каких условиях</li><li>Шаги воспроизведения (нумерованный список)</li><li>Фактический результат (что произошло)</li><li>Ожидаемый результат (что должно было произойти)</li><li>Окружение: браузер, ОС, версия</li><li>Скриншот или видео (обязательно!)</li></ol><h2>Приоритеты</h2><ul><li><strong>P1 Критический:</strong> система не работает, нет workaround</li><li><strong>P2 Высокий:</strong> важная функция не работает, есть workaround</li><li><strong>P3 Средний:</strong> незначительная ошибка</li><li><strong>P4 Низкий:</strong> косметическая проблема</li></ul>',
    'published', 300
);

// === Проекты и методология ===
$pages[] = new SimplePage(
    'Регламент проведения ежедневных стендапов', 'regulation', 'projects',
    '<h2>Формат</h2><p>Ежедневно в 10:00 по МСК. Продолжительность до 15 минут. Проходит в Zoom/Slack Calls.</p><h2>Повестка для каждого участника</h2><ol><li>Что сделано вчера?</li><li>Что планируется сегодня?</li><li>Какие есть блокеры?</li></ol><h2>Правила</h2><ul><li>Стендап — не статус-митинг, а координация</li><li>Обсуждать проблемы — после стендапа</li><li>Опоздания фиксируются в CRM</li></ul>',
    'published', 100
);

$pages[] = new SimplePage(
    'Чеклист запуска нового проекта', 'checklist', 'projects',
    '<h2>Организация</h2><ul><li><label><input type="checkbox"> Создать проект в CRM</label></li><li><label><input type="checkbox"> Назначить проектного менеджера и команду</label></li><li><label><input type="checkbox"> Подготовить устав проекта</label></li><li><label><input type="checkbox"> Согласовать бюджет с заказчиком</label></li></ul><h2>Техническая подготовка</h2><ul><li><label><input type="checkbox"> Создать репозиторий и настроить CI/CD</label></li><li><label><input type="checkbox"> Развернуть dev-окружение</label></li><li><label><input type="checkbox"> Создать задачу-шаблон для первой итерации</label></li></ul><h2>Коммуникация</h2><ul><li><label><input type="checkbox"> Создать чат проекта в Slack</label></li><li><label><input type="checkbox"> Настроить регулярные встречи с заказчиком</label></li><li><label><input type="checkbox"> Определить каналы эскалации</label></li></ul>',
    'published', 200
);

$pages[] = new SimplePage(
    'Протокол стратегической сессии Q2 2026', 'meeting_note', 'projects',
    '<h2>Дата: 15.04.2026</h2><h2>Участники</h2><ul><li>Директор по развитию</li><li>CTO</li><li>Руководители отделов</li></ul><h2>Ключевые решения</h2><ul><li>Запуск нового продукта — модуль AI для CRM (срок: Q3 2026)</li><li>Выход на рынок Казахстана (срок: Q4 2026)</li><li>Увеличение штата разработки на 40% (план найма до конца года)</li></ul><h2>OKR на Q2</h2><ul><li>KR1: NPS клиентов > 85</li><li>KR2: Сокращение времени закрытия инцидентов на 20%</li><li>KR3: Запуск 3 новых интеграций с внешними сервисами</li></ul>',
    'published', 300
);

// === Безопасность и IT ===
$pages[] = new SimplePage(
    'Политика паролей и доступа к системам', 'regulation', 'security',
    '<h2>Требования к паролям</h2><ul><li>Минимум 12 символов</li><li>Должен содержать: заглавные, строчные буквы, цифры, спецсимволы</li><li>Не должен повторять предыдущие 5 паролей</li><li>Смена пароля — каждые 90 дней</li></ul><h2>Многофакторная аутентификация</h2><p>MFA обязательна для всех сотрудников. Используйте приложение Google Authenticator или Яндекс.Ключ.</p><h2>Запрещено</h2><ul><li>Хранить пароли в открытом виде (на стикерах, в файлах)</li><li>Использовать один пароль для разных систем</li><li>Передавать пароли третьим лицам</li></ul>',
    'published', 100
);

$pages[] = new SimplePage(
    'Инструкция: подключение к корпоративному VPN', 'instruction', 'security',
    '<h2>Для Windows</h2><ol><li>Скачайте установщик WireGuard с официального сайта</li><li>Откройте файл конфигурации, полученный от IT-отдела</li><li>Нажмите «Подключить»</li></ol><h2>Для macOS</h2><ol><li>Установите WireGuard из App Store</li><li>Импортируйте конфигурацию из файла .conf</li><li>Активируйте туннель</li></ol><h2>Проверка</h2><p>После подключения откройте http://192.168.1.1 — должен открыться внутренний портал компании.</p>',
    'published', 200
);

$pages[] = new SimplePage(
    'Регламент резервного копирования', 'regulation', 'security',
    '<h2>Расписание</h2><ul><li>Базы данных: ежедневно в 02:00 ночи</li><li>Файлы пользователей: ежедневно инкрементально</li><li>Конфигурации серверов: при каждом изменении</li><li>Полный бекап всей системы: еженедельно в воскресенье</li></ul><h2>Хранение</h2><ul><li>Daily backup — 7 дней</li><li>Weekly backup — 4 недели</li><li>Monthly backup — 12 месяцев</li><li>Yearly backup — 3 года</li></ul><h2>Тестирование</h2><p>Раз в квартал проводится тестовое восстановление из резервной копии. Результаты фиксируются в CRM.</p>',
    'published', 300
);

// Insert pages
$pageIds = []; // public_id => id
$spaceSlugToId = fn(string $slug) => $spaceIds[$slug] ?? 0;

foreach ($pages as $pageModel) {
    $slugValue = $pageModel->slug();
    $spaceId = $spaceSlugToId($pageModel->spaceSlug());

    if (exists($pdo, 'knowledge_pages', 'slug', $slugValue)) {
        $stmt = $pdo->prepare("SELECT id FROM knowledge_pages WHERE slug = :slug");
        $stmt->execute(['slug' => $slugValue]);
        $id = (int)$stmt->fetchColumn();
        $pageIds[$slugValue] = $id;
        echo "  • Page '{$pageModel->title()}' already exists (id={$id})\n";
        continue;
    }

    $pubId = publicId('kbp');
    $html = $pageModel->content();
    $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '</h2>', '</li>', '<br>'], ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    $excerpt = mb_substr($text, 0, 260);
    $nowTs = ago(random_int(1, 30));

    $data = [
        'public_id' => $pubId,
        'space_id' => $spaceId,
        'parent_id' => null,
        'title' => $pageModel->title(),
        'slug' => $slugValue,
        'page_type' => $pageModel->pageType(),
        'status' => $pageModel->status(),
        'content_html' => $html,
        'content_text' => $text,
        'content_json' => null,
        'excerpt' => $excerpt,
        'owner_user_id' => null,
        'last_editor_user_id' => null,
        'published_by_user_id' => null,
        'published_at' => $pageModel->status() === 'published' ? $nowTs : null,
        'review_due_at' => $pageModel->status() === 'published' ? plus(random_int(60, 180)) : null,
        'reviewed_at' => $pageModel->status() === 'published' ? $nowTs : null,
        'review_status' => $pageModel->status() === 'published' ? 'approved' : null,
        'reviewer_user_id' => null,
        'sort_order' => $pageModel->sortOrder(),
        'path' => '/' . $slugValue,
        'depth' => 0,
        'children_count' => 0,
        'comments_count' => 0,
        'attachments_count' => 0,
        'views_count' => random_int(10, 500),
        'likes_count' => 0,
        'row_version' => 1,
        'created_at' => $nowTs,
        'updated_at' => $nowTs,
        'deleted_at' => null,
    ];

    $id = insert($pdo, 'knowledge_pages', $data);
    $pageIds[$slugValue] = $id;

    // Create a version record for published pages
    if ($pageModel->status() === 'published') {
        $verPubId = publicId('kbv');
        insert($pdo, 'knowledge_page_versions', [
            'public_id' => $verPubId,
            'page_id' => $id,
            'version_number' => 1,
            'title' => $pageModel->title(),
            'content_html' => $html,
            'content_text' => $text,
            'content_json' => null,
            'change_summary' => 'Первая публикация',
            'created_by_user_id' => null,
            'created_at' => $nowTs,
        ]);
    }

    echo "  ✓ Created page: {$pageModel->title()} ({$pageModel->pageType()}) in {$pageModel->spaceSlug()}\n";
}

// ── 6. Create Tags and link to pages ──
echo "→ Creating tags...\n";

$tagData = [
    ['code' => 'crm', 'title' => 'CRM', 'color' => '#0f8f72'],
    ['code' => 'php', 'title' => 'PHP', 'color' => '#777bb3'],
    ['code' => 'frontend', 'title' => 'Frontend', 'color' => '#2563eb'],
    ['code' => 'devops', 'title' => 'DevOps', 'color' => '#dc2626'],
    ['code' => 'security', 'title' => 'Security', 'color' => '#374151'],
    ['code' => 'sales', 'title' => 'Продажи', 'color' => '#d97706'],
    ['code' => 'hr', 'title' => 'HR', 'color' => '#7c3aed'],
    ['code' => 'support', 'title' => 'Поддержка', 'color' => '#059669'],
    ['code' => 'onboarding', 'title' => 'Онбординг', 'color' => '#0891b2'],
    ['code' => 'architecture', 'title' => 'Архитектура', 'color' => '#9333ea'],
    ['code' => 'regulation', 'title' => 'Регламент', 'color' => '#b91c1c'],
    ['code' => 'faq', 'title' => 'FAQ', 'color' => '#0d9488'],
];

$tagPublicIds = [];
foreach ($tagData as $tag) {
    // Check if tag exists by code
    $stmt = $pdo->prepare("SELECT id, public_id FROM tags WHERE code = :code LIMIT 1");
    $stmt->execute(['code' => $tag['code']]);
    $existing = $stmt->fetch();
    if ($existing) {
        $tagPublicIds[$tag['code']] = $existing['public_id'];
        echo "  • Tag '{$tag['title']}' already exists\n";
        continue;
    }

    $pubId = publicId('tag');
    insert($pdo, 'tags', [
        'public_id' => $pubId,
        'code' => $tag['code'],
        'title' => $tag['title'],
        'color' => $tag['color'],
        'created_at' => ago(60),
    ]);
    $tagPublicIds[$tag['code']] = $pubId;
    echo "  ✓ Created tag: {$tag['title']}\n";
}

// Link tags to pages
echo "→ Linking tags to pages...\n";

$tagPageLinks = [
    'general' => ['crm', 'regulation', 'faq'],
    'korporativnye-standarty-raboty-v-crm' => ['crm', 'regulation'],
    'politika-ispolzovaniya-korporativnoj-pochty' => ['security', 'regulation'],
    'chasto-zadavaemye-voprosy-po-crm' => ['crm', 'faq'],
    'cheklist-proverka-akkaunta-novogo-sotrudnika' => ['onboarding', 'hr'],
    'standarty-kodirovaniya-php' => ['php', 'architecture'],
    'instrukciya-po-deployu-na-production' => ['devops', 'php'],
    'arhitektura-mikroservisov-prinyatye-resheniya' => ['architecture'],
    'onboarding-frontend-razrabotchika' => ['frontend', 'onboarding'],
    'protokol-arhitekturnogo-komiteta-23' => ['architecture'],
    'reglament-obrabotki-vhodyashchih-zayavok' => ['sales', 'crm'],
    'skript-holodnogo-zvonka' => ['sales'],
    'shablon-kommercheskogo-predlozheniya' => ['sales'],
    'rabota-s-vozrazheniyami-baza-znanij-otdela-prodazh' => ['sales', 'faq'],
    'politika-udalyonnoj-raboty' => ['hr', 'regulation'],
    'cheklist-onbordinga-novogo-sotrudnika' => ['hr', 'onboarding'],
    'programma-adaptacii-roadmap-na-90-dnej' => ['hr', 'onboarding'],
    'process-obrabotki-incidenta-p1' => ['support', 'devops', 'architecture'],
    'faq-chastye-voprosy-klientov-tehpodderzhki' => ['support', 'faq'],
    'pravila-oformleniya-bag-reportov' => ['support', 'frontend'],
    'reglament-provedeniya-ezhednevnyh-stendapov' => ['regulation'],
    'cheklist-zapuska-novogo-proekta' => ['crm'],
    'protokol-strategicheskoj-sessii-q2-2026' => ['architecture'],
    'politika-parolej-i-dostupa-k-sistemam' => ['security', 'regulation'],
    'instrukciya-podklyuchenie-k-korporativnomu-vpn' => ['security', 'devops', 'instruction'],
    'reglament-rezervnogo-kopirovaniya' => ['security', 'devops', 'regulation'],
];

foreach ($tagPageLinks as $pageSlug => $tagCodes) {
    if (!isset($pageIds[$pageSlug])) {
        echo "  ! Page slug '{$pageSlug}' not found, skipping tags\n";
        continue;
    }
    $pagePubId = '';
    $stmt = $pdo->prepare("SELECT public_id FROM knowledge_pages WHERE id = :id");
    $stmt->execute(['id' => $pageIds[$pageSlug]]);
    $pagePubId = (string)$stmt->fetchColumn();

    if ($pagePubId === '') continue;

    foreach ($tagCodes as $tagCode) {
        if (!isset($tagPublicIds[$tagCode])) continue;
        $stmt = $pdo->prepare("SELECT id FROM entity_tags WHERE entity_type = 'knowledge_page' AND entity_public_id = :eid AND tag_id IN (SELECT id FROM tags WHERE public_id = :tid)");
        $stmt->execute(['eid' => $pagePubId, 'tid' => $tagPublicIds[$tagCode]]);
        if ($stmt->fetch()) continue;

        $tagIdStmt = $pdo->prepare("SELECT id FROM tags WHERE public_id = :tid");
        $tagIdStmt->execute(['tid' => $tagPublicIds[$tagCode]]);
        $tagId = (int)$tagIdStmt->fetchColumn();
        if ($tagId <= 0) continue;

        insert($pdo, 'entity_tags', [
            'entity_type' => 'knowledge_page',
            'entity_public_id' => $pagePubId,
            'tag_id' => $tagId,
            'created_at' => now(),
        ]);
    }
    echo "  ✓ Tagged page: {$pageSlug}\n";
}

// ── 7. Add comments to pages ──
echo "→ Adding comments to pages...\n";

$comments = [
    'korporativnye-standarty-raboty-v-crm' => [
        ['body' => 'Коллеги, добавил пункт про обязательное указание времени в логах работы. Проверьте, пожалуйста.', 'days' => 25],
        ['body' => 'Всё верно, спасибо! Только в п.3.2 ошибка — ссылка на старый регламент.', 'days' => 24],
        ['body' => 'Исправил ссылку. Готово к утверждению.', 'days' => 23],
    ],
    'standarty-kodirovaniya-php' => [
        ['body' => 'Предлагаю добавить запрет на использование eval() и extract().', 'days' => 15],
        ['body' => 'Поддерживаю! Ещё хорошо бы явно описать правила именования тестов.', 'days' => 14],
        ['body' => 'Добавил оба пункта в раздел «Безопасность» и «Тестирование».', 'days' => 13],
    ],
    'instrukciya-po-deployu-na-production' => [
        ['body' => 'Отличная инструкция! Только нужно обновить версию PHP — уже 8.3 на проде.', 'days' => 10],
        ['body' => 'Обновил, спасибо за напоминание.', 'days' => 9],
    ],
    'reglament-obrabotki-vhodyashchih-zayavok' => [
        ['body' => 'Просьба добавить SLA для разных тарифных планов.', 'days' => 20],
        ['body' => 'Добавил таблицу с тарифами в раздел «Время реакции».', 'days' => 19],
    ],
    'politika-udalyonnoj-raboty' => [
        ['body' => 'Нужно добавить про компенсацию расходов на электроэнергию.', 'days' => 12],
        ['body' => 'Вопрос на согласовании с бухгалтерией. Добавим после утверждения.', 'days' => 11],
        ['body' => 'Бухгалтерия утвердила — 2000 руб/мес. Добавил в раздел «Компенсация».', 'days' => 8],
    ],
];

$guestUserId = null;

$res = $pdo->query("SELECT id FROM users WHERE login = 'admin' OR login = 'root' LIMIT 1");
$guestUserId = (int)$res->fetchColumn();
if ($guestUserId <= 0) {
    $guestUserId = 1; // fallback
}

foreach ($comments as $pageSlug => $commentList) {
    if (!isset($pageIds[$pageSlug])) continue;
    $pageId = $pageIds[$pageSlug];

    $stmt = $pdo->prepare("SELECT public_id FROM knowledge_pages WHERE id = :id");
    $stmt->execute(['id' => $pageId]);
    $pagePubId = (string)$stmt->fetchColumn();
    if ($pagePubId === '') continue;

    $prevCommentId = null;
    foreach ($commentList as $idx => $c) {
        $commentPubId = publicId('kbc');
        insert($pdo, 'knowledge_comments', [
            'public_id' => $commentPubId,
            'page_id' => $pageId,
            'parent_id' => $prevCommentId,
            'user_id' => $guestUserId,
            'body' => $c['body'],
            'resolved_at' => $idx === 2 && count($commentList) > 2 ? now() : null,
            'created_at' => ago($c['days']),
            'updated_at' => ago($c['days']),
        ]);

        $pdo->prepare("UPDATE knowledge_pages SET comments_count = comments_count + 1 WHERE id = :id")->execute(['id' => $pageId]);
        $prevCommentId = (int)$pdo->lastInsertId();
    }
    echo "  ✓ Added " . count($commentList) . " comments to: {$pageSlug}\n";
}

// ── 8. Add page views ──
echo "→ Adding page views...\n";

$viewSources = ['search', 'direct', 'entity', 'recent', 'favorite'];
$stmtView = $pdo->prepare("INSERT INTO knowledge_page_views (page_id, user_id, source, viewed_at) VALUES (:page_id, :user_id, :source, :viewed_at)");

foreach ($pageIds as $slug => $pageId) {
    $views = random_int(3, 20);
    for ($i = 0; $i < $views; $i++) {
        $stmtView->execute([
            'page_id' => $pageId,
            'user_id' => $guestUserId ?: null,
            'source' => $viewSources[array_rand($viewSources)],
            'viewed_at' => ago(random_int(0, 60)),
        ]);
    }
}
echo "  ✓ Added page views\n";

// ── 9. Add entity links (link pages to projects/tasks/clients) ──
echo "→ Adding entity links...\n";

// Try to find existing entities to link to
$projects = $pdo->query("SELECT id, public_id, title FROM projects LIMIT 10")->fetchAll();
$tasks = $pdo->query("SELECT id, public_id, title FROM tasks LIMIT 10")->fetchAll();
$clients = $pdo->query("SELECT id, public_id, title FROM clients LIMIT 10")->fetchAll();

$entityLinks = [
    'standarty-kodirovaniya-php' => [],
    'instrukciya-po-deployu-na-production' => [],
    'arhitektura-mikroservisov-prinyatye-resheniya' => [],
    'reglament-obrabotki-vhodyashchih-zayavok' => [],
    'cheklist-zapuska-novogo-proekta' => [],
];

// Link to existing projects
foreach ($entityLinks as $pageSlug => &$links) {
    if (!isset($pageIds[$pageSlug])) continue;
    $pageId = $pageIds[$pageSlug];

    $stmt = $pdo->prepare("SELECT public_id FROM knowledge_pages WHERE id = :id");
    $stmt->execute(['id' => $pageId]);
    $pagePubId = (string)$stmt->fetchColumn();
    if ($pagePubId === '') continue;

    foreach ($projects as $proj) {
        if (random_int(0, 1)) continue;
        // Check if link already exists
        $chk = $pdo->prepare("SELECT id FROM knowledge_entity_links WHERE page_id = :pid AND entity_type = 'project' AND entity_public_id = :eid");
        $chk->execute(['pid' => $pageId, 'eid' => $proj['public_id']]);
        if ($chk->fetch()) continue;

        insert($pdo, 'knowledge_entity_links', [
            'public_id' => publicId('kbl'),
            'page_id' => $pageId,
            'entity_type' => 'project',
            'entity_public_id' => $proj['public_id'],
            'relation_type' => 'related',
            'created_by_user_id' => $guestUserId ?: null,
            'created_at' => ago(random_int(1, 30)),
        ]);
        echo "  ✓ Linked '{$pageSlug}' → project: {$proj['title']}\n";
    }

    foreach ($tasks as $task) {
        if (random_int(0, 2)) continue;
        $chk = $pdo->prepare("SELECT id FROM knowledge_entity_links WHERE page_id = :pid AND entity_type = 'task' AND entity_public_id = :eid");
        $chk->execute(['pid' => $pageId, 'eid' => $task['public_id']]);
        if ($chk->fetch()) continue;

        insert($pdo, 'knowledge_entity_links', [
            'public_id' => publicId('kbl'),
            'page_id' => $pageId,
            'entity_type' => 'task',
            'entity_public_id' => $task['public_id'],
            'relation_type' => 'instruction',
            'created_by_user_id' => $guestUserId ?: null,
            'created_at' => ago(random_int(1, 30)),
        ]);
        echo "  ✓ Linked '{$pageSlug}' → task: {$task['title']}\n";
    }

    foreach ($clients as $client) {
        if (random_int(0, 3)) continue;
        $chk = $pdo->prepare("SELECT id FROM knowledge_entity_links WHERE page_id = :pid AND entity_type = 'client' AND entity_public_id = :eid");
        $chk->execute(['pid' => $pageId, 'eid' => $client['public_id']]);
        if ($chk->fetch()) continue;

        insert($pdo, 'knowledge_entity_links', [
            'public_id' => publicId('kbl'),
            'page_id' => $pageId,
            'entity_type' => 'client',
            'entity_public_id' => $client['public_id'],
            'relation_type' => 'related',
            'created_by_user_id' => $guestUserId ?: null,
            'created_at' => ago(random_int(1, 30)),
        ]);
        echo "  ✓ Linked '{$pageSlug}' → client: {$client['title']}\n";
    }
}

// ── 10. Add favorites and subscriptions ──
echo "→ Adding favorites and subscriptions...\n";

$favPages = ['korporativnye-standarty-raboty-v-crm', 'standarty-kodirovaniya-php', 'instrukciya-po-deployu-na-production', 'reglament-obrabotki-vhodyashchih-zayavok', 'politika-udalyonnoj-raboty'];
foreach ($favPages as $pageSlug) {
    if (!isset($pageIds[$pageSlug])) continue;
    $stmt = $pdo->prepare("SELECT public_id FROM knowledge_pages WHERE id = :id");
    $stmt->execute(['id' => $pageIds[$pageSlug]]);
    $pagePubId = (string)$stmt->fetchColumn();
    if ($pagePubId === '' || $guestUserId <= 0) continue;

    // Favorite
    $chk = $pdo->prepare("SELECT id FROM favorites WHERE entity_type = 'knowledge_page' AND entity_public_id = :eid AND user_id = :uid");
    $chk->execute(['eid' => $pagePubId, 'uid' => $guestUserId]);
    if (!$chk->fetch()) {
        insert($pdo, 'favorites', [
            'public_id' => 'fav_' . strtoupper(bin2hex(random_bytes(10))),
            'user_id' => $guestUserId,
            'entity_type' => 'knowledge_page',
            'entity_public_id' => $pagePubId,
            'created_at' => ago(random_int(5, 30)),
        ]);
    }

    // Subscription
    $chk = $pdo->prepare("SELECT id FROM subscriptions WHERE entity_type = 'knowledge_page' AND entity_public_id = :eid AND user_id = :uid");
    $chk->execute(['eid' => $pagePubId, 'uid' => $guestUserId]);
    if (!$chk->fetch()) {
        insert($pdo, 'subscriptions', [
            'public_id' => 'sub_' . strtoupper(bin2hex(random_bytes(10))),
            'user_id' => $guestUserId,
            'entity_type' => 'knowledge_page',
            'entity_public_id' => $pagePubId,
            'created_at' => ago(random_int(5, 30)),
        ]);
    }
    echo "  ✓ Favorite + subscription for: {$pageSlug}\n";
}

// ── 11. Summary ──
echo "\n═══════════════════════════════════════\n";
echo "  KNOWLEDGE BASE SEED COMPLETED\n";
echo "═══════════════════════════════════════\n";

$countSpaces = $pdo->query("SELECT COUNT(*) FROM knowledge_spaces")->fetchColumn();
$countPages = $pdo->query("SELECT COUNT(*) FROM knowledge_pages")->fetchColumn();
$countPublished = $pdo->query("SELECT COUNT(*) FROM knowledge_pages WHERE status = 'published'")->fetchColumn();
$countDrafts = $pdo->query("SELECT COUNT(*) FROM knowledge_pages WHERE status = 'draft'")->fetchColumn();
$countVersions = $pdo->query("SELECT COUNT(*) FROM knowledge_page_versions")->fetchColumn();
$countComments = $pdo->query("SELECT COUNT(*) FROM knowledge_comments")->fetchColumn();
$countTags = $pdo->query("SELECT COUNT(*) FROM entity_tags")->fetchColumn();
$countLinks = $pdo->query("SELECT COUNT(*) FROM knowledge_entity_links")->fetchColumn();
$countViews = $pdo->query("SELECT COUNT(*) FROM knowledge_page_views")->fetchColumn();
$countTemplates = $pdo->query("SELECT COUNT(*) FROM knowledge_templates")->fetchColumn();

echo "  Разделов (Spaces):         {$countSpaces}\n";
echo "  Страниц (Pages):           {$countPages}\n";
echo "  Опубликовано:              {$countPublished}\n";
echo "  Черновиков:                {$countDrafts}\n";
echo "  Версий:                    {$countVersions}\n";
echo "  Комментариев:              {$countComments}\n";
echo "  Тегов привязано:           {$countTags}\n";
echo "  Связей с сущностями:       {$countLinks}\n";
echo "  Просмотров:                {$countViews}\n";
echo "  Шаблонов:                  {$countTemplates}\n";
echo "═══════════════════════════════════════\n";
echo "  База знаний готова к использованию!\n";
echo "  Откройте: index.php?route=knowledge\n";
echo "═══════════════════════════════════════\n";
