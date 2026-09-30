/// <reference types="cypress" />

/**
 * API tests for the demo data import guard
 *
 * Covers:
 *   POST /admin/api/demo/load
 *
 * A successful import replaces data and runs on a fresh install in
 * cypress/e2e/new-system/02-demo-import.spec.js. On the standard test database
 * the endpoint refuses to run without force, which is what this spec checks.
 */
describe("Admin API Demo Load", () => {
    it("refuses to import when the database already has people", () => {
        cy.makePrivateAdminAPICall("POST", "/admin/api/demo/load", {}, 403).then((resp) => {
            expect(resp.body.success).to.eq(false);
            expect(resp.body.message).to.include("only available on fresh installations");
        });
    });
});
