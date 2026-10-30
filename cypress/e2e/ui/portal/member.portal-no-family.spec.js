/// <reference types="cypress" />

// Judith Kennedy (person 95, the nofinance login) has per_fam_ID = 0: a login that belongs to no family.
// Any page that reads a person's family needs a case for her (#10932).
describe("Member without a family in the portal", () => {
    beforeEach(() => {
        cy.setupNoFinanceSession();
    });

    it("offers only the pages that do not need a family", () => {
        cy.visit("/portal/");
        cy.get(".portal-nav-link", { timeout: 10000 }).should("contain.text", "Home").and("contain.text", "Profile");
        cy.get(".portal-nav-link").should("not.contain.text", "My Family").and("not.contain.text", "My Giving");
    });

    it("tells the home card to contact the church office, with no link", () => {
        cy.visit("/portal/");
        cy.get("#portal-home-family-card")
            .should("contain.text", "You are not currently associated with a family")
            .and("contain.text", "Please contact the church office")
            .find("a")
            .should("not.exist");
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

    it("still opens Profile", () => {
        cy.visit("/portal/profile");
        cy.contains("Judith").should("be.visible");
    });

    it("opens the staff editor for her record without a family", () => {
        cy.request("/PersonEditor.php?PersonID=95").then((response) => {
            expect(response.status).to.equal(200);
            expect(response.body).to.contain("Judith");
            expect(response.body).not.to.match(/Fatal error|Uncaught|Warning:|Stack trace/);
        });
    });
});
