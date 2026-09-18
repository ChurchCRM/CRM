/// <reference types="cypress" />

/**
 * Volunteer v2 — the ministry page's FOURTH round of product-owner changes
 * (epic #9701, design §5.4 as amended four times).
 *
 *   1. **The Occurrences tab has no DataTables search box.** The Team · Event ·
 *      From · To form above the table already narrows the query server-side, and
 *      a second box that filters only the page currently drawn was the wrong
 *      answer to the same question asked twice. **Export CSV and Print move
 *      above the form**, into a small right-aligned toolbar row of their own,
 *      keeping their behaviour — the Actions column is still `no-export`.
 *
 *   2. **A position can advertise itself.** Add Position and Edit Position gain
 *      a **Recruit Volunteers** switch, off by default, and the Positions table
 *      gains a sortable **Recruiting** column showing a green check when it is
 *      on and nothing when it is off.
 *
 * The API contract for `recruiting` is pinned by
 * `private.volunteer.recruiting.spec.js`; what is asserted here is the SCREEN.
 *
 * Order inside every hook is API setup → freshAdminLogin() → cy.visit(), because
 * cy.request() rotates the PHP session cookie (cypress-testing.md). Fixture rows
 * are removed in `before` as well as `after`: an `after` hook does not run when
 * the runner crashes mid-spec.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const VOLUNTEER_URL = "/api/ministries";
const MINISTRIES_URL = "/ministries";

const PREFIX = "UIPAGE4";
const MINISTRY_NAME = `${PREFIX} Coffee Bar`;

const POSITION_QUIET = `${PREFIX} Espresso`;
const POSITION_RECRUITING = `${PREFIX} Pastry`;
const POSITION_NEW = `${PREFIX} Greeter`;

const SCHEDULE_SUNDAY = `${PREFIX} Sunday Morning`;

/** The exact wording the product owner chose for the switch and its hint. */
const SWITCH_LABEL = "Recruit Volunteers";
const SWITCH_HINT = "Advertise this position on the Open Opportunities page.";

