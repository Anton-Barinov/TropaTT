/**
 * Domain modules index
 * Loads all domain-specific page renderers.
 * 
 * Usage: Include this file instead of loading page-api-bindings.js directly.
 * The domain modules register their routes with the central page router.
 */

(function () {
  'use strict';

  // Domain module files (load order matters for dependencies)
  var domainFiles = [
    'domains/common.js',
    'domains/tasks.js',
    'domains/projects.js',
    'domains/organizations.js',
    'domains/admin.js'
  ];

  /**
   * Load domain modules sequentially
   */
  function loadDomains() {
    var base = getScriptBase();
    domainFiles.forEach(function (file) {
      var script = document.createElement('script');
      script.src = base + file;
      script.async = false;
      document.head.appendChild(script);
    });
  }

  /**
   * Get base URL for script loading
   */
  function getScriptBase() {
    var scripts = document.querySelectorAll('script[src*="page-api-bindings"], script[src*="domains/index"]');
    if (scripts.length > 0) {
      var src = scripts[0].src;
      return src.substring(0, src.lastIndexOf('/') + 1);
    }
    return 'assets/js/';
  }

  // Auto-load domains when script is included
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadDomains);
  } else {
    loadDomains();
  }
})();
