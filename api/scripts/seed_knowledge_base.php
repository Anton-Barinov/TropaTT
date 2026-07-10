<?php

declare(strict_types=1);
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }


/**
 * Скрипт наполнения Базы Знаний реальным контентом агентства Aurora Digital
 *
 * Запуск:
 *   CRM_SEED_ROOT_PASSWORD=... CRM_SEED_API_BASE=... php api/scripts/seed_knowledge_base.php
 *
 * Без CRM_SEED_API_BASE использует https://localhost
 */

final class KnowledgeBaseSeeder
{
    private string $baseUrl = 'https://localhost/api/index.php?route=';
    private string $token = '';

    private array $users = [];
    private array $projects = [];
    private array $tasks = [];
    private array $clients = [];
    private array $companies = [];
    private array $tags = [];

    private array $spaces = [];
    private array $pages = [];

    public function run(): void
    {
        $baseUrl = trim((string)getenv('CRM_SEED_API_BASE'));
        if ($baseUrl !== '') {
            $this->baseUrl = rtrim($baseUrl, '?&') . (str_contains($baseUrl, '?route=') ? '' : '?route=');
        }

        $rootPassword = trim((string)(getenv('CRM_SEED_ROOT_PASSWORD') ?: getenv('CRM_TEST_ROOT_PASSWORD') ?: ''));
        if ($rootPassword === '') {
            throw new RuntimeException('Set CRM_SEED_ROOT_PASSWORD or CRM_TEST_ROOT_PASSWORD');
        }

        echo "=== Наполнение Базы Знаний Aurora Digital ===\n\n";

        $this->login('root', $rootPassword);
        $this->loadExistingEntities();
        $this->seedTemplates();
        $this->seedAllSpaces();
        $this->addLinksAndComments();
        $this->addFavoritesAndSubscriptions();
        $this->addTagsToPages();
        $this->printSummary();
    }

    private function login(string $login, string $password): void
    {
        echo "Авторизация... ";
        $res = $this->request('POST', 'api/v1/auth/login', [
            'login' => $login,
            'password' => $password,
        ], false);
        $this->token = (string)($res['data']['access_token'] ?? '');
        if ($this->token === '') {
            throw new RuntimeException('Не удалось получить access_token');
        }
        echo "OK\n";
    }

    private function loadExistingEntities(): void
    {
        echo "\nЗагрузка существующих сущностей...\n";

        // Users
        $res = $this->request('GET', 'api/v1/users');
        foreach ($res['data']['items'] ?? [] as $u) {
            $key = $this->userKey((string)($u['full_name'] ?? $u['login'] ?? ''));
            $this->users[$key] = $u;
        }

        // Projects
        $res = $this->request('GET', 'api/v1/projects');
        foreach ($res['data']['items'] ?? [] as $p) {
            $this->projects[(string)$p['public_id']] = $p;
        }

        // Tasks
        $res = $this->request('GET', 'api/v1/tasks');
        foreach ($res['data']['items'] ?? [] as $t) {
            $this->tasks[(string)$t['public_id']] = $t;
        }

        // Clients
        $res = $this->request('GET', 'api/v1/clients');
        foreach ($res['data']['items'] ?? [] as $c) {
            $this->clients[(string)$c['public_id']] = $c;
        }

        // Companies
        $res = $this->request('GET', 'api/v1/companies');
        foreach ($res['data']['items'] ?? [] as $c) {
            $this->companies[(string)$c['public_id']] = $c;
        }

        // Tags
        $res = $this->request('GET', 'api/v1/tags');
        foreach ($res['data']['items'] ?? [] as $t) {
            $this->tags[(string)$t['code']] = $t;
        }

        printf("  Пользователей: %d\n", count($this->users));
        printf("  Проектов: %d\n", count($this->projects));
        printf("  Задач: %d\n", count($this->tasks));
        printf("  Клиентов: %d\n", count($this->clients));
        printf("  Компаний: %d\n", count($this->companies));
        printf("  Тегов: %d\n", count($this->tags));
    }

    private function seedTemplates(): void
    {
        echo "\nСоздание шаблонов...\n";

        $templates = [
            [
                'title' => 'Шаблон инструкции',
                'page_type' => 'instruction',
                'description' => 'Стандартный шаблон для пошаговых инструкций с нумерованными шагами',
                'content_html' => '<h2>Цель</h2><p>Опишите, какую задачу решает инструкция.</p><h2>Необходимые доступы</h2><ul><li>Доступ к панели управления</li><li>Учётная запись с правами администратора</li></ul><h2>Пошаговая инструкция</h2><ol><li>Шаг первый: описание</li><li>Шаг второй: описание</li><li>Шаг третий: описание</li></ol><h2>Ожидаемый результат</h2><p>Что должно получиться после выполнения.</p><h2>Частые ошибки</h2><ul><li>Ошибка 1 — решение</li><li>Ошибка 2 — решение</li></ul>',
            ],
            [
                'title' => 'Шаблон встречи',
                'page_type' => 'meeting_note',
                'description' => 'Шаблон для записи встреч с клиентами и командных синхронизаций',
                'content_html' => '<h2>Дата и участники</h2><p>Дата: </p><p>Участники: </p><h2>Повестка</h2><ol><li>Пункт 1</li><li>Пункт 2</li><li>Пункт 3</li></ol><h2>Обсуждение</h2><p>Ключевые моменты встречи.</p><h2>Решения</h2><ul><li>Решение 1</li><li>Решение 2</li></ul><h2>Действия</h2><ul><li>[@Ответственный] Сделать до [Дата]</li><li>[@Ответственный] Сделать до [Дата]</li></ul>',
            ],
            [
                'title' => 'Шаблон чек-листа релиза',
                'page_type' => 'checklist',
                'description' => 'Чек-лист проверки перед выкаткой релиза на production',
                'content_html' => '<h2>Предрелизная проверка</h2><ul><li>Проверены миграции БД</li><li>Собраны статические ассеты</li><li>Обновлён changelog</li><li>Пройдено code-review</li><li>QA подтвердил готовность</li></ul><h2>Релиз</h2><ul><li>Деплой на staging</li><li>Smoke-тесты</li><li>Деплой на production</li><li>Мониторинг 15 минут</li></ul><h2>Пострелиз</h2><ul><li>Уведомление команды</li><li>Обновление документации</li><li>Ретроспектива</li></ul>',
            ],
            [
                'title' => 'Шаблон Architectural Decision Record',
                'page_type' => 'decision',
                'description' => 'Шаблон для документирования архитектурных решений (ADR)',
                'content_html' => '<h2>Контекст</h2><p>Почему возникла необходимость принять решение.</p><h2>Рассматриваемые варианты</h2><ul><li>Вариант А: описание</li><li>Вариант Б: описание</li></ul><h2>Решение</h2><p>Выбран: <strong>Вариант А/Б</strong></p><h2>Обоснование</h2><p>Почему выбран именно этот вариант.</p><h2>Последствия</h2><ul><li>Позитивные: ...</li><li>Негативные: ...</li></ul><h2>Статус</h2><p>Предложено | Принято | Отклонено | Заменено</p>',
            ],
        ];

        foreach ($templates as $tmpl) {
            try {
                $this->request('POST', 'api/v1/knowledge/templates', $tmpl);
                echo "  + Шаблон: {$tmpl['title']}\n";
            } catch (RuntimeException $e) {
                echo "  ! {$tmpl['title']}: {$e->getMessage()}\n";
            }
        }
    }

