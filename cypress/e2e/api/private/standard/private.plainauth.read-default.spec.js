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
 *   - No EditSelf flag, so canViewFamily() / canReadPerson() must grant access via the
 *     read-default baseline (canReadFamily() / canReadPerson() both return true).
 *
 * Side-effect (intentional): Notes=1 users can also POST notes to any family/person
 * because canReadFamily() (and canViewFamily()) now returns true for all authenticated
 * users, and NotesRoleAuthMiddleware checks only the Notes flag. This is correct
 * behaviour — Notes permission has always implied "can write notes"; the read-default
 * policy simply removes the accidental read-block for non-EditSelf users.
 *
 * The EditSelf-only restriction (family 20 scoping) is separately covered in
 * private.selfedit.family-scope.spec.js and must be unaffected by this change.
 */
describe("Read-default policy — plain authenticated user can read any family/person", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    // -----------------------------------------------------------------------
    // GET /api/person/{personId} — Person profile
    // Person 2 is in family 1 (Campbell). Person 99 is in family 20 (Black).
    // -----------------------------------------------------------------------
    describe("GET /api/person/{personId} - Person profile", () => {
        it("plain-auth user can read person 2 (family 1 Campbell → 200)", () => {
            cy.makePrivatePlainAuthAPICall("GET", "/api/person/2", null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("Id", 2);
                },
            );
        });

        it("plain-auth user can read person 99 (family 20 Black → 200)", () => {
            cy.makePrivatePlainAuthAPICall("GET", "/api/person/99", null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("Id", 99);
                },
            );
        });
    });

    // -----------------------------------------------------------------------
    // GET /api/person/{personId}/notes — Person notes (Notes role required)
    // -----------------------------------------------------------------------
    describe("GET /api/person/{personId}/notes - Person notes", () => {
        it("plain-auth user (Notes=1) can read notes for person 2 (family 1 → 200)", () => {
            cy.makePrivatePlainAuthAPICall("GET", "/api/person/2/notes", null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("notes");
                    expect(response.body.notes).to.be.an("array");
                },
            );
        });

        it("plain-auth user (Notes=1) can read notes for person 99 (family 20 → 200)", () => {
            cy.makePrivatePlainAuthAPICall("GET", "/api/person/99/notes", null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("notes");
                    expect(response.body.notes).to.be.an("array");
                },
            );
        });
    });

    // -----------------------------------------------------------------------
    // POST /api/person/{personId}/note — write still requires edit permission
    // Plain-auth user has Notes=1 but no EditSelf/EditRecords, so canEditPerson()
    // returns false → POST /person/{id}/note must be blocked (403).
    // -----------------------------------------------------------------------
    describe("POST /api/person/{personId}/note - write requires edit permission", () => {
        it("plain-auth user (no EditSelf/EditRecords) CANNOT create note on person 2 → 403", () => {
            cy.makePrivatePlainAuthAPICall(
                "POST",
                "/api/person/2/note",
                { text: "<p>test note</p>" },
                403,
            );
        });
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
        it("plain-auth user can read family 1 avatar → 200", () => {
            cy.makePrivatePlainAuthAPICall("GET", "/api/family/1/avatar", null, 200).then(
                (response) => {
                    expect(response.body).to.exist;
                },
            );
        });

        it("plain-auth user can read family 20 avatar → 200", () => {
            cy.makePrivatePlainAuthAPICall("GET", "/api/family/20/avatar", null, 200).then(
                (response) => {
                    expect(response.body).to.exist;
                },
            );
        });
    });

    // -----------------------------------------------------------------------
    // GET /api/family/{familyId}/nav — Family navigation
    // -----------------------------------------------------------------------
    describe("GET /api/family/{familyId}/nav - Family navigation", () => {
        it("plain-auth user can read family 1 nav → 200", () => {
            cy.makePrivatePlainAuthAPICall("GET", "/api/family/1/nav", null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("PreFamilyId");
                    expect(response.body).to.have.property("NextFamilyId");
                },
            );
        });

        it("plain-auth user can read family 20 nav → 200", () => {
            cy.makePrivatePlainAuthAPICall("GET", "/api/family/20/nav", null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("PreFamilyId");
                    expect(response.body).to.have.property("NextFamilyId");
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
    // POST /api/family/{familyId}/note — intentional side-effect
    // Notes=1 users can write notes to any family because canViewFamily() now
    // returns true for all authenticated users (read-default policy). This is
    // correct behaviour: the Notes flag has always meant "can write notes";
    // the read-default policy only removes the accidental block for users without
    // EditSelf/EditRecords. FamilyMiddleware passes, NotesRoleAuthMiddleware
    // passes (Notes=1), so POST succeeds.
    // -----------------------------------------------------------------------
    describe("POST /api/family/{familyId}/note - Notes=1 user can write notes (intentional side-effect)", () => {
        it("plain-auth user (Notes=1) can POST note to family 1 → 201", () => {
            cy.makePrivatePlainAuthAPICall(
                "POST",
                "/api/family/1/note",
                { text: "<p>read-default policy note</p>" },
                201,
            ).then((response) => {
                expect(response.body).to.have.property("note");
                expect(response.body.note).to.have.property("famId", 1);
            });
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

});
