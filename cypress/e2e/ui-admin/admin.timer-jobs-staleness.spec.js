/// <reference types="cypress" />

/**
 * Issue #9724 — the admin dashboard warns when scheduled tasks stop running.
 *
 * ChurchCRM's background jobs (birthday emails, every plugin CRON_RUN listener)
 * only run on a page load unless the administrator installs the cron entry, so
 * a quiet weekday can silently send no mail. The dashboard surfaces that.
 *
 * Two ordering hazards, both handled by primeAndVisit():
 *  - an x-api-key request replaces the browser's CRM session cookie, so the
 *    admin session has to be (re)established after the config is set, not
 *    before, or the visit lands on the login page;
 *  - every page load fires POST /background/timerjobs from the footer, which
 *    would refresh the very marker the test just aged, so that request is
 *    stubbed in the browser. cy.request is not intercepted, so the one test
 *    that needs a real run still gets one.
 */
describe("Admin Dashboard — scheduled task staleness warning", () => {
    const LAST_RUN_CONFIG = "sLastTimerJobsRunDateTime";
    const BANNER = "#timer-jobs-stale-warning";

    const setLastRun = (value) =>
        cy.makePrivateAdminAPICall(
            "POST",
            `/admin/api/system/config/${LAST_RUN_CONFIG}`,
            { value },
            200,
        );

    const formatLocal = (d) => {
        const pad = (n) => String(n).padStart(2, "0");
        return (
            `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ` +
            `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`
        );
    };

    // A timestamp comfortably past the 26-hour default threshold.
    const staleTimestamp = () => formatLocal(new Date(Date.now() - 72 * 60 * 60 * 1000));
    const freshTimestamp = () => formatLocal(new Date());

    const primeAndVisit = () => {
        // Stub first: an x-api-key request invalidates the browser's session
        // cookie, so cy.session often has to log in again here, and that login
        // page load would otherwise fire a real timer-job run.
        cy.intercept("POST", "**/background/timerjobs", {
            statusCode: 200,
            body: { ran: false, lastRun: null, minIntervalMinutes: 15 },
        });
        cy.setupAdminSession();
        cy.visit("/admin/");
    };

    before(() => {
        // Mark a run as just-completed so the very first login page load is
        // rate-limited away before any stub exists.
        setLastRun(freshTimestamp());
        cy.setupAdminSession();
    });

    after(() => {
        // Leave the marker fresh so the banner does not linger for later specs.
        cy.makePrivateAdminAPICall("POST", "/api/background/timerjobs", {}, 200);
    });

    it("shows the warning with cron instructions when the last run is old", () => {
        setLastRun(staleTimestamp());
        primeAndVisit();

        cy.get(BANNER).should("be.visible");
        cy.get(BANNER).should("contain", "Scheduled tasks are not running");
        // The instruction has to be actionable on its own — the warning is the
        // documentation for installs that never read the docs site.
        cy.get(`${BANNER} #timer-jobs-cron-command`)
            .invoke("text")
            .should("match", /^0 \* \* \* \* .*cli\/timerjobs\.php$/);
    });

    it("shows the warning when the jobs have never run", () => {
        // Empty resets the config item to its default, i.e. no recorded run.
        setLastRun("");
        primeAndVisit();

        cy.get(BANNER).should("be.visible");
        cy.get(BANNER).should("contain", "never run");
    });

    it("hides the warning once the jobs run again", () => {
        setLastRun(staleTimestamp());

        // What the page-load fallback does, without waiting for a page load.
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/background/timerjobs",
            {},
            200,
        ).then((resp) => {
            expect(resp.body.ran).to.be.true;
        });

        primeAndVisit();

        cy.get(BANNER).should("not.exist");
        // Sanity: we really are on the dashboard, not the login page.
        cy.contains("Admin Dashboard").should("be.visible");
    });
});
