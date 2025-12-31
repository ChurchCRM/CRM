/// <reference types="cypress" />

describe("Admin System Reset", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Reset Members", () => {
        cy.visit("admin/system/reset");
        cy.contains(
            "Please type I AGREE to access the database reset functions page.",
        );
    });
});
