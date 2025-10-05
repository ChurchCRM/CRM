context("Admin Email", () => {
    it("Debug", () => {
        cy.loginAdmin("v2/email/debug");
        cy.contains("Debug Email Connection");
    });


});
