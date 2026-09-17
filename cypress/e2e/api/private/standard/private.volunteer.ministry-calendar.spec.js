/// <reference types="cypress" />

/**
 * Volunteer v2 — the ministry's own calendar (#9869, deferred from #9866).
 *
 * Epic #8977 / #9701, design `.agents/skills/churchcrm/member-portal-design.md` §5.3
 * (the "Scope split between this epic and the Volunteer v2 branch" table) and
 * `volunteer-v2-design.md` D19, §2.16.
 *
 * #9866 shipped the half that stands without `volunteer_ministry_vmin`: the
 * `calendars.ministry_id` column, the "Ministry Calendars" heading, `aPortalCalendars`
 * and the portal page. This spec covers the half that needed the volunteer schema:
 *
 *   1. the foreign key to `volunteer_ministry_vmin`, ON DELETE SET NULL;
 *   2. `VolunteerSetupService::createMinistry()` creating the calendar in the same
 *      transaction as the ministry, renaming it with the ministry and deleting it
 *      with the ministry — the D19 pool-Group lifecycle, applied to a calendar;
 *   3. the pin exception: a coordinator WITHOUT Add Events may pin an event to their
 *      own ministry's calendar and to no other.
 *
 * Personas (seed.sql):
 *
 *   person 1    `admin.api.key`   administrator and global volunteer manager
 *   person 3    `user.api.key`    tony.wade — NO bAddEvent; given a ministry scope here
 *
 * The ministry is created through the REAL API rather than raw SQL, because the whole
 * point of half of these assertions is what the service does on the way in. Cleanup runs
 * in `before` as well as `after`: an `after` hook does not run when the runner crashes
 * mid-spec, and a leftover calendar would survive as an unowned church calendar.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const ADMIN_KEY = "admin.api.key";
const COORDINATOR_KEY = "user.api.key";

const PERSON_COORDINATOR = 3; // tony.wade — no bAddEvent in the seed

const CHURCH_SERVICE_TYPE = 1; // seed.sql — "Church Service"
const PUBLIC_CALENDAR = 1; // seed.sql — a church calendar, owned by nobody

const PREFIX = "MINCAL9869";

let originalVersion = "v1";
let ministryA = 0;
let ministryB = 0;
let calendarA = 0;
let calendarB = 0;

// ── helpers (local functions, NOT cy.* commands — cypress-testing.md) ───────

function dbOk(sql, params = []) {
    return cy.dbQuery(sql, params).then((result) => {
        if (result.error !== null) {
            throw new Error(
                `Unexpected SQL failure.\n  SQL: ${sql}\n  ${result.error.code}: ${result.error.message}`,
            );
        }
        return result.rows;
    });
}

function api(key, method, url, body, expectedStatus = 200) {
    return cy.makePrivateAPICall(
        Cypress.env(key),
        method,
        url,
        body,
        expectedStatus,
    );
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

function eventBody(overrides = {}) {
    return {
        Title: `${PREFIX} Ad-hoc`,
        Type: CHURCH_SERVICE_TYPE,
        Start: `${isoDate(20)}T09:00:00`,
        End: `${isoDate(20)}T10:30:00`,
        PinnedCalendars: [PUBLIC_CALENDAR],
        ...overrides,
    };
}

/** Create a ministry through the API and resolve with the whole response body. */
function createMinistryViaApi(name) {
    return api(
        ADMIN_KEY,
        "POST",
        "/api/volunteer/ministries",
        { name: `${PREFIX} ${name}`, description: "ministry calendar fixture" },
        201,
    ).then((resp) => resp.body);
}

