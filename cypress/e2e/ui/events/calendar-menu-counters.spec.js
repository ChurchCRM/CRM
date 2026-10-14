/// <reference types="cypress" />

// noperm.user (901) is not an admin, so the Events module switch decides whether the Calendar menu renders.
const noPermSession = () => cy.setupLoginSession("calendar-counters-noperm", "noperm.user", "changeme");

const interceptPageInit = () => {
    cy.intercept("POST", "**/api/background/timerjobs").as("timerJobs");
    cy.intercept("GET", "**/api/calendar/events-counters*").as("counters");
};

describe("Calendar menu counters", () => {
    describe("with the Events module on", () => {
        beforeEach(noPermSession);

        it("fills the Calendar menu badges", () => {
            interceptPageInit();
            cy.visit("/v2/dashboard");
            cy.wait("@counters");
            cy.get("#BirthdateNumber").should("exist");
            cy.get("#AnniversaryNumber").should("exist");
            cy.get("#EventsNumber").should("exist");
        });
    });

    describe("with the Events module off", () => {
        let original;

        before(() => {
            cy.getSystemConfig("bEnabledEvents").then((value) => {
                original = value;
            });
            cy.makePrivateAdminAPICall("POST", "admin/api/system/config/bEnabledEvents", { value: "0" }, 200);
        });

        after(() => cy.restoreSystemConfig("bEnabledEvents", original));

        beforeEach(noPermSession);

        it("loads pages without requesting the counters", () => {
            interceptPageInit();
            cy.visit("/v2/dashboard");
            cy.wait("@timerJobs");
            cy.get("#BirthdateNumber").should("not.exist");
            cy.get("@counters.all").should("have.length", 0);
        });
    });
});
