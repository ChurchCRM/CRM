/// <reference types="cypress" />

/**
 * Regression tests for GHSA-jjcj-h3cm-p7x7
 *
 * EditSelf is now an exclusive permission mode: a non-admin user with EditSelf=1
 * has ALL other module permissions suppressed at the model layer
 * (User::isEditSelfExclusive()), which causes hasNoAdminPermissions() to return
 * true. AuthMiddleware therefore blocks every internal API request with 403 —
 * including the user's OWN family and OWN person record.
 *
 * Test user: amanda.black (user ID 99, `selfedit.api.key`)
 *   - Permissions: EditSelf=1 ONLY (all others = 0, enforced by model + DB)
 *   - Belongs to: family ID 20 (Black family)
 *
 * Expected: 403 on EVERY internal endpoint. The only self-service surface for an
 * EditSelf user is the token-scoped external verify flow (/external/verify/{token}),
 * which is covered in cypress/e2e/ui/security/limited-access.spec.js.
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
