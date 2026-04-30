/// <reference types="cypress" />

/**
 * UI tests for Private Notes Timeline Visibility
 *
 * Tests that the timeline UI correctly displays:
 * - Private notes show "[Private Note]" placeholder to admins
 * - Full content visible to note creator
 * - Edit button hidden for admins viewing private notes they didn't create
 *
 * Note-creation happens in before() — before beforeEach() establishes the
 * cy.session — so cy.request() never runs with an active session cookie and
 * cannot corrupt the server-side PHP session. One login per test (restored
 * from cy.session cache) is all that is needed.
 */
describe("UI Private Notes Timeline", () => {
    // Populated in before(), cleaned up in after()
    const personNotes = {};
    const familyNotes = {};

    before(() => {
        // Person-timeline notes
        cy.makePrivateUserAPICall("POST", "/api/person/2/note",
            { text: "<p>Secret user note content</p>", private: true }, 201)
            .then(r => { personNotes.userNote = r.body.note.id; });

        cy.makePrivateAdminAPICall("POST", "/api/person/2/note",
            { text: "<p>Admin's full private note</p>", private: true }, 201)
            .then(r => { personNotes.adminNote1 = r.body.note.id; });

        cy.makePrivateAdminAPICall("POST", "/api/person/2/note",
            { text: "<p>Another private note</p>", private: true }, 201)
            .then(r => { personNotes.adminNote2 = r.body.note.id; });

        cy.makePrivateUserAPICall("POST", "/api/person/2/note",
            { text: "<p>User private</p>", private: true }, 201)
            .then(r => { personNotes.editUserNote = r.body.note.id; });

        cy.makePrivateAdminAPICall("POST", "/api/person/2/note",
            { text: "<p>Admin private</p>", private: true }, 201)
            .then(r => { personNotes.editAdminNote = r.body.note.id; });

        // Family-timeline notes
        cy.makePrivateUserAPICall("POST", "/api/family/2/note",
            { text: "<p>Confidential family information</p>", private: true }, 201)
            .then(r => { familyNotes.userNote = r.body.note.id; });

        cy.makePrivateAdminAPICall("POST", "/api/family/2/note",
            { text: "<p>Admin's private family note with details</p>", private: true }, 201)
            .then(r => { familyNotes.adminNote1 = r.body.note.id; });

        cy.makePrivateAdminAPICall("POST", "/api/family/2/note",
            { text: "<p>Marked private family note</p>", private: true }, 201)
            .then(r => { familyNotes.adminNote2 = r.body.note.id; });
    });

    after(() => {
        cy.makePrivateAdminAPICall("DELETE", `/api/note/${personNotes.userNote}`, null, 200);
        cy.makePrivateAdminAPICall("DELETE", `/api/note/${personNotes.adminNote1}`, null, 200);
        cy.makePrivateAdminAPICall("DELETE", `/api/note/${personNotes.adminNote2}`, null, 200);
        cy.makePrivateAdminAPICall("DELETE", `/api/note/${personNotes.editUserNote}`, null, 200);
        cy.makePrivateAdminAPICall("DELETE", `/api/note/${personNotes.editAdminNote}`, null, 200);
        cy.makePrivateAdminAPICall("DELETE", `/api/note/${familyNotes.userNote}`, null, 200);
        cy.makePrivateAdminAPICall("DELETE", `/api/note/${familyNotes.adminNote1}`, null, 200);
        cy.makePrivateAdminAPICall("DELETE", `/api/note/${familyNotes.adminNote2}`, null, 200);
    });

    beforeEach(() => {
        cy.setupAdminSession();
    });

    // -----------------------------------------------------------------------
    // Person Timeline - Private Notes
    // -----------------------------------------------------------------------
    describe("Person Timeline - Private Note Visibility", () => {
        it("Admin sees placeholder for another user's private note", () => {
            cy.visit("/people/view/2");
            cy.get(".breadcrumb", { timeout: 10000 }).should("be.visible");
            cy.get("#nav-item-timeline", { timeout: 10000 }).click();

            cy.contains("[Private Note", { timeout: 5000 }).should("be.visible");
            cy.contains("Secret user note content").should("not.exist");

            cy.contains("[Private Note").parent().parent().within(() => {
                cy.get('[data-note-id]').should("exist");
            });
        });

        it("Note creator sees full content of their private note", () => {
            cy.visit("/people/view/2");
            cy.get(".breadcrumb", { timeout: 10000 }).should("be.visible");
            cy.get("#nav-item-timeline", { timeout: 10000 }).click();

            cy.contains("Admin's full private note").should("be.visible");
            cy.contains("Admin's full private note").parent().parent().within(() => {
                cy.get("a[href*='NoteEditor.php']").should("exist");
            });
        });

        it("Private badge displays on private notes", () => {
            cy.visit("/people/view/2");
            cy.get(".breadcrumb", { timeout: 10000 }).should("be.visible");
            cy.get("#nav-item-timeline", { timeout: 10000 }).click();

            cy.contains("Another private note").closest(".card-body").within(() => {
                cy.get(".badge").contains("Private").should("be.visible");
            });
        });
    });

    // -----------------------------------------------------------------------
    // Family Timeline - Private Notes
    // -----------------------------------------------------------------------
    describe("Family Timeline - Private Note Visibility", () => {
        it("Admin sees placeholder for another user's private family note", () => {
            cy.visit("/people/family/2");
            cy.get(".breadcrumb", { timeout: 10000 }).should("be.visible");

            cy.contains("[Private Note").should("be.visible");
            cy.contains("Confidential family information").should("not.exist");

            cy.contains("[Private Note").parent().parent().within(() => {
                cy.get('[data-note-id]').should("exist");
            });
        });

        it("Note creator sees full content of their private family note", () => {
            cy.visit("/people/family/2");
            cy.get(".breadcrumb", { timeout: 10000 }).should("be.visible");

            cy.contains("Admin's private family note with details").should("be.visible");
            cy.contains("Admin's private family note with details")
                .parent().parent().within(() => {
                    cy.get("a[href*='NoteEditor.php']").should("exist");
                });
        });

        it("Private badge displays on family timeline", () => {
            cy.visit("/people/family/2");
            cy.get(".breadcrumb", { timeout: 10000 }).should("be.visible");

            cy.contains("Marked private family note").closest(".card-body").within(() => {
                cy.get(".badge").contains("Private").should("be.visible");
            });
        });
    });

    // -----------------------------------------------------------------------
    // Edit Button Visibility
    // -----------------------------------------------------------------------
    describe("Edit button visibility for private notes", () => {
        it("Edit button hidden when admin views non-owned private note on person timeline", () => {
            cy.visit("/people/view/2");
            cy.get(".breadcrumb", { timeout: 10000 }).should("be.visible");
            cy.get("#nav-item-timeline", { timeout: 10000 }).click();

            // For a private note the admin doesn't own: no Edit link, but Delete button present.
            cy.contains("[Private Note").closest(".card-body").within(() => {
                cy.get("a[href*='NoteEditor.php']").should("not.exist");
                cy.get("button[data-note-id]").should("exist");
            });
        });

        it("Edit button visible when admin views own private note on person timeline", () => {
            cy.visit("/people/view/2");
            cy.get(".breadcrumb", { timeout: 10000 }).should("be.visible");
            cy.get("#nav-item-timeline", { timeout: 10000 }).click();

            // Admin owns this note: both Edit link and Delete button should be inline.
            cy.contains("Admin private").closest(".card-body").within(() => {
                cy.get("a[href*='NoteEditor.php']").should("be.visible");
                cy.get("button[data-note-id]").should("be.visible");
            });
        });
    });
});
