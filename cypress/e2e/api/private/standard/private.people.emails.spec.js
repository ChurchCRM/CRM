/// <reference types="cypress" />

/**
 * People Emails API Tests
 *
 * Covers GET /api/people/emails, which returns all mailing email addresses
 * grouped by classification role. The endpoint requires the Email role
 * (bEmailMailto permission); users without it receive 403.
 *
 * Related people coverage:
 *  - People without email : cypress/e2e/api/private/people/people.without-email.spec.js
 *  - Person profile       : cypress/e2e/api/private/standard/private.people.person.spec.js
 *
 * TODO: untested business-rule scenarios (require direct DB access or extended seed data):
 *
 * 1. iDoNotEmailPropertyId exclusion
 *    PersonService::getMailingEmails() filters out people who have the property
 *    identified by `iDoNotEmailPropertyId` in SystemConfig (via per_props rows).
 *    Verifying this requires seeding a person with that property assigned and
 *    confirming their address is absent from the `all` and `byRole` arrays.
 *    The current seed data does not include such a person, and Cypress has no
 *    direct DB write path, so this case cannot be exercised without a dedicated
 *    seed fixture.
 *
 * 2. sToEmailAddress appended when configured
 *    When `sToEmailAddress` is set in SystemConfig the service appends that
 *    address to every mailing-email result.  Testing it end-to-end requires
 *    writing to the `sys_config` table before the request and restoring it
 *    after, which is not possible with the current Cypress command set.
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

        it("byRole is an object with array values", () => {
            cy.makePrivateAdminAPICall("GET", "/api/people/emails", null, 200).then(
                (response) => {
                    const byRole = response.body.byRole;
                    expect(byRole).to.be.an("object");
                    Object.values(byRole).forEach((roleEmails) => {
                        expect(roleEmails).to.be.an("array");
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
