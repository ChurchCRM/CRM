/// <reference types="cypress" />

/**
 * Volunteer v2 — a coordinator may only choose an event's audience from the groups their own
 * ministry owns (#10353).
 *
 * An event's audience decides whose names `/event/view` lists as non-attendees once the event
 * has ended. Group pages need Manage Groups, so letting a ministry coordinator link any group
 * would show them who belongs to groups they cannot open.
 *
 *   without the global AddEvent right → only the event ministry's pool Group, or a Sunday
 *                                        School class linked to one of its teams
 *   with the global AddEvent right    → any group, exactly as before
 *
 * Person 3 (tony.wade) is the coordinator persona: `bAddEvent = FALSE` in the seed, so nothing
 * is revoked. The administrator is the global-right persona.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const ADMIN_KEY = "admin.api.key";
const COORDINATOR_KEY = "user.api.key";

const PERSON_COORDINATOR = 3;
const CHURCH_SERVICE_TYPE = 1;
const PUBLIC_CALENDAR = 1;

const FIXTURE_PREFIX = "EVTAUD10353";

let ministryA = 0;
let ministryB = 0;
let calendarA = 0;
let poolGroupA = 0;
let poolGroupB = 0;
let classGroupA = 0;
let foreignGroup = 0;
let originalVersion = "v1";

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
        Cypress.testEnv(key),
        method,
        url,
        body,
        expectedStatus,
    );
}

function isoDate(offsetDays) {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + offsetDays);
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/** A coordinator without Add Events may pin only to their ministry's own calendar. */
function coordinatorBody(title, overrides = {}) {
    return {
        Title: title,
        Type: CHURCH_SERVICE_TYPE,
        Start: `${isoDate(30)}T09:00:00`,
        End: `${isoDate(30)}T10:30:00`,
        PinnedCalendars: [calendarA],
        MinistryId: ministryA,
        ...overrides,
    };
}

function eventIdByTitle(title) {
    return dbOk(
        `SELECT event_id FROM events_event WHERE event_title = ? ORDER BY event_id DESC LIMIT 1`,
        [title],
    ).then((rows) => (rows.length === 0 ? 0 : rows[0].event_id));
}

function audienceOf(eventId) {
    return dbOk(
        `SELECT group_id FROM event_audience WHERE event_id = ? ORDER BY group_id`,
        [eventId],
    ).then((rows) => rows.map((row) => Number(row.group_id)));
}

function createGroup(name) {
    return api(ADMIN_KEY, "POST", "/api/groups/", {
        groupName: name,
        description: "",
    }).then((resp) => Number(resp.body.Id));
}

function createMinistry(suffix) {
    return api(
        ADMIN_KEY,
        "POST",
        "/api/ministries/ministries",
        { name: `${FIXTURE_PREFIX} ${suffix}` },
        201,
    ).then((resp) => ({
        id: Number(resp.body.ministry.id),
        poolGroupId: Number(resp.body.poolGroupId),
        calendarId: Number(resp.body.calendarId),
    }));
}

