/// <reference types="cypress" />

/**
 * Person profile: Address card above the tabs and the Add / Edit Property popup.
 * Seed person 2 (Mathew Campbell) belongs to the geocoded Campbell family.
 */
describe("Person profile address and properties", () => {
    const personId = 2;
    const valuedPropertyId = 1; // person property with a value prompt ("Disabled")

    const unassign = () =>
        cy.makePrivateAdminAPICall("DELETE", `/api/people/properties/person/${personId}/${valuedPropertyId}`, null, [200, 404]);

    beforeEach(() => {
        cy.setupAdminSession();
    });

    afterEach(() => {
        unassign();
    });

    it("shows the Address card above the tabs with a map", () => {
        cy.visit(`/people/view/${personId}`);
        cy.get("#person-address-card").should("contain.text", "Daisy Dr");
        cy.get("#person-map").should("exist");
        cy.get("#person-address-card")
            .invoke("offset")
            .its("top")
            .then((addressTop) => {
                cy.get("#nav-item-overview").invoke("offset").its("top").should("be.gt", addressTop);
            });
    });

    it("adds a valued property in the popup, then edits its value", () => {
        unassign();
        cy.visit(`/people/view/${personId}`);
        cy.get("#add-person-property-btn").click();
        cy.get("#add-person-property-modal").should("be.visible");
        cy.focused().should("have.id", "add-person-property-modal"); // the fade-in has finished
        cy.get("#input-person-properties").select(String(valuedPropertyId));
        cy.get("#prompt-box textarea").type("first note");
        cy.get("#assign-property-btn").click();
        cy.contains(".list-group-item", "Disabled", { timeout: 15000 }).should("contain.text", "first note");

        cy.get(".edit-property-btn").first().click();
        cy.get("#add-person-property-modal").should("be.visible").and("contain.text", "Edit Property");
        cy.focused().should("have.id", "add-person-property-modal");
        cy.get("#prompt-box textarea").should("have.value", "first note").clear().type("second note");
        cy.get("#assign-property-btn").click();
        cy.contains(".list-group-item", "Disabled", { timeout: 15000 }).should("contain.text", "second note");

        cy.get("#add-person-property-btn").click();
        cy.get(`#input-person-properties option[value="${valuedPropertyId}"]`).should("not.exist");
    });

    it("stacks the profile on a phone without sideways scrolling", () => {
        cy.viewport(390, 844);
        cy.visit(`/people/view/${personId}`);
        cy.document().then((doc) => {
            expect(doc.documentElement.scrollWidth).to.be.at.most(390);
        });
        cy.get("#person-address-card").scrollIntoView().should("be.visible");
    });
});
