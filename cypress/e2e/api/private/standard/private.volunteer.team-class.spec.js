/// <reference types="cypress" />

/**
 * Volunteer v2 — a team linked to a Sunday School class (D23, design §2.4, §2.7).
 *
 * The classes are made here through `POST /api/groups/` with `isSundaySchool`, so
 * each has core's own role list: Teacher = option 1, Student = option 2.
 *
 * Tiers: admin (`admin.api.key`, holds Manage Groups — so every 409 on the groups
 * API below is refused to somebody who may write any group) and person 99
 * (`selfedit.api.key`), given a team-leader scope on the linked team.
 *
 * Cleanup runs in `before` AND `after`: an `after` hook does not run when the
 * runner crashes mid-spec. Teams, positions and qualifications cascade from the
 * ministry; the classes, role lists and scope rows are removed explicitly.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const MINISTRIES_URL = "/api/ministries/ministries";
const TEAMS_URL = "/api/ministries/teams";
const POSITIONS_URL = "/api/ministries/positions";
const GROUPS_URL = "/api/groups";

const PREFIX = "TCLS23";
const TEACHER = 1;
const STUDENT = 2;

const PERSON_ADMIN = 1;
const PERSON_TEACHER = 20; // Teacher of class A before the link
const PERSON_STUDENT = 21; // Student of class A
const PERSON_NEW = 22; // in no class
const PERSON_TWICE = 23; // qualified for two positions of the team
const PERSON_CORE = 24; // written through the core groups API
const PERSON_LEADER = 99;
const LEADER_KEY = "selfedit.api.key";

let originalVersion = "v1";
let ministryId = 0;
let poolGroupId = 0;
let teamId = 0;
let otherTeamId = 0;
let classA = 0;
let classB = 0;
let classNoTeacher = 0;
let plainGroup = 0;
let positionLead = 0;
let positionHelper = 0;
let otherTeamPosition = 0;

function dbOk(sql, params = []) {
    return cy.dbQuery(sql, params).then((result) => {
        if (result.error !== null) {
            throw new Error(`Unexpected SQL failure.\n  SQL: ${sql}\n  ${result.error.code}: ${result.error.message}`);
        }
        return result.rows;
    });
}

function admin(method, url, body, status = 200) {
    return cy.makePrivateAdminAPICall(method, url, body, status);
}

function setVersion(value) {
    admin("POST", SETTING_URL, { value });
}

/** The person's role in the group, or null when they are not a member. */
function roleOf(groupId, personId) {
    return dbOk(
        "SELECT p2g2r_rle_ID AS role FROM person2group2role_p2g2r WHERE p2g2r_grp_ID = ? AND p2g2r_per_ID = ?",
        [groupId, personId],
    ).then((rows) => (rows.length === 0 ? null : Number(rows[0].role)));
}

function qualificationOf(personId, positionId) {
    return dbOk(
        `SELECT vqal_ID AS id, vqal_Active AS active, vqal_GrantedBy_per_ID AS grantedBy
           FROM volunteer_qualification_vqal WHERE vqal_per_ID = ? AND vqal_vpos_ID = ?`,
        [personId, positionId],
    ).then((rows) => rows[0] ?? null);
}

function grant(positionId, personId, status = 201, key = "admin.api.key") {
    return cy.makePrivateAPICall(
        Cypress.testEnv(key),
        "POST",
        `${POSITIONS_URL}/${positionId}/qualifications`,
        { personId },
        status,
    );
}

function revoke(personId, positionId) {
    return qualificationOf(personId, positionId).then((row) => {
        admin("DELETE", `/api/ministries/qualifications/${row.id}`, null, 200);
    });
}

function createClass(name, isSundaySchool = true) {
    return admin("POST", `${GROUPS_URL}/`, { groupName: `${PREFIX} ${name}`, isSundaySchool }).then(
        (resp) => resp.body.Id,
    );
}

function cleanupFixtures() {
    const like = [`${PREFIX}%`];
    dbOk(
        `DELETE vscp FROM volunteer_scope_vscp vscp
           JOIN volunteer_team_vtem vtem ON vtem.vtem_ID = vscp.vscp_ScopeId
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vtem.vtem_vmin_ID
          WHERE vscp.vscp_ScopeType = 'team' AND vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE r FROM person2group2role_p2g2r r
           JOIN group_grp g ON g.grp_ID = r.p2g2r_grp_ID
          WHERE g.grp_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE l FROM list_lst l
           JOIN group_grp g ON g.grp_RoleListID = l.lst_ID
          WHERE g.grp_Name LIKE ?`,
        like,
    );
    dbOk("DELETE FROM group_grp WHERE grp_Name LIKE ?", like);
    dbOk("DELETE FROM calendars WHERE name LIKE ?", like);
    dbOk("DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?", like);
}

