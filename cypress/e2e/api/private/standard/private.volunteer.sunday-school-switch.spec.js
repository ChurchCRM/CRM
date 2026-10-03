/// <reference types="cypress" />

/**
 * Volunteer v2 D29 — "Can this ministry provide teachers for Sunday School?" (design §0.8,
 * §2.3, §4.6). Off by default; only a volunteer manager or an administrator changes it; while
 * it is off the ministry's teams, schedules and events cannot use a class.
 *
 * Personas (seed.sql): `admin.api.key` (administrator); `user.api.key` = person 3, tony.wade,
 * Manage My Ministries, made coordinator of the ministry here.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const URL = "/api/ministries";
const ADMIN_KEY = "admin.api.key";
const COORDINATOR_KEY = "user.api.key";
const PERSON_COORDINATOR = 3;
const CHURCH_SERVICE_TYPE = 1;
const UPGRADE_SCRIPT = "src/mysql/upgrade/7.8.0-volunteer-v2-schema.sql";

const PREFIX = "SSW29";

let originalVersion = "v1";
let ministryId = 0;
let teamId = 0;
let classA = 0;
let classB = 0;

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

function admin(method, url, body, expectedStatus = 200) {
    return api(ADMIN_KEY, method, url, body, expectedStatus);
}

function isoDate(offsetDays) {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + offsetDays);
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function switchOf(id) {
    return dbOk("SELECT vmin_SundaySchool AS on_ FROM volunteer_ministry_vmin WHERE vmin_ID = ?", [id]).then((rows) =>
        Number(rows[0].on_),
    );
}

function classSchedule(name, groupId) {
    return { name: `${PREFIX} ${name}`, teamId, linkMode: "class", groupId, windowStart: isoDate(1) };
}

function classEvent(title, groupId) {
    return {
        title: `${PREFIX} ${title}`,
        eventTypeId: CHURCH_SERVICE_TYPE,
        date: isoDate(4),
        startTime: "09:30",
        endTime: "10:30",
        linkedGroupId: groupId,
    };
}

function cleanupFixtures() {
    const like = [`${PREFIX}%`];
    dbOk(`DELETE FROM volunteer_scope_vscp WHERE vscp_per_ID = ?`, [PERSON_COORDINATOR]);
    for (const table of ["calendar_events", "event_audience"]) {
        dbOk(`DELETE t FROM ${table} t JOIN events_event e ON e.event_id = t.event_id WHERE e.event_title LIKE ?`, like);
    }
    dbOk("DELETE FROM events_event WHERE event_title LIKE ?", like);
    dbOk(
        `DELETE l FROM list_lst l JOIN group_grp g ON g.grp_RoleListID = l.lst_ID WHERE g.grp_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE r FROM person2group2role_p2g2r r JOIN group_grp g ON g.grp_ID = r.p2g2r_grp_ID WHERE g.grp_Name LIKE ?`,
        like,
    );
    dbOk("DELETE FROM group_grp WHERE grp_Name LIKE ?", like);
    dbOk("DELETE FROM calendars WHERE name LIKE ?", like);
    dbOk("DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?", like);
}

before(() => {
    cy.rememberTestEnv(["admin.api.key", "user.api.key"]);
});

describe("Volunteer v2 D29 — \"Can this ministry provide teachers for Sunday School?\" (design §0.8, §2.3, §4.6)", () => {
    before(() => {
        admin("GET", SETTING_URL, null).then((resp) => {
            originalVersion = resp.body.value ?? "v1";
        });
        admin("POST", SETTING_URL, { value: "v2" });
        cleanupFixtures();

        admin("POST", `${URL}/ministries`, { name: `${PREFIX} Adult Ministry` }, 201).then((resp) => {
            ministryId = resp.body.ministry.id;
            admin("GET", `${URL}/ministries/${ministryId}`).then((detail) => {
                teamId = detail.body.teams[0].id;
            });
        });
        for (const [name, set] of [
            ["Class A", (id) => (classA = id)],
            ["Class B", (id) => (classB = id)],
        ]) {
            admin("POST", "/api/groups/", { groupName: `${PREFIX} ${name}`, isSundaySchool: true }).then((resp) =>
                set(resp.body.Id),
            );
        }
        cy.then(() => {
            admin("POST", `${URL}/scopes`, { personId: PERSON_COORDINATOR, scopeType: "ministry", scopeId: ministryId }, [
                200, 201,
            ]);
        });
    });

    after(() => {
        cleanupFixtures();
        admin("POST", SETTING_URL, { value: originalVersion });
    });

    describe("Volunteer v2 D29 — the Sunday School switch", () => {
        it("is off unless the ministry is created with it, and every ministry payload carries it", () => {
            admin("GET", `${URL}/ministries/${ministryId}`).then((resp) => {
                expect(resp.body.ministry.sundaySchool).to.eq(false);
            });
            admin("POST", `${URL}/ministries`, { name: `${PREFIX} Children`, sundaySchool: true }, 201).then((resp) => {
                expect(resp.body.ministry.sundaySchool).to.eq(true);
                switchOf(resp.body.ministry.id).should("eq", 1);
            });
            admin("GET", `${URL}/ministries`).then((resp) => {
                const mine = resp.body.ministries.filter((m) => m.name.startsWith(PREFIX));
                expect(mine.map((m) => [m.name, m.sundaySchool])).to.deep.include.members([
                    [`${PREFIX} Adult Ministry`, false],
                    [`${PREFIX} Children`, true],
                ]);
            });
            admin("POST", `${URL}/ministries`, { name: `${PREFIX} Loose`, sundaySchool: "yes" }, 400);
        });

        it("lets a coordinator send the current value but not change it", () => {
            api(COORDINATOR_KEY, "POST", `${URL}/ministries/${ministryId}`, { sundaySchool: false }).then((resp) => {
                expect(resp.body.ministry.sundaySchool).to.eq(false);
            });
            api(COORDINATOR_KEY, "POST", `${URL}/ministries/${ministryId}`, { sundaySchool: true }, 403).then((resp) => {
                expect(resp.body.message).to.contain("volunteer manager");
            });
            switchOf(ministryId).should("eq", 0);
            admin("POST", `${URL}/ministries/${ministryId}`, { sundaySchool: "on" }, 400);
        });

        it("refuses a class link while off and lists no linkable classes", () => {
            admin("POST", `${URL}/teams/${teamId}`, { classGroupId: classA }, 400).then((resp) => {
                expect(resp.body.message).to.contain("does not provide teachers for Sunday School");
            });
            admin("POST", `${URL}/ministries/${ministryId}/teams`, { name: `${PREFIX} Born linked`, classGroupId: classA }, 400);
            dbOk("SELECT vtem_grp_ID AS g FROM volunteer_team_vtem WHERE vtem_ID = ?", [teamId]).then((rows) => {
                expect(rows[0].g).to.eq(null);
            });
            dbOk("SELECT COUNT(*) AS n FROM volunteer_team_vtem WHERE vtem_Name = ?", [`${PREFIX} Born linked`]).then(
                (rows) => expect(Number(rows[0].n)).to.eq(0),
            );
            admin("GET", `${URL}/ministries/${ministryId}/linkable-classes?teamId=${teamId}`).then((resp) => {
                expect(resp.body).to.deep.eq({ sundaySchool: false, classes: [] });
            });
        });

        it("refuses a class-mode schedule while off", () => {
            admin("POST", `${URL}/ministries/${ministryId}/schedules`, classSchedule("Refused", classA), 400).then((resp) => {
                expect(resp.body.message).to.contain("does not provide teachers for Sunday School");
            });
        });

        it("refuses an event's class while off, and creates nothing", () => {
            admin("POST", `${URL}/ministries/${ministryId}/events`, classEvent("Refused", classA), 400).then((resp) => {
                expect(resp.body.message).to.contain("does not provide teachers for Sunday School");
            });
            dbOk("SELECT COUNT(*) AS n FROM events_event WHERE event_title = ?", [`${PREFIX} Refused`]).then((rows) =>
                expect(Number(rows[0].n)).to.eq(0),
            );
            const withoutClass = { ...classEvent("No class", classA), linkedGroupId: null };
            admin("POST", `${URL}/ministries/${ministryId}/events`, withoutClass, 201);
        });

        it("works once a manager turns it on, and cannot be turned off while a team is linked", () => {
            admin("POST", `${URL}/ministries/${ministryId}`, { sundaySchool: true }).then((resp) => {
                expect(resp.body.ministry.sundaySchool).to.eq(true);
            });
            admin("GET", `${URL}/ministries/${ministryId}/linkable-classes`).then((resp) => {
                expect(resp.body.sundaySchool).to.eq(true);
                expect(resp.body.classes.map((c) => c.id)).to.include.members([classA, classB]);
            });
            admin("POST", `${URL}/teams/${teamId}`, { classGroupId: classA }).then((resp) => {
                expect(resp.body.team.classGroupId).to.eq(classA);
            });
            admin("POST", `${URL}/ministries/${ministryId}/events`, classEvent("Class A", classA), 201);
            admin("POST", `${URL}/ministries/${ministryId}/schedules`, classSchedule("Class A meetings", classA), 201);

            admin("POST", `${URL}/ministries/${ministryId}`, { sundaySchool: false }, 409).then((resp) => {
                expect(resp.body.message).to.contain(`${PREFIX} Adult Ministry Team`);
                expect(resp.body.teams).to.deep.eq([{ id: teamId, name: `${PREFIX} Adult Ministry Team` }]);
            });
            switchOf(ministryId).should("eq", 1);
        });

        it("keeps an existing class schedule editable once turned off, but will not re-point it", () => {
            admin("POST", `${URL}/teams/${teamId}`, { classGroupId: null });
            admin("POST", `${URL}/ministries/${ministryId}`, { sundaySchool: false }).then((resp) => {
                expect(resp.body.ministry.sundaySchool).to.eq(false);
            });

            dbOk("SELECT vsch_ID AS id FROM volunteer_schedule_vsch WHERE vsch_Name = ?", [`${PREFIX} Class A meetings`]).then(
                (rows) => {
                    const scheduleId = rows[0].id;
                    admin("POST", `${URL}/schedules/${scheduleId}`, { name: `${PREFIX} Class A meetings (renamed)` });
                    admin("POST", `${URL}/schedules/${scheduleId}`, { groupId: classB }, 400);
                    admin("POST", `${URL}/schedules/${scheduleId}`, { linkMode: "ministry", titleFilter: `${PREFIX} No class` });
                    admin("POST", `${URL}/schedules/${scheduleId}`, { linkMode: "class", groupId: classA }, 400);
                },
            );
        });

        it("turns the switch on in the upgrade for a ministry that already staffs a class", () => {
            cy.readFile(UPGRADE_SCRIPT).then((script) => {
                const start = script.lastIndexOf("UPDATE `volunteer_ministry_vmin`");
                const update = script.slice(start, script.indexOf(";", start)).trim();
                admin("POST", `${URL}/ministries/${ministryId}`, { sundaySchool: true });
                admin("POST", `${URL}/teams/${teamId}`, { classGroupId: classA });
                dbOk("UPDATE volunteer_ministry_vmin SET vmin_SundaySchool = 0 WHERE vmin_ID = ?", [ministryId]);

                dbOk(update);
                switchOf(ministryId).should("eq", 1);
                dbOk(update);
                switchOf(ministryId).should("eq", 1);

                admin("POST", `${URL}/teams/${teamId}`, { classGroupId: null });
                dbOk("UPDATE volunteer_ministry_vmin SET vmin_SundaySchool = 0 WHERE vmin_ID = ?", [ministryId]);
                dbOk("UPDATE volunteer_schedule_vsch SET vsch_LinkMode = 'class', vsch_grp_ID = ? WHERE vsch_Name LIKE ?", [
                    classA,
                    `${PREFIX}%`,
                ]);
                dbOk(update);
                switchOf(ministryId).should("eq", 1);
            });
        });
    });
});
