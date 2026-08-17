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
  // FC v7 fires a benign ResizeObserver notification in some CI environments
  // (Chrome/Electron). The notification is not an error; suppress it so it
  // doesn't fail unrelated calendar tests.
  if (message.includes('ResizeObserver loop')) {
    return false;
  }
  // Bootstrap 5.3.x transitionComplete / dispose() race: cleanup() calls
  // dispose() synchronously (bypassing Bootstrap's hide animation). Bootstrap's
  // BaseComponent.dispose() immediately nullifies all own properties including
  // this._config. A ~330 ms fallback timer queued by _showElement() then fires
  // transitionComplete, which reads this._config.focus and throws:
  //   TypeError: Cannot read properties of null (reading 'focus')
  // This is benign — the modal has already been fully torn down — but Cypress
  // catches it as an uncaught exception and fails the next test in the describe
  // block. This filter is the operative fix for the observed CI failures;
  // the blur-before-dispose guard in calendar-event-editor.js addresses a
  // separate (but related) _handleFocusin document-listener race.
  // Traceable to standard.calendar.spec.js "save (admin-session)" CI failures:
  // runs 31972908013, 31975974494, 31978700022 (jobs 95230541100, 95236069435,
  // 95242696285). The Bootstrap-internal transitionComplete path is unlikely to
  // change without a Bootstrap version bump; revisit if Bootstrap 6 is adopted.
  // TODO(cypress-noise): also investigate replacing cleanup()'s synchronous
  // dispose() with Bootstrap's normal hide() flow so the timer never fires
  // against a null _config — see calendar-event-editor.js cleanup().
  // Anchored to the exact V8 null-property-read message so a genuine app-code
  // bug such as `document.getElementById('x').focus()` where getElementById
  // returns null is still swallowed here — but ONLY if its message is exactly
  // this string. Note: Option B (stack-trace 'bootstrap' guard) was not used
  // because Bootstrap is webpack-bundled into skin-core.*.js, so the stack
  // frame filename does not contain 'bootstrap' and the check would never fire.
  if (/^Cannot read properties of null \(reading 'focus'\)$/.test(message)) {
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
