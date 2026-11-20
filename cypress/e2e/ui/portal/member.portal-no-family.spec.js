/// <reference types="cypress" />

// Judith Kennedy (person 95, the nofinance login) has per_fam_ID = 0: she belongs to no family (#10932).
const JUDITH = 95;
const PUBLIC_CALENDAR = { type: "calendar", id: 1 };

describe("Member without a family in the portal", () => {
    let sharedCalendars;

    before(() => {
        cy.getMemberPortalCalendars().then((calendars) => {
            sharedCalendars = calendars;
        });
        cy.shareMemberPortalCalendars([PUBLIC_CALENDAR]);
    });

    after(() => {
        cy.shareMemberPortalCalendars(sharedCalendars);
    });

    beforeEach(() => {
        cy.useMemberSession(JUDITH);
    });

    it("uses Home, Calendar, Volunteering and Profile with no family card or link", () => {
        cy.visit("/portal/");
        cy.get("#portal-home-profile-card", { timeout: 10000 }).should("contain.text", "Judith");
        cy.get("#portal-home-family-card").should("not.exist");
        cy.get(".portal-welcome").should("not.contain.text", "your family");
        cy.get('a[href$="/portal/family"], a[href$="/portal/giving"]').should("not.exist");
        cy.get("#portal-nav")
            .should("contain.text", "Home")
            .and("contain.text", "Calendar")
            .and("contain.text", "Volunteering")
            .and("contain.text", "Profile")
            .and("not.contain.text", "My Family")
            .and("not.contain.text", "My Giving");

        cy.intercept("GET", "**/api/portal/calendar/events*").as("calendarEvents");
        cy.get("#portal-nav").contains(".portal-nav-link", "Calendar").click();
        cy.wait("@calendarEvents").its("response.statusCode").should("eq", 200);

        cy.get("#portal-nav").contains(".portal-nav-link", "Volunteering").click();
        cy.get("#assignments-empty:visible, #assignments-content:visible", { timeout: 15000 }).should("have.length", 1);

        cy.get("#portal-nav").contains(".portal-nav-link", "Profile").click();
        cy.get('#portal-profile-details [data-field="firstName"]', { timeout: 10000 }).should("have.text", "Judith");
    });

    it("answers the family-only pages with the contact-the-office page", () => {
        for (const path of ["/portal/family", "/portal/family/edit", "/portal/family/confirm", "/portal/giving/statement?year=2026"]) {
            cy.visit(path);
            cy.get("#portal-family-none").should("exist");
        }
    });

    it("answers the family API with 404", () => {
        cy.request({ url: "/api/portal/family", failOnStatusCode: false }).its("status").should("eq", 404);
    });
});

describe("Staff editor for a person without a family", () => {
    beforeEach(() => {
        cy.setupNoFinanceSession();
    });

    it("opens the staff editor for her record", () => {
        cy.request("/PersonEditor.php?PersonID=95").then((response) => {
            expect(response.status).to.equal(200);
            expect(response.body).to.contain("Judith");
            expect(response.body).not.to.match(/Fatal error|Uncaught|Warning:|Stack trace/);
        });
    });
});
