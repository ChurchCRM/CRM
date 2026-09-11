/// <reference types="cypress" />

/**
 * Regression test for #9736 — `events_event` was utf8mb3, so any 4-byte
 * character (every emoji) in an event title made the INSERT fail with
 * SQLSTATE[22007] and POST /api/events answered HTTP 500, leaking the raw
 * INSERT statement.
 *
 * Guards the full round-trip: create with an emoji title, read it back byte
 * for byte, then delete. A 3-byte control character is exercised alongside it
 * so a failure points at the 4-byte case specifically.
 */

const EMOJI_TITLE = "Cypress emoji event 🎂🎉";
const BMP_TITLE = "Cypress BMP event ✓";
const START = "2099-09-20T09:00:00";
const END = "2099-09-20T10:00:00";

describe("API Private Calendar Events — utf8mb4 titles (#9736)", () => {
    /** @type {number[]} */
    const createdIds = [];

    /**
     * Look an event up by exact title through GET /api/events.
     * POST /api/events answers {"success":true} without the new id.
     */
    const findEventIdByTitle = (title) =>
        cy.makePrivateAdminAPICall("GET", "/api/events", null, 200).then((response) => {
            const matches = response.body.Events.filter((event) => event.Title === title);
            expect(matches, `exactly one event titled "${title}"`).to.have.length(1);
            createdIds.push(matches[0].Id);
            return matches[0].Id;
        });

    before(() => {
        // Clear anything an interrupted earlier run left behind, so the
        // "exactly one" lookups below stay deterministic.
        cy.makePrivateAdminAPICall("GET", "/api/events", null, 200).then((response) => {
            response.body.Events.filter(
                (event) => event.Title === EMOJI_TITLE || event.Title === BMP_TITLE,
            ).forEach((event) => {
                cy.request({
                    method: "DELETE",
                    url: `/api/events/${event.Id}`,
                    headers: { "x-api-key": Cypress.env("admin.api.key") },
                    failOnStatusCode: false,
                });
            });
        });
    });

    after(() => {
        // Best-effort cleanup: an id already removed by the delete test answers
        // 404, which is fine here.
        [...new Set(createdIds)].forEach((id) => {
            cy.request({
                method: "DELETE",
                url: `/api/events/${id}`,
                headers: { "x-api-key": Cypress.env("admin.api.key") },
                failOnStatusCode: false,
            });
        });
    });

    it("creates an event whose title contains 4-byte characters", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/events",
            {
                Title: EMOJI_TITLE,
                Type: 1,
                Start: START,
                End: END,
                PinnedCalendars: [1],
            },
            200,
        ).then((response) => {
            expect(response.body.success).to.eq(true);
        });
    });

    it("reads the 4-byte title back unchanged", () => {
        findEventIdByTitle(EMOJI_TITLE).then((id) => {
            cy.makePrivateAdminAPICall("GET", `/api/events/${id}`, null, 200).then((response) => {
                expect(response.body.Title).to.eq(EMOJI_TITLE);
                // Explicitly assert the astral code points survived, not just
                // that the strings compare equal after some lossy substitution.
                expect([...response.body.Title].some((ch) => ch.codePointAt(0) > 0xffff)).to.eq(
                    true,
                    "title should still contain a 4-byte character",
                );
            });
        });
    });

    it("still accepts a 3-byte character title (control)", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/events",
            {
                Title: BMP_TITLE,
                Type: 1,
                Start: START,
                End: END,
                PinnedCalendars: [1],
            },
            200,
        );

        findEventIdByTitle(BMP_TITLE).then((id) => {
            cy.makePrivateAdminAPICall("GET", `/api/events/${id}`, null, 200).then((response) => {
                expect(response.body.Title).to.eq(BMP_TITLE);
            });
        });
    });

    it("deletes the emoji event", () => {
        findEventIdByTitle(EMOJI_TITLE).then((id) => {
            cy.makePrivateAdminAPICall("DELETE", `/api/events/${id}`, null, 200);
            cy.makePrivateAdminAPICall("GET", `/api/events/${id}`, null, 404);
        });
    });
});