let ministryId = 0;
let firstTeamId = 0;
let quietPositionId = 0;
let recruitingPositionId = 0;
let sundayScheduleId = 0;

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
                            200, 404, 409,
                        ]);
                    }
                    // An active ministry cannot be deleted (409): deactivate first (2026-09-17 lifecycle rule).
                    adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministry.id}`, { active: false }, [200, 404]);
                    adminApi("DELETE", `${VOLUNTEER_URL}/ministries/${ministry.id}`, null, [
                        200, 404, 409,
                    ]);
                },
            );
        }
    });
}

function ministryUrl() {
    return `${MINISTRIES_URL}/${ministryId}`;
}

function openPositionsTab() {
    cy.get("#nav-item-positions").click();
    cy.get("#positions .volunteer-loading").should("not.be.visible");
    cy.get("#volunteerPositionsTable").should("be.visible");
}

/**
 * Open the Occurrences tab and wait for the TABLE, not just for the click: the
 * pane is hidden until the tab is activated, so "the spinner is not visible" is
 * already true before the first request has been made.
 */
function openOccurrencesTab() {
    cy.get("#nav-item-occurrences").click();
    cy.get("#occurrences .volunteer-loading").should("not.be.visible");
    cy.get("#volunteerOccurrencesTable").should("be.visible");
}

/** The row of the Positions table whose Name cell holds `name`. */
function positionRow(name) {
    return cy.get("#volunteerPositionsTable tbody tr").contains("td", name).parent();
}

describe("Volunteer v2 ministry page, round four (#9701)", () => {
    before(() => {
        setVersion("v2");
        cleanupFixtures();

        adminApi(
            "POST",
            `${VOLUNTEER_URL}/ministries`,
            { name: MINISTRY_NAME, description: "round-four worked example" },
            201,
        ).then((resp) => {
            ministryId = resp.body.ministry.id;

            adminApi("GET", `${VOLUNTEER_URL}/ministries/${ministryId}`, null, 200).then((detail) => {
                firstTeamId = detail.body.teams[0].id;

                adminApi(
                    "POST",
                    `${VOLUNTEER_URL}/ministries/${ministryId}/positions`,
                    { name: POSITION_QUIET, teamId: firstTeamId, order: 1 },
                    201,
                ).then((position) => {
                    quietPositionId = position.body.position.id;
                });
                adminApi(
                    "POST",
                    `${VOLUNTEER_URL}/ministries/${ministryId}/positions`,
                    {
                        name: POSITION_RECRUITING,
                        teamId: firstTeamId,
                        order: 2,
                        recruiting: true,
                    },
                    201,
                ).then((position) => {
                    recruitingPositionId = position.body.position.id;
                });

                adminApi(
                    "POST",
                    `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`,
                    {
                        name: SCHEDULE_SUNDAY,
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
                    sundayScheduleId = schedule.body.schedule.id;
                    adminApi("POST", `${VOLUNTEER_URL}/schedules/${sundayScheduleId}/generate`, {}, 200);
                });
            });
        });
    });

    after(() => {
        cleanupFixtures();
        setVersion("v1");
    });

    // ═════════════════════════════════════════════════════════════════════════
    // 1. The Occurrences toolbar
    // ═════════════════════════════════════════════════════════════════════════

    describe("the Occurrences tab toolbar", () => {
        beforeEach(() => {
            freshAdminLogin();
        });

        it("has no DataTables search box above the table", () => {
            cy.visit(ministryUrl());
            openOccurrencesTab();

            cy.get("#occurrences .dt-search").should("not.exist");
            cy.get("#occurrences .dataTables_filter").should("not.exist");

            // The form that superseded it is still all four fields.
            cy.get("#occurrence-team-filter").should("be.visible");
            cy.get("#occurrence-event-filter").should("be.visible");
            cy.get("#occurrence-from").should("be.visible");
            cy.get("#occurrence-to").should("be.visible");
        });

        it("renders Export CSV and Print in a toolbar ABOVE the search form", () => {
            cy.visit(ministryUrl());
            openOccurrencesTab();

            cy.get("#occurrences-toolbar .dt-buttons").should("be.visible");
            cy.get("#occurrences-toolbar .dt-buttons button")
                .should("have.length", 2)
                .then(($buttons) => {
                    const titles = [...$buttons].map((b) => b.getAttribute("title") || "");
                    expect(titles.join(" | ")).to.contain("Export CSV");
                    expect(titles.join(" | ")).to.contain("Print");
                });

            // "Above the form" is a DOM fact, not a CSS one: the toolbar precedes
            // the first filter field in document order.
            cy.get("#occurrences-toolbar").then(($toolbar) => {
                cy.get("#occurrence-team-filter").then(($field) => {
                    const position = $toolbar[0].compareDocumentPosition($field[0]);
                    // eslint-disable-next-line no-bitwise
                    expect(
                        position & Node.DOCUMENT_POSITION_FOLLOWING,
                        "the Team field comes after the toolbar",
                    ).to.be.greaterThan(0);
                });
            });
        });

        it("keeps the buttons out of the table's own header row", () => {
            cy.visit(ministryUrl());
            openOccurrencesTab();

            cy.get("#occurrences-table-wrapper .dt-buttons").should("not.exist");
        });

        it("still marks the Actions column no-export", () => {
            cy.visit(ministryUrl());
            openOccurrencesTab();

            cy.get("#volunteerOccurrencesTable thead th").last().should("have.class", "no-export");
        });

        it("renders one toolbar, not one per reload of the table", () => {
            cy.intercept("GET", "**/api/ministries/occurrences?*").as("listOccurrences");
            cy.visit(ministryUrl());
            openOccurrencesTab();

            // Re-run the query; the DataTable is destroyed and rebuilt each time.
            cy.get("#occurrence-event-filter").type("Sunday");
            cy.wait("@listOccurrences");
            cy.get("#occurrences .volunteer-loading").should("not.be.visible");

            cy.get("#occurrences-toolbar .dt-buttons").should("have.length", 1);
        });
    });

    // ═════════════════════════════════════════════════════════════════════════
    // 2. Recruit Volunteers
    // ═════════════════════════════════════════════════════════════════════════

    describe("the Recruit Volunteers switch", () => {
        // API setup BEFORE the login, never after: cy.request() rotates the PHP
        // session cookie, so a mid-test adminApi() call logs the browser out and
        // the next cy.visit() lands on /session/begin (cypress-testing.md).
        beforeEach(() => {
            adminApi(
                "POST",
                `${VOLUNTEER_URL}/positions/${recruitingPositionId}`,
                { recruiting: true },
                200,
            );
            adminApi(
                "POST",
                `${VOLUNTEER_URL}/positions/${quietPositionId}`,
                { recruiting: false },
                200,
            );
            freshAdminLogin();
        });

        it("offers the switch, off, with its hint, on Add position", () => {
            cy.visit(ministryUrl());
            openPositionsTab();

            cy.get("#position-add-btn").click();
            cy.get("#positionModal").should("be.visible");
            cy.get("#position-form-recruiting").should("exist").and("not.be.checked");
            cy.get("#positionModal").should("contain", SWITCH_LABEL);
            cy.get("#positionModal").should("contain", SWITCH_HINT);
        });

        it("creates a position with recruiting on and shows the check in the table", () => {
            cy.visit(ministryUrl());
            openPositionsTab();

            cy.get("#position-add-btn").click();
            cy.get("#position-form-name").clear();
            cy.get("#position-form-name").type(POSITION_NEW);
            cy.get("#position-form-recruiting").check();
            cy.get("#position-form-save").click();

            cy.get("#positionModal").should("not.be.visible");
            positionRow(POSITION_NEW).find("td.volunteer-position-recruiting i").should("exist");

            adminApi("GET", `${VOLUNTEER_URL}/ministries/${ministryId}/positions`, null, 200).then(
                (resp) => {
                    const created = resp.body.positions.find((p) => p.name === POSITION_NEW);
                    expect(created, "the new position exists").to.not.be.undefined;
                    expect(created.recruiting).to.eq(true);
                    adminApi("DELETE", `${VOLUNTEER_URL}/positions/${created.id}`, null, [200, 409]);
                },
            );
        });

        it("reflects the saved value when the position is edited again", () => {
            cy.visit(ministryUrl());
            openPositionsTab();

            positionRow(POSITION_RECRUITING).find(".volunteer-position-edit").click({ force: true });
            cy.get("#positionModal").should("be.visible");
            cy.get("#position-form-recruiting").should("be.checked");

            cy.get("#positionModal .btn-close").click();

            positionRow(POSITION_QUIET).find(".volunteer-position-edit").click({ force: true });
            cy.get("#positionModal").should("be.visible");
            cy.get("#position-form-recruiting").should("not.be.checked");
        });

        it("turns recruiting off again from the dialog", () => {
            cy.visit(ministryUrl());
            openPositionsTab();

            positionRow(POSITION_RECRUITING).find(".volunteer-position-edit").click({ force: true });
            cy.get("#position-form-recruiting").should("be.checked");
            cy.get("#position-form-recruiting").uncheck();
            cy.get("#position-form-save").click();
            cy.get("#positionModal").should("not.be.visible");

            positionRow(POSITION_RECRUITING)
                .find("td.volunteer-position-recruiting i")
                .should("not.exist");
        });
    });

    describe("the Recruiting column", () => {
        beforeEach(() => {
            adminApi(
                "POST",
                `${VOLUNTEER_URL}/positions/${recruitingPositionId}`,
                { recruiting: true },
                200,
            );
            adminApi(
                "POST",
                `${VOLUNTEER_URL}/positions/${quietPositionId}`,
                { recruiting: false },
                200,
            );
            freshAdminLogin();
        });

        it("has a Recruiting header between Team and Status", () => {
            cy.visit(ministryUrl());
            openPositionsTab();

            cy.get("#volunteerPositionsTable thead th")
                .then(($headers) => [...$headers].map((h) => h.textContent.trim()))
                .should("deep.equal", [
                    "Order",
                    "Name",
                    "Description",
                    "Team",
                    "Recruiting",
                    "Status",
                    "Actions",
                ]);
        });

        it("shows a green check for a recruiting position and nothing for a quiet one", () => {
            cy.visit(ministryUrl());
            openPositionsTab();

            positionRow(POSITION_RECRUITING)
                .find("td.volunteer-position-recruiting i")
                .should("have.class", "fa-check")
                .and("have.class", "text-success")
                .and("have.attr", "title", "Recruiting")
                .and("have.attr", "aria-label", "Recruiting");

            positionRow(POSITION_QUIET)
                .find("td.volunteer-position-recruiting")
                .should("exist")
                .find("i")
                .should("not.exist");
        });

        it("is sortable", () => {
            cy.visit(ministryUrl());
            openPositionsTab();

            cy.get("#volunteerPositionsTable thead th")
                .eq(4)
                .should("satisfy", (el) => {
                    const node = el[0] ?? el;
                    return (
                        node.classList.contains("dt-orderable-asc") ||
                        node.classList.contains("dt-orderable-desc")
                    );
                });
        });

        it("renders every label through gettext/i18next, never a raw key", () => {
            cy.visit(ministryUrl());
            openPositionsTab();

            cy.get("#positions").should("not.contain", "i18next");
            cy.get("#positions").invoke("text").should("not.match", /\{\{[a-z]+\}\}/);

            cy.get("#position-add-btn").click();
            cy.get("#positionModal").should("be.visible");
            cy.get("#positionModal").should("not.contain", "i18next");
            cy.get("#positionModal").invoke("text").should("not.match", /\{\{[a-z]+\}\}/);
        });
    });
});
