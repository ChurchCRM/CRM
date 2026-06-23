/// <reference types="cypress" />

/**
 * API tests for Notes Visibility Policy (#9036)
 *
 * Covers the full permission matrix:
 *  - GET  /api/person/{id}/notes     — Notes=1/Admin: 200; zero-perms: 403
 *  - GET  /api/family/{id}/notes     — same
 *  - GET  /api/note/{id}             — private note: 404 for non-admin/non-author
 *  - POST /api/person/{id}/note      — Notes=1+EditRecords/Admin: 201; zero-perms: 403
 *  - POST /api/family/{id}/note      — Notes=1/Admin: 201; zero-perms: 403
 *  - GET  /api/timeline/person/{id}  — all auth: 200; note items stripped for zero-perms
 *  - GET  /api/timeline/family/{id}  — same
 *
 * Users used in this spec:
 *  - Admin             (makePrivateAdminAPICall)   — usr_Admin=1
 *  - tony.wade         (makePrivateUserAPICall)    — Notes=1 + EditRecords
 *  - john.plainauth    (makePrivatePlainAuthAPICall) — Notes=1, no EditRecords/EditSelf
 *      NOTE: john.plainauth HAS Notes=1. Endpoints that only require the Notes flag
 *      should return 200 for this user. Endpoints that also require EditRecords (e.g.
 *      POST person note with canEditPerson()) should still return 403.
 *  - limited.user      (makePrivateLimitedAPICall) — usr_Notes=0, all other flags=0.
 *      Use this fixture whenever you need to test the "no Notes flag → 403" path.
 */
