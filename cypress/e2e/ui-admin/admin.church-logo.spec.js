/// <reference types="cypress" />

/**
 * UI tests for the Church Logo card on Admin -> Church Information (issue #9717)
 *
 * The card drives the shared Uppy photo uploader, and the server renders the
 * logo state, so an upload or a removal reloads the page. The tests check that
 * the uploaded logo replaces the ChurchCRM branding in the sidebar and on the
 * login page, and that removing it restores the bundled brand assets.
 */

// 120x40 solid PNG, built in memory so no binary fixture is needed. Deliberately
// wide: the logo crop is free-form, not the 1:1 crop person photos use.
const LOGO_PNG_BASE64 =
    "iVBORw0KGgoAAAANSUhEUgAAAHgAAAAoCAIAAAC6iKlyAAAAUklEQVR42u3QQQ0AAAgEoOtjJhsZ2hbOBxsJSPVwIApEi0a0aNEWRItGtGjRFkSLRrRo0YgWjWjRohEtGtGiRSNaNKJFi0a0aESLFo1o0Yj+ZwGy/zgts+HrHQAAAABJRU5ErkJggg==";

const LOGO_API_URL = "/admin/api/system/church-logo";

/**
 * cy.request()/cy.makePrivate*APICall() overwrite the PHP session's
 * authentication provider, so a browser session established before an API call
 * is dead afterwards. Always log in fresh AFTER the API calls.
 */
function freshAdminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.env("admin.username"));
    cy.get("input[name=Password]").type(
        `${Cypress.env("admin.password")}{enter}`,
    );
    cy.url().should("not.include", "/session/begin");
}

/**
 * Drive the shared Uppy dashboard end to end: open it, hand the file to Uppy's
 * own hidden <input>, accept the auto-opened image editor, then press Upload.
 */
function uploadLogoThroughUppy() {
    cy.get("#church-logo-upload-btn").click();
    cy.get(".uppy-Dashboard--modal", { timeout: 10000 }).should("be.visible");

    cy.get(".uppy-Dashboard-input")
        .first()
        .selectFile(
            {
                contents: Cypress.Buffer.from(LOGO_PNG_BASE64, "base64"),
                fileName: "church-logo-test.png",
                mimeType: "image/png",
            },
            { force: true },
        );

    cy.get(".uppy-DashboardContent-save", { timeout: 10000 }).click();
    cy.get(".uppy-StatusBar-actionBtn--upload", { timeout: 10000 }).click();
}

function sidebarBrandImages() {
    return cy.get("#sidebar .navbar-brand img");
}

describe("Admin - Church Logo", () => {
    // Base64 of the logo the instance had before this suite ran, or null.
    let originalLogoBase64 = null;

    before(() => {
        // The logo is global state: remember what the instance had so the
        // suite can put it back instead of resetting a customised instance's
        // branding.
        cy.makePrivateAdminAPICall("GET", LOGO_API_URL, null, 200).then(
            (response) => {
                if (!response.body.hasCustomLogo) {
                    return;
                }
                // Root-relative URL with the install's base path already in it;
                // resolve against the origin so a subdirectory install does not
                // get its base path doubled.
                cy.request({
                    url: new URL(response.body.url, Cypress.config("baseUrl"))
                        .href,
                    encoding: "base64",
                }).then((imageResponse) => {
                    originalLogoBase64 = imageResponse.body;
                });
            },
        );
    });

    beforeEach(() => {
        cy.makePrivateAdminAPICall("DELETE", LOGO_API_URL, null, 200);
        freshAdminLogin();
    });

    after(() => {
        if (originalLogoBase64 !== null) {
            cy.makePrivateAdminAPICall(
                "POST",
                LOGO_API_URL,
                { imgBase64: `data:image/png;base64,${originalLogoBase64}` },
                200,
            );
        } else {
            cy.makePrivateAdminAPICall("DELETE", LOGO_API_URL, null, 200);
        }
    });

    it("Opens the shared Uppy dashboard from the Church Logo card", () => {
        cy.visit("/admin/system/church-info");
        cy.window().its("CRM.photoUploader", { timeout: 10000 }).should("exist");

        cy.get("#church-logo-upload-btn").click();
        cy.get(".uppy-Dashboard--modal", { timeout: 10000 }).should("be.visible");

        cy.get(".uppy-Dashboard-close").click();
        cy.get(".uppy-Dashboard--modal").should("not.be.visible");
    });

    it("Uploads a logo and replaces the sidebar branding", () => {
        cy.visit("/admin/system/church-info");

        cy.get("#church-logo-default-note").should("be.visible");
        cy.get("#church-logo-remove-btn").should("not.exist");
        cy.get("#sidebar .navbar-brand-text").should("exist");

        cy.intercept("POST", `**${LOGO_API_URL}`).as("uploadLogo");
        uploadLogoThroughUppy();
        cy.wait("@uploadLogo").its("response.statusCode").should("eq", 200);

        // The page reloads and renders the new state.
        cy.get("#church-logo-remove-btn", { timeout: 10000 }).should("be.visible");
        cy.get("#church-logo-default-note").should("not.exist");
        cy.get("#church-logo-preview")
            .should("have.attr", "src")
            .and("include", "church-logo.png");
        sidebarBrandImages()
            .should("have.length", 1)
            .and("have.attr", "src")
            .and("include", "church-logo.png");
        cy.get("#sidebar .navbar-brand-text").should("not.exist");
    });

    it("Removes the logo and restores the bundled brand assets", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            LOGO_API_URL,
            { imgBase64: `data:image/png;base64,${LOGO_PNG_BASE64}` },
            200,
        );
        freshAdminLogin();
        cy.visit("/admin/system/church-info");

        cy.intercept("DELETE", `**${LOGO_API_URL}`).as("deleteLogo");
        cy.get("#church-logo-remove-btn").click();
        cy.wait("@deleteLogo").its("response.statusCode").should("eq", 200);

        cy.get("#church-logo-remove-btn", { timeout: 10000 }).should("not.exist");
        cy.get("#church-logo-default-note").should("be.visible");
        cy.get("#church-logo-preview")
            .should("have.attr", "src")
            .and("include", "/Images/churchcrm-logo-ink-blue.svg");
        cy.get("#sidebar .navbar-brand .crm-brand-logo-light")
            .should("have.attr", "src")
            .and("include", "/Images/churchcrm-symbol-ink-blue.svg");
        cy.get("#sidebar .navbar-brand .crm-brand-logo-dark")
            .should("have.attr", "src")
            .and("include", "/Images/churchcrm-symbol-paper-blue.svg");
        cy.get("#sidebar .navbar-brand-text").should("exist");
    });

    it("Shows the uploaded logo on the login page and the default after removal", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            LOGO_API_URL,
            { imgBase64: `data:image/png;base64,${LOGO_PNG_BASE64}` },
            200,
        );

        cy.clearCookies();
        cy.visit("/session/begin");
        cy.get(".login-header-logo img")
            .should("have.attr", "src")
            .and("include", "church-logo.png");

        cy.makePrivateAdminAPICall("DELETE", LOGO_API_URL, null, 200);

        cy.clearCookies();
        cy.visit("/session/begin");
        cy.get(".login-header-logo img")
            .should("have.attr", "src")
            .and("include", "/Images/churchcrm-logo-ink-blue.svg");
    });
});
