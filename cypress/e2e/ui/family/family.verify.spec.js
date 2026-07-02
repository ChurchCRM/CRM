/// <reference types="cypress" />

/**
 * Use case B — "self-verify" via a token link, opened by someone with NO account.
 * An admin mints the link (GET /api/family/{id}/verify/url) and shares it; the
 * recipient visits /external/verify/{token} with no session. Path A (EditSelf
 * account users) reaches the SAME page and is covered in
 * cypress/e2e/ui/security/limited-access.spec.js.
 */
describe("Family verification — self-verify token link (no account)", () => {
    const familyId = 1;

    beforeEach(() => {
        // No browser session needed: API call uses x-api-key header auth,
        // and the verify page is public (token-based, no login required)
        cy.makePrivateAdminAPICall("GET", `/api/family/${familyId}/verify/url`, null, 200).then((response) => {
            cy.wrap(response.body.url).as("verifyUrl");
        });
    });

    it("Should display family header and members", function() {
        cy.visit(this.verifyUrl);
        cy.get(".container-fluid").should("be.visible");
        cy.contains("Family Members").should("be.visible");
        cy.get(".col-lg-4").should("exist");
    });

    it("Should show confirmation modal with radio options", function() {
        cy.visit(this.verifyUrl);
        cy.get("#confirmVerifyBtn").click();
        cy.get("#confirm-Verify").should("be.visible");
        cy.get("#NoChanges").should("exist");
        cy.get("#UpdateNeeded").should("exist");
    });

    it("Should allow filling update information", function() {
        cy.visit(this.verifyUrl);
        cy.get("#confirmVerifyBtn").click();
        cy.get("#confirm-Verify").should("be.visible");
        cy.get("#UpdateNeeded").click();
        // Click textarea first to ensure focus after modal animation settles
        cy.get("#confirm-info-data").should("be.visible").click().type("Update needed");
        cy.get("#confirm-info-data").invoke("val").should("include", "Update");
    });

    it("Should display modal footer buttons", function() {
        cy.visit(this.verifyUrl);
        cy.get("#confirmVerifyBtn").click();
        cy.get("#onlineVerifyCancelBtn").should("be.visible");
        cy.get("#onlineVerifyBtn").should("be.visible");
        cy.get("#onlineVerifySiteBtn").should("exist");
    });

    it("Should render avatars without a session and never expose private notes", function() {
        // Path B: a token link opened by someone with NO account/session.
        cy.clearCookies();
        cy.visit(this.verifyUrl);

        // Photos must work without a session: the page renders avatars inline
        // (base64 <img> when a photo exists, initials fallback otherwise). There
        // is deliberately no /api/*/photo sub-request here — that would 403 for a
        // sessionless visitor. Asserting avatars exist guards that behaviour.
        cy.get(".avatar").should("have.length.greaterThan", 0);

        // Privacy invariant: notes are private and must NEVER appear on the
        // verification page. The visitor verifies everything *except* notes.
        cy.get("body").should("not.contain", "Notes");
    });
});
