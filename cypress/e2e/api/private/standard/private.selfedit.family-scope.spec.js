/// <reference types="cypress" />

/**
 * Regression tests for GHSA-jjcj-h3cm-p7x7
 *
 * EditSelf is now an exclusive permission mode: a non-admin user with EditSelf=1
 * has ALL other module permissions suppressed at the model layer
 * (User::isEditSelfExclusive()). AuthMiddleware therefore blocks every internal API request with 403 —
 * including the user's OWN family and OWN person record.
 *
 * Test user: amanda.black (user ID 99, `selfedit.api.key`)
 *   - Permissions: EditSelf=1 ONLY (all others = 0)
 *   - DB invariant enforced by model (User::isEditSelfExclusive()) AND by
 *     the 7.4.2 data migration (src/mysql/upgrade/7.4.2-editself-exclusive.sql)
 *   - Belongs to: family ID 20 (Black family)
 *
 * Expected: 403 on EVERY internal endpoint. The only self-service surface for an
 * EditSelf user is the token-scoped external verify flow (/external/verify/{token}),
 * which is covered in cypress/e2e/ui/security/limited-access.spec.js.
 *
 * Note: avatar, nav, and photo GET endpoints use FamilyReadMiddleware (canReadFamily())
 * instead of FamilyMiddleware (canViewFamily()), making them accessible to plain-auth
 * users. They still return 403 here because AuthMiddleware (User::isEditSelfExclusive())
 * blocks EditSelf-exclusive users before FamilyReadMiddleware is ever reached.
 * See private.plainauth.read-default.spec.js for 200-response coverage of those endpoints.
 */
describe("GHSA-jjcj-h3cm-p7x7 - EditSelf is exclusive: all internal APIs return 403", () => {
    const lockedOutGets = [
        "/api/family/20",       // OWN family — still 403
        "/api/family/1",        // another family
        "/api/family/20/notes",
        "/api/family/1/notes",
        "/api/family/20/photo",
        "/api/family/1/photo",
        "/api/family/20/avatar",
        "/api/family/1/avatar",
        "/api/family/20/nav",   // low-sensitivity via FamilyReadMiddleware — still 403 (AuthMiddleware blocks first)
        "/api/family/1/nav",
        "/api/timeline/family/20",
        "/api/timeline/family/1",
        "/api/person/99",       // OWN person record — still 403
        "/api/person/2",        // another family's person
        "/api/person/99/notes",
        "/api/person/2/notes",
    ];

    lockedOutGets.forEach((url) => {
        it(`GET ${url} → 403 (EditSelf has no internal API access)`, () => {
            cy.makePrivateEditSelfAPICall("GET", url, null, 403);
        });
    });

    it("POST /api/family/20/note (own family) → 403", () => {
        cy.makePrivateEditSelfAPICall(
            "POST",
            "/api/family/20/note",
            { text: "<p>blocked</p>", private: false },
            403,
        );
    });

    it("POST /api/person/99/note (own record) → 403", () => {
        cy.makePrivateEditSelfAPICall(
            "POST",
            "/api/person/99/note",
            { text: "<p>blocked</p>", private: false },
            403,
        );
    });
});

/**
 * Regression sentinel for FamilyReadMiddleware vs FamilyMiddleware swap.
 *
 * Test user: lena.black (user ID 100, `selfedit.plus.notes.api.key`)
 *   - DB flags: usr_EditSelf=1, usr_Notes=1 (both set)
 *   - Belongs to: family 20 (Black family, person 100 = Lena Black)
 *   - Intended regression: if FamilyReadMiddleware::class is accidentally replaced
 *     by FamilyMiddleware::class for avatar/nav/photo, a user who passes AuthMiddleware
 *     but has canViewFamily(nonOwnFamily)=false would start receiving 403 instead of 200.
 *
 * CURRENT STATE — PR #9016 EditSelf exclusive constraint:
 *   User::isEditSelfExclusive() returns true for ANY non-admin user with isEditSelf()=true, regardless
 *   of Notes=1. AuthMiddleware therefore blocks this user (403) before FamilyReadMiddleware
 *   or FamilyMiddleware is ever reached. All assertions below reflect this current state.
 *
 * FUTURE STATE — if EditSelf exclusivity is relaxed to permit EditSelf+Notes:
 *   Update the assertions in the 'low-sensitivity' block to expect 200, not 403:
 *     - GET avatar, nav, photo → 200  (FamilyReadMiddleware, canReadFamily=true)
 *     - GET profile, notes    → 403  (FamilyMiddleware,     canViewFamily=false for non-own family)
 *   A swap of FamilyReadMiddleware::class → FamilyMiddleware::class would then cause the
 *   avatar/nav/photo 200 assertions to fail — that IS the regression to detect.
 *
 *   The plainauth spec (private.plainauth.read-default.spec.js) provides a weaker form
 *   of coverage now: plainauth users get 200 from EITHER middleware (canViewFamily=true
 *   for Notes-only users), so swapping middlewares does not change their result.
 */
describe("FamilyReadMiddleware regression sentinel — EditSelf+Notes user (PR#9016 note)", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    // -----------------------------------------------------------------------
    // LOW-SENSITIVITY (FamilyReadMiddleware group): avatar, nav, photo GET
    // Current (EditSelf exclusive): 403 from AuthMiddleware.
    // Future (if exclusivity relaxed): assert 200 — these are the regression sentinels.
    //   If they return 403, FamilyMiddleware was accidentally used instead.
    // -----------------------------------------------------------------------
    describe("Low-sensitivity endpoints — current: 403 (AuthMiddleware), future: 200 (FamilyReadMiddleware)", () => {
        it("family 1 avatar → 403 now; TODO(PR#9016): assert 200 when EditSelf+Notes can pass AuthMiddleware", () => {
            cy.makePrivateEditSelfPlusNotesAPICall("GET", "/api/family/1/avatar", null, 403);
        });

        it("family 1 nav → 403 now; TODO(PR#9016): assert 200 when EditSelf+Notes can pass AuthMiddleware", () => {
            cy.makePrivateEditSelfPlusNotesAPICall("GET", "/api/family/1/nav", null, 403);
        });

        it("family 1 photo → 403 now; TODO(PR#9016): assert 404 (no photo) when EditSelf+Notes can pass AuthMiddleware", () => {
            cy.makePrivateEditSelfPlusNotesAPICall("GET", "/api/family/1/photo", null, 403);
        });

        it("own family 20 avatar → 403 now; TODO(PR#9016): assert 200 when EditSelf+Notes can pass AuthMiddleware", () => {
            cy.makePrivateEditSelfPlusNotesAPICall("GET", "/api/family/20/avatar", null, 403);
        });
    });

    // -----------------------------------------------------------------------
    // SENSITIVE (FamilyMiddleware group): profile, notes, writes
    // Current: 403 from AuthMiddleware.
    // Future (if exclusivity relaxed): still 403 — FamilyMiddleware enforces canViewFamily=false
    //   for non-own family. Assert 200 only for own family 20.
    // -----------------------------------------------------------------------
    describe("Sensitive endpoints — 403 now (AuthMiddleware) and 403 future (FamilyMiddleware scope)", () => {
        it("family 1 profile (non-own) → 403", () => {
            cy.makePrivateEditSelfPlusNotesAPICall("GET", "/api/family/1", null, 403);
        });

        it("family 1 notes (non-own) → 403", () => {
            cy.makePrivateEditSelfPlusNotesAPICall("GET", "/api/family/1/notes", null, 403);
        });
    });
});
