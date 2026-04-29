/// <reference types="cypress" />

/**
 * UI tests for Private Notes Timeline Visibility
 *
 * Tests that the timeline UI correctly displays:
 * - Private notes show "[Private Note]" placeholder to admins
 * - Full content visible to note creator
 * - Edit button hidden for admins viewing private notes they didn't create
 */
describe("UI Private Notes Timeline", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    // -----------------------------------------------------------------------
    // Person Timeline - Private Notes
    // -----------------------------------------------------------------------
    describe("Person Timeline - Private Note Visibility", () => {
        it("Admin sees placeholder for another user's private note", () => {
            // User creates a private note
            cy.makePrivateUserAPICall(
                "POST",
                "/api/person/1/note",
                { text: "<p>Secret user note content</p>", private: true },
                201,
            ).then((createResp) => {
                const noteId = createResp.body.note.id;

                // Admin visits person 1 page
                cy.visit("/PersonView.php?PersonID=1");
                cy.wait(1000);

                // Navigate to timeline tab if needed
                cy.get("#nav-item-timeline").click();

                // Timeline should contain the note, but with placeholder text
                cy.contains("[Private Note", { timeout: 5000 }).should("be.visible");
                cy.contains("Secret user note content").should("not.exist");

                // Delete button should be present
                cy.contains("[Private Note").parent().parent().within(() => {
                    cy.get('[data-note-id]').should("exist");
                });

                // Clean up
                cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
            });
        });

        it("Note creator sees full content of their private note", () => {
            // Admin creates private note
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/person/1/note",
                { text: "<p>Admin's full private note</p>", private: true },
                201,
            ).then((createResp) => {
                const noteId = createResp.body.note.id;

                // Admin visits person 1 page
                cy.visit("/PersonView.php?PersonID=1");
                cy.wait(1000);

                // Navigate to timeline
                cy.get("#nav-item-timeline").click();

                // Should see full content (not placeholder)
                cy.contains("Admin's full private note").should("be.visible");

                // Edit button should be present
                cy.contains("Admin's full private note").parent().parent().within(() => {
                    cy.get("a[href*='NoteEditor.php']").should("exist");
                });

                // Clean up
                cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
            });
        });

        it("Private badge displays on private notes", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/person/1/note",
                { text: "<p>Another private note</p>", private: true },
                201,
            ).then((createResp) => {
                const noteId = createResp.body.note.id;

                cy.visit("/PersonView.php?PersonID=1");
                cy.wait(1000);
                cy.get("#nav-item-timeline").click();

                // Private badge should be visible
                cy.contains("Another private note").parent().within(() => {
                    cy.get(".badge").contains("Private").should("be.visible");
                });

                cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
            });
        });
    });

    // -----------------------------------------------------------------------
    // Family Timeline - Private Notes
    // -----------------------------------------------------------------------
    describe("Family Timeline - Private Note Visibility", () => {
        it("Admin sees placeholder for another user's private family note", () => {
            // User creates private family note
            cy.makePrivateUserAPICall(
                "POST",
                "/api/family/1/note",
                { text: "<p>Confidential family information</p>", private: true },
                201,
            ).then((createResp) => {
                const noteId = createResp.body.note.id;

                // Admin visits family 1 page
                cy.visit("/FamilyView.php?FamilyID=1");
                cy.wait(1000);

                // Timeline should show placeholder
                cy.contains("[Private Note").should("be.visible");
                cy.contains("Confidential family information").should("not.exist");

                // Delete button should be present
                cy.contains("[Private Note").parent().parent().within(() => {
                    cy.get('[data-note-id]').should("exist");
                });

                cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
            });
        });

        it("Note creator sees full content of their private family note", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/family/1/note",
                { text: "<p>Admin's private family note with details</p>", private: true },
                201,
            ).then((createResp) => {
                const noteId = createResp.body.note.id;

                cy.visit("/FamilyView.php?FamilyID=1");
                cy.wait(1000);

                // Should see full content
                cy.contains("Admin's private family note with details").should("be.visible");

                // Edit button should be accessible
                cy.contains("Admin's private family note with details")
                    .parent()
                    .parent()
                    .within(() => {
                        cy.get("a[href*='NoteEditor.php']").should("exist");
                    });

                cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
            });
        });

        it("Private badge displays on family timeline", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/family/1/note",
                { text: "<p>Marked private family note</p>", private: true },
                201,
            ).then((createResp) => {
                const noteId = createResp.body.note.id;

                cy.visit("/FamilyView.php?FamilyID=1");
                cy.wait(1000);

                // Private badge should display
                cy.contains("Marked private family note").parent().within(() => {
                    cy.get(".badge").contains("Private").should("be.visible");
                });

                cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
            });
        });
    });

    // -----------------------------------------------------------------------
    // Edit Button Visibility
    // -----------------------------------------------------------------------
    describe("Edit button visibility for private notes", () => {
        it("Edit button hidden when admin views non-owned private note on person timeline", () => {
            cy.makePrivateUserAPICall(
                "POST",
                "/api/person/1/note",
                { text: "<p>User private</p>", private: true },
                201,
            ).then((createResp) => {
                const noteId = createResp.body.note.id;

                cy.visit("/PersonView.php?PersonID=1");
                cy.wait(1000);
                cy.get("#nav-item-timeline").click();

                // Find the private note placeholder
                cy.contains("[Private Note").parent().parent().within(() => {
                    // Dropdown button should exist
                    cy.get("button[data-bs-toggle='dropdown']").should("exist");
                });

                // Open dropdown and check that Edit is NOT present
                cy.contains("[Private Note").parent().parent().within(() => {
                    cy.get("button[data-bs-toggle='dropdown']").click();
                });

                cy.get(".dropdown-menu").within(() => {
                    cy.contains("Edit").should("not.exist");
                    // But Delete should exist
                    cy.contains("Delete").should("exist");
                });

                cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
            });
        });

        it("Edit button visible when admin views own private note on person timeline", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/person/1/note",
                { text: "<p>Admin private</p>", private: true },
                201,
            ).then((createResp) => {
                const noteId = createResp.body.note.id;

                cy.visit("/PersonView.php?PersonID=1");
                cy.wait(1000);
                cy.get("#nav-item-timeline").click();

                // Find the note with full content (not placeholder)
                cy.contains("Admin private").parent().parent().within(() => {
                    cy.get("button[data-bs-toggle='dropdown']").click();
                });

                cy.get(".dropdown-menu").within(() => {
                    cy.contains("Edit").should("be.visible");
                    cy.contains("Delete").should("be.visible");
                });

                cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
            });
        });
    });
});
