/// <reference types="cypress" />

/**
 * Volunteer v2 — the staffing-needs editors (§2.10, epic #9701).
 *
 * The defect, as it was reported: a ministry, a team, a position, a schedule and nine
 * generated occurrences — and every occurrence showed Filled `0/0`, Still Needed "Fully
 * Staffed", and a staffing page saying "Nobody is Needed yet" with no way to say who was
 * needed. Staffing requirements were a separate entity with an API and no screen, so a
 * schedule had none and an empty plan reads as a satisfied one to any test that only
 * counts gaps.
 *
 * This spec walks that exact path through the browser:
 *
 *   1. A schedule created through the schedule form, with its default staffing needs,
 *      yields occurrences showing `0/1` filled and "1 Lead Teacher" still needed —
 *      never "Fully staffed".
 *   2. A schedule saved with every need UNCHECKED warns in the form and its occurrences
 *      say "No staffing needs set", which is a different sentence from "Fully staffed"
 *      and links to where it can be fixed.
 *   3. The occurrence page's "Edit staffing needs" changes that one week's counts, and
 *      "Use the schedule's needs" puts it back.
 *
 * Every one of those fails on the unchanged branch: (1) and (2) because the form has no
 * needs section at all, (3) because the button does not exist.
 *
 * Fixtures go in through the very API the pages call, so a shape change breaks the
 * fixture as loudly as it breaks the screen. Order in every hook is API setup →
 * freshAdminLogin() → cy.visit(), because cy.request() rotates the PHP session cookie
 * (cypress-testing.md). Cleanup runs in `before` as well as `after`.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const VOLUNTEER_URL = "/api/ministries";

const PREFIX = "UINEEDS";
const MINISTRY_NAME = `${PREFIX} Children's Ministry`;
const TEAM_NAME = `${PREFIX} Wednesday Night`;
/** The team the ministry is created with; this spec adds "Wednesday Night" beside it. */
const DEFAULT_TEAM_NAME = `${MINISTRY_NAME} Team`;
const POSITION_LEAD = `${PREFIX} Lead Teacher`;
const POSITION_HELPER = `${PREFIX} Helper`;

let ministryId = 0;
let teamId = 0;
let posLead = 0;
let posHelper = 0;

// Local helper — NOT a cy.* command (cypress-testing.md).
function freshAdminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.env("admin.username"));
    cy.get("input[name=Password]").type(Cypress.env("admin.password") + "{enter}");
    cy.url().should("not.include", "/session/begin");
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

/** Remove every schedule this spec's UI created, so each test starts from none. */
function clearSchedules() {
    // Every occurrence first, through the Occurrences tab's recursive delete: a
    // schedule whose occurrences carry assignments refuses to be deleted (service
    // history), and the Generate dialog's tests leave exactly that behind. A one-off
    // occurrence's schedule is hidden from the schedules listing on purpose
    // (2026-09-18), and deleting its occurrence takes the hidden schedule with it.
    cy.makePrivateAdminAPICall(
        "GET",
        `${VOLUNTEER_URL}/occurrences?ministryId=${ministryId}&from=${isoDate(0)}&to=${isoDate(400)}`,
        null,
        200,
    ).then((resp) => {
        for (const occurrence of resp.body.occurrences) {
            cy.makePrivateAdminAPICall("DELETE", `${VOLUNTEER_URL}/occurrences/${occurrence.id}`, null, [200, 404]);
        }
    });
    cy.makePrivateAdminAPICall("GET", `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`, null, 200).then(
        (resp) => {
            for (const schedule of resp.body.schedules) {
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `${VOLUNTEER_URL}/schedules/${schedule.id}`,
                    null,
                    [200, 404, 409],
                );
            }
        },
    );
}

function findOrCreateMinistry() {
    return cy.makePrivateAdminAPICall("GET", `${VOLUNTEER_URL}/ministries`, null, 200).then((resp) => {
        const existing = resp.body.ministries.find((m) => m.name === MINISTRY_NAME);
        if (existing) {
            return cy.wrap(existing.id);
        }
        return cy
            .makePrivateAdminAPICall(
                "POST",
                `${VOLUNTEER_URL}/ministries`,
                { name: MINISTRY_NAME, description: "staffing-needs UI fixture" },
                201,
            )
            .then((created) => created.body.ministry.id);
    });
}

/** Open the Schedules tab of the ministry page and wait for its list to settle. */
function openSchedulesTab() {
    cy.visit(`/ministries/${ministryId}`);
    cy.get("#nav-item-schedules").click();
    cy.get("#schedules-loading").should("not.be.visible");
}