before(() => {
    cy.rememberTestEnv(["admin.api.key", "selfedit.api.key"]);
});

describe("Volunteer v2 — a team linked to a Sunday School class (D23)", () => {
    before(() => {
        admin("GET", SETTING_URL, null).then((resp) => {
            originalVersion = resp.body.value ?? "v1";
        });
        setVersion("v2");
        cleanupFixtures();

        admin("POST", MINISTRIES_URL, { name: `${PREFIX} Children`, sundaySchool: true }, 201).then((resp) => {
            ministryId = resp.body.ministry.id;
            poolGroupId = resp.body.poolGroupId;
            admin("POST", `${MINISTRIES_URL}/${ministryId}/teams`, { name: "Faith City" }, 201).then((team) => {
                teamId = team.body.team.id;
                admin(
                    "POST",
                    `${MINISTRIES_URL}/${ministryId}/positions`,
                    { name: "Lead Teacher", teamId },
                    201,
                ).then((p) => {
                    positionLead = p.body.position.id;
                });
                admin("POST", `${MINISTRIES_URL}/${ministryId}/positions`, { name: "Helper", teamId }, 201).then(
                    (p) => {
                        positionHelper = p.body.position.id;
                    },
                );
                admin("POST", "/api/ministries/scopes", { personId: PERSON_LEADER, scopeType: "team", scopeId: teamId }, 201);
            });
            admin("POST", `${MINISTRIES_URL}/${ministryId}/teams`, { name: "Other Team" }, 201).then((team) => {
                otherTeamId = team.body.team.id;
                admin(
                    "POST",
                    `${MINISTRIES_URL}/${ministryId}/positions`,
                    { name: "Other Position", teamId: otherTeamId },
                    201,
                ).then((p) => {
                    otherTeamPosition = p.body.position.id;
                });
            });
        });

        createClass("Class A").then((id) => {
            classA = id;
            admin("POST", `${GROUPS_URL}/${classA}/addperson/${PERSON_TEACHER}`, { RoleID: TEACHER });
            admin("POST", `${GROUPS_URL}/${classA}/addperson/${PERSON_STUDENT}`, { RoleID: STUDENT });
        });
        createClass("Class B").then((id) => {
            classB = id;
        });
        createClass("No Teacher Role").then((id) => {
            classNoTeacher = id;
            admin("POST", `${GROUPS_URL}/${classNoTeacher}/roles/${TEACHER}`, { groupRoleName: "Leader" });
        });
        createClass("Plain Group", false).then((id) => {
            plainGroup = id;
        });
    });

    after(() => {
        cleanupFixtures();
        setVersion(originalVersion);
    });

    describe("linking", () => {
        it("lists the Sunday School classes a team may link, with their teacher counts", () => {
            admin("GET", `${MINISTRIES_URL}/${ministryId}/linkable-classes`, null).then((resp) => {
                const byId = Object.fromEntries(resp.body.classes.map((c) => [c.id, c]));
                expect(byId[classA]).to.include({ name: `${PREFIX} Class A`, teacherCount: 1 });
                expect(byId[classB]).to.include({ teacherCount: 0 });
                expect(byId).not.to.have.property(String(plainGroup));
            });
        });

        it("refuses a group that is not a Sunday School class, and one that does not exist", () => {
            admin("POST", `${TEAMS_URL}/${teamId}`, { classGroupId: plainGroup }, 400);
            admin("POST", `${TEAMS_URL}/${teamId}`, { classGroupId: 999999 }, 400);
            admin("POST", `${TEAMS_URL}/${teamId}`, { classGroupId: "abc" }, 400);
        });

        it("refuses a class whose role list has no Teacher role", () => {
            admin("POST", `${TEAMS_URL}/${teamId}`, { classGroupId: classNoTeacher }, 400).then((resp) => {
                expect(resp.body.message).to.include("Teacher");
            });
        });

        it("refuses a team leader", () => {
            cy.makePrivateAPICall(
                Cypress.testEnv(LEADER_KEY),
                "POST",
                `${TEAMS_URL}/${teamId}`,
                { classGroupId: classA, importPositionId: positionLead },
                403,
            );
        });

        it("asks which position of the team the class's teachers are qualified for", () => {
            admin("POST", `${TEAMS_URL}/${teamId}`, { classGroupId: classA }, 400).then((resp) => {
                expect(resp.body.message).to.include("Faith City");
                expect(resp.body.teacherCount).to.eq(1);
            });
            admin("POST", `${TEAMS_URL}/${teamId}`, { classGroupId: classA, importPositionId: otherTeamPosition }, 400);
            admin("GET", `${TEAMS_URL}/${teamId}`, null).then((resp) => {
                expect(resp.body.team.classGroupId).to.eq(null);
            });
        });

        it("links the class and imports its teachers as qualifications granted by the actor", () => {
            admin("POST", `${TEAMS_URL}/${teamId}`, { classGroupId: classA, importPositionId: positionLead }).then(
                (resp) => {
                    expect(resp.body.team.classGroupId).to.eq(classA);
                    expect(resp.body.team.classGroupName).to.eq(`${PREFIX} Class A`);
                },
            );
            qualificationOf(PERSON_TEACHER, positionLead).then((row) => {
                expect(Number(row.active)).to.eq(1);
                expect(Number(row.grantedBy)).to.eq(PERSON_ADMIN);
            });
            qualificationOf(PERSON_STUDENT, positionLead).should("eq", null);
            roleOf(poolGroupId, PERSON_TEACHER).should("not.eq", null);
            admin("GET", `${MINISTRIES_URL}/${ministryId}`, null).then((resp) => {
                const team = resp.body.teams.find((t) => t.id === teamId);
                expect(team).to.include({ classGroupId: classA, classGroupName: `${PREFIX} Class A` });
            });
        });

        it("refuses a class another team is linked to, and leaves it out of that team's list", () => {
            admin("POST", `${TEAMS_URL}/${otherTeamId}`, { classGroupId: classA }, 409).then((resp) => {
                expect(resp.body.message).to.include("Faith City");
            });
            admin("GET", `${MINISTRIES_URL}/${ministryId}/linkable-classes?teamId=${otherTeamId}`, null).then((resp) => {
                expect(resp.body.classes.map((c) => c.id)).not.to.include(classA);
            });
            admin("GET", `${MINISTRIES_URL}/${ministryId}/linkable-classes?teamId=${teamId}`, null).then((resp) => {
                expect(resp.body.classes.map((c) => c.id)).to.include(classA);
            });
        });
    });

    describe("qualifications write the Teacher role", () => {
        it("a grant makes the person a teacher of the class", () => {
            grant(positionLead, PERSON_NEW);
            roleOf(classA, PERSON_NEW).should("eq", TEACHER);
            grant(positionLead, PERSON_NEW, 200);
            roleOf(classA, PERSON_NEW).should("eq", TEACHER);
        });

        it("a grant for a Student of the class is refused, naming the class", () => {
            grant(positionLead, PERSON_STUDENT, 409).then((resp) => {
                expect(resp.body.message).to.include(`${PREFIX} Class A`);
            });
            roleOf(classA, PERSON_STUDENT).should("eq", STUDENT);
            qualificationOf(PERSON_STUDENT, positionLead).should("eq", null);
        });

        it("a team leader's grant writes the class too", () => {
            grant(positionHelper, PERSON_TWICE, 201, LEADER_KEY);
            roleOf(classA, PERSON_TWICE).should("eq", TEACHER);
        });

        it("revoking one of two qualifications keeps the Teacher membership", () => {
            grant(positionLead, PERSON_TWICE);
            revoke(PERSON_TWICE, positionHelper);
            roleOf(classA, PERSON_TWICE).should("eq", TEACHER);
        });

        it("revoking the last qualification in the team ends the Teacher membership", () => {
            revoke(PERSON_TWICE, positionLead);
            roleOf(classA, PERSON_TWICE).should("eq", null);
            revoke(PERSON_NEW, positionLead);
            roleOf(classA, PERSON_NEW).should("eq", null);
        });

        it("deleting a position ends teaching for whoever held their last qualification through it", () => {
            admin("POST", `${MINISTRIES_URL}/${ministryId}/positions`, { name: "Assistant", teamId }, 201).then(
                (resp) => {
                    const positionAssistant = resp.body.position.id;
                    grant(positionAssistant, PERSON_NEW);
                    grant(positionAssistant, PERSON_TEACHER);
                    roleOf(classA, PERSON_NEW).should("eq", TEACHER);
                    admin("DELETE", `${POSITIONS_URL}/${positionAssistant}`, null);
                },
            );
            roleOf(classA, PERSON_NEW).should("eq", null);
            roleOf(classA, PERSON_TEACHER).should("eq", TEACHER);
        });

        it("a position with qualified volunteers cannot move out of the linked team", () => {
            admin("POST", `${POSITIONS_URL}/${positionLead}`, { teamId: otherTeamId }, 409);
        });
    });

    describe("the core groups API while the class is linked", () => {
        it("refuses to add, remove or re-role a teacher, even for Manage Groups", () => {
            admin("POST", `${GROUPS_URL}/${classA}/addperson/${PERSON_CORE}`, { RoleID: TEACHER }, 409).then((resp) => {
                expect(resp.body.message).to.include("Faith City");
                expect(resp.body).to.include({ ministryId, teamId, teamName: "Faith City" });
            });
            roleOf(classA, PERSON_CORE).should("eq", null);
            admin("DELETE", `${GROUPS_URL}/${classA}/removeperson/${PERSON_TEACHER}`, null, 409);
            admin("POST", `${GROUPS_URL}/${classA}/userRole/${PERSON_TEACHER}`, { roleID: STUDENT }, 409);
            admin("POST", `${GROUPS_URL}/${classA}/userRole/${PERSON_STUDENT}`, { roleID: TEACHER }, 409);
            roleOf(classA, PERSON_TEACHER).should("eq", TEACHER);
            roleOf(classA, PERSON_STUDENT).should("eq", STUDENT);
        });

        it("refuses to rename or delete the Teacher role, or to change the class's type", () => {
            admin("POST", `${GROUPS_URL}/${classA}/roles/${TEACHER}`, { groupRoleName: "Leader" }, 409);
            admin("DELETE", `${GROUPS_URL}/${classA}/roles/${TEACHER}`, null, 409);
            admin("POST", `${GROUPS_URL}/${classA}`, { groupName: `${PREFIX} Class A`, groupType: 0 }, 409);
        });

        it("keeps student writes on the ordinary path", () => {
            admin("POST", `${GROUPS_URL}/${classA}/addperson/${PERSON_CORE}`, { RoleID: STUDENT });
            roleOf(classA, PERSON_CORE).should("eq", STUDENT);
            admin("DELETE", `${GROUPS_URL}/${classA}/removeperson/${PERSON_CORE}`, null);
            roleOf(classA, PERSON_CORE).should("eq", null);
        });
    });

    describe("rollout and unlinking", () => {
        it("with the rollout at v1 nothing is refused and nothing is projected", () => {
            setVersion("v1");
            admin("POST", `${GROUPS_URL}/${classA}/addperson/${PERSON_CORE}`, { RoleID: TEACHER });
            roleOf(classA, PERSON_CORE).should("eq", TEACHER);
            admin("DELETE", `${GROUPS_URL}/${classA}/removeperson/${PERSON_CORE}`, null);
            roleOf(classA, PERSON_CORE).should("eq", null);
            grant(positionLead, PERSON_NEW, 403);
            roleOf(classA, PERSON_NEW).should("eq", null);
            setVersion("v2");
        });

        it("unlinking leaves the class's membership as it is and ends the lock", () => {
            admin("POST", `${TEAMS_URL}/${teamId}`, { classGroupId: null }).then((resp) => {
                expect(resp.body.team.classGroupId).to.eq(null);
            });
            roleOf(classA, PERSON_TEACHER).should("eq", TEACHER);
            admin("POST", `${GROUPS_URL}/${classA}/addperson/${PERSON_CORE}`, { RoleID: TEACHER });
            roleOf(classA, PERSON_CORE).should("eq", TEACHER);
        });

        it("deleting a linked class unlinks the team", () => {
            admin("POST", `${TEAMS_URL}/${otherTeamId}`, { classGroupId: classB }).then((resp) => {
                expect(resp.body.team.classGroupId).to.eq(classB);
            });
            admin("DELETE", `${GROUPS_URL}/${classB}`, null);
            admin("GET", `${TEAMS_URL}/${otherTeamId}`, null).then((resp) => {
                expect(resp.body.team.classGroupId).to.eq(null);
            });
        });
    });
});
