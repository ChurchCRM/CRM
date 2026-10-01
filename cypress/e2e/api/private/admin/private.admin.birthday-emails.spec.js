/// <reference types="cypress" />

/**
 * API tests for the birthday email test-send
 *
 * Covers:
 *   POST /admin/api/admin/birthday-emails/test
 *
 * The outcome depends on the environment: 200 when the admin has an address and
 * SMTP accepts the message, 400 when the admin has no address on file, 500 when
 * sending fails. Each outcome has its own response contract.
 */
describe("Admin API Birthday Emails test-send", () => {
    it("answers with one of the documented outcomes", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/admin/birthday-emails/test",
            {},
            [200, 400, 500],
        ).then((resp) => {
            if (resp.status === 200) {
                expect(resp.body.success).to.eq(true);
                expect(resp.body.message).to.include("Test email sent to");
            } else {
                expect(resp.body.success).to.eq(false);
                expect(resp.body.message).to.be.a("string").and.not.be.empty;
            }
        });
    });
});
