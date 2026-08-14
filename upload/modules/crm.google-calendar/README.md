# crm.google-calendar

Приватная двусторонняя синхронизация Google Calendar с календарём TropaTT CRM.

## Настройка

1. В Google Cloud Console создайте OAuth Client ID типа **Web application** и включите Google Calendar API.
2. Добавьте в `.env`:

```dotenv
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
# Необязательно: точный HTTPS callback для reverse-proxy или нестандартного URL.
# Если параметр не задан, модуль строит URI из текущего адреса установленной CRM:
# <ваш-домен>/api/index.php?route=/_module/crm.google-calendar/oauth/callback
# GOOGLE_REDIRECT_URI=
# Необязательно: канонический публичный URL установки, если CRM стоит за proxy.
# CRM_PUBLIC_URL=https://ваш-фактический-домен[/подпапка]
```

В Google Cloud Console зарегистрируйте фактический HTTPS callback вашей установки. Домен не зашит в коде: по умолчанию он берётся из текущего запроса и `SCRIPT_NAME`, поэтому работают домен, поддомен и установка в подпапке. `GOOGLE_REDIRECT_URI` нужен только если внешний reverse-proxy публикует CRM по другому каноническому адресу; альтернативно можно задать `CRM_PUBLIC_URL` без фиксированного домена в коде. `APP_SECRET` обязателен: refresh/access tokens хранятся только зашифрованными.

Модуль запрашивает только календарные права (`calendar.readonly`, `calendar.events`) и стандартный scope `openid email` для отображения и проверки Google-аккаунта, использует authorization-code flow с offline access и cron-инкрементальную синхронизацию. После подключения все календари видны владельцу, но синхронизируются только отмеченные включённые календари; направление можно менять отдельно для каждого. Push `watch` намеренно выключен по умолчанию: на shared-hosting нет гарантии публичного HTTPS webhook и продления каналов. Cron является обязательным рабочим fallback.

## Приватность

Подключение принадлежит текущему CRM-пользователю. API не предоставляет администратору список чужих connections, tokens или calendars. Синхронизированные записи получают `source_type=google_calendar` и `source_owner_user_id`; стандартный календарь TropaTT фильтрует такие записи даже для root-пользователя, если он не владелец.

## Синхронизация

Первый запуск загружает список календарей и все доступные события. Далее используется `nextSyncToken`, пагинация и `showDeleted=true`. При `410 Gone` токен удаляется и выполняется ограниченная одной попыткой полная синхронизация. Удаления Google применяются к локальным событиям. При двустороннем режиме Google является авторитетным при одновременном конфликте; локальные изменения отправляются обратно только после pull и с `If-Match` по сохранённому `etag`. CRM→Google использует `extendedProperties.private.tropatt_event_public_id` для восстановления после сбоя записи mapping и не создаёт второе событие при повторе.

## Ограничения

- Участники, напоминания, цвета и ACL не копируются в CRM: внутренний календарь не имеет эквивалентной модели.
- Повторения запрашиваются с `singleEvents=true`: в CRM сохраняются отдельные экземпляры, а в mapping сохраняются `recurring_event_id`. Правило повторения и исключения не редактируются из CRM.
- Фоновые jobs работают через зарегистрированный cron `sync_google_calendars` каждые 15 минут. Для защиты от параллельного cron/ручного запуска используется эксклюзивный lock на подключение; второй запуск получает `409 GOOGLE_SYNC_IN_PROGRESS`.
- Для OAuth Cloud проекта в режиме Testing Google может выдать refresh token с ограниченным сроком; приложение должно быть опубликовано или пользователь должен повторно авторизоваться. При повторной авторизации сначала полностью проверяется список календарей; при ошибке старое подключение восстанавливается.
- Cron приостанавливает синхронизацию неактивных пользователей; полная очистка подключения, локальных событий и зашифрованных токенов выполняется только после удаления пользователя из CRM или отсутствия его аккаунта.

Официальные источники: [OAuth web server flow](https://developers.google.com/identity/protocols/oauth2/web-server), [incremental sync](https://developers.google.com/workspace/calendar/api/guides/sync), [push notifications](https://developers.google.com/workspace/calendar/api/guides/push), [events](https://developers.google.com/calendar/api/v3/reference/events).
