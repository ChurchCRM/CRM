/// <reference types="cypress" />

describe("Person Profile", () => {
    const personId = 2;

    beforeEach(() => cy.setupStandardSession());

    it("should display breadcrumbs with family link", () => {
        cy.visit(`PersonView.php?PersonID=${personId}`);

        // Breadcrumb should show Home / People / Family / Person
        cy.get(".breadcrumb").within(() => {
            cy.contains("People");
            // Family name should be a link in breadcrumbs
            cy.get("a[href*='/v2/family/']").should("exist");
        });
    });

    it("should display toolbar with Edit, Print, Cart, Actions", () => {
        cy.visit(`PersonView.php?PersonID=${personId}`);

        cy.contains("a.btn", "Edit").should("be.visible");
        cy.get("#printPerson").should("be.visible");
        cy.get("#AddPersonToCart").should("be.visible");
        cy.get("#person-actions-dropdown").should("be.visible");
    });

    it("should show person info card with photo and details", () => {
        cy.visit(`PersonView.php?PersonID=${personId}`);

        // Photo card should exist
        cy.get("[data-image-entity-type='person']").should("exist");

        // Contact & Personal Info card
        cy.contains("Contact & Personal Info");
    });

    it("should display timeline tab active by default", () => {
        cy.visit(`PersonView.php?PersonID=${personId}`);

        cy.get("#nav-item-timeline").should("have.class", "active");
        cy.get("#timeline").should("have.class", "active");
    });

    it("should display tabs for Timeline, Groups, and Volunteer", () => {
        cy.visit(`PersonView.php?PersonID=${personId}`);

        cy.get("#nav-item-timeline").should("be.visible");
        cy.get("#nav-item-groups").should("be.visible");
        cy.get("#nav-item-volunteer").should("be.visible");
    });

    it("Printable page", () => {
        cy.visit(`PersonView.php?PersonID=${personId}`);
        cy.contains("Print");

        cy.get("#printPerson").click();
        cy.url().should("contain", `PrintView.php?PersonID=${personId}`);
    });

    it("Add a Note", () => {
        cy.visit(`PersonView.php?PersonID=${personId}`);

        // Open Actions dropdown, then click Add Note
        cy.get("#person-actions-dropdown").click();
        cy.contains('.dropdown-item', 'Add Note').click();
        cy.url().should("contain", `NoteEditor.php?PersonID=${personId}`);

        const currentDateString = new Date().toISOString();
        const noteText = `This is a test note: ${currentDateString}`;
        cy.typeInQuill("NoteText", noteText);
        // Click the submit button (it's an <input type="submit">, not a <button>)
        cy.get('input[type="submit"][name="Submit"]').click();
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
        cy.get('textarea[name="Join"]').clear().type('I love the lord ');
        cy.get('textarea[name="Come"]').clear().type('the feeling of being included');
        cy.get('textarea[name="Suggest"]').clear().type('More Youth Meetings');
        cy.get('textarea[name="HearOfUs"]').clear().type('The website ');
        // Use text-based save to avoid relying on button style class
        cy.contains('button', 'Save').click();

        cy.url().should('contains', 'WhyCameEditor.php');
        cy.contains('More Youth Meetings');

    });
});
