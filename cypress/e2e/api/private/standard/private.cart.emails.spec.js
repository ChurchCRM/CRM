/// <reference types="cypress" />

/**
 * Cart Emails API Tests
 *
 * Covers GET /api/cart/emails, which returns a flat array of email addresses
 * for all persons currently in the session cart. The endpoint requires the
 * Email role (bEmailMailto permission); users without it receive 403.
 *
 * The happy-path suite uses setupStandardSession() + cy.request() so that
 * cart state (stored in $_SESSION['aPeopleCart']) persists across requests
 * within the same browser session — matching the pattern in
 * private.cart.duplicates.spec.js. The standard user (tony.wade, per_id=3)
 * has bEmailMailto=1 so they can reach GET /api/cart/emails.
 *
 * Business rules exercised here:
 *   - Response shape: { emails: string[] }
 *   - No case-insensitive duplicate addresses
 *   - iDoNotEmailPropertyId exclusion: the seed does NOT set iDoNotEmailPropertyId
 *     in config_cfg, so the exclusion set is always empty in CI. Use
 *     POST /admin/api/system/config/iDoNotEmailPropertyId to set/restore it in a test.
 *   - sToEmailAddress append: the seed does NOT set sToEmailAddress in config_cfg,
 *     so no default address is appended in CI. Use
 *     POST /admin/api/system/config/sToEmailAddress to set/restore it in a test.
 *
 * Related cart coverage:
 *  - Duplicate detection  : cypress/e2e/api/private/standard/private.cart.duplicates.spec.js
 *  - Cart-to-group        : cypress/e2e/api/private/standard/private.cart.empty-to-group.spec.js
 */
describe("API Private Cart Emails", () => {
    // -----------------------------------------------------------------------
    // Happy path — standard user (bEmailMailto=1), session-based cart
    // Uses setupStandardSession() + cy.request() so $_SESSION['aPeopleCart']
    // persists across requests (same pattern as private.cart.duplicates.spec.js).
    // -----------------------------------------------------------------------
    describe("GET /api/cart/emails — standard user (Email role, session cart)", () => {
        beforeEach(() => {
            cy.setupStandardSession();
            // Empty the cart so we start from a known state.
            cy.request({
                method: "DELETE",
                url: "/api/cart/",
                headers: { "Content-Type": "application/json" },
                body: null,
            });
            // Seed cart with person 1 (mathew.campbell@example.com).
            cy.request({
                method: "POST",
                url: "/api/cart/",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ Persons: [1] }),
            });
        });

        afterEach(() => {
            cy.setupStandardSession();
            cy.request({
                method: "DELETE",
                url: "/api/cart/",
                headers: { "Content-Type": "application/json" },
                body: null,
            });
        });

        it("returns 200 with an emails array", () => {
            cy.request({
                method: "GET",
                url: "/api/cart/emails",
                headers: { "Content-Type": "application/json" },
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body).to.have.property("emails");
                expect(response.body.emails).to.be.an("array");
            });
        });

        it("emails array contains only non-empty strings", () => {
            cy.request({
                method: "GET",
                url: "/api/cart/emails",
                headers: { "Content-Type": "application/json" },
            }).then((response) => {
                expect(response.body.emails.length).to.be.greaterThan(0);
                response.body.emails.forEach((email) => {
                    expect(email).to.be.a("string").and.not.be.empty;
                });
            });
        });

        it("emails array has no case-insensitive duplicate entries", () => {
            cy.request({
                method: "GET",
                url: "/api/cart/emails",
                headers: { "Content-Type": "application/json" },
            }).then((response) => {
                const lower = response.body.emails.map((e) => e.toLowerCase());
                const unique = [...new Set(lower)];
                expect(unique.length).to.equal(lower.length);
            });
        });

        // -------------------------------------------------------------------
        // iDoNotEmailPropertyId exclusion
        // The seed does not set iDoNotEmailPropertyId in config_cfg (value is
        // empty/0), so the exclusion set is always empty in CI. The assertion
        // below verifies the default-unconfigured behaviour: person 1
        // (mathew.campbell@example.com) is included because no exclusion is
        // active.
        //
        // To test the exclusion path, use POST /admin/api/system/config/iDoNotEmailPropertyId
        // to set the property ID (see private.admin.system.config.spec.js for the pattern),
        // assign the property to a known person, assert their address is absent, then restore
        // the original value via another POST to avoid polluting subsequent tests.
        // -------------------------------------------------------------------
        it("iDoNotEmailPropertyId not configured — all cart persons with emails are included", () => {
            cy.request({
                method: "GET",
                url: "/api/cart/emails",
                headers: { "Content-Type": "application/json" },
            }).then((response) => {
                // Person 1 (mathew.campbell@example.com) was added to cart.
                // With no DoNotEmail exclusion active their address must appear.
                expect(response.body.emails.length).to.be.greaterThan(0);
                const lower = response.body.emails.map((e) => e.toLowerCase());
                expect(lower).to.include("mathew.campbell@example.com");
            });
        });

        // -------------------------------------------------------------------
        // sToEmailAddress append
        // The seed does not set sToEmailAddress in config_cfg (value is empty),
        // so no default address is appended in CI. The assertion below verifies
        // the default-unconfigured behaviour: emails.length <= cart size.
        //
        // To test the append path, use POST /admin/api/system/config/sToEmailAddress
        // to set a sentinel value (see private.admin.system.config.spec.js for the pattern),
        // assert the sentinel appears at the end of emails, then restore the original value
        // via another POST to avoid polluting subsequent tests.
        // -------------------------------------------------------------------
        it("sToEmailAddress not configured — no extra default address appended", () => {
            cy.request({
                method: "GET",
                url: "/api/cart/",
                headers: { "Content-Type": "application/json" },
            }).then((cartResp) => {
                cy.request({
                    method: "GET",
                    url: "/api/cart/emails",
                    headers: { "Content-Type": "application/json" },
                }).then((emailResp) => {
                    // With sToEmailAddress empty, emails.length <= cart size
                    // (some cart persons may have no email address).
                    expect(emailResp.body.emails.length).to.be.at.most(
                        cartResp.body.PeopleCart.length
                    );
                });
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
