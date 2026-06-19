/// <reference types="cypress" />

/**
 * UI tests for the Event Detail page — specifically the "Did Not Attend" list
 * that appears after an event ends when linked groups are present.
 *
 * Setup strategy:
 * - Use quick-create to build events, then POST /{id}/time to push the end
 *   time into the past so the Did Not Attend section is triggered.
 * - eventTypeId: 2 (Sunday School) + groupId: 1 (Angels) = the group-linked event.
 * - eventTypeId: 1 (Church Service, no group) = the no-group control event.
 * - All tests share these two events created in before().
 *
 * Session isolation note:
 * - cy.makePrivateAdminAPICall() (which uses cy.request()) shares the browser
 *   cookie jar with the browser. The API-key auth path in AuthMiddleware calls
 *   AuthenticationManager::authenticate(), which overwrites the PHP session's
 *   AuthenticationProvider with APITokenAuthentication. That provider's
 *   validateUserSessionIsActive() always returns false, so any subsequent
 *   browser cy.visit() ends up redirected to the login page.
 * - To avoid this, all roster data needed for test assertions is collected
 *   once in before() and stored in shared variables. Individual it() blocks
 *   contain only cy.visit() + UI assertions, never cy.request() calls.
 * - The one exception is the last test ("checkin-all"), which must call an
 *   API to mutate state; that test re-establishes the browser session with
 *   cy.setupAdminSession({ forceLogin: true }) inside the .then() before
 *   cy.visit().
 */
describe("Event Detail - Did Not Attend List", () => {
    let pastGroupEventId;   // past event linked to group 1; first member checked in
    let pastNoGroupEventId; // past event with no linked group
    let absentCount;        // number of members not checked in (set in before())
    let checkedInName;      // full name of the one checked-in member (set in before())

    before(() => {
        const yesterday = new Date();
        yesterday.setDate(yesterday.getDate() - 1);
        const pastDate = yesterday.toISOString().slice(0, 10);
        const pastStart = `${pastDate} 09:00:00`;
        const pastEnd = `${pastDate} 10:30:00`;

        // Event with group 1 linked, end time pushed to yesterday
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/events/quick-create",
            { eventTypeId: 2, groupId: 1 },
            200,
        ).then((resp) => {
            expect(resp.body).to.have.property("eventId");
            pastGroupEventId = resp.body.eventId;

            cy.makePrivateAdminAPICall(
                "POST",
                `/api/events/${pastGroupEventId}/time`,
                { startTime: pastStart, endTime: pastEnd },
                200,
            );

            // Check in exactly the first roster member; the rest remain absent.
            // Also capture roster data for later test assertions so individual
            // it() blocks never need to make API calls (see session note above).
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/events/${pastGroupEventId}/roster`,
                null,
                200,
            ).then((rosterResp) => {
                if (rosterResp.body.members.length > 0) {
                    const firstMember = rosterResp.body.members[0];
                    cy.makePrivateAdminAPICall(
                        "POST",
                        `/api/events/${pastGroupEventId}/checkin`,
                        { personId: firstMember.personId },
                        200,
                    ).then(() => {
                        // Re-fetch roster after checkin to get accurate statuses
                        cy.makePrivateAdminAPICall(
                            "GET",
                            `/api/events/${pastGroupEventId}/roster`,
                            null,
                            200,
                        ).then((afterCheckin) => {
                            absentCount = afterCheckin.body.members.filter(
                                (m) => m.status === "not_checked_in",
                            ).length;
                            const ci = afterCheckin.body.members.find(
                                (m) => m.status === "checked_in",
                            );
                            if (ci) {
                                checkedInName = `${ci.firstName} ${ci.lastName}`;
                            }
                        });
                    });
                } else {
                    absentCount = 0;
                }
            });
        });

        // Event with no linked group, also pushed to the past
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/events/quick-create",
            { eventTypeId: 1 },
            200,
        ).then((resp) => {
            expect(resp.body).to.have.property("eventId");
            pastNoGroupEventId = resp.body.eventId;

            cy.makePrivateAdminAPICall(
                "POST",
                `/api/events/${pastNoGroupEventId}/time`,
                { startTime: pastStart, endTime: pastEnd },
                200,
            );
        });
    });

    beforeEach(() => cy.setupAdminSession());

    it("Shows the Did Not Attend card after a group-linked event ends", () => {
        cy.visit(`event/view/${pastGroupEventId}`);
        cy.contains("h3", "Did Not Attend").should("be.visible");
    });

    it("Did Not Attend card has Name, Email and Phone columns", () => {
        cy.visit(`event/view/${pastGroupEventId}`);

        cy.contains("h3", "Did Not Attend")
            .closest(".card")
            .within(() => {
                cy.contains("th", "Name").should("exist");
                cy.contains("th", "Email").should("exist");
                cy.contains("th", "Phone").should("exist");
            });
    });

    it("Non-attendee count in badge matches roster not_checked_in count", () => {
        // absentCount was captured in before() — no API call needed here
        cy.visit(`event/view/${pastGroupEventId}`);

        cy.contains("h3", "Did Not Attend")
            .closest(".card")
            .within(() => {
                cy.get("tbody tr").should("have.length", absentCount);
            });
    });

    it("Checked-in member appears in Attendance and not in Did Not Attend", () => {
        // checkedInName was captured in before() — no API call needed here.
        // Assert the variable was actually set; a falsy value means the seed
        // data has no group members, which would be a setup problem.
        cy.wrap(checkedInName).should("exist");

        cy.visit(`event/view/${pastGroupEventId}`);

        cy.contains("h3", "Attendance")
            .closest(".card")
            .contains(checkedInName);

        cy.contains("h3", "Did Not Attend")
            .closest(".card")
            .should("not.contain.text", checkedInName);
    });

    it("Does NOT show Did Not Attend when the event has no linked groups", () => {
        cy.visit(`event/view/${pastNoGroupEventId}`);
        cy.contains("h3", "Did Not Attend").should("not.exist");
    });

    it("Check-in button is hidden for past events", () => {
        cy.visit(`event/view/${pastGroupEventId}`);
        cy.contains("a", "Check-in").should("not.exist");
    });

    it("Print button appears on the Attendance card for past group events", () => {
        cy.visit(`event/view/${pastGroupEventId}`);

        cy.contains("h3", "Attendance")
            .closest(".card-header")
            .contains("button", "Print")
            .should("be.visible");
    });

    // Run last — calls checkin-all which changes state for all subsequent tests.
    // Must use forceLogin after the API call to restore a valid browser session
    // (the API request overwrites the PHP session's auth provider — see file header).
    it("Shows success state when all group members have checked in", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            `/api/events/${pastGroupEventId}/checkin-all`,
            null,
            200,
        ).then(() => {
            cy.setupAdminSession({ forceLogin: true });
            cy.visit(`event/view/${pastGroupEventId}`);

            cy.contains("h3", "Did Not Attend").should("be.visible");
            cy.contains("Everyone from linked groups checked in!").should("be.visible");
        });
    });
});
