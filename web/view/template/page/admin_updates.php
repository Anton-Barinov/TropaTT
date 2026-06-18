<?php declare(strict_types=1); ?>
<?php $title = $t('admin_updates.title', 'TropaTT — Обновления'); ?>
<body data-page="admin-updates" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-updates-page">
  <style>
    .crm-admin-updates-page { --update-ok:#0f8f72; --update-warn:#b7791f; --update-danger:#c2410c; --update-ink:#172033; }
    .updates-hero {
      position: relative; overflow: hidden; border: 1px solid rgba(15,143,114,.18); border-radius: 22px;
      padding: 24px; margin-bottom: 18px; background:
        radial-gradient(circle at 10% 0%, rgba(20,184,166,.18), transparent 28%),
        linear-gradient(135deg, rgba(255,255,255,.98), rgba(239,253,250,.92));
      box-shadow: 0 18px 44px rgba(15, 23, 42, .08);
    }
    .updates-hero:after { content:""; position:absolute; right:-70px; top:-90px; width:220px; height:220px; border-radius:999px; background:rgba(15,143,114,.08); }
    .updates-hero-main { position:relative; z-index:1; display:flex; justify-content:space-between; gap:18px; align-items:flex-start; flex-wrap:wrap; }
    .updates-eyebrow { margin:0 0 8px; color:var(--crm-accent-strong); font-weight:800; letter-spacing:.08em; text-transform:uppercase; font-size:.72rem; }
    .updates-title { margin:0; color:var(--update-ink); font-size:clamp(1.65rem, 2.5vw, 2.4rem); line-height:1.05; font-weight:850; }
    .updates-subtitle { max-width:760px; margin:10px 0 0; color:var(--crm-text-muted); line-height:1.55; }
    .updates-actions { display:flex; flex-wrap:wrap; gap:8px; justify-content:flex-end; max-width:560px; }
    .updates-pill-row { display:flex; flex-wrap:wrap; gap:8px; margin-top:18px; }
    .updates-pill { display:inline-flex; align-items:center; gap:8px; padding:8px 11px; border:1px solid rgba(15,143,114,.16); border-radius:999px; background:rgba(255,255,255,.72); color:var(--crm-text); font-size:.82rem; font-weight:700; }
    .updates-dot { width:9px; height:9px; border-radius:50%; background:var(--crm-text-muted); box-shadow:0 0 0 4px rgba(100,116,139,.1); }
    .updates-dot.ok { background:var(--update-ok); box-shadow:0 0 0 4px rgba(15,143,114,.12); }
    .updates-dot.warn { background:var(--update-warn); box-shadow:0 0 0 4px rgba(183,121,31,.12); }
    .updates-dot.danger { background:var(--update-danger); box-shadow:0 0 0 4px rgba(194,65,12,.12); }
    .updates-grid { display:grid; grid-template-columns:repeat(12,minmax(0,1fr)); gap:14px; }
    .updates-card { border:1px solid var(--crm-border); border-radius:18px; background:var(--crm-surface); padding:18px; box-shadow:var(--shadow-sm); min-height:100%; }
    .updates-card.span-3 { grid-column:span 3; } .updates-card.span-4 { grid-column:span 4; } .updates-card.span-5 { grid-column:span 5; } .updates-card.span-7 { grid-column:span 7; } .updates-card.span-12 { grid-column:span 12; }
    .updates-card-head { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; margin-bottom:12px; }
    .updates-card h2 { margin:0; font-size:1rem; font-weight:800; color:var(--update-ink); }
    .updates-muted { color:var(--crm-text-muted); font-size:.86rem; line-height:1.45; }
    .updates-kpi-value { margin-top:8px; font-size:1.45rem; font-weight:850; color:var(--update-ink); line-height:1.08; overflow-wrap:anywhere; }
    .updates-kpi-label { color:var(--crm-text-muted); font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
    .updates-badge { display:inline-flex; align-items:center; gap:6px; border-radius:999px; padding:5px 9px; font-size:.75rem; font-weight:800; border:1px solid var(--crm-border); background:var(--crm-surface-2); color:var(--crm-text); }
    .updates-badge.ok { color:#047857; background:#ecfdf5; border-color:#a7f3d0; }
    .updates-badge.warn { color:#92400e; background:#fffbeb; border-color:#fde68a; }
    .updates-badge.danger { color:#9a3412; background:#fff7ed; border-color:#fed7aa; }
    .updates-badge.neutral { color:#475569; background:#f8fafc; border-color:#e2e8f0; }
    .updates-stepper { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:10px; }
    .updates-step { position:relative; border:1px solid var(--crm-border); border-radius:14px; padding:12px; background:linear-gradient(180deg,#fff,#f8fafc); }
    .updates-step strong { display:block; color:var(--update-ink); font-size:.86rem; }
    .updates-step span { display:block; margin-top:4px; color:var(--crm-text-muted); font-size:.76rem; line-height:1.35; }
    .updates-step.active { border-color:rgba(15,143,114,.38); background:#ecfdf5; }
    .updates-step.done { border-color:#a7f3d0; }
    .updates-split { display:grid; grid-template-columns:minmax(0,1.1fr) minmax(320px,.9fr); gap:14px; }
    .updates-list { display:grid; gap:8px; margin:0; padding:0; list-style:none; }
    .updates-list li { display:flex; justify-content:space-between; gap:12px; padding:9px 0; border-bottom:1px solid rgba(226,232,240,.8); }
    .updates-list li:last-child { border-bottom:0; }
    .updates-list code { color:var(--update-ink); font-weight:750; }
    .updates-file-table { width:100%; border-collapse:separate; border-spacing:0; overflow:hidden; }
    .updates-file-table th, .updates-file-table td { padding:10px 9px; border-bottom:1px solid rgba(226,232,240,.85); vertical-align:top; font-size:.86rem; }
    .updates-file-table th { color:var(--crm-text-muted); font-size:.73rem; letter-spacing:.04em; text-transform:uppercase; }
    .updates-file-table td:first-child { font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; overflow-wrap:anywhere; }
    .updates-log { max-height:260px; overflow:auto; border-radius:14px; border:1px solid var(--crm-border); background:#0f172a; color:#dbeafe; padding:12px; font-size:.78rem; line-height:1.55; }
    .updates-raw { margin-top:12px; }
    .updates-raw summary { cursor:pointer; color:var(--crm-accent-strong); font-weight:800; font-size:.84rem; }
    .updates-raw pre { margin:10px 0 0; max-height:320px; overflow:auto; border-radius:14px; background:#0f172a; color:#dbeafe; padding:12px; font-size:.78rem; }
    .updates-alert { border-radius:16px; border:1px solid #fde68a; background:#fffbeb; color:#78350f; padding:14px 16px; margin:0 0 14px; line-height:1.5; }
    .updates-alert strong { color:#78350f; }
    .updates-empty { border:1px dashed var(--crm-border); border-radius:14px; padding:16px; color:var(--crm-text-muted); background:var(--crm-surface-2); }
    .updates-danger-zone { border-color:#fed7aa; background:linear-gradient(180deg,#fff7ed,#fff); }
    .updates-danger-zone h2 { color:#9a3412; }
    @media (max-width: 1120px) { .updates-card.span-3,.updates-card.span-4,.updates-card.span-5,.updates-card.span-7 { grid-column:span 6; } .updates-split { grid-template-columns:1fr; } .updates-stepper { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media (max-width: 720px) { .updates-card.span-3,.updates-card.span-4,.updates-card.span-5,.updates-card.span-7 { grid-column:span 12; } .updates-actions { justify-content:flex-start; } .updates-stepper { grid-template-columns:1fr; } }
  </style>

  <section class="updates-hero">
    <div class="updates-hero-main">
      <div>
        <p class="updates-eyebrow">Core update center</p>
        <h1 class="updates-title">Обновления ядра TropaTT</h1>
        <p class="updates-subtitle">Один экран для проверки update.crm.ru, просмотра changelog, preflight, скачивания готового архива, применения обновления и аварийного восстановления.</p>
      </div>
      <div class="updates-actions">
        <button class="btn crm-btn-secondary" type="button" data-update-action="refresh">Обновить статус</button>
        <button class="btn crm-btn-secondary" type="button" data-update-action="check">Проверить обновления</button>
        <button class="btn crm-btn-secondary" type="button" data-update-action="changes">Показать изменения</button>
        <button class="btn crm-btn-primary" type="button" data-update-action="preflight">Preflight</button>
        <button class="btn crm-btn-primary" type="button" data-update-action="download">Скачать dry-run</button>
        <button class="btn crm-btn-danger-soft" type="button" data-update-action="apply">Применить</button>
        <a class="btn crm-btn-secondary" href="/updater/rescue.php" target="_blank" rel="noopener">Recovery</a>
      </div>
    </div>
    <div class="updates-pill-row">
      <span class="updates-pill"><span id="pillCenter" class="updates-dot"></span><span id="pillCenterText">Update center: проверяем...</span></span>
      <span class="updates-pill"><span id="pillVersion" class="updates-dot"></span><span id="pillVersionText">Версия: неизвестно</span></span>
      <span class="updates-pill"><span id="pillJob" class="updates-dot"></span><span id="pillJobText">Job: нет данных</span></span>
      <span class="updates-pill"><span id="pillMaintenance" class="updates-dot"></span><span id="pillMaintenanceText">Maintenance: проверяем...</span></span>
    </div>
  </section>

  <div class="updates-alert">
    <strong>Границы обновления:</strong> обновляется только ядро (`api/**`, `web/**`, корневые файлы и docs).
    Не трогаются `modules/**`, `storage/**`, `storage_api/**`, `uploads/**`, `.env`, local config и `updater/**`.
    Архивы генерируются на `update.crm.ru` кроном заранее, CRM только скачивает готовый пакет и проверяет подписи.
  </div>

  <section class="updates-grid">
    <article class="updates-card span-3">
      <div class="updates-kpi-label">Установлено</div>
      <div id="kpiInstalled" class="updates-kpi-value">...</div>
      <p id="kpiInstalledMeta" class="updates-muted mb-0">Загрузка состояния ядра.</p>
    </article>
    <article class="updates-card span-3">
      <div class="updates-kpi-label">Доступно</div>
      <div id="kpiTarget" class="updates-kpi-value">...</div>
      <p id="kpiTargetMeta" class="updates-muted mb-0">Нажмите проверку обновлений.</p>
    </article>
    <article class="updates-card span-3">
      <div class="updates-kpi-label">Пакет</div>
      <div id="kpiPackage" class="updates-kpi-value">...</div>
      <p id="kpiPackageMeta" class="updates-muted mb-0">Full или delta после проверки.</p>
    </article>
    <article class="updates-card span-3">
      <div class="updates-kpi-label">Риск</div>
      <div id="kpiRisk" class="updates-kpi-value">...</div>
      <p id="kpiRiskMeta" class="updates-muted mb-0">Оценивается update-center.</p>
    </article>

    <article class="updates-card span-12">
      <div class="updates-card-head">
        <div>
          <h2>Пайплайн обновления</h2>
          <p class="updates-muted mb-0">Двигайтесь слева направо: статус -> проверка -> preflight -> staging -> apply.</p>
        </div>
        <span id="pipelineBadge" class="updates-badge neutral">Ожидание</span>
      </div>
      <div class="updates-stepper">
        <div id="stepStatus" class="updates-step"><strong>1. Статус</strong><span>Версия, audit, последний job</span></div>
        <div id="stepCheck" class="updates-step"><strong>2. План</strong><span>Full/delta, risk, требования</span></div>
        <div id="stepPreflight" class="updates-step"><strong>3. Preflight</strong><span>Подписи, пути, место, доступность</span></div>
        <div id="stepDownload" class="updates-step"><strong>4. Staging</strong><span>Скачивание архива и распаковка</span></div>
        <div id="stepApply" class="updates-step"><strong>5. Apply</strong><span>Backup, maintenance, healthcheck</span></div>
      </div>
    </article>

    <article class="updates-card span-7">
      <div class="updates-card-head">
        <div>
          <h2>План обновления</h2>
          <p class="updates-muted mb-0">Что будет установлено и какой пакет будет использован.</p>
        </div>
        <span id="planBadge" class="updates-badge neutral">Не проверено</span>
      </div>
      <div id="planContent" class="updates-empty">Нажмите «Проверить обновления».</div>
      <details class="updates-raw"><summary>Raw plan JSON</summary><pre id="updatesPlanRaw">{}</pre></details>
    </article>

    <article class="updates-card span-5">
      <div class="updates-card-head">
        <div>
          <h2>Последний job</h2>
          <p class="updates-muted mb-0">Состояние последней операции updater.</p>
        </div>
        <span id="jobBadge" class="updates-badge neutral">Нет job</span>
      </div>
      <div id="jobContent" class="updates-empty">История появится после preflight/download/apply.</div>
      <details class="updates-raw"><summary>Raw status JSON</summary><pre id="updatesStatusRaw">{}</pre></details>
    </article>

    <article class="updates-card span-7">
      <div class="updates-card-head">
        <div>
          <h2>Изменения</h2>
          <p class="updates-muted mb-0">Коммиты, файлы и классификация изменений между текущей и целевой сборкой.</p>
        </div>
        <span id="changesBadge" class="updates-badge neutral">Не загружено</span>
      </div>
      <div id="changesContent" class="updates-empty">Нажмите «Показать изменения».</div>
      <details class="updates-raw"><summary>Raw changes JSON</summary><pre id="updatesChangesRaw">{}</pre></details>
    </article>

    <article class="updates-card span-5">
      <div class="updates-card-head">
        <div>
          <h2>Preflight и staging</h2>
          <p class="updates-muted mb-0">Проверки безопасности перед реальным применением.</p>
        </div>
        <span id="preflightBadge" class="updates-badge neutral">Не выполнялся</span>
      </div>
      <div id="preflightContent" class="updates-empty">Запустите preflight перед скачиванием архива.</div>
      <details class="updates-raw"><summary>Raw preflight/staging JSON</summary><pre id="updatesPreflightRaw">{}</pre></details>
    </article>

    <article class="updates-card span-12 updates-danger-zone">
      <div class="updates-card-head">
        <div>
          <h2>Опасная зона: apply / rollback</h2>
          <p class="updates-muted mb-0">Реальное применение включает maintenance, backup, запись файлов и healthcheck. Rollback восстанавливает файлы из backup.</p>
        </div>
        <button class="btn crm-btn-danger-soft" type="button" data-update-action="rollback">Rollback последнего job</button>
      </div>
      <div id="applyContent" class="updates-empty">Apply станет осмысленным после успешного preflight и staging. Для применения потребуется ввести подтверждение.</div>
      <details class="updates-raw"><summary>Raw apply/rollback JSON</summary><pre id="updatesApplyRaw">{}</pre></details>
    </article>
  </section>
</main></div></div>
<script>
(function () {
  const state = {
    status: null,
    version: null,
    plan: null,
    changes: null,
    preflight: null,
    download: null,
    apply: null,
    lastJobId: null,
  };

  const $ = (id) => document.getElementById(id);
  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
  const pretty = (value) => JSON.stringify(value || {}, null, 2);
  const bytes = (value) => {
    const n = Number(value || 0);
    if (!n) return '0 Б';
    const units = ['Б', 'КБ', 'МБ', 'ГБ'];
    const i = Math.min(units.length - 1, Math.floor(Math.log(n) / Math.log(1024)));
    return `${(n / Math.pow(1024, i)).toFixed(i ? 1 : 0)} ${units[i]}`;
  };
  const badgeClass = (kind) => `updates-badge ${kind || 'neutral'}`;
  const dotClass = (kind) => `updates-dot ${kind || ''}`;

  async function api(url, options = {}) {
    const csrfToken = (window.CRM && window.CRM.api && typeof window.CRM.api.getCsrfToken === 'function')
      ? window.CRM.api.getCsrfToken()
      : decodeURIComponent((document.cookie.match(/crm_csrf_token=([^;]+)/) || [])[1] || '');
    const headers = Object.assign({'Content-Type': 'application/json'}, options.headers || {});
    if (csrfToken && !headers['X-CSRF-Token']) headers['X-CSRF-Token'] = csrfToken;
    const res = await fetch(url, Object.assign({credentials: 'same-origin', headers}, options));
    const text = await res.text();
    let json;
    try { json = JSON.parse(text); } catch (e) { json = {success: false, code: 'INVALID_JSON', message: text.slice(0, 300)}; }
    if (!res.ok && json.success !== false) json.success = false;
    return json;
  }

  function setLoading(action, loading) {
    document.querySelectorAll('[data-update-action]').forEach((btn) => {
      if (loading) {
        btn.dataset.wasDisabled = btn.disabled ? '1' : '0';
        btn.disabled = true;
      } else {
        btn.disabled = btn.dataset.wasDisabled === '1';
        delete btn.dataset.wasDisabled;
      }
    });
    const badge = $('pipelineBadge');
    badge.className = badgeClass(loading ? 'warn' : pipelineKind());
    badge.textContent = loading ? `Выполняется: ${action}` : pipelineText();
  }

  function pipelineKind() {
    const latest = state.status && state.status.latest_job;
    if (latest && latest.state === 'failed') return 'danger';
    if (state.plan && state.plan.update_available === false) return 'ok';
    if (latest && latest.state === 'applied') return 'ok';
    if (state.download) return 'warn';
    if (state.preflight) return 'warn';
    return 'neutral';
  }

  function pipelineText() {
    const latest = state.status && state.status.latest_job;
    if (state.plan && state.plan.update_available === false) return 'Обновлений нет';
    if (latest && latest.state === 'applied') return 'Применено';
    if (state.download) return 'Staging готов';
    if (state.preflight) return 'Preflight готов';
    if (state.plan) return 'План готов';
    return 'Ожидание';
  }

  function setStep(id, status) {
    const el = $(id);
    el.classList.remove('active', 'done');
    if (status) el.classList.add(status);
  }

  function renderStatus() {
    const status = state.status || {};
    const version = state.version || {};
    const installed = version.state ? version : (status.installed_core || {});
    const latest = status.latest_job || null;
    const auditExists = !!status.audit;
    const auditOk = !!(status.audit && status.audit.health_ok);
    const maintenance = !!status.maintenance;

    $('pillCenter').className = dotClass(auditOk ? 'ok' : (auditExists ? 'warn' : ''));
    $('pillCenterText').textContent = auditOk ? 'Update center: OK' : (auditExists ? 'Update center: требует проверки' : 'Update center: audit не создан');
    $('pillVersion').className = dotClass(installed.core_build ? 'ok' : 'warn');
    $('pillVersionText').textContent = installed.core_build ? `Версия: ${installed.core_build}` : 'Версия: не принята';
    $('pillJob').className = dotClass(latest && latest.state === 'failed' ? 'danger' : latest ? 'ok' : 'warn');
    $('pillJobText').textContent = latest ? `Job: ${latest.state}` : 'Job: нет данных';
    $('pillMaintenance').className = dotClass(maintenance ? 'danger' : 'ok');
    $('pillMaintenanceText').textContent = maintenance ? 'Maintenance: включён' : 'Maintenance: выключен';

    $('kpiInstalled').textContent = installed.core_build || 'unknown';
    $('kpiInstalledMeta').textContent = installed.source_sha ? `SHA ${String(installed.source_sha).slice(0, 12)}...` : 'Локальная версия ещё не принята updater.';

    $('updatesStatusRaw').textContent = pretty(status);
    $('jobBadge').className = badgeClass(latest ? (latest.state === 'failed' ? 'danger' : 'ok') : 'neutral');
    $('jobBadge').textContent = latest ? latest.state : 'Нет job';
    $('jobContent').innerHTML = latest ? list({
      'Job ID': latest.job_id || 'n/a',
      'Состояние': latest.state || 'n/a',
      'Backup': latest.backup_id || 'нет',
      'Staged files': latest.staged_file_count || 0,
      'Updated': latest.updated_at || 'n/a',
    }) : '<div class="updates-empty">История появится после первой операции.</div>';

    setStep('stepStatus', 'done');
    if (latest && latest.state === 'applied') setStep('stepApply', 'done');
    $('pipelineBadge').className = badgeClass(pipelineKind());
    $('pipelineBadge').textContent = pipelineText();
  }

  function renderPlan() {
    const plan = state.plan;
    $('updatesPlanRaw').textContent = pretty(plan);
    if (!plan) return;
    const pkg = plan.recommended_package;
    const risk = plan.summary && plan.summary.risk_level ? plan.summary.risk_level : (plan.update_available ? 'unknown' : 'нет');
    const displayTarget = plan.target_build || plan.current_build || (plan.update_available ? 'unknown' : 'latest');

    $('kpiTarget').textContent = displayTarget;
    $('kpiTargetMeta').textContent = plan.update_available ? `Доступно обновление с ${plan.current_build || 'unknown'}` : 'Текущая версия уже latest.';
    $('kpiPackage').textContent = pkg ? String(pkg.type || 'package').toUpperCase() : 'не требуется';
    $('kpiPackageMeta').textContent = pkg ? `${bytes(pkg.size_bytes)} | SHA ${String(pkg.sha256 || '').slice(0, 12)}...` : 'Пакет не требуется.';
    $('kpiRisk').textContent = risk;
    $('kpiRiskMeta').textContent = !plan.update_available ? 'Изменений нет.' : (plan.requires ? [
      plan.requires.backup ? 'backup' : null,
      plan.requires.maintenance ? 'maintenance' : null,
      plan.requires.db_migration ? 'db migration' : null,
    ].filter(Boolean).join(' + ') || 'без особых требований' : 'нет данных');

    $('planBadge').className = badgeClass(plan.update_available ? 'warn' : 'ok');
    $('planBadge').textContent = plan.update_available ? 'Есть обновление' : 'Latest';
    $('planContent').innerHTML = plan.update_available ? list({
      'Текущая сборка': plan.current_build || 'unknown',
      'Целевая сборка': displayTarget,
      'Пакет': pkg ? String(pkg.type).toUpperCase() : 'нет',
      'Размер': pkg ? bytes(pkg.size_bytes) : '0 Б',
      'Target SHA': plan.target_sha ? `${String(plan.target_sha).slice(0, 12)}...` : 'n/a',
      'Risk': risk,
    }) : '<div class="updates-empty">Обновлений нет. CRM уже на последней опубликованной сборке.</div>';

    setStep('stepCheck', 'done');
    $('pipelineBadge').className = badgeClass(pipelineKind());
    $('pipelineBadge').textContent = pipelineText();
  }

  function renderChanges() {
    const payload = state.changes;
    $('updatesChangesRaw').textContent = pretty(payload);
    if (!payload) {
      $('changesBadge').className = badgeClass('');
      $('changesBadge').textContent = 'Не загружено';
      $('changesContent').innerHTML = '<div class="updates-empty">Нажмите «Показать изменения».</div>';
      return;
    }
    if (payload.ok === false || Number(payload.status || 0) >= 400) {
      $('changesBadge').className = badgeClass('danger');
      $('changesBadge').textContent = `Ошибка ${payload.status || ''}`.trim();
      $('changesContent').innerHTML = `<div class="updates-empty">Сервер обновлений не вернул список изменений. ${payload.url ? `URL: <code>${esc(payload.url)}</code>` : ''}</div>`;
      return;
    }
    const data = payload && (payload.data || payload);
    if (!data || !data.summary) {
      const isLatest = state.plan && state.plan.update_available === false;
      $('changesBadge').className = badgeClass(isLatest ? 'ok' : '');
      $('changesBadge').textContent = isLatest ? 'Latest' : 'Нет данных';
      $('changesContent').innerHTML = `<div class="updates-empty">${isLatest ? 'CRM уже считает текущую сборку последней, изменений для установки нет.' : 'Сервер не прислал summary изменений.'}</div>`;
      return;
    }
    $('changesBadge').className = badgeClass('ok');
    $('changesBadge').textContent = `${data.summary.commits || 0} commits / ${data.summary.files || 0} files`;
    const commits = (data.commits || []).slice(0, 6).map((c) => `<li><span><strong>${esc(c.short_sha || '')}</strong> ${esc(c.title || '')}</span><span>${esc(c.committed_at || '')}</span></li>`).join('');
    const files = (data.files || []).slice(0, 12).map((f) => `<tr><td>${esc(f.path)}</td><td>${esc(f.scope)}</td><td>${esc(f.change_type)}</td><td>${f.included_in_package ? '<span class="updates-badge ok">included</span>' : '<span class="updates-badge neutral">excluded</span>'}</td></tr>`).join('');
    const messageText = Number(payload.status || 0) === 204 ? 'Целевая сборка не определена: CRM уже считает доступное состояние актуальным, поэтому изменений для установки нет.' : data.message;
    const message = messageText ? `<div class="updates-empty mb-3">${esc(messageText)}</div>` : '';
    $('changesContent').innerHTML = `
      ${message}
      <div class="updates-split">
        <div>
          <h3 class="h6">Коммиты</h3>
          <ul class="updates-list">${commits || '<li><span>Нет коммитов</span></li>'}</ul>
        </div>
        <div>
          <h3 class="h6">Файлы</h3>
          <table class="updates-file-table"><thead><tr><th>Path</th><th>Scope</th><th>Type</th><th>Package</th></tr></thead><tbody>${files || '<tr><td colspan="4">Нет файлов</td></tr>'}</tbody></table>
        </div>
      </div>`;
  }

  function renderPreflight() {
    const preflight = state.preflight;
    const download = state.download;
    $('updatesPreflightRaw').textContent = pretty({preflight, download});
    if (!preflight) return;
    const report = preflight.preflight || preflight;
    const checks = report.checks || {};
    const rows = Object.keys(checks).map((key) => `<li><span>${esc(key)}</span><span class="${checks[key] ? 'updates-badge ok' : 'updates-badge danger'}">${checks[key] ? 'OK' : 'FAIL'}</span></li>`).join('');
    const staging = download && download.data && download.data.staging ? download.data.staging : null;
    $('preflightBadge').className = badgeClass(report.ok ? 'ok' : 'danger');
    $('preflightBadge').textContent = report.ok ? 'Preflight OK' : 'Preflight failed';
    $('preflightContent').innerHTML = `
      ${list({'Job ID': state.lastJobId || 'n/a', 'Target': report.target_build || 'n/a', 'Package': report.package ? String(report.package.type).toUpperCase() : 'нет', 'Manifest files': report.manifest_report ? report.manifest_report.file_count : 'n/a'})}
      <h3 class="h6 mt-3">Проверки</h3><ul class="updates-list">${rows}</ul>
      ${staging ? `<h3 class="h6 mt-3">Staging</h3>${list({'Files': staging.file_count, 'Preview': (staging.preview || []).join(', ')})}` : ''}`;
    setStep('stepPreflight', report.ok ? 'done' : 'active');
    if (staging) setStep('stepDownload', 'done');
  }

  function renderApply() {
    $('updatesApplyRaw').textContent = pretty(state.apply);
    const apply = state.apply && (state.apply.data || state.apply);
    if (!apply) return;
    if (state.apply.success === false) {
      $('applyContent').innerHTML = `<div class="updates-empty">Ошибка: ${esc(state.apply.message || state.apply.code || 'unknown')}</div>`;
      return;
    }
    $('applyContent').innerHTML = list({
      'Job ID': apply.job_id || 'n/a',
      'Applied files': apply.applied ? apply.applied.count : 'n/a',
      'Backup': apply.backup ? apply.backup.backup_id : 'n/a',
      'Health': apply.health && apply.health.ok ? 'OK' : 'unknown',
      'Installed build': apply.installed_core ? apply.installed_core.core_build : 'n/a',
    });
    setStep('stepApply', 'done');
  }

  function list(items) {
    return `<ul class="updates-list">${Object.entries(items).map(([key, value]) => `<li><span>${esc(key)}</span><code>${esc(value)}</code></li>`).join('')}</ul>`;
  }

  async function withAction(name, fn) {
    setLoading(name, true);
    try {
      await fn();
    } catch (err) {
      $('updatesApplyRaw').textContent = pretty({error: String(err)});
    } finally {
      setLoading(name, false);
    }
  }

  async function loadStatus() {
    const [version, status] = await Promise.all([
      api('/api/index.php?route=api/v1/core/version'),
      api('/api/index.php?route=api/v1/core/updates/status')
    ]);
    state.version = version.data || version;
    state.status = status.data || status;
    if (state.status && state.status.latest_job && state.status.latest_job.job_id) state.lastJobId = state.status.latest_job.job_id;
    renderStatus();
  }

  async function check() {
    const result = await api('/api/index.php?route=api/v1/core/updates/check', {method: 'POST', body: '{}'});
    state.plan = result.data && result.data.plan ? result.data.plan : (result.data || result);
    renderPlan();
  }

  async function changes() {
    const result = await api('/api/index.php?route=api/v1/core/updates/changes');
    state.changes = result.data || result;
    renderChanges();
  }

  async function preflight() {
    const result = await api('/api/index.php?route=api/v1/core/updates/preflight', {method: 'POST', body: JSON.stringify({dry_run: true})});
    const data = result.data || result;
    state.preflight = data.preflight || data;
    state.lastJobId = data.job_id || (data.updater && data.updater.data && data.updater.data.job_id) || state.lastJobId;
    renderPreflight();
    await loadStatus();
  }

  async function download() {
    if (!state.lastJobId) throw new Error('Сначала выполните preflight, чтобы получить job_id.');
    const result = await api('/updater/index.php?action=download', {method: 'POST', body: JSON.stringify({dry_run: true, job_id: state.lastJobId})});
    state.download = result;
    renderPreflight();
    await loadStatus();
  }

  async function updaterSession() {
    const session = await api('/api/index.php?route=api/v1/core/updates/session', {method: 'POST', body: '{}'});
    const token = session && session.data && session.data.updater_token;
    if (!token) throw new Error('Не удалось получить одноразовый updater token.');
    return token;
  }

  async function applyUpdate() {
    if (!state.lastJobId) throw new Error('Нет job_id. Сначала выполните preflight и staging.');
    const confirmation = window.prompt('Для реального применения обновления введите APPLY');
    if (confirmation !== 'APPLY') return;
    const token = await updaterSession();
    const result = await api('/updater/index.php?action=apply', {method: 'POST', body: JSON.stringify({job_id: state.lastJobId, confirm_apply: true, token})});
    state.apply = result;
    renderApply();
    await loadStatus();
    await check();
  }

  async function rollback() {
    const latest = state.status && state.status.latest_job;
    const jobId = state.lastJobId || (latest && latest.job_id);
    if (!jobId) throw new Error('Нет job_id для rollback.');
    const confirmation = window.prompt('Rollback восстановит файлы из backup. Введите ROLLBACK');
    if (confirmation !== 'ROLLBACK') return;
    const token = await updaterSession();
    const result = await api('/updater/index.php?action=rollback', {method: 'POST', body: JSON.stringify({job_id: jobId, token})});
    state.apply = result;
    renderApply();
    await loadStatus();
  }

  document.addEventListener('click', (event) => {
    const btn = event.target.closest && event.target.closest('[data-update-action]');
    if (!btn) return;
    const action = btn.getAttribute('data-update-action');
    const actions = {
      refresh: () => loadStatus(),
      check,
      changes,
      preflight,
      download,
      apply: applyUpdate,
      rollback,
    };
    if (actions[action]) withAction(action, actions[action]);
  });

  withAction('initial load', async () => {
    await loadStatus();
    await check();
  });
})();
</script>
