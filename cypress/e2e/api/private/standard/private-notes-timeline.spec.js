/// <reference types="cypress" />

/**
 * API tests for Private Notes Timeline Visibility
 *
 * Tests the timeline endpoints to ensure private notes have correct visibility:
 * - GET /api/timeline/person/{id} — admin sees placeholder, creator sees full content
 * - GET /api/timeline/family/{id} — same behavior
 */
describe("API Private Notes Timeline Visibility", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    // -----------------------------------------------------------------------
    // Timeline privacy: Creator can see private note in full
    // -----------------------------------------------------------------------
    describe("Creator visibility - private notes on timeline", () => {
        it("Note creator sees private note with full content on person timeline", () => {
            // Admin (ID 1) creates a private note on person 1
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/person/1/note",
                { text: "<p>Admin's private note about person 1</p>", private: true },
                201,
            ).then((createResp) => {
                const noteId = createResp.body.note.id;

                // Admin fetches person 1 timeline — should see full content
                cy.makePrivateAdminAPICall("GET", "/api/timeline/person/1", null, 200).then(
                    (timelineResp) => {
                        expect(timelineResp.body).to.have.property("timeline");
                        const timeline = timelineResp.body.timeline;

                        const adminNote = timeline.find((item) => item.id === noteId.toString());
                        expect(adminNote).to.exist;
                        expect(adminNote.text).to.include("Admin's private note");
                        expect(adminNote.editLink).to.exist;
                        expect(adminNote.editLink).to.not.equal("");
                    },
                );

                cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
            });
        });

        it("Note creator sees private note with full content on family timeline", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/family/1/note",
                { text: "<p>Admin's private family note</p>", private: true },
                201,
            ).then((createResp) => {
                const noteId = createResp.body.note.id;

                cy.makePrivateAdminAPICall("GET", "/api/timeline/family/1", null, 200).then(
                    (timelineResp) => {
                        const timeline = timelineResp.body.timeline;
                        const adminNote = timeline.find((item) => item.id === noteId.toString());
                        expect(adminNote).to.exist;
                        expect(adminNote.text).to.include("Admin's private family note");
                        expect(adminNote.editLink).to.exist;
                        expect(adminNote.editLink).to.not.equal("");
                    },
                );

                cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
            });
        });
    });

    // -----------------------------------------------------------------------
    // Timeline privacy: Admin (non-creator) sees placeholder
    // -----------------------------------------------------------------------
    describe("Non-creator admin visibility - private notes on timeline", () => {
        it("Non-creator admin sees placeholder (no content) for private note on person timeline", () => {
            // User creates a private note on person 1
            cy.makePrivateUserAPICall(
                "POST",
                "/api/person/1/note",
                { text: "<p>User's private note - secret content</p>", private: true },
                201,
            ).then((createResp) => {
                const noteId = createResp.body.note.id;

                // Admin (different from creator) fetches person 1 timeline
                cy.makePrivateAdminAPICall("GET", "/api/timeline/person/1", null, 200).then(
                    (timelineResp) => {
                        const timeline = timelineResp.body.timeline;
                        const userNote = timeline.find((item) => item.id === noteId.toString());
                        expect(userNote).to.exist;

                        // Should see placeholder, not original content
                        expect(userNote.text).to.include("Private Note");
                        expect(userNote.text).to.not.include("secret content");

                        // Should NOT have edit link (empty string)
                        expect(userNote.editLink).to.equal("");

                        // But should have delete link (admin can delete)
                        expect(userNote.deleteLink).to.exist;
                    },
                );

                cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
            });
        });

        it("Non-creator admin sees placeholder for private note on family timeline", () => {
            cy.makePrivateUserAPICall(
                "POST",
                "/api/family/1/note",
                { text: "<p>User's private family note - confidential</p>", private: true },
                201,
            ).then((createResp) => {
                const noteId = createResp.body.note.id;

                cy.makePrivateAdminAPICall("GET", "/api/timeline/family/1", null, 200).then(
                    (timelineResp) => {
                        const timeline = timelineResp.body.timeline;
                        const userNote = timeline.find((item) => item.id === noteId.toString());
                        expect(userNote).to.exist;
                        expect(userNote.text).to.include("Private Note");
                        expect(userNote.text).to.not.include("confidential");
                        expect(userNote.editLink).to.equal("");
                        expect(userNote.deleteLink).to.exist;
                    },
                );

                cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
            });
        });
    });

    // -----------------------------------------------------------------------
    // Timeline privacy: Non-admin, non-creator cannot see private note
    // -----------------------------------------------------------------------
    describe("Non-creator non-admin visibility - private notes hidden", () => {
        it("Non-admin user does not see private note from another user on timeline", () => {
            // Admin creates a private note
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/person/1/note",
                { text: "<p>Admin's private secret</p>", private: true },
                201,
            ).then((createResp) => {
                const noteId = createResp.body.note.id;

                // Non-admin user views timeline — should not see this private note
                cy.makePrivateUserAPICall("GET", "/api/timeline/person/1", null, 200).then(
                    (timelineResp) => {
                        const timeline = timelineResp.body.timeline;
                        const adminNote = timeline.find((item) => item.id === noteId.toString());
                        expect(adminNote).to.not.exist;
                    },
                );

                cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
            });
        });
    });

    // -----------------------------------------------------------------------
    // Public notes are visible to all
    // -----------------------------------------------------------------------
    describe("Public notes visible to everyone", () => {
        it("Admin and non-admin both see full content of public notes", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/person/1/note",
                { text: "<p>Public note - visible to all</p>", private: false },
                201,
            ).then((createResp) => {
                const noteId = createResp.body.note.id;

                // Admin sees full content
                cy.makePrivateAdminAPICall("GET", "/api/timeline/person/1", null, 200).then(
                    (adminResp) => {
                        const adminTimeline = adminResp.body.timeline;
                        const note = adminTimeline.find((item) => item.id === noteId.toString());
                        expect(note.text).to.include("visible to all");
                        expect(note.editLink).to.not.equal("");
                    },
                );

                // Non-admin also sees full content
                cy.makePrivateUserAPICall("GET", "/api/timeline/person/1", null, 200).then(
                    (userResp) => {
                        const userTimeline = userResp.body.timeline;
                        const note = userTimeline.find((item) => item.id === noteId.toString());
                        expect(note.text).to.include("visible to all");
                    },
                );

                cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
            });
        });
    });
});
