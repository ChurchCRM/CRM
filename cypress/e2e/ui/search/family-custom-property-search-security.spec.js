/// <reference types="cypress" />

/**
 * Global search only matches family custom fields the signed-in user may see.
 *
 * Seed family 1 holds "Envelope audit 2026" in a Finance field, "Home visit due in
 * spring." in a Notes field, and 2001 in a field open to every user. A search that
 * matched a hidden field would confirm its value to a user who cannot read it.
 */
describe("Family custom property search - field security", () => {
    const configUrl = "admin/api/system/config/bSearchIncludeFamilyCustomProperties";
    const customPropertyHit = (query) =>
        cy
            .request({ url: `/api/search/${encodeURIComponent(query)}`, failOnStatusCode: false })
            .then((response) => {
                expect(response.status).to.eq(200);
                return JSON.stringify(response.body).includes("family-custom-prop-");
            });

    before(() => {
        cy.makePrivateAdminAPICall("POST", configUrl, { value: "1" }, 200);
    });

    after(() => {
        cy.makePrivateAdminAPICall("POST", configUrl, { value: "0" }, 200);
    });

    it("church-admin matches every field", () => {
        cy.setupPersonaSession("church-admin");
        customPropertyHit("Envelope audit").should("eq", true);
        customPropertyHit("Home visit due").should("eq", true);
        customPropertyHit("2001").should("eq", true);
    });

    it("staff-all-but-admin matches the Finance and Notes fields", () => {
        cy.setupPersonaSession("staff-all-but-admin");
        customPropertyHit("Envelope audit").should("eq", true);
        customPropertyHit("Home visit due").should("eq", true);
    });

    it("office-staff matches only the open field", () => {
        cy.setupPersonaSession("office-staff");
        customPropertyHit("Envelope audit").should("eq", false);
        customPropertyHit("Home visit due").should("eq", false);
        customPropertyHit("2001").should("eq", true);
    });
});
