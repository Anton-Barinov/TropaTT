# crm.bitrix24-migration

Модуль односторонней миграции из облачного или коробочного Битрикс24 в TropaTT.

## Поддержанные способы подключения

- **Incoming webhook** — хранится только в шифрованном виде; URL ограничивается HTTPS и доменом выбранного портала.
- **OAuth 2.0** — access/refresh token и client credentials шифруются через `APP_SECRET`. При `expired_token` модуль обновляет токен через `oauth.bitrix.info` и сохраняет новую пару.

Права Bitrix24 приложения и права пользователя вебхука независимы. Перед импортом нужно выдать минимальные scopes: `user`, `department`, `crm`, `tasks`, `sonet_group`, `calendar`, `disk` и `lists` только если они нужны выбранному набору данных.

## Порядок работы

1. Создать подключение и выполнить `test` (`user.current`).
2. Выполнить discovery и вручную сопоставить Bitrix24 users с активными пользователями CRM.
3. Создать job с выбранными сущностями и включить нужные опции комментариев, файлов и товарных строк.
4. Запустить job. Worker читает страницы API, сохраняет исходный payload в `job_items`, затем импортирует записи по приоритетам: подразделения/users → компании/контакты/leads → проекты → сделки/задачи → комментарии/активности → файлы и товарные строки.
5. Проверить report/unresolved. Failed items можно retry; rollback удаляет только объекты, созданные этой job.

## Маппинг

| Bitrix24 | TropaTT | Политика |
|---|---|---|
| `department.get` | `department` / teams | создаётся подразделение; участники не создаются автоматически |
| `user.get` | user mapping | пользователь CRM не создаётся; исполнитель остаётся пустым до ручного сопоставления |
| `crm.company` | company/counterparty | title, email, phone, INN и базовые UF-поля |
| `crm.contact` | contact | имя, email, phone, должность, связь с компанией |
| `crm.lead` | counterparty person | в CRM нет нативного lead, исходный ID/status сохраняются в `extra_attributes` |
| `sonet_group` | project | название, описание, архивный статус |
| `tasks.task` | task/subtask | hierarchy, status, priority, dates, responsible, tags, raw payload |
| `crm.deal` | task в служебном проекте | в текущем core нет DealService; сумма, валюта, стадия и payload сохраняются в описании/исходном JSON |
| `crm.activity` / `calendar.event` | calendar event | только записи с корректным диапазоном дат |
| `crm.timeline.comment` / task comments | task comment или task | комментарий привязывается к task; для company/contact/lead без native comment target создаётся служебная задача с исходным текстом |
| `disk.file` / comment FILES | task file | HTTPS URL портала/Bitrix24 CDN; контролируемый redirect на публичное object storage, максимум 20 MiB; файл без связи получает отдельную import-task |
| `crm.invoice`, `crm.quote`, `crm.product` | task в служебном проекте | native сущности отсутствуют в core; исходные поля и payload сохраняются в описании/исходном JSON |
| product rows | comment к задаче invoice/quote/deal | количество и цена сохраняются как комментарий; если родитель не импортирован — unresolved |

Поля `UF_*`, для которых нет точного аналога, добавляются в описание безопасным текстовым представлением. Секреты, access tokens и URL вебхука не попадают в API ответы и логи.

## Идемпотентность и большие объёмы

- Уникальность: `connection_id + source_type + source_id`.
- Повторный crawl не сбрасывает успешно импортированный item при неизменном checksum.
- Job хранит cursor и lease; worker повторяет только незавершённый пакет после падения.
- REST list pagination использует `next`/`total`, ограничение одной коллекции — 10 000 записей для защиты shared-hosting.
- Для Bitrix24 учитываются 503/429, `QUERY_LIMIT_EXCEEDED`, `OPERATION_TIME_LIMIT` и `Retry-After` с exponential backoff. Batch ограничен 50 командами; файловые запросы в batch не используются. Повторные попытки считают отдельный вызов, а не общий счётчик запросов соединения.

## Ограничения

- Нативных Deal/Invoice/Quote/Product/Smart-process сервисов в текущем TropaTT core нет. Поэтому deal/invoice/quote/product представлены задачами служебного проекта, а product rows — комментариями к соответствующей задаче.
- Удалённые записи Bitrix24, корзина, сложные множественные UF-поля и все типы CRM activities не могут быть достоверно восстановлены без соответствующего scope/API метода; они получают warning/unresolved. Для календарных событий исходный owner ID сохраняется в описании, но текущий CalendarService назначает владельцем пользователя, запустившего импорт.
- Автоматическое создание CRM users и выдача прав намеренно запрещены.
- Для коробки администратор должен обеспечить публичный HTTPS DNS и совместимость методов REST; старые версии Bitrix24 могут не поддерживать часть методов. Ссылки на файлы принимаются только от Bitrix24/портала, а redirects допускаются лишь на HTTPS-хосты с публичными IP; приватные адреса и DNS-rebinding блокируются.

## Проверка

```bash
find upload/modules/crm.bitrix24-migration -name '*.php' -print0 | xargs -0 -n1 php -l
node --check upload/modules/crm.bitrix24-migration/web/assets/js/bitrix24-migration.js
php upload/api/scripts/module.php check crm.bitrix24-migration
```
