/// <reference types="cypress" />

/**
 * Custom field values reach the database exactly as typed, whatever characters they hold.
 *
 * Values are bound as statement parameters, so quotes, backslashes and SQL text are stored
 * as plain text. HTML tags are stripped. "Highest Degree Received" (c3) is a type-4
 * field the editor renders as a textarea and the seed leaves empty for person 2.
 */
describe("Person editor custom field values", () => {
    const personId = 2;
    const field = "c3";
    const selector = `textarea[name="${field}"]`;

    const save = (value) => {
        cy.visit(`/PersonEditor.php?PersonID=${personId}`);
        cy.get(selector).clear();
        if (value !== "") {
            cy.get(selector).type(value, { parseSpecialCharSequences: false });
        }
        cy.get('button[name="PersonSubmit"]').click();
        cy.url().should("include", `/people/view/${personId}`);
    };

    const stored = () =>
        cy.request(`/api/person/${personId}`).then((response) => {
            const item = response.body.singlePersonCustom.find((entry) => entry.id === field);
            return item ? item.value : "";
        });

    beforeEach(() => cy.setupPersonaSession("church-admin"));

    after(() => {
        cy.setupPersonaSession("church-admin");
        save("");
    });

    [
        { name: "quotes and a backslash", typed: `O'Brien "quoted" back\\slash`, expected: `O'Brien "quoted" back\\slash` },
        { name: "SQL text", typed: `x'; DROP TABLE person_custom; --`, expected: `x'; DROP TABLE person_custom; --` },
        { name: "HTML tags", typed: "<b>bold</b> text", expected: "bold text" },
    ].forEach(({ name, typed, expected }) => {
        it(`stores ${name} as typed`, () => {
            save(typed);
            stored().should("eq", expected);
        });
    });

    it("clears the field when the value is emptied", () => {
        save("something");
        save("");
        stored().should("eq", "");
    });
});
