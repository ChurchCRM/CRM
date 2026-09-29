/// <reference types="cypress" />

/**
 * Issue #9931 — the timer-jobs settings added by #9724 (PR #9793) have to be
 * reachable from Admin → System Settings.
 *
 * SystemSettings.php only renders settings that belong to a category in
 * SystemConfig::buildCategories(), so a ConfigItem that is defined but not
 * categorised silently disappears from the UI. That is what happened to
 * iTimerJobsStaleHours and iTimerJobsMinIntervalMinutes: the dashboard warning
 * told administrators to change iTimerJobsStaleHours, but no page offered it.
 *
 * The internal last-run marker (sLastTimerJobsRunDateTime) is written by the
 * job runner and must stay out of the form: an administrator has no reason to
 * edit it, and the form would post it back on every save.
 */
describe("Admin System Settings — Scheduled Tasks category", () => {
    const STALE_HOURS = "iTimerJobsStaleHours";
    const MIN_INTERVAL = "iTimerJobsMinIntervalMinutes";
    const LAST_RUN = "sLastTimerJobsRunDateTime";
    const input = (name) => `input[name="new_value[${name}]"]`;

    const setConfig = (name, value) =>
        cy.makePrivateAdminAPICall("POST", `/admin/api/system/config/${name}`, { value }, 200);

    beforeEach(() => {
        // API setup goes before the UI login: an x-api-key request replaces
        // the browser's session cookie.
        setConfig(STALE_HOURS, "26");
        setConfig(MIN_INTERVAL, "15");
        cy.setupAdminSession();
        cy.visit("/SystemSettings.php");
        cy.contains("System Settings").should("be.visible");
    });

    after(() => {
        setConfig(STALE_HOURS, "26");
        setConfig(MIN_INTERVAL, "15");
    });

    it("lists a Scheduled Tasks tab with both timer-jobs settings at their defaults", () => {
        cy.get("#settings-nav").contains("a.nav-link", "Scheduled Tasks").click();
        cy.get(".tab-pane.active").within(() => {
            cy.contains(".card-title", "Scheduled Tasks").should("be.visible");
            cy.get(input(STALE_HOURS)).should("be.visible").and("have.value", "26");
            cy.get(input(MIN_INTERVAL)).should("be.visible").and("have.value", "15");
        });
    });

    it("keeps the internal last-run marker out of the form", () => {
        cy.get(input(LAST_RUN)).should("not.exist");
        cy.get(`[name="type[${LAST_RUN}]"]`).should("not.exist");
    });

    it("saves a changed staleness threshold from the form", () => {
        cy.get("#settings-nav").contains("a.nav-link", "Scheduled Tasks").click();
        cy.get(".tab-pane.active").within(() => {
            cy.get(input(STALE_HOURS)).clear().type("48");
            cy.get(input(MIN_INTERVAL)).clear().type("0");
        });
        cy.get("#save").click();

        cy.contains("System Settings").should("be.visible");
        cy.get("#settings-nav").contains("a.nav-link", "Scheduled Tasks").click();
        cy.get(".tab-pane.active").within(() => {
            cy.get(input(STALE_HOURS)).should("have.value", "48");
            cy.get(input(MIN_INTERVAL)).should("have.value", "0");
        });
    });
});
