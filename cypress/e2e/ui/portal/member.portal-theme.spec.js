/// <reference types="cypress" />

/**
 * Member Portal (MP2, #9863) — church themes.
 *
 * Design: .agents/skills/churchcrm/member-portal-design.md §3 (P3–P8).
 *   - a theme is a folder under src/Include/themes/; its folder name is its id
 *   - a theme.css alone recolours every portal page
 *   - a templates/<path>.html.twig replaces the default template of that path
 *   - an edited file is picked up on the next request (auto_reload)
 *   - a broken active theme fails loudly: the administrator sees the error page
 *     with theme, file, line and message; members see "temporarily unavailable"
 *
 * The spec writes a throwaway theme folder on disk (the webserver container
 * bind-mounts ../src) and removes it again in after().
 */
const THEME_ID = "cypresstheme";
const THEME_DIR = `src/Include/themes/${THEME_ID}`;
const HEADER_GREEN = "rgb(0, 100, 0)";

const adminKey = () => Cypress.env("admin.api.key");

const setPortalTheme = (value) =>
    cy.request({
        method: "POST",
        url: "/admin/api/system/config/sMemberPortalTheme",
        headers: { "content-type": "application/json", "x-api-key": adminKey() },
        body: { value },
        failOnStatusCode: false,
    });

const loginAsMember = () => {
    cy.clearCookies();
    cy.visit("session/begin");
    cy.get("input[name=User]").type("lena.black.editself.notes@exampl");
    cy.get("input[name=Password]").type("changeme{enter}");
    cy.url({ timeout: 10000 }).should("include", "/portal");
};

const loginAsAdmin = () => {
    cy.clearCookies();
    cy.visit("session/begin");
    cy.get("input[name=User]").type(Cypress.env("admin.username"));
    cy.get("input[name=Password]").type(Cypress.env("admin.password") + "{enter}");
    cy.url({ timeout: 10000 }).should("include", "/v2/dashboard");
};

describe("Member Portal — church theme", () => {
    before(() => {
        cy.writeFile(
            `${THEME_DIR}/theme.css`,
            `:root { --portal-header-bg: ${HEADER_GREEN}; }\n`
        );
        setPortalTheme(THEME_ID);
    });

    after(() => {
        setPortalTheme("default");
        cy.exec(`rm -rf ${THEME_DIR}`, { failOnNonZeroExit: false });
    });

    it("theme.css alone recolours the portal", () => {
        loginAsMember();
        cy.get('link[href*="/portal/theme/cypresstheme/theme.css"]').should("exist");
        cy.get(".portal-header").should("have.css", "background-color", HEADER_GREEN);
    });

    it("A templates/home.html.twig override replaces the default home page", () => {
        cy.writeFile(
            `${THEME_DIR}/templates/home.html.twig`,
            '{% extends "@default/layout.html.twig" %}\n' +
                '{% block content %}<p id="theme-override">Overridden by the Cypress theme</p>{% endblock %}\n'
        );
        loginAsMember();
        cy.get("#theme-override").should("contain.text", "Overridden by the Cypress theme");
        cy.get(".portal-home").should("not.exist");
    });

    it("An administrator sees the theme-error page when the active theme breaks", () => {
        cy.writeFile(
            `${THEME_DIR}/templates/home.html.twig`,
            '{% extends "@default/layout.html.twig" %}\n{% block content %}\n'
        );
        loginAsAdmin();
        cy.visit("/portal/", { failOnStatusCode: false });
        cy.get(".portal-theme-error", { timeout: 10000 }).should("exist");
        cy.contains("cypresstheme").should("exist");
        cy.contains("home.html.twig").should("exist");
    });

    it("A member sees the 'temporarily unavailable' page when the active theme breaks", () => {
        loginAsMember();
        cy.visit("/portal/", { failOnStatusCode: false });
        cy.get(".portal-unavailable", { timeout: 10000 }).should("exist");
        cy.contains("The Member Portal is temporarily unavailable").should("exist");
        // The member must never be shown the template internals.
        cy.get(".portal-theme-error").should("not.exist");
        cy.contains("home.html.twig").should("not.exist");
    });

    it("Removing the override restores the default home page", () => {
        cy.exec(`rm -f ${THEME_DIR}/templates/home.html.twig`, { failOnNonZeroExit: false });
        loginAsMember();
        cy.get(".portal-home").should("exist");
        cy.get("#theme-override").should("not.exist");
    });
});
