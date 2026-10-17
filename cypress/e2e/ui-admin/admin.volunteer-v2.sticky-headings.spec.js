/// <reference types="cypress" />

/**
 * Volunteer v2 — the Volunteers grid keeps its position headings in view (#10527).
 *
 * The grid (rows = pool members, tick columns = positions) is drawn by one component
 * on the admin ministry page and on the Member Portal team page. With a long pool the
 * heading row used to scroll away under the top bar, and nobody could tell which
 * column was which position. Each case scrolls the page past the first rows and asks
 * the browser what is drawn just below the top bar over a position's column: it must
 * be that position's heading cell. Fixtures go in through the API; the ministry
 * delete removes its pool group, positions and team-leader grant with it.
 */

const VOLUNTEER_URL = "/api/ministries";
const PREFIX = "STICKY10527";
const POSITIONS = ["Song Leader", "Scripture Reader", "Opening Prayer", "Sound Booth"].map(
    (name) => `${PREFIX} ${name}`,
);
/** Enough rows that the page scrolls well past the grid's first rows at 900px tall. */
const POOL_PEOPLE = Array.from({ length: 28 }, (_, i) => i + 2);

const LEADER_USERNAME = "lena.black.editself.notes@example.com";
const LEADER_PASSWORD = "changeme";
const PERSON_LEADER = 100;

let ministryId = 0;
let teamId = 0;
const positionIds = [];
let originalVersion;

function adminApi(method, url, body, expectedStatus = 200) {
    return cy.makePrivateAdminAPICall(method, url, body, expectedStatus);
}

