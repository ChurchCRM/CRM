/// <reference types="cypress" />

/**
 * Use case B — "self-verify" via a token link, opened by someone with NO account.
 * An admin mints the link (GET /api/family/{id}/verify/url) and shares it; the
 * recipient visits /external/verify/{token} with no session. Path A (EditSelf
 * account users) reaches the SAME page and is covered in
 * cypress/e2e/ui/security/limited-access.spec.js.
 */
function verifyPathFromApiUrl(absoluteUrl) {
    const { pathname, search } = new URL(absoluteUrl);
    const basePath = new URL(Cypress.config("baseUrl")).pathname.replace(/\/?$/, "/");
    const path = pathname.startsWith(basePath) ? pathname.slice(basePath.length) : pathname.replace(/^\//, "");
    return path + search;
}

describe("Family verification — self-verify token link (no account)", () => {
    const familyId = 1;

    beforeEach(() => {
        // No browser session needed: API call uses x-api-key header auth,
        // and the verify page is public (token-based, no login required)
        cy.makePrivateAdminAPICall("GET", `/api/family/${familyId}/verify/url`, null, 200).then((response) => {
            // API url is absolute from SystemURLs::getURL() ($URL[0]). Follow the
            // path relative to cy.baseUrl. Strip baseUrl's own path so subdir CI
            // (baseUrl .../churchcrm/) does not visit /churchcrm/churchcrm/... (#9871).
            cy.wrap(verifyPathFromApiUrl(response.body.url)).as("verifyUrl");
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
        cy.clearCookies();
        cy.visit(this.verifyUrl);

        cy.get(".avatar").should("have.length.greaterThan", 0);
        cy.get("body").should("not.contain", "Notes");
    });

    it("Should submit verification and create a self-verify note in the database", function() {
        const uniqueMessage = `Cypress self-verify ${Date.now()}`;

        cy.intercept("POST", "**/external/verify/*").as("verifySubmit");

        cy.visit(this.verifyUrl);
        cy.get("#confirmVerifyBtn").click();
        cy.get("#confirm-Verify").should("be.visible");

        cy.get("#UpdateNeeded").click();
        cy.get("#confirm-info-data").should("be.visible").invoke("val", uniqueMessage);
        cy.get("#onlineVerifyBtn").click();

        cy.wait("@verifySubmit").its("response.statusCode").should("eq", 200);

        cy.get("#confirm-modal-done").should("not.have.class", "d-none");
        cy.get("#onlineVerifyBtn").should("have.class", "d-none");
        cy.get("#onlineVerifySiteBtn").should("not.have.class", "d-none");

        cy.makePrivateAdminAPICall("GET", "/api/families/self-verify", null, 200).then((resp) => {
            const notes = resp.body.families;
            const found = notes.find(
                (n) => Number(n.FamId) === familyId && n.Text === uniqueMessage
            );
            expect(found, "self-verify note for the family should exist with the submitted message").to.exist;
        });

        cy.makePrivateAdminAPICall("GET", `/api/family/${familyId}`, null, 200).then((resp) => {
            expect(resp.body.Id).to.equal(familyId);
        });
    });

    it("Should reject the verify URL once all token uses are exhausted", function() {
        Cypress._.times(5, () => {
            cy.request(this.verifyUrl).its("body").should("include", "confirmVerifyBtn");
        });

        cy.request(this.verifyUrl)
            .its("body")
            .should("not.include", "confirmVerifyBtn")
            .and("include", "Unable to load verification info");
    });
});
