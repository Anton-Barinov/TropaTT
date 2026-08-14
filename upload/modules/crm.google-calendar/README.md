# crm.google-calendar

Приватная двусторонняя синхронизация Google Calendar с календарём TropaTT CRM.

## Настройка

1. В Google Cloud Console создайте OAuth Client ID типа **Web application** и включите Google Calendar API.
2. Добавьте в `.env`:

```dotenv
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=https://crm.example.com/api/index.php?route=api/v1/modules/crm.google-calendar/oauth/callback
```

`GOOGLE_REDIRECT_URI` должен быть заранее заданным HTTPS URL callback, а не значением из запроса пользователя. `APP_SECRET` обязателен: refresh/access tokens хранятся только зашифрованными.

Модуль запрашивает только `calendar.readonly` и `calendar.events`, использует authorization-code flow с offline access и cron-инкрементальную синхронизацию. Push `watch` намеренно выключен по умолчанию: на shared-hosting нет гарантии публичного HTTPS webhook и продления каналов. Cron является обязательным рабочим fallback.

## Приватность

Подключение принадлежит текущему CRM-пользователю. API не предоставляет администратору список чужих connections, tokens или calendars. Синхронизированные записи получают `source_type=google_calendar` и `source_owner_user_id`; стандартный календарь TropaTT фильтрует такие записи даже для root-пользователя, если он не владелец.

## Синхронизация

Первый запуск загружает список календарей и все доступные события. Далее используется `nextSyncToken`, пагинация и `showDeleted=true`. При `410 Gone` токен удаляется и выполняется полная синхронизация. Удаления Google применяются к локальным событиям. При двустороннем режиме Google является авторитетным при одновременном конфликте; локальные изменения отправляются обратно только после pull.

## Ограничения

- Участники, напоминания, цвета и ACL не копируются в CRM: внутренний календарь не имеет эквивалентной модели.
- Повторения сохраняются как событие с recurrence metadata в mapping; экземпляры не размножаются при `singleEvents=false`.
- Фоновые jobs работают через зарегистрированный cron `sync_google_calendars` каждые 15 минут.
- Для OAuth Cloud проекта в режиме Testing Google может выдать refresh token с ограниченным сроком; приложение должно быть опубликовано или пользователь должен повторно авторизоваться.

Официальные источники: [OAuth web server flow](https://developers.google.com/identity/protocols/oauth2/web-server), [incremental sync](https://developers.google.com/workspace/calendar/api/guides/sync), [push notifications](https://developers.google.com/workspace/calendar/api/guides/push), [events](https://developers.google.com/calendar/api/v3/reference/events).
