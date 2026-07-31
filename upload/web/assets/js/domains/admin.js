/**
 * Domain module: Admin pages
 * Extracted from page-api-bindings.js for maintainability.
 * 
 * Pages: admin-users, admin-roles, admin-logs, admin-api-clients, admin-settings,
 *        admin-jobs, admin-ai, admin-statuses, admin-workflow, admin-sla,
 *        admin-custom-fields, admin-priorities, admin-calendar, admin-templates,
 *        admin-tags, admin-webhooks
 */

window.CRM = window.CRM || {};
window.CRM.domains = window.CRM.domains || {};

(function () {
  'use strict';

  // Delegate to page-api-bindings.js functions
  // This module acts as a namespace registry for admin page renderers
  
  var adminRoutes = [
    'admin',
    'admin-users',
    'admin-roles',
    'admin-logs',
    'admin-api-clients',
    'admin-settings',
    'admin-jobs',
    'admin-ai',
    'admin-statuses',
    'admin-workflow',
    'admin-sla',
    'admin-custom-fields',
    'admin-priorities',
    'admin-calendar',
    'admin-templates',
    'admin-tags',
    'admin-webhooks'
  ];

  /**
   * Register admin routes with the navigation system.
   * Called during app initialization.
   */
  function register() {
    if (window.CRM.pageApiBindings && typeof window.CRM.pageApiBindings.registerDomain === 'function') {
      window.CRM.pageApiBindings.registerDomain('admin', adminRoutes);
    }
  }

  window.CRM.domains.admin = {
    routes: adminRoutes,
    register: register
  };
})();
