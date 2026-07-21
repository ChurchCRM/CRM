/// <reference types="cypress" />

/**
 * Cart Emails API Tests
 *
 * Covers GET /api/cart/emails, which returns a flat array of email addresses
 * for all persons currently in the session cart. The endpoint requires the
 * Email role (bEmailMailto permission); users without it receive 403.
 *
 * Related cart coverage:
 *  - Duplicate detection  : cypress/e2e/api/private/standard/private.cart.duplicates.spec.js
 *  - Cart-to-group        : cypress/e2e/api/private/standard/private.cart.empty-to-group.spec.js
 */
describe("API Private Cart Emails", () => {
    // -----------------------------------------------------------------------
    // Happy path — admin user has email permission (usr_Admin=1 → isEmailEnabled()=true)
    // -----------------------------------------------------------------------
    describe("GET /api/cart/emails — admin user (Email role)", () => {
        before(() => {
            // Seed the cart with person 1 so the response is non-empty
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/cart/",
                { Persons: [1] },
                200
            );
        });

        after(() => {
            // Leave the cart empty for subsequent specs
            cy.makePrivateAdminAPICall(
                "DELETE",
                "/api/cart/",
                {},
                200
            );
        });

        it("returns 200 with an emails array", () => {
            cy.makePrivateAdminAPICall("GET", "/api/cart/emails", null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("emails");
                    expect(response.body.emails).to.be.an("array");
                }
            );
        });

        it("emails array contains only strings", () => {
            cy.makePrivateAdminAPICall("GET", "/api/cart/emails", null, 200).then(
                (response) => {
                    response.body.emails.forEach((email) => {
                        expect(email).to.be.a("string").and.not.be.empty;
                    });
                }
            );
        });

        it("emails array has no duplicate entries", () => {
            cy.makePrivateAdminAPICall("GET", "/api/cart/emails", null, 200).then(
                (response) => {
                    const emails = response.body.emails;
                    const lower = emails.map((e) => e.toLowerCase());
                    const unique = [...new Set(lower)];
                    expect(unique.length).to.equal(lower.length);
                }
            );
        });
    });

    // -----------------------------------------------------------------------
    // Authorization — user without Email permission receives 403
    // john.plainauth (per_id=900) has usr_Notes=1 but no bEmailMailto entry
    // → isEmailEnabled() returns false → EmailRoleAuthMiddleware returns 403
    // -----------------------------------------------------------------------
    describe("GET /api/cart/emails — user without Email permission", () => {
        it("returns 403 for a user without bEmailMailto", () => {
            cy.makePrivatePlainAuthAPICall("GET", "/api/cart/emails", null, 403);
        });
    });
});
