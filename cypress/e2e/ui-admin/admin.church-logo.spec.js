/// <reference types="cypress" />

/**
 * UI tests for the Church Logo card on Admin -> Church Information (issue #9717)
 *
 * Verifies that an uploaded logo replaces the ChurchCRM branding in the sidebar
 * and on the login page, and that removing it restores the defaults.
 */

// 1x1 transparent PNG, built in memory so no binary fixture is needed.
const PNG_1X1_BASE64 =
    "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==";

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

function selectLogoFile() {
    cy.get("#church-logo-file").selectFile(
        {
            contents: Cypress.Buffer.from(PNG_1X1_BASE64, "base64"),
            fileName: "church-logo-test.png",
            mimeType: "image/png",
        },
        { force: true },
    );
}

describe("Admin - Church Logo", () => {
    beforeEach(() => {
        // Remove any logo left behind by a previous (possibly failed) run first,
        // then establish the browser session.
        cy.makePrivateAdminAPICall("DELETE", LOGO_API_URL, null, 200);
        freshAdminLogin();
    });

    after(() => {
        // The logo is global state — never leave one behind for other specs.
        cy.makePrivateAdminAPICall("DELETE", LOGO_API_URL, null, 200);
    });

    it("Uploads a logo and replaces the sidebar branding", () => {
        cy.visit("/admin/system/church-info");

        // Default state: stock icon, church name visible, no Remove button.
        cy.get("#church-logo-card").should("exist");
        cy.get("#church-logo-default-note").should("not.have.class", "d-none");
        cy.get("#church-logo-remove-btn").should("have.class", "d-none");
        cy.get("#sidebar-brand-text").should("not.have.class", "d-none");

        cy.intercept("POST", `**${LOGO_API_URL}`).as("uploadLogo");
        selectLogoFile();
        cy.wait("@uploadLogo").its("response.statusCode").should("eq", 200);

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
        selectLogoFile();
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
        cy.get("#sidebar-brand-image").should(($img) => {
            expect($img.attr("src")).to.include("CRM_50x50.png");
        });
        cy.get("#sidebar-brand-text").should("not.have.class", "d-none");
        cy.get("#church-logo-preview").should(($img) => {
            expect($img.attr("src")).to.include("logo-churchcrm-350.jpg");
        });
    });

    it("Shows the uploaded logo on the login page and the default after removal", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            LOGO_API_URL,
            { imgBase64: `data:image/png;base64,${PNG_1X1_BASE64}` },
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
            expect($img.attr("src")).to.include("logo-churchcrm-350.jpg");
        });
    });
});