function cleanupFixtures() {
    adminApi("GET", `${VOLUNTEER_URL}/ministries`, null, 200).then((resp) => {
        for (const ministry of resp.body.ministries) {
            if (!ministry.name.startsWith(PREFIX)) {
                continue;
            }
            adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministry.id}`, { active: false }, [200, 404]);
            adminApi("DELETE", `${VOLUNTEER_URL}/ministries/${ministry.id}`, null, [200, 404]);
        }
    });
}

function leaderLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(LEADER_USERNAME);
    cy.get("input[name=Password]").type(`${LEADER_PASSWORD}{enter}`);
    cy.url({ timeout: 10000 }).should("not.include", "/session/begin");
}

/** `firstPane` is the tab the page opens on: once it has loaded, the tab strip is wired. */
function openVolunteersTab(firstPane) {
    cy.get(`#${firstPane}-loading`, { timeout: 15000 }).should("not.be.visible");
    cy.get("#nav-item-volunteers").click();
    cy.get("#volunteerQualificationsTable tbody tr", { timeout: 15000 }).should("have.length.at.least", POOL_PEOPLE.length);
}

/** The bottom edge of the page's sticky top bar, or 0 when the top bar scrolls away. */
function topBarBottom(win) {
    const bar = [...win.document.querySelectorAll("header")].find((header) =>
        ["sticky", "fixed"].includes(win.getComputedStyle(header).position),
    );

    return bar ? bar.getBoundingClientRect().bottom : 0;
}

function scrollPastFirstRows() {
    cy.get("#volunteerQualificationsTable tbody tr")
        .eq(12)
        .then(($row) => {
            cy.window().then((win) => {
                win.scrollTo(0, $row[0].getBoundingClientRect().top + win.scrollY - 200);
            });
        });
}

/** What is drawn just below the top bar, over the column of the given position, is its heading. */
function expectHeadingInView(positionIndex) {
    cy.window().should((win) => {
        const barBottom = topBarBottom(win);
        const firstRow = win.document.querySelector("#volunteerQualificationsTable tbody tr");
        expect(firstRow.getBoundingClientRect().top, "the first rows are scrolled past").to.be.lessThan(barBottom);

        const box = win.document.querySelector(
            `#volunteerQualificationsTable input.volunteer-qual-toggle[data-position-id="${positionIds[positionIndex]}"]`,
        );
        const column = box.getBoundingClientRect();
        const heading = win.document
            .elementFromPoint(column.left + column.width / 2, barBottom + 2)
            ?.closest("th");

        expect(heading, "a heading cell is drawn below the top bar").to.exist;
        expect(heading.textContent.trim(), "over its own column").to.eq(POSITIONS[positionIndex]);
        const top = heading.getBoundingClientRect().top;
        expect(top, "not above the viewport").to.be.at.least(0);
        expect(top, "at the top bar's bottom edge").to.be.closeTo(barBottom, 1);
    });
}

function scrollGridSideways() {
    cy.get("#volunteers-table-wrapper").then(($wrapper) => {
        const wrapper = $wrapper[0];
        expect(wrapper.scrollWidth, "the grid is wider than a phone").to.be.greaterThan(wrapper.clientWidth);
        wrapper.scrollLeft = wrapper.scrollWidth;
    });
}

describe("Volunteer v2: the Volunteers grid keeps its headings in view (#10527)", () => {
    before(() => {
        cy.getSystemConfig("sVolunteerVersion").then((value) => {
            originalVersion = value;
        });
        adminApi("POST", "/admin/api/system/config/sVolunteerVersion", { value: "v2" }, 200);
        cleanupFixtures();

        adminApi("POST", `${VOLUNTEER_URL}/ministries`, { name: `${PREFIX} Worship`, description: "" }, 201).then(
            (resp) => {
                ministryId = resp.body.ministry.id;
                adminApi("GET", `${VOLUNTEER_URL}/ministries/${ministryId}`, null, 200).then((detail) => {
                    teamId = detail.body.teams[0].id;
                    POSITIONS.forEach((name, i) => {
                        adminApi(
                            "POST",
                            `${VOLUNTEER_URL}/ministries/${ministryId}/positions`,
                            { name, teamId, order: i + 1 },
                            201,
                        ).then((position) => {
                            positionIds[i] = position.body.position.id;
                        });
                    });
                    adminApi(
                        "POST",
                        `${VOLUNTEER_URL}/scopes`,
                        { personId: PERSON_LEADER, scopeType: "team", scopeId: teamId },
                        [200, 201],
                    );
                });
                for (const personId of POOL_PEOPLE) {
                    adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${personId}`, null, [200, 201]);
                }
            },
        );
    });

    after(() => {
        cleanupFixtures();
        cy.restoreSystemConfig("sVolunteerVersion", originalVersion);
    });

    afterEach(() => {
        cy.viewport(1920, 1080);
    });

    it("admin ministry page, desktop: the headings stay below the top bar", () => {
        cy.viewport(1400, 900);
        cy.freshAdminFormLogin();
        cy.visit(`/ministries/${ministryId}`);
        openVolunteersTab("overview");
        scrollPastFirstRows();
        expectHeadingInView(0);
        expectHeadingInView(3);
    });

    it("admin ministry page, phone: the headings stay in view and follow the grid sideways", () => {
        cy.viewport(500, 900);
        cy.freshAdminFormLogin();
        cy.visit(`/ministries/${ministryId}`);
        openVolunteersTab("overview");
        scrollPastFirstRows();
        expectHeadingInView(0);
        scrollGridSideways();
        expectHeadingInView(3);
    });

    it("portal team page, desktop: the headings stay at the top of the window", () => {
        cy.viewport(1400, 900);
        leaderLogin();
        cy.visit(`/portal/teams/${teamId}`);
        openVolunteersTab("positions");
        scrollPastFirstRows();
        expectHeadingInView(0);
        expectHeadingInView(3);
    });

    it("portal team page, phone: the headings stay in view and follow the grid sideways", () => {
        cy.viewport(500, 900);
        leaderLogin();
        cy.visit(`/portal/teams/${teamId}`);
        openVolunteersTab("positions");
        scrollPastFirstRows();
        expectHeadingInView(0);
        scrollGridSideways();
        expectHeadingInView(3);
    });
});
