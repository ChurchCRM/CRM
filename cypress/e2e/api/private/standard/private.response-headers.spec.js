/// <reference types="cypress" />

describe("API response headers (#10211)", () => {
    it("authenticated API responses are nosniff, uncached and carry the version", () => {
        cy.makePrivateAdminAPICall("GET", "/api/persons/latest", null, 200).then((response) => {
            expect(response.headers["x-content-type-options"]).to.eq("nosniff");
            expect(response.headers["cache-control"]).to.include("no-store");
            expect(response.headers["x-crm-version"]).to.be.a("string").and.not.be.empty;
        });
    });

    it("unauthenticated API responses do not disclose the version", () => {
        cy.request({ method: "GET", url: "/api/public/data/countries", failOnStatusCode: false }).then((response) => {
            expect(response.headers["x-content-type-options"]).to.eq("nosniff");
            expect(response.headers).to.not.have.property("x-crm-version");
        });
    });
});
