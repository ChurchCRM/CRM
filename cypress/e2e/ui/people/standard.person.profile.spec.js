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
        cy.get("#person-profile-header #person-contact").should("be.visible");
        cy.get("#nav-item-overview").should("have.class", "active");
        cy.get("#overview").should("have.class", "active");
        cy.get("#nav-item-timeline").should("be.visible");
        cy.get("#nav-item-groups").should("be.visible");
        cy.get("#nav-item-volunteer").should("be.visible");
    });

    it("keeps Timeline as the last tab", () => {
        cy.visit(`/people/view/${personId}`);
        cy.get(".card-header-pills .nav-link:visible").last().should("have.id", "nav-item-timeline");
    });

    it("shows the seeded qualification and upcoming shift on the Volunteer tab", () => {
        cy.visit(`/people/view/${personId}`);
        cy.get("#nav-item-volunteer").click();
        cy.get("#person-volunteer-qualifications").should("contain", "Seeded Door Greeter");
        cy.get("#person-volunteer-assignments").should("contain", "Seeded Door Greeter");
    });

    it("opens the tab that holds the URL hash target", () => {
        cy.visit(`/people/view/${personId}#portal-signin-card`);
        cy.get("#nav-item-portal").should("have.class", "active");
        cy.get("#portal-signin-card").should("be.visible");
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
        // The note field is a Quill rich-text editor (toolbar + editable area),
        // not a plain textarea — drive it through the Quill registry.
        cy.get("#noteWhyCameModal .ql-toolbar").should("be.visible");
        cy.get("#noteText").should("have.class", "ql-container");
        cy.typeInQuill("noteText", noteText);
        cy.get("#noteWhyCameSave").click();
        cy.get("#noteWhyCameModal").should("not.exist");

        cy.get("#nav-item-notes").click();
        cy.contains(noteText);
    });

    it("Add a Note with rich text formatting that survives save and reopen", () => {
        cy.visit(`/people/view/${personId}`);

        cy.contains("button", "Add Note").click();
        cy.focused().should("have.id", "noteWhyCameModal");
        cy.get("#noteWhyCameModal .ql-toolbar").should("be.visible");

        const stamp = new Date().toISOString();
        // Enter bold text through the Quill API, the same HTML the
        // toolbar's Bold button produces.
        cy.window().then((win) => {
            win.quillEditors["noteText"].setContents([
                { insert: `Rich note ${stamp} ` },
                { insert: "bolded", attributes: { bold: true } },
                { insert: "\n" },
            ]);
        });
        cy.get("#noteWhyCameSave").click();
        cy.get("#noteWhyCameModal").should("not.exist");

        // The timeline renders the stored HTML as formatting, not escaped tags.
        cy.get("#nav-item-notes").click();
        cy.contains(".note-preview", `Rich note ${stamp}`).within(() => {
            cy.get("strong").should("contain", "bolded");
        });

        // Reopening the note loads the HTML back into the editor as formatting.
        cy.contains(".note-preview", `Rich note ${stamp}`)
            .closest(".card-body")
            .within(() => {
                cy.get("[data-open-note-id]").click();
            });
        cy.focused().should("have.id", "noteWhyCameModal");
        cy.get("#noteWhyCameModal .ql-editor strong").should("contain", "bolded");
        cy.getQuillText("noteText").should("contain", `Rich note ${stamp}`);
    });

    it("Edit Why Came", () => {
        cy.visit(`/people/view/${personId}`);

        cy.get("#editWhyCame").click();
        cy.focused().should("have.id", "noteWhyCameModal");
        cy.get("#whyJoin").should("be.enabled");
        cy.get("#whyJoin").clear().type("I love the lord ");
        cy.get("#whyCome").clear().type("the feeling of being included");
        cy.get("#whySuggest").clear().type("More Youth Meetings");
        cy.get("#whyHear").clear().type("The website ");
        cy.get("#noteWhyCameSave").click();
        cy.get("#noteWhyCameModal").should("not.exist");

        cy.get("#editWhyCame").click();
        cy.get("#whySuggest").should("have.value", "More Youth Meetings");
    });
});

describe("Why Came editor for an Edit Records-only user", () => {
    beforeEach(() => cy.setupNoFinanceSession());

    it("opens the editor from the person page and saves", () => {
        const note = "Saved by an Edit Records-only user";
        cy.visit("/people/view/2");
        cy.get("#editWhyCame").click();
        cy.focused().should("have.id", "noteWhyCameModal");
        cy.get("#whyJoin").should("be.enabled");
        cy.get("#whyJoin").clear().type(note);
        cy.get("#noteWhyCameSave").click();
        cy.get("#noteWhyCameModal").should("not.exist");

        cy.get("#editWhyCame").click();
        cy.get("#whyJoin").should("have.value", note);
    });
});
