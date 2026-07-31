/**
 * Domain module: Tasks
 * Extracted from page-api-bindings.js for maintainability.
 * 
 * Pages: tasks, task-detail, my-day, my-week, kanban
 */

window.CRM = window.CRM || {};
window.CRM.domains = window.CRM.domains || {};

(function () {
  'use strict';

  var taskRoutes = [
    'tasks',
    'task-detail',
    'my-day',
    'my-week',
    'kanban'
  ];

  function register() {
    if (window.CRM.pageApiBindings && typeof window.CRM.pageApiBindings.registerDomain === 'function') {
      window.CRM.pageApiBindings.registerDomain('tasks', taskRoutes);
    }
  }

  window.CRM.domains.tasks = {
    routes: taskRoutes,
    register: register
  };
})();
