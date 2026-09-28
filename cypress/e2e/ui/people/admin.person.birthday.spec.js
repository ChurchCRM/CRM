/// <reference types="cypress" />
/**
 * Birthday round trip through the person editor (#9788).
 *
 * Exercises the cy.createPersonWithBirthday / cy.deletePersonByName pair:
 * create a person with a birthday in the editor, check the birthday survives
 * to the view page and back into the editor, then delete the person by name
 * and confirm the record is gone.
 *
 * Admin session: deleting a person requires the DeleteRecords role.
 */

describe("Person birthday", () => {
    const firstName = `Birthday${Cypress._.random(0, 1e6)}`;
    const person = { name: firstName, month: 5, day: 1, year: 1990 };

    let personId = null;

    beforeEach(() => cy.setupAdminSession());

    after(() => {
        cy.setupAdminSession();
        cy.deletePersonByName(firstName);

        if (personId !== null) {
            cy.apiRequest({
                method: "GET",
                url: `/api/person/${personId}`,
            })
                .its("status")
                .should("eq", 404);
        }
    });

    it("keeps the birthday on the view page and back in the editor", () => {
        cy.createPersonWithBirthday(person).then((id) => {
            personId = id;

            // The view page renders the birthday beside a cake icon. The date
            // format is configurable (sDateFormatLong), so assert on the year
            // rather than on a particular ordering of the parts.
            cy.get(".fa-cake-candles")
                .should("exist")
                .parent()
                .should("contain", String(person.year));

            // Re-opening the editor must bring the birthday back in the
            // zero-padded form the selects emit.
            cy.visit(`/PersonEditor.php?PersonID=${id}`);
            cy.get("#BirthMonth").should("have.value", "05");
            cy.get("#BirthDay").should("have.value", "01");
            cy.get("#BirthYear").should("have.value", String(person.year));
        });
    });
});