/**
 * Fill the schedule form's non-staffing half. Standalone weekly, so V2 owns the dates
 * and the fixture needs no calendar events.
 */
function fillScheduleBasics(name) {
    cy.get("#schedule-form-name").clear().type(name);
    cy.get("#schedule-form-team").select(TEAM_NAME);
    // "An existing calendar event type" lists the church's event types from the
    // core endpoint, which answers `{ EventTypes: [...] }` (fixed 2026-09-18);
    // the seed carries Church Service and Sunday School.
    cy.get("#schedule-form-link-mode").select("event_type");
    cy.get("#schedule-form-event-type option").should("have.length.at.least", 2);
    cy.get("#schedule-form-event-type").should("contain", "Church Service").and("contain", "Sunday School");
    // The Event picker (2026-09-18): a schedule follows ONE event series of the
    // type; "Any event of this type" is the explicit opt-out.
    cy.get("#schedule-form-title-filter").should("be.visible");
    cy.get("#schedule-form-title-filter option").first().should("have.value", "").and("contain", "Any event of this type");
    cy.get("#schedule-form-link-mode").select("standalone");
    cy.get("#schedule-form-dow").select("Wednesday");
    cy.get("#schedule-form-start-time").clear().type("19:00");
    cy.get("#schedule-form-end-time").clear().type("20:30");
    cy.get("#schedule-form-window-start").clear().type(isoDate(0));
}

/** Generate this schedule's occurrences through the API and return the first id. */
function generateOccurrences(scheduleId) {
    return cy
        .makePrivateAdminAPICall(
            "POST",
            `${VOLUNTEER_URL}/schedules/${scheduleId}/generate`,
            { through: isoDate(21) },
            200,
        )
        .then(() =>
            cy.makePrivateAdminAPICall(
                "GET",
                `${VOLUNTEER_URL}/occurrences?from=${isoDate(-1)}&to=${isoDate(21)}&scheduleId=${scheduleId}`,
                null,
                200,
            ),
        )
        .then((resp) => {
            expect(resp.body.occurrences.length).to.be.greaterThan(0);
            return resp.body.occurrences[0].id;
        });
}

function onlyScheduleId() {
    return cy
        .makePrivateAdminAPICall("GET", `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`, null, 200)
        .then((resp) => {
            expect(resp.body.schedules).to.have.length(1);
            return resp.body.schedules[0].id;
        });
}

