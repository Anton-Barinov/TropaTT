/**
 * Domain module: Organizations
 * Extracted from page-api-bindings.js for maintainability.
 * 
 * Pages: clients, client-detail, client-cabinet, companies, contacts, departments, organizations
 */

window.CRM = window.CRM || {};
window.CRM.domains = window.CRM.domains || {};

(function () {
  'use strict';

  var orgRoutes = [
    'clients',
    'client-detail',
    'client-cabinet',
    'companies',
    'contacts',
    'departments',
    'organizations'
  ];

  function register() {
    if (window.CRM.pageApiBindings && typeof window.CRM.pageApiBindings.registerDomain === 'function') {
      window.CRM.pageApiBindings.registerDomain('organizations', orgRoutes);
    }
  }

  window.CRM.domains.organizations = {
    routes: orgRoutes,
    register: register
  };
})();
