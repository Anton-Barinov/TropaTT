/**
 * Domain module: Common pages
 * Extracted from page-api-bindings.js for maintainability.
 * 
 * Pages: dashboard, teams, analytics, notifications, profile, mentions,
 *        approvals, recycle-bin, recurring, calendar
 */

window.CRM = window.CRM || {};
window.CRM.domains = window.CRM.domains || {};

(function () {
  'use strict';

  var commonRoutes = [
    'dashboard',
    'index',
    'teams',
    'analytics',
    'notifications',
    'profile',
    'mentions',
    'approvals',
    'recycle-bin',
    'recurring',
    'calendar'
  ];

  function register() {
    if (window.CRM.pageApiBindings && typeof window.CRM.pageApiBindings.registerDomain === 'function') {
      window.CRM.pageApiBindings.registerDomain('common', commonRoutes);
    }
  }

  window.CRM.domains.common = {
    routes: commonRoutes,
    register: register
  };
})();
