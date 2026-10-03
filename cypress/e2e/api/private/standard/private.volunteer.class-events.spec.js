/// <reference types="cypress" />

/**
 * Volunteer v2 D28 — a class's events when its team's class changes or the team is deleted,
 * and the Calendar tab's Delete events (design §0.8, §2.4, §3.3, §4.6).
 *
 * The team → class link and an event's Linked Group are different facts: only events the
 * ministry owns change, only as the coordinator chose, and deleting goes through core's event
 * delete with its guards.
 *
 * Personas (seed.sql): `admin.api.key` (administrator, Add Events); `user.api.key` = person 3,
 * tony.wade, no Add Events, made coordinator of the ministry here; `selfedit.api.key` = person
 * 99, made leader of the ministry's first team.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const URL = "/api/ministries";
const ADMIN_KEY = "admin.api.key";
const COORDINATOR_KEY = "user.api.key";
const LEADER_KEY = "selfedit.api.key";
const PERSON_COORDINATOR = 3;
const PERSON_LEADER = 99;
const PERSON_VOLUNTEER = 8;
const PERSON_CHECKED_IN = 2;
const CHURCH_SERVICE_TYPE = 1;

const PREFIX = "CLEV28";

let originalVersion = "v1";
let ministryId = 0;
let firstTeamId = 0;
let otherMinistryId = 0;
let otherTeamId = 0;
let otherPositionId = 0;
const classes = {};
const teams = {};
/** class key → [past, soon, later] event ids the ministry owns */
const owned = {};
/** class key → the id of an event an administrator made for the class, owned by no ministry */
const adminEvent = {};

function dbOk(sql, params = []) {
    return cy.dbQuery(sql, params).then((result) => {
        if (result.error !== null) {
            throw new Error(`Unexpected SQL failure.\n  SQL: ${sql}\n  ${result.error.code}: ${result.error.message}`);
        }
        return result.rows;
    });
}

function api(key, method, url, body, expectedStatus = 200) {
    return cy.makePrivateAPICall(Cypress.env(key), method, url, body, expectedStatus);
}

function admin(method, url, body, expectedStatus = 200) {
    return api(ADMIN_KEY, method, url, body, expectedStatus);
}

function coordinator(method, url, body, expectedStatus = 200) {
    return api(COORDINATOR_KEY, method, url, body, expectedStatus);
}

function isoDate(offsetDays) {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + offsetDays);
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/** One event the ministry owns, through the Calendar tab's create. */
function ownedEvent(title, offsetDays, groupId = null) {
    return admin(
        "POST",
        `${URL}/ministries/${ministryId}/events`,
        {
            title: `${PREFIX} ${title}`,
            eventTypeId: CHURCH_SERVICE_TYPE,
            date: isoDate(offsetDays),
            startTime: "09:30",
            endTime: "10:30",
            linkedGroupId: groupId,
        },
        201,
    ).then((resp) => resp.body.events[0].id);
}

/** An event an administrator made in the core editor: no ministry owns it. */
function unownedEvent(title, groupId, ministry = null) {
    return dbOk(
        `INSERT INTO events_event (event_type, event_title, event_desc, event_start, event_end, event_ministry_id)
         VALUES (?, ?, '', ?, ?, ?)`,
        [CHURCH_SERVICE_TYPE, `${PREFIX} ${title}`, `${isoDate(3)} 09:30:00`, `${isoDate(3)} 10:30:00`, ministry],
    ).then((rows) =>
        dbOk("INSERT INTO event_audience (event_id, group_id) VALUES (?, ?)", [rows.insertId, groupId]).then(
            () => rows.insertId,
        ),
    );
}

function audienceOf(eventId) {
    return dbOk("SELECT group_id FROM event_audience WHERE event_id = ? ORDER BY group_id", [eventId]).then((rows) =>
        rows.map((r) => Number(r.group_id)),
    );
}

function existing(eventIds) {
    return dbOk(`SELECT event_id FROM events_event WHERE event_id IN (${eventIds.map(() => "?").join(",")})`, eventIds).then(
        (rows) => rows.map((r) => Number(r.event_id)).sort((a, b) => a - b),
    );
}

function classOfTeam(teamId) {
    return dbOk("SELECT vtem_grp_ID AS g FROM volunteer_team_vtem WHERE vtem_ID = ?", [teamId]).then((rows) =>
        rows.length === 0 ? "gone" : rows[0].g === null ? null : Number(rows[0].g),
    );
}

