describe("Admin Email", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Debug", () => {
        cy.visit("v2/email/debug");
        cy.contains("Debug Email Connection");
    });


});
