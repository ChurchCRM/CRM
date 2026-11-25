/// <reference types="cypress" />

/**
 * Family "Add Property" popup.
 *
 * The Properties section has an Add Property button that opens a popup with
 * the #input-family-properties select, an optional prompt textarea and the
 * #assign-family-property-btn button. Assignments POST to
 * /api/people/properties/family/{familyId}/{propertyId}.
 */
describe("Family Properties — Add Property popup", () => {
    beforeEach(() => {
        cy.setupAdminSession();
        cy.visit("people/family/1");
        cy.get("#add-family-property-btn", { timeout: 10000 }).click();
        cy.get("#add-family-property-modal").should("be.visible");
    });

    it("opens the assign form in a popup from the Add Property button", () => {
        cy.get("#input-family-properties").should("exist");
        cy.get("#assign-family-property-btn").should("be.visible").and("contain", "Assign");
    });

    it("lists only unassigned properties in the select", () => {
        cy.request("/api/people/properties/family").then((all) => {
            cy.request("/api/people/properties/family/1").then((assigned) => {
                const expected = all.body.length - (assigned.body || []).length;
                cy.get("#input-family-properties option[value]:not([value=''])").should("have.length", expected);
                cy.get("#input-family-properties option:contains('(assigned)')").should("not.exist");
            });
        });
    });

    it("POSTs to /api/people/properties/family/{id}/{propId} when Assign is clicked", function () {
        // Stub the request so the spec is order-independent and doesn't
        // actually mutate the test DB — we only care that the front-end
        // hits the documented API endpoint with a JSON value body.
        cy.intercept(
            "POST",
            "**/api/people/properties/family/*/*",
            { body: { success: true, msg: "stubbed" } },
        ).as("assignFamilyProp");

        cy.get("#input-family-properties option").then(($options) => {
            const pickable = [...$options].find((o) => o.value !== "");
            if (!pickable) {
                this.skip();
                return;
            }
            cy.get("#input-family-properties").select(pickable.value);
            cy.get("#assign-family-property-btn").click();

            cy.wait("@assignFamilyProp").then((intercept) => {
                expect(intercept.request.url).to.match(
                    /\/api\/people\/properties\/family\/1\/\d+$/,
                );
                // value key present (may be empty for properties without
                // a prompt — the handler treats it as "no value").
                expect(intercept.request.body).to.have.property("value");
            });
        });
    });
});
