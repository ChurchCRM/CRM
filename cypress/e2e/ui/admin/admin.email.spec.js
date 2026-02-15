describe("Admin Email", () => {
    beforeEach(() => {
        cy.setupAdminSessionFromEnv();
    });

    it("Debug", () => {
        cy.visit("admin/system/debug/email");
        cy.contains("Debug Email Connection");
    });


});
