/// <reference types="cypress" />

describe("Admin System Reset", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Reset Members", () => {
        cy.visit("v2/admin/database/reset");
        cy.contains(
            "Please type I AGREE to access the database reset functions page.",
        );
    });
});
