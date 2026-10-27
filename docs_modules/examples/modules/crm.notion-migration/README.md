# crm.notion-migration

Односторонняя миграция рабочих пространств Notion (страницы, базы данных и их строки, вложенные страницы, блоки контента и комментарии) в ядро базы знаний TropaTT.

## Настройка

1. Создайте internal integration на https://www.notion.so/my-integrations (тип **Internal integration**).
2. Скопируйте токен (начинается с `secret_`).
3. В Notion откройте нужные страницы/базы → **… → Connections** и подключите созданную интеграцию (иначе API не увидит эти объекты).
4. В TropaTT на странице модуля создайте подключение и вставьте токен.

Токен хранится только зашифрованным (`APP_SECRET`, aes-256-gcm, доменный HKDF) и не отдаётся через API. Разные пользователи могут создавать свои подключения; чужое подключение видит только владелец или пользователь с правом `module.notion-migration.manage`.

## Маппинг

- База данных Notion → раздел базы знаний (`knowledge_space`).
- Страница верхнего уровня → страница базы знаний в выбранном корневом разделе (или автоматически созданном «Notion import»).
- Вложенная страница / `child_page` → вложенная страница (`parent_id`).
- Строка базы → страница внутри раздела этой базы.
- Блоки → безопасный HTML (`paragraph`, `heading_1/2/3`, списки, `to_do`, `toggle`, `quote`, `callout`, `code`, `divider`, `image`, `table`, `child_page` и т.д.).
- Комментарии → комментарии базы знаний.
- Авторы → best-effort сопоставление по email/login; несопоставленные пишутся текстом.

## Ограничения

- Подписанные URL файлов/изображений Notion временные (истекают): изображения и файлы импортируются как ссылки на исходный URL; скачивание вложений не выполняется в первой версии.
- «Space» в Notion отсутствует — верхний уровень это страницы/базы, поэтому они группируются в корневой раздел TropaTT.
- Фоновые задачи выполняются cron-обработчиком `process_notion_imports` (каждые 5 минут), без длинных HTTP-запросов.

Официальные источники: [Notion API](https://developers.notion.com/reference/intro), [Blocks](https://developers.notion.com/reference/block), [Search](https://developers.notion.com/reference/post-search), [Comments](https://developers.notion.com/reference/retrieve-a-comment).
