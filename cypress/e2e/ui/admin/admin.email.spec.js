describe("Admin Email", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Debug", () => {
        cy.visit("admin/system/debug/email");

        // Page title was "Debug Email Connection"; it's now "Email Debug"
        // and the page always renders one of three status cards (config
        // error / success / failure). Assert on structural elements
        // present in every state.
        cy.contains("Email Debug");
        cy.contains("SMTP Configuration");
    });


});
