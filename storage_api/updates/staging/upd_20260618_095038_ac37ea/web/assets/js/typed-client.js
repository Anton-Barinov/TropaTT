/**
 * Typed API Client Wrapper
 * Provides JSDoc-typed methods for all API endpoints.
 * 
 * Usage:
 *   var client = window.CRM.typedClient;
 *   var tasks = await client.tasks.list({ limit: 20 });
 *   var task = await client.tasks.get('task-public-id');
 */

window.CRM = window.CRM || {};

/**
 * @typedef {Object} ApiEnvelope
 * @property {boolean} success
 * @property {string} code
 * @property {string} message
 * @property {Object|null} data
 * @property {Object|Array} errors
 * @property {Object} meta
 */

/**
 * @typedef {Object} RequestOptions
 * @property {string} [method] - HTTP method
 * @property {Object} [headers] - Custom headers
 * @property {Object} [body] - Request body
 * @property {Object} [query] - Query parameters
 * @property {boolean} [auth] - Use auth (default: true)
 * @property {boolean} [silent] - Suppress error notifications
 * @property {number} [timeoutMs] - Timeout in milliseconds
 */

(function () {
  'use strict';

  var api = window.CRM.api;
  if (!api || typeof api.request !== 'function') {
    console.warn('[typed-client] CRM.api not available');
    window.CRM.typedClient = null;
    return;
  }

  /**
   * @param {string} route
   * @param {RequestOptions} [options]
   * @returns {Promise<ApiEnvelope>}
   */
  function request(route, options) {
    return api.request(route, options || {});
  }

  /**
   * @param {string} route
   * @param {RequestOptions} [options]
   * @returns {Promise<ApiEnvelope>}
   */
  function tryRequest(route, options) {
    var opts = options || {};
    opts.silent = true;
    return request(route, opts).catch(function (error) {
      return {
        success: false,
        code: error && error.envelope ? error.envelope.code : 'REQUEST_FAILED',
        message: error && error.message ? error.message : 'Error',
        data: null,
        errors: [],
        meta: {}
      };
    });
  }

  /**
   * @param {ApiEnvelope} envelope
   * @returns {Array}
   */
  function items(envelope) {
    return api.items(envelope);
  }

  // Domain-specific clients

  var tasksClient = {
    /**
     * @param {Object} [params]
     * @returns {Promise<ApiEnvelope>}
     */
    list: function (params) {
      return request('api/v1/tasks', { query: params || {} });
    },
    /**
     * @param {string} publicId
     * @returns {Promise<ApiEnvelope>}
     */
    get: function (publicId) {
      return request('api/v1/tasks/' + encodeURIComponent(publicId));
    },
    /**
     * @param {Object} data
     * @returns {Promise<ApiEnvelope>}
     */
    create: function (data) {
      return request('api/v1/tasks', { method: 'POST', body: data });
    },
    /**
     * @param {string} publicId
     * @param {Object} data
     * @returns {Promise<ApiEnvelope>}
     */
    update: function (publicId, data) {
      return request('api/v1/tasks/' + encodeURIComponent(publicId), { method: 'PATCH', body: data });
    },
    /**
     * @param {string} publicId
     * @returns {Promise<ApiEnvelope>}
     */
    delete: function (publicId) {
      return request('api/v1/tasks/' + encodeURIComponent(publicId), { method: 'DELETE' });
    },
    /**
     * @param {Object} data
     * @returns {Promise<ApiEnvelope>}
     */
    bulk: function (data) {
      return request('api/v1/tasks/bulk', { method: 'POST', body: data });
    }
  };

  var projectsClient = {
    list: function (params) { return request('api/v1/projects', { query: params || {} }); },
    get: function (publicId) { return request('api/v1/projects/' + encodeURIComponent(publicId)); },
    create: function (data) { return request('api/v1/projects', { method: 'POST', body: data }); },
    update: function (publicId, data) { return request('api/v1/projects/' + encodeURIComponent(publicId), { method: 'PATCH', body: data }); },
    delete: function (publicId) { return request('api/v1/projects/' + encodeURIComponent(publicId), { method: 'DELETE' }); }
  };

  var usersClient = {
    list: function (params) { return request('api/v1/users', { query: params || {} }); },
    get: function (publicId) { return request('api/v1/users/' + encodeURIComponent(publicId)); },
    create: function (data) { return request('api/v1/users', { method: 'POST', body: data }); },
    update: function (publicId, data) { return request('api/v1/users/' + encodeURIComponent(publicId), { method: 'PATCH', body: data }); },
    delete: function (publicId) { return request('api/v1/users/' + encodeURIComponent(publicId), { method: 'DELETE' }); }
  };

  var authClient = {
    login: function (login, password) { return api.login(login, password); },
    logout: function () { return api.logout(); },
    me: function () { return api.me(); }
  };

  var notificationsClient = {
    list: function (params) { return request('api/v1/notifications', { query: params || {} }); },
    counters: function () { return request('api/v1/notifications/counters'); },
    markRead: function (publicId) { return request('api/v1/notifications/' + encodeURIComponent(publicId), { method: 'PATCH', body: { is_read: true } }); },
    markAllRead: function () { return request('api/v1/notifications/mark-all-read', { method: 'POST' }); }
  };

  var commentsClient = {
    list: function (taskId) { return request('api/v1/comments', { query: { task_public_id: taskId } }); },
    create: function (data) { return request('api/v1/comments', { method: 'POST', body: data }); },
    update: function (publicId, data) { return request('api/v1/comments/' + encodeURIComponent(publicId), { method: 'PATCH', body: data }); },
    delete: function (publicId) { return request('api/v1/comments/' + encodeURIComponent(publicId), { method: 'DELETE' }); }
  };

  var filesClient = {
    list: function (taskId) { return request('api/v1/files', { query: { task_public_id: taskId } }); },
    upload: function (formData) { return request('api/v1/files', { method: 'POST', body: formData }); },
    delete: function (publicId) { return request('api/v1/files/' + encodeURIComponent(publicId), { method: 'DELETE' }); }
  };

  // Main typed client

  window.CRM.typedClient = {
    request: request,
    tryRequest: tryRequest,
    items: items,
    tasks: tasksClient,
    projects: projectsClient,
    users: usersClient,
    auth: authClient,
    notifications: notificationsClient,
    comments: commentsClient,
    files: filesClient
  };
})();
