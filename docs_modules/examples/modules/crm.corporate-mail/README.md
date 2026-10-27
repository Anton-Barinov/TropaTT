# crm.corporate-mail — Corporate Mail Integration

Mailbox credentials are encrypted with authenticated AES-256-GCM using the CRM `APP_KEY`.
Set a strong `APP_KEY` with at least 32 characters and enable PHP OpenSSL before saving or
reading mailbox credentials. The module fails closed if either requirement is missing; it never
stores credentials as base64 or plaintext.

Legacy records stored as `b64:` or unprefixed base64 are not encrypted and cannot be trusted for
automatic migration. After configuring `APP_KEY`, create a replacement mailbox with freshly
entered credentials, verify it, then remove the old record. Existing `gcm:` records remain readable
when they were encrypted with the currently configured `APP_KEY`; records written with the former
built-in fallback key must also be replaced this way.

Модуль настройки корпоративных почтовых ящиков для TropaTT CRM. Доступны ограниченная синхронизация IMAP и отправка через SMTP по защищённому каналу.

---

## Возможности

- **Управление почтовыми ящиками команды**: подключение корпоративных ящиков по протоколам IMAP/SMTP (Яндекс, Mail.ru, Google Workspace, Exchange/MS365, корпоративные почтовые серверы Postfix/Exim).
- **Проверка защищённого соединения**: проверка доступности TLS-канала и сертификата IMAP (993/143) и SMTP (587/465/25). Эта проверка не подтверждает авторизацию логина и пароля.
- **Интеграция с `.env`**: автоматическое обнаружение системных SMTP/IMAP настроек хостинга (`SMTP_HOST`, `MAIL_HOST`, `IMAP_HOST` и др.) с возможностью импорта в 1 клик.
- **Поддержка вкладки "Конфигурация"**: параметры по умолчанию (`config_defaults` в манифесте) доступны прямо в админке модулей CRM (`admin-module-detail`).
- **Входящие и ответы**: после синхронизации откройте переписки нужного ящика, просмотрите письма в хронологическом порядке и ответьте через его SMTP. Текст письма выводится как текст с экранированием; повторная отправка использует исходный ключ идемпотентности, а при неизвестном результате интерфейс предлагает проверить статус.
- **Безопасная синхронизация**: модуль закрепляет проверенный публичный IP, сохраняет проверку TLS-сертификата по исходному hostname и обрабатывает до 5 писем за запрос, ограничивает окно поиска 1000 UID и сохраняет ошибки UID для повтора; одна синхронизация выполняется на ящик за раз.
- **Надёжная SMTP-отправка**: SMTP/IMAP работают через протокольный клиент PHP cURL; тело письма передаётся в quoted-printable, поэтому не требует расширения SMTP `8BITMIME`.
- **Шифрование паролей**: безопасное хранение реквизитов подключения через AES-256-GCM.
- **Изоляция по организациям**: операции с ящиками и письмами ограничены `organization_id`. Чтение переписок требует отдельного разрешения `corporate_mail.read`; управление ящиками и отправка писем дополнительно требуют `settings.edit`.

> OAuth-подключение Gmail/Microsoft 365 не входит в этот вариант. Новые входящие письма создают записи во «Входящих», если таблица `intake_items` доступна.

---

## Настройка и использование

### Вариант 1: Через веб-интерфейс модуля (Рекомендуемый)
1. Откройте CRM и перейдите в раздел **Администрирование → Модули → Corporate Mail Integration**.
2. Нажмите кнопку **«Открыть страницу модуля»** (или выберите в левом меню пункт **«Корпоративная почта»**).
3. На странице модуля:
   - Если в файле `.env` уже прописан SMTP-сервер, появится плашка с предложением **«Импортировать из .env»**.
   - Чтобы добавить новый ящик вручную, нажмите **«Добавить ящик»**.
   - Укажите Email, имя ящика, хосты и порты IMAP/SMTP и пароли (для Яндекса, Mail.ru и Gmail используйте **Пароль приложения**).
   - Нажмите **«Проверить соединение»**, чтобы убедиться, что сервер доступен по TLS с проверкой сертификата. Эта операция не проверяет логин/пароль.
   - Нажмите **«Сохранить»**. Статус модуля автоматически изменится на **«Подключён · Активен»**.

