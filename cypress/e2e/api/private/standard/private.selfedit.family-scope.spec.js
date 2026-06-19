/// <reference types="cypress" />

/**
 * GHSA-jjcj-h3cm-p7x7 — EditSelf-only users are confined to external pages.
 *
 * Test user: amanda.black (user ID 99, `selfedit.api.key`)
 *   - Permissions: EditSelf=1 ONLY (AddRecords/EditRecords/DeleteRecords/
 *     MenuOptions/ManageGroups/Finance/Notes/Admin all = 0)
 *   - Belongs to: family ID 20 (Black family)
 *
 * Expected: EditSelf grants NO internal API or page access. The
 * hasNoAdminPermissions() gate in AuthMiddleware must reject her API key with
 * 403 on EVERY internal endpoint — including her OWN family (family 20) and her
 * OWN person record (person 99). An EditSelf user's only self-service surface is
 * the token-scoped external verify flow (/external/verify/{token}), exercised by
 * cypress/e2e/ui/security/limited-access.spec.js.
 *
 * This is the primary fix for GHSA-jjcj-h3cm-p7x7: rather than scoping EditSelf
 * users to their own family inside each endpoint, they are blocked from the
 * internal API surface entirely at the entry gate. The object-level
 * canViewFamily()/canEditPerson() checks remain as defense-in-depth for any
 * future EditSelf+module permission combination.
 */
describe("GHSA-jjcj-h3cm-p7x7 - EditSelf-only user is locked out of internal APIs", () => {
    // Representative internal endpoints across the family/person attack surface.
    // EVERY one must return 403 for an EditSelf-only user — own resources included.
    const lockedOutGets = [
        "/api/family/20", // OWN family — still 403 (EditSelf grants no API access)
        "/api/family/1", // another family
        "/api/family/20/notes",
        "/api/family/1/notes",
        "/api/family/20/photo",
        "/api/family/1/photo",
        "/api/family/20/avatar",
        "/api/family/1/avatar",
        "/api/timeline/family/20",
        "/api/timeline/family/1",
        "/api/person/99", // OWN person record
        "/api/person/2", // another family's person
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
