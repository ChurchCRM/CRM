/// <reference types="cypress" />

/**
 * Saving the person editor keeps the custom fields the editing user cannot see.
 *
 * Seed person 80 has a value in `c2` ("Father of confession - Other"), a custom
 * field restricted to administrators. The office-staff persona (Edit Records,
 * not Admin) cannot see that field in the editor, so a save from the form must
 * leave its stored value alone. The admin API only reads the value back.
 */
describe("Person editor keeps custom fields the user cannot see", () => {
    const personId = 80;
    const hiddenFieldId = "c2";
    const hiddenValue = "Fr. Join Smith";
    const visibleField = 'input[name="c3"]';
    const marker = "Cypress keeps hidden fields";

    const hiddenCustomValue = () =>
        cy.makePrivateAdminAPICall("GET", `/api/person/${personId}`, null, 200).then((response) => {
            const field = response.body.singlePersonCustom.find((item) => item.id === hiddenFieldId);
            return field ? field.value : null;
        });

    const saveVisibleField = (value) => {
        cy.visit(`/PersonEditor.php?PersonID=${personId}`);
        cy.get(visibleField).clear();
        if (value !== "") {
            cy.get(visibleField).type(value);
        }
        cy.get('button[name="PersonSubmit"]').click();
        cy.url().should("include", `/people/view/${personId}`);
    };

    beforeEach(() => {
        cy.setupPersonaSession("office-staff");
    });

    after(() => {
        cy.setupPersonaSession("office-staff");
        saveVisibleField("");
    });

    it("does not show the administrator-only field to the editing user", () => {
        cy.visit(`/PersonEditor.php?PersonID=${personId}`);
        cy.get(visibleField).should("exist");
        cy.get(`[name="${hiddenFieldId}"]`).should("not.exist");
    });

    it("keeps the stored value of the hidden field after a save", () => {
        hiddenCustomValue().should("eq", hiddenValue);

        saveVisibleField(marker);

        hiddenCustomValue().should("eq", hiddenValue);
        cy.visit(`/PersonEditor.php?PersonID=${personId}`);
        cy.get(visibleField).should("have.value", marker);
    });
});
