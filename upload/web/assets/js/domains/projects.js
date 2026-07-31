/**
 * Domain module: Projects
 * Extracted from page-api-bindings.js for maintainability.
 * 
 * Pages: projects, project-detail, gantt
 */

window.CRM = window.CRM || {};
window.CRM.domains = window.CRM.domains || {};

(function () {
  'use strict';

  var projectRoutes = [
    'projects',
    'project-detail',
    'gantt'
  ];

  function register() {
    if (window.CRM.pageApiBindings && typeof window.CRM.pageApiBindings.registerDomain === 'function') {
      window.CRM.pageApiBindings.registerDomain('projects', projectRoutes);
    }
  }

  window.CRM.domains.projects = {
    routes: projectRoutes,
    register: register
  };
})();
