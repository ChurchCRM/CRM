/// <reference types="cypress" />

/**
 * Volunteer v2 — the ministry page's second round of product-owner changes
 * (epic #9701, design §5.4 as amended twice).
 *
 * Three of them are here; the fourth (the team leader moving into the team
 * dialogs) is in admin.volunteer-v2.scopes.spec.js, where the rest of the scope
 * UI already lives, and the fifth (the tab order) is in
 * admin.volunteer-v2.ministry-page.spec.js beside the tab strip it renames.
 *
 *   1. **The Volunteers grid scrolls sideways.** Position names and counts are
 *      unlimited, so the qualification grid cannot be made to fit; the wrapper
 *      scrolls, the scrollbar comes and goes with the window width, and the row
 *      action menu is NOT clipped by the scroller the way `.table-responsive`
 *      clips one (table-action-menu.md).
 *
 *   2. **A tick persists.** Reported as "ticking a position is not persisting and
 *      there is no save button". The write always reached the database — the
 *      matrix endpoint returns it — but the grid is re-rendered from a CACHED
 *      document that the save never updated, so leaving the tab and coming back
 *      drew the state as it was before the tick. The cases below leave and come
 *      back, reload, and switch teams to and fro; every one of them fails on the
 *      unchanged branch.
 *
 *   3. **Occurrences default to upcoming.** A past occurrence is not in the list
 *      the tab opens on.
 *
 * Round THREE has since moved two things out of this file: the inline "Saved" badge
 * beside a ticked box became a toast (it was shifting the checkbox column), and the
 * "Filter by Date" dialog became a live Team / Event / From / To search form. Both
 * are covered in admin.volunteer-v2.ministry-page-3.spec.js; what is left here is
 * the behaviour round three did not change.
 *
 * Order inside every hook is API setup → freshAdminLogin() → cy.visit(), because
 * cy.request() rotates the PHP session cookie (cypress-testing.md). Fixture rows
 * are removed in `before` as well as `after`: an `after` hook does not run when
 * the runner crashes mid-spec.
 *
 * `cy.dbQuery()` appears exactly once, for the past occurrence. It is registered
 * for this suite (docker-admin.config.ts) and there is deliberately no API that
 * can create one: generation refuses to back-fill, because historical
 * occurrences are immutable (§2.9). Everything else goes in through the very
 * endpoints the page calls.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const VOLUNTEER_URL = "/api/volunteer";
const MINISTRIES_URL = "/volunteer/ministries";

const PREFIX = "UIPAGE2";
const MINISTRY_NAME = `${PREFIX} Coffee Bar`;
const SECOND_TEAM_NAME = `${PREFIX} Saturday Crew`;

/**
 * Enough columns that the grid cannot fit a 900px window, and few enough that it
 * fits a 1600px one — which is what makes "the scrollbar comes and goes" provable
 * with one grid and two viewport sizes rather than two different grids.
 */
const WIDE_POSITION_COUNT = 3;
const positionName = (n) => `${PREFIX} Coffee Position ${n}`;
/** The one position on the SECOND team, so a team switch changes the columns. */
const SECOND_TEAM_POSITION = `${PREFIX} Saturday Barista`;

/** Two pool members, so the grid has rows. Person 4 is the one that gets ticked. */
const POOL_PEOPLE = [4, 5];
const TICKED_PERSON = 4;

let ministryId = 0;
let firstTeamId = 0;
let secondTeamId = 0;
let firstPositionId = 0;
let secondTeamPositionId = 0;
let scheduleId = 0;

// Local helpers — NOT cy.* commands (cypress-testing.md).
function freshAdminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.env("admin.username"));
    cy.get("input[name=Password]").type(Cypress.env("admin.password") + "{enter}");
    cy.url().should("not.include", "/session/begin");
}

function adminApi(method, url, body, expectedStatus = 200) {
    return cy.makePrivateAdminAPICall(method, url, body, expectedStatus);
}

function setVersion(value) {
    adminApi("POST", SETTING_URL, { value }, 200);
}

