/// <reference types="cypress" />

/**
 * MVC Error Pages — HTTP status code contract
 *
 * Pure-API assertions that unknown MVC routes return the correct status code.
 * DOM-level rendering tests (Tabler layout, nav shell, stack-trace leakage)
 * live in cypress/e2e/ui/system/mvc-error-pages.spec.js.
 */
describe("API Private Admin - MVC Error Page Status Codes", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    describe("v2 404 — unknown route", () => {
        it("should return HTTP 404 for an unknown v2 route", () => {
            cy.request({ url: "/v2/this-route-does-not-exist", failOnStatusCode: false })
                .its("status")
                .should("equal", 404);
        });
    });
});
