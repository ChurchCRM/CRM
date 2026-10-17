/// <reference types="cypress" />

/**
 * Volunteer v2 — the ministry page's team-only view (#10530).
 *
 * Persona: person 3 (`standard.username`), a staff login with Manage My Ministries,
 * given one `team` scope and no ministry scope. The API side of the same persona is
 * pinned in private.volunteer.team-leader-admin.spec.js.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const VOLUNTEER_URL = "/api/ministries";
const DASHBOARD_URL = "/ministries/dashboard";

const PERSON_LEADER = 3;
const POOL_MEMBER = 8;

const PREFIX = "UI10530";
const MINISTRY_NAME = `${PREFIX} Hospitality`;
const TEAM_LED = `${PREFIX} Greeters`;
const TEAM_OTHER = `${PREFIX} Ushers`;
const POSITION_LED = `${PREFIX} Door`;
const POSITION_OTHER = `${PREFIX} Aisle`;
const POSITION_NEW = `${PREFIX} Welcome Desk`;
const EVENT_TITLE = `${PREFIX} Sunday`;
const SCHEDULE_OTHER = `${PREFIX} Ushers rota`;
const SCHEDULE_NEW = `${PREFIX} Greeters rota`;

let ministryId = 0;
let otherMinistryId = 0;
let teamLed = 0;
let teamOther = 0;
let posLed = 0;

function login(usernameKey, passwordKey) {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.testEnv(usernameKey));
    cy.get("input[name=Password]").type(`${Cypress.testEnv(passwordKey)}{enter}`);
    cy.url().should("not.include", "/session/begin");
}

const leaderLogin = () => login("standard.username", "standard.password");
const adminLogin = () => login("admin.username", "admin.password");

function adminApi(method, url, body, expectedStatus = 200) {
    return cy.makePrivateAdminAPICall(method, url, body, expectedStatus);
}

function isoDate(offsetDays) {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + offsetDays);
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function cleanupFixtures() {
    adminApi("GET", `${VOLUNTEER_URL}/ministries`, null, 200).then((resp) => {
        for (const ministry of resp.body.ministries) {
            if (ministry.name.startsWith(PREFIX)) {
                adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministry.id}`, { active: false }, [200, 404]);
                adminApi("DELETE", `${VOLUNTEER_URL}/ministries/${ministry.id}`, null, [200, 404]);
            }
        }
    });
    adminApi("GET", "/api/events").then((resp) => {
        cy.cleanupEvents(resp.body.Events.filter((e) => e.Title.startsWith(PREFIX)).map((e) => e.Id));
    });
}

before(() => {
    cy.rememberTestEnv(["admin.username", "admin.password", "standard.username", "standard.password"]);
    cy.useChurchTimeZone();
});

after(() => {
    cy.useHostTimeZone();
});

describe("Volunteer v2 — the team-only ministry view (#10530)", () => {
    before(() => {
        adminApi("POST", SETTING_URL, { value: "v2" }, 200);
        cleanupFixtures();

        adminApi("POST", `${VOLUNTEER_URL}/ministries`, { name: MINISTRY_NAME }, 201).then((resp) => {
            ministryId = resp.body.ministry.id;
        });
        adminApi("POST", `${VOLUNTEER_URL}/ministries`, { name: `${PREFIX} Missions` }, 201).then((resp) => {
            otherMinistryId = resp.body.ministry.id;
        });
        cy.then(() => {
            adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}/teams`, { name: TEAM_LED }, 201).then((resp) => {
                teamLed = resp.body.team.id;
            });
            adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}/teams`, { name: TEAM_OTHER }, 201).then((resp) => {
                teamOther = resp.body.team.id;
            });
        });
        cy.then(() => {
            adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}/positions`, {
                name: POSITION_LED,
                teamId: teamLed,
                order: 1,
            }, 201).then((resp) => {
                posLed = resp.body.position.id;
            });
            adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}/positions`, {
                name: POSITION_OTHER,
                teamId: teamOther,
            }, 201);
            adminApi("POST", `${VOLUNTEER_URL}/scopes`, {
                personId: PERSON_LEADER,
                scopeType: "team",
                scopeId: teamLed,
            }, [200, 201]);
            adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${POOL_MEMBER}`, null, [200, 201]);
        });
        cy.then(() => {
            const first = (7 - new Date().getDay()) % 7 || 7;
            [0, 7, 14].forEach((week) => {
                const day = isoDate(first + week);
                adminApi("POST", "/api/events", {
                    Title: EVENT_TITLE,
                    Type: 1,
                    Start: `${day}T09:00:00`,
                    End: `${day}T10:00:00`,
                    MinistryId: ministryId,
                    PinnedCalendars: [],
                });
            });
        });
        cy.then(() => {
            adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`, {
                name: SCHEDULE_OTHER,
                linkMode: "ministry",
                titleFilter: EVENT_TITLE,
                teamId: teamOther,
                windowStart: isoDate(0),
                windowEnd: isoDate(28),
            }, 201);
        });
    });

    after(() => {
        cleanupFixtures();
        adminApi("POST", SETTING_URL, { value: "v1" }, 200);
    });

    it("Links the ministry and the team from the dashboard instead of 'View only'", () => {
        leaderLogin();
        cy.visit(DASHBOARD_URL);

        cy.get(`#volunteer-scope-ministries a[href$="/ministries/${ministryId}"]`, { timeout: 15000 })
            .should("contain.text", MINISTRY_NAME)
            .and("contain.text", "Team leader");
        cy.get("#volunteer-scope-ministries").should("not.contain.text", "View only");

        cy.get(`#volunteer-scope-teams a[href$="/ministries/${ministryId}?team=${teamLed}"]`)
            .should("contain.text", TEAM_LED)
            .click();
        cy.url().should("include", `/ministries/${ministryId}?team=${teamLed}`);
        cy.get("#volunteer-ministry").should("exist");
    });

    it("Opens on Positions with four tabs, showing only their team", () => {
        leaderLogin();
        cy.visit(`/ministries/${ministryId}`);

        cy.url().should("not.include", "access-denied");
        cy.get(".page-title").should("contain.text", MINISTRY_NAME);
        cy.contains(`Your teams: ${TEAM_LED}`).should("exist");
        cy.get("#volunteer-ministry-tabs .nav-link").should("have.length", 4);
        cy.get("#nav-item-positions").should("have.class", "active");

        cy.get("#volunteerPositionsTable tbody", { timeout: 15000 }).should("contain.text", POSITION_LED);
        cy.get("#volunteerPositionsTable tbody").should("not.contain.text", POSITION_OTHER);

        cy.get("#nav-item-schedules").click();
        cy.get("#schedules-empty", { timeout: 15000 }).should("be.visible");

        cy.get("#nav-item-occurrences").click();
        cy.get("#occurrences-empty", { timeout: 15000 }).should("be.visible");
    });

    it("Shows none of the ministry-wide controls", () => {
        leaderLogin();
        cy.visit(`/ministries/${ministryId}`);
        cy.get("#volunteerPositionsTable tbody", { timeout: 15000 }).should("contain.text", POSITION_LED);

        [
            "#ministry-edit-btn",
            "#ministry-deactivate-btn",
            "#ministry-delete-btn",
            "#nav-item-overview",
            "#nav-item-calendar",
            "#nav-item-help-wanted",
            "#volunteer-teams-card",
            "#volunteer-scope-panel",
            "#volunteer-help-wanted",
            "#qualification-add-person",
            "#qualification-cart-btn",
            "#qualification-team-filter",
            "#occurrence-team-filter",
            "#teamModal",
            "#ministryEditModal",
            "#addVolunteerModal",
            "#addFromCartModal",
            "#ministryEventModal",
        ].forEach((selector) => {
            cy.get(selector).should("not.exist");
        });
    });

    it("Adds a position to their team and ticks a qualification", () => {
        leaderLogin();
        cy.visit(`/ministries/${ministryId}`);
        cy.get("#volunteerPositionsTable tbody", { timeout: 15000 }).should("contain.text", POSITION_LED);

        cy.get("#position-add-btn").click();
        cy.get("#positionModal").should("be.visible");
        cy.get("#position-form-team option").should("have.length", 1).and("have.value", String(teamLed));
        cy.get("#position-form-name").should("be.focused").type(POSITION_NEW);
        cy.get("#position-form-save").click();
        cy.get("#volunteerPositionsTable tbody", { timeout: 15000 }).should("contain.text", POSITION_NEW);

        cy.get("#nav-item-volunteers").click();
        cy.get(`.volunteer-qual-toggle[data-person-id="${POOL_MEMBER}"][data-position-id="${posLed}"]`, {
            timeout: 15000,
        }).check();
        cy.get(`.volunteer-qual-toggle[data-person-id="${POOL_MEMBER}"][data-position-id="${posLed}"]`, {
            timeout: 15000,
        })
            .should("not.be.disabled")
            .and("be.checked");
        cy.get(".volunteer-remove-volunteer").should("not.exist");

        cy.then(() => {
            adminApi("GET", `${VOLUNTEER_URL}/positions/${posLed}/qualifications?active=1`).then((resp) => {
                expect(resp.body.qualifications.filter((q) => q.personId === POOL_MEMBER)).to.have.length(1);
            });
        });
    });

    it("Adds a schedule for their team, whose Save makes its dates", () => {
        leaderLogin();
        cy.visit(`/ministries/${ministryId}`);
        cy.get("#volunteerPositionsTable tbody", { timeout: 15000 }).should("contain.text", POSITION_LED);

        cy.get("#nav-item-schedules").click();
        cy.get("#schedule-add-btn", { timeout: 15000 }).click();
        cy.get("#scheduleModal").should("be.visible");
        cy.get("#schedule-form-team option").should("have.length", 1).and("have.value", String(teamLed));
        cy.get("#schedule-form-name").clear().type(SCHEDULE_NEW);
        cy.get("#schedule-form-link-mode").select("ministry");
        cy.get(`#schedule-form-title-filter option[value="${EVENT_TITLE}"]`, { timeout: 10000 }).should("exist");
        cy.get("#schedule-form-title-filter").select(EVENT_TITLE);
        cy.get("#schedule-form-window-start").clear().type(isoDate(0));
        cy.get("#schedule-form-window-end").clear().type(isoDate(28));
        cy.get("#schedule-form-save").click();

        cy.get("#nav-item-occurrences", { timeout: 15000 }).should("have.class", "active");
        cy.get("#volunteerOccurrencesTable tbody", { timeout: 15000 }).should("contain.text", SCHEDULE_NEW);
        cy.get("#volunteerOccurrencesTable tbody").should("not.contain.text", SCHEDULE_OTHER);

        cy.get("#nav-item-schedules").click();
        cy.get("#volunteerSchedulesTable tbody", { timeout: 15000 }).should("contain.text", SCHEDULE_NEW);
        cy.get("#volunteerSchedulesTable tbody").should("not.contain.text", SCHEDULE_OTHER);
    });

    it("Staffs a date on the occurrence page and returns to the team view", () => {
        leaderLogin();
        cy.visit(`/ministries/${ministryId}?tab=occurrences`);

        cy.get("#volunteerOccurrencesTable tbody tr td a", { timeout: 15000 }).first().click();
        cy.url().should("include", "/ministries/occurrences/");
        cy.get(`.volunteer-requirement[data-position-id="${posLed}"] .volunteer-assign-btn`, { timeout: 15000 }).click();
        cy.get("#volunteer-assign-modal").should("be.visible");
        cy.get("#assign-person-select option", { timeout: 10000 }).should("have.length.greaterThan", 1);
        cy.get("#assign-person-select").select(String(POOL_MEMBER), { force: true });
        cy.get("#assign-save").click();
        cy.get(`.volunteer-requirement[data-position-id="${posLed}"]`, { timeout: 15000 }).should("contain.text", "Herminia");

        cy.get("#occurrence-back").click();
        cy.url().should("include", `/ministries/${ministryId}?`).and("include", "tab=occurrences");
        cy.get("#nav-item-occurrences", { timeout: 15000 }).should("have.class", "active");
        cy.get("#volunteerOccurrencesTable tbody", { timeout: 15000 }).should("contain.text", SCHEDULE_NEW);
    });

    it("Is refused a ministry where they lead no team", () => {
        leaderLogin();
        cy.visit(`/ministries/${otherMinistryId}`);
        cy.url().should("include", "access-denied");
    });

    it("Leaves the administrator's view of the ministry unchanged", () => {
        adminLogin();
        cy.visit(`/ministries/${ministryId}`);

        cy.get("#volunteer-ministry-tabs .nav-link").should("have.length", 7);
        cy.get("#nav-item-overview").should("have.class", "active");
        cy.get("#ministry-edit-btn").should("exist");
        cy.get("#volunteer-teams-card").should("exist");
        cy.get("#qualification-add-person").should("exist");
        cy.get("#qualification-team-filter").should("exist");
        cy.contains("Your team").should("not.exist");
    });
});
