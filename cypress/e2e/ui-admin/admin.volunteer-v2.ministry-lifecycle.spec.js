/// <reference types="cypress" />

/**
 * Volunteer v2 — the ministry lifecycle: Deactivate → Deactivated Ministries →
 * Reactivate / Delete (epic #9701, design §4.6 and §5.4 as amended 2026-09-17).
 *
 * Product-owner decision: a ministry must be deactivated before it can be
 * deleted. Deactivating moves it from the Ministries heading's active entries to a
 * **Deactivated Ministries** group nested under that heading, which exists only
 * while there is one; that
 * page offers Reactivate and — to an administrator or Manage Ministries user —
 * Delete, which then removes everything under the ministry, service history
 * included. Proved here:
 *
 *   1. an administrator sees Deactivate on an active ministry and neither
 *      Reactivate nor Delete; confirming moves the ministry between the two
 *      headings and swaps the buttons;
 *   2. Reactivate puts it back;
 *   3. Delete on a deactivated ministry quotes the occurrence and assignment
 *      counts, then lands on the Ministry Dashboard with the ministry gone
 *      (API 404, no stale scope rows, no Deactivated Ministries entry);
 *   4. the API refuses DELETE on an active ministry with 409 — hiding the button
 *      is not the rule (D5);
 *   5. a coordinator may deactivate and reactivate their own ministry but never
 *      sees Delete, and their DELETE is a 403;
 *   6. a refused delete keeps the page and surfaces the API's message as a toast.
 *
 * Order inside every hook is API setup → fresh login → cy.visit(), because
 * cy.request() rotates the PHP session cookie (cypress-testing.md). Fixtures are
 * removed in `before` as well as `after`.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const VOLUNTEER_URL = "/api/ministries";
const MINISTRIES_URL = "/ministries";
const DASHBOARD_URL = "/ministries/dashboard";

const PREFIX = "UIMINLIFE";
const MINISTRY_NAME = `${PREFIX} Parking Team`;

/** tony.wade — every flag but Admin, and NOT Manage Ministries (usr_ManageMinistries = 0). */
const COORDINATOR_PERSON = 3;

let ministryId = 0;

// Local helpers — NOT cy.* commands (cypress-testing.md).
function freshAdminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.env("admin.username"));
    cy.get("input[name=Password]").type(Cypress.env("admin.password") + "{enter}");
    cy.url().should("not.include", "/session/begin");
}

function freshCoordinatorLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.env("standard.username"));
    cy.get("input[name=Password]").type(Cypress.env("standard.password") + "{enter}");
    cy.url().should("not.include", "/session/begin");
}

function adminApi(method, url, body, expectedStatus = 200) {
    return cy.makePrivateAdminAPICall(method, url, body, expectedStatus);
}

function setVersion(value) {
    adminApi("POST", SETTING_URL, { value }, 200);
}

function cleanupScopes() {
    adminApi("GET", `${VOLUNTEER_URL}/scopes?personId=${COORDINATOR_PERSON}`, null, 200).then((resp) => {
        for (const scope of resp.body.scopes) {
            adminApi("DELETE", `${VOLUNTEER_URL}/scopes/${scope.id}`, null, [200, 404]);
        }
    });
}

