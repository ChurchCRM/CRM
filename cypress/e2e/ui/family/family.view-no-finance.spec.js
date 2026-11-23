/// <reference types="cypress" />

/**
 * Regression guard for #11874 / #11875 / #11876 (7.8.6 family-page 500s).
 *
 * The family view runs a finance query for finance-enabled users. A family
 * with no pledges, payments or deposits at all — the fresh-install shape —
 * must still render, and saving a family edit must land back on a working
 * family page. (On installs that reached 7.8.6 via 7.8.5, the underlying
 * cause was a skipped recurring-pledges DB migration; the migration fix
 * ships alongside this spec. CI seeds from Install.sql, so this spec
 * guards the behaviour rather than the missing-column state itself.)
 */
describe("Family view with no financial data", () => {
    const createdFamilyIds = [];
    const familyName = `NoFinance${Cypress._.random(0, 1e6)}`;
    const street = "11874 Regression Way";
    const newStreet = "11876 Updated Way";

    beforeEach(() => {
        cy.setupAdminSession();
    });

    after(() => {
        cy.cleanupFamilies(createdFamilyIds);
    });

    const postEditorForm = (fields) =>
        cy.get("#familyEditor").then(($form) => {
            const body = new URLSearchParams(new FormData($form[0]));
            Object.entries({ ...fields, FamilySubmit: "" }).forEach(([key, value]) => body.set(key, value));
            return cy.request({
                method: "POST",
                url: $form.attr("action"),
                headers: { "content-type": "application/x-www-form-urlencoded" },
                body: body.toString(),
                followRedirect: false,
            });
        });

    const assertFamilyView = (familyId, address) => {
        cy.visit(`/people/family/${familyId}`);
        cy.get("h2.page-title").should("contain.text", familyName);
        cy.get("#family-profile-header").should("be.visible");
        cy.get("#family-address-card").should("contain.text", address);
    };

    it("renders a brand-new family with no financial records, and survives an edit + save", () => {
        cy.visit("/FamilyEditor.php");
        cy.get("#FamilyName").type(familyName);
        postEditorForm({
            Address1: street,
            City: "Kansas City",
            stateType: "dropDown",
            State: "MO",
            Zip: "64111",
            Country: "US",
        }).then((response) => {
            expect(response.status).to.eq(302);
            const familyId = Number(response.headers.location.split("/").pop());
            expect(familyId).to.be.greaterThan(0);
            createdFamilyIds.push(familyId);

            assertFamilyView(familyId, street);

            cy.visit(`/FamilyEditor.php?FamilyID=${familyId}`);
            cy.get("#FamilyName").should("have.value", familyName);
            postEditorForm({ Address1: newStreet }).its("status").should("eq", 302);
            assertFamilyView(familyId, newStreet);
        });
    });
});
