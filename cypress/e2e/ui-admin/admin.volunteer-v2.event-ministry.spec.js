/// <reference types="cypress" />

/**
 * Volunteer v2 — the ministry field on the event editor, and the Volunteers
 * card on the event view (#9713, design §2.16 item 10, §3.5, §6.3).
 *
 * Three surfaces, all of them user-visible:
 *
 *   1. `/event/editor/{id}` — the ministry select rendered beside
 *      `#linkedGroupSelect` inside the "Show more options" collapse. The same
 *      renderer (`webpack/event-form.js`) powers the calendar modal, so the
 *      modal gets it for free and is not separately asserted here.
 *   2. `/event/view/{id}` — the read-only **Volunteers** card listing the V2
 *      occurrences linked to this event, with their gap counts and a link to
 *      `/ministries/occurrences/{id}`.
 *   3. `/ministries/occurrences/{id}` — the link back, now carrying the event's
 *      title and location rather than a bare "Times come from this event".
 *
 * §2.16 does **not** specify a visible calendar badge — only the
 * `extendedProps` the feed carries — so there is deliberately nothing asserted
 * on `/event/calendar` here. `private.volunteer.event-ministry.spec.js` asserts
 * the feed itself.
 *
 * Order inside every hook is API setup → freshAdminLogin() → cy.visit(),
 * because cy.request() rotates the PHP session cookie (cypress-testing.md).
 * Fixtures are removed in `before` as well as `after`.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const VOLUNTEER_URL = "/api/volunteer";

const PREFIX = "UI9713";
const MINISTRY_NAME = `${PREFIX} Sound Booth`;
const OTHER_MINISTRY_NAME = `${PREFIX} Coffee Bar`;
const POSITION_NAME = `${PREFIX} Audio Engineer`;
const EVENT_TITLE = `${PREFIX} Linked Service`;
const PLAIN_EVENT_TITLE = `${PREFIX} Plain Event`;

const CHURCH_SERVICE_TYPE = 1;
const PUBLIC_CALENDAR = 1;

let ministryId = 0;
let otherMinistryId = 0;
/**
 * The ministry's own calendar (#9869). Every ministry is created with one, and the
 * editor pre-pins it when the event is given that ministry.
 */
let ministryCalendarId = 0;
/** The team the ministry was created with; positions and schedules both name it. */
let teamId = 0;
let positionId = 0;
let scheduleId = 0;
let occurrenceId = 0;
let linkedEventId = 0;
let plainEventId = 0;

// Local helper — NOT a cy.* command (cypress-testing.md).
function freshAdminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.env("admin.username"));
    cy.get("input[name=Password]").type(
        Cypress.env("admin.password") + "{enter}",
    );
    cy.url().should("not.include", "/session/begin");
}

function setVersion(value) {
    cy.makePrivateAdminAPICall("POST", SETTING_URL, { value }, 200);
}

function isoDate(offsetDays) {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + offsetDays);
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function daysToNext(dow) {
    const today = new Date().getDay();
    return ((dow - today + 7) % 7) || 7;
}

function deleteFixtures() {
    cy.makePrivateAdminAPICall(
        "GET",
        `${VOLUNTEER_URL}/ministries`,
        null,
        [200, 403],
    ).then((resp) => {
        if (resp.status !== 200) {
            return;
        }
        const mine = (resp.body.ministries ?? []).filter((m) =>
            m.name.startsWith(PREFIX),
        );
        for (const m of mine) {
            cy.makePrivateAdminAPICall(
                "GET",
                `${VOLUNTEER_URL}/ministries/${m.id}/schedules`,
                null,
                200,
            ).then((sResp) => {
                for (const s of sResp.body.schedules ?? []) {
                    cy.makePrivateAdminAPICall(
                        "DELETE",
                        `${VOLUNTEER_URL}/schedules/${s.id}`,
                        null,
                        [200, 404, 409],
                    );
                }
            });
        }
        for (const m of mine) {
            // An active ministry cannot be deleted (409): deactivate first (2026-09-17 lifecycle rule).
            cy.makePrivateAdminAPICall("POST", `${VOLUNTEER_URL}/ministries/${m.id}`, { active: false }, [200, 404]);
            cy.makePrivateAdminAPICall(
                "DELETE",
                `${VOLUNTEER_URL}/ministries/${m.id}`,
                null,
                [200, 404, 409],
            );
        }
    });

    // The events this spec owns, by title.
    cy.dbQuery(
        `DELETE ce FROM calendar_events ce
           JOIN events_event e ON e.event_id = ce.event_id
          WHERE e.event_title LIKE ?`,
        [`${PREFIX}%`],
    );
    cy.dbQuery(`DELETE FROM events_event WHERE event_title LIKE ?`, [
        `${PREFIX}%`,
    ]);
}

