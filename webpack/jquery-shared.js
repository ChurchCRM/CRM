/**
 * Page bundles must use the jQuery that churchcrm.min.js already patched.
 * Each entry otherwise gets a fresh copy, so DataTable and tomselect are missing.
 */
if (!window.jQuery) {
  window.jQuery = window.$ = require("jquery");
}

module.exports = window.jQuery;
