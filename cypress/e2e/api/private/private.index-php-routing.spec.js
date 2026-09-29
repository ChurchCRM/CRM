/// <reference types="cypress" />

/**
 * index.php front-controller — HTTP status/redirect contract
 *
 * Covers the dispatch logic in src/index.php: the removed legacy filename
 * shims (UserEditor.php / SettingsUser.php, migrated to Admin MVC in #9173),
 * the /index.php -> /v2/dashboard redirect, and the 404 flow for paths that
 * don't resolve to a real .php file under the doc root (bare 404 for
 * js/css-looking paths, the standalone errors/not-found.php page for
 * everything else).
 */
describe("API Private - index.php Routing", () => {
    beforeEach(() => {
        cy.setupStandardSession();
    });

    it("redirects /index.php to the v2 dashboard", () => {
        cy.request({ url: "/index.php", followRedirect: false, failOnStatusCode: false })
            .then((response) => {
                expect(response.status).to.equal(302);
                expect(response.headers.location).to.include("/v2/dashboard");
            });
    });

    it("no longer special-cases the deleted UserEditor.php shim", () => {
        cy.request({ url: "/UserEditor.php?PersonID=1", followRedirect: false, failOnStatusCode: false })
            .then((response) => {
                expect(response.status).to.equal(302);
                expect(response.headers.location).to.include("/errors/not-found.php");
                expect(response.headers.location).to.not.include("/admin/system/users");
            });
    });

    it("no longer special-cases the deleted SettingsUser.php shim", () => {
        cy.request({ url: "/SettingsUser.php", followRedirect: false, failOnStatusCode: false })
            .then((response) => {
                expect(response.status).to.equal(302);
                expect(response.headers.location).to.include("/errors/not-found.php");
                expect(response.headers.location).to.not.include("/admin/system/users");
            });
    });

    it("redirects an unresolved page path to the standalone 404 page", () => {
        cy.request({ url: "/this-page-was-never-real.php", followRedirect: false, failOnStatusCode: false })
            .then((redirectResponse) => {
                expect(redirectResponse.status).to.equal(302);
                expect(redirectResponse.headers.location).to.include("/errors/not-found.php");

                // Use new URL() to resolve the Location header against baseUrl — Cypress
                // cy.request concatenates baseUrl + url for paths starting with '/', which
                // in a subdir install doubles the prefix (e.g. /churchcrm/ + /churchcrm/errors/…
                // → /churchcrm/churchcrm/errors/…). Constructing an absolute URL first
                // prevents Cypress from prepending baseUrl again.
                const resolvedUrl = new URL(
                    redirectResponse.headers.location,
                    Cypress.config('baseUrl')
                ).href;
                cy.request({ url: resolvedUrl, failOnStatusCode: false })
                    .then((finalResponse) => {
                        expect(finalResponse.status).to.equal(404);
                        expect(finalResponse.body).to.include("Page Not Found");
                        expect(finalResponse.body).to.include("this-page-was-never-real.php");
                    });
            });
    });

    it("returns a bare 404 for a missing static asset instead of the HTML error page", () => {
        cy.request({ url: "/skin/v2/this-chunk-does-not-exist.js", failOnStatusCode: false })
            .then((response) => {
                expect(response.status).to.equal(404);
                expect(response.body).to.not.include("Page Not Found");
            });
    });
});
