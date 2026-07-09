/// <reference types="cypress" />

/**
 * Family property inline-assign form (7.2.1).
 *
 * The family profile used to open a bootbox.prompt modal when the user
 * clicked "Assign". That flow has been replaced with an inline form in
 * the sidebar that mirrors the Person view UX — #input-family-properties
 * select + optional prompt textarea + #assign-family-property-btn button.
 *
 * These tests verify the inline form renders, populates from the master
 * property list, and that assignments POST to the existing
 * /api/people/properties/family/{familyId}/{propertyId} endpoint.
 */
describe("Family Properties — inline assign form", () => {
    beforeEach(() => {
        cy.setupAdminSession();
        cy.visit("people/family/1");
    });

    it("renders the inline assign form (not a bootbox modal)", () => {
        // Wait for the API-loaded list to finish populating and the form
        // wrapper to be revealed. The legacy flow rendered no select at
        // all; seeing the select here proves the new UX is in place.
        cy.get("#family-property-assign-wrapper", { timeout: 10000 }).should("be.visible");
        cy.get("#input-family-properties").should("exist");
        cy.get("#assign-family-property-btn").should("be.visible").and("contain", "Assign");
    });

    it("populates the select from the master family property definitions API", () => {
        cy.request("/api/people/properties/family").then((response) => {
            expect(response.status).to.equal(200);
            // The API returns every family-class property (even assigned
            // ones). The select should hold one <option> per entry plus
            // the empty placeholder.
            cy.get("#input-family-properties option").should("have.length", response.body.length + 1);
        });
    });

    it("marks already-assigned properties with an (assigned) suffix so users can edit their value", () => {
        cy.request(`/api/people/properties/family/1`).then((response) => {
            const assigned = response.body || [];
            if (assigned.length === 0) {
                // Nothing to check — this family has no assignments in the
                // test DB. The other specs still cover the happy path.
                return;
            }
            cy.get("#input-family-properties option:contains('(assigned)')").should(
                "have.length.at.least",
                1,
            );
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
