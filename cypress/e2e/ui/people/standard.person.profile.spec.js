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

        // TODO: add editing
    });
});
