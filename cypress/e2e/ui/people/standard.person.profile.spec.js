/// <reference types="cypress" />

describe("Person Profile", () => {
    const personId = 2;
    
    beforeEach(() => cy.setupStandardSession());

    it("Printable page", () => {
        cy.visit(`PersonView.php?PersonID=${personId}`);
        cy.contains("Printable Page");

        cy.get("#printPerson").click();
        cy.url().should("contain", `PrintView.php?PersonID=${personId}`);
    });

    it("Add a Note", () => {
        cy.visit(`PersonView.php?PersonID=${personId}`);

        // Click FAB note button
        cy.get('.fab-note').click();
        cy.url().should("contain", `NoteEditor.php?PersonID=${personId}`);

        const currentDateString = new Date().toISOString();
        const noteText = `This is a test note: ${currentDateString}`;
        cy.typeInQuill("NoteText", noteText);
        cy.get(".btn-success").click();
        cy.url().should("contain", `PersonView.php?PersonID=${personId}`);

        cy.get("#nav-item-timeline").click();
        cy.contains(noteText);
    });

    it("Edit Why Came", () => {
        cy.visit(`PersonView.php?PersonID=${personId}`);
        cy.contains('Edit "Why Came" Notes');

        cy.get("#editWhyCame").click();
        cy.url().should("contain", `WhyCameEditor.php?PersonID=${personId}`);
        cy.get('tr:nth-child(1) textarea').clear().type('{backspace}');
        cy.get('tr:nth-child(1) textarea').clear().type('I love the lord ');
        cy.get('tr:nth-child(2) textarea').clear().type('{backspace}');
        cy.get('tr:nth-child(2) textarea').clear().type('{backspace}');
        cy.get('tr:nth-child(2) textarea').clear().type('the feeling of being included');
        cy.get('tr:nth-child(3) textarea').clear().type('More Youth Meetings');
        cy.get('tr:nth-child(4) textarea').clear().type('The website ');
        cy.get('td > .btn-primary').click();

        cy.url().should('contains', 'WhyCameEditor.php');
        cy.contains('More Youth Meetings');

    });
});
