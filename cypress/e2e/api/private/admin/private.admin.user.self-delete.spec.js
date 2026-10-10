/// <reference types="cypress" />

/**
 * DELETE /admin/api/user/{id}/ must refuse the signed-in admin's own account.
 * The admin API key is person id 1 (Church Admin). Deleting a different user
 * is covered by cypress/e2e/ui-admin/admin.user.spec.js.
 */
describe("Admin API refuses self-deletion", () => {
    it("returns 403 and leaves the Church Admin account in place", () => {
        cy.makePrivateAdminAPICall("DELETE", "/admin/api/user/1/", null, 403).then((resp) => {
            expect(resp.body.success).to.eq(false);
            expect(resp.body.message).to.eq("You cannot delete your own account.");
        });

        // The API-key call replaces the browser session, so log in again
        // before reading the users page. There is no GET for this route.
        cy.setupAdminSession();
        cy.visit("admin/system/users");
        cy.contains("#user-listing-table tbody tr", "Church Admin").should("exist");
    });
});
