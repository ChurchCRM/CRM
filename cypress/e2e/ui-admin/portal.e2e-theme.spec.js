/// <reference types="cypress" />

/**
 * Member Portal — #9869 scenario 4, "the theme flow", as ONE end-to-end run.
 *
 * Epic #8977, issue #9869, design `.agents/skills/churchcrm/member-portal-design.md`
 * §3 (P3–P8) and §4; the authoring guide is `docs/portal-themes.md`.
 *
 * The epic's largest promise is the one hardest to prove from a unit of code: a church
 * can make the portal look like their church, over FTP, without a developer and
 * without touching anything an upgrade will overwrite. `member.portal-theme.spec.js`
 * proves the mechanism — a `theme.css` recolours, a template override replaces, a
 * broken theme fails loudly. `admin.member-portal-page.spec.js` proves the admin page's
 * controls. This run is the church's actual afternoon, in order, with the two things
 * neither of those asserts:
 *
 *   1. that activating from the ADMIN PAGE reaches EVERY portal page, not just the
 *      home page the other specs sample — a theme that recolours the landing page and
 *      leaves the calendar in the default palette is the failure a church would find
 *      on day two, not day one;
 *   2. that a broken theme is refused BEFORE it is activated, leaving members on the
 *      theme that works — the difference between "we broke the portal at 4pm on a
 *      Saturday" and "the admin page said no".
 *
 * The afternoon:
 *
 *     upload a theme folder ──► it appears on Admin → Member Portal, marked valid
 *        │
 *        ├─ Check it ─────────► "This theme has no problems."
 *        ├─ Activate it ──────► every portal page wears it
 *        ├─ override a page ──► that page, and only that page, changes
 *        ├─ try a broken one ─► refused, findings listed, members untouched
 *        ├─ developer mode ───► every page says which template drew it
 *        └─ back to default ──► nothing of the theme is left
 *
 * The spec writes throwaway theme folders on disk — the webserver container
 * bind-mounts `../src` — and removes them in `after()`. `src/Include/themes/` is
 * git-ignored for church themes, which is the whole point of the location (P5).
 *
 * This spec lives in `ui-admin/` because it drives Admin → Member Portal;
 * `docker-admin.config.ts` is the config that runs it.
 */

const GOOD_THEME = "e2e9869good";
const BROKEN_THEME = "e2e9869broken";
const GOOD_DIR = `src/Include/themes/${GOOD_THEME}`;
const BROKEN_DIR = `src/Include/themes/${BROKEN_THEME}`;

/** A colour no default token uses, so "it changed" cannot be a coincidence. */
const THEME_TEAL = "rgb(0, 105, 107)";

const MEMBER_USER = "lena.black.editself.notes@exampl";
const MEMBER_PASSWORD = "changeme";

/** Every portal page the member can open — the theme has to reach all of them. */
const PORTAL_PAGES = [
    ["home", "/portal/", ".portal-home"],
    ["calendar", "/portal/calendar", ".portal-calendar-page"],
    ["profile", "/portal/profile", ".portal-card"],
    ["family", "/portal/family", ".portal-card"],
];

const adminKey = () => Cypress.env("admin.api.key");

const setConfig = (name, value) =>
    cy.request({
        method: "POST",
        url: `/admin/api/system/config/${name}`,
        headers: { "content-type": "application/json", "x-api-key": adminKey() },
        body: { value },
        failOnStatusCode: false,
    });

const readConfig = (name) =>
    cy.request({
        url: `/admin/api/system/config/${name}`,
        headers: { "x-api-key": adminKey() },
        failOnStatusCode: false,
    });

function adminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.env("admin.username"));
    cy.get("input[name=Password]").type(`${Cypress.env("admin.password")}{enter}`);
    cy.url({ timeout: 10000 }).should("include", "/v2/dashboard");
}

function memberLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(MEMBER_USER);
    cy.get("input[name=Password]").type(`${MEMBER_PASSWORD}{enter}`);
    cy.url({ timeout: 10000 }).should("include", "/portal");
}

