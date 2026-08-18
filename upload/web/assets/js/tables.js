window.CRM = window.CRM || {};
window.CRM.tables = (function () {
  var HSCROLL_SELECTOR = '.table-responsive, .crm-table-wrap';

  function updateScrollHints() {
    document.querySelectorAll(HSCROLL_SELECTOR).forEach(function (el) {
      el.classList.toggle('crm-has-hscroll', el.scrollWidth > el.clientWidth + 1);
    });
  }

  function bindTable(table) {
    if (!table || table.dataset.selectBound === '1') return;
    table.dataset.selectBound = '1';

    var bulk = document.getElementById(table.dataset.bulkTarget || '');

    function update() {
      var rowCheckboxes = Array.from(table.querySelectorAll('tbody input[type="checkbox"]'));
      var selected = rowCheckboxes.filter(function (cb) { return cb.checked; }).length;

      Array.from(table.querySelectorAll('tbody tr')).forEach(function (row) {
        var cb = row.querySelector('input[type="checkbox"]');
        row.classList.toggle('is-selected', !!(cb && cb.checked));
      });

      var master = table.querySelector('thead input[type="checkbox"][data-select-all]');
      if (master) {
        master.checked = rowCheckboxes.length > 0 && selected === rowCheckboxes.length;
        master.indeterminate = selected > 0 && selected < rowCheckboxes.length;
      }

      if (bulk) {
        bulk.classList.toggle('d-none', selected === 0);
        var counter = bulk.querySelector('[data-selected-count]');
        if (counter) counter.textContent = String(selected);
      }
    }

    table.addEventListener('change', function (event) {
      var target = event.target;
      if (!(target instanceof HTMLInputElement) || target.type !== 'checkbox') return;

      if (target.matches('[data-select-all]')) {
        var shouldCheck = !!target.checked;
        table.querySelectorAll('tbody input[type="checkbox"]').forEach(function (cb) {
          cb.checked = shouldCheck;
        });
      }

      update();
    });

    update();
  }

  function init() {
    document.querySelectorAll('[data-select-table]').forEach(bindTable);
    updateScrollHints();
    if (window.requestAnimationFrame && window.MutationObserver) {
      var pending = false;
      var observer = new MutationObserver(function () {
        if (pending) return;
        pending = true;
        window.requestAnimationFrame(function () {
          pending = false;
          updateScrollHints();
        });
      });
      observer.observe(document.body, { childList: true, subtree: true });
    }
    window.addEventListener('resize', updateScrollHints);
  }

  return { init: init };
})();
