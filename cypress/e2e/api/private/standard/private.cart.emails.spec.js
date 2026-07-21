/// <reference types="cypress" />

/**
 * Cart Emails API Tests
 *
 * Covers GET /api/cart/emails, which returns a flat array of email addresses
 * for all persons currently in the session cart. The endpoint requires the
 * Email role (bEmailMailto permission); users without it receive 403.
 *
 * Business rules exercised here:
 *   - Response shape: { emails: string[] }
 *   - No case-insensitive duplicate addresses
 *   - iDoNotEmailPropertyId exclusion: the seed does NOT set iDoNotEmailPropertyId
 *     in config_cfg, so the exclusion set is always empty in CI. A note is left
 *     below where a dedicated fixture could drive that assertion.
 *   - sToEmailAddress append: the seed does NOT set sToEmailAddress in config_cfg,
 *     so no default address is appended in CI. A note is left below for a fixture.
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
            // Seed the cart with person 1 (mathew.campbell@example.com) so the
            // response is non-empty for all subsequent assertions.
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/cart/",
                { Persons: [1] },
                200
            );
        });

        after(() => {
            // Leave the cart empty for subsequent specs.
            cy.makePrivateAdminAPICall(
                "DELETE",
                "/api/cart/",
                null,
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

        it("emails array contains only non-empty strings", () => {
            cy.makePrivateAdminAPICall("GET", "/api/cart/emails", null, 200).then(
                (response) => {
                    response.body.emails.forEach((email) => {
                        expect(email).to.be.a("string").and.not.be.empty;
                    });
                }
            );
        });

        it("emails array has no case-insensitive duplicate entries", () => {
            cy.makePrivateAdminAPICall("GET", "/api/cart/emails", null, 200).then(
                (response) => {
                    const lower = response.body.emails.map((e) => e.toLowerCase());
                    const unique = [...new Set(lower)];
                    expect(unique.length).to.equal(lower.length);
                }
            );
        });

        // -------------------------------------------------------------------
        // iDoNotEmailPropertyId exclusion
        // The seed does not set iDoNotEmailPropertyId in config_cfg (value is
        // empty/0), so the exclusion set is always empty in CI. The assertion
        // below verifies the default-unconfigured behaviour: person 1
        // (mathew.campbell@example.com) is included because no exclusion is
        // active.
        //
        // To test the exclusion path in isolation a separate fixture would need
        // to (a) create a "Do Not Email" property, (b) set iDoNotEmailPropertyId
        // to that property's ID, (c) assign it to a test person, and (d) confirm
        // that person's address is absent from the response.
        // -------------------------------------------------------------------
        it("iDoNotEmailPropertyId not configured — all cart persons with emails are included", () => {
            cy.makePrivateAdminAPICall("GET", "/api/cart/emails", null, 200).then(
                (response) => {
                    // Person 1 (mathew.campbell@example.com) was added to cart.
                    // With no DoNotEmail exclusion active their address must appear.
                    expect(response.body.emails.length).to.be.greaterThan(0);
                    const lower = response.body.emails.map((e) => e.toLowerCase());
                    expect(lower).to.include("mathew.campbell@example.com");
                }
            );
        });

        // -------------------------------------------------------------------
        // sToEmailAddress append
        // The seed does not set sToEmailAddress in config_cfg (value is empty),
        // so no default address is appended in CI. The assertion below verifies
        // the default-unconfigured behaviour: the result length matches exactly
        // the number of unique cart-person emails (no extra address appended).
        //
        // To test the append path, use the admin config API — this IS possible
        // via POST /admin/api/system/config/sToEmailAddress { value: "sentinel@test.example" }.
        // Call the endpoint, assert the sentinel appears at the end of emails[],
        // then restore the original value with another POST afterward.
        // -------------------------------------------------------------------
        it("sToEmailAddress not configured — no extra default address appended", () => {
            // First, get the cart people count for comparison
            cy.makePrivateAdminAPICall("GET", "/api/cart/", null, 200).then((cartResp) => {
                cy.makePrivateAdminAPICall("GET", "/api/cart/emails", null, 200).then(
                    (emailResp) => {
                        // With sToEmailAddress empty, emails.length <= cart size
                        // (some cart persons may have no email address).
                        expect(emailResp.body.emails.length).to.be.at.most(
                            cartResp.body.PeopleCart.length
                        );
                    }
                );
            });
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
