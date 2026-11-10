/// <reference types="cypress" />

/**
 * Use case B — "self-verify" via a token link, opened by someone with NO account.
 * An admin mints the link (GET /api/family/{id}/verify/url) and shares it; the
 * recipient visits /external/verify/{token} with no session. Path A, a member
 * signed in to the Member Portal, confirms the family there (MP4) —
 * see cypress/e2e/ui/portal/member.portal-family.spec.js.
 */
function verifyPathFromApiUrl(absoluteUrl) {
    const { pathname, search } = new URL(absoluteUrl);
    const basePath = new URL(Cypress.config("baseUrl")).pathname.replace(/\/?$/, "/");
    const path = pathname.startsWith(basePath) ? pathname.slice(basePath.length) : pathname.replace(/^\//, "");
    return path + search;
}

describe("Family verification — self-verify token link (no account)", () => {
    const familyId = 1;

    // The tests that POST create verify notes. Those notes must not land on the
    // seeded family: an earlier "Change requested" note would satisfy the
    // assertion even if the current POST saved nothing, and the note DELETE API
    // cannot remove verify notes — so a disposable family with members is
    // registered in before() and deleted in after() instead (#11339).
    const disposableFamilyIds = [];
    let disposableFamilyId;
    let savedSelfReg;

    const registerDisposableFamily = () =>
        cy
            .request({
                method: "POST",
                url: "/api/public/register/family",
                body: {
                    Name: `CypressVerify ${Date.now()}`,
                    Address1: "1 Verify Way",
                    City: "Testville",
                    State: "TS",
                    Country: "US",
                    Zip: "00000",
                    people: [
                        { firstName: "Vera", lastName: "Verify", gender: 2, role: 1, email: "" },
                        { firstName: "Victor", lastName: "Verify", gender: 1, role: 2, email: "" },
                    ],
                },
            })
            .then((response) => {
                disposableFamilyIds.push(response.body.Id);
                disposableFamilyId = response.body.Id;
            });

    before(() => {
        // Registration is how this suite obtains a disposable family with
        // members; enable it only for the setup window and restore afterwards.
        cy.getSystemConfig("bEnableSelfRegistration").then((value) => {
            savedSelfReg = value;
        });
        cy.makePrivateAdminAPICall("POST", "admin/api/system/config/bEnableSelfRegistration", { value: "1" });
        registerDisposableFamily();
    });

    after(() => {
        cy.cleanupFamilies(disposableFamilyIds);
        cy.restoreSystemConfig("bEnableSelfRegistration", savedSelfReg);
    });

    beforeEach(() => {
        // No browser session needed: API call uses x-api-key header auth,
        // and the verify page is public (token-based, no login required).
        // A fresh token is minted per test: the exhaustion test spends all five.
        cy.makePrivateAdminAPICall("GET", `/api/family/${familyId}/verify/url`, null, 200).then((response) => {
            // API url is absolute from SystemURLs::getURL() ($URL[0]). Follow the
            // path relative to cy.baseUrl. Strip baseUrl's own path so subdir CI
            // (baseUrl .../churchcrm/) does not visit /churchcrm/churchcrm/... (#9871).
            cy.wrap(verifyPathFromApiUrl(response.body.url)).as("verifyUrl");
        });
        cy.makePrivateAdminAPICall("GET", `/api/family/${disposableFamilyId}/verify/url`, null, 200).then((response) => {
            cy.wrap(verifyPathFromApiUrl(response.body.url)).as("disposableVerifyUrl");
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
        // Bootstrap moves focus to the dialog when its fade-in ends; typing that started
        // before that moment gets cut off ("Updat"). Let the fade finish, then type.
        cy.get("#confirm-info-data").should("be.visible");
        cy.wait(400);
        cy.get("#confirm-info-data").click().type("Update needed").should("have.value", "Update needed");
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

        cy.visit(this.disposableVerifyUrl);
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
                (n) => Number(n.FamId) === disposableFamilyId && n.Text === uniqueMessage
            );
            expect(found, "self-verify note for the family should exist with the submitted message").to.exist;
        });

        cy.makePrivateAdminAPICall("GET", `/api/family/${disposableFamilyId}`, null, 200).then((resp) => {
            expect(resp.body.Id).to.equal(disposableFamilyId);
        });
    });

    it("Should keep serving the verify URL on repeated GETs and only spend uses on a real submission", function() {
        // Reading the page never consumes a use (#11145): link previews,
        // email scanners and refreshes must not burn the family's attempts,
        // so more GETs than the token has uses all still render the page.
        Cypress._.times(6, () => {
            cy.request(this.disposableVerifyUrl).its("body").should("include", "confirmVerifyBtn");
        });

        // A successful submission is what spends a use. Once the five uses
        // are gone the page is rejected.
        Cypress._.times(5, () => {
            cy.request({
                method: "POST",
                url: this.disposableVerifyUrl,
                form: true,
                body: { verifyType: "no-change" },
            })
                .its("statusCode")
                .should("eq", 200);
        });

        cy.request(this.disposableVerifyUrl)
            .its("body")
            .should("not.include", "confirmVerifyBtn")
            .and("include", "Unable to load verification info");
    });

    it("Should save a change request when change-needed is posted with no comment", function() {
        // "change needed" with an empty comment must not be recorded as
        // "No Changes" (#11145): the note keeps the change request. The
        // disposable family starts with no notes, so the assertion cannot be
        // satisfied by an earlier run's leftover.
        cy.request({
            method: "POST",
            url: this.disposableVerifyUrl,
            form: true,
            body: { verifyType: "change-needed" },
        })
            .its("statusCode")
            .should("eq", 200);

        cy.makePrivateAdminAPICall("GET", "/api/families/self-verify", null, 200).then((resp) => {
            const notes = resp.body.families;
            const found = notes.find(
                (n) => Number(n.FamId) === disposableFamilyId && n.Text === "Change requested"
            );
            expect(found, "an empty change-needed POST should save a change request note").to.exist;
        });
    });
});
