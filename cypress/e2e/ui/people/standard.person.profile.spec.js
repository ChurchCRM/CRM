/// <reference types="cypress" />

describe("Person Profile", () => {
    const personId = 2;
    
    beforeEach(() => cy.setupStandardSession());

    it("Printable page", () => {
        cy.visit(`PersonView.php?PersonID=${personId}`);
        cy.contains("Print");

        cy.get("#printPerson").click();
        cy.url().should("contain", `PrintView.php?PersonID=${personId}`);
    });

    it("Add a Note", () => {
        cy.visit(`PersonView.php?PersonID=${personId}`);

        // Click FAB note button
        cy.get('.fab-note').click({ force: true });
        cy.url().should("contain", `NoteEditor.php?PersonID=${personId}`);

        const currentDateString = new Date().toISOString();
        const noteText = `This is a test note: ${currentDateString}`;
        cy.typeInQuill("NoteText", noteText);
        // Click Save by text instead of style class
        cy.contains('button', 'Save').click();
        cy.url().should("contain", `PersonView.php?PersonID=${personId}`);

        cy.get("#nav-item-timeline").click();
        cy.contains(noteText);
    });

    it("Edit Why Came", () => {
        cy.visit(`PersonView.php?PersonID=${personId}`);

        // Open Actions dropdown, then click Why Came
        cy.get("#person-actions-dropdown").click();
        cy.get("#editWhyCame").click();
        cy.url().should("contain", `WhyCameEditor.php?PersonID=${personId}`);
        cy.get('tr:nth-child(1) textarea').clear().type('{backspace}');
        cy.get('tr:nth-child(1) textarea').clear().type('I love the lord ');
        cy.get('tr:nth-child(2) textarea').clear().type('{backspace}');
        cy.get('tr:nth-child(2) textarea').clear().type('{backspace}');
        cy.get('tr:nth-child(2) textarea').clear().type('the feeling of being included');
        cy.get('tr:nth-child(3) textarea').clear().type('More Youth Meetings');
        cy.get('tr:nth-child(4) textarea').clear().type('The website ');
        // Use text-based save to avoid relying on button style class
        cy.contains('button', 'Save').click();

        cy.url().should('contains', 'WhyCameEditor.php');
        cy.contains('More Youth Meetings');

    });
});