function buildFixtures() {
    cy.makePrivateAdminAPICall(
        "POST",
        `${VOLUNTEER_URL}/ministries`,
        { name: MINISTRY_NAME, description: "#9713 UI fixture" },
        [200, 201],
    ).then((resp) => {
        ministryId = resp.body.ministry.id;
        ministryCalendarId = resp.body.calendarId;

        // A ministry is created with one team, and a position always belongs to a
        // team, so the fixture reads the team it was given rather than inventing one.
        cy.makePrivateAdminAPICall(
            "GET",
            `${VOLUNTEER_URL}/ministries/${ministryId}`,
            null,
            200,
        ).then((detail) => {
            teamId = detail.body.teams[0].id;

            cy.makePrivateAdminAPICall(
                "POST",
                `${VOLUNTEER_URL}/ministries/${ministryId}/positions`,
                { name: POSITION_NAME, description: "#9713 UI fixture", teamId },
                [200, 201],
            ).then((pResp) => {
                positionId = pResp.body.position.id;
            });
        });
    });

    cy.makePrivateAdminAPICall(
        "POST",
        `${VOLUNTEER_URL}/ministries`,
        { name: OTHER_MINISTRY_NAME, description: "#9713 UI fixture" },
        [200, 201],
    ).then((resp) => {
        otherMinistryId = resp.body.ministry.id;
    });

    const first = daysToNext(0);

    cy.makePrivateAdminAPICall(
        "POST",
        "/api/events/repeat",
        {
            Title: EVENT_TITLE,
            Type: CHURCH_SERVICE_TYPE,
            StartTime: "10:30",
            EndTime: "11:45",
            RecurType: "weekly",
            RecurDOW: "Sunday",
            RangeStart: isoDate(first),
            RangeEnd: isoDate(first + 7),
            PinnedCalendars: [PUBLIC_CALENDAR],
        },
        200,
    );

    cy.makePrivateAdminAPICall(
        "POST",
        "/api/events",
        {
            Title: PLAIN_EVENT_TITLE,
            Type: CHURCH_SERVICE_TYPE,
            Start: `${isoDate(20)}T19:00:00`,
            End: `${isoDate(20)}T20:00:00`,
            PinnedCalendars: [PUBLIC_CALENDAR],
        },
        200,
    );

    cy.dbQuery(
        `SELECT event_id FROM events_event WHERE event_title = ? ORDER BY event_id DESC LIMIT 1`,
        [PLAIN_EVENT_TITLE],
    ).then((r) => {
        plainEventId = r.rows[0].event_id;
    });

    cy.then(() => {
        cy.makePrivateAdminAPICall(
            "POST",
            `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`,
            {
                name: `${PREFIX} Sunday`,
                // A schedule always names a team (D18).
                teamId,
                linkMode: "event_type",
                eventTypeId: CHURCH_SERVICE_TYPE,
                titleFilter: EVENT_TITLE,
                windowStart: isoDate(0),
            },
            201,
        ).then((resp) => {
            scheduleId = resp.body.schedule.id;

            cy.makePrivateAdminAPICall(
                "POST",
                `${VOLUNTEER_URL}/schedules/${scheduleId}/requirements`,
                { positionId, minCount: 2 },
                [200, 201],
            );

            cy.makePrivateAdminAPICall(
                "POST",
                `${VOLUNTEER_URL}/schedules/${scheduleId}/generate`,
                { through: isoDate(first + 7) },
                200,
            );

            cy.makePrivateAdminAPICall(
                "GET",
                `${VOLUNTEER_URL}/occurrences?scheduleId=${scheduleId}&from=${isoDate(0)}&to=${isoDate(first + 14)}`,
                null,
                200,
            ).then((oResp) => {
                const linked = (oResp.body.occurrences ?? []).filter(
                    (o) => o.eventId,
                );
                expect(linked.length, "linked occurrences exist").to.be.greaterThan(
                    0,
                );
                occurrenceId = linked[0].id;
                linkedEventId = linked[0].eventId;
            });
        });
    });
}

