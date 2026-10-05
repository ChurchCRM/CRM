/// <reference types="cypress" />

/**
 * Regression cover for the event plugin-hook dispatch points added in #9734.
 *
 * EVENT_UPDATED and EVENT_DELETED are dispatched from Event::postUpdate() and
 * Event::postDelete(), and EVENT_DELETED's payload is snapshotted in
 * preDelete() before the child-row cascade. Those Propel hooks sit directly in
 * the save/delete path of every event, so a mistake there breaks event writes
 * outright rather than just the hook. This spec drives every API route that
 * reaches one of those dispatch points and asserts the write actually landed.
 *
 * What this spec deliberately does NOT assert: that a plugin callback ran.
 * There is no hook-observability endpoint (HookManager::didAction() counts are
 * per-PHP-request and nothing exposes them), no existing Cypress pattern for
 * asserting a hook fired, and no test plugin in the repo. That half of #9734
 * was verified by registering listeners on a core plugin and reading the app
 * log — the procedure and its output are recorded in the commit body.
 */
describe("API Event Hooks - dispatch path regression", () => {
    // No browser login — pure API spec using x-api-key auth.
    const eventTypeId = 1; // seeded "Church Service" type, as the other event specs use

    /** Find a created event by title (POST /events returns only {success:true}). */
    const findEventIdByTitle = (title) =>
        cy.makePrivateAdminAPICall("GET", "/api/events", null, 200).then((response) => {
            const match = response.body.Events.find((e) => e.Title === title);
            expect(match, `event "${title}" is listed after creation`).to.exist;
            return match.Id;
        });

    it("Creates, updates, retimes, restatuses and deletes an event", () => {
        const title = `HookSpec Event ${Date.now()}`;
        const renamed = `${title} (renamed)`;

        cy.makePrivateAdminAPICall(
            "POST",
            "/api/events",
            {
                Title: title,
                Type: eventTypeId,
                Desc: "",
                Text: "",
                Start: "2030-06-02T09:00:00",
                End: "2030-06-02T10:00:00",
                PinnedCalendars: [],
            },
            200,
        );

        findEventIdByTitle(title).then((eventId) => {
            // POST /events/{id} — the main update path (Event::postUpdate()).
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/events/${eventId}`,
                {
                    Title: renamed,
                    Type: eventTypeId,
                    Desc: "",
                    Text: "",
                    Start: "2030-06-02 09:00:00",
                    End: "2030-06-02 10:00:00",
                    PinnedCalendars: [],
                },
                200,
            );

            cy.makePrivateAdminAPICall("GET", `/api/events/${eventId}`, null, 200).then((resp) => {
                expect(resp.body.Title, "update persisted").to.equal(renamed);
            });

            // POST /events/{id}/time — a second update path through the same hook.
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/events/${eventId}/time`,
                { startTime: "2030-06-02 11:00:00", endTime: "2030-06-02 12:00:00" },
                200,
            );

            cy.makePrivateAdminAPICall("GET", `/api/events/${eventId}`, null, 200).then((resp) => {
                expect(resp.body.Start, "retime persisted").to.contain("11:00:00");
            });

            // POST /events/{id}/status — a third update path.
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/events/${eventId}/status`,
                { active: false },
                200,
            );

            cy.makePrivateAdminAPICall("GET", `/api/events/${eventId}`, null, 200).then((resp) => {
                expect(Number(resp.body.InActive), "status persisted").to.equal(1);
            });

            // DELETE /events/{id} — preDelete() snapshot + cascade + postDelete().
            cy.makePrivateAdminAPICall("DELETE", `/api/events/${eventId}`, null, 200).then(
                (resp) => {
                    expect(resp.body).to.have.property("success", true);
                },
            );

            cy.makePrivateAdminAPICall("GET", `/api/events/${eventId}`, null, 404);
        });
    });

    it("Bulk-creates repeat events and deletes each one", () => {
        const title = `HookSpec Repeat ${Date.now()}`;

        cy.makePrivateAdminAPICall(
            "POST",
            "/api/events/repeat",
            {
                Title: title,
                Type: eventTypeId,
                StartTime: "09:00",
                EndTime: "10:00",
                RecurType: "weekly",
                RecurDOW: "Sunday",
                RangeStart: "2030-07-07",
                RangeEnd: "2030-07-28",
                PinnedCalendars: [],
            },
            200,
        ).then((response) => {
            expect(response.body.success).to.be.true;
            expect(response.body.eventIds).to.be.an("array").and.not.be.empty;
            expect(response.body.count).to.equal(response.body.eventIds.length);

            // Every id the service reports must be a real, fetchable event —
            // this is the loop that now fires EVENT_CREATED per occurrence.
            response.body.eventIds.forEach((eventId) => {
                cy.makePrivateAdminAPICall("GET", `/api/events/${eventId}`, null, 200).then(
                    (resp) => {
                        expect(resp.body.Title).to.equal(title);
                    },
                );
                cy.makePrivateAdminAPICall("DELETE", `/api/events/${eventId}`, null, 200);
            });
        });
    });

    it("Generates recurring events and deletes each one", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/events/generate-recurring",
            {
                eventTypeId,
                startDate: "2030-08-04",
                endDate: "2030-08-18",
                dayOfWeek: 0,
                startTime: "09:00",
                skipExisting: false,
            },
            200,
        ).then((response) => {
            expect(response.body.created).to.be.a("number").and.be.at.least(1);
            expect(response.body.events).to.have.length(response.body.created);

            response.body.events.forEach((created) => {
                cy.makePrivateAdminAPICall("GET", `/api/events/${created.id}`, null, 200);
                cy.makePrivateAdminAPICall("DELETE", `/api/events/${created.id}`, null, 200);
            });
        });
    });
});
