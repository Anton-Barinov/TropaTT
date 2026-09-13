# Руководство разработчика модулей TropaTT CRM

> Исчерпывающее техническое руководство по проектированию, разработке, интеграции, упаковке и распространению модулей расширения для TropaTT CRM.

---

## Оглавление

1. [Архитектура модульной подсистемы](#1-архитектура-модульной-подсистемы)
2. [Анатомия модуля и файловая структура](#2-анатомия-модуля-и-файловая-структура)
3. [Спецификация манифеста manifest.json](#3-спецификация-манифеста-manifestjson)
4. [Провайдер сервисов (AbstractModuleServiceProvider) и внедрение зависимостей](#4-провайдер-сервисов-abstractmoduleserviceprovider-и-внедрение-зависимостей)
5. [Шина событий и полный каталог ModuleEvents](#5-шина-событий-и-полный-каталог-moduleevents)
6. [Web UI интеграция: слоты позиций (PositionRegistry) и шаблоны](#6-web-ui-интеграция-слоты-позиций-positionregistry-и-шаблоны)
7. [Маршрутизация Web и REST API](#7-маршрутизация-web-и-rest-api)
8. [Управление ресурсами (Assets: CSS, JS)](#8-управление-ресурсами-assets-css-js)
9. [Миграции базы данных и жизненный цикл схемы](#9-миграции-базы-данных-и-жизненный-цикл-схемы)
10. [Фоновые задачи: Cron-планировщик и транзакционная очередь задач](#10-фоновые-задачи-cron-планировщик-и-транзакционная-очередь-задач)
11. [Безопасность, статическая валидация кода и изоляция ошибок (Circuit Breaker)](#11-безопасность-статическая-валидация-кода-и-изоляция-ошибок-circuit-breaker)
12. [Упаковка, версионирование и удаленная установка](#12-упаковка-версионирование-и-удаленная-установка)
13. [Полный эталонный пример модуля: Acme Telegram Notifier](#13-полный-эталонный-пример-модуля-acme-telegram-notifier)

---

## 1. Архитектура модульной подсистемы

Модульная подсистема TropaTT CRM спроектирована по принципам **Zero-Daemon**, максимальной производительности при ограничениях shared-хостинга (< 32 MB RAM, таймауты до 25 секунд) и строгой изоляции расширений от ядра системы.

### Ключевые архитектурные принципы

- **Неинвазивность (Zero-Core-Edits):** Модули расширяют функционал CRM исключительно через объявленные декларативные контракты (`manifest.json`), точки внедрения интерфейса (`PositionRegistry`), шину хуков (`HookManager`) и маршрутизацию (`api_routes`, `web_routes`). Правка исходного кода ядра категорически запрещена.
- **Двухуровневая изоляция ошибок (Fault Tolerance):** Сбой или исключение в коде модуля не должны приводить к падению ядра или отказу пользовательских запросов. Для защиты ядра применяются `try-catch` изоляция каждого хука и автоматический предохранитель **Circuit Breaker**.
- **Статический аудит безопасности:** Перед установкой и активацией каждый PHP-файл модуля валидируется через `ModuleCodeValidator` методом лексического анализа токенов (`token_get_all`). Использование опасных функций и системных вызовов (`eval`, `exec`, `shell_exec`, `passthru`, `file_put_contents` и др.) приводит к немедленной блокировке модуля.
- **Транзакционная целостность миграций:** Модуль может иметь собственные таблицы и поля. Все изменения схемы БД регистрируются в системной таблице `module_migrations` с поддержкой автоматического отката при установке/удалении.
- **Стандарт PSR-4 для модулей:** Все классы модуля регистрируются под унифицированным пространством имен `Module\<VendorName>\<ModuleName>\` через оптимизированный класс-маппер ядра.

### Архитектурная схема взаимодействия подсистем

```
+-----------------------------------------------------------------------------------+
|                                 TropaTT Core                                      |
|                                                                                   |
|   +-------------------+       +--------------------+       +------------------+   |
|   |   FrontController |       |    PositionRegistry|       |   HookManager    |   |
|   |   (Web & REST)    |       |  (UI Slots Engine) |       |  (Event Bus)     |   |
|   +---------+---------+       +---------+----------+       +--------+---------+   |
|             |                           |                           |             |
+-------------|---------------------------|---------------------------|-------------+
              |                           |                           |
              v                           v                           v
+-----------------------------------------------------------------------------------+
|                             PluginManager Runtime                                 |
|                                                                                   |
|  +--------------------+   +-----------------------+   +------------------------+  |
|  | ModuleCodeValidator|   | ModuleCircuitBreaker  |   | ModuleMigrationRunner  |  |
|  +--------------------+   +-----------------------+   +------------------------+  |
|                                                                                   |
|  +--------------------+   +-----------------------+   +------------------------+  |
|  | ModuleAssetManager |   |  ModuleCronScheduler  |   |  ModuleJobDispatcher   |  |
|  +--------------------+   +-----------------------+   +------------------------+  |
+-------------------------------------+---------------------------------------------+
                                      |
                                      v
+-----------------------------------------------------------------------------------+
|                        Сторонний Модуль (Vendor / Extension)                      |
|                                                                                   |
|  +---------------+  +--------------------------+  +----------------------------+  |
|  | manifest.json |  | ServiceProvider.php      |  | Controllers & Models       |  |
|  +---------------+  +--------------------------+  +----------------------------+  |
|  +---------------+  +--------------------------+  +----------------------------+  |
|  | migrations/*.sql | assets/ (css, js)       |  | templates/ (*.twig / *.php)|  |
|  +---------------+  +--------------------------+  +----------------------------+  |
+-----------------------------------------------------------------------------------+
```

---

## 2. Анатомия модуля и файловая структура

Каждый модуль TropaTT CRM располагается в отдельной директории внутри корневого каталога модулей:
`modules/{vendor}.{name}/` (или `upload/modules/{vendor}.{name}/`).

Рекомендуемое соглашение по именованию директории модуля: `{vendor}.{name}`, где все символы в нижнем регистре с разделением точкой (например, `acme.telegram_notifier`, `retail.cdek_shipping`).

### Эталонная иерархия директорий

```
modules/acme.telegram_notifier/
├── manifest.json                  # Декларативный манифест модуля (обязателен)
├── ServiceProvider.php            # Провайдер сервисов, жизненный цикл и регистрация
├── api_routes.php                 # Регистрация REST API маршрутов
├── web_routes.php                 # Регистрация маршрутов веб-интерфейса
├── controllers/                   # Контроллеры модуля
│   ├── ApiSettingsController.php
│   └── WebSettingsController.php
├── models/                        # Модели предметной области и доступ к данным
│   └── TelegramLogModel.php
├── templates/                     # Шаблоны представлений (HTML/Twig/PHP)
│   ├── settings.php
│   └── sidebar_widget.php
├── assets/                        # Статические публичные ресурсы
│   ├── css/
│   │   └── widget.css
│   └── js/
│       └── notifier.js
├── migrations/                    # SQL-файлы миграций базы данных
│   ├── 001_create_telegram_logs.up.sql
│   └── 001_create_telegram_logs.down.sql
└── cron/                          # Обработчики запланированных задач
    └── NotificationQueueHandler.php
```

---

## 3. Спецификация манифеста manifest.json

Файл `manifest.json` является входной точкой модуля. Он парсится ядром с помощью класса `Api\System\Library\Module\Manifest` при обнаружении модуля в файловой системе.

### Полная схема полей manifest.json

| Поле | Тип | Обязательное | Описание | Пример |
|---|---|:---:|---|---|
| `name` | `string` | **Да** | Уникальный системный идентификатор модуля в формате `{vendor}.{name}` | `"acme.telegram_notifier"` |
| `version` | `string` | **Да** | Семантическая версия модуля (`SemVer`) | `"1.2.0"` |
| `vendor` | `string` | **Да** | Имя разработчика, организации или бренда | `"Acme Software Ltd"` |
| `author` | `string` | **Да** | Имя или псевдоним автора | `"Anton Barinov"` |
| `author_url` | `string` | Нет | Ссылка на сайт или профиль автора | `"https://github.com/Anton-Barinov"` |
| `title` | `string` | **Да** | Человекочитаемое название модуля для интерфейса CRM | `"Telegram Уведомления"` |
| `description` | `string` | **Да** | Краткое описание назначения и функций модуля | `"Отправка оперативных уведомлений по задачам в Telegram чаты."` |
| `category` | `string` | Нет | Категория модуля в каталоге (`integrations`, `crm`, `finance`, `tools`, `reports`) | `"integrations"` |
| `core_version` | `string` | Нет | Требуемая версия ядра CRM (по умолчанию `>=1.0.0`) | `">=1.0.0"` |
| `dependencies` | `array` | Нет | Список других модулей, требуемых для работы | `["acme.core_tools"]` |
| `require_permissions` | `array` | Нет | Список системных RBAC-прав, необходимых модулю | `["tasks.read", "settings.manage"]` |
| `service_provider` | `string` | Нет | FQCN или имя файла сервис-провайдера | `"Module\\Acme\\TelegramNotifier\\ServiceProvider"` |
| `api_routes` | `string` | Нет | Относительный путь к файлу REST API роутов | `"api_routes.php"` |
| `web_routes` | `string` | Нет | Относительный путь к файлу Web роутов | `"web_routes.php"` |
| `migrations` | `string` | Нет | Относительный путь к каталогу SQL-миграций | `"migrations"` |
| `hooks` | `object` | Нет | Декларативная регистрация слушателей событий ядра | `{"task.created": [{"handler": "...", "priority": 10}]}` |
| `positions` | `object` | Нет | Декларативная регистрация рендереров в слоты UI | `{"task.detail.sidebar": [{"renderer": "...", "priority": 10}]}` |
| `menu_items` | `array` | Нет | Элементы бокового меню CRM | См. структуру ниже |
| `config_defaults` | `object` | Нет | Значения настроек по умолчанию (ключ-значение) | `{"bot_token": "", "chat_id": ""}` |
| `assets` | `object` | Нет | Регистрация CSS и JS стилей/скриптов | См. раздел ресурсов |
| `web_hooks` | `array` | Нет | Декларация внешних вебхуков для приема событий | `[{"route": "telegram/webhook", "action": "handle"}]` |

### Пример полного манифеста:

```json
{
  "name": "acme.telegram_notifier",
  "version": "1.0.0",
  "vendor": "Acme Software Ltd",
  "author": "Anton Barinov",
  "author_url": "https://github.com/Anton-Barinov",
  "title": "Telegram Уведомления",
  "description": "Мгновенная отправка уведомлений о смене статусов и комментариях в Telegram",
  "category": "integrations",
  "core_version": ">=1.0.0",
  "dependencies": [],
  "require_permissions": [
    "tasks.read",
    "tasks.edit"
  ],
  "service_provider": "Module\\Acme\\TelegramNotifier\\ServiceProvider",
  "api_routes": "api_routes.php",
  "web_routes": "web_routes.php",
  "migrations": "migrations",
  "config_defaults": {
    "telegram_bot_token": "",
    "telegram_default_chat_id": "",
    "notify_on_status_change": 1,
    "notify_on_comment": 1
  },
  "menu_items": [
    {
      "route": "module/acme_telegram/settings",
      "label": "Настройки Telegram",
      "icon": "bi bi-telegram",
      "permission": "settings.manage",
      "parent": "settings"
    }
  ],
  "assets": {
    "css": [
      "assets/css/widget.css"
    ],
    "js": [
      "assets/js/notifier.js"
    ],
    "css_routes": {
      "task-detail": "assets/css/widget.css"
    }
  },
  "positions": {
    "task.detail.sidebar": [
      {
        "renderer": "Module\\Acme\\TelegramNotifier\\Controllers\\WebSettingsController::renderSidebarWidget",
        "priority": 15,
        "key": "acme_telegram_sidebar"
      }
    ]
  },
  "hooks": {
    "task.status_changed": [
      {
        "handler": "Module\\Acme\\TelegramNotifier\\Handlers\\StatusChangeHandler::handle",
        "priority": 10
      }
    ],
    "comment.added": [
      {
        "handler": "Module\\Acme\\TelegramNotifier\\Handlers\\CommentHandler::handle",
        "priority": 5
      }
    ]
  }
}
```

---

## 4. Провайдер сервисов (AbstractModuleServiceProvider) и внедрение зависимостей

Класс `ServiceProvider` связывает модуль с ядром CRM. Рекомендуется наследовать класс от `Api\System\Library\Module\AbstractModuleServiceProvider`, который реализует интерфейс `ModuleServiceProviderInterface`.

### Жизненный цикл провайдера

1. **`register(Container $container): void`**
   - Вызывается на этапе сборки зависимостей контейнера до загрузки маршрутов и обработки запроса.
   - Здесь регистрируются собственные сервисы модуля, синглтоны, клиенты API и модели в DI-контейнер ядра.
   - **Важно:** В методе `register()` нельзя обращаться к другим модулям или выполнять побочные действия, так как остальные сервисы еще могут быть не инициализированы.

2. **`boot(Container $container): void`**
   - Вызывается после того, как все модули и сервисы ядра зарегистрированы.
   - Здесь подписываются динамические слушатели событий, настраиваются обработчики хуков, проверяются динамические условия окружения.

### Методы декларативного контракта ServiceProvider

| Метод | Возвращаемый тип | Описание |
|---|---|---|
| `getHooks()` | `array` | Массив слушателей событий вида `[event_name => [['handler' => ..., 'priority' => 10]]]` |
| `getPermissions()` | `array<int, string>` | Список уникальных ключей прав доступа, добавляемых модулем в систему RBAC |
| `getMenuItems()` | `array<int, array>` | Элементы навигационного меню CRM, публикуемые модулем |
| `getConfig()` | `array<string, mixed>` | Динамические или дефолтные конфигурации модуля |
| `getAssets()` | `array<string, mixed>` | Регистрация публичных скриптов и стилей |
| `getScheduledTasks()` | `array<int, ScheduledTask>` | Экземпляры объектов `ScheduledTask` для планировщика Cron |

### Эталонный пример ServiceProvider.php

```php
<?php
declare(strict_types=1);

namespace Module\Acme\TelegramNotifier;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;
use Api\System\Library\Module\ModuleEvents;
use Api\System\Library\Module\ScheduledTask;
use Module\Acme\TelegramNotifier\Services\TelegramService;
use Module\Acme\TelegramNotifier\Cron\NotificationQueueHandler;

class ServiceProvider extends AbstractModuleServiceProvider
{
    public function register(Container $container): void
    {
        // Регистрация сервиса в контейнере зависимостей ядра
        $container->singleton(TelegramService::class, static function (Container $c): TelegramService {
            $config = $c->get('config');
            $pdo = $c->get(\PDO::class);
            return new TelegramService(
                botToken: (string)($config['telegram_bot_token'] ?? ''),
                pdo: $pdo
            );
        });
    }

    public function boot(Container $container): void
    {
        // Динамическая логика при старте модуля
    }

    public function getHooks(): array
    {
        return [
            ModuleEvents::TASK_STATUS_CHANGED => [
                [
                    'handler' => '\\Module\\Acme\\TelegramNotifier\\Handlers\\StatusChangeHandler::handle',
                    'priority' => 20,
                ],
            ],
            ModuleEvents::COMMENT_ADDED => [
                [
                    'handler' => '\\Module\\Acme\\TelegramNotifier\\Handlers\\CommentHandler::handle',
                    'priority' => 10,
                ],
            ],
        ];
    }

    public function getPermissions(): array
    {
        return [
            'acme.telegram.manage',
            'acme.telegram.view_logs',
        ];
    }

    public function getMenuItems(): array
    {
        return [
            [
                'route' => 'module/acme_telegram/settings',
                'label' => 'Telegram Бот',
                'icon' => 'bi bi-telegram',
                'permission' => 'acme.telegram.manage',
                'parent' => 'settings',
            ],
        ];
    }

    public function getScheduledTasks(): array
    {
        return [
            new ScheduledTask(
                moduleName: 'acme.telegram_notifier',
                taskName: 'flush_telegram_queue',
                schedule: '*/2 * * * *', // Каждые 2 минуты
                handlerClass: NotificationQueueHandler::class,
                handlerMethod: 'run'
            ),
        ];
    }
}
```

---

## 5. Шина событий и полный каталог ModuleEvents

TropaTT CRM включает событийную шину, построенную вокруг класса `ModuleEvents` (`Api\System\Library\Module\ModuleEvents`). При наступлении ключевых бизнес-событий ядро диспетчеризирует их через `HookManager`.

### Полный каталог констант ModuleEvents

```php
namespace Api\System\Library\Module;

final class ModuleEvents
{
    // Жизненный цикл задач (TaskController)
    public const TASK_CREATED          = 'task.created';
    public const TASK_UPDATED          = 'task.updated';
    public const TASK_STATUS_CHANGED   = 'task.status_changed';
    public const TASK_ASSIGNEE_CHANGED = 'task.assignee_changed';
    public const TASK_DELETED          = 'task.deleted';

    // Жизненный цикл проектов (ProjectController)
    public const PROJECT_CREATED       = 'project.created';
    public const PROJECT_UPDATED       = 'project.updated';
    public const PROJECT_DELETED       = 'project.deleted';

    // Жизненный цикл пользователей (UserController)
    public const USER_CREATED          = 'user.created';
    public const USER_UPDATED          = 'user.updated';
    public const USER_DELETED          = 'user.deleted';

    // Жизненный цикл спринтов / рабочих циклов (WorkCycleController)
    public const CYCLE_CREATED         = 'cycle.created';
    public const CYCLE_STARTED         = 'cycle.started';
    public const CYCLE_COMPLETED       = 'cycle.completed';
    public const CYCLE_REOPENED        = 'cycle.reopened';
    public const CYCLE_ARCHIVED        = 'cycle.archived';
    public const CYCLE_DELETED         = 'cycle.deleted';

    // CRM сущности (клиенты, контрагенты, контакты, компании, организации)
    public const CLIENT_CREATED        = 'client.created';
    public const CLIENT_UPDATED        = 'client.updated';
    public const CLIENT_DELETED        = 'client.deleted';
    public const COUNTERPARTY_CREATED  = 'counterparty.created';
    public const COUNTERPARTY_UPDATED  = 'counterparty.updated';
    public const COUNTERPARTY_DELETED  = 'counterparty.deleted';
    public const CONTACT_CREATED       = 'contact.created';
    public const CONTACT_UPDATED       = 'contact.updated';
    public const CONTACT_DELETED       = 'contact.deleted';
    public const COMPANY_CREATED       = 'company.created';
    public const COMPANY_UPDATED       = 'company.updated';
    public const COMPANY_DELETED       = 'company.deleted';
    public const ORGANIZATION_CREATED  = 'organization.created';
    public const ORGANIZATION_UPDATED  = 'organization.updated';
    public const ORGANIZATION_DELETED  = 'organization.deleted';

    // Таксономия и классификаторы
    public const TAG_CREATED           = 'tag.created';
    public const TAG_UPDATED           = 'tag.updated';
    public const TAG_DELETED           = 'tag.deleted';
    public const STATUS_CREATED        = 'status.created';
    public const STATUS_UPDATED        = 'status.updated';
    public const STATUS_DELETED        = 'status.deleted';
    public const PRIORITY_CREATED      = 'priority.created';
    public const PRIORITY_UPDATED      = 'priority.updated';
    public const PRIORITY_DELETED      = 'priority.deleted';
    public const CUSTOM_FIELD_CREATED  = 'custom_field.created';
    public const CUSTOM_FIELD_UPDATED  = 'custom_field.updated';
    public const CUSTOM_FIELD_DELETED  = 'custom_field.deleted';

    // Совместная работа, чат и файлы
    public const COMMENT_ADDED         = 'comment.added';
    public const FILE_UPLOADED         = 'file.uploaded';
    public const CHAT_MESSAGE_CREATED  = 'chat.message_created';
    public const CHAT_MESSAGE_UPDATED  = 'chat.message_updated';
    public const CHAT_MESSAGE_DELETED  = 'chat.message_deleted';

    // Рендеринг интерфейса ядра (Web\Controller)
    public const RENDER_BEFORE         = 'render.before';
    public const RENDER_AFTER          = 'render.after';
}
```

### Формат обработчика событий

Обработчик хука может быть представлен статическим методом или замыканием. В качестве первого аргумента передается контекстный ассоциативный массив данных события:

```php
<?php
declare(strict_types=1);

namespace Module\Acme\TelegramNotifier\Handlers;

final class StatusChangeHandler
{
    /**
     * @param array{
     *     task_id: int,
     *     task_public_id: string,
     *     old_status: string,
     *     new_status: string,
     *     user_id: int,
     *     title: string
     * } $payload
     */
    public static function handle(array $payload): void
    {
        $taskKey = $payload['task_key'] ?? ('Task #' . ($payload['task_id'] ?? 0));
        $oldStatus = $payload['old_status'] ?? 'unknown';
        $newStatus = $payload['new_status'] ?? 'unknown';

        // Отправка в транзакционную очередь фоновых задач
        // (не блокируя HTTP-ответ пользователя!)
        \Module\Acme\TelegramNotifier\Services\TelegramQueue::push([
            'text' => sprintf("Задача %s сменила статус с %s на %s", $taskKey, $oldStatus, $newStatus),
        ]);
    }
}
```

---

## 6. Web UI интеграция: слоты позиций (PositionRegistry) и шаблоны

Для внедрения кастомных блоков интерфейса без изменения шаблонов ядра используется реестр слотов позиций — `Web\System\Module\PositionRegistry`.

### Принцип работы слотов

В шаблонах ядра вызывается функция-помощник:
`<?= module_position('task.detail.sidebar', ['task' => $task, 'public_id' => $publicId]) ?>`

Реестр собирает всех зарегистрированных поставщиков контента для этой позиции, сортирует их по параметру `priority` (по убыванию: 100 выше, чем 10) и оборачивает вывод в безопасный контейнер с ключом `key`.

### Поддерживаемые позиции ядра

| Позиция | Страница / Контекст ядра | Передаваемый контекст ($context) |
|---|---|---|
| `task.detail.sidebar` | Боковая панель детального просмотра задачи | `['task' => array, 'task_id' => int, 'public_id' => string]` |
| `task.detail.tabs` | Дополнительные вкладки в карточке задачи | `['task' => array, 'task_id' => int]` |
| `project.detail.sidebar` | Сайдбар карточки проекта | `['project' => array, 'project_id' => int]` |
| `client.detail.sidebar` | Сайдбар карточки клиента CRM | `['client' => array, 'client_id' => int]` |
| `dashboard.widgets.top` | Верхний ряд виджетов главного дашборда | `['user_id' => int, 'dashboard_data' => array]` |
| `header.actions.right` | Правый блок быстрых действий в верхней навигационной панели | `['current_user' => array]` |

### Пример рендерера для слота

```php
<?php
declare(strict_types=1);

namespace Module\Acme\TelegramNotifier\Controllers;

final class WebSettingsController
{
    /**
     * Рендеринг виджета в сайдбаре карточки задачи.
     * @param array<string, mixed> $context
     * @return string HTML-разметка виджета
     */
    public static function renderSidebarWidget(array $context): string
    {
        $task = $context['task'] ?? [];
        $taskId = (int)($task['id'] ?? 0);
        $taskKey = htmlspecialchars((string)($task['task_key'] ?? ''), ENT_QUOTES, 'UTF-8');

        // Рендерим HTML
        ob_start();
        ?>
        <div class="card mb-3 acme-telegram-widget" data-task-id="<?= $taskId ?>">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <span class="fw-semibold small"><i class="bi bi-telegram text-primary me-1"></i> Telegram Чат</span>
                <span class="badge bg-success-subtle text-success">Подключен</span>
            </div>
            <div class="card-body py-2 small">
                <p class="text-muted mb-2">Уведомления для задачи <strong><?= $taskKey ?></strong> отправляются в общий чат разработки.</p>
                <button type="button" class="btn btn-sm btn-outline-primary w-100" onclick="AcmeTelegram.testNotification(<?= $taskId ?>)">
                    Тестовое уведомление
                </button>
            </div>
        </div>
        <?php
        return (string)ob_get_clean();
    }
}
```

---

## 7. Маршрутизация Web и REST API

Модуль может объявлять как публичные и закрытые эндпоинты REST API (`api_routes.php`), так и маршруты веб-интерфейса CRM (`web_routes.php`).

### Регистрация REST API маршрутов (`api_routes.php`)

Файл возвращает ассоциативный массив маршрутов или регистрирует их через предоставленный объект роутера:

```php
<?php
declare(strict_types=1);

use Module\Acme\TelegramNotifier\Controllers\ApiTelegramController;

return [
    'POST /api/v1/module/acme-telegram/test' => [
        'controller' => ApiTelegramController::class,
        'action'     => 'sendTest',
        'permission' => 'acme.telegram.manage',
        'rate_limit' => 30, // 30 запросов в минуту
    ],
    'GET /api/v1/module/acme-telegram/logs' => [
        'controller' => ApiTelegramController::class,
        'action'     => 'listLogs',
        'permission' => 'acme.telegram.view_logs',
    ],
];
```

### Контроллер REST API модуля

Контроллеры API возвращают структурированный массив данных, который сериализуется ядром в единый формат ответов TropaTT API (`ApiResponse`):

```php
<?php
declare(strict_types=1);

namespace Module\Acme\TelegramNotifier\Controllers;

use Api\System\Library\Support\ApiResponse;
use Api\System\Library\Container;

final class ApiTelegramController
{
    public function __construct(
        private readonly Container $container
    ) {}

    public function sendTest(array $params): array
    {
        $chatId = (string)($params['chat_id'] ?? '');
        if ($chatId === '') {
            return ApiResponse::error('CHAT_ID_REQUIRED', 'Укажите ID чата Telegram', 400);
        }

        /** @var \Module\Acme\TelegramNotifier\Services\TelegramService $telegram */
        $telegram = $this->container->get(\Module\Acme\TelegramNotifier\Services\TelegramService::class);
        $result = $telegram->sendMessage($chatId, "Тестовое уведомление от TropaTT CRM!");

        if (!$result['success']) {
            return ApiResponse::error('SEND_FAILED', $result['error'] ?? 'Ошибка отправки', 502);
        }

        return ApiResponse::success(['delivered' => true, 'timestamp' => time()]);
    }
}
```

---

## 8. Управление ресурсами (Assets: CSS, JS)

Ядро TropaTT управляет модульными скриптами и стилями через класс `Web\System\Module\ModuleAssetManager`.

### Секция assets в manifest.json

- **`assets.css`**: Массив CSS-файлов, подключаемых глобально на всех страницах CRM.
- **`assets.js`**: Массив JavaScript-файлов, подключаемых глобально.
- **`assets.css_routes`**: Словарь `{"route_name": "assets/path.css"}` для точечного подключения стилей только на конкретных маршрутах CRM (например, только в карточке задачи `task-detail`).
- **`assets.js_routes`**: Словарь для точечного подключения скриптов по маршруту.

```json
"assets": {
  "css": [
    "assets/css/global_badge.css"
  ],
  "js": [
    "assets/js/core_notifier.js"
  ],
  "css_routes": {
    "task-detail": "assets/css/widget.css",
    "module/acme_telegram/settings": "assets/css/settings.css"
  },
  "js_routes": {
    "task-detail": "assets/js/widget_interaction.js"
  }
}
```

Все пути автоматически трансформируются ядром в публичные URL вида:
`/modules/{vendor}.{name}/assets/...` с добавлением версионирующего хэша (`?v=1.0.0`) для инвалидации кэша браузера.

---

## 9. Миграции базы данных и жизненный цикл схемы

Модуль может создавать собственные таблицы, индексы и наполнять начальные данные. Выполнение миграций контролирует `Api\System\Library\Module\ModuleMigrationRunner`.

### Правила организации SQL-миграций

1. SQL-файлы размещаются в каталоге, указанном в `manifest.json` (например, `migrations/`).
2. Имена файлов должны строго следовать шаблону нумерации и направления:
   - `{номер}_{название}.up.sql` — применение миграции.
   - `{номер}_{название}.down.sql` — откат миграции.
3. Пример:
   - `001_create_telegram_tables.up.sql`
   - `001_create_telegram_tables.down.sql`
   - `002_add_retry_index.up.sql`
   - `002_add_retry_index.down.sql`

### Пример файла миграции (001_create_telegram_tables.up.sql)

```sql
CREATE TABLE IF NOT EXISTS `module_acme_telegram_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `task_id` BIGINT UNSIGNED NULL,
    `chat_id` VARCHAR(64) NOT NULL,
    `message_text` TEXT NOT NULL,
    `status` ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    `response_payload` JSON NULL,
    `error_message` VARCHAR(512) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `sent_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_status_created` (`status`, `created_at`),
    KEY `idx_task_id` (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Пример файла отката (001_create_telegram_tables.down.sql)

```sql
DROP TABLE IF EXISTS `module_acme_telegram_logs`;
```

При активации модуля ядро атомарно в транзакции применяет все невыполненные `.up.sql` файлы и делает запись в таблицу `module_migrations`. При удалении модуля ядро последовательно в обратном порядке накатывает соответствующие `.down.sql` скрипты.

---

## 10. Фоновые задачи: Cron-планировщик и транзакционная очередь задач

На shared-хостинге нет возможности держать постоянные демоны типа RabbitMQ или Supervisor. Архитектура TropaTT предлагает два механизма асинхронной обработки:

### 1. Планировщик Cron (`ModuleCronScheduler`)

Модули объявляют периодические задачи через метод `getScheduledTasks()` своего `ServiceProvider`.

```php
new ScheduledTask(
    moduleName: 'acme.telegram_notifier',
    taskName: 'telegram_retry_queue',
    schedule: '*/5 * * * *', // Каждые 5 минут
    handlerClass: NotificationQueueHandler::class,
    handlerMethod: 'run'
)
```

**Ограничения безопасности Cron:**
- **Handler Allowlist:** Классы обработчиков cron обязаны находиться в доверенных пространствах имен (`Api\...` или `Module\...`).
- **Method Allowlist:** Разрешены только безопасные имена методов (`run`, `execute`, `handle`, `process`, `freshnessScan`, `draftsCleanup`, `versionsCleanup`, `reindexSearch`, `captureDaily`, `autoClosePeriods`, `dispatchQueue`).
- Если модуль деактивирован, планировщик автоматически пропускает выполнение его задач (`skipped`), предотвращая ошибки.

### 2. Транзакционная очередь задач (`ModuleJobDispatcher`)

Для отложенного выполнения длительных операций (отправка внешних HTTP-запросов, генерация документов) используется таблица `module_jobs` через `ModuleJobDispatcher`.

```php
use Api\System\Library\Module\ModuleJobDispatcher;

// Постановка задачи в очередь с задержкой 10 секунд
$dispatcher = $container->get(ModuleJobDispatcher::class);
$jobId = $dispatcher->dispatch(
    moduleName: 'acme.telegram_notifier',
    jobName: 'send_telegram_http_message',
    payload: [
        'chat_id' => '-100123456789',
        'text' => 'Привет из очереди!',
    ],
    delay: 10
);
```

Задачи из очереди обрабатываются системным тиком cron малыми порциями (`SELECT ... FOR UPDATE`), укладываясь в лимиты памяти < 32 MB и времени выполнения < 25 секунд.

---

## 11. Безопасность, статическая валидация кода и изоляция ошибок (Circuit Breaker)

TropaTT CRM уделяет первостепенное внимание стабильности ядра при работе сторонних расширений.

### 1. Статическая валидация кода (`ModuleCodeValidator`)

Перед распаковкой или включением модуля валидатор сканирует все PHP-файлы через парсер токенов `token_get_all`.
Запрещены к использованию следующие функции и конструкции:

```php
private array $forbiddenFunctions = [
    'eval', 'exec', 'system', 'shell_exec', 'passthru',
    'popen', 'proc_open', 'pcntl_exec', 'assert',
    'create_function', 'include', 'file_put_contents',
    'unlink', 'rmdir', 'chmod', 'chown',
    'dl', 'ffi',
];
```

При обнаружении любого из этих токенов модуль бракуется с детальным указанием файла и номера строки.

### 2. Защита от каскадных сбоев (`ModuleCircuitBreaker`)

Если хук или метод модуля начинает выбрасывать непредвиденные исключения, активируется автоматический выключатель:
- **Состояние `CLOSED`**: Штатный режим работы модуля.
- **Порог ошибок**: При возникновении **5 сбоев подряд** Circuit Breaker переводит модуль в состояние **`OPEN`**.
- В состоянии `OPEN` ядро полностью изолирует модуль: его хуки перестают вызываться, разгружая запросы ядра CRM.
- **Таймаут восстановления**: По истечении 60 секунд предохранитель переходит в состояние **`HALF_OPEN`**, допуская до 3 пробных вызовов. Если они проходят успешно, статус сбрасывается в `CLOSED`. При повторе сбоя статус мгновенно возвращается в `OPEN`.

---

## 12. Упаковка, версионирование и удаленная установка

Модули TropaTT распространяются в виде ZIP-архивов.

### Структура архива для распространения

В корне архива `.zip` должны сразу находиться файлы модуля (без лишней верхней вложенной папки):
```
module-acme-telegram-1.0.0.zip
├── manifest.json
├── ServiceProvider.php
├── api_routes.php
├── migrations/
└── ...
```

### Удаленная установка (`ModuleRemoteInstaller`)

Администраторы CRM могут устанавливать модули как загрузкой архива через интерфейс, так и по публичному URL:

```php
use Api\System\Library\Module\ModuleRemoteInstaller;

$installer = $container->get(ModuleRemoteInstaller::class);

// Установка по защищенному HTTPS URL:
$moduleName = $installer->installFromUrl(
    url: 'https://marketplace.tropatt.com/downloads/acme.telegram_notifier-1.0.0.zip',
    verifySignature: true
);
```

**Процедура инсталляции:**
1. Валидация URL на SSRF через `UrlSafetyValidator` (запрещены localhost, 127.0.0.1, приватные подсети 10.0.0.0/8, 192.168.0.0/16).
2. Загрузка архива во временный каталог.
3. Проверка ZIP-структуры и наличия `manifest.json`.
4. Статическая валидация всех PHP-файлов через `ModuleCodeValidator`.
5. Проверка соответствия требуемой версии ядра `core_version` и зависимостей `dependencies`.
6. Распаковка в рабочую папку `modules/{vendor}.{name}`.
7. Применение миграций БД через `ModuleMigrationRunner`.
8. Регистрация дефолтных настроек `config_defaults`.
9. Запись модуля в системную таблицу активных плагинов.

---

## 13. Полный эталонный пример модуля: Acme Telegram Notifier

Ниже представлен завершенный пример готового к установке модуля, объединяющего все ключевые возможности подсистемы.

### Файл `manifest.json`

```json
{
  "name": "acme.telegram_notifier",
  "version": "1.0.0",
  "vendor": "Acme",
  "author": "Anton Barinov",
  "author_url": "https://github.com/Anton-Barinov",
  "title": "Telegram Notifier",
  "description": "Оповещения в Telegram-чаты при событиях по задачам TropaTT CRM",
  "category": "integrations",
  "core_version": ">=1.0.0",
  "dependencies": [],
  "require_permissions": ["tasks.read"],
  "service_provider": "Module\\Acme\\TelegramNotifier\\ServiceProvider",
  "api_routes": "api_routes.php",
  "migrations": "migrations",
  "config_defaults": {
    "bot_token": "",
    "chat_id": ""
  },
  "positions": {
    "task.detail.sidebar": [
      {
        "renderer": "Module\\Acme\\TelegramNotifier\\Controllers\\SidebarController::render",
        "priority": 20,
        "key": "telegram_sidebar"
      }
    ]
  },
  "hooks": {
    "task.status_changed": [
      {
        "handler": "Module\\Acme\\TelegramNotifier\\Handlers\\StatusHandler::handle",
        "priority": 10
      }
    ]
  }
}
```

### Файл `ServiceProvider.php`

```php
<?php
declare(strict_types=1);

namespace Module\Acme\TelegramNotifier;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;

final class ServiceProvider extends AbstractModuleServiceProvider
{
    public function register(Container $container): void
    {
        // Регистрация сервисов модуля
    }

    public function boot(Container $container): void
    {
        // Выполняется после инициализации всех сервисов
    }
}
```

### Файл `Handlers/StatusHandler.php`

```php
<?php
declare(strict_types=1);

namespace Module\Acme\TelegramNotifier\Handlers;

use Api\System\Library\Support\AppLog;

final class StatusHandler
{
    public static function handle(array $data): void
    {
        $taskTitle = $data['title'] ?? 'Без названия';
        $newStatus = $data['new_status'] ?? 'неизвестно';
        AppLog::info(sprintf("[TelegramNotifier] Задача '%s' перешла в статус '%s'", $taskTitle, $newStatus));
    }
}
```

### Файл `Controllers/SidebarController.php`

```php
<?php
declare(strict_types=1);

namespace Module\Acme\TelegramNotifier\Controllers;

final class SidebarController
{
    public static function render(array $context): string
    {
        $task = $context['task'] ?? [];
        $title = htmlspecialchars((string)($task['title'] ?? ''), ENT_QUOTES, 'UTF-8');

        return '<div class="alert alert-info py-2 small mb-3">'
             . '<i class="bi bi-telegram me-1"></i> Telegram-уведомления активны для: <strong>' . $title . '</strong>'
             . '</div>';
    }
}
```

---

*Документация актуальна для ядра TropaTT CRM релизной серии 2026 года.*