function isoDate(offsetDays) {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + offsetDays);
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/**
 * Teams, positions and the pool Group cascade from the ministry row, but
 * OCCURRENCES do not: a ministry that still has any is refused with a 409 and
 * told to deactivate instead (§2.9). They belong to schedules, so the schedules
 * go first and take their occurrences with them.
 */
function cleanupFixtures() {
    adminApi("GET", `${VOLUNTEER_URL}/ministries`, null, 200).then((resp) => {
        for (const ministry of resp.body.ministries) {
            if (!ministry.name.startsWith(PREFIX)) {
                continue;
            }
            adminApi("GET", `${VOLUNTEER_URL}/ministries/${ministry.id}/schedules`, null, 200).then(
                (list) => {
                    for (const schedule of list.body.schedules) {
                        adminApi("DELETE", `${VOLUNTEER_URL}/schedules/${schedule.id}`, null, [
                            200,
                            404,
                            409,
                        ]);
                    }
                    // An active ministry cannot be deleted (409): deactivate first (2026-09-17 lifecycle rule).
                    adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministry.id}`, { active: false }, [200, 404]);
                    adminApi("DELETE", `${VOLUNTEER_URL}/ministries/${ministry.id}`, null, [
                        200,
                        404,
                        409,
                    ]);
                },
            );
        }
    });
}

function ministryUrl() {
    return `${MINISTRIES_URL}/${ministryId}`;
}

/** Open the Volunteers tab and wait for the grid, not just for the click. */
function openVolunteersTab() {
    cy.get("#nav-item-volunteers").click();
    cy.get("#volunteers .volunteer-loading").should("not.be.visible");
    cy.get("#volunteerQualificationsTable").should("be.visible");
}

function qualBox(personId, positionId) {
    return cy.get(
        `#volunteerQualificationsTable input.volunteer-qual-toggle[data-person-id="${personId}"][data-position-id="${positionId}"]`,
    );
}

/** Every qualification this spec's ticking could have left behind, so each run starts clean. */
function clearQualifications(positionId) {
    adminApi("GET", `${VOLUNTEER_URL}/positions/${positionId}/qualifications?active=1`, null, 200).then(
        (resp) => {
            for (const qualification of resp.body.qualifications) {
                adminApi("DELETE", `${VOLUNTEER_URL}/qualifications/${qualification.id}`, null, [200, 404]);
            }
        },
    );
}

describe("Volunteer v2 ministry page, round two (#9701)", () => {
    before(() => {
        setVersion("v2");
        cleanupFixtures();

        adminApi(
            "POST",
            `${VOLUNTEER_URL}/ministries`,
            { name: MINISTRY_NAME, description: "round-two worked example" },
            201,
        ).then((resp) => {
            ministryId = resp.body.ministry.id;

            adminApi("GET", `${VOLUNTEER_URL}/ministries/${ministryId}`, null, 200).then((detail) => {
                firstTeamId = detail.body.teams[0].id;

                for (let n = 1; n <= WIDE_POSITION_COUNT; n++) {
                    adminApi(
                        "POST",
                        `${VOLUNTEER_URL}/ministries/${ministryId}/positions`,
                        { name: positionName(n), teamId: firstTeamId, order: n },
                        201,
                    ).then((position) => {
                        if (n === 1) {
                            firstPositionId = position.body.position.id;
                        }
                    });
                }

                // A second team with a position of its own, so switching teams
                // genuinely changes the columns the grid is showing.
                adminApi(
                    "POST",
                    `${VOLUNTEER_URL}/ministries/${ministryId}/teams`,
                    { name: SECOND_TEAM_NAME },
                    201,
                ).then((team) => {
                    secondTeamId = team.body.team.id;
                    adminApi(
                        "POST",
                        `${VOLUNTEER_URL}/ministries/${ministryId}/positions`,
                        { name: SECOND_TEAM_POSITION, teamId: secondTeamId, order: 1 },
                        201,
                    ).then((position) => {
                        secondTeamPositionId = position.body.position.id;
                    });
                });

                // A standalone weekly schedule, generated forward. Its occurrences
                // are the "upcoming" half; the past one is inserted below.
                adminApi(
                    "POST",
                    `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`,
                    {
                        name: `${PREFIX} Sunday Morning`,
                        teamId: firstTeamId,
                        linkMode: "standalone",
                        recurType: "weekly",
                        recurDow: "Sunday",
                        startTime: "09:00",
                        endTime: "10:30",
                        windowStart: isoDate(0),
                        windowEnd: isoDate(60),
                    },
                    201,
                ).then((schedule) => {
                    scheduleId = schedule.body.schedule.id;
                    adminApi("POST", `${VOLUNTEER_URL}/schedules/${scheduleId}/generate`, {}, 200);
                });
            });

            for (const personId of POOL_PEOPLE) {
                adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${personId}`, null, [200, 201]);
            }
        });
    });

    after(() => {
        cleanupFixtures();
        setVersion("v1");
    });

    // ═════════════════════════════════════════════════════════════════════════
    // 1. The grid scrolls sideways, and the row menu is not clipped by it
    // ═════════════════════════════════════════════════════════════════════════

    describe("the Volunteers grid at different window widths", () => {
        beforeEach(() => {
            freshAdminLogin();
        });

        afterEach(() => {
            // Leave the viewport as the rest of the suite expects to find it.
            cy.viewport(1920, 1080);
        });

        it("needs no horizontal scrollbar when the window is wide enough", () => {
            cy.viewport(1600, 900);
            cy.visit(ministryUrl());
            openVolunteersTab();

            cy.get("#volunteers-table-wrapper").then(($wrapper) => {
                const el = $wrapper[0];
                // One pixel of tolerance: sub-pixel table widths round up.
                expect(el.scrollWidth, "grid content width").to.be.at.most(el.clientWidth + 1);
            });
        });

        it("scrolls sideways when the window is too narrow for the columns", () => {
            cy.viewport(900, 800);
            cy.visit(ministryUrl());
            openVolunteersTab();

            cy.get("#volunteers-table-wrapper").then(($wrapper) => {
                const el = $wrapper[0];
                expect(el.scrollWidth, "grid content width").to.be.greaterThan(el.clientWidth);
                expect(getComputedStyle(el).overflowX, "wrapper overflow-x").to.eq("auto");

                // And it genuinely scrolls, rather than merely reporting that it could.
                el.scrollLeft = 200;
                expect(el.scrollLeft, "scrolled distance").to.be.greaterThan(0);
            });
        });

        it("opens the row action menu fully visible even while the grid is scrolling", () => {
            cy.viewport(900, 800);
            cy.visit(ministryUrl());
            openVolunteersTab();

            cy.get("#volunteers-table-wrapper").then(($wrapper) => {
                expect($wrapper[0].scrollWidth).to.be.greaterThan($wrapper[0].clientWidth);
            });

            cy.get(`#volunteerQualificationsTable .volunteer-remove-volunteer[data-person-id="${TICKED_PERSON}"]`)
                .parents(".dropdown")
                .find("[data-bs-toggle='dropdown']")
                .click();

            cy.get(`#volunteerQualificationsTable .volunteer-remove-volunteer[data-person-id="${TICKED_PERSON}"]`)
                .parents(".dropdown-menu")
                .should("be.visible")
                .then(($menu) => {
                    const menu = $menu[0];
                    const rect = menu.getBoundingClientRect();

                    // The technique: an open menu is taken out of the scroller by
                    // `position: fixed`, which no ancestor's overflow can clip.
                    expect(getComputedStyle(menu).position, "open menu positioning").to.eq("fixed");

                    expect(rect.width, "menu width").to.be.greaterThan(0);
                    expect(rect.height, "menu height").to.be.greaterThan(0);
                    expect(rect.left, "menu left edge").to.be.at.least(0);
                    expect(rect.right, "menu right edge").to.be.at.most(900);
                    expect(rect.bottom, "menu bottom edge").to.be.at.most(800);

                    // Painted and on top, not merely laid out: a clipped menu is not
                    // what the browser finds under its own middle.
                    const hit = menu.ownerDocument.elementFromPoint(
                        rect.left + rect.width / 2,
                        rect.top + rect.height / 2,
                    );
                    expect(menu.contains(hit), "menu is what is drawn at its own centre").to.eq(true);
                });
        });
    });

    // ═════════════════════════════════════════════════════════════════════════
    // 2. A tick persists — and says so
    // ═════════════════════════════════════════════════════════════════════════

    describe("ticking a qualification", () => {
        beforeEach(() => {
            clearQualifications(firstPositionId);
            clearQualifications(secondTeamPositionId);
            freshAdminLogin();
        });

        it("says there is no Save button because each tick saves itself", () => {
            cy.visit(ministryUrl());
            openVolunteersTab();

            cy.get("#volunteers-save-hint").should("be.visible").and("contain", "Ticks save as you make them.");
            cy.get("#volunteers button").each(($button) => {
                expect($button.text().trim()).to.not.eq("Save");
            });
        });

        // Round three moved this confirmation OUT of the cell: the inline badge was
        // wider than the checkbox and shifted the whole column for a second and a
        // half on every tick. The toast that replaced it, and the proof that nothing
        // in the grid moves, are in admin.volunteer-v2.ministry-page-3.spec.js; what
        // this case still owns is that a tick confirms itself at all.
        it("confirms the save", () => {
            cy.visit(ministryUrl());
            openVolunteersTab();

            qualBox(TICKED_PERSON, firstPositionId).should("not.be.checked").check();

            cy.get(".notyf__toast--success").should("be.visible");
        });

        it("takes the 'not qualified yet' badge away as soon as a box is ticked", () => {
            cy.intercept("POST", "**/api/volunteer/positions/*/qualifications").as("grantQual");
            cy.intercept("DELETE", "**/api/volunteer/qualifications/*").as("revokeQual");
            cy.visit(ministryUrl());
            openVolunteersTab();

            const row = () =>
                qualBox(TICKED_PERSON, firstPositionId).parents("tr").find(".volunteer-pool-hint");

            row().should("be.visible").and("contain", "In the pool, not qualified yet");
            qualBox(TICKED_PERSON, firstPositionId).check();
            cy.wait("@grantQual");
            row().should("not.be.visible");

            // …and it comes back when the last tick goes, without a re-render.
            qualBox(TICKED_PERSON, firstPositionId).uncheck();
            cy.wait("@revokeQual");
            row().should("be.visible");
        });

        it("keeps the tick when the tab is left and come back to", () => {
            // The write is waited for over the network rather than through the new
            // "Saved" badge, so this case fails on the unchanged branch for the
            // REPORTED reason — the box comes back empty — rather than because the
            // badge it was looking for does not exist there yet.
            cy.intercept("POST", "**/api/volunteer/positions/*/qualifications").as("grantQual");
            cy.visit(ministryUrl());
            openVolunteersTab();

            qualBox(TICKED_PERSON, firstPositionId).should("not.be.checked").check();
            cy.wait("@grantQual").its("response.statusCode").should("be.oneOf", [200, 201]);

            // The whole of the reported defect: the grid is re-rendered from its
            // cached document on every activation, and the save used to leave that
            // document untouched.
            cy.get("#nav-item-positions").click();
            cy.get("#volunteerPositionsTable").should("be.visible");
            openVolunteersTab();

            qualBox(TICKED_PERSON, firstPositionId).should("be.checked");
        });

        it("keeps the tick across a reload of the page", () => {
            cy.intercept("POST", "**/api/volunteer/positions/*/qualifications").as("grantQual");
            cy.visit(ministryUrl());
            openVolunteersTab();

            qualBox(TICKED_PERSON, firstPositionId).should("not.be.checked").check();
            cy.wait("@grantQual").its("response.statusCode").should("be.oneOf", [200, 201]);

            cy.reload();
            openVolunteersTab();
            cy.get("#qualification-team-filter").should("have.value", String(firstTeamId));
            qualBox(TICKED_PERSON, firstPositionId).should("be.checked");
        });

        it("keeps the tick when the team is switched away and back", () => {
            cy.intercept("POST", "**/api/volunteer/positions/*/qualifications").as("grantQual");
            cy.visit(ministryUrl());
            openVolunteersTab();

            qualBox(TICKED_PERSON, firstPositionId).should("not.be.checked").check();
            cy.wait("@grantQual").its("response.statusCode").should("be.oneOf", [200, 201]);

            cy.get("#qualification-team-filter").select(String(secondTeamId));
            cy.get("#volunteers .volunteer-loading").should("not.be.visible");
            qualBox(TICKED_PERSON, secondTeamPositionId).should("not.be.checked");

            cy.get("#qualification-team-filter").select(String(firstTeamId));
            cy.get("#volunteers .volunteer-loading").should("not.be.visible");
            qualBox(TICKED_PERSON, firstPositionId).should("be.checked");

            // And once more, to prove it is the stored state being read rather than
            // a lucky cache that happens to be right the first time.
            cy.get("#qualification-team-filter").select(String(secondTeamId));
            cy.get("#volunteers .volunteer-loading").should("not.be.visible");
            cy.get("#qualification-team-filter").select(String(firstTeamId));
            cy.get("#volunteers .volunteer-loading").should("not.be.visible");
            qualBox(TICKED_PERSON, firstPositionId).should("be.checked");
        });

        it("clears the tick again, and the clearing persists too", () => {
            adminApi(
                "POST",
                `${VOLUNTEER_URL}/positions/${firstPositionId}/qualifications`,
                { personId: TICKED_PERSON },
                [200, 201],
            );
            freshAdminLogin();

            cy.intercept("DELETE", "**/api/volunteer/qualifications/*").as("revokeQual");
            cy.visit(ministryUrl());
            openVolunteersTab();
            qualBox(TICKED_PERSON, firstPositionId).should("be.checked").uncheck();
            cy.wait("@revokeQual").its("response.statusCode").should("eq", 200);

            cy.get("#nav-item-positions").click();
            cy.get("#volunteerPositionsTable").should("be.visible");
            openVolunteersTab();
            qualBox(TICKED_PERSON, firstPositionId).should("not.be.checked");
        });
    });

    // ═════════════════════════════════════════════════════════════════════════
    // 3. Occurrences default to upcoming — the "Filter by Date" dialog is gone
    //
    // Round three replaced the dialog with a live Team / Event / From / To search
    // form, so the dialog cases that were here have moved to
    // admin.volunteer-v2.ministry-page-3.spec.js and are written against the form.
    // What survives here is the half of the behaviour that did NOT change: the tab
    // still opens on what is still to come, and a past occurrence is not in it.
    // ═════════════════════════════════════════════════════════════════════════

    describe("the Occurrences tab", () => {
        const PAST_DATE = isoDate(-30);

        before(() => {
            // Generation deliberately refuses to back-fill (§2.9), so the one thing
            // this section needs cannot be made through the API.
            cy.dbQuery(
                `INSERT INTO volunteer_occurrence_vocc
                    (vocc_vsch_ID, vocc_OccurrenceDate, vocc_StartDateTime, vocc_EndDateTime, vocc_Status, vocc_GeneratedDate)
                 VALUES (?, ?, ?, ?, 'scheduled', NOW())`,
                [scheduleId, PAST_DATE, `${PAST_DATE} 09:00:00`, `${PAST_DATE} 10:30:00`],
            );
        });

        beforeEach(() => {
            freshAdminLogin();
        });

        it("opens on what is still to come, with From set to today", () => {
            cy.visit(ministryUrl());
            cy.get("#nav-item-occurrences").click();
            cy.get("#occurrences .volunteer-loading").should("not.be.visible");
            cy.get("#volunteerOccurrencesTable").should("be.visible");

            cy.get("#occurrence-from").should("have.value", isoDate(0));
            cy.get("#volunteerOccurrencesTable tbody").should("not.contain", PAST_DATE);
        });

        it("has no Filter by Date button, dialog or range note left", () => {
            cy.visit(ministryUrl());
            cy.get("#nav-item-occurrences").click();
            cy.get("#occurrences .volunteer-loading").should("not.be.visible");
            cy.get("#volunteerOccurrencesTable").should("be.visible");

            cy.get("#occurrences-filter-btn").should("not.exist");
            cy.get("#occurrenceRangeModal").should("not.exist");
            cy.get("#occurrences-range-note").should("not.exist");
            cy.get("#occurrences-range-reset").should("not.exist");
        });
    });
});
