/// <reference types="cypress" />

// Issue #10687: edit and delete are separate roles. The seeded no-finance
// user judith.matthews has EditRecords=1 but DeleteRecords=0.
describe("Family member actions on a person profile respect DeleteRecords", () => {
    it("shows Edit but never offers Delete to an EditRecords-only user", () => {
        cy.setupNoFinanceSession();
        cy.visit("/people/view/2");
        // This family has other members, so this is a real menu-permission test.
        cy.get('.dropdown-menu a[href*="PersonEditor.php?PersonID="]')
            .should("have.length.at.least", 1);
        cy.get('.dropdown-menu .delete-person[data-view="family"]').should("not.exist");
        cy.get("#deletePersonBtn").should("not.exist");
    });

    it("keeps family member Delete available to a DeleteRecords user", () => {
        cy.setupStandardSession(); // tony.wade, DeleteRecords=1 (non-admin)
        cy.visit("/people/view/2");
        cy.get('.dropdown-menu .delete-person[data-view="family"]')
            .should("have.length.at.least", 1);
    });
});
