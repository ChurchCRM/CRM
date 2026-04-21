/// <reference types="cypress" />

/**
 * UI coverage for the calendar page's time-zone indicator (issue #8712 follow-up).
 *
 * The indicator always shows the server-configured sTimeZone. When the
 * browser's resolved time zone differs, a warning badge reveals itself so
 * users can spot the misconfiguration that otherwise manifests silently as
 * shifted event times.
 */
describe("Calendar time-zone indicator", () => {
    beforeEach(() => cy.setupStandardSession());

    it("renders the configured time zone on page load", () => {
        cy.visit("event/calendars");

        cy.get("#calendarTimezoneIndicator").should("be.visible");
        cy.get("#calendarTimezoneConfigured")
            .should("be.visible")
            .invoke("text")
            .should("match", /[A-Za-z_]+\/[A-Za-z_]+|UTC/);
    });

    it("reveals the warning when the browser time zone differs from the server's", () => {
        // Stub Intl BEFORE navigation so the IIFE in calendar.php picks up
        // the override on its first (and only) run. Use an extreme TZ that
        // will not collide with the server's configured value in any seed.
        const fakeBrowserTZ = "Pacific/Kiritimati"; // UTC+14
        cy.visit("event/calendars", {
            onBeforeLoad(win) {
                const originalDTF = win.Intl.DateTimeFormat;
                function DTFStub(...args) {
                    const instance = new originalDTF(...args);
                    // ONLY override resolvedOptions() for the default
                    // no-args / no-explicit-timeZone call (the one the
                    // IIFE uses to fetch the browser zone). When the IIFE
                    // canonicalizes the configured zone via
                    // `Intl.DateTimeFormat(undefined, { timeZone: configured })`
                    // we must let the real implementation return the
                    // canonical configured zone — otherwise both sides
                    // end up equal to the fake value and the mismatch
                    // branch never fires.
                    const options = args[1] || {};
                    const explicitTz = options.timeZone;
                    if (!explicitTz) {
                        const originalResolved = instance.resolvedOptions.bind(instance);
                        instance.resolvedOptions = () => {
                            const opts = originalResolved();
                            opts.timeZone = fakeBrowserTZ;
                            return opts;
                        };
                    }
                    return instance;
                }
                DTFStub.supportedLocalesOf =
                    originalDTF.supportedLocalesOf.bind(originalDTF);
                win.Intl.DateTimeFormat = DTFStub;
            },
        });

        cy.get("#calendarTimezoneWarning")
            .should("be.visible")
            .and("not.have.class", "d-none");
        cy.get("#calendarTimezoneBrowser").should("contain.text", fakeBrowserTZ);
    });
});
