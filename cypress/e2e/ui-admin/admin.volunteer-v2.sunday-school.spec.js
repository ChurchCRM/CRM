/// <reference types="cypress" />

/**
 * Volunteer v2 on screen — the ministry's Sunday School switch (D29) and a class's events when
 * its team changes, the team is deleted, or the Calendar tab deletes events (D28).
 *
 * Order inside every test is API setup → fresh login → cy.visit(), because cy.request() rotates
 * the PHP session cookie (cypress-testing.md). tony.wade (person 3, no Add Events) is made
 * coordinator of both fixture ministries.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const URL = "/api/ministries";
const GROUPS_URL = "/api/groups";
const PREFIX = "UISS2829";
const OFF_MINISTRY = `${PREFIX} Building`;
const ON_MINISTRY = `${PREFIX} Children`;
const OTHER_MINISTRY = `${PREFIX} Coffee Bar`;
const NEW_MINISTRY = `${PREFIX} Youth`;
const PERSON_COORDINATOR = 3;
const PERSON_VOLUNTEER = 8;
const CHURCH_SERVICE_TYPE = 1;

let offMinistryId = 0;
let onMinistryId = 0;
let otherMinistryId = 0;
const classes = {};
const teams = {};

function freshLogin(username, password) {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(username);
    cy.get("input[name=Password]").type(`${password}{enter}`);
    cy.url().should("not.include", "/session/begin");
}

function adminLogin() {
    freshLogin(Cypress.testEnv("admin.username"), Cypress.testEnv("admin.password"));
}

function coordinatorLogin() {
    freshLogin(Cypress.testEnv("standard.username"), Cypress.testEnv("standard.password"));
}

function admin(method, url, body, status = 200) {
    return cy.makePrivateAdminAPICall(method, url, body, status);
}

function isoDate(offsetDays) {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + offsetDays);
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function ownedEvent(ministryId, title, offsetDays, groupId = null) {
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

function eventsTitled(pattern) {
    return cy
        .dbQuery("SELECT event_id FROM events_event WHERE event_title LIKE ?", [`${PREFIX} ${pattern}`])
        .then((result) => result.rows.map((row) => Number(row.event_id)));
}

function cleanupFixtures() {
    const like = [`${PREFIX}%`];
    cy.dbQuery(
        `DELETE vasg FROM volunteer_assignment_vasg vasg
           JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vasg.vasg_vocc_ID
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    cy.dbQuery("DELETE FROM volunteer_scope_vscp WHERE vscp_per_ID = ?", [PERSON_COORDINATOR]);
    for (const table of ["calendar_events", "event_audience"]) {
        cy.dbQuery(`DELETE t FROM ${table} t JOIN events_event e ON e.event_id = t.event_id WHERE e.event_title LIKE ?`, like);
    }
    cy.dbQuery("DELETE FROM events_event WHERE event_title LIKE ?", like);
    admin("GET", `${URL}/ministries`, null).then((resp) => {
        for (const ministry of resp.body.ministries) {
            if (ministry.name.startsWith(PREFIX)) {
                admin("POST", `${URL}/ministries/${ministry.id}`, { active: false }, [200, 404]);
                admin("DELETE", `${URL}/ministries/${ministry.id}`, null, [200, 404]);
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

function openTab(ministryId, tab) {
    cy.visit(`/ministries/${ministryId}`);
    cy.get(`#nav-item-${tab}`).click();
}

function teamAction(teamName, selector) {
    cy.contains("#volunteerTeamsTable tbody tr", teamName).find("button[data-bs-toggle=dropdown]").click();
    cy.contains("#volunteerTeamsTable tbody tr", teamName).find(selector).should("be.visible").click();
}

function tickEvent(title) {
    cy.contains("#volunteerMinistryEventsTable tbody tr", `${PREFIX} ${title}`).find(".ministry-event-select").check();
}

before(() => {
    cy.rememberTestEnv(["admin.username", "admin.password", "standard.username", "standard.password"]);
    cy.useChurchTimeZone();
});

after(() => {
    cy.useHostTimeZone();
});

describe("Volunteer v2 — the Sunday School switch (D29) and a class's events (D28), on screen", () => {
    before(() => {
        admin("POST", SETTING_URL, { value: "v2" });
        cleanupFixtures();

        for (const key of ["A", "B", "C"]) {
            admin("POST", `${GROUPS_URL}/`, { groupName: `${PREFIX} Class ${key}`, isSundaySchool: true }).then((resp) => {
                classes[key] = resp.body.Id;
            });
        }
        admin("POST", `${URL}/ministries`, { name: OFF_MINISTRY }, 201).then((resp) => {
            offMinistryId = resp.body.ministry.id;
        });
        admin("POST", `${URL}/ministries`, { name: OTHER_MINISTRY }, 201).then((resp) => {
            otherMinistryId = resp.body.ministry.id;
        });
        admin("POST", `${URL}/ministries`, { name: ON_MINISTRY, sundaySchool: true }, 201).then((resp) => {
            onMinistryId = resp.body.ministry.id;
        });

        cy.then(() => {
            for (const [key, name] of [
                ["A", `${PREFIX} Faith City Team`],
                ["C", `${PREFIX} Class C Team`],
            ]) {
                admin("POST", `${URL}/ministries/${onMinistryId}/teams`, { name, classGroupId: classes[key] }, 201).then(
                    (resp) => {
                        teams[key] = resp.body.team.id;
                    },
                );
            }
            for (const offset of [-7, 3, 10]) {
                ownedEvent(onMinistryId, `Faith City ${offset}`, offset, classes.A);
            }
            for (const offset of [4, 11]) {
                ownedEvent(onMinistryId, `Class C ${offset}`, offset, classes.C);
            }
            for (const offset of [5, 6]) {
                ownedEvent(onMinistryId, `Workday ${offset}`, offset);
            }
            ownedEvent(onMinistryId, "Staffed workday", 7).then((eventId) => {
                admin("GET", `${URL}/ministries/${otherMinistryId}`).then((detail) => {
                    const otherTeamId = detail.body.teams[0].id;
                    admin(
                        "POST",
                        `${URL}/ministries/${otherMinistryId}/positions`,
                        { name: `${PREFIX} Barista`, teamId: otherTeamId },
                        201,
                    ).then((position) => {
                        const positionId = position.body.position.id;
                        admin("POST", `${URL}/positions/${positionId}/qualifications`, { personId: PERSON_VOLUNTEER }, [
                            200, 201,
                        ]);
                        admin(
                            "POST",
                            `${URL}/ministries/${otherMinistryId}/staffed-events`,
                            { eventId, teamId: otherTeamId },
                            201,
                        ).then((staffed) => {
                            admin(
                                "POST",
                                `${URL}/occurrences/${staffed.body.occurrence.id}/assignments`,
                                { positionId, personId: PERSON_VOLUNTEER },
                                201,
                            );
                        });
                    });
                });
            });
            for (const ministryId of [offMinistryId, onMinistryId]) {
                admin(
                    "POST",
                    `${URL}/scopes`,
                    { personId: PERSON_COORDINATOR, scopeType: "ministry", scopeId: ministryId },
                    [200, 201],
                );
            }
        });
    });

    after(() => {
        cleanupFixtures();
        admin("POST", SETTING_URL, { value: "v1" });
    });

    it("asks the Sunday School question in Add Ministry, off by default", () => {
        adminLogin();
        cy.visit("/ministries/dashboard");
        cy.get("#ministry-new-btn").click();
        cy.get("#ministryCreateModal").should("be.visible");
        cy.get("#ministry-create-name").should("have.focus").type(NEW_MINISTRY);
        cy.get("#ministry-create-sunday-school")
            .should("not.be.checked")
            .parent()
            .should("contain", "Can this ministry provide teachers for Sunday School?");
        cy.get("#ministry-create-sunday-school").check();
        cy.get("#ministry-create-save").click();
        cy.url().should("match", /\/ministries\/\d+$/);

        admin("GET", `${URL}/ministries`).then((resp) => {
            const created = resp.body.ministries.find((ministry) => ministry.name === NEW_MINISTRY);
            expect(created.sundaySchool).to.eq(true);
        });
    });

    it("offers no class anywhere while the switch is off", () => {
        adminLogin();
        openTab(offMinistryId, "overview");
        cy.get("#volunteerTeamsTable tbody tr").should("have.length", 1);
        cy.get("#team-add-btn").click();
        cy.get("#team-form-name").should("have.focus");
        cy.get("#team-form-class-row").should("not.be.visible");
        cy.get("#teamModal [data-bs-dismiss=modal]").first().click();
        cy.get("#teamModal").should("not.be.visible");

        cy.get("#nav-item-schedules").click();
        cy.get("#schedules-loading").should("not.be.visible");
        cy.get("#schedule-add-btn").click();
        cy.get("#schedule-form-name").should("have.focus");
        cy.get("#schedule-form-link-mode option").then(($options) => {
            expect([...$options].map((option) => option.value)).to.deep.eq(["event_type", "ministry"]);
        });
        cy.get("#scheduleModal [data-bs-dismiss=modal]").first().click();
        cy.get("#scheduleModal").should("not.be.visible");

        cy.get("#nav-item-calendar").click();
        cy.get("#ministry-event-add-btn").click();
        cy.get("#ministry-event-form-title").should("have.focus");
        cy.get("#ministry-event-form-class-row").should("not.be.visible");
    });

    it("shows the switch read-only to a coordinator in Edit ministry", () => {
        coordinatorLogin();
        cy.visit(`/ministries/${offMinistryId}`);
        cy.get("#ministry-edit-btn").click();
        cy.get("#ministry-edit-name").should("have.focus").and("have.value", OFF_MINISTRY);
        cy.get("#ministry-edit-sunday-school").should("be.disabled").and("not.be.checked");
        cy.get("#ministry-edit-sunday-school-note").should("contain", "Only a volunteer manager can change this.");
    });

    it("lets a manager turn it on in Edit ministry, and the class controls appear", () => {
        adminLogin();
        cy.visit(`/ministries/${offMinistryId}`);
        cy.get("#ministry-edit-btn").click();
        cy.get("#ministry-edit-name").should("have.focus");
        cy.get("#ministry-edit-sunday-school").should("be.enabled").and("not.be.checked").check();
        cy.get("#ministry-edit-save").click();
        cy.get("#ministryEditModal").should("not.be.visible");
        cy.get("#volunteerTeamsTable tbody tr").should("have.length", 1);

        cy.get("#team-add-btn").click();
        cy.get("#team-form-name").should("have.focus");
        cy.get("#team-form-class-row").should("be.visible");
        cy.get(`#team-form-class option[value="${classes.B}"]`).should("exist");

        admin("GET", `${URL}/ministries/${offMinistryId}`).then((resp) => {
            expect(resp.body.ministry.sundaySchool).to.eq(true);
        });
    });

    it("asks what happens to the old class's events when a team's class changes", () => {
        adminLogin();
        cy.visit(`/ministries/${onMinistryId}`);
        teamAction(`${PREFIX} Faith City Team`, ".volunteer-team-edit");
        cy.get("#team-form-name").should("have.focus");
        cy.get("#team-form-class").should("have.value", String(classes.A));
        cy.get("#team-form-class-events").should("not.be.visible");

        cy.get("#team-form-class").select(String(classes.B));
        cy.get("#team-form-class-events")
            .should("be.visible")
            .and("contain", `${ON_MINISTRY} created 3 events for ${PREFIX} Class A (2 upcoming).`);
        cy.get('input[name="team-form-class-events"][value="keep"]').should("be.checked");
        cy.get("#team-form-class-events-keep").should("have.text", `Keep them on ${PREFIX} Class A`);
        cy.get("#team-form-class-events-remove").should("have.text", "Remove the class from them");
        cy.get("#team-form-class-events-move").should("have.text", `Move them to ${PREFIX} Class B`);

        cy.get("#team-form-class").select("0");
        cy.get("#team-form-class-events-move-row").should("not.be.visible");
        cy.get("#team-form-class").select(String(classes.B));
        cy.get('input[name="team-form-class-events"][value="move"]').check();
        cy.get("#team-form-save").click();
        cy.get("#teamModal").should("not.be.visible");

        admin("GET", `${URL}/teams/${teams.A}/class-events`).then((resp) => {
            expect(resp.body).to.include({ classGroupId: classes.B, total: 3, upcoming: 2 });
        });
    });

    it("asks the same when the team is deleted, and deletes those events on request", () => {
        adminLogin();
        cy.visit(`/ministries/${onMinistryId}`);
        teamAction(`${PREFIX} Class C Team`, ".volunteer-team-delete");
        cy.get(".bootbox")
            .should("be.visible")
            .and("contain", `${ON_MINISTRY} created 2 events for ${PREFIX} Class C (2 upcoming).`)
            .and("contain", "Delete those events");
        cy.get('.bootbox input[name="team-delete-class-events"][value="keep"]').should("be.checked");
        cy.get('.bootbox input[name="team-delete-class-events"][value="delete"]').check();
        cy.get(".bootbox .btn-danger").click();
        cy.get(".notyf__toast").should("contain", "Team deleted");
        cy.contains("#volunteerTeamsTable tbody tr", `${PREFIX} Class C Team`).should("not.exist");
        eventsTitled("Class C%").should("deep.eq", []);
    });

    it("names other ministries' staffing when deleting events, and a coordinator is refused", () => {
        coordinatorLogin();
        openTab(onMinistryId, "calendar");
        cy.get("#ministry-events-loading").should("not.be.visible");
        cy.get("#ministry-events-delete-btn").should("be.disabled");
        tickEvent("Staffed workday");
        tickEvent("Workday 5");
        cy.get("#ministry-events-delete-btn").should("be.enabled").and("contain", "Delete events (2)");
        cy.get("#ministry-events-delete-btn").click();
        cy.get(".bootbox").should("be.visible").and("contain", "Delete 2 events?").and("contain", OTHER_MINISTRY);
        cy.get(".bootbox .btn-danger").click();
        cy.get(".notyf__toast").should("contain", OTHER_MINISTRY);
        eventsTitled("%Workday%").its("length").should("eq", 3);
    });

    it("selects every row and deletes them all for someone who manages the calendar", () => {
        adminLogin();
        openTab(onMinistryId, "calendar");
        cy.get("#volunteerMinistryEventsTable tbody tr").should("have.length", 5);
        cy.get("#ministry-events-select-all").first().check();
        cy.get("#volunteerMinistryEventsTable .ministry-event-select:checked").should("have.length", 5);
        cy.get("#ministry-events-delete-btn").should("contain", "Delete events (5)").click();
        cy.get(".bootbox").should("be.visible").and("contain", "Delete 5 events?");
        cy.get(".bootbox .btn-danger").click();
        cy.get(".notyf__toast").should("contain", "5 events deleted");
        cy.get("#ministry-events-empty").should("be.visible");
        eventsTitled("%Workday%").should("deep.eq", []);
    });
});
