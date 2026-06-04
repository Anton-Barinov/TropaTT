window.CRM = window.CRM || {};
window.CRM.lists = (function () {
  function tableBody(tableSelector) {
    var tbody = document.querySelector(tableSelector + ' tbody') || document.querySelector(tableSelector);
    return tbody || null;
  }

  function setTableBody(tableSelector) {
    return tableBody(tableSelector) !== null;
  }

  function selectedIds(root, selector, attribute) {
    var scope = root && typeof root.querySelectorAll === 'function' ? root : document;
    var attr = String(attribute || 'value');
    return Array.from(scope.querySelectorAll(selector || 'input[type="checkbox"]:checked')).map(function (node) {
      if (attr === 'value') return String(node.value || '').trim();
      return String(node.getAttribute(attr) || '').trim();
    }).filter(Boolean);
  }

  return {
    tableBody: tableBody,
    setTableBody: setTableBody,
    selectedIds: selectedIds
  };
})();
