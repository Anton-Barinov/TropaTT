# TropaTT CRM — Документация

## Архитектура

TropaTT — self-hosted PHP 8.1+ / MySQL CRM с MVC-архитектурой.

### Стек
- **PHP 8.1+** (базовый)
- **MySQL** (основная БД)
- **PDO** (через QueryBuilder)
- **Файловые сессии и кэш** (без Redis/Memcached)
- **PSR-4 автозагрузчик**

### Структура проекта
- `api/` — API приложение (контроллеры, модели, сервисы, конфигурация)
- `web/` — Web приложение (браузерный installer, страницы, шаблоны)
- `modules/` — модули расширения
- `web/install.php` — публичный установщик
- `web/docs/` — документация (этот файл)

### Точки входа
- API запросы: `api/index.php`
- Web страницы: `web/index.php`
- Cron задачи: `web/cron.php`
- AI Cron: `api/scripts/ai_cron.php`
- Установка: `web/install.php`

---

## Безопасность

### Аутентификация
- Bearer token OR Cookie + CSRF
- Сессии с device fingerprint верификацией
- 2FA через TOTP + backup codes

### CSRF защита
- Все state-changing запросы проверяются через CSRF токены
- 62 проверки CSRF/token в коде

### Rate Limiting
- 115+ референсов RateLimiter/throttle
- DatabaseRateLimiter для shared hosting совместимости

### Данные
- Пароли: bcrypt хэши
- AI ключи: AES-256-GCM шифрование
- API ключи в `.env`, не в коде
- Stack traces НЕ возвращаются в API ответах (исправлено в iters 150-151)

### SQL безопасность
- Все запросы через PDO prepared statements
- Нет конкатенации пользовательского ввода в SQL
- `SELECT * FROM users` запрещён (AGENTS.md)

---

## API

### Формат ответов
```json
{
  "ok": true/false,
  "code": "STATUS_CODE",
  "message": "Human-readable message",
  "data": {},
  "meta": {}
}
```

### Основные эндпоинты
- `GET /api/v1/tasks` — список задач
- `POST /api/v1/tasks` — создать задачу
- `GET /api/v1/projects` — список проектов
- `GET /api/v1/tags` — список тегов
- `GET /api/v1/ai/providers` — AI провайдеры
- Полный список: `api/config/routes.php`

### Аутентификация API
```
Authorization: Bearer <token>
```
или Cookie-based с CSRF токеном.

---

## Установка

1. Загрузить файлы в `public_html`
2. Перейти на `your-domain.com/install/index.php`
3. Пройти 9-шаговый мастер установки:
   - Проверка требований (PHP 8.1+, MySQL, PDO)
   - Настройка подключения к БД
   - Выбор отрасли и ролей с правами доступа
   - Создание admin аккаунта
   - Инициализация схемы
4. Удалить папку `install/` после установки

Подробное руководство: [web/docs/install.md](install.md)

### Shared Hosting
- Работает на XAMPP, MAMP, PHP built-in server
- Не требует root/sudo
- Не требует правки конфигов сервера
- Не требует Docker/systemd
- Cron задачи через cron-job.org или системный cron

---

## AI функции

- AI провайдеры: OpenAI-совместимые, mock (для тестов)
- Интеграции: анализ идей, daily work plan, task risk scan, meeting agenda
- Feature flags: `ai.cron.*` — управление AI задачами
- Безопасность: ключи шифруются, промпты не логируются в прод

---

## Разработка

### Запуск (dev)
```bash
php -S localhost:8000 -t . web/index.php
```

### Проверка синтаксиса
```bash
find . -name "*.php" -not -path "./vendor/*" -print0 | xargs -0 -n1 php -l
```

### Генерация OpenAPI
```bash
php api/scripts/generate_openapi.php
```

---

## Версионирование и обновления

- Семантическое версионирование (MAJOR.MINOR.PATCH)
- Версия ядра хранится в `VERSION`, номер сборки — `YYYYMMDD.NNN`
- Обновление из браузера: **Администрирование → Обновления системы** (`web/index.php?route=admin-updates`)
- Сервер обновлений (`update.tropatt.com`) собирает и подписывает архивы по cron; CRM только скачивает готовый пакет, проверяет подпись, делает backup и применяет
- Core обновления через `api/system/library/update/CoreUpdateClient.php` (проверка) и `updater/` (preflight → download → apply → rollback)
- Миграции БД: `api/system/library/database/migration/`
- Откат: backup создаётся перед установкой, кнопка «Восстановить из backup» на странице обновлений

Полное описание системы обновлений: **[updates.md](updates.md)**

---

*Последнее обновление: 2026-07-31*
*Версия: v0.x*
