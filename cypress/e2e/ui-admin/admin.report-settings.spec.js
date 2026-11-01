/// <reference types="cypress" />

// 120x40 solid PNG, built in memory so no binary fixture is needed.
const LETTERHEAD_PNG_BASE64 =
    "iVBORw0KGgoAAAANSUhEUgAAAHgAAAAoCAIAAAC6iKlyAAAAUklEQVR42u3QQQ0AAAgEoOtjJhsZ2hbOBxsJSPVwIApEi0a0aNEWRItGtGjRFkSLRrRo0YgWjWjRohEtGtGiRSNaNKJFi0a0aESLFo1o0Yj+ZwGy/zgts+HrHQAAAABJRU5ErkJggg==";

const LETTERHEAD_API_URL = "/admin/api/system/letterhead";

function uploadThroughUppy() {
    cy.get("#church-letterhead-upload-btn").click();
    cy.get(".uppy-Dashboard--modal", { timeout: 10000 }).should("be.visible");
    cy.get(".uppy-Dashboard-input")
        .first()
        .selectFile(
            { contents: Cypress.Buffer.from(LETTERHEAD_PNG_BASE64, "base64"), fileName: "letterhead-test.png", mimeType: "image/png" },
            { force: true },
        );
    cy.get(".uppy-DashboardContent-save", { timeout: 10000 }).should("not.be.disabled").click();
    cy.get(".uppy-StatusBar-actionBtn--upload", { timeout: 10000 }).click();
}

// cy.request() replaces the PHP session's authentication, so each test signs in
// with a fresh form login after its API calls.
const removeStoredLetterhead = () => cy.makePrivateAdminAPICall("DELETE", LETTERHEAD_API_URL, null, 200);
const storeLetterhead = () =>
    cy.makePrivateAdminAPICall("POST", LETTERHEAD_API_URL, { imgBase64: `data:image/png;base64,${LETTERHEAD_PNG_BASE64}` }, 200);
const hasLetterhead = () => cy.makePrivateAdminAPICall("GET", LETTERHEAD_API_URL, null, 200).its("body.hasLetterhead");

describe("Admin - Report Settings page", () => {
    beforeEach(() => {
        removeStoredLetterhead();
        cy.freshAdminFormLogin({ enabled: true });
    });

    after(() => {
        removeStoredLetterhead();
    });

    it("shows the letterhead card and opens the shared uploader", () => {
        cy.visit("/admin/system/report-settings");
        cy.contains("Report Settings").should("be.visible");
        cy.get("#church-letterhead-card").should("be.visible");
        cy.window().its("CRM.photoUploader", { timeout: 10000 }).should("exist");

        cy.get("#church-letterhead-upload-btn").click();
        cy.get(".uppy-Dashboard--modal", { timeout: 10000 }).should("be.visible");
    });

    it("uploads a letterhead and shows its preview", () => {
        cy.visit("/admin/system/report-settings");
        cy.get("#church-letterhead-default-note").should("be.visible");
        cy.get("#church-letterhead-remove-btn").should("not.exist");

        cy.intercept("POST", `**${LETTERHEAD_API_URL}`).as("uploadLetterhead");
        uploadThroughUppy();
        cy.wait("@uploadLetterhead", { timeout: 15000 }).its("response.statusCode").should("eq", 200);

        cy.get("#church-letterhead-remove-btn", { timeout: 10000 }).should("be.visible");
        cy.get("#church-letterhead-default-note").should("not.exist");
        cy.get("#church-letterhead-preview").should("have.attr", "src").and("include", "church-letterhead.png");
        hasLetterhead().should("eq", true);
    });

    it("keeps the letterhead when the removal is cancelled, and removes it when confirmed", () => {
        storeLetterhead();
        cy.freshAdminFormLogin({ enabled: true });
        cy.visit("/admin/system/report-settings");
        cy.get("#church-letterhead-preview").should("be.visible");

        let deleteRequests = 0;
        cy.intercept("DELETE", `**${LETTERHEAD_API_URL}`, () => {
            deleteRequests += 1;
        }).as("removeLetterhead");
        cy.get("#church-letterhead-remove-btn").click();
        cy.get(".bootbox .bootbox-cancel").click();
        cy.get(".bootbox").should("not.exist");
        cy.get("#church-letterhead-preview").should("be.visible");
        cy.then(() => expect(deleteRequests).to.eq(0));

        cy.get("#church-letterhead-remove-btn").click();
        cy.get(".bootbox .bootbox-accept").click();
        cy.wait("@removeLetterhead").its("response.statusCode").should("eq", 200);

        cy.get("#church-letterhead-default-note", { timeout: 10000 }).should("be.visible");
        cy.get("#church-letterhead-preview").should("not.exist");
        hasLetterhead().should("eq", false);
    });

    it("sends a user without the admin role to access denied", () => {
        cy.setupPersonaSession("finance-only");
        cy.visit("/admin/system/report-settings", { failOnStatusCode: false });
        cy.url().should("include", "/v2/access-denied");
    });
});