function cleanupFixtures() {
    dbOk(
        `DELETE ea FROM event_audience ea
           JOIN events_event e ON e.event_id = ea.event_id
          WHERE e.event_title LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE ce FROM calendar_events ce
           JOIN events_event e ON e.event_id = ce.event_id
          WHERE e.event_title LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(`DELETE FROM events_event WHERE event_title LIKE ?`, [
        `${FIXTURE_PREFIX}%`,
    ]);
    dbOk(
        `UPDATE volunteer_team_vtem vtem
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vtem.vtem_vmin_ID
            SET vtem.vtem_grp_ID = NULL
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(`DELETE FROM volunteer_scope_vscp WHERE vscp_per_ID = ?`, [
        PERSON_COORDINATOR,
    ]);
    dbOk(
        `DELETE grp FROM group_grp grp
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = grp.grp_ministry_id
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(`DELETE FROM group_grp WHERE grp_Name LIKE ?`, [`${FIXTURE_PREFIX}%`]);
    dbOk(`DELETE FROM calendars WHERE name LIKE ?`, [`${FIXTURE_PREFIX}%`]);
    dbOk(`DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?`, [
        `${FIXTURE_PREFIX}%`,
    ]);
}

before(() => {
    cy.rememberTestEnv(["admin.api.key", "user.api.key"]);
    cy.useChurchTimeZone();
});

after(() => {
    cy.useHostTimeZone();
});

describe("Volunteer v2 — a coordinator's choice of event audience (#10353)", () => {
    before(() => {
        cy.makePrivateAdminAPICall("GET", SETTING_URL, null, 200).then(
            (resp) => {
                originalVersion = resp.body.value ?? resp.body.data ?? "v1";
            },
        );
        cy.makePrivateAdminAPICall("POST", SETTING_URL, { value: "v2" }, 200);

        cleanupFixtures();

        createMinistry("Ministry A").then((ministry) => {
            ministryA = ministry.id;
            poolGroupA = ministry.poolGroupId;
            calendarA = ministry.calendarId;
        });
        createMinistry("Ministry B").then((ministry) => {
            ministryB = ministry.id;
            poolGroupB = ministry.poolGroupId;
        });
        createGroup(`${FIXTURE_PREFIX} Foreign group`).then((id) => {
            foreignGroup = id;
        });
        createGroup(`${FIXTURE_PREFIX} Class group`).then((id) => {
            classGroupA = id;
        });

        cy.then(() => {
            dbOk(
                `UPDATE volunteer_team_vtem SET vtem_grp_ID = ? WHERE vtem_vmin_ID = ? LIMIT 1`,
                [classGroupA, ministryA],
            );
            api(
                ADMIN_KEY,
                "POST",
                "/api/ministries/scopes",
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
        cy.makePrivateAdminAPICall(
            "POST",
            SETTING_URL,
            { value: originalVersion },
            200,
        );
    });

    it("refuses a group the ministry does not own, and leaves no event behind", () => {
        const title = `${FIXTURE_PREFIX} foreign group`;

        api(
            COORDINATOR_KEY,
            "POST",
            "/api/events",
            coordinatorBody(title, { LinkedGroupId: foreignGroup }),
            403,
        );

        eventIdByTitle(title).then((id) => {
            expect(id, "no event was created").to.eq(0);
        });
    });

    it("refuses another ministry's pool group", () => {
        api(
            COORDINATOR_KEY,
            "POST",
            "/api/events",
            coordinatorBody(`${FIXTURE_PREFIX} other pool`, {
                LinkedGroupId: poolGroupB,
            }),
            403,
        );
    });

    it("accepts the event ministry's own pool group", () => {
        const title = `${FIXTURE_PREFIX} own pool`;

        api(
            COORDINATOR_KEY,
            "POST",
            "/api/events",
            coordinatorBody(title, { LinkedGroupId: poolGroupA }),
            200,
        );

        eventIdByTitle(title)
            .then((id) => audienceOf(id))
            .then((groups) => {
                expect(groups).to.deep.eq([poolGroupA]);
            });
    });

    it("accepts a class linked to one of the ministry's teams", () => {
        const title = `${FIXTURE_PREFIX} linked class`;

        api(
            COORDINATOR_KEY,
            "POST",
            "/api/events",
            coordinatorBody(title, { LinkedGroupId: classGroupA }),
            200,
        );

        eventIdByTitle(title)
            .then((id) => audienceOf(id))
            .then((groups) => {
                expect(groups).to.deep.eq([classGroupA]);
            });
    });

    describe("changing the audience of an existing event", () => {
        let eventId = 0;
        const title = `${FIXTURE_PREFIX} editable`;

        before(() => {
            api(
                COORDINATOR_KEY,
                "POST",
                "/api/events",
                coordinatorBody(title, { LinkedGroupId: poolGroupA }),
                200,
            );
            eventIdByTitle(title).then((id) => {
                eventId = id;
            });
        });

        it("lets the coordinator send the audience that is already there", () => {
            api(
                COORDINATOR_KEY,
                "POST",
                `/api/events/${eventId}`,
                { Title: title, LinkedGroupId: poolGroupA },
                200,
            );

            audienceOf(eventId).then((groups) => {
                expect(groups).to.deep.eq([poolGroupA]);
            });
        });

        it("refuses a change to a group the ministry does not own, and keeps the audience", () => {
            api(
                COORDINATOR_KEY,
                "POST",
                `/api/events/${eventId}`,
                { Title: title, LinkedGroupId: foreignGroup },
                403,
            );

            audienceOf(eventId).then((groups) => {
                expect(groups).to.deep.eq([poolGroupA]);
            });
        });
    });

    describe("an audience an administrator chose", () => {
        let eventId = 0;
        const title = `${FIXTURE_PREFIX} admin audience`;

        before(() => {
            api(
                ADMIN_KEY,
                "POST",
                "/api/events",
                {
                    Title: title,
                    Type: CHURCH_SERVICE_TYPE,
                    Start: `${isoDate(31)}T09:00:00`,
                    End: `${isoDate(31)}T10:30:00`,
                    PinnedCalendars: [PUBLIC_CALENDAR],
                    MinistryId: ministryA,
                    LinkedGroupId: foreignGroup,
                },
                200,
            );
            eventIdByTitle(title).then((id) => {
                eventId = id;
            });
        });

        it("keeps any group for the global AddEvent right", () => {
            audienceOf(eventId).then((groups) => {
                expect(groups).to.deep.eq([foreignGroup]);
            });
        });

        it("cannot be cleared by a coordinator", () => {
            api(
                COORDINATOR_KEY,
                "POST",
                `/api/events/${eventId}`,
                { Title: title, LinkedGroupId: 0 },
                403,
            );

            audienceOf(eventId).then((groups) => {
                expect(groups).to.deep.eq([foreignGroup]);
            });
        });

        it("survives a coordinator's edit that does not mention the audience", () => {
            api(
                COORDINATOR_KEY,
                "POST",
                `/api/events/${eventId}`,
                { Title: title },
                200,
            );

            audienceOf(eventId).then((groups) => {
                expect(groups).to.deep.eq([foreignGroup]);
            });
        });
    });
});
