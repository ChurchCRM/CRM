/// <reference types="cypress" />

/**
 * API tests for Private Notes Timeline Visibility
 *
 * Tests the timeline endpoints to ensure private notes have correct visibility
 * per the #9036 policy:
 * - Admin sees FULL CONTENT of any private note (no placeholder — policy inverted from old behavior)
 * - Note creator (any role) sees their own private note with full content
 * - Notes=1 non-admin sees only their own private notes; other users' private notes are absent
 */
describe("API Private Notes Timeline Visibility", () => {
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

                // Admin fetches person 1 timeline — should see full content (admin is author)
                cy.makePrivateAdminAPICall("GET", "/api/timeline/person/1", null, 200).then(
                    (timelineResp) => {
                        expect(timelineResp.body).to.have.property("timeline");
                        const timeline = timelineResp.body.timeline;

                        const adminNote = timeline.find((item) => item.id === noteId.toString());
                        expect(adminNote).to.exist;
                        // Full content — no placeholder
                        expect(adminNote.text).to.include("Admin's private note");
                        expect(adminNote.text).to.not.include("[Private Note");
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
                        expect(adminNote.text).to.not.include("[Private Note");
                        expect(adminNote.editLink).to.exist;
                        expect(adminNote.editLink).to.not.equal("");
                    },
                );

                cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
            });
        });
    });

    // -----------------------------------------------------------------------
    // Timeline privacy: Admin (non-creator) sees FULL CONTENT (new behavior)
    // The old [Private Note — visible only to creator] placeholder is removed.
    // Admins now see full content of private notes they did not author.
    // -----------------------------------------------------------------------
    describe("Non-creator admin visibility - admin sees full private note content", () => {
        it("Admin sees FULL CONTENT (not placeholder) for user-authored private note on person timeline", () => {
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

                        // Admin sees full content — NOT the old [Private Note] placeholder
                        expect(userNote.text).to.include("secret content");
                        expect(userNote.text).to.not.include("[Private Note");

                        // Admin has edit link (can edit any note)
                        expect(userNote.editLink).to.be.a("string").and.not.equal("");

                        // Delete link still present
                        expect(userNote.deleteLink).to.exist;
                    },
                );

                cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
            });
        });

        it("Admin sees FULL CONTENT for user-authored private note on family timeline", () => {
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
                        // Full content
                        expect(userNote.text).to.include("confidential");
                        expect(userNote.text).to.not.include("[Private Note");
                        expect(userNote.editLink).to.be.a("string").and.not.equal("");
                        expect(userNote.deleteLink).to.exist;
                    },
                );

                cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
            });
        });
    });

    // -----------------------------------------------------------------------
    // Timeline privacy: Notes=1 non-admin, non-creator — private note absent
    // -----------------------------------------------------------------------
    describe("Non-creator non-admin visibility - private notes hidden", () => {
        it("Notes=1 user does not see another user's private note on timeline (absent, not placeholder)", () => {
            // Admin creates a private note
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/person/1/note",
                { text: "<p>Admin's private secret</p>", private: true },
                201,
            ).then((createResp) => {
                const noteId = createResp.body.note.id;

                // Non-admin user views timeline — private note should be completely absent
                cy.makePrivateUserAPICall("GET", "/api/timeline/person/1", null, 200).then(
                    (timelineResp) => {
                        const timeline = timelineResp.body.timeline;
                        const adminNote = timeline.find((item) => item.id === noteId.toString());
                        // Absent — not even a placeholder
                        expect(adminNote).to.not.exist;
                    },
                );

                cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
            });
        });

        it("Notes=1 user DOES see their own private note on timeline", () => {
            cy.makePrivateUserAPICall(
                "POST",
                "/api/person/1/note",
                { text: "<p>User's own private note</p>", private: true },
                201,
            ).then((createResp) => {
                const noteId = createResp.body.note.id;

                cy.makePrivateUserAPICall("GET", "/api/timeline/person/1", null, 200).then(
                    (timelineResp) => {
                        const timeline = timelineResp.body.timeline;
                        const ownNote = timeline.find((item) => item.id === noteId.toString());
                        expect(ownNote).to.exist;
                        expect(ownNote.text).to.include("User's own private note");
                    },
                );

                cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
            });
        });
    });

    // -----------------------------------------------------------------------
    // Public notes are visible to Notes=1 and Admin
    // -----------------------------------------------------------------------
    describe("Public notes visible to Notes=1 and Admin", () => {
        it("Admin and Notes=1 user both see full content of public notes", () => {
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
