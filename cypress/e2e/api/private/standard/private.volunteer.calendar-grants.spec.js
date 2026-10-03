/// <reference types="cypress" />

/**
 * Volunteer v2 D25 — administrators open church calendars to ministries.
 * Design: volunteer-v2-design.md §0.8 D25, §2.16, §4.6; member-portal-design.md §5.3.
 *
 * Personas (seed.sql): person 1 `admin.api.key` (administrator); person 3 `user.api.key`
 * (tony.wade, no bAddEvent), made coordinator of ministries A and C here, never of B.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const ADMIN_KEY = "admin.api.key";
const COORDINATOR_KEY = "user.api.key";

const PERSON_COORDINATOR = 3;
const CHURCH_SERVICE_TYPE = 1;
const PUBLIC_CALENDAR = 1;

const PREFIX = "VCALGRANT";

let originalVersion = "v1";
const ministry = {};
const ministryCalendar = {};
let bibleClasses = 0;

function dbOk(sql, params = []) {
    return cy.dbQuery(sql, params).then((result) => {
        if (result.error !== null) {
            throw new Error(`Unexpected SQL failure.\n  SQL: ${sql}\n  ${result.error.code}: ${result.error.message}`);
        }
        return result.rows;
    });
}

function api(key, method, url, body, expectedStatus = 200) {
    return cy.makePrivateAPICall(Cypress.testEnv(key), method, url, body, expectedStatus);
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

function eventBody(title, overrides = {}) {
    return {
        Title: `${PREFIX} ${title}`,
        Type: CHURCH_SERVICE_TYPE,
        Start: `${isoDate(20)}T09:30:00`,
        End: `${isoDate(20)}T10:15:00`,
        PinnedCalendars: [],
        ...overrides,
    };
}

function eventIdByTitle(title) {
    return dbOk(`SELECT event_id FROM events_event WHERE event_title = ?`, [`${PREFIX} ${title}`]).then(
        (rows) => rows.map((r) => r.event_id),
    );
}

function pinsOf(eventId) {
    return dbOk(`SELECT calendar_id FROM calendar_events WHERE event_id = ? ORDER BY calendar_id`, [eventId]).then(
        (rows) => rows.map((r) => Number(r.calendar_id)),
    );
}

function grantRows(where, params) {
    return dbOk(`SELECT vcal_calendar_id, vcal_vmin_ID FROM volunteer_calendar_vcal WHERE ${where}`, params);
}

function setGrants(calendarId, ministryIds, key = ADMIN_KEY, expectedStatus = 200) {
    return api(key, "PUT", `/api/calendars/${calendarId}/ministries`, { ministryIds }, expectedStatus);
}

function createMinistry(label) {
    return api(
        ADMIN_KEY,
        "POST",
        "/api/ministries/ministries",
        { name: `${PREFIX} ${label}`, description: "calendar grant fixture" },
        201,
    ).then((resp) => resp.body);
}

function createChurchCalendar(label) {
    return api(ADMIN_KEY, "POST", "/api/calendars", {
        Name: `${PREFIX} ${label}`,
        ForegroundColor: "#FFFFFF",
        BackgroundColor: "#1565C0",
    }).then((resp) => resp.body.Id);
}

function cleanupFixtures() {
    dbOk(
        `DELETE ce FROM calendar_events ce
           JOIN events_event e ON e.event_id = ce.event_id
          WHERE e.event_title LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(`DELETE FROM events_event WHERE event_title LIKE ?`, [`${PREFIX}%`]);
    dbOk(
        `DELETE vscp FROM volunteer_scope_vscp vscp
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vscp.vscp_ScopeId
          WHERE vscp.vscp_ScopeType = 'ministry' AND vmin.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(
        `DELETE p2g2r FROM person2group2role_p2g2r p2g2r
           JOIN group_grp g ON g.grp_ID = p2g2r.p2g2r_grp_ID
          WHERE g.grp_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(`DELETE FROM group_grp WHERE grp_Name LIKE ?`, [`${PREFIX}%`]);
    dbOk(`DELETE FROM calendars WHERE name LIKE ?`, [`${PREFIX}%`]);
    dbOk(`DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?`, [`${PREFIX}%`]);
}

before(() => {
    cy.rememberTestEnv(["admin.api.key", "user.api.key"]);
});

describe("Volunteer v2 D25 — administrators open church calendars to ministries", () => {
    before(() => {
        cy.makePrivateAdminAPICall("GET", SETTING_URL, null, 200).then((resp) => {
            originalVersion = resp.body.value ?? resp.body.data ?? "v1";
        });
        setVersion("v2");
        cleanupFixtures();

        for (const key of ["A", "B", "C"]) {
            createMinistry(`Ministry ${key}`).then((body) => {
                ministry[key] = body.ministry.id;
                ministryCalendar[key] = body.calendarId;
            });
        }
        createChurchCalendar("Bible Classes").then((id) => {
            bibleClasses = id;
        });

        cy.then(() => {
            for (const key of ["A", "C"]) {
                api(
                    ADMIN_KEY,
                    "POST",
                    "/api/ministries/scopes",
                    { personId: PERSON_COORDINATOR, scopeType: "ministry", scopeId: ministry[key] },
                    [200, 201],
                );
            }
        });
    });

    after(() => {
        cleanupFixtures();
        setVersion(originalVersion);
    });

    describe("Volunteer v2 D25 — calendar grants", () => {
        describe("reading and replacing a calendar's grants", () => {
            it("is refused to a coordinator without Add Events", () => {
                api(COORDINATOR_KEY, "GET", `/api/calendars/${bibleClasses}/ministries`, null, 403);
                setGrants(bibleClasses, [ministry.A], COORDINATOR_KEY, 403);
                grantRows("vcal_calendar_id = ?", [bibleClasses]).then((rows) => {
                    expect(rows).to.have.length(0);
                });
            });

            it("lets an administrator replace the grants and read them back", () => {
                setGrants(bibleClasses, [ministry.A, ministry.B]).then((resp) => {
                    expect(resp.body.calendarId).to.eq(bibleClasses);
                    expect(resp.body.ministries.map((m) => m.id).sort()).to.deep.eq([ministry.A, ministry.B].sort());
                    expect(resp.body.ministries[0]).to.have.all.keys("id", "name", "active");
                });

                setGrants(bibleClasses, [ministry.A]);
                api(ADMIN_KEY, "GET", `/api/calendars/${bibleClasses}/ministries`).then((resp) => {
                    expect(resp.body.ministries).to.have.length(1);
                    expect(resp.body.ministries[0].id).to.eq(ministry.A);
                    expect(resp.body.ministries[0].name).to.eq(`${PREFIX} Ministry A`);
                    expect(resp.body.ministries[0].active).to.eq(true);
                });
            });

            it("rejects a malformed list, an unknown ministry and an unknown calendar", () => {
                api(ADMIN_KEY, "PUT", `/api/calendars/${bibleClasses}/ministries`, {}, 400);
                setGrants(bibleClasses, [0], ADMIN_KEY, 400);
                setGrants(bibleClasses, ["abc"], ADMIN_KEY, 400);
                setGrants(bibleClasses, [99999999], ADMIN_KEY, 400);
                setGrants(99999999, [ministry.A], ADMIN_KEY, 404);
                grantRows("vcal_calendar_id = ?", [bibleClasses]).then((rows) => {
                    expect(rows.map((r) => r.vcal_vmin_ID), "a refused write changed nothing").to.deep.eq([ministry.A]);
                });
            });

            it("refuses to open a ministry's own calendar to other ministries", () => {
                setGrants(ministryCalendar.A, [ministry.B], ADMIN_KEY, 409);
            });

            it("is closed while the rollout flag is v1", () => {
                setVersion("v1");
                api(ADMIN_KEY, "GET", `/api/calendars/${bibleClasses}/ministries`, null, 403);
                setVersion("v2");
            });
        });

        describe("a coordinator without Add Events pinning to a granted calendar", () => {
            it("pins their own ministry's event to it", () => {
                api(
                    COORDINATOR_KEY,
                    "POST",
                    "/api/events",
                    eventBody("Granted Pin", { MinistryId: ministry.A, PinnedCalendars: [bibleClasses, ministryCalendar.A] }),
                    200,
                );
                eventIdByTitle("Granted Pin").then((ids) => {
                    expect(ids).to.have.length(1);
                    pinsOf(ids[0]).then((pins) => {
                        expect(pins).to.deep.eq([ministryCalendar.A, bibleClasses].sort((a, b) => a - b));
                    });
                });
            });

            it("is refused a church calendar nobody opened to the ministry", () => {
                api(
                    COORDINATOR_KEY,
                    "POST",
                    "/api/events",
                    eventBody("Ungranted Pin", { MinistryId: ministry.A, PinnedCalendars: [PUBLIC_CALENDAR] }),
                    403,
                );
                eventIdByTitle("Ungranted Pin").then((ids) => expect(ids).to.have.length(0));
            });

            it("is refused for another ministry's event", () => {
                api(
                    COORDINATOR_KEY,
                    "POST",
                    "/api/events",
                    eventBody("Other Ministry Pin", { MinistryId: ministry.B, PinnedCalendars: [bibleClasses] }),
                    403,
                );
                eventIdByTitle("Other Ministry Pin").then((ids) => expect(ids).to.have.length(0));
            });

            it("is refused for an event of a second ministry they run that was not granted the calendar", () => {
                api(
                    COORDINATOR_KEY,
                    "POST",
                    "/api/events",
                    eventBody("Second Ministry Pin", { MinistryId: ministry.C, PinnedCalendars: [bibleClasses] }),
                    403,
                );
                eventIdByTitle("Second Ministry Pin").then((ids) => expect(ids).to.have.length(0));
            });

            it("is refused for an event with no ministry", () => {
                api(
                    COORDINATOR_KEY,
                    "POST",
                    "/api/events",
                    eventBody("No Ministry Pin", { PinnedCalendars: [bibleClasses] }),
                    403,
                );
                eventIdByTitle("No Ministry Pin").then((ids) => expect(ids).to.have.length(0));
            });

            it("cannot carry a granted pin across when moving the event to a ministry without the grant", () => {
                eventIdByTitle("Granted Pin").then(([eventId]) => {
                    api(
                        COORDINATOR_KEY,
                        "POST",
                        `/api/events/${eventId}`,
                        eventBody("Granted Pin", { MinistryId: ministry.C, PinnedCalendars: [bibleClasses] }),
                        403,
                    );
                    dbOk(`SELECT event_ministry_id FROM events_event WHERE event_id = ?`, [eventId]).then((rows) => {
                        expect(rows[0].event_ministry_id).to.eq(ministry.A);
                    });
                });
            });

            it("is offered exactly the calendars the rule allows", () => {
                api(COORDINATOR_KEY, "GET", `/api/calendars/pinnable?ministryId=${ministry.A}`).then((resp) => {
                    expect(resp.body.ministryId).to.eq(ministry.A);
                    expect(resp.body.calendarIds).to.include.members([ministryCalendar.A, bibleClasses]);
                    expect(resp.body.calendarIds).to.not.include.members([PUBLIC_CALENDAR, ministryCalendar.B, ministryCalendar.C]);
                });
                api(COORDINATOR_KEY, "GET", `/api/calendars/pinnable?ministryId=${ministry.C}`).then((resp) => {
                    expect(resp.body.calendarIds).to.deep.eq([ministryCalendar.C]);
                });
                api(COORDINATOR_KEY, "GET", `/api/calendars/pinnable?ministryId=${ministry.B}`).then((resp) => {
                    expect(resp.body.calendarIds).to.deep.eq([]);
                });
                api(COORDINATOR_KEY, "GET", "/api/calendars/pinnable").then((resp) => {
                    expect(resp.body.ministryId).to.eq(null);
                    expect(resp.body.calendarIds).to.deep.eq([]);
                });
                api(ADMIN_KEY, "GET", "/api/calendars/pinnable").then((resp) => {
                    expect(resp.body.calendarIds).to.include.members([PUBLIC_CALENDAR, bibleClasses, ministryCalendar.B]);
                });
            });
        });

        describe("editing an event whose pins are not all the coordinator's", () => {
            let eventId = 0;

            before(() => {
                api(
                    ADMIN_KEY,
                    "POST",
                    "/api/events",
                    eventBody("Mixed Pins", { MinistryId: ministry.A, PinnedCalendars: [PUBLIC_CALENDAR, bibleClasses] }),
                    200,
                );
                eventIdByTitle("Mixed Pins").then((ids) => {
                    eventId = ids[0];
                });
            });

            it("keeps a pin somebody with Add Events made through the coordinator's edit", () => {
                api(
                    COORDINATOR_KEY,
                    "POST",
                    `/api/events/${eventId}`,
                    eventBody("Mixed Pins", { MinistryId: ministry.A, PinnedCalendars: [PUBLIC_CALENDAR, bibleClasses] }),
                    200,
                );
                pinsOf(eventId).then((pins) => expect(pins).to.deep.eq([PUBLIC_CALENDAR, bibleClasses]));
            });

            it("refuses to remove that pin", () => {
                api(
                    COORDINATOR_KEY,
                    "POST",
                    `/api/events/${eventId}`,
                    eventBody("Mixed Pins", { MinistryId: ministry.A, PinnedCalendars: [bibleClasses] }),
                    403,
                );
                pinsOf(eventId).then((pins) => expect(pins).to.deep.eq([PUBLIC_CALENDAR, bibleClasses]));
            });

            it("may remove the granted pin", () => {
                api(
                    COORDINATOR_KEY,
                    "POST",
                    `/api/events/${eventId}`,
                    eventBody("Mixed Pins", { MinistryId: ministry.A, PinnedCalendars: [PUBLIC_CALENDAR] }),
                    200,
                );
                pinsOf(eventId).then((pins) => expect(pins).to.deep.eq([PUBLIC_CALENDAR]));
            });

            it("leaves the pins alone when an update does not name them", () => {
                api(ADMIN_KEY, "POST", `/api/events/${eventId}`, { Title: `${PREFIX} Mixed Pins` }, 200);
                pinsOf(eventId).then((pins) => expect(pins).to.deep.eq([PUBLIC_CALENDAR]));
            });
        });

        describe("withdrawing a grant", () => {
            it("makes the same pin 403 again", () => {
                setGrants(bibleClasses, []);
                api(
                    COORDINATOR_KEY,
                    "POST",
                    "/api/events",
                    eventBody("After Withdrawal", { MinistryId: ministry.A, PinnedCalendars: [bibleClasses] }),
                    403,
                );
                eventIdByTitle("After Withdrawal").then((ids) => expect(ids).to.have.length(0));
            });
        });

        describe("the grants go with what they join", () => {
            it("deleting a ministry removes its grants", () => {
                createMinistry("Disposable").then((body) => {
                    const doomed = body.ministry.id;
                    setGrants(bibleClasses, [doomed, ministry.A]);
                    api(ADMIN_KEY, "POST", `/api/ministries/ministries/${doomed}`, { active: false }, 200);
                    api(ADMIN_KEY, "DELETE", `/api/ministries/ministries/${doomed}`, null, 200);
                    grantRows("vcal_vmin_ID = ?", [doomed]).then((rows) => expect(rows).to.have.length(0));
                    grantRows("vcal_calendar_id = ?", [bibleClasses]).then((rows) => {
                        expect(rows.map((r) => r.vcal_vmin_ID), "the other ministry keeps its grant").to.deep.eq([ministry.A]);
                    });
                });
            });

            it("deleting a calendar removes its grants", () => {
                createChurchCalendar("Doomed Calendar").then((doomed) => {
                    setGrants(doomed, [ministry.A]);
                    api(ADMIN_KEY, "DELETE", `/api/calendars/${doomed}`, null, 200);
                    grantRows("vcal_calendar_id = ?", [doomed]).then((rows) => expect(rows).to.have.length(0));
                });
            });
        });
    });
});
