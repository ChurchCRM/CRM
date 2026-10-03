/// <reference types="cypress" />

/**
 * Volunteer v2 — linking a team to a Sunday School class, on screen (D23).
 *
 * Order inside every hook is API setup → freshAdminLogin() → cy.visit(), because
 * cy.request() rotates the PHP session cookie (cypress-testing.md). No cy.dbQuery():
 * ui-admin specs run under docker-admin.config.ts, which does not register it.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const MINISTRIES_URL = "/api/ministries/ministries";
const GROUPS_URL = "/api/groups";

const PREFIX = "UITCLS23";
const MINISTRY_NAME = `${PREFIX} Children`;
const CLASS_NAME = `${PREFIX} Faith City`;
const TEAM_NAME = `${PREFIX} Faith City Team`;
const TEACHER = 1;
const STUDENT = 2;
const PERSON_TEACHER = 20;
const PERSON_STUDENT = 21;

let ministryId = 0;
let classId = 0;
let teamId = 0;

function freshAdminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.env("admin.username"));
    cy.get("input[name=Password]").type(`${Cypress.env("admin.password")}{enter}`);
    cy.url().should("not.include", "/session/begin");
}

function admin(method, url, body, status = 200) {
    return cy.makePrivateAdminAPICall(method, url, body, status);
}

function cleanupFixtures() {
    admin("GET", MINISTRIES_URL, null).then((resp) => {
        for (const ministry of resp.body.ministries) {
            if (ministry.name.startsWith(PREFIX)) {
                admin("POST", `${MINISTRIES_URL}/${ministry.id}`, { active: false }, [200, 404]);
                admin("DELETE", `${MINISTRIES_URL}/${ministry.id}`, null, [200, 404]);
            }
        }
    });
    admin("GET", `${GROUPS_URL}/`, null).then((resp) => {
        for (const group of resp.body) {
            if (String(group.Name).startsWith(PREFIX)) {
                admin("DELETE", `${GROUPS_URL}/${group.Id}`, null, [200, 404]);
            }
        }
    });
}

function roleOf(personId) {
    return admin("GET", `${GROUPS_URL}/${classId}/members`, null).then((resp) => {
        const member = resp.body.Person2group2roleP2g2rs.find((m) => Number(m.PersonId) === personId);
        return member ? Number(member.RoleId) : null;
    });
}

describe("Volunteer v2 — a team linked to a Sunday School class, on screen (D23)", () => {
    before(() => {
        admin("POST", SETTING_URL, { value: "v2" });
        cleanupFixtures();
        admin("POST", MINISTRIES_URL, { name: MINISTRY_NAME, sundaySchool: true }, 201).then((resp) => {
            ministryId = resp.body.ministry.id;
        });
        admin("POST", `${GROUPS_URL}/`, { groupName: CLASS_NAME, isSundaySchool: true }).then((resp) => {
            classId = resp.body.Id;
            admin("POST", `${GROUPS_URL}/${classId}/addperson/${PERSON_TEACHER}`, { RoleID: TEACHER });
            admin("POST", `${GROUPS_URL}/${classId}/addperson/${PERSON_STUDENT}`, { RoleID: STUDENT });
        });
    });

    after(() => {
        cleanupFixtures();
        admin("POST", SETTING_URL, { value: "v1" });
    });

    it("links a class from the team dialog and imports its teachers", () => {
        freshAdminLogin();
        cy.visit(`/ministries/${ministryId}`);
        cy.get("#volunteerTeamsTable tbody tr").should("have.length.at.least", 1);

        cy.get("#team-add-btn").click();
        cy.get("#team-form-name").should("have.focus").type(TEAM_NAME);
        cy.get("#team-form-class option").should("have.length.greaterThan", 1);
        cy.get("#team-form-import").should("not.be.visible");
        cy.get("#team-form-class").select(String(classId));
        cy.get("#team-form-import").should("be.visible");
        cy.get("#team-form-import-count").should("contain", "1");
        cy.get("#team-form-import-position").should("have.value", "new");
        cy.get("#team-form-save").click();
        cy.get("#teamModal").should("not.be.visible");

        cy.contains("#volunteerTeamsTable tbody tr", TEAM_NAME)
            .find(".volunteer-team-class-link")
            .should("have.text", CLASS_NAME)
            .and("have.attr", "href")
            .and("include", `/groups/sundayschool/class/${classId}`);

        admin("GET", `${MINISTRIES_URL}/${ministryId}`, null).then((resp) => {
            const team = resp.body.teams.find((t) => t.name === TEAM_NAME);
            expect(team.classGroupId).to.eq(classId);
            teamId = team.id;
            const position = resp.body.positions.find((p) => p.teamId === team.id);
            expect(position.name).to.eq("Teacher");
            admin("GET", `/api/ministries/positions/${position.id}/qualifications`, null).then((quals) => {
                expect(quals.body.qualifications.map((q) => q.personId)).to.deep.eq([PERSON_TEACHER]);
            });
        });
    });

    it("editing the team keeps the link and does not ask again", () => {
        freshAdminLogin();
        cy.visit(`/ministries/${ministryId}`);
        cy.contains("#volunteerTeamsTable tbody tr", TEAM_NAME).find("button[data-bs-toggle=dropdown]").click();
        cy.contains("#volunteerTeamsTable tbody tr", TEAM_NAME).find(".volunteer-team-edit").click();
        cy.get("#team-form-name").should("have.focus");
        cy.get("#team-form-class option").should("have.length.greaterThan", 1);
        cy.get("#team-form-class").should("have.value", String(classId));
        cy.get("#team-form-import").should("not.be.visible");
    });

    it("the class page says where its teachers are managed and stops offering teacher moves", () => {
        freshAdminLogin();
        cy.visit(`/groups/sundayschool/class/${classId}`);
        cy.get("#class-teachers-managed-note")
            .should("contain", MINISTRY_NAME)
            .and("contain", TEAM_NAME)
            .find(`a[href$="/ministries/${ministryId}"]`)
            .should("exist");
        cy.get('.ss-move-role[data-role="Teacher"]').should("not.exist");
        cy.get('.ss-copy-role[data-role="Teacher"]').should("not.exist");
        cy.get('.ss-move-role[data-role="all"]').should("not.exist");
        cy.get('.ss-move-role[data-role="Student"]').should("exist");
    });

    it("the group view offers no teacher removal", () => {
        freshAdminLogin();
        cy.visit(`/groups/view/${classId}`);
        cy.get("#class-teachers-managed-note").should("contain", TEAM_NAME);
        cy.get(`#membersTable .remove-member-btn[data-personid="${PERSON_STUDENT}"]`).should("exist");
        cy.get(`#membersTable .remove-member-btn[data-personid="${PERSON_TEACHER}"]`).should("not.exist");
        cy.get(`#membersTable .changeMembership[data-personid="${PERSON_TEACHER}"]`).should("not.exist");
    });

    it("the portal team page names the class, read-only", () => {
        freshAdminLogin();
        cy.visit(`/portal/teams/${teamId}`);
        cy.get("#portal-team-class").should("contain", CLASS_NAME).find("a").should("not.exist");
    });

    it("the role editor refuses to make a student a teacher", () => {
        freshAdminLogin();
        cy.visit(`/groups/${classId}/members/${PERSON_STUDENT}/role?return=group`);
        cy.get("#NewRole").select(String(TEACHER));
        cy.get("input[name=Submit]").click();
        cy.location("pathname").should("match", new RegExp(`/groups/view/${classId}$`));
        cy.get(".notyf__toast").should("contain", TEAM_NAME);
        roleOf(PERSON_STUDENT).should("eq", STUDENT);
    });
});