/** The other ministry staffs the event with one volunteer assigned (pending). */
function staffByOtherMinistry(eventId) {
    return admin("POST", `${URL}/ministries/${otherMinistryId}/staffed-events`, { eventId, teamId: otherTeamId }, 201).then(
        (resp) => {
            const occurrenceId = resp.body.occurrence.id;
            return dbOk(
                `INSERT INTO volunteer_assignment_vasg (vasg_vocc_ID, vasg_vpos_ID, vasg_per_ID, vasg_Status, vasg_AssignedDate)
                 VALUES (?, ?, ?, 'pending', NOW())`,
                [occurrenceId, otherPositionId, PERSON_VOLUNTEER],
            ).then(() => occurrenceId);
        },
    );
}

function cleanupFixtures() {
    const like = [`${PREFIX}%`];
    dbOk(
        `DELETE vasg FROM volunteer_assignment_vasg vasg
           JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vasg.vasg_vocc_ID
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vsch FROM volunteer_schedule_vsch vsch
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk("DELETE FROM volunteer_scope_vscp WHERE vscp_per_ID IN (?, ?)", [PERSON_COORDINATOR, PERSON_LEADER]);
    for (const table of ["calendar_events", "event_audience", "event_attend"]) {
        dbOk(`DELETE t FROM ${table} t JOIN events_event e ON e.event_id = t.event_id WHERE e.event_title LIKE ?`, like);
    }
    dbOk(
        `DELETE k FROM kioskassginment_kasm k JOIN events_event e ON e.event_id = k.kasm_EventId WHERE e.event_title LIKE ?`,
        like,
    );
    dbOk("DELETE FROM events_event WHERE event_title LIKE ?", like);
    dbOk(
        `DELETE r FROM person2group2role_p2g2r r JOIN group_grp g ON g.grp_ID = r.p2g2r_grp_ID WHERE g.grp_Name LIKE ?`,
        like,
    );
    dbOk(`DELETE l FROM list_lst l JOIN group_grp g ON g.grp_RoleListID = l.lst_ID WHERE g.grp_Name LIKE ?`, like);
    dbOk("DELETE FROM group_grp WHERE grp_Name LIKE ?", like);
    dbOk("DELETE FROM calendars WHERE name LIKE ?", like);
    dbOk("DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?", like);
}

const CLASS_KEYS = ["keep", "remove", "move", "moveTo", "delKeep", "delRemove", "delDelete", "delCore"];

describe("Volunteer v2 D28 — a class's events when its team's class changes or the team is deleted, and the Calendar tab's Delete events (design §0.8, §2.4, §3.3, §4.6)", () => {
    before(() => {
        admin("GET", SETTING_URL, null).then((resp) => {
            originalVersion = resp.body.value ?? "v1";
        });
        admin("POST", SETTING_URL, { value: "v2" });
        cleanupFixtures();

        admin("POST", `${URL}/ministries`, { name: `${PREFIX} Children`, sundaySchool: true }, 201).then((resp) => {
            ministryId = resp.body.ministry.id;
            admin("GET", `${URL}/ministries/${ministryId}`).then((detail) => {
                firstTeamId = detail.body.teams[0].id;
            });
        });
        admin("POST", `${URL}/ministries`, { name: `${PREFIX} Coffee Bar` }, 201).then((resp) => {
            otherMinistryId = resp.body.ministry.id;
            admin("GET", `${URL}/ministries/${otherMinistryId}`).then((detail) => {
                otherTeamId = detail.body.teams[0].id;
                admin(
                    "POST",
                    `${URL}/ministries/${otherMinistryId}/positions`,
                    { name: `${PREFIX} Barista`, teamId: otherTeamId },
                    201,
                ).then((position) => {
                    otherPositionId = position.body.position.id;
                });
            });
        });
        for (const key of CLASS_KEYS) {
            admin("POST", "/api/groups/", { groupName: `${PREFIX} Class ${key}`, isSundaySchool: true }).then((resp) => {
                classes[key] = resp.body.Id;
            });
        }

        cy.then(() => {
            for (const key of CLASS_KEYS.filter((k) => k !== "moveTo")) {
                admin(
                    "POST",
                    `${URL}/ministries/${ministryId}/teams`,
                    { name: `${PREFIX} Team ${key}`, classGroupId: classes[key] },
                    201,
                ).then((resp) => {
                    teams[key] = resp.body.team.id;
                });
                owned[key] = [];
                for (const offset of [-7, 3, 10]) {
                    ownedEvent(`${key} ${offset}`, offset, classes[key]).then((id) => {
                        owned[key].push(id);
                    });
                }
                unownedEvent(`${key} by an administrator`, classes[key]).then((id) => {
                    adminEvent[key] = id;
                });
            }
            admin("POST", `${URL}/scopes`, { personId: PERSON_COORDINATOR, scopeType: "ministry", scopeId: ministryId }, [
                200, 201,
            ]);
            admin("POST", `${URL}/scopes`, { personId: PERSON_LEADER, scopeType: "team", scopeId: firstTeamId }, [200, 201]);
        });
    });

    after(() => {
        cleanupFixtures();
        admin("POST", SETTING_URL, { value: originalVersion });
    });

    describe("Volunteer v2 D28 — what the team dialog is told", () => {
        it("counts the class's events the ministry owns, upcoming apart, and never an administrator's", () => {
            coordinator("GET", `${URL}/teams/${teams.keep}/class-events`).then((resp) => {
                expect(resp.body).to.deep.eq({
                    teamId: teams.keep,
                    ministryId,
                    ministryName: `${PREFIX} Children`,
                    classGroupId: classes.keep,
                    classGroupName: `${PREFIX} Class keep`,
                    total: 3,
                    upcoming: 2,
                    otherStaffing: [],
                });
            });
        });

        it("answers zero for a team with no class, and 403 outside the caller's teams", () => {
            coordinator("GET", `${URL}/teams/${firstTeamId}/class-events`).then((resp) => {
                expect(resp.body).to.include({ classGroupId: null, classGroupName: null, total: 0, upcoming: 0 });
            });
            api(LEADER_KEY, "GET", `${URL}/teams/${firstTeamId}/class-events`);
            api(LEADER_KEY, "GET", `${URL}/teams/${teams.keep}/class-events`, null, 403);
        });
    });

    describe("Volunteer v2 D28 — the team's class changes", () => {
        it("keeps the events on the old class when nothing is chosen", () => {
            coordinator("POST", `${URL}/teams/${teams.keep}`, { classGroupId: null });
            classOfTeam(teams.keep).should("eq", null);
            for (const eventId of owned.keep) {
                audienceOf(eventId).should("deep.eq", [classes.keep]);
            }
        });

        it("refuses an unknown choice and a delete on update, changing nothing", () => {
            coordinator("POST", `${URL}/teams/${teams.remove}`, { classGroupId: null, classEvents: "delete" }, 400).then(
                (resp) => expect(resp.body.message).to.contain("keep, remove, move"),
            );
            coordinator("POST", `${URL}/teams/${teams.remove}`, { classGroupId: null, classEvents: "drop" }, 400);
            classOfTeam(teams.remove).should("eq", classes.remove);
        });

        it("removes the class from the ministry's events and leaves an administrator's alone", () => {
            coordinator("POST", `${URL}/teams/${teams.remove}`, { classGroupId: null, classEvents: "remove" });
            classOfTeam(teams.remove).should("eq", null);
            for (const eventId of owned.remove) {
                audienceOf(eventId).should("deep.eq", []);
            }
            audienceOf(adminEvent.remove).should("deep.eq", [classes.remove]);
        });

        it("moves only to a new class, and then moves exactly the ministry's events", () => {
            coordinator("POST", `${URL}/teams/${teams.move}`, { classGroupId: null, classEvents: "move" }, 400);
            classOfTeam(teams.move).should("eq", classes.move);
            audienceOf(owned.move[0]).should("deep.eq", [classes.move]);

            coordinator("POST", `${URL}/teams/${teams.move}`, { classGroupId: classes.moveTo, classEvents: "move" }).then(
                (resp) => expect(resp.body.team.classGroupId).to.eq(classes.moveTo),
            );
            for (const eventId of owned.move) {
                audienceOf(eventId).should("deep.eq", [classes.moveTo]);
            }
            audienceOf(adminEvent.move).should("deep.eq", [classes.move]);
            coordinator("GET", `${URL}/teams/${teams.move}/class-events`).then((resp) => {
                expect(resp.body).to.include({ classGroupId: classes.moveTo, total: 3, upcoming: 2 });
            });
        });
    });

    describe("Volunteer v2 D28 — the team is deleted", () => {
        it("keeps the events by default", () => {
            coordinator("DELETE", `${URL}/teams/${teams.delKeep}`, null);
            classOfTeam(teams.delKeep).should("eq", "gone");
            for (const eventId of owned.delKeep) {
                audienceOf(eventId).should("deep.eq", [classes.delKeep]);
            }
        });

        it("refuses an unknown choice and a move", () => {
            coordinator("DELETE", `${URL}/teams/${teams.delRemove}`, { classEvents: "move" }, 400);
            classOfTeam(teams.delRemove).should("eq", classes.delRemove);
        });

        it("removes the class from the ministry's events", () => {
            coordinator("DELETE", `${URL}/teams/${teams.delRemove}`, { classEvents: "remove" });
            classOfTeam(teams.delRemove).should("eq", "gone");
            for (const eventId of owned.delRemove) {
                audienceOf(eventId).should("deep.eq", []);
            }
            audienceOf(adminEvent.delRemove).should("deep.eq", [classes.delRemove]);
        });

        it("will not delete events another ministry staffs unless the caller has Add Events", () => {
            staffByOtherMinistry(owned.delDelete[1]).then((occurrenceId) => {
                coordinator("GET", `${URL}/teams/${teams.delDelete}/class-events`).then((resp) => {
                    expect(resp.body.otherStaffing).to.deep.eq([
                        { ministryId: otherMinistryId, ministryName: `${PREFIX} Coffee Bar`, assigned: 1, eventCount: 1 },
                    ]);
                });

                coordinator("DELETE", `${URL}/teams/${teams.delDelete}`, { classEvents: "delete" }, 409).then((resp) => {
                    expect(resp.body.message).to.contain(`${PREFIX} Coffee Bar`);
                    expect(resp.body.ministries).to.deep.eq([
                        { ministryId: otherMinistryId, ministryName: `${PREFIX} Coffee Bar` },
                    ]);
                });
                classOfTeam(teams.delDelete).should("eq", classes.delDelete);
                existing(owned.delDelete).should("deep.eq", [...owned.delDelete].sort((a, b) => a - b));

                admin("DELETE", `${URL}/teams/${teams.delDelete}`, { classEvents: "delete" });
                classOfTeam(teams.delDelete).should("eq", "gone");
                existing(owned.delDelete).should("deep.eq", []);
                existing([adminEvent.delDelete]).should("deep.eq", [adminEvent.delDelete]);
                audienceOf(adminEvent.delDelete).should("deep.eq", [classes.delDelete]);
                dbOk("SELECT vocc_event_id AS e FROM volunteer_occurrence_vocc WHERE vocc_ID = ?", [occurrenceId]).then(
                    (rows) => expect(rows[0].e, "the other ministry keeps its history").to.eq(null),
                );
            });
        });

        it("deletes nothing when core refuses one of the events", () => {
            dbOk("INSERT INTO event_attend (event_id, person_id, checkin_date) VALUES (?, ?, NOW())", [
                owned.delCore[1],
                PERSON_CHECKED_IN,
            ]);
            admin("DELETE", `${URL}/teams/${teams.delCore}`, { classEvents: "delete" }, 409).then((resp) => {
                expect(resp.body.message).to.contain("currently checked in");
                expect(resp.body.eventId).to.eq(owned.delCore[1]);
            });
            classOfTeam(teams.delCore).should("eq", classes.delCore);
            existing(owned.delCore).should("deep.eq", [...owned.delCore].sort((a, b) => a - b));
        });
    });

    describe("Volunteer v2 D28 — the Calendar tab's Delete events", () => {
        const plain = [];
        let staffed = 0;
        let kiosked = 0;
        let otherOwned = 0;
        let occurrenceId = 0;

        before(() => {
            for (const offset of [4, 5, 6]) {
                ownedEvent(`Workday ${offset}`, offset).then((id) => {
                    plain.push(id);
                });
            }
            ownedEvent("Staffed", 7).then((id) => {
                staffed = id;
                staffByOtherMinistry(id).then((occurrence) => {
                    occurrenceId = occurrence;
                });
            });
            ownedEvent("At the kiosk", 8).then((id) => {
                kiosked = id;
                dbOk("INSERT INTO kioskassginment_kasm (kasm_AssignmentType, kasm_EventId) VALUES (1, ?)", [id]);
            });
            unownedEvent("Coffee Bar's own", classes.keep, null).then((id) => {
                dbOk("UPDATE events_event SET event_ministry_id = ? WHERE event_id = ?", [otherMinistryId, id]);
                otherOwned = id;
            });
        });

        it("lists other ministries' staffing on each event", () => {
            coordinator("GET", `${URL}/ministries/${ministryId}/events`).then((resp) => {
                const byId = Object.fromEntries(resp.body.events.map((event) => [event.id, event]));
                expect(byId[staffed].otherStaffing).to.deep.eq([
                    { ministryId: otherMinistryId, ministryName: `${PREFIX} Coffee Bar`, assigned: 1 },
                ]);
                expect(byId[plain[0]].otherStaffing).to.deep.eq([]);
            });
        });

        it("refuses a malformed list, an unknown event and an event the ministry does not own", () => {
            const url = `${URL}/ministries/${ministryId}/events`;
            coordinator("DELETE", url, {}, 400);
            coordinator("DELETE", url, { eventIds: [] }, 400);
            coordinator("DELETE", url, { eventIds: ["x"] }, 400);
            coordinator("DELETE", url, { eventIds: [plain[0], 99999999] }, 404);
            coordinator("DELETE", url, { eventIds: [plain[0], adminEvent.keep] }, 403);
            coordinator("DELETE", url, { eventIds: [plain[0], otherOwned] }, 403).then((resp) => {
                expect(resp.body.message).to.contain(`is not an event of ${PREFIX} Children`);
            });
            existing([plain[0], adminEvent.keep, otherOwned]).should(
                "deep.eq",
                [plain[0], adminEvent.keep, otherOwned].sort((a, b) => a - b),
            );
        });

        it("is a coordinator's: a team leader gets 403", () => {
            api(LEADER_KEY, "DELETE", `${URL}/ministries/${ministryId}/events`, { eventIds: [plain[0]] }, 403);
            existing([plain[0]]).should("deep.eq", [plain[0]]);
        });

        it("refuses, deleting nothing, while another ministry has volunteers there", () => {
            coordinator("DELETE", `${URL}/ministries/${ministryId}/events`, { eventIds: [plain[0], staffed] }, 409).then(
                (resp) => expect(resp.body.message).to.contain(`${PREFIX} Coffee Bar`),
            );
            existing([plain[0], staffed]).should("deep.eq", [plain[0], staffed].sort((a, b) => a - b));
        });

        it("comes back with core's refusal, deleting nothing", () => {
            coordinator("DELETE", `${URL}/ministries/${ministryId}/events`, { eventIds: [plain[0], kiosked] }, 409).then(
                (resp) => expect(resp.body.message).to.contain("assigned to a kiosk"),
            );
            existing([plain[0], kiosked]).should("deep.eq", [plain[0], kiosked].sort((a, b) => a - b));
        });

        it("leaves core's own delete refusing the same event with the same reason", () => {
            admin("DELETE", `/api/events/${kiosked}`, null, 409).then((resp) => {
                expect(resp.body.message).to.eq("Cannot delete event: event is currently assigned to a kiosk.");
            });
            existing([kiosked]).should("deep.eq", [kiosked]);
        });

        it("deletes the ministry's own events through core", () => {
            coordinator("DELETE", `${URL}/ministries/${ministryId}/events`, { eventIds: [plain[0], plain[1]] }).then((resp) => {
                expect(resp.body).to.deep.eq({ deleted: 2 });
            });
            existing(plain).should("deep.eq", [plain[2]]);
            dbOk("SELECT COUNT(*) AS n FROM calendar_events WHERE event_id IN (?, ?)", [plain[0], plain[1]]).then((rows) =>
                expect(Number(rows[0].n)).to.eq(0),
            );
        });

        it("lets someone with Add Events delete an event another ministry staffs, keeping its history", () => {
            admin("DELETE", `${URL}/ministries/${ministryId}/events`, { eventIds: [staffed] });
            existing([staffed]).should("deep.eq", []);
            dbOk("SELECT vocc_event_id AS e FROM volunteer_occurrence_vocc WHERE vocc_ID = ?", [occurrenceId]).then((rows) =>
                expect(rows[0].e).to.eq(null),
            );
        });
    });
});
