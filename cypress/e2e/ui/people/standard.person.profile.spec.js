/// <reference types="cypress" />

describe("Person Profile", () => {
    const personId = 2;

    beforeEach(() => cy.setupStandardSession());

    it("shows the person with a family breadcrumb, toolbar and tabs", () => {
        cy.visit(`/people/view/${personId}`);

        cy.get(".breadcrumb").within(() => {
            cy.contains("People");
            cy.get("a[href*='/people/family/']").should("exist");
        });
        cy.contains("a.btn", "Edit").should("be.visible");
        cy.get("#printPerson").should("be.visible");
        cy.get("#AddPersonToCart").should("be.visible");
        cy.get("#person-actions-dropdown").should("be.visible");
        cy.get("#uploadImageButton").should("exist");
        cy.contains("Contact & Personal Info");
        cy.get("#nav-item-timeline").should("have.class", "active");
        cy.get("#timeline").should("have.class", "active");
        cy.get("#nav-item-groups").should("be.visible");
        cy.get("#nav-item-volunteer").should("be.visible");
    });

    it("Print button triggers window.print", () => {
        cy.visit(`/people/view/${personId}`);

        cy.window().then((win) => {
            cy.stub(win, "print").as("printStub");
        });
        cy.get("#printPerson").should("be.visible").click();
        cy.get("@printStub").should("have.been.calledOnce");
    });

    it("Add a Note", () => {
        cy.visit(`/people/view/${personId}`);

        // "Add Note" is a standalone toolbar button, not inside the Actions dropdown
        cy.contains("button", "Add Note").click();
        cy.focused().should("have.id", "noteWhyCameModal");

        const currentDateString = new Date().toISOString();
        const noteText = `This is a test note: ${currentDateString}`;
        cy.get("#noteText").type(noteText);
        cy.get("#noteWhyCameSave").click();
        cy.get("#noteWhyCameModal").should("not.exist");

        cy.get("#nav-item-timeline").click();
        cy.contains(noteText);
    });

    it("Edit Why Came", () => {
        cy.visit(`/people/view/${personId}`);

        cy.get("#person-actions-dropdown").click();
        cy.get("#editWhyCame").click();
        cy.focused().should("have.id", "noteWhyCameModal");
        cy.get("#whyJoin").should("be.enabled");
        cy.get("#whyJoin").clear().type("I love the lord ");
        cy.get("#whyCome").clear().type("the feeling of being included");
        cy.get("#whySuggest").clear().type("More Youth Meetings");
        cy.get("#whyHear").clear().type("The website ");
        cy.get("#noteWhyCameSave").click();
        cy.get("#noteWhyCameModal").should("not.exist");

        cy.get("#person-actions-dropdown").click();
        cy.get("#editWhyCame").click();
        cy.get("#whySuggest").should("have.value", "More Youth Meetings");
    });
});

describe("Why Came editor for an Edit Records-only user", () => {
    beforeEach(() => cy.setupNoFinanceSession());

    it("opens the editor from the person page and saves", () => {
        const note = "Saved by an Edit Records-only user";
        cy.visit("/people/view/2");
        cy.get("#person-actions-dropdown").click();
        cy.get("#editWhyCame").click();
        cy.focused().should("have.id", "noteWhyCameModal");
        cy.get("#whyJoin").should("be.enabled");
        cy.get("#whyJoin").clear().type(note);
        cy.get("#noteWhyCameSave").click();
        cy.get("#noteWhyCameModal").should("not.exist");

        cy.get("#person-actions-dropdown").click();
        cy.get("#editWhyCame").click();
        cy.get("#whyJoin").should("have.value", note);
    });
});
