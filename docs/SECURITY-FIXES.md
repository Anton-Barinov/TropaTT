# Security Audit — Fixes Specification

> Ready for new analysis. Clear for next audit cycle.

---

## Неисправленные находки (требуют nginx config)

| SEC | Severity | Проблема | Статус |
|-----|----------|----------|--------|
| SEC-001 | Low | composer.json HTTP 200 | ⚠️ Не исправлено |
| SEC-002 | Low | Demo API HTTP 302 | ⚠️ Не исправлено |

### Что сделано
- Проверено: обе проблемы воспроизводятся на демо
- `api/.htaccess` уже блокирует `.json` и `.lock` файлы (работает на Apache)
- На демо стоит nginx — `.htaccess` игнорируется
- PHP-код не может повлиять на nginx routing (nginx отдаёт статику до вызова PHP)

### Что не получилось
- Не удалось настроить nginx — нет доступа к конфигурации сервера
- Проблемы не могут быть исправлены на уровне PHP-кода

### Решение
Применить на сервере:
```nginx
# Блокировка composer.json/composer.lock
location ~* ^/(api/)?(composer\.(json|lock)|\.\..*)$ { deny all; }

# Маршрутизация API через PHP
location /api/ { try_files $uri /api/index.php?$query_string; }
```