describe("Notes Visibility Policy (#9036)", () => {
    // Note IDs created in before() and cleaned up in after()
    const fixtures = {};

    before(() => {
        // Admin creates a public person note (person 2 = Mathew Campbell)
        cy.makePrivateAdminAPICall("POST", "/api/person/2/note",
            { text: "<p>Public person note by admin</p>", private: false }, 201)
            .then(r => { fixtures.adminPublicPersonNote = r.body.note.id; });

        // Admin creates a private person note
        cy.makePrivateAdminAPICall("POST", "/api/person/2/note",
            { text: "<p>Private person note by admin - secret</p>", private: true }, 201)
            .then(r => { fixtures.adminPrivatePersonNote = r.body.note.id; });

        // User (tony.wade) creates a private person note (they own it)
        cy.makePrivateUserAPICall("POST", "/api/person/2/note",
            { text: "<p>Private person note by user</p>", private: true }, 201)
            .then(r => { fixtures.userPrivatePersonNote = r.body.note.id; });

        // Admin creates a public family note (family 2 = Hart)
        cy.makePrivateAdminAPICall("POST", "/api/family/2/note",
            { text: "<p>Public family note by admin</p>", private: false }, 201)
            .then(r => { fixtures.adminPublicFamilyNote = r.body.note.id; });

        // Admin creates a private family note
        cy.makePrivateAdminAPICall("POST", "/api/family/2/note",
            { text: "<p>Private family note by admin - confidential</p>", private: true }, 201)
            .then(r => { fixtures.adminPrivateFamilyNote = r.body.note.id; });
    });

    after(() => {
        cy.makePrivateAdminAPICall("DELETE", `/api/note/${fixtures.adminPublicPersonNote}`, null, 200);
        cy.makePrivateAdminAPICall("DELETE", `/api/note/${fixtures.adminPrivatePersonNote}`, null, 200);
        cy.makePrivateAdminAPICall("DELETE", `/api/note/${fixtures.userPrivatePersonNote}`, null, 200);
        cy.makePrivateAdminAPICall("DELETE", `/api/note/${fixtures.adminPublicFamilyNote}`, null, 200);
        cy.makePrivateAdminAPICall("DELETE", `/api/note/${fixtures.adminPrivateFamilyNote}`, null, 200);
    });

    // -----------------------------------------------------------------------
    // Read gating: GET /person/{id}/notes and GET /family/{id}/notes
    // -----------------------------------------------------------------------
    describe("Read gating — GET notes endpoints", () => {
        it("Admin can GET person notes → 200 including private", () => {
            cy.makePrivateAdminAPICall("GET", "/api/person/2/notes", null, 200).then(resp => {
                expect(resp.body).to.have.property("notes");
                expect(resp.body.notes).to.be.an("array");
                const ids = resp.body.notes.map(n => n.id);
                expect(ids).to.include(fixtures.adminPrivatePersonNote);
                expect(ids).to.include(fixtures.adminPublicPersonNote);
            });
        });

        it("Notes=1 user (tony.wade) can GET person notes → 200 but NOT see admin's private note", () => {
            cy.makePrivateUserAPICall("GET", "/api/person/2/notes", null, 200).then(resp => {
                expect(resp.body).to.have.property("notes");
                const ids = resp.body.notes.map(n => n.id);
                // public note is visible
                expect(ids).to.include(fixtures.adminPublicPersonNote);
                // admin's private note is NOT visible
                expect(ids).to.not.include(fixtures.adminPrivatePersonNote);
                // user's own private note IS visible (they are the author)
                expect(ids).to.include(fixtures.userPrivatePersonNote);
            });
        });

        it("john.plainauth (Notes=1, no EditRecords) can GET person notes → 200", () => {
            // john.plainauth has Notes=1, so they pass NotesRoleAuthMiddleware.
            // They cannot POST person notes (no canEditPerson()), but GET is allowed.
            cy.makePrivatePlainAuthAPICall("GET", "/api/person/2/notes", null, 200).then(resp => {
                expect(resp.body).to.have.property("notes");
                expect(resp.body.notes).to.be.an("array");
                // Admin's private note must NOT be visible to a non-admin non-author
                const ids = resp.body.notes.map(n => n.id);
                expect(ids).to.not.include(fixtures.adminPrivatePersonNote);
                expect(ids).to.include(fixtures.adminPublicPersonNote);
            });
        });

        it("limited.user (Notes=0) CANNOT GET person notes → 403", () => {
            cy.makePrivateLimitedAPICall("GET", "/api/person/2/notes", null, 403);
        });

        it("Admin can GET family notes → 200 including private", () => {
            cy.makePrivateAdminAPICall("GET", "/api/family/2/notes", null, 200).then(resp => {
                expect(resp.body).to.have.property("notes");
                const ids = resp.body.notes.map(n => n.id);
                expect(ids).to.include(fixtures.adminPrivateFamilyNote);
                expect(ids).to.include(fixtures.adminPublicFamilyNote);
            });
        });

        it("Notes=1 user (tony.wade) can GET family notes → 200 but NOT see admin's private family note", () => {
            cy.makePrivateUserAPICall("GET", "/api/family/2/notes", null, 200).then(resp => {
                expect(resp.body).to.have.property("notes");
                const ids = resp.body.notes.map(n => n.id);
                expect(ids).to.include(fixtures.adminPublicFamilyNote);
                expect(ids).to.not.include(fixtures.adminPrivateFamilyNote);
            });
        });

        it("john.plainauth (Notes=1) can GET family notes → 200", () => {
            cy.makePrivatePlainAuthAPICall("GET", "/api/family/2/notes", null, 200).then(resp => {
                expect(resp.body).to.have.property("notes");
                const ids = resp.body.notes.map(n => n.id);
                expect(ids).to.not.include(fixtures.adminPrivateFamilyNote);
                expect(ids).to.include(fixtures.adminPublicFamilyNote);
            });
        });

        it("limited.user (Notes=0) CANNOT GET family notes → 403", () => {
            cy.makePrivateLimitedAPICall("GET", "/api/family/2/notes", null, 403);
        });
    });

    // -----------------------------------------------------------------------
    // Single note: GET /note/{id} private-note visibility
    // -----------------------------------------------------------------------
    describe("Single note visibility — GET /note/{id}", () => {
        it("Admin can GET another user's private note → 200 with full content", () => {
            cy.makePrivateAdminAPICall("GET", `/api/note/${fixtures.userPrivatePersonNote}`, null, 200)
                .then(resp => {
                    expect(resp.body).to.have.property("note");
                    expect(resp.body.note.text).to.include("Private person note by user");
                });
        });

        it("Notes=1 user gets 404 for admin's private note (existence not leaked)", () => {
            cy.makePrivateUserAPICall("GET", `/api/note/${fixtures.adminPrivatePersonNote}`, null, 404);
        });

        it("Notes=1 user CAN GET their own private note → 200", () => {
            cy.makePrivateUserAPICall("GET", `/api/note/${fixtures.userPrivatePersonNote}`, null, 200)
                .then(resp => {
                    expect(resp.body.note.private).to.equal(true);
                });
        });

        it("limited.user (Notes=0) CANNOT GET any note → 403", () => {
            cy.makePrivateLimitedAPICall("GET", `/api/note/${fixtures.adminPublicPersonNote}`, null, 403);
        });
    });

    // -----------------------------------------------------------------------
    // Write gating: POST /person/{id}/note
    // -----------------------------------------------------------------------
    describe("Write gating — POST /person/{id}/note", () => {
        it("Admin can POST person note → 201", () => {
            cy.makePrivateAdminAPICall("POST", "/api/person/2/note",
                { text: "<p>Admin write test</p>", private: false }, 201)
                .then(resp => {
                    const noteId = resp.body.note.id;
                    cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
                });
        });

        it("Notes=1+EditRecords user (tony.wade) can POST person note → 201", () => {
            cy.makePrivateUserAPICall("POST", "/api/person/2/note",
                { text: "<p>User write test</p>", private: false }, 201)
                .then(resp => {
                    const noteId = resp.body.note.id;
                    cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
                });
        });

        it("john.plainauth (Notes=1, no EditRecords) CANNOT POST person note → 403", () => {
            // john.plainauth has Notes=1 but no EditRecords/EditSelf.
            // POST /person/{id}/note requires Notes=1 (middleware) AND canEditPerson()
            // (inline check). canEditPerson() returns false (no EditRecords/EditSelf)
            // so the request is blocked with 403 at the object-level scope check.
            cy.makePrivatePlainAuthAPICall("POST", "/api/person/2/note",
                { text: "<p>Should fail</p>", private: false }, 403);
        });

        it("limited.user (Notes=0) CANNOT POST person note → 403", () => {
            cy.makePrivateLimitedAPICall("POST", "/api/person/2/note",
                { text: "<p>Should fail</p>", private: false }, 403);
        });
    });

    // -----------------------------------------------------------------------
    // Write gating: POST /family/{id}/note
    // -----------------------------------------------------------------------
    describe("Write gating — POST /family/{id}/note", () => {
        it("Admin can POST family note → 201", () => {
            cy.makePrivateAdminAPICall("POST", "/api/family/2/note",
                { text: "<p>Admin family write test</p>", private: false }, 201)
                .then(resp => {
                    const noteId = resp.body.note.id;
                    cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
                });
        });

        it("Notes=1 user (tony.wade) can POST family note → 201", () => {
            cy.makePrivateUserAPICall("POST", "/api/family/2/note",
                { text: "<p>User family write test</p>", private: false }, 201)
                .then(resp => {
                    const noteId = resp.body.note.id;
                    cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
                });
        });

        it("john.plainauth (Notes=1) can POST family note → 201 (Notes flag is sufficient for family notes)", () => {
            // Family note POST only requires canWriteNoteOnFamily() = Notes=1 or Admin.
            // john.plainauth has Notes=1 so this should succeed.
            cy.makePrivatePlainAuthAPICall("POST", "/api/family/2/note",
                { text: "<p>john.plainauth family note</p>", private: false }, 201)
                .then(resp => {
                    const noteId = resp.body.note.id;
                    cy.makePrivateAdminAPICall("DELETE", `/api/note/${noteId}`, null, 200);
                });
        });

        it("limited.user (Notes=0) CANNOT POST family note → 403", () => {
            cy.makePrivateLimitedAPICall("POST", "/api/family/2/note",
                { text: "<p>Should fail</p>", private: false }, 403);
        });
    });

    // -----------------------------------------------------------------------
    // Timeline: note filtering for different user types
    // -----------------------------------------------------------------------
    describe("Timeline note filtering — GET /timeline/person/{id}", () => {
        it("judith.matthews (EditRecords, Notes=0) gets person timeline → 200, note items absent", () => {
            // judith.matthews passes AuthMiddleware (has EditRecords) but canReadNotes()=false.
            // Timeline returns 200; type='note' items must be stripped by TimelineService.
            cy.makePrivateEditRecordsAPICall("GET", "/api/timeline/person/2", null, 200).then(resp => {
                expect(resp.body).to.have.property("timeline");
                const timeline = resp.body.timeline;
                // No user-entered note items (type='note') for no-Notes users
                const noteItems = timeline.filter(item => item.type === "note");
                expect(noteItems).to.have.length(0);
                // Endpoint returns 200 with an array
                expect(timeline).to.be.an("array");
            });
        });

        it("limited.user (Notes=0, all flags=0) CANNOT GET person timeline → 403", () => {
            // limited.user is blocked by AuthMiddleware::hasNoAdminPermissions() → 403.
            // This is different from the 'authenticated but no notes' case above.
            cy.makePrivateLimitedAPICall("GET", "/api/timeline/person/2", null, 403);
        });

        it("Notes=1 user gets person timeline → 200 with public note, WITHOUT admin's private note", () => {
            cy.makePrivateUserAPICall("GET", "/api/timeline/person/2", null, 200).then(resp => {
                const timeline = resp.body.timeline;
                const itemIds = timeline.map(item => item.id);
                // public note is visible
                expect(itemIds).to.include(fixtures.adminPublicPersonNote.toString());
                // admin's private note is absent
                expect(itemIds).to.not.include(fixtures.adminPrivatePersonNote.toString());
                // user's own private note IS present (they are the author)
                expect(itemIds).to.include(fixtures.userPrivatePersonNote.toString());
            });
        });

        it("Admin gets person timeline → 200 with ALL note items including private", () => {
            cy.makePrivateAdminAPICall("GET", "/api/timeline/person/2", null, 200).then(resp => {
                const timeline = resp.body.timeline;
                const itemIds = timeline.map(item => item.id);
                expect(itemIds).to.include(fixtures.adminPublicPersonNote.toString());
                expect(itemIds).to.include(fixtures.adminPrivatePersonNote.toString());
                expect(itemIds).to.include(fixtures.userPrivatePersonNote.toString());
            });
        });

        it("Admin sees full content of another user's private note (no placeholder)", () => {
            cy.makePrivateAdminAPICall("GET", "/api/timeline/person/2", null, 200).then(resp => {
                const timeline = resp.body.timeline;
                const userNote = timeline.find(item => item.id === fixtures.userPrivatePersonNote.toString());
                expect(userNote).to.exist;
                // Full content, not placeholder
                expect(userNote.text).to.include("Private person note by user");
                expect(userNote.text).to.not.include("[Private Note");
                // Admin has edit link
                expect(userNote.editLink).to.be.a("string").and.not.equal("");
            });
        });

        it("Notes=1 user sees full content of their own private note in timeline", () => {
            cy.makePrivateUserAPICall("GET", "/api/timeline/person/2", null, 200).then(resp => {
                const timeline = resp.body.timeline;
                const ownNote = timeline.find(item => item.id === fixtures.userPrivatePersonNote.toString());
                expect(ownNote).to.exist;
                expect(ownNote.text).to.include("Private person note by user");
                expect(ownNote.editLink).to.be.a("string").and.not.equal("");
            });
        });
    });

    describe("Timeline note filtering — GET /timeline/family/{id}", () => {
        it("judith.matthews (EditRecords, Notes=0) gets family timeline → 200, note items absent", () => {
            cy.makePrivateEditRecordsAPICall("GET", "/api/timeline/family/2", null, 200).then(resp => {
                expect(resp.body).to.have.property("timeline");
                const noteItems = resp.body.timeline.filter(item => item.type === "note");
                expect(noteItems).to.have.length(0);
            });
        });

        it("limited.user (all flags=0) CANNOT GET family timeline → 403", () => {
            cy.makePrivateLimitedAPICall("GET", "/api/timeline/family/2", null, 403);
        });

        it("Notes=1 user gets family timeline → 200 with public note, admin's private absent", () => {
            cy.makePrivateUserAPICall("GET", "/api/timeline/family/2", null, 200).then(resp => {
                const timeline = resp.body.timeline;
                const itemIds = timeline.map(item => item.id);
                expect(itemIds).to.include(fixtures.adminPublicFamilyNote.toString());
                expect(itemIds).to.not.include(fixtures.adminPrivateFamilyNote.toString());
            });
        });

        it("Admin gets family timeline → 200 with ALL notes including private", () => {
            cy.makePrivateAdminAPICall("GET", "/api/timeline/family/2", null, 200).then(resp => {
                const timeline = resp.body.timeline;
                const itemIds = timeline.map(item => item.id);
                expect(itemIds).to.include(fixtures.adminPublicFamilyNote.toString());
                expect(itemIds).to.include(fixtures.adminPrivateFamilyNote.toString());
            });
        });
    });
});
