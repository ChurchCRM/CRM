/// <reference types="cypress" />

/**
 * window.CRM.notify() type mapping — regression coverage for issue #9726.
 *
 * src/skin/js/notifier.js branched on "danger", "success" and "warning" only,
 * so type: "error" — used by ~40 call sites — fell through to the info branch
 * and rendered a blue informational toast instead of a red error toast.
 * "error" is now an accepted alias for "danger".
 */
describe("window.CRM.notify() notification types", () => {
    const ERROR_RED = "rgb(237, 61, 61)"; // Notyf default error colour
    const INFO_BLUE = "rgb(23, 162, 184)"; // Bootstrap 4 $info

    /**
     * Fire a notification and resolve the computed background colour of the
     * toast's ripple element (Notyf paints the toast colour there).
     */
    const notifyAndReadColor = (type) => {
        cy.window().then((win) => {
            win.CRM.notify(`probe ${type}`, { type: type, delay: 20000 });
        });
        return cy
            .get(".notyf__toast", { timeout: 8000 })
            .last()
            .find(".notyf__ripple")
            .should("exist")
            .then(($ripple) => $ripple.css("background-color"));
    };

    beforeEach(() => {
        cy.setupAdminSession();
        cy.visit("/v2/dashboard");
        cy.window().its("CRM.notify").should("be.a", "function");
    });

    it('renders type: "error" as a red error toast', () => {
        notifyAndReadColor("error").should("equal", ERROR_RED);
    });

    it('renders type: "danger" as a red error toast', () => {
        notifyAndReadColor("danger").should("equal", ERROR_RED);
    });

    it('still renders type: "info" as a blue informational toast', () => {
        notifyAndReadColor("info").should("equal", INFO_BLUE);
    });

    it('falls back to the info style for an unknown type', () => {
        notifyAndReadColor("not-a-real-type").should("equal", INFO_BLUE);
    });
});