describe("Volunteer v2 — event ministry field and Volunteers card (#9713)", () => {
    before(() => {
        setVersion("v2");
        deleteFixtures();
        buildFixtures();
    });

    after(() => {
        deleteFixtures();
        setVersion("v1");
    });

    describe("the ministry select on the event editor (§2.16 item 10)", () => {
        it("offers every ministry an administrator manages, and saves the choice", () => {
            freshAdminLogin();
            cy.visit(`/event/editor/${plainEventId}`);

            cy.get("#eventAdvancedFields", { timeout: 15000 }).should("exist");
            cy.get('[data-bs-target="#eventAdvancedFields"]').click();

            cy.get("#eventMinistrySelect", { timeout: 10000 })
                .should("be.visible")
                .find("option")
                .then(($opts) => {
                    const labels = [...$opts].map((o) => o.textContent.trim());
                    expect(labels).to.include(MINISTRY_NAME);
                    expect(labels).to.include(OTHER_MINISTRY_NAME);
                });

            cy.get("#eventMinistrySelect").select(String(ministryId));
            cy.get("#event-editor-save").click();

            cy.url().should("not.include", "/event/editor");

            // Reload the editor: the stored value comes back selected.
            cy.visit(`/event/editor/${plainEventId}`);
            cy.get('[data-bs-target="#eventAdvancedFields"]', {
                timeout: 15000,
            }).click();
            cy.get("#eventMinistrySelect", { timeout: 10000 }).should(
                "have.value",
                String(ministryId),
            );
        });

        it("offers an explicit 'no ministry' choice that clears the field", () => {
            freshAdminLogin();
            cy.visit(`/event/editor/${plainEventId}`);
            cy.get('[data-bs-target="#eventAdvancedFields"]', {
                timeout: 15000,
            }).click();

            cy.get("#eventMinistrySelect", { timeout: 10000 }).select("0");
            cy.get("#event-editor-save").click();
            cy.url().should("not.include", "/event/editor");

            cy.dbQuery(
                `SELECT event_ministry_id FROM events_event WHERE event_id = ?`,
                [plainEventId],
            ).then((r) => {
                expect(r.rows[0].event_ministry_id).to.eq(null);
            });
        });

        // #9869: choosing a ministry also pre-pins that ministry's own calendar, because
        // a coordinator without Add Events may pin there and nowhere else.
        it("pre-pins the ministry's own calendar when a ministry is chosen", () => {
            // Reset the event to one church pin and no ministry: the two tests above
            // leave it in whatever state they ended in, and this one is about what the
            // editor ADDS. API setup before the login — cy.request() rotates the cookie.
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/events/${plainEventId}`,
                {
                    Title: PLAIN_EVENT_TITLE,
                    Type: CHURCH_SERVICE_TYPE,
                    Start: `${isoDate(20)}T19:00:00`,
                    End: `${isoDate(20)}T20:00:00`,
                    MinistryId: null,
                    PinnedCalendars: [PUBLIC_CALENDAR],
                },
                200,
            );

            freshAdminLogin();
            cy.visit(`/event/editor/${plainEventId}`);
            cy.get('[data-bs-target="#eventAdvancedFields"]', {
                timeout: 15000,
            }).click();

            // Before: only the church calendar the fixture pinned.
            cy.get("#pinnedCalendarsSelect", { timeout: 10000 })
                .parent()
                .find(".item")
                .should("not.contain", MINISTRY_NAME);

            cy.get("#eventMinistrySelect", { timeout: 10000 }).select(
                String(ministryId),
            );

            // After: the ministry's calendar has joined the list, and the church
            // calendar the user already chose is still there — the pre-pin adds, it
            // never replaces.
            cy.get("#pinnedCalendarsSelect")
                .parent()
                .find(".item")
                .should("contain", MINISTRY_NAME);

            cy.get("#event-editor-save").click();
            cy.url().should("not.include", "/event/editor");

            cy.dbQuery(
                `SELECT calendar_id FROM calendar_events WHERE event_id = ?`,
                [plainEventId],
            ).then((r) => {
                const pinned = r.rows.map((row) => Number(row.calendar_id));
                expect(pinned, "the ministry calendar was saved").to.include(
                    ministryCalendarId,
                );
                expect(pinned, "the church calendar survived").to.include(
                    PUBLIC_CALENDAR,
                );
            });

            // Put the event back the way the other tests expect it.
            cy.visit(`/event/editor/${plainEventId}`);
            cy.get('[data-bs-target="#eventAdvancedFields"]', {
                timeout: 15000,
            }).click();
            cy.get("#eventMinistrySelect", { timeout: 10000 }).select("0");
            cy.get("#event-editor-save").click();
            cy.url().should("not.include", "/event/editor");
        });

        it("is not rendered at all while the rollout flag is v1", () => {
            setVersion("v1");
            freshAdminLogin();
            cy.visit(`/event/editor/${plainEventId}`);
            cy.get('[data-bs-target="#eventAdvancedFields"]', {
                timeout: 15000,
            }).click();
            cy.get("#linkedGroupSelect").should("be.visible");
            cy.get("#eventMinistryField").should("not.be.visible");
            setVersion("v2");
        });
    });

    describe("the Volunteers card on the event view (§3.5)", () => {
        it("lists the linked occurrence with its gap count and links to it", () => {
            freshAdminLogin();
            cy.visit(`/event/view/${linkedEventId}`);

            cy.get("#event-volunteers-card", { timeout: 15000 }).should(
                "be.visible",
            );
            cy.get("#event-volunteers-card").should("contain", MINISTRY_NAME);
            // minCount 2, nobody assigned.
            cy.get("#event-volunteers-card").should("contain", "2");
            cy.get("#event-volunteers-card")
                .find(`a[href*="/ministries/occurrences/${occurrenceId}"]`)
                .should("exist");
        });

        it("is absent on an event with no volunteer occurrences", () => {
            freshAdminLogin();
            cy.visit(`/event/view/${plainEventId}`);
            cy.get(".card", { timeout: 15000 }).should("exist");
            cy.get("#event-volunteers-card").should("not.exist");
        });

        it("is absent while the rollout flag is v1", () => {
            setVersion("v1");
            freshAdminLogin();
            cy.visit(`/event/view/${linkedEventId}`);
            cy.get(".card", { timeout: 15000 }).should("exist");
            cy.get("#event-volunteers-card").should("not.exist");
            setVersion("v2");
        });
    });

    describe("the occurrence page's link back to the event (§3.5)", () => {
        it("names the event it takes its times from", () => {
            freshAdminLogin();
            cy.visit(`/ministries/occurrences/${occurrenceId}`);

            cy.get("#occurrence-event-link", { timeout: 15000 })
                .should("be.visible")
                .should("have.attr", "href")
                .and("include", `/event/view/${linkedEventId}`);

            cy.get("#occurrence-event").should("contain", EVENT_TITLE);
        });
    });
});
