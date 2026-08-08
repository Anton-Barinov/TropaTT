window.CRM = window.CRM || {};
window.CRM.text = (function () {
  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function safeText(value) {
    return escapeHtml(value);
  }

  function stripHtml(value) {
    // Visual-editor fields (e.g. task/cycle descriptions) store HTML like
    // "<p>text</p>". For compact previews we want plain text: strip tags,
    // decode common entities, normalize whitespace, then the caller escapes
    // for display.
    return String(value || '')
      .replace(/<[^>]*>/g, ' ')
      .replace(/&nbsp;/gi, ' ')
      .replace(/&lt;/g, '<')
      .replace(/&gt;/g, '>')
      .replace(/&quot;/g, '"')
      .replace(/&#0?39;/g, "'")
      .replace(/&apos;/g, "'")
      .replace(/&amp;/g, '&')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function pluralRu(count, one, few, many) {
    var n = Math.abs(Number(count) || 0);
    var mod10 = n % 10;
    var mod100 = n % 100;
    if (mod10 === 1 && mod100 !== 11) return one;
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return few;
    return many;
  }

  return {
    escapeHtml: escapeHtml,
    safeText: safeText,
    stripHtml: stripHtml,
    pluralRu: pluralRu
  };
})();