После подключения используйте **«Синхронизировать»** в строке ящика, а затем **«Входящие»**. В панели переписок выберите цепочку, прочитайте её письма и отправьте ответ. Ответ привязывается к исходной цепочке через `In-Reply-To`. При неизвестном сетевом результате ключ попытки сохраняется в `sessionStorage` текущей вкладки (тело письма и адрес не сохраняются) и переживает перезагрузку страницы. Сначала проверьте статус той же попытки. Если браузерное хранилище недоступно, отправка блокируется до безопасного восстановления; если сервер подтвердил `failed`, письмо точно не было принято и его можно отправить заново.

### Вариант 2: Через системную конфигурацию модуля
В разделе **Администрирование → Модули → Corporate Mail Integration → Конфигурация** доступны глобальные параметры:
- `use_env_smtp` (bool): использовать ли параметры SMTP из `.env` по умолчанию.
- `default_mailbox_name` (string): стандартное название почтового ящика поддержки.
- `smtp_host`, `smtp_port`, `smtp_username`, `smtp_password`, `smtp_encryption`: параметры исходящей почты по умолчанию.
- `imap_host`, `imap_port`, `imap_username`, `imap_password`, `imap_encryption`: параметры входящей почты по умолчанию.
- `sync_interval_minutes` (number): интервал опроса новых сообщений (в минутах).

### Вариант 3: Переменные окружения (.env)
Модуль автоматически считывает следующие переменные:
```dotenv
SMTP_HOST=smtp.yandex.ru
SMTP_PORT=587
SMTP_USERNAME=support@company.com
SMTP_PASSWORD=app_specific_password_here
SMTP_ENCRYPTION=tls
SMTP_FROM=support@company.com

IMAP_HOST=imap.yandex.ru
IMAP_PORT=993
IMAP_USERNAME=support@company.com
IMAP_PASSWORD=app_specific_password_here
IMAP_ENCRYPTION=ssl
```

---

## API эндпоинты

Все API эндпоинты модуля монтируются с префиксом `_module/crm.corporate-mail/`:

| Метод | Путь | Описание |
|---|---|---|
| `GET` | `/_module/crm.corporate-mail/status` | Статус модуля, количество ящиков и готовность `.env` |
| `GET` | `/_module/crm.corporate-mail/mailboxes` | Список настроенных почтовых ящиков |
| `GET` | `/_module/crm.corporate-mail/mailboxes/{public_id}` | Детали ящика (пароли маскируются) |
| `POST` | `/_module/crm.corporate-mail/mailboxes` | Создание или обновление почтового ящика |
| `DELETE` | `/_module/crm.corporate-mail/mailboxes/{public_id}` | Удаление почтового ящика |
| `POST` | `/_module/crm.corporate-mail/mailboxes/{public_id}/test` | Проверка связи для сохранённого ящика |
| `GET` | `/_module/crm.corporate-mail/mailboxes/{public_id}/sync-failures` | Ошибки отдельных UID в текущем workspace и UIDVALIDITY |
| `POST` | `/_module/crm.corporate-mail/mailboxes/{public_id}/sync-failures/retry` | Повторить обработку UID из текущего failure ledger (`uid`, `uidvalidity`) |
| `POST` | `/_module/crm.corporate-mail/mailboxes/{public_id}/sync` | Запуск синхронизации писем |
| `GET` | `/_module/crm.corporate-mail/mailboxes/{public_id}/threads` | Последние переписки ящика в активном workspace |
| `GET` | `/_module/crm.corporate-mail/mailboxes/{public_id}/messages?thread_id=...` | Сообщения выбранной переписки, ограниченный plain-text результат |
| `POST` | `/_module/crm.corporate-mail/mailboxes/{public_id}/send` | Отправка письма через SMTP |
| `GET` | `/_module/crm.corporate-mail/mailboxes/{public_id}/send-status?idempotency_key=...` | Проверка статуса отправки по исходному ключу |
| `POST` | `/_module/crm.corporate-mail/test-connection` | Экспресс-тест соединения с серверами |
| `POST` | `/_module/crm.corporate-mail/import-env` | Быстрый импорт ящика из параметров `.env` |

