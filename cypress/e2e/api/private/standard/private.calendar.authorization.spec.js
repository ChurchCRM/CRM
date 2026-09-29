/// <reference types="cypress" />

/**
 * Authorization coverage for the /api/calendars write routes.
 *
 * Every route that mutates a calendar must sit behind
 * AddEventsRoleAuthMiddleware (User::canManageEvents() — the bAddEvent
 * permission, plus the Events module being enabled; admin bypasses).
 *
 * DELETE /api/calendars/{id} carried only CalendarMiddleware (an existence
 * check, not an authorization check) from the day the route landed in
 * a561f13ef (2018), so any authenticated caller — including a
 * zero-permission login or a read-only API key — could destroy a church
 * calendar. These specs pin the gate on all four write routes so the
 * omission cannot come back.
 *
 * Fixtures:
 *   - noperm.user    (id=901) every permission flag 0, EditSelf=0 — read-only
 *   - john.plainauth (id=900) Notes=1 only — read-only
 *   - Admin          (id=1)   canManageEvents() === true via isAdmin()
 */

const newCalendarBody = (suffix) => ({
    Name: `AuthZ Calendar ${suffix}`,
    ForegroundColor: "#ffffff",
    BackgroundColor: "#3788d8",
});

describe("API Private Calendar Authorization", () => {
    let calendarId = null;

    const createCalendar = () =>
        cy
            .makePrivateAdminAPICall(
                "POST",
                "/api/calendars",
                newCalendarBody(`${Date.now()}-${Cypress._.random(1e6)}`),
                200,
            )
            .then((response) => {
                expect(response.body).to.have.property("Id");
                calendarId = response.body.Id;
                return calendarId;
            });

    const expectCalendarExists = (id) =>
        cy
            .makePrivateAdminAPICall("GET", `/api/calendars/${id}`, null, 200)
            .then((response) => {
                expect(response.body.Calendars, "calendar still present").to.have.length(1);
                expect(response.body.Calendars[0].Id).to.eq(id);
            });

    beforeEach(() => {
        createCalendar();
    });

    afterEach(() => {
        if (calendarId !== null) {
            // 404 is fine — the "admin can delete" case already removed it.
            cy.makePrivateAdminAPICall(
                "DELETE",
                `/api/calendars/${calendarId}`,
                null,
                [200, 404],
            );
            calendarId = null;
        }
    });

    describe("DELETE /api/calendars/{id}", () => {
        it("Returns 403 for a zero-permission user and leaves the calendar intact", () => {
            const id = calendarId;
            cy.makePrivateNoPermAPICall(
                "DELETE",
                `/api/calendars/${id}`,
                null,
                403,
            ).then((response) => {
                expect(response.body).to.have.property("code", 403);
            });
            expectCalendarExists(id);
        });

        it("Returns 403 for a plain read-only user and leaves the calendar intact", () => {
            const id = calendarId;
            cy.makePrivatePlainAuthAPICall(
                "DELETE",
                `/api/calendars/${id}`,
                null,
                403,
            );
            expectCalendarExists(id);
        });

        it("Returns 401 when unauthenticated and leaves the calendar intact", () => {
            const id = calendarId;
            cy.apiRequest({
                method: "DELETE",
                url: `/api/calendars/${id}`,
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.eq(401);
            });
            expectCalendarExists(id);
        });

        it("Allows a user with the events permission (admin) to delete the calendar", () => {
            const id = calendarId;
            cy.makePrivateAdminAPICall(
                "DELETE",
                `/api/calendars/${id}`,
                null,
                200,
            );
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/calendars/${id}`,
                null,
                200,
            ).then((response) => {
                expect(response.body.Calendars, "calendar removed").to.have.length(0);
            });
            calendarId = null;
        });
    });

    describe("Sibling calendar write routes keep their gates", () => {
        it("Returns 403 for a zero-permission user on POST /api/calendars", () => {
            cy.makePrivateNoPermAPICall(
                "POST",
                "/api/calendars",
                newCalendarBody("rejected"),
                403,
            );
        });

        it("Returns 403 for a zero-permission user on POST /api/calendars/{id}/NewAccessToken", () => {
            const id = calendarId;
            cy.makePrivateNoPermAPICall(
                "POST",
                `/api/calendars/${id}/NewAccessToken`,
                null,
                403,
            );
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/calendars/${id}`,
                null,
                200,
            ).then((response) => {
                expect(response.body.Calendars[0].AccessToken).to.be.oneOf([
                    null,
                    "",
                ]);
            });
        });

        it("Returns 403 for a zero-permission user on DELETE /api/calendars/{id}/AccessToken", () => {
            const id = calendarId;
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/calendars/${id}/NewAccessToken`,
                null,
                200,
            ).then((response) => {
                expect(response.body.AccessToken).to.be.a("string").and.not.be
                    .empty;
            });
            cy.makePrivateNoPermAPICall(
                "DELETE",
                `/api/calendars/${id}/AccessToken`,
                null,
                403,
            );
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/calendars/${id}`,
                null,
                200,
            ).then((response) => {
                expect(
                    response.body.Calendars[0].AccessToken,
                    "token survives the rejected delete",
                ).to.be.a("string").and.not.be.empty;
            });
        });
    });
});
