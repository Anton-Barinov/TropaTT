(function () {
  'use strict';

  var SUPPORTED = ['ru', 'en', 'zh'];
  var STORAGE_KEY = 'crm_landing_locale';
  var SITE_URL = 'https://tropatt.com';
  var GITHUB_URL = 'https://github.com/Anton-Barinov/TropaTT';
  var DOCS_URL = GITHUB_URL + '#installation';
  var CONTACT_URL = GITHUB_URL + '/issues/new';
  var lang = 'en';
  var sectionTargets = ['hero', 'capabilities', 'ai', 'ideas', 'automation', 'open-source', 'services', 'faq'];

  function escapeHtml(value) {
    var node = document.createElement('div');
    node.appendChild(document.createTextNode(value == null ? '' : String(value)));
    return node.innerHTML;
  }

  function detectLanguage() {
    var queryLang = new URLSearchParams(window.location.search).get('lang');
    if (SUPPORTED.indexOf(queryLang) !== -1) return queryLang;

    var saved = localStorage.getItem(STORAGE_KEY);
    if (SUPPORTED.indexOf(saved) !== -1) return saved;

    var browser = (navigator.language || '').toLowerCase();
    if (browser.indexOf('zh') === 0) return 'zh';
    if (browser.indexOf('ru') === 0) return 'ru';
    return 'en';
  }

  function setLanguageState(nextLang) {
    lang = nextLang;
    localStorage.setItem(STORAGE_KEY, lang);
    document.documentElement.lang = lang === 'zh' ? 'zh-CN' : lang;
    document.querySelectorAll('.crm-lang-btn').forEach(function (button) {
      var active = button.getAttribute('data-lang') === lang;
      button.classList.toggle('active', active);
      button.setAttribute('aria-current', active ? 'true' : 'false');
    });
  }

  function updateMeta(data) {
    document.title = data.meta.title;
    setMeta('meta[name="description"]', 'content', data.meta.description);
    setMeta('meta[property="og:title"]', 'content', data.meta.ogTitle);
    setMeta('meta[property="og:description"]', 'content', data.meta.ogDescription);
    setMeta('meta[name="twitter:title"]', 'content', data.meta.ogTitle);
    setMeta('meta[name="twitter:description"]', 'content', data.meta.ogDescription);

    var siteUrl = SITE_URL;
    var base = siteUrl.replace(/\/+$/, '');
    setCanonical(base);
    setHreflang(base, lang);
  }

  function setCanonical(href) {
    var link = document.querySelector('link[rel="canonical"]');
    if (!link) { link = document.createElement('link'); link.setAttribute('rel', 'canonical'); document.head.appendChild(link); }
    link.setAttribute('href', href + '?lang=' + lang);
  }

  function setHreflang(base, currentLang) {
    var langs = { ru: 'ru', en: 'en', zh: 'zh' };
    document.querySelectorAll('link[rel="alternate"][hreflang]').forEach(function (el) { el.remove(); });
    Object.keys(langs).forEach(function (code) {
      var link = document.createElement('link');
      link.setAttribute('rel', 'alternate');
      link.setAttribute('hreflang', langs[code]);
      link.setAttribute('href', (base || '') + '/?lang=' + code);
      document.head.appendChild(link);
    });
    var xd = document.createElement('link');
    xd.setAttribute('rel', 'alternate');
    xd.setAttribute('hreflang', 'x-default');
    xd.setAttribute('href', (base || '') + '/');
    document.head.appendChild(xd);
  }

  function setMeta(selector, attr, value) {
    var node = document.querySelector(selector);
    if (node) node.setAttribute(attr, value);
  }

  function renderNav(data) {
    var nav = data.nav || {};
    var languages = data.languages || {};
    var headerNav = document.getElementById('headerNav');

    if (headerNav) {
      var links = (nav.items || []).map(function (item) {
        return '<a href="' + escapeHtml(item.href) + '" class="crm-nav-link">' + escapeHtml(item.label) + '</a>';
      }).join('');

      var langButtons = SUPPORTED.map(function (code) {
        return '<button class="crm-lang-btn" data-lang="' + code + '" aria-label="' + escapeHtml(languages[code] || code) + '">' + escapeHtml(languages[code] || code.toUpperCase()) + '</button>';
      }).join('');

      headerNav.innerHTML = links + '<div class="crm-lang-switcher" aria-label="' + escapeHtml(nav.langSwitcherAria || '') + '">' + langButtons + '</div>';
      headerNav.setAttribute('aria-label', nav.ariaLabel || '');
    }

    var logo = document.getElementById('crmLogo');
    if (logo) logo.setAttribute('aria-label', nav.homeAriaLabel || '');

    var hamburger = document.getElementById('hamburgerBtn');
    if (hamburger) hamburger.setAttribute('aria-label', nav.hamburgerAria || '');

    var sectionNav = document.getElementById('sectionNav');
    if (sectionNav) {
      sectionNav.innerHTML = (nav.sectionLabels || []).map(function (label, index) {
        return '<button class="crm-section-nav-dot" data-target="' + escapeHtml(sectionTargets[index] || 'hero') + '" aria-label="' + escapeHtml(label) + '"></button>';
      }).join('');
      sectionNav.setAttribute('aria-label', nav.sectionNavAria || '');
    }

    var noScript = document.getElementById('noscriptFallback');
    if (noScript && data.ui && data.ui.noScript) {
      noScript.innerHTML = '<section class="crm-section"><div class="crm-container"><h1>TropaTT</h1><p>' + escapeHtml(data.ui.noScript) + '</p></div></section>';
    }
  }

  function section(id, className, inner) {
    return '<section class="crm-section ' + (className || '') + '" id="' + id + '"><div class="crm-container">' + inner + '</div></section>';
  }

  function intro(block, centered, showLabel) {
    return '<div class="crm-section-intro ' + (centered ? 'centered ' : '') + 'crm-reveal">' +
      (showLabel && block.label ? '<span class="crm-section-label">' + escapeHtml(block.label) + '</span>' : '') +
      '<h2 class="crm-section-title">' + escapeHtml(block.title) + '</h2>' +
      (block.text ? '<p class="crm-section-lede">' + escapeHtml(block.text) + '</p>' : '') +
      '</div>';
  }

  function card(item, className) {
    return '<article class="crm-card ' + (className || '') + ' crm-reveal-stagger">' +
      '<div class="crm-card-kicker">' + escapeHtml(item.kicker || '') + '</div>' +
      '<h3>' + escapeHtml(item.title) + '</h3>' +
      '<p>' + escapeHtml(item.text) + '</p>' +
      '</article>';
  }

  function field(name, label, type, placeholder, required, helper) {
    return '<label class="crm-field" for="lead-' + name + '">' +
      '<span>' + escapeHtml(label) + (required ? ' <b aria-hidden="true">*</b>' : '') + '</span>' +
      '<input id="lead-' + name + '" name="' + name + '" type="' + type + '" placeholder="' + escapeHtml(placeholder || '') + '"' + (required ? ' required' : '') + ' autocomplete="' + (type === 'email' ? 'email' : 'on') + '">' +
      '<small>' + escapeHtml(helper || '') + '</small>' +
      '<em class="crm-field-error" data-error-for="' + name + '"></em>' +
      '</label>';
  }

  function renderLeadForm(data) {
    var formText = {
      ru: {
        title: 'Обсудить внедрение',
        text: 'Опишите задачу коротко. После проверки формы появится ссылка на GitHub issue с уже подготовленным текстом обращения.',
        name: 'Имя',
        namePlaceholder: 'Как к вам обращаться',
        email: 'Email',
        emailPlaceholder: 'name@company.ru',
        company: 'Компания',
        companyPlaceholder: 'Название или сайт',
        message: 'Что нужно сделать',
        messagePlaceholder: 'Установка, интеграция, модуль, обучение...',
        submit: 'Отправить заявку',
        sending: 'Отправляем...',
        required: 'Заполните это поле.',
        emailError: 'Введите корректный email.',
        configuredError: 'Не удалось подготовить обращение. Откройте GitHub и создайте issue вручную.',
        success: 'Форма готова.',
        issueLink: 'Открыть GitHub issue',
        privacy: 'Без подписки и внешних сервисов. Поле website оставлено для защиты от спама.'
      },
      en: {
        title: 'Discuss implementation',
        text: 'Tell us what you need. After validation, a prefilled GitHub issue link will appear.',
        name: 'Name',
        namePlaceholder: 'How should we address you',
        email: 'Email',
        emailPlaceholder: 'name@company.com',
        company: 'Company',
        companyPlaceholder: 'Company name or website',
        message: 'What do you need',
        messagePlaceholder: 'Installation, integration, module, training...',
        submit: 'Send request',
        sending: 'Sending...',
        required: 'Please fill out this field.',
        emailError: 'Enter a valid email.',
        configuredError: 'Could not prepare the request. Open GitHub and create an issue manually.',
        success: 'The request is ready.',
        issueLink: 'Open GitHub issue',
        privacy: 'No subscription or external service. The website field is a spam trap.'
      },
      zh: {
        title: '讨论实施',
        text: '简单说明您的需求。验证通过后会出现已预填内容的 GitHub issue 链接。',
        name: '姓名',
        namePlaceholder: '如何称呼您',
        email: '邮箱',
        emailPlaceholder: 'name@company.com',
        company: '公司',
        companyPlaceholder: '公司名称或网站',
        message: '需要什么',
        messagePlaceholder: '安装、集成、模块、培训...',
        submit: '发送请求',
        sending: '发送中...',
        required: '请填写此字段。',
        emailError: '请输入有效邮箱。',
        configuredError: '无法准备请求。请打开 GitHub 手动创建 issue。',
        success: '请求已准备好。',
        issueLink: '打开 GitHub issue',
        privacy: '无订阅、无外部服务。website 字段用于防垃圾信息。'
      }
    }[lang] || {};

    return '<aside class="crm-lead-panel crm-reveal" id="contact-form">' +
      '<div class="crm-lead-copy"><span>' + escapeHtml(formText.title) + '</span><p>' + escapeHtml(formText.text) + '</p></div>' +
      '<form class="crm-lead-form" action="' + CONTACT_URL + '" method="get" target="_blank" novalidate data-required="' + escapeHtml(formText.required) + '" data-email-error="' + escapeHtml(formText.emailError) + '" data-configured-error="' + escapeHtml(formText.configuredError) + '" data-success="' + escapeHtml(formText.success) + '" data-issue-link="' + escapeHtml(formText.issueLink) + '" data-sending="' + escapeHtml(formText.sending) + '">' +
      '<div class="crm-form-grid">' +
      field('name', formText.name, 'text', formText.namePlaceholder, true, '') +
      field('email', formText.email, 'email', formText.emailPlaceholder, true, '') +
      '</div>' +
      field('company', formText.company, 'text', formText.companyPlaceholder, false, '') +
      '<label class="crm-field crm-field-full" for="lead-message"><span>' + escapeHtml(formText.message) + ' <b aria-hidden="true">*</b></span><textarea id="lead-message" name="message" rows="4" placeholder="' + escapeHtml(formText.messagePlaceholder) + '" required></textarea><small>' + escapeHtml(formText.privacy) + '</small><em class="crm-field-error" data-error-for="message"></em></label>' +
      '<label class="crm-honeypot" for="lead-website">Website<input id="lead-website" name="website" type="text" tabindex="-1" autocomplete="off"></label>' +
      '<button class="crm-btn crm-btn-primary crm-form-submit" type="submit" data-label="' + escapeHtml(formText.submit) + '">' + escapeHtml(formText.submit) + '<span class="crm-btn-arrow">↗</span></button>' +
      '<p class="crm-form-status" role="status" aria-live="polite"></p>' +
      '</form></aside>';
  }

  function render(data) {
    var html = '';
    html += renderHero(data);
    html += renderShortExplanation(data);
    html += renderHelp(data);
    html += renderCapabilities(data);
    html += renderAI(data);
    html += renderIdeas(data);
    html += renderAutomation(data);
    html += renderOpenSource(data);
    html += renderServices(data);
    html += renderUseCases(data);
    html += renderHowItWorks(data);
    html += renderAudience(data);
    html += renderFAQ(data);
    html += renderFinalCta(data);
    html += renderFooter(data);
    document.getElementById('landing-content').innerHTML = html;
    initInteractions(data);
  }

  function renderHero(data) {
    var hero = data.hero;
    var chips = (hero.chips || []).map(function (chip) {
      return '<span>' + escapeHtml(chip) + '</span>';
    }).join('');
    var rows = (hero.visualNodes || []).map(function (node, index) {
      return '<div class="crm-product-row row-' + index + '"><span></span><strong>' + escapeHtml(node.title) + '</strong><em>' + escapeHtml(node.text) + '</em></div>';
    }).join('');

    return '<section class="crm-hero crm-reveal" id="hero"><div class="crm-container crm-hero-grid">' +
      '<div class="crm-hero-content">' +
      '<div class="crm-hero-badge"><span></span>' + escapeHtml(hero.badge) + '</div>' +
      '<h1 class="crm-hero-title">' + escapeHtml(hero.title) + '</h1>' +
      '<p class="crm-hero-subtitle">' + escapeHtml(hero.subtitle) + '</p>' +
      '<div class="crm-hero-actions"><a href="' + GITHUB_URL + '" class="crm-btn crm-btn-primary" target="_blank" rel="noopener">' + escapeHtml(hero.ctaPrimary) + '<span class="crm-btn-arrow">↗</span></a><a href="#capabilities" class="crm-btn crm-btn-secondary">' + escapeHtml(hero.ctaSecondary) + '</a>' +
      (hero.demoUrl ? '<a href="' + escapeHtml(hero.demoUrl) + '" class="crm-btn crm-btn-accent" target="_blank" rel="noopener">' + escapeHtml(hero.demoLabel) + '</a>' : '') +
      '</div>' +
      (hero.demoHint ? '<p class="crm-hero-demo-hint">' + escapeHtml(hero.demoHint) + '</p>' : '') +
      '<div class="crm-hero-chips" aria-label="Technical highlights">' + chips + '</div>' +
      '</div>' +
      '<div class="crm-hero-visual" aria-hidden="true"><div class="crm-product-shell"><div class="crm-product-top"><i></i><i></i><i></i><b>TropaTT / workspace</b></div><div class="crm-product-body"><div class="crm-product-sidebar"><span></span><span></span><span></span><span></span></div><div class="crm-product-main"><div class="crm-product-chart"><strong>743</strong><span>API endpoints</span></div><div class="crm-product-board">' + rows + '</div><div class="crm-product-meter"><span style="width:78%"></span></div></div></div></div><div class="crm-product-card one"><strong>0</strong><span>license cost</span></div><div class="crm-product-card two"><strong>68</strong><span>interface pages</span></div></div>' +
      '</div></section>';
  }

  function renderShortExplanation(data) {
    return section('what-is', 'crm-section-soft', intro(data.whatIs, false, true) +
      '<div class="crm-proof-strip crm-reveal">' + (data.whatIs.facts || []).map(function (fact) {
        return '<div><strong>' + escapeHtml(fact.value) + '</strong><span>' + escapeHtml(fact.label) + '</span></div>';
      }).join('') + '</div>');
  }

  function renderHelp(data) {
    return section('helps', '', intro(data.helps, false, false) +
      '<div class="crm-mosaic-grid">' + (data.helps.items || []).map(function (item, index) {
        return card(item, index === 0 ? 'crm-card-large' : '');
      }).join('') + '</div>');
  }

  function renderCapabilities(data) {
    var groups = data.capabilities.groups || [];
    var tabs = groups.map(function (group, index) {
      return '<button class="crm-tab-btn' + (index === 0 ? ' active' : '') + '" data-tab="' + index + '">' + escapeHtml(group.label) + '</button>';
    }).join('');
    var panels = groups.map(function (group, index) {
      return '<div class="crm-tab-panel' + (index === 0 ? ' active' : '') + '" data-panel="' + index + '">' +
        '<div class="crm-capability-copy"><h3>' + escapeHtml(group.title) + '</h3><p>' + escapeHtml(group.text) + '</p></div>' +
        '<div class="crm-capability-list">' + group.items.map(function (item) { return '<span>' + escapeHtml(item) + '</span>'; }).join('') + '</div>' +
        '</div>';
    }).join('');

    return section('capabilities', 'crm-section-ink', intro(data.capabilities, false, true) +
      '<div class="crm-tabs crm-reveal"><div class="crm-tab-buttons">' + tabs + '</div>' + panels + '</div>');
  }

  function renderAI(data) {
    return section('ai', '', intro(data.ai, false, false) +
      '<div class="crm-ai-layout">' +
      '<div class="crm-ai-note crm-reveal"><span>' + escapeHtml(data.ai.statusLabel) + '</span><p>' + escapeHtml(data.ai.statusText) + '</p></div>' +
      '<div class="crm-ai-grid">' + (data.ai.items || []).map(function (item) { return card(item, 'crm-card-compact'); }).join('') + '</div>' +
      '</div>');
  }

  function renderIdeas(data) {
    return section('ideas', 'crm-section-soft', intro(data.ideas, false, false) +
      '<div class="crm-flow crm-reveal">' + (data.ideas.steps || []).map(function (step, index) {
        return '<article><span>0' + (index + 1) + '</span><h3>' + escapeHtml(step.title) + '</h3><p>' + escapeHtml(step.text) + '</p></article>';
      }).join('') + '</div>' +
      '<div class="crm-cards-3 crm-reveal">' + (data.ideas.features || []).map(function (item) { return card(item, ''); }).join('') + '</div>');
  }

  function renderAutomation(data) {
    return section('automation', 'crm-section-soft', intro(data.automation, false, false) +
      '<div class="crm-flow crm-reveal">' + (data.automation.steps || []).map(function (step, index) {
        return '<article><span>0' + (index + 1) + '</span><h3>' + escapeHtml(step.title) + '</h3><p>' + escapeHtml(step.text) + '</p></article>';
      }).join('') + '</div>' +
      '<div class="crm-cards-3">' + (data.automation.items || []).map(function (item) { return card(item, ''); }).join('') + '</div>');
  }

  function renderOpenSource(data) {
    return section('open-source', '', intro(data.openSource, false, false) +
      '<div class="crm-open-grid">' + (data.openSource.items || []).map(function (item) {
        return '<article class="crm-open-item crm-reveal-stagger"><h3>' + escapeHtml(item.title) + '</h3><p>' + escapeHtml(item.text) + '</p></article>';
      }).join('') + '</div>' +
      '<div class="crm-section-actions crm-reveal"><a href="' + GITHUB_URL + '" class="crm-btn crm-btn-primary" target="_blank" rel="noopener">' + escapeHtml(data.openSource.cta) + '<span class="crm-btn-arrow">↗</span></a></div>');
  }

  function renderServices(data) {
    return section('services', 'crm-section-ink crm-services-band', intro(data.paidServices, false, true) +
      '<div class="crm-services-grid">' + (data.paidServices.services || []).map(function (item) { return card(item, 'crm-card-dark'); }).join('') + '</div>' +
      '<div class="crm-service-contact"><p class="crm-service-footer crm-reveal">' + escapeHtml(data.paidServices.footer) + '</p>' +
      '<div class="crm-section-actions crm-reveal"><a href="#contact-form" class="crm-btn crm-btn-light">' + escapeHtml(data.paidServices.ctaPrimary) + '</a></div></div>' +
      renderLeadForm(data));
  }

  function renderUseCases(data) {
    return section('use-cases', '', intro(data.useCases, true, false) +
      '<div class="crm-usecase-grid">' + (data.useCases.items || []).map(function (item) {
        return '<article class="crm-usecase crm-reveal-stagger"><span>' + escapeHtml(item.label) + '</span><h3>' + escapeHtml(item.title) + '</h3><p>' + escapeHtml(item.text) + '</p></article>';
      }).join('') + '</div>');
  }

  function renderHowItWorks(data) {
    var commands = (data.howItWorks.commands || []).map(function (command) {
      return '<div><span class="prompt">$</span> <span class="cmd">' + escapeHtml(command) + '</span></div>';
    }).join('');
    var copyValue = escapeHtml((data.howItWorks.commands || []).join(' && '));

    return section('how-it-works', 'crm-section-soft', intro(data.howItWorks, false, false) +
      '<div class="crm-how-grid"><div class="crm-steps">' + (data.howItWorks.steps || []).map(function (step, index) {
        return '<article class="crm-reveal-stagger"><span>' + (index + 1) + '</span><h3>' + escapeHtml(step.title) + '</h3><p>' + escapeHtml(step.text) + '</p></article>';
      }).join('') + '</div><div class="crm-code crm-reveal">' + commands + '<button class="crm-code-copy" data-copy="' + copyValue + '" aria-label="' + escapeHtml(data.ui.copy) + '">' + escapeHtml(data.ui.copy) + '</button><p>' + escapeHtml(data.howItWorks.note) + '</p></div></div>');
  }

  function renderAudience(data) {
    return section('audience', '', '<div class="crm-audience-grid">' +
      '<article class="crm-audience crm-reveal"><span>' + escapeHtml(data.businessOwners.label) + '</span><h2>' + escapeHtml(data.businessOwners.title) + '</h2><p>' + escapeHtml(data.businessOwners.text) + '</p><ul>' + data.businessOwners.items.map(function (item) { return '<li>' + escapeHtml(item) + '</li>'; }).join('') + '</ul></article>' +
      '<article class="crm-audience crm-reveal"><span>' + escapeHtml(data.technicalUsers.label) + '</span><h2>' + escapeHtml(data.technicalUsers.title) + '</h2><p>' + escapeHtml(data.technicalUsers.text) + '</p><ul>' + data.technicalUsers.items.map(function (item) { return '<li>' + escapeHtml(item) + '</li>'; }).join('') + '</ul></article>' +
      '</div>');
  }

  function renderFAQ(data) {
    return section('faq', 'crm-section-soft', intro(data.faq, true, false) +
      '<div class="crm-faq">' + (data.faq.items || []).map(function (item, index) {
        return '<article class="crm-faq-item crm-reveal-stagger"><button class="crm-faq-q" aria-expanded="false" data-faq="' + index + '">' + escapeHtml(item.q) + '</button><div class="crm-faq-a" data-faq-a="' + index + '"><p>' + escapeHtml(item.a) + '</p></div></article>';
      }).join('') + '</div>');
  }

  function renderFinalCta(data) {
    return '<section class="crm-section-cta" id="cta"><div class="crm-container crm-reveal"><span class="crm-section-label">' + escapeHtml(data.finalCta.label) + '</span><h2>' + escapeHtml(data.finalCta.title) + '</h2><p>' + escapeHtml(data.finalCta.text) + '</p><div class="crm-section-actions"><a href="' + GITHUB_URL + '" class="crm-btn crm-btn-light" target="_blank" rel="noopener">' + escapeHtml(data.finalCta.ctaPrimary) + '</a><a href="#contact-form" class="crm-btn crm-btn-outline">' + escapeHtml(data.finalCta.ctaSecondary) + '</a></div></div></section>';
  }

  function renderFooter(data) {
    var links = data.footer.links || {};
    return '<footer class="crm-footer"><div class="crm-container"><div class="crm-footer-inner"><span class="crm-footer-brand">' + escapeHtml(data.footer.text) + '</span><nav class="crm-footer-links" aria-label="' + escapeHtml(data.footer.ariaLabel) + '"><a href="' + GITHUB_URL + '" target="_blank" rel="noopener">' + escapeHtml(links.github) + '</a><a href="' + DOCS_URL + '" target="_blank" rel="noopener">' + escapeHtml(links.docs) + '</a><a href="' + CONTACT_URL + '" target="_blank" rel="noopener">' + escapeHtml(links.contact) + '</a></nav></div></div></footer>';
  }

  function initInteractions(data) {
    bindLanguageButtons();
    bindMobileMenu(data);
    bindFaq();
    bindTabs();
    bindReveal();
    bindHeaderState();
    bindSectionNav();
    bindCopy(data);
    bindLeadForm();
    bindPointerHighlights();
    bindSmoothNav();
    injectFaqSchema(data);
  }

  function bindLanguageButtons() {
    document.querySelectorAll('.crm-lang-btn').forEach(function (button) {
      button.addEventListener('click', function () {
        loadLanguage(button.getAttribute('data-lang'));
      });
    });
  }

  function bindMobileMenu(data) {
    var button = document.getElementById('hamburgerBtn');
    var overlay = document.getElementById('mobileOverlay');
    if (!button || !overlay) return;

    var nav = data.nav || {};
    overlay.innerHTML = '<div class="crm-mobile-menu-content">' + (nav.items || []).map(function (item) {
      return '<a href="' + escapeHtml(item.href) + '">' + escapeHtml(item.label) + '</a>';
    }).join('') + '</div>';

    button.onclick = function () {
      toggleMobileMenu(button, overlay, nav);
    };
    overlay.onclick = function (event) {
      if (event.target === overlay) toggleMobileMenu(button, overlay, nav);
    };
    overlay.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        toggleMobileMenu(button, overlay, nav);
      });
    });
  }

  function toggleMobileMenu(button, overlay, nav) {
    var isOpen = button.classList.contains('open');
    button.classList.toggle('open', !isOpen);
    overlay.classList.toggle('open', !isOpen);
    button.setAttribute('aria-expanded', String(!isOpen));
    button.setAttribute('aria-label', isOpen ? nav.hamburgerAria : nav.hamburgerCloseAria);
    document.body.style.overflow = isOpen ? '' : 'hidden';
  }

  function bindFaq() {
    document.querySelectorAll('.crm-faq-q').forEach(function (button) {
      button.addEventListener('click', function () {
        var id = button.getAttribute('data-faq');
        var answer = document.querySelector('[data-faq-a="' + id + '"]');
        var open = button.classList.contains('open');
        button.classList.toggle('open', !open);
        button.setAttribute('aria-expanded', String(!open));
        if (answer) answer.classList.toggle('open', !open);
      });
    });
  }

  function bindTabs() {
    document.querySelectorAll('.crm-tab-btn').forEach(function (button) {
      button.addEventListener('click', function () {
        var id = button.getAttribute('data-tab');
        document.querySelectorAll('.crm-tab-btn').forEach(function (item) { item.classList.toggle('active', item === button); });
        document.querySelectorAll('.crm-tab-panel').forEach(function (panel) { panel.classList.toggle('active', panel.getAttribute('data-panel') === id); });
      });
    });
  }

  function bindReveal() {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) entry.target.classList.add('visible');
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -24px 0px' });
    document.querySelectorAll('.crm-reveal,.crm-reveal-stagger').forEach(function (node) { observer.observe(node); });
  }

  function bindHeaderState() {
    var header = document.getElementById('crmHeader');
    if (!header) return;
    var update = function () { header.classList.toggle('scrolled', window.scrollY > 24); };
    window.addEventListener('scroll', update);
    update();
  }

  function bindSectionNav() {
    var dots = document.querySelectorAll('.crm-section-nav-dot');
    if (!dots.length) return;
    var sections = [];
    dots.forEach(function (dot) {
      var target = document.getElementById(dot.getAttribute('data-target'));
      if (target) sections.push({ dot: dot, target: target });
      dot.addEventListener('click', function () {
        if (target) target.scrollIntoView({ behavior: 'smooth' });
      });
    });
    var update = function () {
      var marker = window.scrollY + window.innerHeight / 3;
      var active = sections[0];
      sections.forEach(function (item) {
        if (marker >= item.target.offsetTop) active = item;
      });
      dots.forEach(function (dot) { dot.classList.remove('active'); });
      if (active) active.dot.classList.add('active');
    };
    window.addEventListener('scroll', update);
    update();
  }

  function bindCopy(data) {
    var button = document.querySelector('.crm-code-copy');
    if (!button) return;
    button.addEventListener('click', function () {
      var value = button.getAttribute('data-copy') || '';
      var done = function () {
        button.textContent = data.ui.copied;
        button.classList.add('copied');
        setTimeout(function () {
          button.textContent = data.ui.copy;
          button.classList.remove('copied');
        }, 1800);
      };
      if (navigator.clipboard) navigator.clipboard.writeText(value).then(done);
    });
  }

  function bindSmoothNav() {
    document.querySelectorAll('.crm-nav-link,.crm-mobile-menu-content a,.crm-hero-actions a[href^="#"],.crm-section-actions a[href^="#"]').forEach(function (link) {
      link.addEventListener('click', function (event) {
        var href = link.getAttribute('href');
        if (!href || href.charAt(0) !== '#') return;
        event.preventDefault();
        var target = document.querySelector(href);
        if (target) target.scrollIntoView({ behavior: 'smooth' });
      });
    });
  }

  function bindPointerHighlights() {
    if (window.matchMedia && window.matchMedia('(pointer: coarse)').matches) return;
    document.querySelectorAll('.crm-card,.crm-open-item,.crm-usecase,.crm-tabs,.crm-lead-panel').forEach(function (node) {
      node.addEventListener('pointermove', function (event) {
        var rect = node.getBoundingClientRect();
        node.style.setProperty('--mx', (event.clientX - rect.left) + 'px');
        node.style.setProperty('--my', (event.clientY - rect.top) + 'px');
      });
      node.addEventListener('pointerleave', function () {
        node.style.removeProperty('--mx');
        node.style.removeProperty('--my');
      });
    });
  }

  function bindLeadForm() {
    var form = document.querySelector('.crm-lead-form');
    if (!form) return;
    var status = form.querySelector('.crm-form-status');
    var button = form.querySelector('.crm-form-submit');
    var query = new URLSearchParams(window.location.search);
    if (query.get('sent') === '1' || query.get('contact') === 'success') {
      form.classList.add('is-success');
      if (status) status.textContent = form.getAttribute('data-success') || '';
    }

    form.querySelectorAll('input,textarea').forEach(function (control) {
      control.addEventListener('input', function () {
        clearFieldError(form, control.name);
      });
    });

    form.addEventListener('submit', function (event) {
      var errors = validateLeadForm(form);
      if (errors.length) {
        event.preventDefault();
        if (status) status.textContent = errors[0].message;
        var first = form.querySelector('[name="' + errors[0].name + '"]');
        if (first) first.focus();
        return;
      }

      if ((form.querySelector('[name="website"]') || {}).value) {
        event.preventDefault();
        return;
      }

      event.preventDefault();
      form.classList.add('is-submitting');
      if (button) {
        button.disabled = true;
        button.textContent = form.getAttribute('data-sending') || button.textContent;
      }
      openPrefilledIssue(form, status, button);
    });
  }

  function openPrefilledIssue(form, status, button) {
    var name = (form.querySelector('[name="name"]') || {}).value || '';
    var email = (form.querySelector('[name="email"]') || {}).value || '';
    var company = (form.querySelector('[name="company"]') || {}).value || '';
    var message = (form.querySelector('[name="message"]') || {}).value || '';
    var title = lang === 'ru' ? 'Запрос внедрения TropaTT' : (lang === 'zh' ? 'TropaTT 实施请求' : 'TropaTT implementation request');
    var body = [
      'Name: ' + name.trim(),
      'Email: ' + email.trim(),
      'Company: ' + company.trim(),
      '',
      'Request:',
      message.trim()
    ].join('\\n');
    var url = CONTACT_URL + '?title=' + encodeURIComponent(title) + '&body=' + encodeURIComponent(body);
    form.classList.remove('is-submitting');
    form.classList.add('is-success');
    if (button) {
      button.disabled = false;
      button.textContent = button.getAttribute('data-label') || button.textContent;
    }
    if (status) {
      status.textContent = '';
      var link = document.createElement('a');
      link.href = url;
      link.target = '_blank';
      link.rel = 'noopener';
      link.textContent = form.getAttribute('data-issue-link') || form.getAttribute('data-success') || '';
      status.appendChild(document.createTextNode((form.getAttribute('data-success') || '') + ' '));
      status.appendChild(link);
    }
  }

  function validateLeadForm(form) {
    var errors = [];
    form.querySelectorAll('.crm-field-error').forEach(function (error) { error.textContent = ''; });
    ['name', 'email', 'message'].forEach(function (name) {
      var control = form.querySelector('[name="' + name + '"]');
      if (!control || String(control.value || '').trim()) return;
      setFieldError(form, name, form.getAttribute('data-required') || '');
      errors.push({ name: name, message: form.getAttribute('data-required') || '' });
    });
    var email = form.querySelector('[name="email"]');
    if (email && email.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
      setFieldError(form, 'email', form.getAttribute('data-email-error') || '');
      errors.push({ name: 'email', message: form.getAttribute('data-email-error') || '' });
    }
    return errors;
  }

  function setFieldError(form, name, message) {
    var control = form.querySelector('[name="' + name + '"]');
    var error = form.querySelector('[data-error-for="' + name + '"]');
    if (control) control.setAttribute('aria-invalid', 'true');
    if (error) error.textContent = message;
  }

  function clearFieldError(form, name) {
    var control = form.querySelector('[name="' + name + '"]');
    var error = form.querySelector('[data-error-for="' + name + '"]');
    if (control) control.removeAttribute('aria-invalid');
    if (error) error.textContent = '';
    form.classList.remove('has-error');
    var status = form.querySelector('.crm-form-status');
    if (status) status.textContent = '';
  }

  function injectFaqSchema(data) {
    var existing = document.querySelector('script[data-schema="faq"]');
    if (existing) existing.remove();
    var items = (data.faq && data.faq.items) || [];
    if (!items.length) return;
    var mainEntity = items.map(function (item) {
      return { "@type": "Question", "name": item.q, "acceptedAnswer": { "@type": "Answer", "text": item.a } };
    });
    var schema = { "@context": "https://schema.org", "@type": "FAQPage", "mainEntity": mainEntity };
    var script = document.createElement('script');
    script.type = 'application/ld+json';
    script.setAttribute('data-schema', 'faq');
    script.textContent = JSON.stringify(schema);
    document.head.appendChild(script);
  }

  function loadLanguage(nextLang) {
    if (SUPPORTED.indexOf(nextLang) === -1) nextLang = 'en';
    try { window.history.replaceState(null, '', '?lang=' + nextLang); } catch (_) {}
    var request = new XMLHttpRequest();
    request.open('GET', 'content/' + nextLang + '.json?v=20260531', true);
    request.onload = function () {
      if (request.status < 200 || request.status >= 300) return renderError();
      try {
        var data = JSON.parse(request.responseText);
        renderNav(data);
        setLanguageState(nextLang);
        updateMeta(data);
        render(data);
      } catch (error) {
        renderError();
      }
    };
    request.onerror = renderError;
    request.send();
  }

  function renderError() {
    document.getElementById('landing-content').innerHTML = '<section class="crm-section"><div class="crm-container"><p>Content loading error.</p></div></section>';
  }

  loadLanguage(detectLanguage());
}());
