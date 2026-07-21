/// <reference types="cypress" />

/**
 * People Emails API Tests
 *
 * Covers GET /api/people/emails, which returns all mailing email addresses
 * grouped by classification role. The endpoint requires the Email role
 * (bEmailMailto permission); users without it receive 403.
 *
 * Business rules exercised here:
 *   - Response shape: { all: string[], byRole: Record<string, string[]> }
 *   - all is a superset of every byRole list
 *   - No case-insensitive duplicate addresses in all
 *   - iDoNotEmailPropertyId exclusion: the seed does NOT set iDoNotEmailPropertyId
 *     in config_cfg (value is empty/0), so the exclusion set is always empty in CI.
 *     Use POST /admin/api/system/config/iDoNotEmailPropertyId to set/restore it in a test.
 *   - sToEmailAddress append: the seed does NOT set sToEmailAddress in config_cfg,
 *     so no default address is appended in CI. Use
 *     POST /admin/api/system/config/sToEmailAddress to set/restore it in a test.
 *
 * Related people coverage:
 *  - People without email : cypress/e2e/api/private/people/people.without-email.spec.js
 *  - Person profile       : cypress/e2e/api/private/standard/private.people.person.spec.js
 */
describe("API Private People Emails", () => {
    // -----------------------------------------------------------------------
    // Happy path — admin user has email permission (usr_Admin=1 → isEmailEnabled()=true)
    // -----------------------------------------------------------------------
    describe("GET /api/people/emails — admin user (Email role)", () => {
        it("returns 200 with all and byRole properties", () => {
            cy.makePrivateAdminAPICall("GET", "/api/people/emails", null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("all");
                    expect(response.body).to.have.property("byRole");
                }
            );
        });

        it("all is a non-empty array of strings", () => {
            cy.makePrivateAdminAPICall("GET", "/api/people/emails", null, 200).then(
                (response) => {
                    expect(response.body.all).to.be.an("array").and.have.length.above(0);
                    response.body.all.forEach((email) => {
                        expect(email).to.be.a("string").and.not.be.empty;
                    });
                }
            );
        });

        it("byRole is an object whose values are non-empty string arrays", () => {
            cy.makePrivateAdminAPICall("GET", "/api/people/emails", null, 200).then(
                (response) => {
                    const byRole = response.body.byRole;
                    expect(byRole).to.be.an("object");
                    Object.values(byRole).forEach((roleEmails) => {
                        expect(roleEmails).to.be.an("array").and.have.length.above(0);
                        roleEmails.forEach((email) => {
                            expect(email).to.be.a("string").and.not.be.empty;
                        });
                    });
                }
            );
        });

        it("all contains every email that appears in byRole (superset)", () => {
            cy.makePrivateAdminAPICall("GET", "/api/people/emails", null, 200).then(
                (response) => {
                    const allLower = response.body.all.map((e) => e.toLowerCase());
                    Object.values(response.body.byRole).forEach((roleEmails) => {
                        roleEmails.forEach((email) => {
                            expect(allLower).to.include(email.toLowerCase());
                        });
                    });
                }
            );
        });

        it("all has no case-insensitive duplicate entries", () => {
            cy.makePrivateAdminAPICall("GET", "/api/people/emails", null, 200).then(
                (response) => {
                    const lower = response.body.all.map((e) => e.toLowerCase());
                    const unique = [...new Set(lower)];
                    expect(unique.length).to.equal(lower.length);
                }
            );
        });

        // -------------------------------------------------------------------
        // iDoNotEmailPropertyId exclusion
        // The seed does not set iDoNotEmailPropertyId in config_cfg (value is
        // empty/0), so the exclusion set is always empty in CI and all persons
        // with a non-empty email in an active family are included. The assertion
        // below verifies this default-unconfigured behaviour: person 1
        // (mathew.campbell@example.com) has a known email and is in an active
        // family, so their address must appear in all.
        //
        // To test the exclusion path: use POST /admin/api/system/config/iDoNotEmailPropertyId
        // to set the property ID (see private.admin.system.config.spec.js for the pattern),
        // assign the property to person 1, assert mathew.campbell@example.com is absent,
        // then restore the original value via another POST.
        // -------------------------------------------------------------------
        it("iDoNotEmailPropertyId not configured — known person email is included", () => {
            cy.makePrivateAdminAPICall("GET", "/api/people/emails", null, 200).then(
                (response) => {
                    const lower = response.body.all.map((e) => e.toLowerCase());
                    // Person 1 (mathew.campbell@example.com) is in an active family
                    // and has an email; with no DoNotEmail exclusion they must appear.
                    expect(lower).to.include("mathew.campbell@example.com");
                }
            );
        });

        // -------------------------------------------------------------------
        // sToEmailAddress append
        // The seed does not set sToEmailAddress in config_cfg (value is empty),
        // so no extra default address is injected in CI. The assertion below
        // verifies this default-unconfigured behaviour: calling the endpoint
        // twice returns an identical list (idempotent, no dynamic address added).
        //
        // To test the append path: use POST /admin/api/system/config/sToEmailAddress
        // to set a sentinel (e.g. "default@test.example") (see private.admin.system.config.spec.js),
        // assert the sentinel appears in both all and every byRole list,
        // then restore the original value via another POST.
        // -------------------------------------------------------------------
        it("sToEmailAddress not configured — response is idempotent (no extra address)", () => {
            cy.makePrivateAdminAPICall("GET", "/api/people/emails", null, 200).then(
                (firstResp) => {
                    cy.makePrivateAdminAPICall("GET", "/api/people/emails", null, 200).then(
                        (secondResp) => {
                            // Without a dynamic sToEmailAddress both calls return
                            // the same sorted address list.
                            const first = [...firstResp.body.all].sort();
                            const second = [...secondResp.body.all].sort();
                            expect(first).to.deep.equal(second);
                        }
                    );
                }
            );
        });
    });

    // -----------------------------------------------------------------------
    // Authorization — user without Email permission receives 403
    // john.plainauth (per_id=900) has usr_Notes=1 but no bEmailMailto entry
    // → isEmailEnabled() returns false → EmailRoleAuthMiddleware returns 403
    // -----------------------------------------------------------------------
    describe("GET /api/people/emails — user without Email permission", () => {
        it("returns 403 for a user without bEmailMailto", () => {
            cy.makePrivatePlainAuthAPICall("GET", "/api/people/emails", null, 403);
        });
    });
});


