/// <reference types="cypress" />

/**
 * API tests for an event's primary / secondary contact endpoints.
 *
 * Regression coverage for issue #9721: the `PrimaryContact` foreign key in
 * orm/schema.xml referenced `event_type` instead of `primary_contact_person_id`,
 * so Propel generated `getPersonRelatedByType()` and
 * `GET /api/events/{id}/primarycontact` returned HTTP 500
 * ("Call to undefined method: getPersonRelatedByPrimaryContactPersonId.").
 *
 * Seed data: event 3 ("Summer Camp") has primary contact per_ID 1 and
 * secondary contact per_ID 2. Events 1 and 2 have neither.
 */
describe("API Private Calendar Event Contacts", () => {
    const EVENT_WITH_CONTACTS = 3;
    const EVENT_WITHOUT_CONTACTS = 1;

    describe("GET /api/events/{id}/primarycontact", () => {
        it("Returns 200 with the person JSON when a primary contact is set", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/events/${EVENT_WITH_CONTACTS}/primarycontact`,
                null,
                200,
            ).then((response) => {
                expect(response.body).to.exist;
                expect(response.body).to.have.property("Id", 1);
                expect(response.body).to.have.property("FirstName", "Church");
                expect(response.body).to.have.property("LastName", "Admin");
            });
        });

        it("Returns 404 when no primary contact is set", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/events/${EVENT_WITHOUT_CONTACTS}/primarycontact`,
                null,
                404,
            ).then((response) => {
                // Guards against the #9721 regression: a wrong foreign key
                // surfaced as a 500 "undefined method" instead of a clean 404.
                expect(JSON.stringify(response.body)).to.not.contain(
                    "undefined method",
                );
            });
        });

        it("Returns 401 when not authenticated", () => {
            cy.apiRequest({
                method: "GET",
                url: `/api/events/${EVENT_WITH_CONTACTS}/primarycontact`,
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.eq(401);
            });
        });
    });

    describe("GET /api/events/{id}/secondarycontact", () => {
        it("Returns 200 with the person JSON when a secondary contact is set", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/events/${EVENT_WITH_CONTACTS}/secondarycontact`,
                null,
                200,
            ).then((response) => {
                expect(response.body).to.exist;
                expect(response.body).to.have.property("Id", 2);
                expect(response.body).to.have.property("FirstName", "Mathew");
                expect(response.body).to.have.property("LastName", "Campbell");
            });
        });

        it("Returns 404 when no secondary contact is set", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/events/${EVENT_WITHOUT_CONTACTS}/secondarycontact`,
                null,
                404,
            ).then((response) => {
                expect(JSON.stringify(response.body)).to.not.contain(
                    "undefined method",
                );
            });
        });

        it("Returns 401 when not authenticated", () => {
            cy.apiRequest({
                method: "GET",
                url: `/api/events/${EVENT_WITH_CONTACTS}/secondarycontact`,
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.eq(401);
            });
        });
    });
});
