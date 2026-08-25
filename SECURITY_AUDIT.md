# Security Audit Log

> Internal audit findings and their dispositions. This file is not published
> in release packages.

---

## Finding C-1: No module code isolation (sandbox)

**Date found:** 2026-08-25 (initial audit, pass #2)  
**Severity:** ~~CRITICAL~~ → **ACCEPTED RISK** (2026-08-25)  
**Finding:** ~15 classes promising sandbox/validation/isolation for module
code (`ModuleSandbox`, `ModuleFilesystemSandbox`, `ModuleNetworkSandbox`,
`ModuleTenantDataIsolator`, `ModuleProfiler`, `ModuleResourceLimits`,
`ModuleTableValidator`, `ModuleCircuitBreaker`, `ModuleBulkhead`,
`ModuleRateLimiter`, `ModuleWatchDog`, `ModuleSizeLimits`, `ModuleCanaryRelease`,
`ModuleBlueGreenDeploy`, `ModulePointInTimeRecovery`, `ModuleDatabasePool`,
`ModuleDependencyGraphGenerator`) were registered in the container but **never
invoked at runtime**. Module code runs in the same process as core with
full filesystem, database, and network access.

**Disposition:** Owner accepted the risk. Module code is trust-level core.
The stub classes that were never invoked have been removed. The remaining
barriers are documented in `MODULE_DEVELOPMENT.md` §9 and `SECURITY.md`:

1. Root-only install gate (`routes.php` requires admin).
2. `MODULE_SIGNING_KEY` — fail-closed: missing or mismatched key → install refused.
3. `ModuleCodeValidator` — scans remote packages for dangerous constructs before
   files are written to disk (`ModuleRemoteInstaller`).
4. `upload/modules/.htaccess` — denies direct web access to module PHP files.
5. Event handler isolation — a throwing handler does not crash the core request.

**References:**
- `MODULE_DEVELOPMENT.md` §9 — Trust model and module security
- `SECURITY.md` — Security-Sensitive Areas
- `upload/api/system/library/app.php` — C-1 comment (line ~1816)

---

## Remaining open findings

- **H-1 … H-7, C-2:** Closed in commit `c324c434` (2026-08-25). See audit pass #2.
- **C-3 (push rendering on real devices):** Not yet verified live. See `TZ_ostatki_2026-08.md` block C-3p.