/// <reference types="cypress" />

/**
 * Regression tests for GHSA-jjcj-h3cm-p7x7
 *
 * Improper object-level authorization — EditSelf user can read/modify any
 * family's data via family API endpoints.
 *
 * Test user: tony.wade (user ID 3, `user.api.key`)
 *   - Permissions: EditSelf=1, Notes=1
 *   - Belongs to: family ID 1 (Campbell family)
 *
 * Expected: tony.wade CAN access family 1 (own family), but MUST receive 403
 * for any request targeting a different family (family 2 = Hart family).
 */
describe("GHSA-jjcj-h3cm-p7x7 - EditSelf user family scope restriction", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    // -----------------------------------------------------------------------
    // GET /api/family/{familyId}
    // -----------------------------------------------------------------------
    describe("GET /api/family/{familyId} - Family profile", () => {
        it("EditSelf user can access own family profile (family 1 → 200)", () => {
            cy.makePrivateUserAPICall("GET", "/api/family/1", null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("Id", 1);
                },
            );
        });

        it("EditSelf user is BLOCKED from another family profile (family 2 → 403)", () => {
            cy.makePrivateUserAPICall("GET", "/api/family/2", null, 403);
        });
    });

    // -----------------------------------------------------------------------
    // GET /api/family/{familyId}/notes
    // -----------------------------------------------------------------------
    describe("GET /api/family/{familyId}/notes - Family notes", () => {
        it("EditSelf user can access own family notes (family 1 → 200)", () => {
            cy.makePrivateUserAPICall("GET", "/api/family/1/notes", null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("notes");
                    expect(response.body.notes).to.be.an("array");
                },
            );
        });

        it("EditSelf user is BLOCKED from another family notes (family 2 → 403)", () => {
            cy.makePrivateUserAPICall("GET", "/api/family/2/notes", null, 403);
        });
    });

    // -----------------------------------------------------------------------
    // POST /api/family/{familyId}/note
    // -----------------------------------------------------------------------
    describe("POST /api/family/{familyId}/note - Create family note", () => {
        it("EditSelf user is BLOCKED from creating a note on another family (family 2 → 403)", () => {
            cy.makePrivateUserAPICall(
                "POST",
                "/api/family/2/note",
                { text: "<p>Unauthorized note attempt</p>", private: false },
                403,
            );
        });

        it("EditSelf user can create a note on own family (family 1 → 201)", () => {
            cy.makePrivateUserAPICall(
                "POST",
                "/api/family/1/note",
                { text: "<p>GHSA test note on own family</p>", private: false },
                201,
            ).then((response) => {
                expect(response.body).to.have.property("note");
                const note = response.body.note;
                expect(note).to.have.property("famId", 1);

                // Clean up
                cy.makePrivateAdminAPICall("DELETE", `/api/note/${note.id}`, null, 200);
            });
        });
    });

    // -----------------------------------------------------------------------
    // GET /api/timeline/family/{familyId}
    // -----------------------------------------------------------------------
    describe("GET /api/timeline/family/{familyId} - Family timeline", () => {
        it("EditSelf user can access own family timeline (family 1 → 200)", () => {
            cy.makePrivateUserAPICall("GET", "/api/timeline/family/1", null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("timeline");
                    expect(response.body.timeline).to.be.an("array");
                },
            );
        });

        it("EditSelf user is BLOCKED from another family timeline (family 2 → 403)", () => {
            cy.makePrivateUserAPICall("GET", "/api/timeline/family/2", null, 403);
        });
    });

    // -----------------------------------------------------------------------
    // GET /api/family/{familyId}/photo
    // -----------------------------------------------------------------------
    describe("GET /api/family/{familyId}/photo - Family photo", () => {
        it("EditSelf user is BLOCKED from another family photo (family 2 → 403)", () => {
            cy.makePrivateUserAPICall("GET", "/api/family/2/photo", null, 403);
        });

        it("EditSelf user requesting own family photo gets 404 (no photo uploaded) not 200 with image data", () => {
            // Family 1 has no uploaded photo in test data — expect 404, not 200.
            // The key assertion: auth passes (no 403) and we reach the photo-existence check.
            cy.makePrivateUserAPICall("GET", "/api/family/1/photo", null, 404);
        });
    });

    // -----------------------------------------------------------------------
    // GET /api/family/{familyId}/avatar
    // -----------------------------------------------------------------------
    describe("GET /api/family/{familyId}/avatar - Family avatar", () => {
        it("EditSelf user can access own family avatar (family 1 → 200)", () => {
            cy.makePrivateUserAPICall("GET", "/api/family/1/avatar", null, 200).then(
                (response) => {
                    expect(response.body).to.exist;
                },
            );
        });

        it("EditSelf user is BLOCKED from another family avatar (family 2 → 403)", () => {
            cy.makePrivateUserAPICall("GET", "/api/family/2/avatar", null, 403);
        });
    });
});
