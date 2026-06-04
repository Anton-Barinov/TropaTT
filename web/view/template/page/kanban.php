<?php declare(strict_types=1); ?>
<?php $title = 'TropaTT — Канбан'; ?>
<body data-page="kanban" data-protected="1"><div class="crm-app"><aside class="crm-sidebar"><div class="crm-brand"><span class="crm-brand-mark"></span> TropaTT</div><nav class="nav flex-column crm-nav"></nav></aside>
<div class="crm-main-wrap"><header class="crm-topbar py-2"><div class="container-fluid"></div></header>
<main class="crm-content crm-kanban-page"><div class="crm-page-head"><div><h1 class="crm-page-title">Канбан</h1><p class="crm-subtitle">Задачи по статусам в рабочей доске.</p></div><div class="crm-page-actions"><button class="btn crm-btn-primary" type="button" data-open-modal="createTaskModal">Создать задачу</button></div></div>
<div class="crm-kanban-local-actions"><a class="btn crm-btn-secondary" href="index.php?route=tasks">Задачи</a></div>
<div class="crm-mobile-work-mode-hint d-md-none">Колонки доски пролистываются горизонтально. Для полного списка задач используйте раздел «Задачи».</div>
<div id="kanbanMobileStatusTabs" class="crm-kanban-mobile-tabs d-md-none" role="tablist" aria-label="Статусы канбана"></div>
    <div class="crm-kanban"></div>
</main></div></div>
