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

// ---------------------------------------------------------------------------
// Test-database drift guard (#9769)
// ---------------------------------------------------------------------------
//
// Several specs used to create events and notes and never remove them, so a
// local dev database grew by dozens of events and hundreds of notes per run
// (CI never noticed because every job seeds a fresh database). These hooks
// snapshot a handful of row counts before each spec file and fail the spec
// file if it finished with more rows than it started with.
//
// The counts come from the `rowCounts` task registered by
// cypress/configs/row-count-guard.ts. Only the three docker configs register
// it; the new-system / locale / upgrade configs deliberately do not, because
// those suites reseed the database or import demo data on purpose. When the
// task is absent (or the database is unreachable) the guard disables itself.
//
// A spec that genuinely cannot restore a table declares the shortfall itself:
//
//   before(() => {
//       cy.allowRowDrift("family_fam", 1, "reason the row cannot be removed");
//   });
//
// Anything beyond a declared allowance fails, naming the table and the delta.
//
// note_nte is the one exception: it is an append-only timeline that the
// application writes to on nearly every person/family write (an edit, a photo
// upload, a check-in, even deleting a note leaves a `delete-note` row), and no
// endpoint removes those rows. Growth there is reported in the Cypress log but
// never fails a spec — see SOFT_TABLES in cypress/configs/row-count-guard.ts.

const rowGuard = {
    enabled: false,
    baseline: null,
    allowances: {},
};

Cypress.Commands.add("allowRowDrift", (table, maxDelta, reason) => {
    if (!reason) {
        throw new Error(
            `cy.allowRowDrift("${table}", ${maxDelta}) needs a reason — the allowance is a documented exception, not a mute button`,
        );
    }
    rowGuard.allowances[table] = { maxDelta, reason };
});

before(function () {
    rowGuard.enabled = false;
    rowGuard.baseline = null;
    rowGuard.allowances = {};

    if (!Cypress.env("rowCountGuard")) {
        return;
    }

    cy.task("rowCounts", null, { log: false }).then((result) => {
        if (!result || !result.available) {
            // Fail-soft: a database we cannot reach must not fail every spec.
            cy.log(`Row-count guard disabled: ${result ? result.reason : "no result"}`);
            return;
        }
        rowGuard.enabled = true;
        rowGuard.baseline = result.counts;
    });
});

after(function () {
    if (!rowGuard.enabled || !rowGuard.baseline) {
        return;
    }

    const baseline = rowGuard.baseline;
    const allowances = rowGuard.allowances;

    cy.task("rowCounts", null, { log: false }).then((result) => {
        if (!result || !result.available) {
            return;
        }

        const softTables = result.softTables || [];
        const offenders = [];
        const reported = [];
        Object.keys(baseline).forEach((table) => {
            const delta = result.counts[table] - baseline[table];
            const allowance = allowances[table];
            const allowed = allowance ? allowance.maxDelta : 0;
            if (delta <= allowed) {
                return;
            }
            const description =
                `${table}: +${delta} row(s) (${baseline[table]} -> ${result.counts[table]}` +
                (allowance ? `, declared allowance ${allowed}: ${allowance.reason}` : "") +
                ")";
            if (softTables.includes(table)) {
                reported.push(description);
            } else {
                offenders.push(description);
            }
        });

        // Cypress.log() writes to the command log synchronously. cy.log() only
        // *enqueues* a command, and everything enqueued inside this callback is
        // abandoned the instant the throw below fires — so a spec that tripped a
        // soft and a hard table in the same run used to lose the soft
        // diagnostics entirely.
        reported.forEach((description) => {
            Cypress.log({
                name: "row-count-guard",
                displayName: "row-count-guard",
                message: `reported, not failed: ${description}`,
            });
        });

        if (offenders.length > 0) {
            // The soft-table drift is repeated here as well: the command log is
            // easy to miss next to a failure, and the error message is what ends
            // up in CI output.
            const alsoReported =
                reported.length > 0
                    ? `Also reported (soft tables, not failed):\n${reported.join("\n")}\n`
                    : "";
            throw new Error(
                `This spec file left rows behind in the test database (#9769).\n` +
                    `${offenders.join("\n")}\n` +
                    alsoReported +
                    "Delete what the spec creates in an after()/afterEach() hook, or declare the " +
                    "shortfall with cy.allowRowDrift(table, maxDelta, reason).",
            );
        }
    });
});