describe("Member Portal e2e — #9869 scenario 4, a church themes the portal", () => {
    before(() => {
        // What a church uploads: one stylesheet, and a theme.json so the admin page
        // has a name to show instead of a folder.
        cy.writeFile(
            `${GOOD_DIR}/theme.css`,
            `:root {\n  --portal-header-bg: ${THEME_TEAL};\n}\n`,
        );
        cy.writeFile(
            `${GOOD_DIR}/theme.json`,
            JSON.stringify(
                {
                    name: "E2E 9869 Church",
                    author: "Cypress",
                    description: "The throwaway theme of scenario 4",
                },
                null,
                2,
            ),
        );
        // …and the one somebody uploaded with an unclosed block.
        cy.writeFile(
            `${BROKEN_DIR}/templates/home.html.twig`,
            '{% extends "@default/layout.html.twig" %}\n{% block content %}\n',
        );

        // The calendar has to be switched on, or one of the pages below is a 404.
        setConfig("bPortalShowCalendar", "1");
    });

    after(() => {
        setConfig("sMemberPortalTheme", "default");
        setConfig("bPortalDeveloperMode", "0");
        cy.exec(`rm -rf ${GOOD_DIR} ${BROKEN_DIR}`, { failOnNonZeroExit: false });
    });

    it("finds the uploaded folder on Admin → Member Portal and says it is valid", () => {
        adminLogin();
        cy.visit("/admin/member-portal");

        cy.get("#portalThemeSelect").should("contain.text", "E2E 9869 Church");
        cy.get("#portalThemeSelect").select(GOOD_THEME);
        cy.get("#portalThemeCheckButton").click();

        cy.get("#portalThemeFindings", { timeout: 10000 }).should(
            "contain.text",
            "This theme has no problems.",
        );
        cy.get("#portalThemeStatusBadge").should("contain.text", "Valid");
    });

    it("activates it from the admin page, and EVERY portal page wears it", () => {
        adminLogin();
        cy.visit("/admin/member-portal");
        cy.get("#portalThemeSelect").select(GOOD_THEME);
        cy.get("#portalThemeActivateButton").click();

        readConfig("sMemberPortalTheme").then((resp) => {
            expect(resp.body.value).to.eq(GOOD_THEME);
        });

        memberLogin();
        for (const [name, url] of PORTAL_PAGES) {
            cy.visit(url);
            cy.get(`link[href*="/portal/theme/${GOOD_THEME}/theme.css"]`, {
                timeout: 10000,
            }).should("exist");
            cy.get(".portal-header").should(
                "have.css",
                "background-color",
                THEME_TEAL,
            );
            cy.log(`themed: ${name}`);
        }
    });

    it("lets the church replace one page without touching the others", () => {
        cy.writeFile(
            `${GOOD_DIR}/templates/home.html.twig`,
            '{% extends "@default/layout.html.twig" %}\n' +
                '{% block content %}<p id="e2e-theme-home">Welcome to our church</p>{% endblock %}\n',
        );

        memberLogin();
        cy.get("#e2e-theme-home").should("contain.text", "Welcome to our church");
        cy.get(".portal-home").should("not.exist");

        // The pages the church did NOT override are untouched — an override
        // replaces one template, not the theme's relationship to the rest.
        cy.visit("/portal/profile");
        cy.get(".portal-card").should("exist");
        cy.get("#e2e-theme-home").should("not.exist");
        cy.get(".portal-header").should("have.css", "background-color", THEME_TEAL);

        cy.exec(`rm -f ${GOOD_DIR}/templates/home.html.twig`, {
            failOnNonZeroExit: false,
        });
    });

    it("refuses the broken theme, lists the findings, and leaves members alone", () => {
        adminLogin();
        cy.visit("/admin/member-portal");
        cy.get("#portalThemeSelect").select(BROKEN_THEME);
        cy.get("#portalThemeActivateButton").click();

        cy.get("#portalThemeFindings", { timeout: 10000 }).should(
            "contain.text",
            "home.html.twig",
        );
        cy.get("#portalThemeStatusBadge").should("contain.text", "Errors");

        // Nothing was written — the refusal is the point, not the error page.
        readConfig("sMemberPortalTheme").then((resp) => {
            expect(resp.body.value, "the active theme did not change").to.eq(
                GOOD_THEME,
            );
        });

        // And the member's Saturday evening is exactly as it was.
        memberLogin();
        cy.get(".portal-home").should("exist");
        cy.get(".portal-unavailable").should("not.exist");
        cy.get(".portal-header").should("have.css", "background-color", THEME_TEAL);
    });

    it("tells the theme author which template drew each page, in developer mode", () => {
        setConfig("bPortalDeveloperMode", "1");
        memberLogin();

        // The comment is the first thing in the response, ahead of the doctype,
        // so the raw body is read rather than the parsed document.
        cy.request("/portal/")
            .its("body")
            .should("contain", "<!-- portal template: home.html.twig -->");
        cy.request("/portal/profile")
            .its("body")
            .should("contain", "<!-- portal template: profile/index.html.twig -->");

        setConfig("bPortalDeveloperMode", "0");
        memberLogin();
        cy.request("/portal/").its("body").should("not.contain", "<!-- portal template:");
    });

    it("goes back to the system theme and leaves nothing of the church's behind", () => {
        setConfig("sMemberPortalTheme", "default");

        memberLogin();
        cy.get(".portal-home").should("exist");
        cy.get(`link[href*="/portal/theme/${GOOD_THEME}/"]`).should("not.exist");
        cy.get(".portal-header").should(
            "not.have.css",
            "background-color",
            THEME_TEAL,
        );
    });
});
