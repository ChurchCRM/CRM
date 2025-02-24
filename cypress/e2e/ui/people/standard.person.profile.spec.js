/// <reference types="cypress" />

context("Person Profile", () => {
    const personId = 2;

    it("Printable page", () => {
        cy.loginStandard(`PersonView.php?PersonID=${personId}`);
        cy.contains("Printable Page");

        cy.get("#printPerson").click();
        cy.url().should("contains", `PrintView.php?PersonID=${personId}`);
    });

    it("Add a Note", () => {
        cy.loginStandard(`PersonView.php?PersonID=${personId}`);
        cy.contains("Add a Note");

        cy.get("#addNote").click();
        cy.url().should("contains", `NoteEditor.php?PersonID=${personId}`);

        const currentDateString = new Date().toISOString();
        const noteText = `This is a test note: ${currentDateString}`;
        cy.get("#NoteText").type(noteText);
        cy.get(".btn-success").click();
        cy.url().should("contains", `PersonView.php?PersonID=${personId}`);

        cy.get("#nav-item-timeline").click();
        cy.contains(noteText);
    });

    it("Edit Why Came", () => {
        cy.loginStandard(`PersonView.php?PersonID=${personId}`);
        cy.contains('Edit "Why Came" Notes');

        cy.get("#editWhyCame").click();
        cy.url().should("contains", `WhyCameEditor.php?PersonID=${personId}`);
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
