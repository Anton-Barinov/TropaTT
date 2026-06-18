<?php declare(strict_types=1); ?>
<?php $title = $t('admin_updates.title', 'TropaTT — Обновления'); ?>
<body data-page="admin-updates" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-admin-updates-page">
	  <style>
	    .crm-admin-updates-page {
	      --update-ok:#0f766e; --update-warn:#b45309; --update-danger:#b42318; --update-ink:#101828;
	      --update-soft:#f6f8fb; --update-line:#e4e7ec; --update-blue:#175cd3;
	      background:
	        radial-gradient(circle at 82% 0%, rgba(23,92,211,.08), transparent 30%),
	        linear-gradient(180deg, #f8fafc 0%, #ffffff 440px);
	    }
	    .updates-shell { max-width:1280px; margin:0 auto; }
	    .updates-hero { display:grid; grid-template-columns:minmax(0,1.25fr) minmax(320px,.75fr); gap:18px; margin-bottom:18px; }
	    .updates-hero-panel, .updates-card, .updates-next {
	      border:1px solid var(--update-line); border-radius:24px; background:rgba(255,255,255,.94);
	      box-shadow:0 18px 44px rgba(16,24,40,.07);
	    }
	    .updates-hero-panel { padding:28px; overflow:hidden; position:relative; }
	    .updates-hero-panel:after { content:""; position:absolute; right:-72px; bottom:-92px; width:260px; height:260px; border-radius:999px; background:rgba(15,118,110,.08); }
	    .updates-kicker { display:inline-flex; align-items:center; gap:8px; margin-bottom:14px; color:#344054; font-weight:800; font-size:.82rem; }
	    .updates-kicker:before { content:""; width:10px; height:10px; border-radius:999px; background:var(--update-ok); box-shadow:0 0 0 5px rgba(15,118,110,.12); }
	    .updates-title { margin:0; color:var(--update-ink); font-size:clamp(2rem, 3.2vw, 3.45rem); line-height:.98; letter-spacing:-.045em; font-weight:900; max-width:760px; }
	    .updates-subtitle { max-width:690px; margin:16px 0 0; color:#475467; line-height:1.6; font-size:1rem; }
	    .updates-actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:22px; }
	    .updates-actions .btn { border-radius:999px; padding:.62rem 1rem; font-weight:800; }
	    .updates-next { padding:22px; display:flex; flex-direction:column; justify-content:space-between; gap:18px; }
	    .updates-next-label { color:#667085; font-weight:800; font-size:.78rem; text-transform:uppercase; letter-spacing:.08em; }
	    .updates-next-title { margin:7px 0 8px; color:var(--update-ink); font-size:1.45rem; line-height:1.1; font-weight:900; letter-spacing:-.02em; }
	    .updates-next-text { margin:0; color:#475467; line-height:1.55; }
	    .updates-next-foot { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
	    .updates-notice { display:none; margin:0 0 16px; border-radius:18px; padding:14px 16px; border:1px solid #fecaca; background:#fff1f2; color:#9f1239; font-weight:700; }
	    .updates-notice.show { display:block; }
	    .updates-trust { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin-bottom:16px; }
	    .updates-metric { border:1px solid var(--update-line); border-radius:18px; background:#fff; padding:16px; min-height:132px; }
	    .updates-kpi-label { color:#667085; font-size:.78rem; font-weight:800; text-transform:uppercase; letter-spacing:.06em; }
	    .updates-kpi-value { margin-top:10px; font-size:1.55rem; font-weight:900; color:var(--update-ink); line-height:1.05; overflow-wrap:anywhere; }
	    .updates-muted { color:#667085; font-size:.9rem; line-height:1.5; }
	    .updates-pill-row { display:flex; flex-wrap:wrap; gap:8px; margin-top:20px; position:relative; z-index:1; }
	    .updates-pill { display:inline-flex; align-items:center; gap:8px; padding:8px 11px; border:1px solid #d0d5dd; border-radius:999px; background:#fff; color:#344054; font-size:.82rem; font-weight:750; }
	    .updates-dot { width:9px; height:9px; border-radius:50%; background:#98a2b3; box-shadow:0 0 0 4px rgba(152,162,179,.12); }
	    .updates-dot.ok { background:var(--update-ok); box-shadow:0 0 0 4px rgba(15,118,110,.14); }
	    .updates-dot.warn { background:var(--update-warn); box-shadow:0 0 0 4px rgba(180,83,9,.13); }
	    .updates-dot.danger { background:var(--update-danger); box-shadow:0 0 0 4px rgba(180,35,24,.13); }
	    .updates-grid { display:grid; grid-template-columns:repeat(12,minmax(0,1fr)); gap:14px; }
	    .updates-card { padding:20px; min-height:100%; }
	    .updates-card.span-4 { grid-column:span 4; } .updates-card.span-5 { grid-column:span 5; } .updates-card.span-7 { grid-column:span 7; } .updates-card.span-12 { grid-column:span 12; }
	    .updates-card-head { display:flex; justify-content:space-between; gap:14px; align-items:flex-start; margin-bottom:14px; }
	    .updates-card h2 { margin:0; font-size:1.08rem; font-weight:900; color:var(--update-ink); letter-spacing:-.01em; }
	    .updates-badge { display:inline-flex; align-items:center; gap:6px; border-radius:999px; padding:6px 10px; font-size:.76rem; font-weight:850; border:1px solid #d0d5dd; background:#f9fafb; color:#344054; white-space:nowrap; }
	    .updates-badge.ok { color:#0f766e; background:#ecfdf3; border-color:#abefc6; }
	    .updates-badge.warn { color:#b45309; background:#fffaeb; border-color:#fedf89; }
	    .updates-badge.danger { color:#b42318; background:#fef3f2; border-color:#fecdca; }
	    .updates-badge.neutral { color:#475467; background:#f9fafb; border-color:#eaecf0; }
	    .updates-stepper { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:10px; }
	    .updates-step { border:1px solid #eaecf0; border-radius:16px; padding:14px; background:#fcfcfd; }
	    .updates-step strong { display:block; color:var(--update-ink); font-size:.9rem; }
	    .updates-step span { display:block; margin-top:5px; color:#667085; font-size:.79rem; line-height:1.35; }
	    .updates-step.active { border-color:#7cd4fd; background:#f0f9ff; }
	    .updates-step.done { border-color:#abefc6; background:#f6fef9; }
	    .updates-split { display:grid; grid-template-columns:minmax(0,1fr) minmax(300px,.9fr); gap:14px; }
	    .updates-list { display:grid; gap:0; margin:0; padding:0; list-style:none; }
	    .updates-list li { display:flex; justify-content:space-between; gap:12px; padding:10px 0; border-bottom:1px solid #eaecf0; }
	    .updates-list li:last-child { border-bottom:0; }
	    .updates-list code { color:var(--update-ink); font-weight:800; white-space:normal; text-align:right; }
	    .updates-file-table { width:100%; border-collapse:separate; border-spacing:0; overflow:hidden; }
	    .updates-file-table th, .updates-file-table td { padding:10px 9px; border-bottom:1px solid #eaecf0; vertical-align:top; font-size:.86rem; }
	    .updates-file-table th { color:#667085; font-size:.73rem; letter-spacing:.04em; text-transform:uppercase; }
	    .updates-file-table td:first-child { font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; overflow-wrap:anywhere; }
	    .updates-raw { margin-top:12px; border-top:1px solid #eaecf0; padding-top:10px; }
	    .updates-raw summary { cursor:pointer; color:#475467; font-weight:800; font-size:.84rem; }
	    .updates-raw pre { margin:10px 0 0; max-height:320px; overflow:auto; border-radius:14px; background:#101828; color:#d1e9ff; padding:12px; font-size:.78rem; }
	    .updates-info { border-radius:18px; border:1px solid #d0d5dd; background:#fff; color:#344054; padding:15px 16px; margin:0 0 16px; line-height:1.55; }
	    .updates-info strong { color:#101828; }
	    .updates-empty { border:1px dashed #d0d5dd; border-radius:16px; padding:16px; color:#667085; background:#f9fafb; }
	    .updates-danger-zone { border-color:#fecdca; background:linear-gradient(180deg,#fff,#fffbfa); }
	    .updates-danger-zone h2 { color:#b42318; }
	    @media (max-width: 1120px) { .updates-hero { grid-template-columns:1fr; } .updates-trust { grid-template-columns:repeat(2,minmax(0,1fr)); } .updates-card.span-4,.updates-card.span-5,.updates-card.span-7 { grid-column:span 12; } .updates-split { grid-template-columns:1fr; } .updates-stepper { grid-template-columns:repeat(2,minmax(0,1fr)); } }
	    @media (max-width: 720px) { .updates-shell { padding:0 2px; } .updates-hero-panel,.updates-next,.updates-card { border-radius:18px; padding:18px; } .updates-trust { grid-template-columns:1fr; } .updates-stepper { grid-template-columns:1fr; } }
	  </style>

	  <div class="updates-shell">
	  <div id="updatesNotice" class="updates-notice"></div>

	  <section class="updates-hero">
	    <div class="updates-hero-panel">
	      <div class="updates-kicker">Центр обновлений</div>
	      <h1 class="updates-title">Обновления без лишнего риска</h1>
	      <p class="updates-subtitle">CRM сама проверит доступную сборку, подготовит архив, выполнит безопасную проверку и подскажет следующий шаг.</p>
	      <div class="updates-actions">
	        <button id="primaryActionBtn" class="btn crm-btn-primary" type="button" data-update-action="check">Проверить обновления</button>
	        <button class="btn crm-btn-secondary" type="button" data-update-action="refresh">Обновить статус</button>
	        <button class="btn crm-btn-secondary" type="button" data-update-action="changes">Что изменится?</button>
	        <a class="btn crm-btn-secondary" href="/updater/rescue.php" target="_blank" rel="noopener">Аварийное восстановление</a>
	      </div>
	      <div class="updates-pill-row">
	        <span class="updates-pill"><span id="pillCenter" class="updates-dot"></span><span id="pillCenterText">Сервер обновлений проверяется</span></span>
	        <span class="updates-pill"><span id="pillVersion" class="updates-dot"></span><span id="pillVersionText">Версия пока неизвестна</span></span>
	        <span class="updates-pill"><span id="pillJob" class="updates-dot"></span><span id="pillJobText">Операций еще не было</span></span>
	        <span class="updates-pill"><span id="pillMaintenance" class="updates-dot"></span><span id="pillMaintenanceText">CRM работает штатно</span></span>
	      </div>
	    </div>
	    <aside class="updates-next">
	      <div>
	        <div class="updates-next-label">Рекомендация</div>
	        <h2 id="nextTitle" class="updates-next-title">Сейчас безопасно проверить обновления</h2>
	        <p id="nextText" class="updates-next-text">Проверка ничего не меняет в файлах CRM. Она только сравнит вашу версию с готовыми архивами на сервере обновлений.</p>
	      </div>
	      <div class="updates-next-foot">
	        <span id="nextStatusBadge" class="updates-badge neutral">Ожидание</span>
	        <span id="nextPlanBadge" class="updates-badge neutral">Не проверено</span>
	      </div>
	    </aside>
	  </section>

	  <div class="updates-info">
	    <strong>Как это работает:</strong> архив обновления заранее собирается кроном на update.crm.ru. Эта CRM не генерирует архив при каждом открытии страницы, а только скачивает готовый пакет, проверяет его и применяет после вашего подтверждения.
	  </div>

	  <section class="updates-trust">
	    <article class="updates-metric">
	      <div class="updates-kpi-label">Текущая версия</div>
	      <div id="kpiInstalled" class="updates-kpi-value">...</div>
	      <p id="kpiInstalledMeta" class="updates-muted mb-0">Загружаем состояние CRM.</p>
	    </article>
	    <article class="updates-metric">
	      <div class="updates-kpi-label">Доступная версия</div>
	      <div id="kpiTarget" class="updates-kpi-value">...</div>
	      <p id="kpiTargetMeta" class="updates-muted mb-0">Покажем после проверки.</p>
	    </article>
	    <article class="updates-metric">
	      <div class="updates-kpi-label">Что скачается</div>
	      <div id="kpiPackage" class="updates-kpi-value">...</div>
	      <p id="kpiPackageMeta" class="updates-muted mb-0">Готовый архив или ничего.</p>
	    </article>
	    <article class="updates-metric">
	      <div class="updates-kpi-label">Уровень риска</div>
	      <div id="kpiRisk" class="updates-kpi-value">...</div>
	      <p id="kpiRiskMeta" class="updates-muted mb-0">Оценим перед установкой.</p>
	    </article>
	  </section>

	  <section class="updates-grid">

    <article class="updates-card span-12">
      <div class="updates-card-head">
        <div>
	          <h2>Путь обновления</h2>
	          <p class="updates-muted mb-0">Страница ведет по шагам: сначала проверка, потом безопасная подготовка, и только затем установка.</p>
        </div>
        <span id="pipelineBadge" class="updates-badge neutral">Ожидание</span>
      </div>
      <div class="updates-stepper">
	        <div id="stepStatus" class="updates-step"><strong>1. Состояние</strong><span>Понимаем текущую версию CRM</span></div>
	        <div id="stepCheck" class="updates-step"><strong>2. Проверка</strong><span>Ищем готовое обновление</span></div>
	        <div id="stepPreflight" class="updates-step"><strong>3. Безопасность</strong><span>Проверяем архив до установки</span></div>
	        <div id="stepDownload" class="updates-step"><strong>4. Подготовка</strong><span>Скачиваем пакет во временную папку</span></div>
	        <div id="stepApply" class="updates-step"><strong>5. Установка</strong><span>Backup, обновление и проверка</span></div>
      </div>
    </article>

    <article class="updates-card span-7">
      <div class="updates-card-head">
        <div>
	          <h2>Что предлагает система</h2>
	          <p class="updates-muted mb-0">Краткое решение: есть ли обновление, что будет скачано и нужны ли дополнительные меры.</p>
        </div>
        <span id="planCardBadge" class="updates-badge neutral">Не проверено</span>
      </div>
	      <div id="planContent" class="updates-empty">Нажмите «Проверить обновления». Это безопасно и ничего не меняет в CRM.</div>
	      <details class="updates-raw"><summary>Технические данные плана</summary><pre id="updatesPlanRaw">{}</pre></details>
    </article>

    <article class="updates-card span-5">
      <div class="updates-card-head">
        <div>
	          <h2>Последняя операция</h2>
	          <p class="updates-muted mb-0">Что CRM делала с обновлениями в последний раз.</p>
        </div>
        <span id="jobBadge" class="updates-badge neutral">Нет job</span>
      </div>
      <div id="jobContent" class="updates-empty">История появится после preflight/download/apply.</div>
	      <details class="updates-raw"><summary>Технические данные состояния</summary><pre id="updatesStatusRaw">{}</pre></details>
    </article>

    <article class="updates-card span-7">
      <div class="updates-card-head">
        <div>
	          <h2>Что изменится</h2>
	          <p class="updates-muted mb-0">Короткий список изменений между вашей версией и доступной сборкой.</p>
        </div>
        <span id="changesBadge" class="updates-badge neutral">Не загружено</span>
      </div>
	      <div id="changesContent" class="updates-empty">Нажмите «Что изменится?», чтобы увидеть понятное резюме.</div>
	      <details class="updates-raw"><summary>Технические данные изменений</summary><pre id="updatesChangesRaw">{}</pre></details>
    </article>

    <article class="updates-card span-5">
      <div class="updates-card-head">
        <div>
	          <h2>Проверка перед установкой</h2>
	          <p class="updates-muted mb-0">CRM проверит архив, права на файлы, подписи и свободное место до любых изменений.</p>
        </div>
	        <span id="preflightBadge" class="updates-badge neutral">Не запускалась</span>
	      </div>
	      <div id="preflightContent" class="updates-empty">Когда обновление будет найдено, сначала запустите безопасную проверку. Она не применяет файлы.</div>
	      <div class="updates-actions mt-3">
	        <button class="btn crm-btn-primary" type="button" data-update-action="preflight">Проверить безопасность</button>
	        <button class="btn crm-btn-secondary" type="button" data-update-action="download">Подготовить архив</button>
	      </div>
	      <details class="updates-raw"><summary>Технические данные проверки</summary><pre id="updatesPreflightRaw">{}</pre></details>
    </article>

    <article class="updates-card span-12 updates-danger-zone">
      <div class="updates-card-head">
        <div>
	          <h2>Установка и восстановление</h2>
	          <p class="updates-muted mb-0">Установка запускается только после проверки и ручного подтверждения. Перед заменой файлов создается backup.</p>
	        </div>
	        <div class="updates-actions mt-0">
	          <button class="btn crm-btn-danger-soft" type="button" data-update-action="apply">Установить обновление</button>
	          <button class="btn crm-btn-secondary" type="button" data-update-action="rollback">Восстановить из backup</button>
	        </div>
	      </div>
	      <div id="applyContent" class="updates-empty">Установка станет доступной по смыслу после успешной проверки и подготовки архива. Для применения потребуется ввести подтверждение.</div>
	      <details class="updates-raw"><summary>Технические данные установки</summary><pre id="updatesApplyRaw">{}</pre></details>
	    </article>
	  </section>
	  </div>
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
	  const actionLabels = {
	    'initial load': 'начальная проверка',
	    refresh: 'обновляем статус',
	    check: 'проверяем обновления',
	    changes: 'загружаем изменения',
	    preflight: 'проверяем безопасность',
	    download: 'подготавливаем архив',
	    apply: 'устанавливаем обновление',
	    rollback: 'восстанавливаем backup'
	  };

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
	    json.http_status = res.status;
	    return json;
	  }

	  function showNotice(message, kind = 'danger') {
	    const box = $('updatesNotice');
	    if (!box) return;
	    box.className = `updates-notice show ${kind}`;
	    box.textContent = message;
	  }

	  function clearNotice() {
	    const box = $('updatesNotice');
	    if (!box) return;
	    box.className = 'updates-notice';
	    box.textContent = '';
	  }

	  function errorMessage(payload, fallback) {
	    const status = Number(payload && payload.http_status || 0);
	    if (status === 401) return 'Сессия истекла. Обновите страницу и войдите в CRM снова.';
	    if (status === 403) return 'У пользователя нет прав на управление обновлениями. Нужен root/admin или право управления настройками.';
	    return String((payload && (payload.message || payload.code)) || fallback || 'Не удалось выполнить действие.');
	  }

	  function ensureSuccess(payload, fallback) {
	    if (payload && payload.success === false) {
	      throw new Error(errorMessage(payload, fallback));
	    }
	    return payload;
	  }

	  function setBadge(id, kind, text) {
	    const el = $(id);
	    if (!el) return;
	    el.className = badgeClass(kind);
	    el.textContent = text;
	  }

	  function setPrimary(action, label) {
	    const btn = $('primaryActionBtn');
	    if (!btn) return;
	    btn.setAttribute('data-update-action', action);
	    btn.textContent = label;
	  }

	  function updateRecommendation() {
	    const nextTitle = $('nextTitle');
	    const nextText = $('nextText');
	    const latest = state.status && state.status.latest_job;
	    const plan = state.plan;
	    if (latest && latest.state === 'failed') {
	      nextTitle.textContent = 'Последняя операция завершилась ошибкой';
	      nextText.textContent = 'Проверьте технические детали операции. Если CRM работает нестабильно, используйте восстановление из backup.';
	      setPrimary('refresh', 'Обновить статус');
	    } else if (state.download) {
	      nextTitle.textContent = 'Архив подготовлен, можно устанавливать';
	      nextText.textContent = 'Перед установкой CRM создаст backup. Запускайте установку только если готовы к короткому maintenance-окну.';
	      setPrimary('apply', 'Установить обновление');
	    } else if (state.preflight) {
	      nextTitle.textContent = 'Проверка пройдена, подготовьте архив';
	      nextText.textContent = 'Следующий шаг скачает готовый пакет обновления во временную папку. Рабочие файлы CRM еще не меняются.';
	      setPrimary('download', 'Подготовить архив');
	    } else if (plan && plan.update_available) {
	      nextTitle.textContent = 'Найдено обновление, сначала нужна проверка';
	      nextText.textContent = 'CRM проверит подпись архива, доступ к файлам и свободное место. Это безопасный шаг до установки.';
	      setPrimary('preflight', 'Проверить безопасность');
	    } else if (plan && plan.update_available === false) {
	      nextTitle.textContent = 'CRM уже актуальна';
	      nextText.textContent = 'Устанавливать ничего не нужно. Архив обновления не требуется, рисков для текущей версии нет.';
	      setPrimary('check', 'Проверить еще раз');
	    } else {
	      nextTitle.textContent = 'Сейчас безопасно проверить обновления';
	      nextText.textContent = 'Проверка ничего не меняет в файлах CRM. Она только сравнит вашу версию с готовыми архивами на сервере обновлений.';
	      setPrimary('check', 'Проверить обновления');
	    }
	    setBadge('nextStatusBadge', pipelineKind(), pipelineText());
	    setBadge('nextPlanBadge', plan ? (plan.update_available ? 'warn' : 'ok') : 'neutral', plan ? (plan.update_available ? 'Есть обновление' : 'Обновлений нет') : 'Не проверено');
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
	    badge.textContent = loading ? `Выполняется: ${actionLabels[action] || action}` : pipelineText();
	    setBadge('nextStatusBadge', loading ? 'warn' : pipelineKind(), loading ? 'Выполняется' : pipelineText());
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
	    updateRecommendation();
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

	    setBadge('planCardBadge', plan.update_available ? 'warn' : 'ok', plan.update_available ? 'Есть обновление' : 'Обновлений нет');
	    $('planContent').innerHTML = plan.update_available ? list({
	      'Сейчас установлено': plan.current_build || 'unknown',
	      'Будет установлено': displayTarget,
	      'Тип архива': pkg ? String(pkg.type).toUpperCase() : 'нет',
	      'Размер': pkg ? bytes(pkg.size_bytes) : '0 Б',
	      'Уровень риска': risk,
	    }) : '<div class="updates-empty">Обновлений нет. CRM уже на последней опубликованной сборке.</div>';

	    setStep('stepCheck', 'done');
	    $('pipelineBadge').className = badgeClass(pipelineKind());
	    $('pipelineBadge').textContent = pipelineText();
	    updateRecommendation();
	  }

  function renderChanges() {
    const payload = state.changes;
    $('updatesChangesRaw').textContent = pretty(payload);
    if (!payload) {
      $('changesBadge').className = badgeClass('');
      $('changesBadge').textContent = 'Не загружено';
	      $('changesContent').innerHTML = '<div class="updates-empty">Нажмите «Что изменится?», чтобы увидеть понятное резюме.</div>';
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
	    $('changesBadge').textContent = `${data.summary.commits || 0} коммитов / ${data.summary.files || 0} файлов`;
    const commits = (data.commits || []).slice(0, 6).map((c) => `<li><span><strong>${esc(c.short_sha || '')}</strong> ${esc(c.title || '')}</span><span>${esc(c.committed_at || '')}</span></li>`).join('');
    const files = (data.files || []).slice(0, 12).map((f) => `<tr><td>${esc(f.path)}</td><td>${esc(f.scope)}</td><td>${esc(f.change_type)}</td><td>${f.included_in_package ? '<span class="updates-badge ok">included</span>' : '<span class="updates-badge neutral">excluded</span>'}</td></tr>`).join('');
    const messageText = Number(payload.status || 0) === 204 ? 'Целевая сборка не определена: CRM уже считает доступное состояние актуальным, поэтому изменений для установки нет.' : data.message;
    const message = messageText ? `<div class="updates-empty mb-3">${esc(messageText)}</div>` : '';
    $('changesContent').innerHTML = `
      ${message}
      <div class="updates-split">
        <div>
	          <h3 class="h6">Краткая история</h3>
          <ul class="updates-list">${commits || '<li><span>Нет коммитов</span></li>'}</ul>
        </div>
        <div>
	          <h3 class="h6">Затронутые файлы</h3>
	          <table class="updates-file-table"><thead><tr><th>Файл</th><th>Зона</th><th>Тип</th><th>Архив</th></tr></thead><tbody>${files || '<tr><td colspan="4">Нет файлов</td></tr>'}</tbody></table>
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
	    updateRecommendation();
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
	    updateRecommendation();
	  }

  function list(items) {
    return `<ul class="updates-list">${Object.entries(items).map(([key, value]) => `<li><span>${esc(key)}</span><code>${esc(value)}</code></li>`).join('')}</ul>`;
  }

	  async function withAction(name, fn) {
	    setLoading(name, true);
	    try {
	      clearNotice();
	      await fn();
	    } catch (err) {
	      showNotice(String(err && err.message ? err.message : err));
	      $('updatesApplyRaw').textContent = pretty({error: String(err)});
	    } finally {
	      setLoading(name, false);
	      updateRecommendation();
	    }
	  }

  async function loadStatus() {
	    const [version, status] = await Promise.all([
	      api('/api/index.php?route=api/v1/core/version'),
	      api('/api/index.php?route=api/v1/core/updates/status')
	    ]);
	    ensureSuccess(version, 'Не удалось загрузить текущую версию CRM.');
	    ensureSuccess(status, 'Не удалось загрузить статус обновлений.');
	    state.version = version.data || version;
    state.status = status.data || status;
    if (state.status && state.status.latest_job && state.status.latest_job.job_id) state.lastJobId = state.status.latest_job.job_id;
    renderStatus();
  }

	  async function check() {
	    const result = await api('/api/index.php?route=api/v1/core/updates/check', {method: 'POST', body: '{}'});
	    ensureSuccess(result, 'Не удалось проверить обновления.');
	    state.plan = result.data && result.data.plan ? result.data.plan : (result.data || result);
	    renderPlan();
	  }

	  async function changes() {
	    const result = await api('/api/index.php?route=api/v1/core/updates/changes');
	    ensureSuccess(result, 'Не удалось загрузить список изменений.');
	    state.changes = result.data || result;
	    renderChanges();
	  }

	  async function preflight() {
	    const result = await api('/api/index.php?route=api/v1/core/updates/preflight', {method: 'POST', body: JSON.stringify({dry_run: true})});
	    ensureSuccess(result, 'Не удалось выполнить безопасную проверку.');
    const data = result.data || result;
    state.preflight = data.preflight || data;
    state.lastJobId = data.job_id || (data.updater && data.updater.data && data.updater.data.job_id) || state.lastJobId;
    renderPreflight();
    await loadStatus();
  }

	  async function download() {
	    if (!state.lastJobId) throw new Error('Сначала выполните preflight, чтобы получить job_id.');
	    const result = await api('/updater/index.php?action=download', {method: 'POST', body: JSON.stringify({dry_run: true, job_id: state.lastJobId})});
	    ensureSuccess(result, 'Не удалось подготовить архив.');
    state.download = result;
    renderPreflight();
    await loadStatus();
  }

  async function updaterSession() {
	    const session = await api('/api/index.php?route=api/v1/core/updates/session', {method: 'POST', body: '{}'});
	    ensureSuccess(session, 'Не удалось получить одноразовый updater token.');
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
	    ensureSuccess(result, 'Не удалось установить обновление.');
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
	    ensureSuccess(result, 'Не удалось восстановить backup.');
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
