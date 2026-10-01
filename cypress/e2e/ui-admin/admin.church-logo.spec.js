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
function uploadLogoThroughUppy(
    file = {
        contents: Cypress.Buffer.from(LOGO_PNG_BASE64, "base64"),
        fileName: "church-logo-test.png",
        mimeType: "image/png",
    },
) {
    cy.get("#church-logo-upload-btn").click();
    cy.get(".uppy-Dashboard--modal", { timeout: 10000 }).should("be.visible");

    cy.get(".uppy-Dashboard-input").first().selectFile(file, { force: true });

    cy.get(".uppy-DashboardContent-save", { timeout: 10000 })
        .should("not.be.disabled")
        .click();
    cy.get(".uppy-StatusBar-actionBtn--upload", { timeout: 10000 }).click();
}

/**
 * A 4032x3024 JPEG of random noise, drawn in the browser. Noise does not
 * compress, so the file is well over the 2 MB the server accepts in one
 * request: the size of a real phone photo, which the uploader has to shrink
 * before sending.
 */
function buildPhonePhoto() {
    return cy.window().then(
        (win) =>
            new Cypress.Promise((resolve) => {
                const canvas = win.document.createElement("canvas");
                canvas.width = 4032;
                canvas.height = 3024;
                const context = canvas.getContext("2d");
                const pixels = context.createImageData(canvas.width, canvas.height);
                for (let offset = 0; offset < pixels.data.length; offset += 65536) {
                    win.crypto.getRandomValues(
                        pixels.data.subarray(offset, offset + 65536),
                    );
                }
                for (let i = 3; i < pixels.data.length; i += 4) {
                    pixels.data[i] = 255;
                }
                context.putImageData(pixels, 0, 0);
                canvas.toBlob(
                    (blob) =>
                        blob.arrayBuffer().then((buffer) =>
                            resolve({
                                contents: Cypress.Buffer.from(buffer),
                                fileName: "phone-photo.jpg",
                                mimeType: "image/jpeg",
                            }),
                        ),
                    "image/jpeg",
                    0.95,
                );
            }),
    );
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
        cy.get('.uppy-DashboardTab[data-uppy-acquirer-id="Webcam"]').should(
            "not.exist",
        );

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

    it("Accepts a phone photo larger than the server upload limit", () => {
        cy.visit("/admin/system/church-info");

        buildPhonePhoto().then((photo) => {
            expect(photo.contents.length).to.be.greaterThan(2 * 1024 * 1024);

            cy.intercept("POST", `**${LOGO_API_URL}`).as("uploadLogo");
            uploadLogoThroughUppy(photo);
        });

        cy.wait("@uploadLogo").then(({ request, response }) => {
            expect(JSON.stringify(request.body).length).to.be.lessThan(2 * 1024 * 1024);
            expect(response.statusCode).to.equal(200);
        });
        cy.get("#church-logo-remove-btn", { timeout: 10000 }).should("be.visible");
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
