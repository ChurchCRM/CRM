/// <reference types="cypress" />

/**
 * #10485 through the shared Delete Person control: a person with recorded
 * history is refused with the deactivation advice and stays; a person with
 * none is deleted.
 */
describe("Delete Person control and recorded history (#10485)", () => {
    const created = [];

    const createPerson = (label) =>
        cy.createPersonWithBirthday({ name: `${label}${Date.now()}`, lastName: "DeleteGuardUi" }).then((personId) => {
            created.push(personId);
            return personId;
        });

    const deleteFromProfile = (personId) => {
        cy.visit(`/people/view/${personId}`);
        cy.get("#person-actions-dropdown").first().click();
        cy.get("#deletePersonBtn").first().click();
        cy.get(".bootbox-confirm .bootbox-accept").click();
    };

    beforeEach(() => cy.setupAdminSession());

    after(() => cy.cleanupPeople(created));

    it("refuses a person who has a note and tells the user to deactivate them", () => {
        createPerson("Guarded").then((personId) => {
            cy.makePrivateAdminAPICall("POST", `/api/person/${personId}/note`, { text: "Keep this history" }, 201);
            cy.setupAdminSession({ forceLogin: true });
            deleteFromProfile(personId);
            cy.contains("Deactivate the person instead").should("be.visible");
            cy.makePrivateAdminAPICall("GET", `/api/person/${personId}`, null, 200);
        });
    });

    it("deletes a person with no recorded history", () => {
        createPerson("Unguarded").then((personId) => {
            deleteFromProfile(personId);
            cy.location("pathname").should("match", /\/people\/list$/);
            cy.makePrivateAdminAPICall("GET", `/api/person/${personId}`, null, 404);
        });
    });
});
