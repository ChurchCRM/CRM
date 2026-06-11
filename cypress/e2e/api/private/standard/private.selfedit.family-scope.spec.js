/// <reference types="cypress" />

/**
 * Regression tests for GHSA-jjcj-h3cm-p7x7
 *
 * Improper object-level authorization — EditSelf user can read/modify any
 * family's data via family API endpoints.
 *
 * Test user: amanda.black (user ID 99, `selfedit.api.key`)
 *   - Permissions: EditSelf=1 ONLY (AddRecords=0, EditRecords=0, Notes=0, Admin=0)
 *   - Belongs to: family ID 20 (Black family)
 *
 * Expected: amanda.black CAN access family 20 (own family), but MUST receive
 * 403 for any request targeting a different family (family 1 = Campbell family).
 *
 * Note: amanda.black has Notes=0, so all /notes and /note endpoints return 403
 * via NotesRoleAuthMiddleware regardless of family. The 403 cross-family tests
 * still validate that the endpoint is blocked; the own-family notes tests are
 * omitted since they'd test NotesRoleAuth rather than family-scope authz.
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
        // amanda.black has Notes=0; NotesRoleAuthMiddleware fires before FamilyMiddleware,
        // so both own-family and cross-family return 403. The cross-family case validates
        // the endpoint is blocked for EditSelf users on a different family.
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
