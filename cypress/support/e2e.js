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
// file if it finished with a different number of rows than it started with:
// more rows means the spec leaked, fewer rows means it deleted something it
// did not create.
//
// The counts come from the `rowCounts` task registered by
// cypress/configs/row-count-guard.ts. Only the three docker configs register
// it; the new-system / locale / upgrade configs deliberately do not, because
// those suites reseed the database or import demo data on purpose.
//
// A snapshot that cannot be taken is a failure, not a pass: a guard that
// quietly switches itself off proves nothing about cleanup. The error names
// the host/port/database it tried and how to point it at the right one
// (ROW_GUARD_DB_PORT). A local run may opt out explicitly with
// ROW_GUARD_DISABLED=1; CI sets ROW_GUARD_REQUIRED=1, which ignores the opt-out.
//
// A spec that genuinely cannot restore a table declares the shortfall itself:
//
//   before(() => {
//       cy.allowRowDrift("family_fam", 1, "reason the row cannot be removed");
//   });
//
// An allowance only ever covers growth: the accepted delta is [0, maxDelta].
// Anything above it fails, naming the table and the delta; a negative delta
// always fails, because it means seeded rows are gone.
//
// note_nte is the one exception on the growth side: it is an append-only
// timeline that the application writes to on nearly every person/family write
// (an edit, a photo upload, a check-in, even deleting a note leaves a
// `delete-note` row), and no endpoint removes those rows. Growth there is
// reported — in the Cypress command log and on the terminal — but never fails
// a spec. See SOFT_TABLES in cypress/configs/row-count-guard.ts.
//
// Limitation: COUNT(*) is an aggregate. A spec that deleted one seeded row
// and leaked one of its own would show a delta of 0 and pass. The
// cy.cleanup*() helpers close that gap for the rows they know about by
// re-reading every id after deleting it, and specs only pass them ids they
// created; the guard itself cannot tell one row from another.

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
    if (!Number.isInteger(maxDelta) || maxDelta < 0) {
        throw new Error(
            `cy.allowRowDrift("${table}", ${maxDelta}) — maxDelta must be a non-negative integer; an allowance covers growth only, never the removal of seeded rows`,
        );
    }
    rowGuard.allowances[table] = { maxDelta, reason };
});

/** The connection error the guard raises when a snapshot cannot be taken. */
function snapshotUnavailableError(which, result) {
    const reason = result ? result.reason : "the rowCounts task returned nothing";
    const hint = result && result.hint ? ` ${result.hint}` : "";
    return new Error(
        `Row-count guard (#9769) could not take its ${which} snapshot of the test database: ${reason}.${hint}`,
    );
}

/**
 * Compare the closing counts with the baseline.
 *
 * @returns {{offenders: string[], reported: string[]}} offenders fail the spec,
 *   reported (soft-table growth) is logged only.
 */
function evaluateDrift(baseline, counts, softTables, allowances) {
    const offenders = [];
    const reported = [];
    Object.keys(baseline).forEach((table) => {
        const before = baseline[table];
        const after = counts[table];
        const delta = after - before;
        const allowance = allowances[table];
        const allowed = allowance ? allowance.maxDelta : 0;

        if (delta < 0) {
            offenders.push(
                `${table}: ${delta} row(s) (${before} -> ${after}) — rows that existed before this spec ` +
                    "were deleted; a spec may only remove what it created",
            );
            return;
        }
        if (delta <= allowed) {
            return;
        }
        const description =
            `${table}: +${delta} row(s) (${before} -> ${after}` +
            (allowance ? `, declared allowance ${allowed}: ${allowance.reason}` : "") +
            ")";
        if (softTables.includes(table)) {
            reported.push(description);
        } else {
            offenders.push(description);
        }
    });
    return { offenders, reported };
}

before(function () {
    rowGuard.enabled = false;
    rowGuard.baseline = null;
    rowGuard.allowances = {};

    if (!Cypress.env("rowCountGuard")) {
        return;
    }

    cy.task("rowCounts", null, { log: false }).then((result) => {
        if (!result || !result.available) {
            throw snapshotUnavailableError("opening", result);
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

    cy.task("rowCounts", null, { log: false })
        .then((result) => {
            if (!result || !result.available) {
                // The opening snapshot succeeded, so the database was reachable
                // when this spec started; losing it now leaves the spec's cleanup
                // unverified, which is a failure of the run, not a pass.
                throw snapshotUnavailableError("closing", result);
            }
            return evaluateDrift(baseline, result.counts, result.softTables || [], allowances);
        })
        .then((verdict) => {
            // Cypress.log() writes to the command log synchronously and cy.task()
            // is enqueued here, *before* the throw in the next .then(), so both
            // survive a hard-table failure in the same spec. (Anything enqueued
            // inside the callback that throws would be abandoned.)
            verdict.reported.forEach((description) => {
                const message = `reported, not failed: ${description}`;
                Cypress.log({
                    name: "row-count-guard",
                    displayName: "row-count-guard",
                    message,
                });
                cy.task("rowGuardLog", `${Cypress.spec.relative}: ${message}`, { log: false });
            });
            return cy.wrap(verdict, { log: false });
        })
        .then((verdict) => {
            if (verdict.offenders.length === 0) {
                return;
            }
            // The soft-table drift is repeated here as well: the command log is
            // easy to miss next to a failure, and the error message is what ends
            // up in CI output.
            const alsoReported =
                verdict.reported.length > 0
                    ? `Also reported (soft tables, not failed):\n${verdict.reported.join("\n")}\n`
                    : "";
            throw new Error(
                `This spec file changed the row counts of the test database (#9769).\n` +
                    `${verdict.offenders.join("\n")}\n` +
                    alsoReported +
                    "Delete what the spec creates in an after()/afterEach() hook (the cy.cleanup*() " +
                    "helpers verify each id is gone), never delete seeded rows, or declare unavoidable " +
                    "growth with cy.allowRowDrift(table, maxDelta, reason).",
            );
        });
});
