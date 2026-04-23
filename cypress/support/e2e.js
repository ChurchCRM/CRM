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
// Logging handled by installLogsPrinter in cypress/configs/_shared.ts (setupCommonNodeEvents)

// Capture unhandled rejections and errors for terminal reporter
window.addEventListener('unhandledrejection', (event) => {
  console.error('Unhandled promise rejection:', event.reason);
  if (event.reason && event.reason.stack) {
    console.error('Stack:', event.reason.stack);
  }
});

// Swallow a noisy unhandled-rejection signature that occasionally bubbles out
// of page-init JS on the login / forced-password-change / church-info flow and
// fails unrelated PRs (e.g. `.github/`-only diffs). The message has the form
// "An unknown error has occurred: [object Object]" — the `[object Object]`
// tail is the tell that an Error-like object was stringified into a template
// literal somewhere in app or third-party JS. The test's real assertions
// still run; only this specific signature is filtered.
//
// TODO(cypress-noise): remove this filter once the source of the
// "[object Object]" stringification is identified and fixed. See PR #8738.
Cypress.on('uncaught:exception', (err) => {
  // Anchor the match with ^…$ so only the exact signature is swallowed — any
  // real error that happens to contain this substring still fails the test.
  const message = (err?.message ?? String(err ?? '')).trim();
  if (/^An unknown error has occurred:\s*\[object Object\]$/.test(message)) {
    return false;
  }
  // Dashboard widget API calls (cart, familiesInCart) fire immediately on page
  // load and return 500 when the PHP session is in a transitional state during
  // system-reset tests. These 500s do not affect the test's real assertions.
  if (
    /"status"\s*:\s*500/.test(message) &&
    /api\/cart\/|api\/families\/familiesInCart/.test(message)
  ) {
    return false;
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