function cleanupMinistries() {
    adminApi("GET", `${VOLUNTEER_URL}/ministries`, null, 200).then((resp) => {
        for (const ministry of resp.body.ministries) {
            if (ministry.name.startsWith(PREFIX)) {
                adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministry.id}`, { active: false }, [200, 404]);
                adminApi("DELETE", `${VOLUNTEER_URL}/ministries/${ministry.id}`, null, [200, 404]);
            }
        }
    });
}

function cleanupFixtures() {
    cleanupScopes();
    cleanupMinistries();
}

/** One active ministry, and tony.wade made its coordinator. */
function createFixtures() {
    adminApi(
        "POST",
        `${VOLUNTEER_URL}/ministries`,
        { name: MINISTRY_NAME, description: "Cones and waving" },
        201,
    ).then((resp) => {
        ministryId = resp.body.ministry.id;
        adminApi(
            "POST",
            `${VOLUNTEER_URL}/scopes`,
            { personId: COORDINATOR_PERSON, scopeType: "ministry", scopeId: ministryId },
            201,
        );
    });
}

function ministryUrl() {
    return `${MINISTRIES_URL}/${ministryId}`;
}

/**
 * The collapse container of a top-level sidebar heading, found by its own label
 * (the same helper admin.volunteer-v2.menu.spec.js uses).
 */
function menuSection(title) {
    return cy
        .get("a[data-bs-toggle='collapse'] .nav-link-title")
        .filter((_i, el) => el.textContent.trim() === title)
        .should("have.length", 1)
        .parents("a[data-bs-toggle='collapse']")
        .invoke("attr", "href")
        .then((href) => cy.get(href));
}

/**
 * Deactivated Ministries is a group NESTED inside the Ministries heading, so "under
 * Ministries" means a DIRECT entry of that heading's list — the nested group's own
 * entries sit one `ul` deeper and must not count.
 */
function headingHasMinistry(title, expected) {
    menuSection(title)
        .find(`> ul > li > div > a[href$="${ministryUrl()}"]`)
        .should(expected ? "exist" : "not.exist");
}

/** The nested group may hold other specs' residue; ours is what matters. */
function deactivatedHeadingLacksMinistry() {
    // A jQuery filter inside .then(), not cy.filter(): the Cypress one retries until
    // it finds something, and "no such group at all" is the expected common case.
    cy.get("a[data-bs-toggle='collapse'] .nav-link-title").then(($titles) => {
        const heading = $titles.filter((_i, el) => el.textContent.trim() === "Deactivated Ministries");
        if (heading.length === 0) {
            return;
        }
        headingHasMinistry("Deactivated Ministries", false);
    });
}

/** The nested group is a child of the Ministries heading, never a heading of its own. */
function deactivatedGroupIsNestedUnderMinistries() {
    menuSection("Ministries").within(() => {
        cy.get("a[data-bs-toggle='collapse'] .nav-link-title")
            .filter((_i, el) => el.textContent.trim() === "Deactivated Ministries")
            .should("have.length", 1);
    });
}

describe("Volunteer v2 — ministry lifecycle (Deactivate, Reactivate, Delete)", () => {
    before(() => {
        setVersion("v2");
        cleanupFixtures();
    });

    after(() => {
        cleanupFixtures();
    });

    beforeEach(() => {
        cleanupFixtures();
        createFixtures();
    });

    // Every cy.request() below happens in a hook BEFORE the login of the test that
    // follows it: a request after the login rotates the PHP session cookie, the
    // page's next fetch is a 401 and the shell bounces to /session/begin.
    describe("as an administrator, on an active ministry", () => {
        beforeEach(() => {
            freshAdminLogin();
            cy.visit(ministryUrl());
            cy.get("#volunteer-ministry").should("exist");
        });

        it("offers Deactivate, and neither Reactivate nor Delete", () => {
            cy.get("#ministry-deactivate-btn").should("be.visible").and("contain", "Deactivate");
            cy.get("#ministry-reactivate-btn").should("not.exist");
            cy.get("#ministry-delete-btn").should("not.exist");
            cy.get(".breadcrumb").should("contain", "Ministries");
            headingHasMinistry("Ministries", true);
            deactivatedHeadingLacksMinistry();
        });

        it("moves a deactivated ministry to the Deactivated Ministries heading and swaps the buttons; Reactivate puts it back", () => {
            cy.get("#ministry-deactivate-btn").click();
            cy.get(".bootbox").should("be.visible").and("contain", MINISTRY_NAME);
            cy.get(".bootbox .btn-danger").click();

            // The page reloads server-rendered from the flag.
            cy.get("#ministry-reactivate-btn").should("be.visible").and("contain", "Reactivate");
            cy.get("#volunteer-ministry .card-header .badge").should("contain", "Inactive");
            cy.get("#ministry-deactivate-btn").should("not.exist");
            cy.get("#ministry-delete-btn").should("be.visible").and("contain", "Delete");
            headingHasMinistry("Ministries", false);
            headingHasMinistry("Deactivated Ministries", true);
            deactivatedGroupIsNestedUnderMinistries();
            // Both levels open on a deactivated ministry's own page.
            menuSection("Ministries").should("have.class", "show");
            menuSection("Deactivated Ministries").should("have.class", "show");

            cy.get("#ministry-reactivate-btn").click();
            cy.get("#ministry-deactivate-btn").should("be.visible");
            cy.get("#ministry-reactivate-btn").should("not.exist");
            cy.get("#ministry-delete-btn").should("not.exist");
            cy.get("#volunteer-ministry .card-header .badge").should("not.exist");
            headingHasMinistry("Ministries", true);
            deactivatedHeadingLacksMinistry();
        });
    });

    describe("as an administrator, the API", () => {
        it("refuses DELETE while the ministry is active — the missing button is not the rule (D5)", () => {
            adminApi("DELETE", `${VOLUNTEER_URL}/ministries/${ministryId}`, null, 409).then((resp) => {
                expect(resp.body.message).to.include("Deactivate");
            });
            adminApi("GET", `${VOLUNTEER_URL}/ministries/${ministryId}`, null, 200).then((resp) => {
                expect(resp.body.ministry.active).to.eq(true);
            });
        });
    });

    describe("as an administrator, on a deactivated ministry", () => {
        beforeEach(() => {
            adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}`, { active: false }, 200);
            freshAdminLogin();
            cy.visit(ministryUrl());
            cy.get("#volunteer-ministry").should("exist");
        });

        it("deletes it after a confirmation that names the counts, and lands on the Ministry Dashboard", () => {
            cy.get("#ministry-delete-btn").should("be.visible").click();
            cy.get(".bootbox")
                .should("be.visible")
                .and("contain", MINISTRY_NAME)
                .and("contain", "0 occurrences")
                .and("contain", "0 assignments")
                .and("contain", "cannot be undone");
            cy.get(".bootbox .btn-danger").click();

            cy.url().should("include", DASHBOARD_URL);
            cy.get("#volunteer-dashboard").should("exist");
            cy.contains("Ministry Dashboard").should("be.visible");
            cy.get(`a[href$="${ministryUrl()}"]`).should("not.exist");
            deactivatedHeadingLacksMinistry();

            // Really gone — the requests are last, after every UI step.
            adminApi("GET", `${VOLUNTEER_URL}/ministries/${ministryId}`, null, 404);
            adminApi("GET", `${VOLUNTEER_URL}/scopes?personId=${COORDINATOR_PERSON}`, null, 200).then((resp) => {
                const stale = resp.body.scopes.filter((scope) => scope.scopeId === ministryId);
                expect(stale, "scope rows pointing at the deleted ministry").to.have.length(0);
            });
        });

        it("keeps the page and shows the API's reason when a delete is refused", () => {
            cy.get("#ministry-delete-btn").should("be.visible");

            const refusal = "Deactivate this ministry before deleting it.";
            cy.intercept("DELETE", `**${VOLUNTEER_URL}/ministries/${ministryId}`, {
                statusCode: 409,
                body: { message: refusal },
            }).as("refusedDelete");

            cy.get("#ministry-delete-btn").click();
            cy.get(".bootbox .btn-danger").click();
            cy.wait("@refusedDelete");

            cy.get(".notyf__toast").should("contain", refusal);
            cy.url().should("include", ministryUrl());
            cy.get("#ministry-delete-btn").should("be.visible").and("not.be.disabled");
        });
    });

    describe("as a ministry coordinator", () => {
        it("may deactivate and reactivate the ministry but never sees Delete, and the API refuses their DELETE", () => {
            freshCoordinatorLogin();
            cy.visit(ministryUrl());
            cy.get("#volunteer-ministry").should("exist");
            cy.get("#ministry-deactivate-btn").should("be.visible");
            cy.get("#ministry-delete-btn").should("not.exist");

            cy.get("#ministry-deactivate-btn").click();
            cy.get(".bootbox .btn-danger").click();
            cy.get("#ministry-reactivate-btn").should("be.visible");
            cy.get("#ministry-delete-btn").should("not.exist");
            headingHasMinistry("Deactivated Ministries", true);

            cy.get("#ministry-reactivate-btn").click();
            cy.get("#ministry-deactivate-btn").should("be.visible");
            headingHasMinistry("Ministries", true);

            // Hiding is not security (D5): the same session's DELETE is a 403. Last,
            // because cy.request() rotates the session the page above was using.
            cy.get("#ministry-deactivate-btn").click();
            cy.get(".bootbox .btn-danger").click();
            cy.get("#ministry-reactivate-btn").should("be.visible");
            cy.request({
                method: "DELETE",
                url: `${VOLUNTEER_URL}/ministries/${ministryId}`,
                failOnStatusCode: false,
            }).then((resp) => {
                expect(resp.status).to.eq(403);
            });
        });
    });
});
