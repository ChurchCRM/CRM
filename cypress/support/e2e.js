// ***********************************************************
// This example support/e2e.js is processed and
// loaded automatically before your test files.
//
// This is a great place to put global configuration and
// behavior that modifies Cypress.
//
// You can change the location of this file or turn off
// automatically serving support files with the
// 'supportFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/configuration
// ***********************************************************

// Initialize cy-verify-downloads plugin - must be done before test specs
require("cy-verify-downloads").addCustomCommand();

// Import commands.js using ES2015 syntax:
import "./ui-commands";
import "./api-commands";

// Alternatively you can use CommonJS syntax:
// require('./commands')

// Note: cypress-terminal-report installLogsCollector disabled due to Cypress 15.x compatibility
// Logging handled by installLogsPrinter in cypress.config.ts

// Capture unhandled rejections and errors for terminal reporter
window.addEventListener('unhandledrejection', (event) => {
  console.error('Unhandled promise rejection:', event.reason);
  if (event.reason && event.reason.stack) {
    console.error('Stack:', event.reason.stack);
  }
});

window.addEventListener('error', (event) => {
  console.error('Unhandled error:', event.error || event.message);
  if (event.error && event.error.stack) {
    console.error('Stack:', event.error.stack);
  }
});

// Hide fetch/XHR requests in Cypress logs for cleaner output
const app = window.top;
if (!app.document.head.querySelector('[data-hide-command-log-request]')) {
  const style = app.document.createElement('style');
  style.innerHTML = '.command-name-request, .command-name-xhr { display: none }';
  style.setAttribute('data-hide-command-log-request', '');
  app.document.head.appendChild(style);
}