---

## Диагностика и устранение неполадок

1. **Ошибка «Connection timed out» при проверке связи:**
   - Убедитесь, что хостинг не блокирует исходящие порты 587, 465, 993 на уровне файрвола (iptables/ufw).
2. **Ошибка «Authentication failed» при верном пароле:**
   - Публичные почтовые службы (Google, Yandex, Mail.ru) запрещают вход по основному паролю аккаунта. Требуется сгенерировать **Пароль приложения** в настройках безопасности почтового сервиса.
3. **Статус «Установлен · не подключён»:**
   - Нажмите «Добавить ящик» и подключите хотя бы один активный почтовый ящик, либо импортируйте параметры из `.env`.

При отправке письма передавайте случайный стабильный `idempotency_key` (16–128 ASCII-символов) в JSON или заголовке `X-TropaTT-Idempotency-Key`; повтор того же запроса с тем же ключом не отправит письмо повторно. Один ключ нельзя переиспользовать с другим содержимым. Если провайдер мог принять DATA, но CRM не получила подтверждение, запрос получает `MAIL_DELIVERY_UNCERTAIN`; проверяйте `GET /_module/crm.corporate-mail/mailboxes/{public_id}/send-status?idempotency_key=...` с исходным ключом. Не создавайте новый ключ для повтора такой отправки. Ошибка до передачи DATA помечается как `failed` и безопасно допускает повтор с тем же ключом.

Синхронизация запускается кнопкой или API-вызовом и ограничена 50 новыми письмами за запуск; планировщик shared-hosting может вызывать endpoint с нужным интервалом. Поддерживаются IMAP INBOX, дедупликация по `Message-ID`, связывание по `In-Reply-To` и SMTP SSL/STARTTLS с `AUTH PLAIN`. Вложения и multipart-разбор намеренно не входят в этот вертикальный срез.

Для исходящей почты и IMAP требуется включённое в PHP расширение cURL, собранное с поддержкой протоколов `smtp`, `smtps`, `imap` и `imaps`. На shared-хостинге проверьте список `curl_version()['protocols']`; модуль при отсутствии любого нужного протокола завершает операцию безопасной ошибкой и не переключается на сырой TCP-сокет. STARTTLS обязателен для режимов `tls`/`starttls`, проверка цепочки и имени TLS-сертификата всегда включена. Подключение закрепляется за IP-адресом, который прошёл проверку на публичную маршрутизацию.

SMTP/IMAP транспорт переведён на протокольный клиент libcurl в версии 1.0.14. SMTP payload передаётся через upload callback после принятия `DATA`; если результат доставки становится неопределённым после начала передачи, повтор блокируется существующим idempotency-механизмом.


### Обработка IMAP-партий и проблемных UID

Каждый запуск синхронизации ограничен пятью письмами и окном поиска в 1000 UID. Для shared hosting интерфейс запускает одну короткую партию за запрос; если API сообщает `has_more`, нажмите синхронизацию ещё раз. Одновременные запуски одного ящика блокируются lease-записью в БД. Просроченная lease переводит ящик в состояние ошибки и позволяет безопасно повторить синхронизацию.

Ошибка чтения или разбора одного письма сохраняется вместе с `UIDVALIDITY` и UID. После потенциально неполного IMAP literal соединение закрывается и не переиспользуется; следующие UID загружаются отдельной партией. Кнопка «UID» показывает код, число попыток и позволяет подключиться заново и повторить UID. Повтор доступен только пока UIDVALIDITY ящика совпадает с записью ошибки. Ошибки старой UIDVALIDITY остаются в журнале БД, но больше не предлагаются для повторного чтения.
