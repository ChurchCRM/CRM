/// <reference types="cypress" />

/**
 * index.php front-controller — HTTP status/redirect contract
 *
 * Covers the dispatch logic in src/index.php: the removed legacy filename
 * shims (UserEditor.php / SettingsUser.php, migrated to Admin MVC in #9173),
 * the /index.php -> /v2/dashboard redirect, and the 404 flow for paths that
 * don't resolve to a real .php file under the doc root (bare 404 for
 * js/css-looking paths, the in-app 404 for a signed-in user; a signed-out
 * visitor gets the standalone errors/not-found.php page).
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
                expect(response.status).to.equal(404);
                expect(response.body).to.include("Page Not Found");
            });
    });

    it("no longer special-cases the deleted SettingsUser.php shim", () => {
        cy.request({ url: "/SettingsUser.php", followRedirect: false, failOnStatusCode: false })
            .then((response) => {
                expect(response.status).to.equal(404);
                expect(response.body).to.include("Page Not Found");
            });
    });

    it("shows the in-app 404 with the application header to a signed-in user", () => {
        cy.request({ url: "/this-page-was-never-real.php", failOnStatusCode: false }).then((response) => {
            expect(response.status).to.equal(404);
            expect(response.body).to.include("Page Not Found");
            expect(response.body).to.include("navbar-brand");
            expect(response.body).to.not.include("standalone-page");
        });
    });

    it("shows the same 404 text on an unknown MVC route", () => {
        cy.request({ url: "/v2/this-route-was-never-real", failOnStatusCode: false }).then((response) => {
            expect(response.status).to.equal(404);
            expect(response.body).to.include("Page Not Found");
            expect(response.body).to.include("could not be found. It may have been moved, renamed, or no longer exists.");
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
