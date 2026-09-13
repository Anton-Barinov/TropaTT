# Руководство разработчика модулей TropaTT CRM

> Исчерпывающее техническое руководство по проектированию, разработке, интеграции, упаковке и распространению модулей расширения для TropaTT CRM. Документация полностью синхронизирована с кодовой базой ядра (серия релизов 2026 года).

---

## Оглавление

1. [Архитектура модульной подсистемы](#1-архитектура-модульной-подсистемы)
2. [Анатомия модуля, файловая структура и автозагрузка (PSR-4 / ModuleAutoloader)](#2-анатомия-модуля-файловая-структура-и-автозагрузка-psr-4--moduleautoloader)
3. [Спецификация манифеста manifest.json](#3-спецификация-манифеста-manifestjson)
4. [Провайдер сервисов (AbstractModuleServiceProvider), DI-контейнер и RBAC](#4-провайдер-сервисов-abstractmoduleserviceprovider-di-контейнер-и-rbac)
5. [Шина событий: полный каталог ModuleEvents и HookManager](#5-шина-событий-полный-каталог-moduleevents-и-hookmanager)
6. [Web UI интеграция: слоты позиций (PositionRegistry), render-хуки и i18n](#6-web-ui-интеграция-слоты-позиций-positionregistry-render-хуки-и-i18n)
7. [Маршрутизация Web и REST API](#7-маршрутизация-web-и-rest-api)
8. [Управление ресурсами (Assets: CSS, JS)](#8-управление-ресурсами-assets-css-js)
9. [Миграции базы данных и жизненный цикл схемы](#9-миграции-базы-данных-и-жизненный-цикл-схемы)
10. [Фоновые задачи: Cron-планировщик и транзакционная очередь задач](#10-фоновые-задачи-cron-планировщик-и-транзакционная-очередь-задач)
11. [Безопасность, статическая валидация кода и изоляция ошибок (Circuit Breaker)](#11-безопасность-статическая-валидация-кода-и-изоляция-ошибок-circuit-breaker)
12. [Упаковка, версионирование и удаленная установка](#12-упаковка-версионирование-и-удаленная-установка)
13. [Полный эталонный рабочий модуль: Acme Telegram Notifier](#13-полный-эталонный-рабочий-модуль-acme-telegram-notifier)

---

## 1. Архитектура модульной подсистемы

Модульная подсистема TropaTT CRM спроектирована по принципам **Zero-Daemon**, максимальной производительности при ограничениях shared-хостинга (< 32 MB RAM, таймауты выполнения до 25 секунд) и строгой изоляции расширений от ядра системы.

### Ключевые архитектурные принципы

- **Неинвазивность (Zero-Core-Edits):** Модули расширяют функционал CRM исключительно через объявленные декларативные контракты (`manifest.json`), точки внедрения интерфейса (`PositionRegistry`), шину хуков (`HookManager`) и маршрутизацию (`api_routes`, `web_routes`). Правка исходного кода ядра категорически запрещена.
- **Двухуровневая изоляция ошибок (Fault Tolerance):** Сбой или исключение в коде модуля не должны приводить к падению ядра или отказу пользовательских запросов. Для защиты ядра применяются `try-catch` изоляция каждого хука и автоматический предохранитель **Circuit Breaker** (`ModuleCircuitBreaker`), размыкающий цепь при 5 повторных сбоях подряд.
- **Статический аудит безопасности:** Перед установкой и активацией каждый PHP-файл модуля валидируется через `ModuleCodeValidator` методом лексического анализа токенов (`token_get_all`). Использование опасных функций и системных вызовов (`eval`, `exec`, `shell_exec`, `passthru`, `file_put_contents`, `unlink`, `dl`, `ffi` и др.) приводит к немедленной блокировке модуля.
- **Транзакционная целостность миграций:** Модуль может иметь собственные таблицы и поля. Все изменения схемы БД регистрируются в системной таблице `module_migrations` с поддержкой отката как отдельных шагов, так и всех миграций целиком (`_rollback.sql` или `.down.sql`).
- **Строгая схема пространства имен:** Все классы модуля регистрируются под унифицированным пространством имен `Module\<VendorName>\<ModuleName>\...` через оптимизированный загрузчик ядра `ModuleAutoloader`.

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

## 2. Анатомия модуля, файловая структура и автозагрузка (PSR-4 / ModuleAutoloader)

Каждый модуль TropaTT CRM располагается в отдельной директории внутри корневого каталога модулей:
`modules/{vendor}.{name}/` (на боевом сервере или в репозитории: `upload/modules/{vendor}.{name}/`).

### Соглашение по именованию модуля
- Формат имени: `{vendor}.{name}` (только строчные латинские буквы, цифры и дефисы: `^[a-z0-9]+\.[a-z0-9\-]+$`).
- Примеры: `crm.wip-limit`, `crm.slack-integration`, `acme.telegram-notifier`.

### Автозагрузка классов (`ModuleAutoloader`)
Класс `Api\System\Library\Module\ModuleAutoloader` регистрирует SPL-автозагрузчик для пространства имен `Module\<Vendor>\<Name>\...`.
Правила маппинга:
1. Имя вендора и модуля с дефисами автоматически трансформируются в **CamelCase**:
   - Папка модуля: `modules/acme.telegram-notifier/`
   - Базовый namespace: `Module\Acme\TelegramNotifier\`
2. Классы ищутся в двух поддиректориях: сначала `api/`, затем `web/`:
   - `Module\Acme\TelegramNotifier\Service\Notifier` -> ищется в:
     1. `modules/acme.telegram-notifier/api/Service/Notifier.php`
     2. `modules/acme.telegram-notifier/api/service/Notifier.php`
     3. `modules/acme.telegram-notifier/web/Service/Notifier.php`
     4. `modules/acme.telegram-notifier/web/service/Notifier.php`
3. Сервис-провайдер модуля может располагаться непосредственно в корне `api/` (например `api/TelegramNotifierServiceProvider.php`).

### Эталонная иерархия директорий модуля

```
modules/acme.telegram-notifier/
├── manifest.json                      # Декларативный манифест модуля (обязателен)
├── README.md                          # Описание и инструкция для пользователей
├── api/                               # Серверная логика API и бэкенда
│   ├── TelegramNotifierServiceProvider.php  # ServiceProvider модуля
│   ├── config/
│   │   └── routes.php                 # Маршруты REST API
│   ├── controller/
│   │   └── ApiTelegramController.php  # API-контроллер
│   ├── service/
│   │   ├── TelegramService.php        # Бизнес-логика отправки
│   │   └── TelegramQueue.php          # Очередь
│   ├── hook/
│   │   └── StatusHook.php             # Слушатели хуков ядра
│   ├── cron/
│   │   └── QueueWorkerHandler.php     # Обработчик cron
│   └── migrations/                    # SQL-миграции базы данных
│       ├── 001_create_tables.sql
│       └── 001_create_tables_rollback.sql
└── web/                               # Веб-интерфейс и пользовательские слоты
    ├── config/
    │   └── routes.php                 # Маршруты веб-страниц CRM
    ├── controller/
    │   └── WebSettingsController.php  # Контроллер веб-страницы
    ├── position/
    │   └── TaskSidebarPanel.php       # Рендерер UI-слота в сайдбаре задачи
    ├── template/
    │   └── page/
    │       └── settings.php           # Шаблон страницы настроек
    ├── language/                      # Локализация интерфейса
    │   ├── ru-ru.php
    │   └── en-us.php
    └── assets/                        # Статические ресурсы
        ├── css/
        │   └── widget.css
        └── js/
            └── widget.js
```

---

## 3. Спецификация манифеста manifest.json

Файл `manifest.json` является единым источником правды для ядра системы. Он загружается и валидируется классом `Api\System\Library\Module\PluginManager` при инициализации CRM.

### Полная схема полей manifest.json

| Поле | Тип | Обязательное | Описание | Пример |
|---|---|:---:|---|---|
| `name` | `string` | **Да** | Идентификатор модуля (`^[a-z0-9]+\.[a-z0-9\-]+$`, макс. 64 симв.) | `"acme.telegram-notifier"` |
| `version` | `string` | **Да** | Семантическая версия модуля (`SemVer`: `X.Y.Z`) | `"1.2.0"` |
| `vendor` | `string` | **Да** | Имя вендора / разработчика | `"acme"` |
| `author` | `string` | **Да** | Имя или псевдоним автора | `"Anton Barinov"` |
| `author_url` | `string` | Нет | Ссылка на профиль автора или репозиторий | `"https://github.com/Anton-Barinov"` |
| `title` | `string` | **Да** | Название модуля для интерфейса CRM | `"Telegram Уведомления"` |
| `description` | `string` | **Да** | Краткое описание назначения модуля | `"Отправка уведомлений в Telegram по событиям задач."` |
| `license` | `string` | Нет | Лицензия распространения | `"MIT"` / `"Proprietary"` |
| `category` | `string` | Нет | Категория (`integration`, `productivity`, `crm`, `finance`, `migration`) | `"integration"` |
| `core_version` | `string` | Нет | Требуемая версия ядра CRM (по умолчанию `>=1.0.0`) | `">=1.0.0"` |
| `dependencies` | `array` | Нет | Зависимости от других модулей `[{"name": "..."}]` | `[]` |
| `require_permissions` | `array` | Нет | Системные RBAC-права ядра, необходимые модулю | `["tasks.read", "settings.manage"]` |
| `service_provider` | `string` | Нет | FQCN сервис-провайдера | `"Module\\Acme\\TelegramNotifier\\TelegramNotifierServiceProvider"` |
| `api_routes` | `string` | Нет | Путь к файлу маршрутов API относительно папки модуля | `"api/config/routes.php"` |
| `web_routes` | `string` | Нет | Путь к файлу веб-маршрутов относительно папки модуля | `"web/config/routes.php"` |
| `migrations` | `string` | Нет | Относительный путь к каталогу SQL-миграций | `"api/migrations/"` |
| `hooks` | `object` | Нет | Декларативные хуки вида `{"event": [{"handler": "...", "priority": 10}]}` | `{}` |
| `positions` | `object` | Нет | Регистрация рендереров в слоты UI (с ключом блока) | См. раздел 6 |
| `menu_items` | `array` | Нет | Элементы навигационного меню CRM | См. раздел 4 |
| `config_defaults` | `object` | Нет | Значения настроек по умолчанию (ключ-значение) | `{"bot_token": "", "chat_id": ""}` |
| `assets` | `object` | Нет | Регистрация CSS и JS стилей/скриптов | См. раздел 8 |
| `web_hooks` | `object` | Нет | Рендер-фазовые хуки страницы (`render.before`, `render.after`) | `{}` |

### Пример эталонного manifest.json:

```json
{
  "name": "acme.telegram-notifier",
  "version": "1.0.0",
  "vendor": "acme",
  "author": "Anton Barinov",
  "author_url": "https://github.com/Anton-Barinov",
  "title": "Telegram Уведомления",
  "description": "Мгновенная отправка уведомлений о смене статусов и комментариях в Telegram",
  "category": "integration",
  "license": "MIT",
  "core_version": ">=1.0.0",
  "dependencies": [],
  "require_permissions": [
    "tasks.read"
  ],
  "service_provider": "Module\\Acme\\TelegramNotifier\\TelegramNotifierServiceProvider",
  "api_routes": "api/config/routes.php",
  "web_routes": "web/config/routes.php",
  "migrations": "api/migrations/",
  "config_defaults": {
    "telegram_bot_token": "",
    "telegram_default_chat_id": "",
    "notify_on_status_change": 1
  },
  "menu_items": [
    {
      "route": "module-telegram-settings",
      "label": "Настройки Telegram",
      "icon": "<i class=\"bi bi-telegram\"></i>",
      "permission": "acme.telegram.manage",
      "parent": null
    }
  ],
  "assets": {
    "css": [
      "web/assets/css/widget.css"
    ],
    "js_routes": {
      "task-detail": "web/assets/js/widget.js"
    },
    "css_routes": {
      "task-detail": "web/assets/css/widget.css"
    }
  },
  "positions": {
    "task.detail.sidebar": [
      {
        "renderer": "Module\\Acme\\TelegramNotifier\\Position\\TaskSidebarPanel::render",
        "priority": 15,
        "key": "telegram_notifier"
      }
    ]
  },
  "hooks": {}
}
```

---

## 4. Провайдер сервисов (AbstractModuleServiceProvider), DI-контейнер и RBAC

Класс `ServiceProvider` связывает модуль с ядром CRM. Рекомендуется наследовать класс от `Api\System\Library\Module\AbstractModuleServiceProvider`, который реализует интерфейс `ModuleServiceProviderInterface`.

### Жизненный цикл провайдера

1. **`register(Container $container): void`**
   - Вызывается в методе `App::initModuleSystem()` на этапе сборки зависимостей контейнера.
   - Здесь регистрируются собственные сервисы модуля, синглтоны и клиенты API в DI-контейнер ядра (`$container->singleton(...)` или `$container->set(...)`).
   - **Важно:** В методе `register()` нельзя вызывать методы других сервисов ядра или модулей, так как они могут быть еще не зарегистрированы.

2. **`boot(Container $container): void`**
   - Вызывается после того, как все сервис-провайдеры завершили этап `register()`.
   - Здесь подписываются слушатели событий в `HookManager`, выполняются начальные проверки и инициализации.

### Методы декларативного контракта ServiceProvider

| Метод | Возвращаемый тип | Описание |
|---|---|---|
| `getHooks()` | `array` | Массив слушателей событий вида `[event_name => [['handler' => ..., 'priority' => 10]]]` |
| `getPermissions()` | `array<int, string>` | Список уникальных RBAC-прав. Автоматически регистрируется в системе прав CRM! |
| `getMenuItems()` | `array<int, array>` | Элементы навигационного меню CRM: `['route', 'label', 'icon', 'permission', 'parent']` |
| `getConfig()` | `array<string, mixed>` | Динамические конфигурационные параметры модуля |
| `getAssets()` | `array<string, mixed>` | Регистрация ресурсов модуля |
| `getScheduledTasks()` | `array<int, ScheduledTask>` | Задачи для Cron-планировщика ядра |

### Регистрация прав RBAC
Коды разрешений, возвращаемые методом `getPermissions()`, ядро автоматически регистрирует в системном репозитории разрешений (`PermissionRepository::ensureRegistry()`). Точечный формат прав (например, `acme.telegram.manage`) преобразуется в человекочитаемый вид (`acme telegram manage`) и становится доступен в панели назначения ролей пользователей.

### Пример реализации ServiceProvider.php

```php
<?php
declare(strict_types=1);

namespace Module\Acme\TelegramNotifier;

use Api\System\Library\Container;
use Api\System\Library\Hook\HookManager;
use Api\System\Library\Module\AbstractModuleServiceProvider;
use Api\System\Library\Module\ModuleEvents;
use Api\System\Library\Module\ScheduledTask;
use Module\Acme\TelegramNotifier\Service\TelegramService;
use Module\Acme\TelegramNotifier\Cron\QueueWorkerHandler;

final class TelegramNotifierServiceProvider extends AbstractModuleServiceProvider
{
    public function register(Container $container): void
    {
        // Регистрация сервиса как singleton в DI-контейнере ядра
        $container->singleton(TelegramService::class, static function (Container $c): TelegramService {
            $pdo = $c->get(\PDO::class);
            return new TelegramService($pdo);
        });
    }

    public function boot(Container $container): void
    {
        /** @var HookManager $hooks */
        $hooks = $container->get('hook.manager');

        // Подписка на событие смены статуса задачи с приоритетом 100
        $hooks->register(ModuleEvents::TASK_STATUS_CHANGED, function (array &$context): void {
            $telegram = $this->container->get(TelegramService::class);
            $telegram->onStatusChanged($context);
        }, 100);
    }

    public function getPermissions(): array
    {
        return [
            'acme.telegram.view',
            'acme.telegram.manage',
        ];
    }

    public function getMenuItems(): array
    {
        return [
            [
                'route' => 'module-telegram-settings',
                'label' => 'Telegram Бот',
                'icon' => '<i class="bi bi-telegram"></i>',
                'permission' => 'acme.telegram.manage',
                'parent' => null,
            ],
        ];
    }

    public function getScheduledTasks(): array
    {
        return [
            new ScheduledTask(
                moduleName: 'acme.telegram-notifier',
                taskName: 'flush_telegram_queue',
                schedule: '*/2 * * * *',
                handlerClass: QueueWorkerHandler::class,
                handlerMethod: 'run'
            ),
        ];
    }
}
```

---

## 5. Шина событий: полный каталог ModuleEvents и HookManager

Ядро TropaTT CRM управляет событиями жизненного цикла через `HookManager` (`Api\System\Library\Hook\HookManager`). Каталог доступных имен событий строго централизован в финальном классе `Api\System\Library\Module\ModuleEvents`.

### Сигнатура вызова обработчика хука
```php
function (array &$context): void
```
Контекст `$context` передается **по ссылке**. Обработчик может не только считывать параметры события, но и обогащать их, не прерывая выполнение запроса. Ошибки внутри хуков перехватываются `HookManager` и логируются в системный лог без падения основного процесса CRM.

### Полный каталог констант ModuleEvents (35+ событий)

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

    // Жизненный цикл рабочих циклов / спринтов (WorkCycleController)
    public const CYCLE_CREATED         = 'cycle.created';
    public const CYCLE_STARTED         = 'cycle.started';
    public const CYCLE_COMPLETED       = 'cycle.completed';
    public const CYCLE_REOPENED        = 'cycle.reopened';
    public const CYCLE_ARCHIVED        = 'cycle.archived';
    public const CYCLE_DELETED         = 'cycle.deleted';

    // CRM записи (клиенты, контрагенты, контакты, компании, организации)
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

    // Таксономия и конфигурационные сущности
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

    // Коллаборация, файлы и чат
    public const COMMENT_ADDED         = 'comment.added';
    public const FILE_UPLOADED         = 'file.uploaded';
    public const CHAT_MESSAGE_CREATED  = 'chat.message_created';
    public const CHAT_MESSAGE_UPDATED  = 'chat.message_updated';
    public const CHAT_MESSAGE_DELETED  = 'chat.message_deleted';

    // Рендеринг интерфейса ядра (Web\Core\Controller)
    public const RENDER_BEFORE         = 'render.before';
    public const RENDER_AFTER          = 'render.after';
}
```

---

## 6. Web UI интеграция: слоты позиций (PositionRegistry), render-хуки и i18n

Для неинвазивного внедрения элементов интерфейса используется реестр позиций `Web\System\Module\PositionRegistry`.

### Реализованные позиции шаблонов ядра

В шаблонах страниц CRM ядро вызывает хелпер `module_position($slotName, $context)`:

| Слот позиции | Шаблон ядра | Передаваемый контекст (`$context`) |
|---|---|---|
| `task.detail.sidebar` | `view/template/page/task_detail.php` | `['route' => 'task-detail', 'task_public_id' => 'tsk_...']` |
| `project.detail.sidebar` | `view/template/page/project_detail.php` | `['route' => 'project-detail', 'project_public_id' => 'prj_...']` |
| `gantt.content.after` | `view/template/page/gantt.php` | `['route' => 'gantt']` |
| `kanban.board.after` | `view/template/page/kanban.php` | `['route' => 'kanban']` |
| `tasks.list.after` | `view/template/page/tasks.php` | `['route' => 'tasks']` |
| `calendar.content.after` | `view/template/page/calendar.php` | `['route' => 'calendar']` |
| `counterparties.content.after` | `view/template/page/counterparties.php` | `['route' => 'counterparties']` |
| `profile.content.after` | `view/template/page/profile.php` | `['route' => 'profile']` |
| `dashboard.content.after` | `view/template/page/dashboard.php` | `['route' => 'dashboard']` |

### Регистрация позиции в manifest.json
Каждый рендерер позиции описывается структурой с полями `renderer` (статический метод вида `Class::method`), `priority` (сортировка от большего к меньшему) и `key` (уникальный идентификатор блока):

```json
"positions": {
  "task.detail.sidebar": [
    {
      "renderer": "Module\\Acme\\TelegramNotifier\\Position\\TaskSidebarPanel::render",
      "priority": 10,
      "key": "telegram_panel"
    }
  ]
}
```

### Рендерер позиции (Position/TaskSidebarPanel.php)
Метод рендерера обязан принимать массив контекста и возвращать готовую HTML-строку:

```php
<?php
declare(strict_types=1);

namespace Module\Acme\TelegramNotifier\Position;

final class TaskSidebarPanel
{
    public static function render(array $context): string
    {
        $taskPublicId = trim((string)($context['task_public_id'] ?? ''));
        if ($taskPublicId === '') {
            return '';
        }

        $safePublicId = htmlspecialchars($taskPublicId, ENT_QUOTES, 'UTF-8');

        return '<div class="crm-card mb-3" data-task-public-id="' . $safePublicId . '">'
            . '<div class="crm-side-card-head">'
            . '<h2 class="h6 mb-0"><i class="bi bi-telegram text-primary me-1"></i> Telegram Уведомления</h2>'
            . '</div>'
            . '<div class="p-3 small">'
            . '<p class="text-muted mb-2">Уведомления по задаче отправляются в привязанный чат.</p>'
            . '</div>'
            . '</div>';
    }
}
```

### Локализация интерфейса (i18n)
Если модуль содержит поддиректорию `web/language/`, ядро автоматически загружает переводы для текущей локали пользователя (`web/language/ru-ru.php`, `web/language/en-us.php`):
```php
<?php
// web/language/ru-ru.php
return [
    'module_telegram_settings' => [
        'title' => 'Настройки Telegram-бота',
        'bot_token' => 'Токен бота',
        'save' => 'Сохранить',
    ],
];
```

---

## 7. Маршрутизация Web и REST API

Модули в TropaTT CRM регистрируют как REST API эндпоинты, так и страницы интерфейса.

### Маршрутизация REST API (`api/config/routes.php`)

**Важнейшая особенность ядра:**
При регистрации маршрутов через `Router::addManyFromModule($routes, $modulePrefix)` ядро автоматически добавляет к пути маршрута префикс:
`/_module/{vendor}.{name}`

Файл `routes.php` должен возвращать массив спецификаций:
```php
<?php
declare(strict_types=1);

use Module\Acme\TelegramNotifier\Controller\ApiTelegramController;

return [
    [
        'methods'    => ['GET'],
        'route'      => '/settings', // Преобразуется в /_module/acme.telegram-notifier/settings
        'controller' => ApiTelegramController::class,
        'action'     => 'getSettings',
        'auth'       => true,
    ],
    [
        'methods'    => ['POST'],
        'route'      => '/test-message', // Преобразуется в /_module/acme.telegram-notifier/test-message
        'controller' => ApiTelegramController::class,
        'action'     => 'sendTest',
        'auth'       => true,
    ],
];
```

### API Контроллер и JsonResponse
API-контроллеры модулей принимают `Container` через конструктор и возвращают объект ответа `Api\System\Library\Http\JsonResponse`:

```php
<?php
declare(strict_types=1);

namespace Module\Acme\TelegramNotifier\Controller;

use Api\System\Library\Container;
use Api\System\Library\Http\JsonResponse;

final class ApiTelegramController
{
    public function __construct(private readonly Container $container) {}

    public function getSettings(array $params = []): JsonResponse
    {
        return JsonResponse::success('TELEGRAM_SETTINGS', 'OK', [
            'bot_configured' => true,
        ]);
    }

    public function sendTest(array $params = []): JsonResponse
    {
        $raw = file_get_contents('php://input');
        $body = json_decode($raw ?: '{}', true) ?: [];

        $chatId = trim((string)($body['chat_id'] ?? ''));
        if ($chatId === '') {
            return JsonResponse::error('INVALID_PARAM', 'chat_id is required', 400);
        }

        return JsonResponse::success('SENT', 'Test message dispatched');
    }
}
```

### Маршрутизация Web-страниц (`web/config/routes.php`)
Файл веб-маршрутов возвращает ассоциативную карту:
```php
<?php
declare(strict_types=1);

use Module\Acme\TelegramNotifier\Controller\WebSettingsController;

return [
    'module-telegram-settings' => [WebSettingsController::class, 'index'],
];
```
Обращение к этой странице в браузере выполняется по стандартному роуту:
`https://crm.example.com/web/index.php?route=module-telegram-settings`

### Web Контроллер
Контроллеры веб-интерфейса наследуют `Web\System\Core\Controller`:
```php
<?php
declare(strict_types=1);

namespace Module\Acme\TelegramNotifier\Controller;

use Web\System\Core\Controller;

final class WebSettingsController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/settings.php', [
            'title' => 'Настройки Telegram',
            'route' => 'module-telegram-settings',
        ]);
    }
}
```

---

## 8. Управление ресурсами (Assets: CSS, JS)

Ядро управляет ресурсами модулей через `Web\System\Module\ModuleAssetManager`.

### Режимы подключения ресурсов в manifest.json:
- **Глобальные стили/скрипты:**
  - `assets.css`: список файлов, подключаемых на **всех** страницах CRM.
  - `assets.js`: список скриптов, подключаемых глобально.
- **Маршрутные стили/скрипты (Route-scoped):**
  - `assets.css_routes`: ассоциативный массив `{"route_name": "path/to.css"}`.
  - `assets.js_routes`: ассоциативный массив `{"route_name": "path/to.js"}`.

```json
"assets": {
  "css": [
    "web/assets/css/global-badge.css"
  ],
  "css_routes": {
    "task-detail": "web/assets/css/widget.css",
    "module-telegram-settings": "web/assets/css/settings.css"
  },
  "js_routes": {
    "task-detail": "web/assets/js/widget.js"
  }
}
```

---

## 9. Миграции базы данных и жизненный цикл схемы

Миграции модуля выполняются транзакционно через класс `Api\System\Library\Module\ModuleMigrationRunner`.

### Поддерживаемые соглашения по именованию файлов миграций

`ModuleMigrationRunner` поддерживает два стандартных формата именования:

1. **Суффикс `_rollback.sql` (стандарт встроенных модулей TropaTT):**
   - Накат: `001_create_tables.sql`
   - Откат: `001_create_tables_rollback.sql`
   - Накат: `002_add_index.sql`
   - Откат: `002_add_index_rollback.sql`

2. **Суффикс `.up.sql` / `.down.sql`:**
   - Накат: `001_init.up.sql`
   - Откат: `001_init.down.sql`

Файлы сортируются естественным порядком (`strnatcmp`), что гарантирует строго последовательное применение: `001 -> 002 -> 003`.

### Пример файла миграции (`api/migrations/001_create_tables.sql`)
```sql
CREATE TABLE IF NOT EXISTS `module_acme_telegram_queue` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `chat_id` VARCHAR(64) NOT NULL,
    `message_text` TEXT NOT NULL,
    `status` ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `sent_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_status_created` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Пример файла отката (`api/migrations/001_create_tables_rollback.sql`)
```sql
DROP TABLE IF EXISTS `module_acme_telegram_queue`;
```

---

## 10. Фоновые задачи: Cron-планировщик и транзакционная очередь задач

На shared-хостинге нет возможности держать постоянные демоны. Архитектура TropaTT предлагает два механизма фоновой обработки:

### 1. Планировщик Cron (`ModuleCronScheduler`)

Модули объявляют периодические задачи через метод `getScheduledTasks()` своего `ServiceProvider`.

```php
new ScheduledTask(
    moduleName: 'acme.telegram-notifier',
    taskName: 'telegram_queue_worker',
    schedule: '*/5 * * * *', // Каждые 5 минут
    handlerClass: QueueWorkerHandler::class,
    handlerMethod: 'run'
)
```

**Ограничения безопасности Cron:**
- **Handler Allowlist:** Классы обработчиков cron обязаны находиться строго в пространствах имен `Api\...` или `Module\...`.
- **Method Allowlist:** Разрешены только следующие методы:
  `run`, `execute`, `handle`, `process`, `freshnessScan`, `draftsCleanup`, `versionsCleanup`, `reindexSearch`, `captureDaily`, `autoClosePeriods`, `dispatchQueue`.
- Если модуль деактивирован, планировщик автоматически пропускает выполнение его задач (`skipped`), предотвращая ошибки.

### 2. Транзакционная очередь задач (`ModuleJobDispatcher`)

Для отложенного выполнения длительных операций без создания кастомных таблиц используется системная очередь `module_jobs` через `ModuleJobDispatcher`.

```php
use Api\System\Library\Module\ModuleJobDispatcher;

$dispatcher = $container->get(ModuleJobDispatcher::class);
$jobId = $dispatcher->dispatch(
    moduleName: 'acme.telegram-notifier',
    jobName: 'send_telegram_http_message',
    payload: [
        'chat_id' => '-100123456789',
        'text' => 'Привет из транзакционной очереди!',
    ],
    delay: 10 // Задержка 10 секунд
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

В корне архива `.zip` должны сразу находиться файлы модуля (без лишней верхней папки):
```
module-acme-telegram-1.0.0.zip
├── manifest.json
├── README.md
├── api/
└── web/
```

### Удаленная установка (`ModuleRemoteInstaller`)

Администраторы CRM могут устанавливать модули как загрузкой архива через интерфейс, так и по публичному URL:

```php
use Api\System\Library\Module\ModuleRemoteInstaller;

$installer = $container->get(ModuleRemoteInstaller::class);

// Установка по HTTPS URL:
$moduleName = $installer->installFromUrl(
    url: 'https://marketplace.tropatt.com/downloads/acme.telegram-notifier-1.0.0.zip',
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

## 13. Полный эталонный рабочий модуль: Acme Telegram Notifier

Ниже представлен завершенный пример готового к установке модуля, объединяющего все ключевые возможности подсистемы.

### Файл `manifest.json`

```json
{
  "name": "acme.telegram-notifier",
  "version": "1.0.0",
  "vendor": "acme",
  "author": "Anton Barinov",
  "author_url": "https://github.com/Anton-Barinov",
  "title": "Telegram Notifier",
  "description": "Оповещения в Telegram-чаты при событиях задач TropaTT CRM",
  "category": "integration",
  "license": "MIT",
  "core_version": ">=1.0.0",
  "dependencies": [],
  "require_permissions": ["tasks.read"],
  "service_provider": "Module\\Acme\\TelegramNotifier\\TelegramNotifierServiceProvider",
  "api_routes": "api/config/routes.php",
  "web_routes": "web/config/routes.php",
  "migrations": "api/migrations/",
  "config_defaults": {
    "bot_token": "",
    "chat_id": ""
  },
  "positions": {
    "task.detail.sidebar": [
      {
        "renderer": "Module\\Acme\\TelegramNotifier\\Position\\TaskSidebarPanel::render",
        "priority": 15,
        "key": "telegram_panel"
      }
    ]
  },
  "hooks": {}
}
```

### Файл `api/TelegramNotifierServiceProvider.php`

```php
<?php
declare(strict_types=1);

namespace Module\Acme\TelegramNotifier;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;

final class TelegramNotifierServiceProvider extends AbstractModuleServiceProvider
{
    public function register(Container $container): void
    {
        // Регистрация сервисов
    }

    public function boot(Container $container): void
    {
        // Логика инициализации
    }
}
```

---

*Документация актуальна для ядра TropaTT CRM релизной серии 2026 года.*
