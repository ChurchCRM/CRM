describe("Admin Email", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Debug", () => {
        cy.visit("admin/system/debug/email");
        cy.contains("Debug Email Connection");
    });


});
