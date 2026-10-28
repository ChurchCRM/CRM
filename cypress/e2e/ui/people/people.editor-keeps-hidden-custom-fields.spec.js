/// <reference types="cypress" />

/**
 * Saving the person editor keeps the custom fields the editing user cannot see.
 *
 * Seed person 2 holds a value in custom fields of every security class: `c2`
 * (administrators), `c5`/`c6`/`c12` (Finance), `c7` (Notes), `c9` (Manage Groups),
 * `c11` (Delete Records), plus fields open to every editor. Each persona below sees
 * only its own classes in the editor. A save from the form must change the visible
 * field it edited and nothing else, so the church-admin snapshot taken before and
 * after the save differs in that one field only.
 *
 * The snapshots are read through the church-admin browser session, not an API key:
 * the API-key helpers replace the session cookie, which would turn the editing
 * persona into an administrator.
 */
describe("Person editor keeps custom fields the user cannot see", () => {
    const personId = 2;
    const editedField = "c3";
    const marker = "Cypress keeps hidden fields";
    // "Highest Degree Received" is a type-4 custom field, which the editor renders as a textarea.
    const editedSelector = `textarea[name="${editedField}"]`;

    // persona, then the custom fields it must not see in the editor
    const cases = [
        { persona: "office-staff", hidden: ["c2", "c5", "c6", "c7", "c9", "c11", "c12"] },
        { persona: "staff-all-but-admin", hidden: ["c2"] },
    ];

    const snapshotAsAdmin = () => {
        cy.setupPersonaSession("church-admin");
        return cy.request(`/api/person/${personId}`).then((response) => {
            const values = {};
            response.body.singlePersonCustom.forEach((item) => {
                values[item.id] = item.value;
            });
            return values;
        });
    };

    const saveEditedField = (persona, value) => {
        cy.setupPersonaSession(persona);
        cy.visit(`/PersonEditor.php?PersonID=${personId}`);
        cy.get(editedSelector).clear();
        if (value !== "") {
            cy.get(editedSelector).type(value);
        }
        cy.get('button[name="PersonSubmit"]').click();
        cy.url().should("include", `/people/view/${personId}`);
    };

    cases.forEach(({ persona, hidden }) => {
        describe(persona, () => {
            after(() => {
                saveEditedField(persona, "");
            });

            it("does not show the fields of other security classes", () => {
                cy.setupPersonaSession(persona);
                cy.visit(`/PersonEditor.php?PersonID=${personId}`);
                cy.get(editedSelector).should("exist");
                hidden.forEach((field) => {
                    cy.get(`[name="${field}"]`).should("not.exist");
                });
            });

            it("changes only the edited field when the form is saved", () => {
                snapshotAsAdmin().then((before) => {
                    hidden.forEach((field) => expect(before[field], `seeded ${field}`).to.exist);

                    saveEditedField(persona, marker);

                    snapshotAsAdmin().then((after) => {
                        expect(after[editedField]).to.eq(marker);
                        const { [editedField]: _beforeEdited, ...beforeRest } = before;
                        const { [editedField]: _afterEdited, ...afterRest } = after;
                        expect(afterRest).to.deep.eq(beforeRest);
                    });
                });
            });
        });
    });
});
