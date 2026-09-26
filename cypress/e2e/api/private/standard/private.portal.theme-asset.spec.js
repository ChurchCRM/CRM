/// <reference types="cypress" />

/**
 * Member Portal (MP2, #9863) — the theme asset route.
 *
 * Design: .agents/skills/churchcrm/member-portal-design.md §2.2 step 6 / P5.
 * Include/ stays deny-all at the web-server level, so theme files are streamed
 * by the application at GET /portal/theme/{name}/{path}:
 *   - only the allow-listed extensions come out (css js png jpg jpeg gif svg
 *     webp ico woff woff2 ttf) — everything else is 404
 *   - ETag from filemtime+size, Cache-Control: public, max-age=31536000,
 *     304 on If-None-Match
 *   - no "..", no absolute path, no unknown theme
 *   - the route is public: it must serve without a session
 */
const ASSET_THEME = "cypressasset";
const ASSET_DIR = `src/Include/themes/${ASSET_THEME}`;

describe("Member Portal theme asset route", () => {
    before(() => {
        cy.writeFile(`${ASSET_DIR}/theme.css`, ":root { --portal-primary: #123456; }\n");
        cy.writeFile(`${ASSET_DIR}/theme.json`, '{"name": "Cypress Asset Theme"}\n');
        cy.writeFile(`${ASSET_DIR}/templates/home.html.twig`, "<p>nothing</p>\n");
        cy.writeFile(`${ASSET_DIR}/evil.php`, "<?php echo 'nope';\n");
    });

    after(() => {
        cy.exec(`rm -rf ${ASSET_DIR}`, { failOnNonZeroExit: false });
    });

    it("serves the default theme stylesheet with cache headers", () => {
        cy.clearCookies();
        cy.request({ url: "/portal/theme/default/theme.css" }).then((resp) => {
            expect(resp.status).to.eq(200);
            expect(resp.headers["content-type"]).to.contain("text/css");
            expect(resp.headers["cache-control"]).to.contain("public");
            expect(resp.headers["cache-control"]).to.contain("max-age=31536000");
            expect(resp.headers.etag).to.be.a("string").and.not.be.empty;
            expect(resp.body).to.contain("--portal-");
        });
    });

    it("answers 304 when the ETag still matches", () => {
        cy.clearCookies();
        cy.request({ url: "/portal/theme/default/theme.css" }).then((first) => {
            cy.request({
                url: "/portal/theme/default/theme.css",
                headers: { "If-None-Match": first.headers.etag },
                failOnStatusCode: false,
            }).then((second) => {
                expect(second.status).to.eq(304);
            });
        });
    });

    it("serves a church theme stylesheet without a session", () => {
        cy.clearCookies();
        cy.request({ url: `/portal/theme/${ASSET_THEME}/theme.css` }).then((resp) => {
            expect(resp.status).to.eq(200);
            expect(resp.body).to.contain("--portal-primary");
        });
    });

    it("refuses theme.json — the extension is not allow-listed", () => {
        cy.request({
            url: `/portal/theme/${ASSET_THEME}/theme.json`,
            failOnStatusCode: false,
        })
            .its("status")
            .should("eq", 404);
    });

    it("refuses a template file", () => {
        cy.request({
            url: "/portal/theme/default/templates/layout.html.twig",
            failOnStatusCode: false,
        })
            .its("status")
            .should("eq", 404);
        cy.request({
            url: `/portal/theme/${ASSET_THEME}/templates/home.html.twig`,
            failOnStatusCode: false,
        })
            .its("status")
            .should("eq", 404);
    });

    it("refuses a PHP file inside a theme", () => {
        cy.request({
            url: `/portal/theme/${ASSET_THEME}/evil.php`,
            failOnStatusCode: false,
        })
            .its("status")
            .should("eq", 404);
    });

    it("refuses traversal out of the theme folder", () => {
        cy.request({
            url: "/portal/theme/default/..%2F..%2FConfig.php",
            failOnStatusCode: false,
        })
            .its("status")
            .should("eq", 404);
        cy.request({
            url: "/portal/theme/default/%2e%2e%2f%2e%2e%2fConfig.php",
            failOnStatusCode: false,
        })
            .its("status")
            .should("eq", 404);
        cy.request({
            url: "/portal/theme/..%2F..%2FInclude/Config.php",
            failOnStatusCode: false,
        })
            .its("status")
            .should("eq", 404);
    });

    it("refuses an unknown theme", () => {
        cy.request({
            url: "/portal/theme/no-such-theme/theme.css",
            failOnStatusCode: false,
        })
            .its("status")
            .should("eq", 404);
    });
});
