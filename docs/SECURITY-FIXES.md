# Security Audit — Fixes Specification

> Ready for a new analysis cycle.

## Summary

| Severity | Count |
|----------|-------|
| Critical | — |
| High     | — |
| Medium   | — |
| Low      | — |
| **Total** | **0** |

## Last Cycle Summary

| # | Severity | Status | Change |
|---|----------|--------|--------|
| SEC-031 | 🟠 Medium | ✅ False positive | Token in `window.CRM.__memoryAccessToken` + HttpOnly cookie. localStorage только для locale. |
| SEC-032 | 🟡 Low | ✅ Fixed | `web/.htaccess` — `<FilesMatch "composer\.(json|lock)">` добавлен |
| SEC-033 | 🟡 Low | ✅ Already handled | `composer.json.dist` удалён из git, `blockedPatterns` обновлён. Ожидает деплой. |
| SEC-034 | 🟡 Low | ✅ Already handled | `blockedPatterns` содержит `#/storage_api/secrets/#`, `.htaccess` на Apache блокирует. |
