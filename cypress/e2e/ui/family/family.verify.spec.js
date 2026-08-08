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

    // ── Happy-path: submit the form and assert the note is recorded in the DB ──

    it("Should submit verification and create a self-verify note in the database", function() {
        const uniqueMessage = `Cypress self-verify ${Date.now()}`;

        // Register intercept before page load so the fetch POST is captured.
        // Glob prefix ('**') required for subdirectory CI mode.
        cy.intercept("POST", "**/external/verify/*").as("verifySubmit");

        cy.visit(this.verifyUrl);
        cy.get("#confirmVerifyBtn").click();
        cy.get("#confirm-Verify").should("be.visible");

        // Select "changes needed" and set the textarea value directly.
        // Using invoke("val") instead of .type() avoids a Bootstrap 5 modal
        // focus-trap issue where the keyboard delivery gets cut off mid-string;
        // the submit handler reads textarea.value at click-time, so a direct
        // DOM-property write produces the same result as typing.
        cy.get("#UpdateNeeded").click();
        cy.get("#confirm-info-data").should("be.visible").invoke("val", uniqueMessage);
        cy.get("#onlineVerifyBtn").click();

        // Wait for the fetch POST to complete successfully
        cy.wait("@verifySubmit").its("response.statusCode").should("eq", 200);

        // UI transitions: done panel shown, submit hidden, church-website shown
        cy.get("#confirm-modal-done").should("not.have.class", "d-none");
        cy.get("#onlineVerifyBtn").should("have.class", "d-none");
        cy.get("#onlineVerifySiteBtn").should("not.have.class", "d-none");

        // DB assertion: a self-verify note with our message must exist.
        // GET /api/families/self-verify returns notes with EnteredBy = SELF_VERIFY (-2),
        // the exact value set by the external POST handler in src/external/routes/verify.php.
        cy.makePrivateAdminAPICall("GET", "/api/families/self-verify", null, 200).then((resp) => {
            const notes = resp.body.families;
            // Number() coercion guards against PHP/PDO returning integer
            // columns as strings in some MariaDB/PDO configurations.
            const found = notes.find(
                (n) => Number(n.FamId) === familyId && n.Text === uniqueMessage
            );
            expect(found, "self-verify note for the family should exist with the submitted message").to.exist;
        });

        // Sanity: the family record itself is still accessible and unchanged
        cy.makePrivateAdminAPICall("GET", `/api/family/${familyId}`, null, 200).then((resp) => {
            expect(resp.body.Id).to.equal(familyId);
        });
    });

    // ── Token consumption: token uses drain to 0 and are eventually rejected ──

    it("Should reject the verify URL once all token uses are exhausted", function() {
        // Token.build('verifyFamily') sets RemainingUses = 5.
        // Each GET to the verify URL calls token.consume(), decrementing by one.
        // When RemainingUses reaches 0, isValid() returns false and the handler
        // renders the error template instead of the family-verification form.
        //
        // We exhaust the 5 uses via cy.request (fast HTTP-only GETs, no browser
        // rendering), then assert the 6th request shows the error page.
        Cypress._.times(5, () => {
            // Each valid response contains the "Confirm Family Info" button
            cy.request(this.verifyUrl).its("body").should("include", "confirmVerifyBtn");
        });

        // Token now exhausted (RemainingUses = 0): next request renders error page
        cy.request(this.verifyUrl)
            .its("body")
            .should("not.include", "confirmVerifyBtn")
            .and("include", "Unable to load verification info");
    });
});
