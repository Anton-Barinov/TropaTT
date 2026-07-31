window.CRM = window.CRM || {};
window.CRM.errors = (function () {
  function normalize(error, fallbackMessage) {
    if (window.CRM.api && typeof window.CRM.api.normalizeError === 'function') {
      return window.CRM.api.normalizeError(error, fallbackMessage);
    }

    return {
      code: 'REQUEST_FAILED',
      message: String(fallbackMessage || window.CRM.i18n.t('js.error.request_error', 'Request error')),
      fieldErrors: {},
      retryAfter: '',
      requestId: '',
      correlationId: '',
      status: 0,
      isPermissionError: false,
      isNotFound: false,
      isAborted: false,
      isRateLimited: false,
      isServerError: false
    };
  }

  function format(error, options) {
    if (window.CRM.api && typeof window.CRM.api.formatErrorMessage === 'function') {
      return window.CRM.api.formatErrorMessage(error, options || {});
    }
    return String(error && error.message ? error.message : window.CRM.i18n.t('js.error.request_error', 'Request error'));
  }

  function toUiResult(error, fallbackMessage, options) {
    var normalized = normalize(error, fallbackMessage);
    var opts = options && typeof options === 'object' ? options : {};
    var message = format(normalized, {
      withRequestId: opts.withRequestId !== undefined
        ? !!opts.withRequestId
        : !!(normalized.isServerError || normalized.isRateLimited)
    });

    return {
      success: false,
      code: String(normalized.code || 'REQUEST_FAILED'),
      message: message,
      data: null,
      errors: normalized.fieldErrors || {},
      meta: {
        retry_after: normalized.retryAfter || '',
        request_id: normalized.requestId || '',
        correlation_id: normalized.correlationId || '',
        status: Number(normalized.status || 0) || 0
      },
      ui_error: normalized
    };
  }

  return {
    normalize: normalize,
    format: format,
    toUiResult: toUiResult
  };
})();
