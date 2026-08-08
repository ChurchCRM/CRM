/// <reference types="cypress" />

/**
 * Regression spec for GHSA-4wmp-3v34-g7q8:
 * Person-property API permitted MenuOptions-only users to read and modify
 * arbitrary person/family records through the person-properties API without
 * requiring EditRecords permission (broken object-level authorization).
 *
 * Fix: EditRecordsRoleAuthMiddleware was added to the six record-level property
 * routes (GET/POST/DELETE for person and family). Property-definition list routes
 * (GET /person, GET /family, DELETE /definition/:id) remain MenuOptions-only.
 *
 * Seed users used:
 *   - menuoptions.user (id=902): usr_MenuOptions=1, all other perm flags 0, non-admin.
 *     Should be blocked (403) on all record-level property routes.
 *   - tony.wade (user.api.key, id=3): MenuOptions=1, EditRecords=1, Admin=0.
 *     Should be allowed (200) on all property routes.
 *
 * Seed data:
 *   - person 2 (Mathew Campbell)
 *   - property 1 ('Disabled', class='p') — person-type property
 *   - family 1 (Campbell family)
 *   - property 2 ('Single Parent', class='f') — family-type property
 */
describe("People Properties API — EditRecords authorization gate (GHSA-4wmp-3v34-g7q8)", () => {
    const PERSON_ID = 2; // Mathew Campbell (seed)
    const PERSON_PROPERTY_ID = 1; // 'Disabled' person-class property (seed)
    const FAMILY_ID = 1; // Campbell family (seed)
    const FAMILY_PROPERTY_ID = 2; // 'Single Parent' family-class property (seed)

    // ── Property definition list routes — MenuOptions sufficient (unchanged) ──

    context("Definition list routes remain accessible to MenuOptions-only user", () => {
        it("GET /api/people/properties/person — 200 for MenuOptions-only user", () => {
            cy.makePrivateMenuOptionsAPICall(
                "GET",
                "/api/people/properties/person",
                "",
                200,
            ).then((resp) => {
                expect(resp.body).to.be.an("array");
            });
        });

        it("GET /api/people/properties/family — 200 for MenuOptions-only user", () => {
            cy.makePrivateMenuOptionsAPICall(
                "GET",
                "/api/people/properties/family",
                "",
                200,
            ).then((resp) => {
                expect(resp.body).to.be.an("array");
            });
        });
    });

    // ── Person record-level property routes — EditRecords required ──

    context("Person record property routes: MenuOptions-only user blocked (403)", () => {
        it("GET /api/people/properties/person/:id -> 403", () => {
            cy.makePrivateMenuOptionsAPICall(
                "GET",
                `/api/people/properties/person/${PERSON_ID}`,
                "",
                403,
            );
        });

        it("POST /api/people/properties/person/:id/:propId -> 403", () => {
            cy.makePrivateMenuOptionsAPICall(
                "POST",
                `/api/people/properties/person/${PERSON_ID}/${PERSON_PROPERTY_ID}`,
                {},
                403,
            );
        });

        it("DELETE /api/people/properties/person/:id/:propId -> 403", () => {
            cy.makePrivateMenuOptionsAPICall(
                "DELETE",
                `/api/people/properties/person/${PERSON_ID}/${PERSON_PROPERTY_ID}`,
                "",
                403,
            );
        });
    });

    context("Person record property routes: MenuOptions + EditRecords user succeeds", () => {
        before(() => {
            // Ensure the assignment row exists before DELETE runs.
            // addPropertyToPerson returns 200 whether the row is new or already present.
            cy.makePrivateUserAPICall(
                "POST",
                `/api/people/properties/person/${PERSON_ID}/${PERSON_PROPERTY_ID}`,
                {},
                200,
            );
        });

        it("GET /api/people/properties/person/:id -> 200", () => {
            cy.makePrivateUserAPICall(
                "GET",
                `/api/people/properties/person/${PERSON_ID}`,
                "",
                200,
            ).then((resp) => {
                expect(resp.body).to.be.an("array");
            });
        });

        it("POST /api/people/properties/person/:id/:propId -> 200", () => {
            cy.makePrivateUserAPICall(
                "POST",
                `/api/people/properties/person/${PERSON_ID}/${PERSON_PROPERTY_ID}`,
                {},
                200,
            ).then((resp) => {
                expect(resp.body).to.have.property("success", true);
            });
        });

        it("DELETE /api/people/properties/person/:id/:propId -> 200", () => {
            cy.makePrivateUserAPICall(
                "DELETE",
                `/api/people/properties/person/${PERSON_ID}/${PERSON_PROPERTY_ID}`,
                "",
                200,
            ).then((resp) => {
                expect(resp.body).to.have.property("success", true);
            });
        });
    });

    // ── Family record-level property routes — EditRecords required (parity) ──

    context("Family record property routes: MenuOptions-only user blocked (403)", () => {
        it("GET /api/people/properties/family/:id -> 403", () => {
            cy.makePrivateMenuOptionsAPICall(
                "GET",
                `/api/people/properties/family/${FAMILY_ID}`,
                "",
                403,
            );
        });

        it("POST /api/people/properties/family/:id/:propId -> 403", () => {
            cy.makePrivateMenuOptionsAPICall(
                "POST",
                `/api/people/properties/family/${FAMILY_ID}/${FAMILY_PROPERTY_ID}`,
                {},
                403,
            );
        });

        it("DELETE /api/people/properties/family/:id/:propId -> 403", () => {
            cy.makePrivateMenuOptionsAPICall(
                "DELETE",
                `/api/people/properties/family/${FAMILY_ID}/${FAMILY_PROPERTY_ID}`,
                "",
                403,
            );
        });
    });

    context("Family record property routes: MenuOptions + EditRecords user succeeds", () => {
        before(() => {
            // Ensure the assignment row exists before DELETE runs.
            cy.makePrivateUserAPICall(
                "POST",
                `/api/people/properties/family/${FAMILY_ID}/${FAMILY_PROPERTY_ID}`,
                {},
                200,
            );
        });

        it("GET /api/people/properties/family/:id -> 200", () => {
            cy.makePrivateUserAPICall(
                "GET",
                `/api/people/properties/family/${FAMILY_ID}`,
                "",
                200,
            ).then((resp) => {
                expect(resp.body).to.be.an("array");
            });
        });

        it("POST /api/people/properties/family/:id/:propId -> 200", () => {
            cy.makePrivateUserAPICall(
                "POST",
                `/api/people/properties/family/${FAMILY_ID}/${FAMILY_PROPERTY_ID}`,
                {},
                200,
            ).then((resp) => {
                expect(resp.body).to.have.property("success", true);
            });
        });

        it("DELETE /api/people/properties/family/:id/:propId -> 200", () => {
            cy.makePrivateUserAPICall(
                "DELETE",
                `/api/people/properties/family/${FAMILY_ID}/${FAMILY_PROPERTY_ID}`,
                "",
                200,
            ).then((resp) => {
                expect(resp.body).to.have.property("success", true);
            });
        });
    });
});
