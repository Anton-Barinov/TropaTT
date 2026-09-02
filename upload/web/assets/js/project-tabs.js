/**
 * project-tabs.js — Lightweight tab/accordion/menu interactions for project-detail.
 * Extracted from page-api-bindings.js to avoid loading the full 325KB file on
 * project-detail pages that don't need the rest of page-api-bindings.
 */
(function () {
  'use strict';
  window.CRM = window.CRM || {};

  function projectActivateTab(name) {
    document.querySelectorAll('[data-project-tab]').forEach(function (btn) {
      var active = btn.getAttribute('data-project-tab') === name;
      btn.classList.toggle('active', active);
      btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    document.querySelectorAll('[data-project-panel]').forEach(function (panel) {
      panel.classList.toggle('active', panel.getAttribute('data-project-panel') === name);
    });
  }

  function projectCloseAllPopups() {
    document.querySelectorAll('.crm-pr-menu.open, .crm-pr-popover.open').forEach(function (node) {
      node.classList.remove('open');
      var wrap = node.classList.contains('crm-pr-menu') ? node.parentElement : null;
      if (wrap) {
        var btn = wrap.querySelector('.crm-pr-menu-btn');
        if (btn) btn.setAttribute('aria-expanded', 'false');
      }
    });
  }

  function setupProjectDetailInteractions() {
    if (window.CRM._projectDetailInteractionsBound) return;
    window.CRM._projectDetailInteractionsBound = true;

    document.addEventListener('click', function (e) {
      var tabBtn = e.target.closest('[data-project-tab]');
      if (tabBtn) {
        projectActivateTab(String(tabBtn.getAttribute('data-project-tab') || 'overview'));
        return;
      }

      var accHead = e.target.closest('.crm-pr-acc-head');
      if (accHead) {
        var acc = accHead.closest('.crm-pr-acc');
        if (!acc) return;
        var panel = acc.querySelector('.crm-pr-acc-panel');
        var isOpen = acc.classList.contains('open');
        if (isOpen) {
          if (panel) panel.style.maxHeight = '0';
          acc.classList.remove('open');
          accHead.setAttribute('aria-expanded', 'false');
        } else {
          acc.classList.add('open');
          if (panel) panel.style.maxHeight = panel.scrollHeight + 'px';
          accHead.setAttribute('aria-expanded', 'true');
        }
        return;
      }

      var menuBtn = e.target.closest('#projectMoreBtn');
      if (menuBtn) {
        var menu = document.getElementById('projectMoreMenu');
        if (!menu) return;
        projectCloseAllPopups();
        var willOpen = !menu.classList.contains('open');
        menu.classList.toggle('open', willOpen);
        menuBtn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        return;
      }

      var editTab = e.target.closest('[data-project-edit-tab]');
      if (editTab) {
        var modal = editTab.closest('.modal');
        var scope = modal || document;
        var name = String(editTab.getAttribute('data-project-edit-tab') || 'identity');
        scope.querySelectorAll('[data-project-edit-tab]').forEach(function (btn) {
          var active = btn.getAttribute('data-project-edit-tab') === name;
          btn.classList.toggle('active', active);
          btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        scope.querySelectorAll('[data-project-edit-panel]').forEach(function (p) {
          p.classList.toggle('active', p.getAttribute('data-project-edit-panel') === name);
        });
        return;
      }

      var aiSegment = e.target.closest('[data-ai-segment]');
      if (aiSegment) {
        var card = aiSegment.closest('#projectAiCompactCard') || document;
        var segName = String(aiSegment.getAttribute('data-ai-segment') || 'summary');
        card.querySelectorAll('[data-ai-segment]').forEach(function (btn) {
          var active = btn.getAttribute('data-ai-segment') === segName;
          btn.classList.toggle('active', active);
          btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        card.querySelectorAll('[data-ai-panel]').forEach(function (p) {
          p.classList.toggle('active', p.getAttribute('data-ai-panel') === segName);
        });
        return;
      }

      var aiOpen = e.target.closest('#projectAiCompactOpenBtn');
      if (aiOpen) {
        projectActivateTab('ai');
        return;
      }

      if (!e.target.closest('.crm-pr-menu-wrap') && !e.target.closest('.crm-pr-status-row')) {
        projectCloseAllPopups();
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') projectCloseAllPopups();
    });
  }

  // Expose for inline scripts
  window.CRM.projectActivateTab = projectActivateTab;
  window.CRM.setupProjectDetailInteractions = setupProjectDetailInteractions;

  // Auto-init
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupProjectDetailInteractions);
  } else {
    setupProjectDetailInteractions();
  }
})();
