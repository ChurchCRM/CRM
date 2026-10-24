/// <reference types="cypress" />

/**
 * Regression spec for GHSA-4wmp-3v34-g7q8:
 * The person-properties API must require EditRecords to read or modify a
 * person's or family's properties, and EditRecords is enough on its own.
 *
 * Seed users used:
 *   - editrecords.user (judith.matthews, id=95): EditRecords=1, all other
 *     permission flags 0, non-admin. Must succeed on every route here.
 *   - noperm.user (id=901): every permission flag 0. Must get 403.
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

    // ── Property definition list routes — EditRecords required ──

    context("Definition list routes", () => {
        it("GET /api/people/properties/person — 200 for an EditRecords-only user", () => {
            cy.makePrivateEditRecordsAPICall(
                "GET",
                "/api/people/properties/person",
                "",
                200,
            ).then((resp) => {
                expect(resp.body).to.be.an("array");
            });
        });

        it("GET /api/people/properties/family — 200 for an EditRecords-only user", () => {
            cy.makePrivateEditRecordsAPICall(
                "GET",
                "/api/people/properties/family",
                "",
                200,
            ).then((resp) => {
                expect(resp.body).to.be.an("array");
            });
        });

        it("GET /api/people/properties/person -> 403 without EditRecords", () => {
            cy.makePrivateNoPermAPICall(
                "GET",
                "/api/people/properties/person",
                "",
                403,
            );
        });
    });

    // ── Person record-level property routes — EditRecords required ──

    context("Person record property routes: user without EditRecords blocked (403)", () => {
        it("POST /api/people/properties/person/:id/:propId -> 403", () => {
            cy.makePrivateNoPermAPICall(
                "POST",
                `/api/people/properties/person/${PERSON_ID}/${PERSON_PROPERTY_ID}`,
                {},
                403,
            );
        });
    });

    context("Person record property routes: EditRecords-only user succeeds", () => {
        before(() => {
            // Ensure the assignment row exists before DELETE runs.
            // addPropertyToPerson returns 200 whether the row is new or already present.
            cy.makePrivateEditRecordsAPICall(
                "POST",
                `/api/people/properties/person/${PERSON_ID}/${PERSON_PROPERTY_ID}`,
                {},
                200,
            );
        });

        it("GET /api/people/properties/person/:id -> 200", () => {
            cy.makePrivateEditRecordsAPICall(
                "GET",
                `/api/people/properties/person/${PERSON_ID}`,
                "",
                200,
            ).then((resp) => {
                expect(resp.body).to.be.an("array");
            });
        });

        it("POST /api/people/properties/person/:id/:propId -> 200", () => {
            cy.makePrivateEditRecordsAPICall(
                "POST",
                `/api/people/properties/person/${PERSON_ID}/${PERSON_PROPERTY_ID}`,
                {},
                200,
            ).then((resp) => {
                expect(resp.body).to.have.property("success", true);
            });
        });

        it("DELETE /api/people/properties/person/:id/:propId -> 200", () => {
            cy.makePrivateEditRecordsAPICall(
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

    context("Family record property routes: user without EditRecords blocked (403)", () => {
        it("POST /api/people/properties/family/:id/:propId -> 403", () => {
            cy.makePrivateNoPermAPICall(
                "POST",
                `/api/people/properties/family/${FAMILY_ID}/${FAMILY_PROPERTY_ID}`,
                {},
                403,
            );
        });
    });

    context("Family record property routes: EditRecords-only user succeeds", () => {
        before(() => {
            // Ensure the assignment row exists before DELETE runs.
            cy.makePrivateEditRecordsAPICall(
                "POST",
                `/api/people/properties/family/${FAMILY_ID}/${FAMILY_PROPERTY_ID}`,
                {},
                200,
            );
        });

        it("GET /api/people/properties/family/:id -> 200", () => {
            cy.makePrivateEditRecordsAPICall(
                "GET",
                `/api/people/properties/family/${FAMILY_ID}`,
                "",
                200,
            ).then((resp) => {
                expect(resp.body).to.be.an("array");
            });
        });

        it("POST /api/people/properties/family/:id/:propId -> 200", () => {
            cy.makePrivateEditRecordsAPICall(
                "POST",
                `/api/people/properties/family/${FAMILY_ID}/${FAMILY_PROPERTY_ID}`,
                {},
                200,
            ).then((resp) => {
                expect(resp.body).to.have.property("success", true);
            });
        });

        it("DELETE /api/people/properties/family/:id/:propId -> 200", () => {
            cy.makePrivateEditRecordsAPICall(
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
