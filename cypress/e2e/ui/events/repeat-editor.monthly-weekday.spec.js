/// <reference types="cypress" />

describe("Repeat Events editor: Nth weekday of the month (#10906)", () => {
    const title = `Nth weekday UI ${Date.now()}`;

    beforeEach(() => {
        cy.setupAdminSession();
    });

    after(() => {
        cy.makePrivateAdminAPICall("GET", "/api/events", null, 200).then((resp) => {
            const ids = (resp.body.Events || []).filter((event) => event.Title === title).map((event) => event.Id);
            cy.cleanupEvents(ids);
        });
    });

    it("enables only the week and weekday selects for the pattern", () => {
        cy.visit("/event/repeat-editor/1");
        cy.get("#RecurWOM").should("be.disabled");
        cy.get("#RecurMonthDOW").should("be.disabled");

        cy.get("#recurMonthlyWeekday").check();
        cy.get("#RecurWOM").should("be.enabled");
        cy.get("#RecurMonthDOW").should("be.enabled");
        cy.get("#RecurDOW").should("be.disabled");
        cy.get("#RecurDOM").should("be.disabled");
    });

    it("creates the 1st Sunday of each month", () => {
        cy.visit("/event/repeat-editor/1");
        cy.get("#EventTitle").clear().type(title);
        cy.get("#RangeStart").clear().type("2042-01-01");
        cy.get("#RangeEnd").clear().type("2042-03-31");
        cy.get("#recurMonthlyWeekday").check();
        cy.get("#RecurWOM").select("1");
        cy.get("#RecurMonthDOW").select("Sunday");
        cy.get("button[name='CreateRepeat']").click();

        cy.location("pathname").should("contain", "/event/dashboard");
        cy.makePrivateAdminAPICall("GET", "/api/events", null, 200).then((resp) => {
            const starts = (resp.body.Events || [])
                .filter((event) => event.Title === title)
                .map((event) => String(event.Start).slice(0, 10))
                .sort();
            expect(starts).to.deep.eq(["2042-01-05", "2042-02-02", "2042-03-02"]);
        });
    });
});
