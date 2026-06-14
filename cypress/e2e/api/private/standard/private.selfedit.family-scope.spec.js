/// <reference types="cypress" />

/**
 * Regression tests for GHSA-jjcj-h3cm-p7x7
 *
 * Improper object-level authorization — EditSelf user can read/modify any
 * family's data via family API endpoints.
 *
 * Test user: amanda.black (user ID 99, `selfedit.api.key`)
 *   - Permissions: EditSelf=1, Notes=1 only (AddRecords=0, EditRecords=0, Admin=0)
 *   - Belongs to: family ID 20 (Black family)
 *
 * Expected: amanda.black CAN access family 20 (own family), but MUST receive
 * 403 for any request targeting a different family (family 1 = Campbell family).
 *
 * Notes=1 is required so that amanda.black passes the hasNoAdminPermissions()
 * check in AuthMiddleware (GHSA-5w59-32c8-933v) — EditSelf alone is not
 * sufficient to use the API.
 */
describe("GHSA-jjcj-h3cm-p7x7 - EditSelf user family scope restriction", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    // -----------------------------------------------------------------------
    // GET /api/family/{familyId}
    // -----------------------------------------------------------------------
    describe("GET /api/family/{familyId} - Family profile", () => {
        it("EditSelf user can access own family profile (family 20 → 200)", () => {
            cy.makePrivateEditSelfAPICall("GET", "/api/family/20", null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("Id", 20);
                },
            );
        });

        it("EditSelf user is BLOCKED from another family profile (family 1 → 403)", () => {
            cy.makePrivateEditSelfAPICall("GET", "/api/family/1", null, 403);
        });
    });

    // -----------------------------------------------------------------------
    // GET /api/family/{familyId}/notes
    // -----------------------------------------------------------------------
    describe("GET /api/family/{familyId}/notes - Family notes", () => {
        it("EditSelf user can access own family notes (family 20 → 200)", () => {
            cy.makePrivateEditSelfAPICall("GET", "/api/family/20/notes", null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("notes");
                    expect(response.body.notes).to.be.an("array");
                },
            );
        });

        it("EditSelf user is BLOCKED from another family notes (family 1 → 403)", () => {
            cy.makePrivateEditSelfAPICall("GET", "/api/family/1/notes", null, 403);
        });
    });

    // -----------------------------------------------------------------------
    // POST /api/family/{familyId}/note
    // -----------------------------------------------------------------------
    describe("POST /api/family/{familyId}/note - Create family note", () => {
        it("EditSelf user is BLOCKED from creating a note on another family (family 1 → 403)", () => {
            cy.makePrivateEditSelfAPICall(
                "POST",
                "/api/family/1/note",
                { text: "<p>Unauthorized note attempt</p>", private: false },
                403,
            );
        });

        it("EditSelf user can create a note on own family (family 20 → 201)", () => {
            cy.makePrivateEditSelfAPICall(
                "POST",
                "/api/family/20/note",
                { text: "<p>GHSA test note on own family</p>", private: false },
                201,
            ).then((response) => {
                expect(response.body).to.have.property("note");
                const note = response.body.note;
                expect(note).to.have.property("famId", 20);

                // Clean up
                cy.makePrivateAdminAPICall("DELETE", `/api/note/${note.id}`, null, 200);
            });
        });
    });

    // -----------------------------------------------------------------------
    // Person notes — GET /api/person/{personId}/notes, POST /api/person/{personId}/note
    // Person routes use PersonMiddleware (no family scope) + NotesRoleAuthMiddleware,
    // so the object-level canEditPerson() check lives in the handlers. amanda.black
    // (person 99) may touch her OWN record but not another family's person (person 2,
    // Campbell family 1).
    // -----------------------------------------------------------------------
    describe("Person notes - object-level scope", () => {
        it("EditSelf user can read notes on own person record (person 99 → 200)", () => {
            cy.makePrivateEditSelfAPICall("GET", "/api/person/99/notes", null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("notes");
                    expect(response.body.notes).to.be.an("array");
                },
            );
        });

        it("EditSelf user is BLOCKED from reading notes on another family's person (person 2 → 403)", () => {
            cy.makePrivateEditSelfAPICall("GET", "/api/person/2/notes", null, 403);
        });

        it("EditSelf user is BLOCKED from creating a note on another family's person (person 2 → 403)", () => {
            cy.makePrivateEditSelfAPICall(
                "POST",
                "/api/person/2/note",
                { text: "<p>Unauthorized person note attempt</p>", private: false },
                403,
            );
        });

        it("EditSelf user can create a note on own person record (person 99 → 201)", () => {
            cy.makePrivateEditSelfAPICall(
                "POST",
                "/api/person/99/note",
                { text: "<p>GHSA test note on own record</p>", private: false },
                201,
            ).then((response) => {
                expect(response.body).to.have.property("note");
                const note = response.body.note;
                expect(note).to.have.property("perId", 99);

                // Clean up
                cy.makePrivateAdminAPICall("DELETE", `/api/note/${note.id}`, null, 200);
            });
        });
    });

    // -----------------------------------------------------------------------
    // GET /api/timeline/family/{familyId}
    // -----------------------------------------------------------------------
    describe("GET /api/timeline/family/{familyId} - Family timeline", () => {
        it("EditSelf user can access own family timeline (family 20 → 200)", () => {
            cy.makePrivateEditSelfAPICall("GET", "/api/timeline/family/20", null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("timeline");
                    expect(response.body.timeline).to.be.an("array");
                },
            );
        });

        it("EditSelf user is BLOCKED from another family timeline (family 1 → 403)", () => {
            cy.makePrivateEditSelfAPICall("GET", "/api/timeline/family/1", null, 403);
        });
    });

    // -----------------------------------------------------------------------
    // GET /api/family/{familyId}/photo
    // -----------------------------------------------------------------------
    describe("GET /api/family/{familyId}/photo - Family photo", () => {
        it("EditSelf user is BLOCKED from another family photo (family 1 → 403)", () => {
            cy.makePrivateEditSelfAPICall("GET", "/api/family/1/photo", null, 403);
        });

        it("EditSelf user requesting own family photo gets 404 (no photo uploaded) not 200 with image data", () => {
            // Family 20 has no uploaded photo in test data — expect 404, not 200.
            // The key assertion: auth passes (no 403) and we reach the photo-existence check.
            cy.makePrivateEditSelfAPICall("GET", "/api/family/20/photo", null, 404);
        });
    });

    // -----------------------------------------------------------------------
    // GET /api/family/{familyId}/avatar
    // -----------------------------------------------------------------------
    describe("GET /api/family/{familyId}/avatar - Family avatar", () => {
        it("EditSelf user can access own family avatar (family 20 → 200)", () => {
            cy.makePrivateEditSelfAPICall("GET", "/api/family/20/avatar", null, 200).then(
                (response) => {
                    expect(response.body).to.exist;
                },
            );
        });

        it("EditSelf user is BLOCKED from another family avatar (family 1 → 403)", () => {
            cy.makePrivateEditSelfAPICall("GET", "/api/family/1/avatar", null, 403);
        });
    });
});
