/// <reference types="cypress" />

/**
 * Issue #9724 — the page-load trigger is rate limited server-side.
 *
 * POST /api/background/timerjobs is fired from the page footer on every
 * authenticated page load. Before this, a busy Sunday morning ran the jobs once
 * per page view. The endpoint now refuses to run again inside
 * iTimerJobsMinIntervalMinutes and reports that in its response.
 */
describe("API Background Timer Jobs (#9724)", () => {
    const LAST_RUN_CONFIG = "sLastTimerJobsRunDateTime";
    const INTERVAL_CONFIG = "iTimerJobsMinIntervalMinutes";

    const setConfig = (name, value) =>
        cy.makePrivateAdminAPICall(
            "POST",
            `/admin/api/system/config/${name}`,
            { value },
            200,
        );

    // Clear the recorded run so the first call in each test is always eligible.
    beforeEach(() => {
        setConfig(LAST_RUN_CONFIG, "");
    });

    after(() => {
        setConfig(INTERVAL_CONFIG, "15");
    });

    it("Runs the jobs and reports when they last ran", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/background/timerjobs",
            {},
            200,
        ).then((resp) => {
            expect(resp.body.ran).to.be.true;
            expect(resp.body.lastRun).to.be.a("string");
            expect(resp.body.minIntervalMinutes).to.be.a("number");
        });
    });

    it("Skips a second call inside the rate-limit window", () => {
        setConfig(INTERVAL_CONFIG, "15");

        cy.makePrivateAdminAPICall(
            "POST",
            "/api/background/timerjobs",
            {},
            200,
        ).then((first) => {
            expect(first.body.ran).to.be.true;

            cy.makePrivateAdminAPICall(
                "POST",
                "/api/background/timerjobs",
                {},
                200,
            ).then((second) => {
                expect(second.body.ran).to.be.false;
                // The skipped call must not move the marker forward, otherwise a
                // busy site would never become "due" again.
                expect(second.body.lastRun).to.equal(first.body.lastRun);
            });
        });
    });

    it("Runs every call when the interval is set to 0", () => {
        setConfig(INTERVAL_CONFIG, "0");

        cy.makePrivateAdminAPICall(
            "POST",
            "/api/background/timerjobs",
            {},
            200,
        ).then((first) => {
            expect(first.body.ran).to.be.true;
            expect(first.body.minIntervalMinutes).to.equal(0);

            cy.makePrivateAdminAPICall(
                "POST",
                "/api/background/timerjobs",
                {},
                200,
            ).then((second) => {
                expect(second.body.ran).to.be.true;
            });
        });
    });

    it("Requires authentication", () => {
        cy.apiRequest({
            method: "POST",
            url: "/api/background/timerjobs",
            body: {},
            failOnStatusCode: false,
        }).then((response) => {
            expect(response.status).to.eq(401);
        });
    });
});
