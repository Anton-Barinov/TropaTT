# Интеграция TropaTT CRM с Zoom (Meetings, Recordings, Transcripts)

Комплексный модуль интеграции TropaTT CRM с платформой видеоконференций **Zoom**.

---

## Ключевой функционал

1. **Планирование встреч и мгновенная генерация Zoom Meetings**:
   - Создание онлайн-встреч напрямую из карточки сделки, задачи или календаря CRM.
   - Автоматическая генерация уникальных защищенных ссылок подключения (`join_url` для участников, `start_url` для организатора, passcode).
   - Встреча мгновенно отображается в календаре TropaTT CRM с кнопкой быстрого подключения.
2. **Облачные записи (Cloud Recordings) и архив в CRM**:
   - При завершении созвона модуль автоматически подтягивает ссылки на видеозаписи, аудиодорожки и расшифровку в карточку соответствующей сделки или задачи.
3. **Учет участников и посещаемости**:
   - Фиксация фактического времени подключения участников к встрече через вебхуки Zoom (`meeting.participant_joined`, `meeting.participant_left`).
4. **AI-агенты и MCP Mega-tools**:
   - `zoom_status` — проверка подключения OAuth/Server-to-Server токена.
   - `zoom_create_meeting` — планирование звонка AI-агентом по голосовому или текстовому запросу с генерацией ссылки и добавлением в календарь.

---

## Архитектура таблиц

- `module_zoom_settings` — параметры авторизации (Account ID, Client ID, Client Secret, Server-to-Server OAuth).
- `module_zoom_meetings` — реестр онлайн-созвонов с `zoom_meeting_id`, `join_url`, `start_url`, `passcode`, привязкой к `calendar_events`, `projects`, `tasks`, `clients`.
- `module_zoom_recordings` — облачные записи встреч (MP4, M4A, VTT субтитры/транскрипты).

---

## API Эндпоинты

- `GET /api/v1/zoom/status` — проверка подключения.
- `POST /api/v1/zoom/meetings` — создание встречи Zoom с записью в календарь CRM.
- `GET /api/v1/zoom/meetings` — список запланированных и прошедших встреч.
- `POST /api/v1/zoom/webhooks` — обработка событий Zoom (завершение встречи, готовность записи).

---

## Автор
Anton Barinov (https://github.com/Anton-Barinov)
Лицензия: MIT
