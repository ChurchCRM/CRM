/// <reference types="cypress" />

/**
 * UI tests for the Church Logo card on Admin -> Church Information (issue #9717)
 *
 * The card uses the project's shared Uppy photo uploader (the same dashboard the
 * person, family and user photo pages mount), so these tests drive the real Uppy
 * flow: open the dashboard, drop a file on its hidden input, accept the image
 * editor, then press Upload. They then verify that the uploaded logo replaces the
 * ChurchCRM branding in the sidebar and on the login page, and that removing it
 * restores the defaults.
 */

// 120x40 solid PNG, built in memory so no binary fixture is needed. Deliberately
// wide: the logo crop is free-form, not the 1:1 crop person photos use.
const LOGO_PNG_BASE64 =
    "iVBORw0KGgoAAAANSUhEUgAAAHgAAAAoCAIAAAC6iKlyAAAAUklEQVR42u3QQQ0AAAgEoOtjJhsZ2hbOBxsJSPVwIApEi0a0aNEWRItGtGjRFkSLRrRo0YgWjWjRohEtGtGiRSNaNKJFi0a0aESLFo1o0Yj+ZwGy/zgts+HrHQAAAABJRU5ErkJggg==";

const LOGO_API_URL = "/api/system/church-logo";

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

    // The dashboard is configured with autoOpen: "imageEditor" — accept the
    // default (free-ratio) crop.
    cy.get(".uppy-DashboardContent-save", { timeout: 10000 }).click();

    cy.get(".uppy-StatusBar-actionBtn--upload", { timeout: 10000 }).click();
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
        // Remove any logo left behind by a previous (possibly failed) run first,
        // then establish the browser session.
        cy.makePrivateAdminAPICall("DELETE", LOGO_API_URL, null, 200);
        freshAdminLogin();
    });

    after(() => {
        // Leave the instance as it was found: restore the original logo or make
        // sure none is left behind for other specs.
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

        // The hand-rolled file input is gone: the card drives Uppy instead.
        cy.get("#church-logo-file").should("not.exist");
        cy.window().its("CRM.photoUploader", { timeout: 10000 }).should("exist");

        cy.get("#church-logo-upload-btn").click();
        cy.get(".uppy-Dashboard--modal", { timeout: 10000 }).should("be.visible");

        cy.get(".uppy-Dashboard-close").click();
        cy.get(".uppy-Dashboard--modal").should("not.be.visible");
    });

    it("Uploads a logo and replaces the sidebar branding", () => {
        cy.visit("/admin/system/church-info");

        // Default state: stock icon, church name visible, no Remove button.
        cy.get("#church-logo-card").should("exist");
        cy.get("#church-logo-default-note").should("not.have.class", "d-none");
        cy.get("#church-logo-remove-btn").should("have.class", "d-none");
        cy.get("#sidebar-brand-text").should("not.have.class", "d-none");

        cy.intercept("POST", `**${LOGO_API_URL}`).as("uploadLogo");
        uploadLogoThroughUppy();
        cy.wait("@uploadLogo").its("response.statusCode").should("eq", 200);

        // The dashboard closes itself once the upload succeeds.
        cy.get(".uppy-Dashboard--modal", { timeout: 10000 }).should(
            "not.be.visible",
        );

        cy.get("#church-logo-message").should("have.class", "alert-success");
        cy.get("#church-logo-default-note").should("have.class", "d-none");
        cy.get("#church-logo-remove-btn").should("not.have.class", "d-none");

        // Sidebar updates in place: logo shown, church-name text hidden.
        cy.get("#sidebar-brand-image").should(($img) => {
            expect($img.attr("src")).to.include("church-logo.png");
        });
        cy.get("#sidebar-brand-text").should("have.class", "d-none");

        // The server-rendered page agrees after a reload.
        cy.reload();
        cy.get("#sidebar-brand-image").should(($img) => {
            expect($img.attr("src")).to.include("church-logo.png");
        });
        cy.get("#sidebar-brand-text").should("have.class", "d-none");
        cy.get("#church-logo-preview").should(($img) => {
            expect($img.attr("src")).to.include("church-logo.png");
        });
    });

    it("Removes the logo and restores the ChurchCRM defaults", () => {
        cy.visit("/admin/system/church-info");

        cy.intercept("POST", `**${LOGO_API_URL}`).as("uploadLogo");
        uploadLogoThroughUppy();
        cy.wait("@uploadLogo").its("response.statusCode").should("eq", 200);
        cy.get("#church-logo-remove-btn").should("not.have.class", "d-none");

        cy.intercept("DELETE", `**${LOGO_API_URL}`).as("deleteLogo");
        cy.get("#church-logo-remove-btn").click();
        cy.wait("@deleteLogo").its("response.statusCode").should("eq", 200);

        cy.get("#church-logo-message").should("have.class", "alert-success");
        cy.get("#church-logo-default-note").should("not.have.class", "d-none");
        cy.get("#church-logo-remove-btn").should("have.class", "d-none");
        cy.get("#sidebar-brand-text").should("not.have.class", "d-none");

        cy.reload();
        // Without an uploaded logo the sidebar shows the bundled brand mark, not the upload slot
        cy.get("#sidebar-brand-image").should("have.class", "d-none");
        cy.get(".crm-brand-default.crm-brand-logo-light").should(($img) => {
            expect($img.attr("src")).to.include("churchcrm-symbol-ink-blue.svg");
            expect($img.hasClass("d-none")).to.eq(false);
        });
        cy.get("#sidebar-brand-text").should("not.have.class", "d-none");
        cy.get("#church-logo-preview").should(($img) => {
            expect($img.attr("src")).to.include("churchcrm-logo-ink-blue.svg");
        });
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
        cy.get("#login-logo").should(($img) => {
            expect($img.attr("src")).to.include("church-logo.png");
        });

        cy.makePrivateAdminAPICall("DELETE", LOGO_API_URL, null, 200);

        cy.clearCookies();
        cy.visit("/session/begin");
        cy.get("#login-logo").should(($img) => {
            expect($img.attr("src")).to.include("churchcrm-logo-ink-blue.svg");
        });
    });
});
