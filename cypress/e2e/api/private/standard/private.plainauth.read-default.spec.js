/// <reference types="cypress" />

/**
 * Tests for the explicit read-default security policy (issues #8999, #9000, #9001).
 *
 * Verifies that ALL authenticated users — even those with no edit/admin role flags —
 * can read basic people and family metadata. This formalises the implicit behaviour
 * that was present before PR #8964 introduced object-level authorization.
 *
 * Test user: john.plainauth (user ID 900, `plainauth.api.key`)
 *   - Permissions: Notes=1 only (Admin=0, EditRecords=0, EditSelf=0, AddRecords=0)
 *   - Notes=1 is required to pass the hasNoAdminPermissions() check in AuthMiddleware.
 *   - No EditSelf flag, so canViewFamily() must grant access via canReadFamily() baseline.
 *
 * Expected: john.plainauth CAN read ANY family and person — not restricted to a subset.
 *
 * The EditSelf-only restriction (family 20 scoping) is separately covered in
 * private.selfedit.family-scope.spec.js and must be unaffected by this change.
 */
describe("Read-default policy — plain authenticated user can read any family/person", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    // -----------------------------------------------------------------------
    // GET /api/family/{familyId} — Family profile
    // -----------------------------------------------------------------------
    describe("GET /api/family/{familyId} - Family profile", () => {
        it("plain-auth user can read family 1 (Campbell family → 200)", () => {
            cy.makePrivatePlainAuthAPICall("GET", "/api/family/1", null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("Id", 1);
                },
            );
        });

        it("plain-auth user can read family 20 (Black family → 200)", () => {
            cy.makePrivatePlainAuthAPICall("GET", "/api/family/20", null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("Id", 20);
                },
            );
        });
    });

    // -----------------------------------------------------------------------
    // GET /api/family/{familyId}/avatar
    // -----------------------------------------------------------------------
    describe("GET /api/family/{familyId}/avatar - Family avatar", () => {
        it("plain-auth user can read family 1 avatar (Campbell family → 200)", () => {
            cy.makePrivatePlainAuthAPICall("GET", "/api/family/1/avatar", null, 200).then(
                (response) => {
                    expect(response.body).to.exist;
                },
            );
        });

        it("plain-auth user can read family 20 avatar (Black family → 200)", () => {
            cy.makePrivatePlainAuthAPICall("GET", "/api/family/20/avatar", null, 200).then(
                (response) => {
                    expect(response.body).to.exist;
                },
            );
        });
    });

    // -----------------------------------------------------------------------
    // GET /api/family/{familyId}/notes — requires Notes permission
    // -----------------------------------------------------------------------
    describe("GET /api/family/{familyId}/notes - Family notes", () => {
        it("plain-auth user (Notes=1) can read family 1 notes → 200", () => {
            cy.makePrivatePlainAuthAPICall("GET", "/api/family/1/notes", null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("notes");
                    expect(response.body.notes).to.be.an("array");
                },
            );
        });

        it("plain-auth user (Notes=1) can read family 20 notes → 200", () => {
            cy.makePrivatePlainAuthAPICall("GET", "/api/family/20/notes", null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("notes");
                    expect(response.body.notes).to.be.an("array");
                },
            );
        });
    });

    // -----------------------------------------------------------------------
    // GET /api/timeline/family/{familyId}
    // -----------------------------------------------------------------------
    describe("GET /api/timeline/family/{familyId} - Family timeline", () => {
        it("plain-auth user can read family 1 timeline → 200", () => {
            cy.makePrivatePlainAuthAPICall("GET", "/api/timeline/family/1", null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("timeline");
                    expect(response.body.timeline).to.be.an("array");
                },
            );
        });

        it("plain-auth user can read family 20 timeline → 200", () => {
            cy.makePrivatePlainAuthAPICall("GET", "/api/timeline/family/20", null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("timeline");
                    expect(response.body.timeline).to.be.an("array");
                },
            );
        });
    });

    // -----------------------------------------------------------------------
    // Sanity check: EditSelf restriction still works as before
    // A plain-auth user (no EditSelf) is NOT restricted to a single family —
    // that's the whole point of the new baseline.
    // -----------------------------------------------------------------------
    describe("Sanity: EditSelf restriction is unaffected by read-default policy", () => {
        it("EditSelf user (amanda.black) is still BLOCKED from family 1 → 403", () => {
            cy.makePrivateEditSelfAPICall("GET", "/api/family/1", null, 403);
        });

        it("EditSelf user (amanda.black) can still read own family 20 → 200", () => {
            cy.makePrivateEditSelfAPICall("GET", "/api/family/20", null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("Id", 20);
                },
            );
        });
    });
});