describe("Volunteer v2 — staffing needs (§2.10)", () => {
    before(() => {
        setVersion("v2");

        findOrCreateMinistry().then((id) => {
            ministryId = id;
        });

        cy.then(() => {
            cy.makePrivateAdminAPICall("GET", `${VOLUNTEER_URL}/ministries/${ministryId}`, null, 200).then(
                (resp) => {
                    const team = resp.body.teams.find((t) => t.name === TEAM_NAME);
                    if (team) {
                        teamId = team.id;
                        return;
                    }
                    cy.makePrivateAdminAPICall(
                        "POST",
                        `${VOLUNTEER_URL}/ministries/${ministryId}/teams`,
                        { name: TEAM_NAME, description: "" },
                        201,
                    ).then((created) => {
                        teamId = created.body.team.id;
                    });
                },
            );
        });

        cy.then(() => {
            cy.makePrivateAdminAPICall(
                "GET",
                `${VOLUNTEER_URL}/ministries/${ministryId}/positions`,
                null,
                200,
            ).then((resp) => {
                for (const [name, order] of [
                    [POSITION_LEAD, 1],
                    [POSITION_HELPER, 2],
                ]) {
                    const assign = (id) => {
                        if (name === POSITION_LEAD) {
                            posLead = id;
                        } else {
                            posHelper = id;
                        }
                    };
                    const found = resp.body.positions.find((p) => p.name === name);
                    if (found) {
                        assign(found.id);
                        continue;
                    }
                    cy.makePrivateAdminAPICall(
                        "POST",
                        `${VOLUNTEER_URL}/ministries/${ministryId}/positions`,
                        { name, description: "", teamId, order },
                        201,
                    ).then((created) => assign(created.body.position.id));
                }
            });
        });

        cy.then(clearSchedules);
    });

    after(() => {
        clearSchedules();
        setVersion("v1");
    });

    describe("the schedule form's staffing-needs section", () => {
        beforeEach(() => {
            clearSchedules();
            freshAdminLogin();
            openSchedulesTab();
        });

        it("lists the team's positions, pre-checked, at 1 / 1 for a new schedule", () => {
            cy.get("#schedule-add-btn").click();
            cy.get("#scheduleModal").should("be.visible");
            cy.get("#schedule-form-team").select(TEAM_NAME);

            cy.get("#schedule-form-needs .volunteer-need-row").should("have.length", 2);
            cy.get(`#schedule-form-needs .volunteer-need-row`).each(($row) => {
                cy.wrap($row).find(".volunteer-need-check").should("be.checked");
                cy.wrap($row).find(".volunteer-need-min").should("have.value", "1");
                cy.wrap($row).find(".volunteer-need-max").should("have.value", "1");
            });
            cy.get("#schedule-form-needs").should("contain.text", POSITION_LEAD);
            cy.get("#schedule-form-needs").should("contain.text", POSITION_HELPER);
        });

        it("warns before saving a plan with nothing checked, but still saves it", () => {
            cy.get("#schedule-add-btn").click();
            fillScheduleBasics(`${PREFIX} No Needs`);

            cy.get("#schedule-form-needs .volunteer-need-check").uncheck({ force: true });
            cy.get(".volunteer-needs-notice")
                .should("be.visible")
                .and("contain.text", "generated occurrences will need nobody");

            cy.get("#schedule-form-save").click();
            cy.get("#scheduleModal").should("not.be.visible");

            onlyScheduleId().then((scheduleId) => {
                cy.makePrivateAdminAPICall(
                    "GET",
                    `${VOLUNTEER_URL}/schedules/${scheduleId}/requirements`,
                    null,
                    200,
                ).then((resp) => {
                    expect(resp.body.requirements).to.have.length(0);
                });
            });
        });

        it("refuses a maximum below the minimum", () => {
            cy.get("#schedule-add-btn").click();
            fillScheduleBasics(`${PREFIX} Bad Counts`);

            cy.get("#schedule-form-needs .volunteer-need-row")
                .first()
                .within(() => {
                    cy.get(".volunteer-need-min").clear().type("3");
                    cy.get(".volunteer-need-max").clear().type("1");
                });

            cy.get(".volunteer-needs-notice")
                .should("be.visible")
                .and("contain.text", "the maximum cannot be below the minimum");

            cy.get("#schedule-form-save").click();
            // The modal stays open and nothing was written.
            cy.get("#scheduleModal").should("be.visible");
            cy.get("#schedule-form-error").should("be.visible");
            cy.makePrivateAdminAPICall(
                "GET",
                `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.schedules).to.have.length(0);
            });
        });

        it("re-renders the rows when the team changes", () => {
            cy.get("#schedule-add-btn").click();
            cy.get("#schedule-form-team").select(TEAM_NAME);
            cy.get("#schedule-form-needs .volunteer-need-row").should("have.length", 2);

            // The ministry's OTHER team — the one it was created with — owns no
            // positions, so switching to it must empty the list rather than leave the
            // previous team's rows on screen. There is no "whole ministry" choice to
            // fall back to any more: a schedule always names one team, and each team's
            // positions are its own.
            cy.get("#schedule-form-team").select(DEFAULT_TEAM_NAME);
            cy.get("#schedule-form-needs .volunteer-need-row").should("have.length", 0);
            cy.get("#schedule-form-needs [data-role=no-positions]").should("be.visible");

            cy.get("#schedule-form-team").select(TEAM_NAME);
            cy.get("#schedule-form-needs .volunteer-need-row").should("have.length", 2);
        });

        it("pre-fills the stored plan on edit, unchecking a position that has none", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`,
                {
                    name: `${PREFIX} Stored Plan`,
                    linkMode: "standalone",
                    recurType: "weekly",
                    recurDow: "Wednesday",
                    startTime: "19:00:00",
                    endTime: "20:30:00",
                    windowStart: isoDate(0),
                    teamId,
                    requirements: [{ positionId: posLead, minCount: 2, maxCount: 3 }],
                },
                201,
            );

            // cy.request() rotates the PHP session cookie, so the browser has to sign in
            // again before the page it is about to load can call the API for itself.
            cy.then(freshAdminLogin);
            openSchedulesTab();
            cy.get("#volunteerSchedulesTable .volunteer-schedule-edit").first().click({ force: true });
            cy.get("#scheduleModal").should("be.visible");

            cy.get(`#staffing-need-${posLead}-check`).should("be.checked");
            cy.get(`#staffing-need-${posLead}-min`).should("have.value", "2");
            cy.get(`#staffing-need-${posLead}-max`).should("have.value", "3");
            // No requirement row means the position is not part of this plan.
            cy.get(`#staffing-need-${posHelper}-check`).should("not.be.checked");
            cy.get(`#staffing-need-${posHelper}-min`).should("be.disabled");
        });
    });

    describe("what the occurrence list says", () => {
        beforeEach(() => {
            clearSchedules();
            freshAdminLogin();
        });

        it("shows the red icon, naming the short position in its tooltip, after a schedule is made in the UI", () => {
            openSchedulesTab();
            cy.get("#schedule-add-btn").click();
            fillScheduleBasics(`${PREFIX} Default Needs`);

            // Leave the defaults alone: every position checked at 1 / 1. This is the
            // path the defect report took, and the one that used to end at "0/0".
            cy.get(`#staffing-need-${posHelper}-check`).uncheck({ force: true });
            cy.get("#schedule-form-save").click();
            cy.get("#scheduleModal").should("not.be.visible");

            onlyScheduleId().then((scheduleId) => {
                generateOccurrences(scheduleId).then(() => {
                    freshAdminLogin();
                    cy.visit(`/ministries/${ministryId}`);
                    cy.get("#nav-item-occurrences").click();
                    cy.get("#occurrences-loading").should("not.be.visible");

                    cy.get("#volunteerOccurrencesTable tbody tr")
                        .first()
                        .within(() => {
                            // One Filled cell since 2026-09-18, icon only: red, with the
                            // count and the short position by name in the tooltip.
                            cy.get("td").eq(4).invoke("text").invoke("trim").should("eq", "");
                            cy.get("td").eq(4).find(".text-red .fa-triangle-exclamation").should("exist");
                            cy.get("td").eq(4).find("[title]").invoke("attr", "title")
                                .should("contain", "0 of 1")
                                .and("contain", "1 ")
                                .and("contain", "Lead Teacher");
                        });
                });
            });
        });

        it("deletes the ticked occurrences from the Delete button above the table (2026-09-18)", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`,
                {
                    name: `${PREFIX} To Delete`,
                    linkMode: "standalone",
                    recurType: "weekly",
                    recurDow: "Wednesday",
                    startTime: "19:00:00",
                    endTime: "20:30:00",
                    windowStart: isoDate(0),
                    teamId,
                    requirements: [],
                },
                201,
            ).then((created) => {
                generateOccurrences(created.body.schedule.id).then(() => {
                    freshAdminLogin();
                    cy.visit(`/ministries/${ministryId}`);
                    cy.get("#nav-item-occurrences").click();
                    cy.get("#occurrences-loading").should("not.be.visible");

                    // No Actions column; the checkbox column leads, Team follows When.
                    cy.get("#volunteerOccurrencesTable thead th").should("have.length", 5);
                    cy.get("#volunteerOccurrencesTable thead th").eq(2).should("contain", "Team");
                    cy.get("#volunteerOccurrencesTable tbody tr").first().find("td").eq(2).should("contain", TEAM_NAME);
                    cy.get("#occurrences-delete-btn").should("be.disabled");

                    cy.get("#volunteerOccurrencesTable tbody tr").its("length").then((before) => {
                        expect(before).to.be.greaterThan(1);
                        cy.get("#volunteerOccurrencesTable tbody tr").eq(0).find(".volunteer-occurrence-select").check();
                        cy.get("#volunteerOccurrencesTable tbody tr").eq(1).find(".volunteer-occurrence-select").check();
                        cy.get("#occurrences-delete-btn").should("not.be.disabled").and("contain", "2");
                        cy.get("#occurrences-delete-btn").click();
                        cy.get(".bootbox").should("be.visible").and("contain", "2 occurrences");
                        cy.get(".bootbox .btn-danger").click();
                        cy.get(".notyf__toast").should("contain", "2 occurrences deleted");
                        cy.get("#occurrences-loading").should("not.be.visible");
                        cy.get("#volunteerOccurrencesTable tbody tr").should("have.length", before - 2);
                        cy.get("#occurrences-delete-btn").should("be.disabled");
                    });

                    // Select All ticks every row and wakes the button; unticking sleeps it.
                    // The confirm dialog's backdrop may still be fading out after the
                    // delete above; the checkbox itself is live.
                    cy.get("#occurrences-select-all").check({ force: true });
                    cy.get("#occurrences-delete-btn").should("not.be.disabled");
                    cy.get("#occurrences-select-all").uncheck({ force: true });
                    cy.get("#occurrences-delete-btn").should("be.disabled");
                });
            });
        });

        it("adds a one-off occurrence with a hidden schedule of its own, and deleting it takes the schedule too (2026-09-18)", () => {
            const NAME = `${PREFIX} Harvest Supper`;
            cy.visit(`/ministries/${ministryId}`);
            cy.get("#nav-item-occurrences").click();
            cy.get("#occurrences-loading").should("not.be.visible");

            cy.get("#occurrences-add-btn").click();
            cy.get("#oneOffOccurrenceModal").should("be.visible");
            // The dialog focuses Name at `shown.bs.modal`; typing before the fade
            // ends would lose the tail of the name to Bootstrap's own focus move.
            cy.get("#one-off-form-name").should("have.focus").type(NAME);
            cy.get("#one-off-form-team").select(TEAM_NAME);
            cy.get("#one-off-form-date").clear().type(isoDate(3));
            cy.get("#one-off-form-start-time").clear().type("18:00");
            cy.get("#one-off-form-end-time").clear().type("20:00");
            // The needs editor lists the team's positions, every one ticked at 1 / 1.
            cy.get("#one-off-form-needs .volunteer-need-row").should("have.length.at.least", 1);
            cy.intercept("POST", "**/api/ministries/ministries/*/occurrences").as("createOneOff");
            cy.get("#one-off-form-save").click();
            cy.wait("@createOneOff").then((call) => {
                expect(call.request.body.name, "the name the dialog sent").to.eq(NAME);
                expect(call.response.statusCode, JSON.stringify(call.response.body)).to.eq(201);
            });
            cy.get("#oneOffOccurrenceModal").should("not.be.visible");
            cy.get(".notyf__toast").should("contain", "Occurrence added");

            // Listed, flagged, on the right team and date. The Event box narrows the
            // list server-side, so the row is on the page whatever else is scheduled.
            cy.get("#occurrence-event-filter").clear().type(NAME);
            cy.get("#occurrences-loading").should("not.be.visible");
            cy.get("#volunteerOccurrencesTable tbody tr").should("have.length", 1);
            cy.get("#volunteerOccurrencesTable tbody tr").first().as("row");
            cy.get("@row").find("td").eq(2).should("contain", TEAM_NAME);
            cy.get("@row").find("td").eq(3).should("contain", "one-off");
            cy.get("@row").find("td").eq(1).should("contain", isoDate(3));

            // Its private schedule is not on the Schedules tab, but does exist.
            cy.get("#nav-item-schedules").click();
            cy.get("#schedules-loading").should("not.be.visible");
            cy.get("#volunteerSchedulesTable tbody").should("not.contain", NAME);
            cy.dbQuery(`SELECT vsch_ID, vsch_Name, vsch_OneOff FROM volunteer_schedule_vsch WHERE vsch_Name LIKE ?`, [`${PREFIX} Harvest%`]).then((r) => {
                expect(r.error).to.eq(null);
                expect(r.rows, `the hidden schedule (found: ${JSON.stringify(r.rows)})`).to.have.length(1);
                expect(r.rows[0].vsch_Name).to.eq(NAME);
                expect(Number(r.rows[0].vsch_OneOff)).to.eq(1);
            });

            // Delete the occurrence: the schedule goes with it.
            cy.get("#nav-item-occurrences").click();
            cy.get("#occurrences-loading").should("not.be.visible");
            cy.get("#occurrence-event-filter").clear().type(NAME);
            cy.get("#occurrences-loading").should("not.be.visible");
            cy.get("#volunteerOccurrencesTable tbody tr").should("have.length", 1);
            cy.get("#volunteerOccurrencesTable tbody tr").first().find(".volunteer-occurrence-select").check();
            cy.get("#occurrences-delete-btn").click();
            cy.get(".bootbox .btn-danger").click();
            cy.get(".notyf__toast").should("contain", "1 occurrences deleted");
            cy.get("#occurrences-empty, #volunteerOccurrencesTable tbody").should("not.contain", NAME);
            cy.dbQuery(`SELECT COUNT(*) AS c FROM volunteer_schedule_vsch WHERE vsch_Name = ?`, [NAME]).then((r) => {
                expect(r.error).to.eq(null);
                expect(Number(r.rows[0].c), "the hidden schedule is gone with its occurrence").to.eq(0);
            });
        });

        it("shows the 'No staffing needs set' icon — never the green check — for an empty plan", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`,
                {
                    name: `${PREFIX} Empty Plan`,
                    linkMode: "standalone",
                    recurType: "weekly",
                    recurDow: "Wednesday",
                    startTime: "19:00:00",
                    endTime: "20:30:00",
                    windowStart: isoDate(0),
                    teamId,
                    requirements: [],
                },
                201,
            ).then((created) => {
                generateOccurrences(created.body.schedule.id).then(() => {
                    freshAdminLogin();
                    cy.visit(`/ministries/${ministryId}`);
                    cy.get("#nav-item-occurrences").click();
                    cy.get("#occurrences-loading").should("not.be.visible");

                    cy.get("#volunteerOccurrencesTable tbody tr")
                        .first()
                        .within(() => {
                            cy.get("td").eq(4).invoke("text").invoke("trim").should("eq", "");
                            cy.get("td").eq(4).find("a").should("have.attr", "title", "No staffing needs set");
                            cy.get("td").eq(4).find(".fa-circle-check").should("not.exist");
                            // The icon is the way in to fixing it.
                            cy.get("td").eq(4).find("a").should("have.attr", "href").and("include", "/occurrences/");
                        });
                });
            });
        });
    });

    describe("the Generate occurrences dialog (2026-09-18)", () => {
        let scheduleId = 0;
        // tony.wade (3) and the seed's plain member (900): both real people, neither
        // in this ministry's pool until they are qualified.
        const PERSON_DEFAULT = 3;

        beforeEach(() => {
            clearSchedules();
            cy.makePrivateAdminAPICall(
                "POST",
                `${VOLUNTEER_URL}/positions/${posLead}/qualifications`,
                { personId: PERSON_DEFAULT, notes: "" },
                [200, 201],
            );
            cy.makePrivateAdminAPICall(
                "POST",
                `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`,
                {
                    name: `${PREFIX} Generate Dialog`,
                    linkMode: "standalone",
                    recurType: "weekly",
                    recurDow: "Thursday",
                    startTime: "19:00:00",
                    endTime: "20:30:00",
                    windowStart: isoDate(0),
                    windowEnd: isoDate(21),
                    teamId,
                    requirements: [
                        { positionId: posLead, minCount: 1, maxCount: 1 },
                        { positionId: posHelper, minCount: 1, maxCount: 2 },
                    ],
                },
                201,
            ).then((created) => {
                scheduleId = created.body.schedule.id;
            });
            cy.then(freshAdminLogin);
            openSchedulesTab();
        });

        /** Open the row's menu once the row has rendered, then the dialog, and wait for its rows. */
        function openGenerateDialog() {
            cy.get("#volunteerSchedulesTable tbody tr", { timeout: 15000 }).should("contain.text", `${PREFIX} Generate Dialog`);
            cy.get(`.volunteer-schedule-generate[data-schedule-id="${scheduleId}"]`)
                .closest("tr")
                .find("[data-bs-toggle='dropdown']")
                .click();
            cy.get(`.volunteer-schedule-generate[data-schedule-id="${scheduleId}"]`).should("be.visible").click();
            cy.get("#generateOccurrencesModal").should("be.visible");
            cy.get("#generate-form-loading").should("not.be.visible");
            cy.get("#generate-form-rows .generate-default-row").should("have.length", 2);
        }

        it("names the column and the action after occurrences, not dates", () => {
            cy.get("#volunteerSchedulesTable thead").should("contain.text", "Occurrences").and("not.contain.text", "Dates");
            cy.get("#volunteerSchedulesTable tbody tr", { timeout: 15000 }).should("contain.text", `${PREFIX} Generate Dialog`);
            cy.get(`.volunteer-schedule-generate[data-schedule-id="${scheduleId}"]`)
                .closest("tr")
                .find("[data-bs-toggle='dropdown']")
                .click();
            cy.get(`.volunteer-schedule-generate[data-schedule-id="${scheduleId}"]`)
                .should("be.visible")
                .and("contain.text", "Generate occurrences")
                .and("not.contain.text", "Generate dates");
        });

        it("offers a default per position, only the qualified, and assigns them accepted on every new occurrence", () => {
            openGenerateDialog();

            // The lead position has one qualified person to offer; the helper has none
            // and says so rather than showing an empty picker.
            cy.get(`.generate-default-row[data-position-id="${posLead}"]`).within(() => {
                cy.contains(POSITION_LEAD);
                cy.contains("1 needed");
                cy.contains("label", "Fill by default with:");
                cy.get("select.generate-default-select option").should("have.length", 2);
                cy.get("select.generate-default-select option").eq(1).should("contain.text", "has not served yet");
                // The Accepted box waits for a choice.
                cy.get(".generate-default-accepted-wrap").should("not.be.visible");
            });
            cy.get(`.generate-default-row[data-position-id="${posHelper}"]`).within(() => {
                cy.contains("1 to 2 needed");
                cy.get("select.generate-default-select").should("not.exist");
                cy.contains("Nobody is qualified for this position yet");
            });

            // Choose the default through the underlying select (TomSelect mirrors it and
            // fires change), then tick Set as Accepted once it appears.
            cy.get(`.generate-default-row[data-position-id="${posLead}"] select.generate-default-select`).then(($select) => {
                const value = $select.find("option").eq(1).val();
                cy.wrap($select).select(String(value), { force: true });
            });
            cy.get(`.generate-default-row[data-position-id="${posLead}"] .generate-default-accepted-wrap`).should("be.visible");
            cy.get(`.generate-default-row[data-position-id="${posLead}"] .generate-default-accepted`).check({ force: true });

            cy.intercept("POST", `**/api/ministries/schedules/${scheduleId}/generate`).as("generate");
            cy.get("#generate-form-save").click();
            cy.wait("@generate").its("request.body.defaults").should("deep.eq", [
                { positionId: posLead, personId: PERSON_DEFAULT, accepted: true },
            ]);
            cy.get("#generateOccurrencesModal").should("not.be.visible");
            cy.get("#volunteerSchedulesTable tbody tr", { timeout: 15000 })
                .first()
                .find("td")
                .eq(3)
                .invoke("text")
                .then((text) => {
                    expect(Number(text.trim())).to.be.greaterThan(0);
                });

            // Every occurrence this run made carries the accepted assignment. The
            // Occurrences tab shows them all green: assigned AND confirmed, no gaps
            // on the lead position — the helper is still open, so the icon is red.
            cy.get("#nav-item-occurrences").click();
            cy.get("#volunteerOccurrencesTable tbody tr", { timeout: 15000 }).should("have.length.at.least", 1);

            // Last: the request rotates the session.
            cy.then(() => {
                cy.dbQuery(
                    `SELECT vasg.vasg_Status, vasg.vasg_per_ID, vasg.vasg_vpos_ID,
                            (SELECT COUNT(*) FROM volunteer_occurrence_vocc o WHERE o.vocc_vsch_ID = ?) AS occurrences
                       FROM volunteer_assignment_vasg vasg
                       JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vasg.vasg_vocc_ID
                      WHERE vocc.vocc_vsch_ID = ?`,
                    [scheduleId, scheduleId],
                ).then((result) => {
                    expect(result.error).to.eq(null);
                    expect(result.rows.length).to.be.greaterThan(0);
                    expect(result.rows.length).to.eq(Number(result.rows[0].occurrences));
                    for (const row of result.rows) {
                        expect(row.vasg_Status).to.eq("accepted");
                        expect(Number(row.vasg_per_ID)).to.eq(PERSON_DEFAULT);
                        expect(Number(row.vasg_vpos_ID)).to.eq(posLead);
                    }
                });
            });
        });

        it("generates with nothing chosen and assigns nobody", () => {
            openGenerateDialog();

            cy.intercept("POST", `**/api/ministries/schedules/${scheduleId}/generate`).as("generate");
            cy.get("#generate-form-save").click();
            cy.wait("@generate").then(({ request, response }) => {
                expect(request.body.defaults).to.eq(undefined);
                expect(response.body.created).to.be.greaterThan(0);
                expect(response.body.assigned).to.eq(0);
            });
            cy.get("#generateOccurrencesModal").should("not.be.visible");
        });
    });

    describe("the occurrence page's needs editor", () => {
        let scheduleId = 0;
        let occurrenceId = 0;

        beforeEach(() => {
            clearSchedules();

            cy.makePrivateAdminAPICall(
                "POST",
                `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`,
                {
                    name: `${PREFIX} Occurrence Editor`,
                    linkMode: "standalone",
                    recurType: "weekly",
                    recurDow: "Wednesday",
                    startTime: "19:00:00",
                    endTime: "20:30:00",
                    windowStart: isoDate(0),
                    teamId,
                    requirements: [{ positionId: posLead, minCount: 1, maxCount: 1 }],
                },
                201,
            ).then((created) => {
                scheduleId = created.body.schedule.id;
                generateOccurrences(scheduleId).then((id) => {
                    occurrenceId = id;
                });
            });

            cy.then(freshAdminLogin);
        });

        it("overrides this week's needs and puts them back", () => {
            cy.visit(`/ministries/occurrences/${occurrenceId}`);
            cy.get("#requirements-loading").should("not.be.visible");
            cy.get(".volunteer-requirement .requirement-counts").should("contain.text", "0 / 1");

            cy.get("#requirements-edit").click();
            cy.get("#volunteer-needs-modal").should("be.visible");
            cy.get("#needs-loading").should("not.be.visible");
            // Pre-filled from the EFFECTIVE plan: the schedule's row is checked, the
            // position with no row is offered unchecked.
            cy.get(`#staffing-need-${posLead}-check`).should("be.checked");
            cy.get(`#staffing-need-${posHelper}-check`).should("not.be.checked");
            // Nothing to reset back to yet.
            cy.get("#needs-form-reset").should("not.be.visible");

            cy.get(`#staffing-need-${posLead}-min`).clear().type("3");
            cy.get(`#staffing-need-${posLead}-max`).clear().type("3");
            cy.get(`#staffing-need-${posHelper}-check`).check({ force: true });
            cy.get("#needs-form-save").click();

            cy.get("#volunteer-needs-modal").should("not.be.visible");
            cy.get(".volunteer-requirement").should("have.length", 2);
            cy.get(`.volunteer-requirement[data-position-id="${posLead}"] .requirement-counts`).should(
                "contain.text",
                "0 / 3",
            );

            // And back again.
            cy.get("#requirements-edit").click();
            cy.get("#needs-loading").should("not.be.visible");
            cy.get("#needs-form-reset").should("be.visible").click();
            cy.get(".bootbox .btn-danger").click();

            cy.get("#volunteer-needs-modal").should("not.be.visible");
            cy.get(".volunteer-requirement").should("have.length", 1);
            cy.get(`.volunteer-requirement[data-position-id="${posLead}"] .requirement-counts`).should(
                "contain.text",
                "0 / 1",
            );

            // Last, because cy.request() rotates the session cookie out from under the
            // page: the schedule's own plan was never touched, only this one week's.
            cy.makePrivateAdminAPICall(
                "GET",
                `${VOLUNTEER_URL}/schedules/${scheduleId}/requirements`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.requirements).to.have.length(1);
                expect(resp.body.requirements[0].minCount).to.eq(1);
            });
        });

        it("says 'No staffing needs set' on an occurrence with no plan, and fixes it from there", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${VOLUNTEER_URL}/schedules/${scheduleId}`,
                { requirements: [] },
                200,
            );

            cy.then(freshAdminLogin);
            cy.visit(`/ministries/occurrences/${occurrenceId}`);
            cy.get("#requirements-loading").should("not.be.visible");
            cy.get("#requirements-empty")
                .should("be.visible")
                .and("contain.text", "No staffing needs set")
                .and("not.contain.text", "Nobody is needed yet");

            cy.get("#requirements-empty-edit").click();
            cy.get("#volunteer-needs-modal").should("be.visible");
            cy.get("#needs-loading").should("not.be.visible");
            cy.get(`#staffing-need-${posLead}-check`).check({ force: true });
            cy.get(`#staffing-need-${posLead}-min`).clear().type("2");
            cy.get(`#staffing-need-${posLead}-max`).clear().type("2");
            cy.get("#needs-form-save").click();

            cy.get("#volunteer-needs-modal").should("not.be.visible");
            cy.get("#requirements-empty").should("not.be.visible");
            cy.get(`.volunteer-requirement[data-position-id="${posLead}"] .requirement-counts`).should(
                "contain.text",
                "0 / 2",
            );
        });

        it("refuses a maximum below the minimum in the modal", () => {
            cy.visit(`/ministries/occurrences/${occurrenceId}`);
            cy.get("#requirements-loading").should("not.be.visible");
            cy.get("#requirements-edit").click();
            cy.get("#volunteer-needs-modal").should("be.visible");
            cy.get("#needs-loading").should("not.be.visible");
            // `#needs-loading` starts hidden, so "not visible" can be true before the
            // rows arrive; wait for the row this test edits to be there and checked,
            // or the numbers are typed into a field that is about to be replaced.
            cy.get(`#staffing-need-${posLead}-check`).should("be.checked");

            // `{selectall}` rather than `.clear()`: on an `<input type=number>` Cypress's
            // clear intermittently leaves the old digit behind, and the typed one is then
            // appended — "1" + "2" = 12, which is a valid maximum and quietly turns this
            // test green-then-red. Replacing the selection is deterministic; the value
            // assertions keep it honest.
            cy.get(`#staffing-need-${posLead}-min`).type("{selectall}4").should("have.value", "4");
            cy.get(`#staffing-need-${posLead}-max`).type("{selectall}2").should("have.value", "2");
            cy.get("#needs-form-save").click();

            cy.get("#volunteer-needs-modal").should("be.visible");
            cy.get("#needs-form-error").should("be.visible");
        });
    });
});