    private function seedAllSpaces(): void
    {
        echo "\nСоздание пространств и страниц...\n";

        // === Space 1: Внутренние регламенты ===
        $this->createSpace('Внутренние регламенты', 'vnutrennie-reglamenty',
            'Политики, регламенты и нормативные документы агентства',
            'book-open', '#1e40af');

        $this->createPage('kbs_1', 'Политика безопасности данных', 'article', 'published', '
            <h2>Общие положения</h2>
            <p>Настоящая политика определяет порядок обработки, хранения и передачи данных клиентов и сотрудников агентства Aurora Digital.</p>
            <h2>Категории данных</h2>
            <ul>
                <li><strong>Персональные данные</strong>: ФИО, телефоны, email, паспортные данные сотрудников и контактных лиц клиентов</li>
                <li><strong>Коммерческая информация</strong>: договоры, коммерческие предложения, акты, финансовые документы</li>
                <li><strong>Техническая информация</strong>: доступы к серверам, API-ключи, пароли, токены</li>
            </ul>
            <h2>Правила доступа</h2>
            <p>Каждый сотрудник получает доступ только к тем данным, которые необходимы для выполнения его рабочих обязанностей. Доступ к паролям и токенам осуществляется исключительно через корпоративный менеджер паролей.</p>
            <h2>Хранение данных</h2>
            <p>Все клиентские данные хранятся на серверах, расположенных на территории РФ. Резервное копирование выполняется ежедневно с хранением копий за 30 дней.</p>
            <h2>Ответственность</h2>
            <p>Нарушение политики безопасности влечёт дисциплинарную ответственность вплоть до увольнения.</p>
        ');

        $this->createPage('kbs_1', 'Регламент работы с клиентскими данными', 'regulation', 'published', '
            <h2>Введение</h2>
            <p>Настоящий регламент устанавливает порядок получения, обработки, хранения и удаления данных клиентов агентства.</p>
            <h2>Порядок получения данных</h2>
            <ol>
                <li>Подписание NDA до начала любых работ</li>
                <li>Приёмка данных через защищённый канал (SFTP или зашифрованный архив)</li>
                <li>Фиксация перечня полученных данных в акте приёма-передачи</li>
            </ol>
            <h2>Обработка данных</h2>
            <p>Все операции с клиентскими данными логируются. Изменения production-данных производятся только через официальные запросы с согласованием.</p>
            <h2>Удаление данных</h2>
            <p>После завершения договора клиентские данные удаляются с серверов в течение 30 дней. Архивированная копия хранится до 1 года на изолированном носителе.</p>
        ');

        $this->createPage('kbs_1', 'Регламент code-review и PR', 'regulation', 'published', '
            <h2>Обязательные требования</h2>
            <ul>
                <li>Каждый PR должен быть проверен минимум одним разработчиком</li>
                <li>PR не может быть принят автором самостоятельно</li>
                <li>Время реакции на PR — не более 4 рабочих часов</li>
            </ul>
            <h2>Что проверяем</h2>
            <ol>
                <li>Корректность архитектуры: нет ли нарушения слоёв, принципов SOLID</li>
                <li>Безопасность: отсутствие SQL-инъекций, XSS, уязвимостей авторизации</li>
                <li>Производительность: нет ли N+1 запросов, тяжёлых вычислений на фронте</li>
                <li>Тесты: написаны ли unit-тесты, проходят ли CI</li>
                <li>Стиль кода: соответствует ли PSR-12 / Code Style проекта</li>
            </ol>
            <h2>Метки для PR</h2>
            <ul>
                <li><strong>approved</strong> — можно мёржить</li>
                <li><strong>changes-requested</strong> — нужны исправления</li>
                <li><strong>comment</strong> — замечание без блокировки</li>
            </ul>
        ');

        $this->createPage('kbs_1', 'Порядок оформления документов', 'instruction', 'published', '
            <h2>Типы документов</h2>
            <ul>
                <li><strong>Договор</strong> — через шаблон в Google Docs, согласование с юристом</li>
                <li><strong>Акт выполненных работ</strong> — ежемесячно, до 5 числа</li>
                <li><strong>Коммерческое предложение</strong> — в корпоративном шаблоне Canva</li>
                <li><strong>Счёт</strong> — через 1С или ЭДО</li>
            </ul>
            <h2>Правила оформления</h2>
            <ol>
                <li>Использовать корпоративные шаблоны</li>
                <li>Проверять реквизиты контрагента через сервис проверки контрагентов</li>
                <li>Соблюдать маскирование коммерческой тайны (гриф "ДСП")</li>
            </ol>
        ');

        // === Space 2: Процессы и методологии ===
        $this->createSpace('Процессы и методологии', 'processy-i-metodologii',
            'Описание рабочих процессов, методологий и best practices',
            'clipboard-list', '#0f8f72');

        $this->createPage('kbs_2', 'Методология работы в агентстве', 'article', 'published', '
            <h2>Гибридная методология</h2>
            <p>Мы используем гибридный подход: Scrum в продуктовых проектах и Kanban в поддержке.</p>
            <h2>Цикл спринта (2 недели)</h2>
            <ul>
                <li><strong>Понедельник 1 недели</strong>: Планирование спринта (2 часа)</li>
                <li><strong>Ежедневно</strong>: Daily standup (15 минут)</li>
                <li><strong>Пятница 2 недели</strong>: Обзор спринта и ретроспектива</li>
            </ul>
            <h2>Роли</h2>
            <ul>
                <li><strong>Project Manager</strong>: планирование, коммуникация с клиентом, контроль сроков</li>
                <li><strong>Team Lead</strong>: архитектура, code-review, распределение задач</li>
                <li><strong>Разработчик</strong>: реализация задач, документация кода</li>
                <li><strong>QA Engineer</strong>: тестирование, автотесты, регресс</li>
                <li><strong>Аккаунт-менеджер</strong>: управление ожиданиями, доп. продажи</li>
            </ul>
        ');

        $this->createPage('kbs_2', 'Развёртывание проекта на staging', 'runbook', 'published', '
            <h2>Подготовка</h2>
            <ul>
                <li>Убедиться, что последняя версия кода собрана (CI зелёный)</li>
                <li>Проверить доступность staging-сервера</li>
            </ul>
            <h2>Деплой</h2>
            <ol>
                <li>Переключиться на ветку main/master: <code>git checkout main && git pull</code></li>
                <li>Запустить деплой: <code>./deploy.sh staging</code> или через GitHub Actions</li>
                <li>Проверить миграции: <code>php api/scripts/migrate.php</code></li>
                <li>Собрать ассеты: <code>npm run build</code></li>
            </ol>
            <h2>Проверка</h2>
            <ul>
                <li>Открыть главную страницу — 200 OK</li>
                <li>Проверить авторизацию и ключевые сценарии</li>
                <li>Проверить логи: <code>tail -f /var/log/nginx/error.log</code></li>
            </ul>
            <h2>Откат</h2>
            <p>В случае ошибки: <code>git revert HEAD --no-edit && git push && ./deploy.sh staging</code></p>
        ');

        $this->createPage('kbs_2', 'Чек-лист перед релизом', 'checklist', 'published', '
            <h2>За день до релиза</h2>
            <ul>
                <li>Все задачи в статусе "Готово к релизу"</li>
                <li>Протестированы критические сценарии QA</li>
                <li>Составлен changelog</li>
            </ul>
            <h2>В день релиза</h2>
            <ul>
                <li>Уведомить команду в Slack о начале релизного окна</li>
                <li>Залить миграции на production</li>
                <li>Развернуть новую версию</li>
                <li>Проверить health-check endpoint</li>
                <li>Проверить консоль браузера на наличие ошибок JS</li>
            </ul>
            <h2>После релиза</h2>
            <ul>
                <li>Мониторинг ошибок 30 минут через Sentry</li>
                <li>Отправить уведомление клиенту</li>
                <li>Обновить документацию</li>
            </ul>
        ');

        $this->createPage('kbs_2', 'Работа с Git: правила и соглашения', 'instruction', 'published', '
            <h2>Ветки</h2>
            <ul>
                <li><code>main</code> — продакшен, защищена от прямых пушей</li>
                <li><code>develop</code> — интеграционная ветка</li>
                <li><code>feature/TASK-123-kratkoe-opisanie</code> — ветки задач</li>
                <li><code>hotfix/opisanie</code> — срочные исправления</li>
            </ul>
            <h2>Коммиты</h2>
            <p>Используем Conventional Commits:</p>
            <ul>
                <li><code>feat:</code> — новый функционал</li>
                <li><code>fix:</code> — исправление бага</li>
                <li><code>refactor:</code> — рефакторинг без изменения поведения</li>
                <li><code>docs:</code> — документация</li>
                <li><code>test:</code> — тесты</li>
                <li><code>chore:</code> — технические изменения</li>
            </ul>
        ');

        // === Space 3: Клиентские проекты ===
        $this->createSpace('Клиентские проекты', 'klientskie-proekty',
            'Документация по проектам клиентов: архитектура, решения, встречи',
            'briefcase', '#7c3aed');

        $this->createPage('kbs_3', 'Verona Travel — Архитектура решения', 'project_note', 'published', '
            <h2>Обзор проекта</h2>
            <p>Редизайн и SEO-рост сайта туристического агентства Verona Travel. Цель — увеличить органический трафик на 200% и улучшить конверсию бронирований.</p>
            <h2>Технический стек</h2>
            <ul>
                <li>Frontend: Next.js 14 + Tailwind CSS</li>
                <li>Backend: PHP 8.2 + Laravel Octane</li>
                <li>База данных: PostgreSQL 16</li>
                <li>Search: Meilisearch для поиска туров</li>
                <li>Infra: Docker + Kubernetes (Digital Ocean)</li>
            </ul>
            <h2>Структура данных</h2>
            <p>Основные сущности: Туры, Категории, Страны, Отели, Отзывы, Бронирования. Реализована полнотекстового поиска через Meilisearch с фильтрацией по датам, цене, типу тура.</p>
            <h2>SEO-архитектура</h2>
            <ul>
                <li>Динамические meta-теги для каждой страницы</li>
                <li>Schema.org (Product, Vacation, Review)</li>
                <li>SSR для поисковых ботов</li>
                <li>Карта сайта с приоритетами</li>
            </ul>
            <h2>API интеграции</h2>
            <ul>
                <li>Mystifly — поиск и бронирование авиабилетов</li>
                <li>TravelLine — отельная база</li>
                <li>Mindbox — CRM и триггерные email-рассылки</li>
            </ul>
        ');

        $this->createPage('kbs_3', 'Verona Travel — План SEO-оптимизации', 'project_note', 'published', '
            <h2>Аудит текущего состояния</h2>
            <p>Проведён технический аудит: выявлено 47 критических ошибок (дубли страниц, медленная загрузка, отсутствие alt-тегов, битые ссылки).</p>
            <h2>Этапы работ</h2>
            <ol>
                <li><strong>Этап 1 (2 недели)</strong>: Исправление технических ошибок, настройка 301 редиректов</li>
                <li><strong>Этап 2 (3 недели)</strong>: Переписывание мета-тегов, создание SEO-текстов для 50 страниц</li>
                <li><strong>Этап 3 (2 недели)</strong>: Внедрение Schema.org разметки, JSON-LD</li>
                <li><strong>Этап 4 (1 неделя)</strong>: Настройка Google Search Console, Google Analytics 4, целей</li>
            </ol>
            <h2>Метрики</h2>
            <ul>
                <li>Core Web Vitals: все зелёные</li>
                <li>Mobile first index — адаптация под Mobile-first</li>
                <li>Organic traffic growth: +200% за 6 месяцев</li>
            </ul>
        ');

        $this->createPage('kbs_3', 'Alpina Home — Выбор платформы каталога', 'decision', 'published', '
            <h2>Контекст</h2>
            <p>Интернет-магазин Alpina Home (товары для дома) требует обновления каталога. Старая платформа не справляется с нагрузкой и не поддерживает новые форматы контента.</p>
            <h2>Рассматриваемые варианты</h2>
            <ul>
                <li><strong>Вариант А:</strong> Shopware 6 — мощная, но тяжёлая платформа с высокими требованиями к хостингу</li>
                <li><strong>Вариант Б:</strong> Модернизация текущей self-made платформы — дешевле, но дольше</li>
                <li><strong>Вариант В:</strong> Shoper — SaaS-решение, быстро, но ограниченная кастомизация</li>
            </ul>
            <h2>Решение</h2>
            <p>Выбран <strong>Вариант Б: модернизация текущей платформы</strong>. Основание: клиент не хочет менять привычную админку, бюджет ограничен, функциональные требования покрываются текущей архитектурой с доработками.</p>
            <h2>План доработок</h2>
            <ul>
                <li>Переход на PHP 8.2 + улучшение производительности</li>
                <li>Внедрение Elasticsearch для поиска</li>
                <li>Рефакторинг корзины и оформления заказа</li>
                <li>API-интеграция с 1С и МойСклад</li>
            </ul>
            <h2>Статус</h2>
            <p>Принято, реализация в процессе.</p>
        ');

        $this->createPage('kbs_3', 'Tehnograd Logistic — B2B портал заказов', 'project_note', 'published', '
            <h2>О проекте</h2>
            <p>Разработка B2B-портала для дилеров компании Техноград Логистик. Система позволяет дилерам оформлять заказы, отслеживать поставки, просматривать остатки на складах и формировать отчётность.</p>
            <h2>Архитектура</h2>
            <ul>
                <li>Frontend: React + TypeScript + Ant Design</li>
                <li>Backend: PHP 8.2 + API Platform (REST + GraphQL)</li>
                <li>DB: MySQL 8 с партиционированием</li>
                <li>ERP интеграция: RabbitMQ + XML-коннектор</li>
            </ul>
            <h2>Ключевые модули</h2>
            <ol>
                <li><strong>Личный кабинет дилера</strong>: авторизация через SSO, управление профилем</li>
                <li><strong>Каталог и заказы</strong>: поиск по артикулам, корзина, оформление, история</li>
                <li><strong>Мониторинг поставок</strong>: статусы, трекинг, уведомления</li>
                <li><strong>Дашборды KPI</strong>: объём заказов, сроки, просрочки</li>
                <li><strong>Отчёты</strong>: Excel-выгрузки, графики по периодам</li>
            </ol>
            <h2>Сроки</h2>
            <p>MVP — 3 месяца, полный запуск — 6 месяцев.</p>
        ');

        $this->createPage('kbs_3', 'Встреча с Verona Travel 15 апреля', 'meeting_note', 'published', '
            <h2>Дата и участники</h2>
            <p>Дата: 15 апреля 2026</p>
            <p>Участники: Анна Смирнова (аккаунт-менеджер), Ирина Морозова (PM), Екатерина Романова (клиент), Максим Виноградов (технический директор клиента)</p>
            <h2>Повестка</h2>
            <ol>
                <li>Статус редизайна главной страницы</li>
                <li>Результаты SEO-аудита</li>
                <li>Планы на следующий спринт</li>
                <li>Бюджет и сроки</li>
            </ol>
            <h2>Обсуждение</h2>
            <p>Клиент доволен прогрессом, но просит ускорить внедрение Schema.org разметки. Договорились выделить дополнительного разработчика на эту задачу. По SEO-аудиту — клиент утвердил все рекомендации.</p>
            <h2>Решения</h2>
            <ul>
                <li>Выделить Марию Киселёву на Schema.org разметку</li>
                <li>Увеличить бюджет на копирайтинг SEO-текстов</li>
                <li>Следующая встреча: 29 апреля</li>
            </ul>
        ');

        $this->createPage('kbs_3', 'Ретроспектива проекта Verona Travel', 'meeting_note', 'published', '
            <h2>Дата и участники</h2>
            <p>Дата: 18 апреля 2026</p>
            <p>Команда: Ирина, Никита, Алексей, Мария, Илья, Евгения</p>
            <h2>Что прошло хорошо ✅</h2>
            <ul>
                <li>Быстрый старт благодаря хорошей подготовке</li>
                <li>Отличная коммуникация с клиентом</li>
                <li>Чистый код, минимум багов на ревью</li>
            </ul>
            <h2>Что можно улучшить 🔧</h2>
            <ul>
                <li>Оценки задач: часто переоцениваем или недооцениваем</li>
                <li>Не хватает автотестов на критические сценарии</li>
                <li>Запаздывает документация API</li>
            </ul>
            <h2>Action items</h2>
            <ul>
                <li>Ввести покер планирования для сложных задач</li>
                <li>Добавить в Definition of Done обязательные автотесты</li>
                <li>Писать документацию параллельно с кодом</li>
            </ul>
        ');

        // === Space 4: Разработка и технологии ===
        $this->createSpace('Разработка и технологии', 'razrabotka-i-tehnologii',
            'Техническая документация, стандарты разработки, интеграции',
            'code', '#2563eb');

        $this->createPage('kbs_4', 'Настройка CI/CD для проектов', 'article', 'published', '
            <h2>GitHub Actions Pipeline</h2>
            <p>Все проекты используют GitHub Actions для CI/CD. Базовый pipeline включает:</p>
            <ul>
                <li>Lint: PHP CS Fixer + ESLint</li>
                <li>TypeScript check: tsc --noEmit</li>
                <li>Unit tests: PHPUnit + Jest</li>
                <li>Build: npm run build + composer install --no-dev</li>
                <li>Deploy: через SSH на staging/production</li>
            </ul>
            <h2>Настройка для нового проекта</h2>
            <ol>
                <li>Скопировать <code>.github/workflows/ci.yml</code> из template-репозитория</li>
                <li>Настроить секреты: <code>DEPLOY_KEY</code>, <code>DEPLOY_HOST</code>, <code>DEPLOY_PATH</code></li>
                <li>Проверить переменные окружения в <code>.env.production</code></li>
                <li>Запустить тестовый деплой</li>
            </ol>
        ');

        $this->createPage('kbs_4', 'Стандарты кода PHP', 'article', 'published', '
            <h2>Стандарты оформления</h2>
            <ul>
                <li>PSR-12 (обязательно)</li>
                <li>Строгая типизация (<code>declare(strict_types=1)</code>)</li>
                <li>Именование: camelCase для методов, snake_case для БД</li>
                <li>DocBlocks только для сложной логики</li>
            </ul>
            <h2>Архитектурные принципы</h2>
            <ul>
                <li>SOLID, особенно Dependency Injection</li>
                <li>Repository Pattern для доступа к данным</li>
                <li>Service Layer для бизнес-логики</li>
                <li>DTO для передачи данных между слоями</li>
            </ul>
            <h2>Запрещённые практики</h2>
            <ul>
                <li>Глобальные состояния и синглтоны</li>
                <li>die()/exit() в production-коде</li>
                <li>Прямые SQL-запросы в контроллерах</li>
                <li>Смешивание бизнес-логики и представления</li>
            </ul>
        ');

        $this->createPage('kbs_4', 'Интеграция с ERP Альпины', 'instruction', 'published', '
            <h2>Общая схема</h2>
            <p>Интеграция интернет-магазина Alpina Home с ERP системой (1С:Управление торговлей) через RabbitMQ.</p>
            <h2>Потоки данных</h2>
            <ul>
                <li><strong>Товары → из ERP</strong>: импорт номенклатуры, цен, остатков (каждые 30 минут)</li>
                <li><strong>Заказы → в ERP</strong>: экспорт созданных заказов (real-time)</li>
                <li><strong>Статусы → в магазин</strong>: обновление статусов доставки и оплаты</li>
            </ul>
            <h2>Формат сообщений</h2>
            <pre><code>{
    "event": "order.created",
    "order_id": 12345,
    "items": [{"sku": "ART-001", "qty": 2}],
    "total": 5990.00
}</code></pre>
            <h2>Мониторинг</h2>
            <ul>
                <li>RabbitMQ Management UI — проверка очередей</li>
                <li>Логи: /var/log/erp-integration/*.log</li>
                <li>Дашборд в Grafana: метрики количества сообщений, ошибок, времени обработки</li>
            </ul>
        ');

        $this->createPage('kbs_4', 'Частые ошибки при сборке проектов', 'faq', 'published', '
            <h2>Ошибка: "npm install" падает с EACCES</h2>
            <p>Решение: использовать <code>nvm</code> и не запускать npm от root. Проверьте права на <code>node_modules</code>.</p>
            <h2>Ошибка: PHP memory limit exhausted</h2>
            <p>Увеличьте <code>memory_limit</code> в php.ini до 512M. Для composer: <code>COMPOSER_MEMORY_LIMIT=-1 composer install</code></p>
            <h2>Ошибка: MySQL max_allowed_packet</h2>
            <p>Импорт больших дампов падает. Увеличьте <code>max_allowed_packet</code> до 256M в my.cnf.</p>
            <h2>Git: merge conflict в composer.lock</h2>
            <p>Не редактируйте его вручную. Выполните: <code>git checkout --theirs composer.lock && composer install</code></p>
            <h2>Docker: порт уже занят</h2>
            <p>Проверьте <code>docker ps</code> или смените порт в docker-compose.yml. Типичный конфликт — порт 3306 (MySQL).</p>
        ');

        // === Space 5: Дизайн и UX ===
        $this->createSpace('Дизайн и UX', 'dizain-i-ux',
            'Дизайн-система, UI-киты, UX-регламенты',
            'palette', '#d97706');

        $this->createPage('kbs_5', 'Дизайн-система агентства', 'article', 'published', '
            <h2>Компоненты</h2>
            <p>Дизайн-система базируется на Figma UI Kit и включает:</p>
            <ul>
                <li>Typography: Inter для интерфейсов, PT Sans для текстов</li>
                <li>Цветовая палитра: 8 основных цветов с градациями от 50 до 900</li>
                <li>Spacing: 4-8-12-16-24-32-48-64</li>
                <li>Shadow: 3 уровня (низкий, средний, высокий)</li>
                <li>Border radius: 4px стандарт, 8px для карточек, 12px для модалок</li>
            </ul>
            <h2>Иконки</h2>
            <p>Используем Lucide Icons (opensource). Набор из 500+ иконок. При необходимости кастомные иконки в Figma.</p>
            <h2>Доступность (a11y)</h2>
            <ul>
                <li>Контрастность текста: минимум AA (4.5:1 для обычного, 3:1 для крупного)</li>
                <li>Минимальный размер кликабельных элементов: 44x44px</li>
                <li>Все иконки должны иметь aria-label</li>
            </ul>
        ');

        $this->createPage('kbs_5', 'UX-аудит перед сдачей проекта', 'checklist', 'published', '
            <h2>Функциональность</h2>
            <ul>
                <li>Все ссылки и кнопки работают</li>
                <li>Формы отправляются с валидацией</li>
                <li>Состояния загрузки и ошибок отображаются</li>
                <li>Нет битых изображений</li>
            </ul>
            <h2>Адаптивность</h2>
            <ul>
                <li>Проверка на 320px, 768px, 1024px, 1920px</li>
                <li>Меню корректно работает на мобильных</li>
                <li>Таблицы скроллятся горизонтально</li>
            </ul>
            <h2>Производительность</h2>
            <ul>
                <li>Lighthouse Performance > 85</li>
                <li>First Contentful Paint < 1.5s</li>
                <li>Total Blocking Time < 200ms</li>
                <li>Cumulative Layout Shift < 0.1</li>
            </ul>
        ');

        $this->createPage('kbs_5', 'Работа с Figma: гайд для разработчиков', 'instruction', 'published', '
            <h2>Структура Figma проекта</h2>
            <ul>
                <li><strong>🎨 UI Kit</strong> — компоненты, атомы, молекулы</li>
                <li><strong>🖥️ Screens</strong> — экраны продукта в разных разрешениях</li>
                <li><strong>🧩 Prototype</strong> — прототип с переходами</li>
                <li><strong>📝 Specs</strong> — спецификации и комментарии</li>
            </ul>
            <h2>Как перенести в код</h2>
            <ol>
                <li>Открыть Figma → Enable Dev Mode</li>
                <li>Кликнуть на элемент — справа CSS/Android/iOS код</li>
                <li>Скопировать стили и адаптировать под проект</li>
                <li>Использовать токены дизайн-системы, а не жёсткие значения</li>
            </ol>
            <h2>Правила</h2>
            <ul>
                <li>Не отклоняться от UI Kit без согласования с дизайнером</li>
                <li>Все отступы должны соответствовать сетке (4px шаг)</li>
                <li>Цвета брать из токенов, не хардкодить hex</li>
            </ul>
        ');

        // === Space 6: Онбординг сотрудников ===
        $this->createSpace('Онбординг сотрудников', 'onboarding-sotrudnikov',
            'Материалы для адаптации новых членов команды',
            'graduation-cap', '#059669');

        $this->createPage('kbs_6', 'План онбординга для разработчика', 'onboarding', 'published', '
            <h2>Неделя 1: Погружение</h2>
            <ul>
                <li>Знакомство с командой и ключевыми людьми</li>
                <li>Настройка рабочего окружения (IDE, Docker, доступы)</li>
                <li>Изучение документации проекта</li>
                <li>Просмотр код-базы через Code Reading Session</li>
            </ul>
            <h2>Неделя 2-3: Первые задачи</h2>
            <ul>
                <li>Получение первой задачи (баг или small feature)</li>
                <li>Работа с git flow агентства</li>
                <li>Участие в daily и планировании</li>
                <li>Code-review с наставником</li>
            </ul>
            <h2>Неделя 4-6: Самостоятельная работа</h2>
            <ul>
                <li>Полноценное выполнение задач</li>
                <li>Проведение code-review для коллег</li>
                <li>Участие в ретроспективе</li>
            </ul>
            <h2>Чек-лист успешного онбординга</h2>
            <ul>
                <li>Настроен доступ ко всем системам</li>
                <li>Получены права на репозитории</li>
                <li>Создан первый PR и принят</li>
                <li>Проведён 1:1 с менеджером</li>
            </ul>
        ');

        $this->createPage('kbs_6', 'Структура компании и команды', 'article', 'published', '
            <h2>Организационная структура</h2>
            <ul>
                <li><strong>CEO</strong>: Ольга Кузнецова — стратегия, развитие бизнеса</li>
                <li><strong>Аккаунт-менеджеры</strong>: Анна Смирнова, Павел Лебедев</li>
                <li><strong>Project Manager</strong>: Ирина Морозова, Дмитрий Белов</li>
                <li><strong>Team Lead</strong>: Никита Фролов</li>
                <li><strong>Разработчики</strong>: Алексей Попов, Мария Киселева, Роман Егоров</li>
                <li><strong>QA</strong>: Евгения Соколова</li>
                <li><strong>UI/UX</strong>: Илья Жданов</li>
                <li><strong>Поддержка</strong>: Ксения Волкова</li>
            </ul>
            <h2>Команды</h2>
            <ul>
                <li><strong>Frontend</strong>: React, Next.js, TypeScript</li>
                <li><strong>Backend</strong>: PHP, API Platform, MySQL/PostgreSQL</li>
                <li><strong>QA</strong>: Manual + Automation (Playwright)</li>
                <li><strong>Support</strong>: Ticketing, мониторинг, SLA</li>
            </ul>
            <h2>Коммуникация</h2>
            <ul>
                <li>Slack — оперативные вопросы</li>
                <li>Jira — задачи и спринты</li>
                <li>Confluence / База Знаний — документация</li>
                <li>Еженедельная all-hands в пятницу 16:00</li>
            </ul>
        ');

        $this->createPage('kbs_6', 'Первая неделя: чек-лист', 'checklist', 'published', '
            <h2>День 1</h2>
            <ul>
                <li>Получить ноутбук и настроить рабочее место</li>
                <li>Создать аккаунты: Slack, Jira, GitLab, Figma</li>
                <li>Познакомиться с командой</li>
                <li>Прочитать Welcome Guide</li>
            </ul>
            <h2>День 2-3</h2>
            <ul>
                <li>Настроить локальное окружение</li>
                <li>Склонировать репозиторий и запустить проект</li>
                <li>Изучить структуру проекта и архитектуру</li>
            </ul>
            <h2>День 4-5</h2>
            <ul>
                <li>Взять первую small задачу</li>
                <li>Создать первый PR</li>
                <li>Провести 1:1 с тимлидом</li>
            </ul>
        ');

        // === Space 7: Встречи и решения ===
        $this->createSpace('Встречи и решения', 'vstrechi-i-resheniya',
            'Протоколы встреч, архитектурные решения, ADR',
            'file-text', '#dc2626');

        $this->createPage('kbs_7', 'ADR: Микросервисы vs Монолит для Tehnograd', 'decision', 'published', '
            <h2>Контекст</h2>
            <p>При проектировании B2B-портала для Техноград Логистик встал вопрос выбора архитектуры: разбивать ли систему на микросервисы или оставаться на монолите.</p>
            <h2>Рассматриваемые варианты</h2>
            <ul>
                <li><strong>Микросервисы</strong>: модули каталога, заказов, отчётов как отдельные сервисы + API Gateway</li>
                <li><strong>Модульный монолит</strong>: единое приложение с чёткими модульными границами</li>
            </ul>
            <h2>Решение</h2>
            <p>Выбран <strong>модульный монолит</strong> с перспективой выделения микросервисов при росте нагрузки.</p>
            <h2>Обоснование</h2>
            <ul>
                <li>Команда 3 разработчика — микросервисы создадут overhead</li>
                <li>Сроки MVP жёсткие (3 месяца)</li>
                <li>Бизнес-логика тесно связана — разделение будет искусственным</li>
            </ul>
            <h2>Последствия</h2>
            <ul>
                <li>+ Быстрая разработка и деплой</li>
                <li>+ Проще тестирование (один скоуп)</li>
                <li>- При росте нагрузки потребуется рефакторинг</li>
            </ul>
            <h2>Статус</h2>
            <p>Принято</p>
        ');

        $this->createPage('kbs_7', 'Еженедельная планерка 21 апреля', 'meeting_note', 'published', '
            <h2>Дата и участники</h2>
            <p>Дата: 21 апреля 2026</p>
            <p>Участники: вся команда</p>
            <h2>Статус проектов</h2>
            <ul>
                <li><strong>Verona Travel</strong>: редизайн на этапе вёрстки, SEO-аудит завершён. Риски: нужно больше контента для SEO.</li>
                <li><strong>Alpina Home</strong>: корзина на мобильных починена, импорт ERP в работе. Рисков нет.</li>
                <li><strong>Tehnograd Logistic</strong>: SSO авторизация готова, API заказов в разработке. Риски: сроки по дашбордам.</li>
                <li><strong>Support Automation</strong>: SLA-дашборд собран, настройка маршрутизации в процессе.</li>
            </ul>
            <h2>Блокеры</h2>
            <ul>
                <li>Дизайн Verona: загрузка иллюстраций</li>
                <li>Tehnograd: API-документация от ERP вендора</li>
            </ul>
            <h2>План на неделю</h2>
            <ul>
                <li>Ирина: согласование макетов Verona, контроль Tehnograd</li>
                <li>Никита: ревью архитектуры авторизации Tehnograd</li>
                <li>Алексей: импорт ERP Альпины, Schema.org Verona</li>
                <li>Мария: UI дашборда Tehnograd</li>
                <li>Роман: фикс корзины Альпины, лид-форма внутреннего маркетинга</li>
                <li>Евгения: тестирование SSO и импорта ERP</li>
                <li>Илья: UI Kit макеты для Tehnograd</li>
            </ul>
        ');

        // === Space 8: База знаний клиентов ===
        $this->createSpace('База знаний клиентов', 'baza-znanii-klientov',
            'Информация о клиентах, FAQ, особенности проектов',
            'users', '#0891b2');

        $this->createPage('kbs_8', 'Verona Travel — FAQ', 'faq', 'published', '
            <h2>Какой у Verona Travel стек технологий?</h2>
            <p>Next.js + Tailwind CSS на фронте, Laravel на бэке, PostgreSQL, Meilisearch для поиска туров.</p>
            <h2>Кто отвечает за контент?</h2>
            <p>Клиент предоставляет контент (тексты и фото), мы верстаем и оптимизируем под SEO. Если нужен копирайтинг — подключаем подрядчика.</p>
            <h2>Как часто проходят встречи?</h2>
            <p>Еженедельный статус-call по вторникам в 11:00, демо в конце каждого спринта (раз в 2 недели).</p>
            <h2>Ключевые контакты</h2>
            <ul>
                <li>Екатерина Романова — маркетинг, контент</li>
                <li>Максим Виноградов — технические вопросы</li>
            </ul>
        ');

        $this->createPage('kbs_8', 'Alpina Home — особенности поддержки', 'client_note', 'published', '
            <h2>Тикеты</h2>
            <p>Приоритеты: критичные (падение магазина) — реакция 1 час, высокие (ошибка в корзине) — 4 часа, обычные — 24 часа.</p>
            <h2>Релизное окно</h2>
            <p>Каждую среду с 10:00 до 14:00. Hotfix — в любое время через отдельный процесс.</p>
            <h2>Контакты</h2>
            <ul>
                <li>Максим Виноградов — тех. директор (Telegram предпочтителен)</li>
                <li>Сергей Филиппов — операционные вопросы</li>
            </ul>
            <h2>Важные ссылки</h2>
            <ul>
                <li>Админка: https://admin.alpina-home.ru</li>
                <li>Мониторинг: https://grafana.alpina-home.ru</li>
                <li>Репозиторий: github.com/aurora/alpina-home</li>
            </ul>
        ');

        $this->createPage('kbs_8', 'Tehnograd Logistic — особенности работ', 'client_note', 'published', '
            <h2>Специфика</h2>
            <p>B2B-портал для дилеров. Важно: система должна работать 24/7, downtime недопустим. Все изменения через blue-green деплой.</p>
            <h2>Интеграции</h2>
            <ul>
                <li>ERP: 1С через XML-коннектор + RabbitMQ</li>
                <li>SSO: через Keycloak</li>
                <li>Платёжный шлюз: Сбербанк E-Commerce</li>
            </ul>
            <h2>Команда клиента</h2>
            <ul>
                <li>Сергей Филиппов — руководитель проекта</li>
                <li>Анна Козлова — тех. поддержка</li>
                <li>Дмитрий Соколов — ERP-администратор</li>
            </ul>
        ');

        $this->createPage('kbs_8', 'История проектов и ключевые решения', 'article', 'published', '
            <h2>Аurora Digital — история</h2>
            <p>Агентство основано в 2023 году. Специализация: веб-разработка, SEO, UI/UX дизайн. За 3 года реализовано более 20 проектов.</p>
            <h2>Ключевые решения</h2>
            <ul>
                <li><strong>2024</strong>: Переход на гибридную методологию (Scrum + Kanban)</li>
                <li><strong>2025</strong>: Внедрение Базы Знаний как единого источника правды</li>
                <li><strong>2026</strong>: Запуск AI-функций для анализа документации</li>
            </ul>
            <h2>Используемые технологии</h2>
            <ul>
                <li>PHP 8.2+, Laravel, API Platform</li>
                <li>React, Next.js 14+, TypeScript</li>
                <li>MySQL 8, PostgreSQL 16, Meilisearch</li>
                <li>Docker, Kubernetes, GitHub Actions</li>
                <li>Sentry, Grafana, Prometheus</li>
            </ul>
        ');

        echo "  Создано пространств: " . count($this->spaces) . "\n";
        echo "  Создано страниц: " . count($this->pages) . "\n";
    }

    // --- Вспомогательные методы ---

    private function createSpace(string $title, string $slug, string $description, string $icon, string $color): void
    {
        try {
            $res = $this->request('POST', 'api/v1/knowledge/spaces', [
                'title' => $title,
                'slug' => $slug,
                'description' => $description,
                'icon' => $icon,
                'color' => $color,
                'visibility' => 'public',
                'default_access_level' => 'view',
            ]);
            $space = $res['data']['space'] ?? [];
            if (!empty($space['public_id'])) {
                $this->spaces[$slug] = $space;
                echo "  ✓ Пространство: {$title}\n";
            }
        } catch (RuntimeException $e) {
            echo "  ✗ Пространство {$title}: {$e->getMessage()}\n";
        }
    }

    private function createPage(string $spaceKey, string $title, string $pageType, string $status, string $contentHtml): void
    {
        $space = $this->spaces[$spaceKey] ?? null;
        if (!$space) {
            echo "  ✗ Страница {$title}: пространство не найдено\n";
            return;
        }
        try {
            $res = $this->request('POST', 'api/v1/knowledge/pages', [
                'title' => $title,
                'space_public_id' => $space['public_id'],
                'page_type' => $pageType,
                'status' => $status,
                'content_html' => $contentHtml,
            ]);
            $page = $res['data']['page'] ?? [];
            if (!empty($page['public_id'])) {
                $this->pages[$title] = $page;
            }
        } catch (RuntimeException $e) {
            // Может уже существует — игнорируем
        }
    }

    private function addLinksAndComments(): void
    {
        echo "\nДобавление связей и комментариев...\n";

        $projectKeys = array_keys($this->projects);
        $taskKeys = array_keys($this->tasks);
        $clientKeys = array_keys($this->clients);

        // Связь страниц Verona с проектом и задачами
        $veronaProj = $this->findProject('Verona Travel');
        if ($veronaProj && isset($this->pages['Verona Travel — Архитектура решения'])) {
            $this->linkPage($this->pages['Verona Travel — Архитектура решения']['public_id'], 'project', $veronaProj['public_id'], 'describes');
        }
        if ($veronaProj && isset($this->pages['Verona Travel — План SEO-оптимизации'])) {
            $this->linkPage($this->pages['Verona Travel — План SEO-оптимизации']['public_id'], 'project', $veronaProj['public_id'], 'describes');
        }
        if ($veronaProj && isset($this->pages['Verona Travel — FAQ'])) {
            $this->linkPage($this->pages['Verona Travel — FAQ']['public_id'], 'project', $veronaProj['public_id'], 'related');
        }

        // Связь с задачами Verona
        $veronaTasks = $this->findTasks('Verona');
        foreach ($veronaTasks as $tk => $tv) {
            $pageKey = 'Verona Travel — Архитектура решения';
            if (isset($this->pages[$pageKey])) {
                $this->linkPage($this->pages[$pageKey]['public_id'], 'task', $tv['public_id'], 'related');
                break;
            }
        }

        // Alpina
        $alpinaProj = $this->findProject('Alpina Home');
        if ($alpinaProj && isset($this->pages['Alpina Home — Выбор платформы каталога'])) {
            $this->linkPage($this->pages['Alpina Home — Выбор платформы каталога']['public_id'], 'project', $alpinaProj['public_id'], 'decision');
        }
        if ($alpinaProj && isset($this->pages['Alpina Home — особенности поддержки'])) {
            $this->linkPage($this->pages['Alpina Home — особенности поддержки']['public_id'], 'project', $alpinaProj['public_id'], 'support');
        }
        if ($alpinaProj && isset($this->pages['Интеграция с ERP Альпины'])) {
            $this->linkPage($this->pages['Интеграция с ERP Альпины']['public_id'], 'project', $alpinaProj['public_id'], 'describes');
        }

        // Tehnograd
        $tehnogradProj = $this->findProject('Техноград');
        if ($tehnogradProj && isset($this->pages['Tehnograd Logistic — B2B портал заказов'])) {
            $this->linkPage($this->pages['Tehnograd Logistic — B2B портал заказов']['public_id'], 'project', $tehnogradProj['public_id'], 'describes');
        }
        if ($tehnogradProj && isset($this->pages['Tehnograd Logistic — особенности работ'])) {
            $this->linkPage($this->pages['Tehnograd Logistic — особенности работ']['public_id'], 'project', $tehnogradProj['public_id'], 'related');
        }
        if ($tehnogradProj && isset($this->pages['ADR: Микросервисы vs Монолит для Tehnograd'])) {
            $this->linkPage($this->pages['ADR: Микросервисы vs Монолит для Tehnograd']['public_id'], 'project', $tehnogradProj['public_id'], 'decision');
        }

        // Связь с клиентами
        foreach ($this->clients as $c) {
            $title = (string)($c['title'] ?? '');
            if (str_contains($title, 'Верона') && isset($this->pages['Verona Travel — FAQ'])) {
                $this->linkPage($this->pages['Verona Travel — FAQ']['public_id'], 'client', $c['public_id'], 'related');
            }
            if (str_contains($title, 'Альпина') && isset($this->pages['Alpina Home — особенности поддержки'])) {
                $this->linkPage($this->pages['Alpina Home — особенности поддержки']['public_id'], 'client', $c['public_id'], 'support');
            }
            if (str_contains($title, 'Техноград') && isset($this->pages['Tehnograd Logistic — особенности работ'])) {
                $this->linkPage($this->pages['Tehnograd Logistic — особенности работ']['public_id'], 'client', $c['public_id'], 'related');
            }
        }

        // Комментарии к ключевым страницам
        $this->addComment('Verona Travel — Архитектура решения', 'Отличная архитектура, особенно понравилось решение с Meilisearch. Нужно добавить схему БД для полноты.');
        $this->addComment('Verona Travel — Архитектура решения', '@Никита Фролов, добавил ER-диаграмму в раздел "Структура данных". Посмотри, пожалуйста.');
        $this->addComment('Чек-лист перед релизом', 'Предлагаю добавить пункт про проверку логов после деплоя. Часто забываем.');
        $this->addComment('Чек-лист перед релизом', 'Согласен, добавил. Ещё стоит проверить Sentry на наличие новых ошибок.');
        $this->addComment('ADR: Микросервисы vs Монолит для Tehnograd', 'Отличное решение. У нас маленькая команда, микросервисы только затормозят разработку.');
        $this->addComment('Политика безопасности данных', 'Коллеги, напоминаю: всем нужно подписать обновлённую политику до конца месяца.');
    }

    private function addFavoritesAndSubscriptions(): void
    {
        echo "Добавление избранного и подписок...\n";

        // Добавляем избранное для ключевых пользователей
        $users = array_values($this->users);
        if (count($users) >= 2) {
            $pageKeys = ['Verona Travel — Архитектура решения', 'Чек-лист перед релизом', 'Политика безопасности данных',
                         'Дизайн-система агентства', 'Стандарты кода PHP', 'План онбординга для разработчика'];

            $idx = 0;
            foreach ($pageKeys as $key) {
                if (isset($this->pages[$key])) {
                    $uid = (int)($users[$idx % count($users)]['id'] ?? 0);
                    if ($uid > 0) {
                        try {
                            $this->request('POST', 'api/v1/knowledge/pages/' . $this->pages[$key]['public_id'] . '/favorite');
                        } catch (RuntimeException) {}
                    }
                    $idx++;
                }
            }
        }
    }

    private function addTagsToPages(): void
    {
        echo "Добавление тегов к страницам...\n";

        $tagMap = [
            'Verona Travel — Архитектура решения' => ['backend', 'frontend'],
            'Verona Travel — План SEO-оптимизации' => ['seo', 'analytics'],
            'Alpina Home — Выбор платформы каталога' => ['backend', 'integration'],
            'Tehnograd Logistic — B2B портал заказов' => ['backend', 'frontend', 'integration'],
            'Стандарты кода PHP' => ['backend'],
            'Интеграция с ERP Альпины' => ['integration', 'backend'],
            'Дизайн-система агентства' => ['design'],
            'UX-аудит перед сдачей проекта' => ['design', 'frontend'],
            'Чек-лист перед релизом' => ['support', 'frontend', 'backend'],
            'Настройка CI/CD для проектов' => ['backend', 'support'],
            'Политика безопасности данных' => ['support'],
            'Частые ошибки при сборке проектов' => ['support', 'backend', 'frontend'],
            'Развёртывание проекта на staging' => ['support', 'backend'],
            'Работа с Git: правила и соглашения' => ['backend', 'frontend'],
        ];

        foreach ($tagMap as $pageTitle => $tagCodes) {
            if (!isset($this->pages[$pageTitle])) {
                continue;
            }
            foreach ($tagCodes as $code) {
                if (isset($this->tags[$code])) {
                    try {
                        $this->request('POST', 'api/v1/knowledge/pages/' . $this->pages[$pageTitle]['public_id'] . '/tags/' . $this->tags[$code]['public_id']);
                    } catch (RuntimeException) {}
                }
            }
        }
    }

    private function linkPage(string $pagePublicId, string $entityType, string $entityPublicId, string $relationType): void
    {
        try {
            $this->request('POST', 'api/v1/knowledge/pages/' . $pagePublicId . '/links', [
                'entity_type' => $entityType,
                'entity_public_id' => $entityPublicId,
                'relation_type' => $relationType,
            ]);
        } catch (RuntimeException) {}
    }

    private function addComment(string $pageTitle, string $body): void
    {
        if (!isset($this->pages[$pageTitle])) {
            return;
        }
        try {
            $this->request('POST', 'api/v1/knowledge/pages/' . $this->pages[$pageTitle]['public_id'] . '/comments', [
                'body' => $body,
            ]);
        } catch (RuntimeException) {}
    }

    private function findProject(string $search): ?array
    {
        foreach ($this->projects as $p) {
            if (str_contains((string)($p['title'] ?? ''), $search)) {
                return $p;
            }
        }
        return null;
    }

    private function findTasks(string $projectSearch): array
    {
        $result = [];
        foreach ($this->tasks as $pk => $pt) {
            $title = (string)($pt['title'] ?? '');
            if (str_contains($title, $projectSearch)) {
                $result[$pk] = $pt;
            }
        }
        return $result;
    }

    private function userKey(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/[^a-zа-яё]/u', '_', $name) ?? $name;
        return trim($name, '_');
    }

    private function printSummary(): void
    {
        echo "\n=== Сводка ===\n";

        try {
            $res = $this->request('GET', 'api/v1/knowledge/analytics');
            $stats = $res['data']['stats'] ?? [];
            printf("  Пространств: %d\n", (int)($stats['active_spaces'] ?? 0));
            printf("  Всего страниц: %d\n", (int)($stats['total_pages'] ?? 0));
            printf("  Опубликовано: %d\n", (int)($stats['published'] ?? 0));
            printf("  Черновиков: %d\n", (int)($stats['drafts'] ?? 0));
            printf("  Комментариев: %d\n", (int)($stats['total_comments'] ?? 0));
            printf("  Версий: %d\n", (int)($stats['total_versions'] ?? 0));
            printf("  Связей: %d\n", (int)($stats['total_links'] ?? 0));
        } catch (RuntimeException $e) {
            echo "  Ошибка получения статистики: {$e->getMessage()}\n";
        }

        echo "\n✓ База Знаний успешно наполнена!\n";
    }

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
            // Не фатально для связей и комментариев
            throw new RuntimeException("API error {$http} {$code}: {$message}");
        }

        return $decoded;
    }
}

$seeder = new KnowledgeBaseSeeder();
$seeder->run();