function cleanupFixtures() {
    dbOk(
        `DELETE FROM events_event WHERE event_title LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(
        `DELETE ce FROM calendar_events ce
           JOIN calendars c ON c.calendar_id = ce.calendar_id
          WHERE c.name LIKE ?`,
        [`${PREFIX}%`],
    );
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
    dbOk(`DELETE FROM volunteer_team_vtem WHERE vtem_Name LIKE ?`, [
        `${PREFIX}%`,
    ]);
    dbOk(`DELETE FROM group_grp WHERE grp_Name LIKE ?`, [`${PREFIX}%`]);
    // Calendars BEFORE ministries: the FK is ON DELETE SET NULL, so one left behind
    // would survive as an unowned church calendar and leak into the next run.
    dbOk(`DELETE FROM calendars WHERE name LIKE ?`, [`${PREFIX}%`]);
    dbOk(`DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?`, [
        `${PREFIX}%`,
    ]);
}

// ── suite ──────────────────────────────────────────────────────────────────

before(() => {
    cy.makePrivateAdminAPICall("GET", SETTING_URL, null, 200).then((resp) => {
        originalVersion = resp.body.value ?? resp.body.data ?? "v1";
    });
    setVersion("v2");

    cleanupFixtures();

    createMinistryViaApi("Ministry A").then((body) => {
        ministryA = body.ministry.id;
        calendarA = body.calendarId;
    });
    createMinistryViaApi("Ministry B").then((body) => {
        ministryB = body.ministry.id;
        calendarB = body.calendarId;
    });

    cy.then(() => {
        // Person 3 coordinates ministry A only, and holds no bAddEvent.
        api(
            ADMIN_KEY,
            "POST",
            "/api/volunteer/scopes",
            {
                personId: PERSON_COORDINATOR,
                scopeType: "ministry",
                scopeId: ministryA,
            },
            [200, 201],
        );
    });
});

after(() => {
    cleanupFixtures();
    setVersion(originalVersion);
});

describe("Volunteer v2 — the ministry calendar (#9869)", () => {
    describe("the schema", () => {
        it("calendars.ministry_id carries an ON DELETE SET NULL FK to volunteer_ministry_vmin", () => {
            dbOk(
                `SELECT rc.DELETE_RULE, kcu.REFERENCED_TABLE_NAME, kcu.REFERENCED_COLUMN_NAME
                   FROM information_schema.REFERENTIAL_CONSTRAINTS rc
                   JOIN information_schema.KEY_COLUMN_USAGE kcu
                     ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
                    AND kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
                  WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
                    AND rc.TABLE_NAME = 'calendars'
                    AND kcu.COLUMN_NAME = 'ministry_id'`,
            ).then((rows) => {
                expect(rows.length, "the constraint exists").to.eq(1);
                expect(rows[0].REFERENCED_TABLE_NAME).to.eq(
                    "volunteer_ministry_vmin",
                );
                expect(rows[0].REFERENCED_COLUMN_NAME).to.eq("vmin_ID");
                expect(rows[0].DELETE_RULE).to.eq("SET NULL");
            });
        });

        it("a ministry deleted behind the service's back nulls the link, never the calendar", () => {
            let doomedMinistry = 0;
            let doomedCalendar = 0;

            dbOk(
                `INSERT INTO volunteer_ministry_vmin
                    (vmin_Name, vmin_Description, vmin_Active, vmin_CreatedDate)
                 VALUES (?, 'ministry calendar FK fixture', 1, NOW())`,
                [`${PREFIX} FK Ministry`],
            )
                .then((rows) => {
                    doomedMinistry = rows.insertId;
                    return dbOk(
                        `INSERT INTO calendars (name, foregroundColor, backgroundColor, ministry_id)
                         VALUES (?, 'FFFFFF', '2E7D32', ?)`,
                        [`${PREFIX} FK Calendar`, doomedMinistry],
                    );
                })
                .then((rows) => {
                    doomedCalendar = rows.insertId;
                    return dbOk(
                        `DELETE FROM volunteer_ministry_vmin WHERE vmin_ID = ?`,
                        [doomedMinistry],
                    );
                })
                .then(() =>
                    dbOk(
                        `SELECT ministry_id FROM calendars WHERE calendar_id = ?`,
                        [doomedCalendar],
                    ),
                )
                .then((rows) => {
                    expect(rows.length, "the calendar survived").to.eq(1);
                    expect(rows[0].ministry_id, "the link was nulled").to.eq(
                        null,
                    );
                });
        });
    });

    describe("the lifecycle (§5.3, the D19 shape)", () => {
        it("creating a ministry creates its calendar, named after it and owned by it", () => {
            expect(calendarA, "the create response names the calendar").to.be.a(
                "number",
            );
            expect(calendarA).to.be.greaterThan(0);

            dbOk(
                `SELECT name, ministry_id, backgroundColor, foregroundColor, accesstoken
                   FROM calendars WHERE calendar_id = ?`,
                [calendarA],
            ).then((rows) => {
                expect(rows.length).to.eq(1);
                expect(rows[0].name).to.eq(`${PREFIX} Ministry A`);
                expect(Number(rows[0].ministry_id)).to.eq(ministryA);
                // A colour from the palette, not an empty column: the swatch in the
                // portal legend has to mean something on day one.
                expect(rows[0].backgroundColor).to.match(/^[0-9A-Fa-f]{6}$/);
                expect(rows[0].foregroundColor).to.match(/^[0-9A-Fa-f]{6}$/);
                // Never published by default — that is an administrator's decision.
                expect(rows[0].accesstoken).to.eq(null);
            });
        });

        it("gives two ministries two different calendars", () => {
            expect(calendarB).to.be.greaterThan(0);
            expect(calendarB, "not the same row").to.not.eq(calendarA);

            dbOk(
                `SELECT COUNT(*) AS n FROM calendars WHERE ministry_id IN (?, ?)`,
                [ministryA, ministryB],
            ).then((rows) => {
                expect(Number(rows[0].n)).to.eq(2);
            });
        });

        it("renaming the ministry renames its calendar", () => {
            api(
                ADMIN_KEY,
                "POST",
                `/api/volunteer/ministries/${ministryA}`,
                { name: `${PREFIX} Ministry A Renamed` },
                200,
            );

            dbOk(`SELECT name FROM calendars WHERE calendar_id = ?`, [
                calendarA,
            ]).then((rows) => {
                expect(rows[0].name).to.eq(`${PREFIX} Ministry A Renamed`);
            });

            // Put it back so the pin tests below read as they were written.
            api(
                ADMIN_KEY,
                "POST",
                `/api/volunteer/ministries/${ministryA}`,
                { name: `${PREFIX} Ministry A` },
                200,
            );
        });

        it("deleting the ministry deletes its calendar", () => {
            let doomedMinistry = 0;
            let doomedCalendar = 0;

            createMinistryViaApi("Disposable").then((body) => {
                doomedMinistry = body.ministry.id;
                doomedCalendar = body.calendarId;
                expect(doomedCalendar).to.be.greaterThan(0);

                // An active ministry cannot be deleted (409): deactivate first (2026-09-17 lifecycle rule).
                api(ADMIN_KEY, "POST", `/api/volunteer/ministries/${doomedMinistry}`, { active: false }, 200);
                api(
                    ADMIN_KEY,
                    "DELETE",
                    `/api/volunteer/ministries/${doomedMinistry}`,
                    null,
                    200,
                );

                dbOk(
                    `SELECT calendar_id FROM calendars WHERE calendar_id = ?`,
                    [doomedCalendar],
                ).then((rows) => {
                    expect(
                        rows.length,
                        "the calendar went with the ministry, not SET NULL",
                    ).to.eq(0);
                });
            });
        });
    });

    describe("a coordinator without Add Events (§5.3, the pin exception)", () => {
        it("pins an event to their OWN ministry's calendar", () => {
            api(
                COORDINATOR_KEY,
                "POST",
                "/api/events",
                eventBody({
                    Title: `${PREFIX} Own Pin`,
                    MinistryId: ministryA,
                    PinnedCalendars: [calendarA],
                }),
                200,
            );

            dbOk(
                `SELECT ce.calendar_id
                   FROM calendar_events ce
                   JOIN events_event e ON e.event_id = ce.event_id
                  WHERE e.event_title = ?`,
                [`${PREFIX} Own Pin`],
            ).then((rows) => {
                expect(rows.length, "the pin was written").to.eq(1);
                expect(Number(rows[0].calendar_id)).to.eq(calendarA);
            });
        });

        it("is refused a pin to a church calendar nobody's ministry owns", () => {
            api(
                COORDINATOR_KEY,
                "POST",
                "/api/events",
                eventBody({
                    Title: `${PREFIX} Church Pin`,
                    MinistryId: ministryA,
                    PinnedCalendars: [PUBLIC_CALENDAR],
                }),
                403,
            );

            dbOk(`SELECT event_id FROM events_event WHERE event_title = ?`, [
                `${PREFIX} Church Pin`,
            ]).then((rows) => {
                expect(rows.length, "nothing was created").to.eq(0);
            });
        });

        it("is refused a pin to another ministry's calendar", () => {
            api(
                COORDINATOR_KEY,
                "POST",
                "/api/events",
                eventBody({
                    Title: `${PREFIX} Foreign Pin`,
                    MinistryId: ministryA,
                    PinnedCalendars: [calendarB],
                }),
                403,
            );

            dbOk(`SELECT event_id FROM events_event WHERE event_title = ?`, [
                `${PREFIX} Foreign Pin`,
            ]).then((rows) => {
                expect(rows.length, "nothing was created").to.eq(0);
            });
        });

        it("is refused a pin added on an UPDATE, and the event keeps its old pins", () => {
            api(
                COORDINATOR_KEY,
                "POST",
                "/api/events",
                eventBody({
                    Title: `${PREFIX} Repin`,
                    MinistryId: ministryA,
                    PinnedCalendars: [calendarA],
                }),
                200,
            );

            dbOk(`SELECT event_id FROM events_event WHERE event_title = ?`, [
                `${PREFIX} Repin`,
            ]).then((rows) => {
                const eventId = rows[0].event_id;

                api(
                    COORDINATOR_KEY,
                    "POST",
                    `/api/events/${eventId}`,
                    eventBody({
                        Title: `${PREFIX} Repin`,
                        MinistryId: ministryA,
                        PinnedCalendars: [calendarA, PUBLIC_CALENDAR],
                    }),
                    403,
                );

                dbOk(
                    `SELECT calendar_id FROM calendar_events WHERE event_id = ?`,
                    [eventId],
                ).then((pins) => {
                    expect(pins.length, "still exactly one pin").to.eq(1);
                    expect(Number(pins[0].calendar_id)).to.eq(calendarA);
                });
            });
        });

        it("may publish their own ministry's calendar but not another's", () => {
            api(
                COORDINATOR_KEY,
                "POST",
                `/api/calendars/${calendarA}/NewAccessToken`,
                null,
                200,
            );
            api(
                COORDINATOR_KEY,
                "POST",
                `/api/calendars/${calendarB}/NewAccessToken`,
                null,
                403,
            );
            api(
                COORDINATOR_KEY,
                "POST",
                `/api/calendars/${PUBLIC_CALENDAR}/NewAccessToken`,
                null,
                403,
            );

            api(
                COORDINATOR_KEY,
                "DELETE",
                `/api/calendars/${calendarA}/AccessToken`,
                null,
                200,
            );
        });

        it("loses the exception when the rollout flag goes back to v1", () => {
            setVersion("v1");

            api(
                COORDINATOR_KEY,
                "POST",
                "/api/events",
                eventBody({
                    Title: `${PREFIX} V1 Pin`,
                    MinistryId: ministryA,
                    PinnedCalendars: [calendarA],
                }),
                403,
            );

            setVersion("v2");
        });
    });

    describe("an administrator (the global right is unchanged)", () => {
        it("pins to any calendar, ministry-owned or not", () => {
            api(
                ADMIN_KEY,
                "POST",
                "/api/events",
                eventBody({
                    Title: `${PREFIX} Admin Pin`,
                    PinnedCalendars: [PUBLIC_CALENDAR, calendarA, calendarB],
                }),
                200,
            );

            dbOk(
                `SELECT ce.calendar_id
                   FROM calendar_events ce
                   JOIN events_event e ON e.event_id = ce.event_id
                  WHERE e.event_title = ?`,
                [`${PREFIX} Admin Pin`],
            ).then((rows) => {
                expect(rows.length, "all three pins were written").to.eq(3);
            });
        });
    });

    describe("the admin Calendars tab", () => {
        it("reports the ministry calendars with their ministry id", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/admin/api/member-portal/calendars",
                null,
                200,
            ).then((resp) => {
                const mine = resp.body.calendars.find(
                    (c) => c.type === "calendar" && c.id === calendarA,
                );
                expect(mine, "ministry A's calendar is listed").to.exist;
                expect(mine.ministryId).to.eq(ministryA);

                const church = resp.body.calendars.find(
                    (c) => c.type === "calendar" && c.id === PUBLIC_CALENDAR,
                );
                expect(church.ministryId, "a church calendar has none").to.eq(
                    null,
                );
            });
        });
    });
});
