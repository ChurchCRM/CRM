/// <reference types="cypress" />

// Seeded data, read only: person 1 is an administrator, person 3 is the
// standard user (tony.wade, DeleteRecords, non-admin), persons 99 and 102 are
// in family 20 (99 has a login, 102 does not), person 10 has no login, and
// family 3 has no member with a login.
describe("Standard user - delete controls for people who have a login", () => {
    beforeEach(() => cy.setupStandardSession());

    it("greys out Delete Person on an administrator's profile with the reason", () => {
        cy.visit("/people/view/1");
        cy.get("#deletePersonBtn")
            .should("have.attr", "aria-disabled", "true")
            .and("have.attr", "title")
            .and("match", /administrator/i);
    });

    it("greys out Delete Person on the signed-in user's own profile", () => {
        cy.visit("/people/view/3");
        cy.get("#deletePersonBtn")
            .should("have.attr", "aria-disabled", "true")
            .and("have.attr", "title")
            .and("match", /yourself/i);
    });

    it("leaves Delete Person enabled for a person without a login", () => {
        cy.visit("/people/view/10");
        cy.get("#deletePersonBtn").should("not.have.attr", "aria-disabled");
    });

    it("explains the reason instead of opening the confirmation when a disabled control is clicked", () => {
        cy.visit("/people/view/1");
        cy.get("#person-actions-dropdown").first().click();
        cy.get("#deletePersonBtn").first().click();
        cy.get(".bootbox").should("contain", "administrator");
        cy.get(".bootbox-accept").click({ force: true });
    });

    it("greys out only the family members who have a login", () => {
        cy.visit("/people/family/20");
        cy.get('.delete-person[data-person_id="99"]').should("have.attr", "aria-disabled", "true");
        cy.get('.delete-person[data-person_id="102"]').should("not.have.attr", "aria-disabled");
    });

    it("exposes the blocked people to the page scripts", () => {
        cy.visit("/people/list");
        cy.window().then((win) => {
            expect(win.CRM.personDeleteBlocked).to.have.property("1");
            expect(win.CRM.personDeleteBlocked).to.not.have.property("10");
        });
    });

    it("disables deleting a family with its members when a member has a login", () => {
        cy.visit("/SelectDelete.php?FamilyID=2");
        cy.get("#deleteFamilyAndMembersBtn").should("be.disabled");
        cy.get("#deleteFamilyAndMembersBlockedReason").should("contain", "administrator");
        cy.get("#deleteFamilyOnlyBtn").should("be.enabled");
    });

    it("keeps deleting a family with its members available when no member has a login", () => {
        cy.visit("/SelectDelete.php?FamilyID=3");
        cy.get("#deleteFamilyAndMembersBtn").should("be.enabled");
        cy.get("#deleteFamilyAndMembersBlockedReason").should("not.exist");
    });
});
