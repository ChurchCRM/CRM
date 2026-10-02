/// <reference types="cypress" />

/**
 * Volunteer v2 — staffing counts read as status words (D34): "Needs 1 more",
 * "Covered · 1 more welcome", "Optional · 1 of up to 2", "Full · 3 of 3", on the occurrence
 * page, the Calendar tab, the Occurrences tab, the dashboard and the core event view.
 *
 * One staffed event owned by the ministry, four positions:
 *
 *   Barista  min 2 / max 3, three assigned  → Full · 3 of 3
 *   Cashier  min 2 / max 3, two assigned    → Covered · 1 more welcome
 *   Greeter  min 0 / max 2, one assigned    → Optional · 1 of up to 2
 *   Runner   min 1 / max 1, nobody, until the rollup tests assign one
 *
 * Fixtures go in through the admin API; order in every hook is fixture → login → visit,
 * because cy.request() rotates the PHP session cookie.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const VOLUNTEER_URL = "/api/ministries";
const MINISTRIES_URL = `${VOLUNTEER_URL}/ministries`;

const PREFIX = "UILABEL34";
const MINISTRY_NAME = `${PREFIX} Coffee Bar`;
const EVENT_TITLE = `${PREFIX} Sunday Coffee`;

const POSITIONS = {
    barista: { name: `${PREFIX} Barista`, min: 2, max: 3, people: [14, 15, 16] },
    cashier: { name: `${PREFIX} Cashier`, min: 2, max: 3, people: [17, 18] },
    greeter: { name: `${PREFIX} Greeter`, min: 0, max: 2, people: [19] },
    runner: { name: `${PREFIX} Runner`, min: 1, max: 1, people: [] },
};
const RUNNER_PERSON = 20;

let ministryId = 0;
let teamName = "";
let eventId = 0;
let occurrenceId = 0;
const positionIds = {};

function admin(method, url, body, status = 200) {
    return cy.makePrivateAdminAPICall(method, url, body, status);
}

function freshAdminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.env("admin.username"));
    cy.get("input[name=Password]").type(`${Cypress.env("admin.password")}{enter}`);
    cy.url().should("not.include", "/session/begin");
}

function isoDate(offsetDays) {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + offsetDays);
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function assignAccepted(positionId, personId) {
    admin("POST", `${VOLUNTEER_URL}/positions/${positionId}/qualifications`, { personId }, [200, 201]);
    admin(
        "POST",
        `${VOLUNTEER_URL}/occurrences/${occurrenceId}/assignments`,
        { positionId, personId, allowOutsidePool: true },
        201,
    ).then((resp) => {
        admin("POST", `${VOLUNTEER_URL}/assignments/${resp.body.assignment.id}/status`, { status: "accepted" });
    });
}

function cleanupFixtures() {
    admin("GET", MINISTRIES_URL).then((resp) => {
        for (const ministry of resp.body.ministries) {
            if (ministry.name.startsWith(PREFIX)) {
                admin("POST", `${MINISTRIES_URL}/${ministry.id}`, { active: false }, [200, 404]);
                admin("DELETE", `${MINISTRIES_URL}/${ministry.id}`, null, [200, 404]);
            }
        }
    });
    cy.dbQuery("DELETE t FROM calendar_events t JOIN events_event e ON e.event_id = t.event_id WHERE e.event_title LIKE ?", [
        `${PREFIX}%`,
    ]);
    cy.dbQuery("DELETE FROM events_event WHERE event_title LIKE ?", [`${PREFIX}%`]);
}

function card(key) {
    return cy.get(`.volunteer-requirement[data-position-id="${positionIds[key]}"]`);
}

describe("Volunteer v2 — staffing counts read as status words (D34)", () => {
    before(() => {
        admin("POST", SETTING_URL, { value: "v2" });
        cleanupFixtures();

        admin("POST", MINISTRIES_URL, { name: MINISTRY_NAME, description: "D34 fixture" }, 201).then((resp) => {
            ministryId = resp.body.ministry.id;
            admin("GET", `${MINISTRIES_URL}/${ministryId}`).then((detail) => {
                const team = detail.body.teams[0];
                teamName = team.name;
                Object.entries(POSITIONS).forEach(([key, position], order) => {
                    admin(
                        "POST",
                        `${MINISTRIES_URL}/${ministryId}/positions`,
                        { name: position.name, teamId: team.id, order },
                        201,
                    ).then((created) => {
                        positionIds[key] = created.body.position.id;
                    });
                });
                admin(
                    "POST",
                    `${MINISTRIES_URL}/${ministryId}/events`,
                    { title: EVENT_TITLE, eventTypeId: 1, date: isoDate(3), startTime: "09:00", endTime: "10:00" },
                    201,
                ).then((events) => {
                    eventId = events.body.events[0].id;
                    admin(
                        "POST",
                        `${MINISTRIES_URL}/${ministryId}/staffed-events`,
                        {
                            eventId,
                            teamId: team.id,
                            startOffsetMinutes: 0,
                            endOffsetMinutes: 0,
                            requirements: Object.keys(POSITIONS).map((key) => ({
                                positionId: positionIds[key],
                                minCount: POSITIONS[key].min,
                                maxCount: POSITIONS[key].max,
                            })),
                        },
                        201,
                    ).then((staffed) => {
                        occurrenceId = staffed.body.occurrence.id;
                        for (const key of Object.keys(POSITIONS)) {
                            for (const personId of POSITIONS[key].people) {
                                assignAccepted(positionIds[key], personId);
                            }
                        }
                    });
                });
            });
        });
    });

    after(() => {
        cleanupFixtures();
        admin("POST", SETTING_URL, { value: "v1" });
    });

    describe("one position, on the occurrence page", () => {
        beforeEach(() => {
            freshAdminLogin();
            cy.visit(`/ministries/occurrences/${occurrenceId}`);
            cy.get("#requirements-loading").should("not.be.visible");
        });

        it("says Full · 3 of 3 for min 2 / max 3 with three assigned", () => {
            card("barista").find(".requirement-counts").should("have.text", "Full · 3 of 3").and("have.attr", "data-tone", "success");
            card("barista").find(".requirement-detail").should("not.be.visible");
        });

        it("says Covered · 1 more welcome for min 2 / max 3 with two assigned", () => {
            card("cashier")
                .find(".requirement-counts")
                .should("have.text", "Covered · 1 more welcome")
                .and("have.attr", "data-tone", "success");
            card("cashier").find(".requirement-detail").should("have.text", "2 assigned, 2 needed, room for 1 more");
        });

        it("says Optional · 1 of up to 2 for min 0 / max 2 with one assigned", () => {
            card("greeter")
                .find(".requirement-counts")
                .should("have.text", "Optional · 1 of up to 2")
                .and("have.attr", "data-tone", "success");
        });

        it("says Needs 1 more, in red, for a short position", () => {
            card("runner").find(".requirement-counts").should("have.text", "Needs 1 more").and("have.attr", "data-tone", "danger");
            card("runner").find(".requirement-detail").should("have.text", "0 of 1");
        });

        it("never shows the old assigned / minimum fraction", () => {
            cy.get(".requirement-counts").each(($badge) => {
                expect($badge.text()).not.to.match(/\d+ \/ \d+/);
            });
        });
    });

    describe("the rollups", () => {
        before(() => {
            assignAccepted(positionIds.runner, RUNNER_PERSON);
        });

        beforeEach(() => {
            freshAdminLogin();
        });

        it("words the Calendar tab chip as the team's rollup", () => {
            cy.visit(`/ministries/${ministryId}`);
            cy.get("#nav-item-calendar").click();
            cy.get("#ministry-events-loading").should("not.be.visible");
            cy.contains("#volunteerMinistryEventsTable tbody tr", EVENT_TITLE)
                .find(".ministry-event-staffing")
                .should("have.text", `${teamName}: Covered · 2 more welcome`)
                .and("have.attr", "data-tone", "success")
                .and("have.attr", "title")
                .and("contain", "7 assigned, 5 needed, room for 2 more");
        });

        it("words the dashboard's Upcoming Staffed badge as the occurrence's rollup", () => {
            cy.visit("/ministries/dashboard");
            cy.get("#volunteer-upcoming-content").should("be.visible");
            cy.get("#volunteer-upcoming-card input[type=search]").type(PREFIX);
            cy.contains("#volunteer-upcoming-table tbody tr", MINISTRY_NAME)
                .find(".volunteer-staffed-badge")
                .should("have.text", "Covered · 2 more welcome")
                .and("have.attr", "data-tone", "success");
        });

        it("puts the rollup in the Occurrences tab's icon tooltip", () => {
            cy.visit(`/ministries/${ministryId}`);
            cy.get("#nav-item-occurrences").click();
            cy.get("#occurrences-loading").should("not.be.visible");
            cy.get("#volunteerOccurrencesTable tbody tr")
                .first()
                .find(".volunteer-occurrence-staffing")
                .should("have.attr", "data-tone", "success")
                .and("have.class", "text-green")
                .and("have.attr", "title")
                .and("contain", "Covered · 2 more welcome");
        });

        it("words the core event view's Volunteers card the same way", () => {
            cy.visit(`/event/view/${eventId}`);
            cy.get("#event-volunteers-card .volunteer-staffing-badge", { timeout: 15000 })
                .should("have.text", "Covered · 2 more welcome")
                .and("have.attr", "data-tone", "success");
            cy.get("#event-volunteers-card .volunteer-staffing-detail").should(
                "have.text",
                "7 assigned, 5 needed, room for 2 more",
            );
        });
    });
});
