/// <reference types="cypress" />

/**
 * Volunteer v2 — the ministry page's Delete button (epic #9701, design §4.6 and
 * §5.4 as amended 2026-09-17).
 *
 * Deleting a ministry is the Administrator / Manage Ministries tier's alone. The
 * view renders the button for exactly those users (`$bIsManager`), and the API
 * gate behind it is `ManageMinistriesRoleAuthMiddleware` plus the service's own
 * `isGlobalManager()` check. Three things are proved here:
 *
 *   1. an administrator sees the button, confirms, and lands on the Ministry
 *      Dashboard with the ministry gone (sidebar, API);
 *   2. a ministry coordinator opens the same page and there is no button — and the
 *      API refuses the DELETE for them with 403 regardless;
 *   3. a refused delete (the 409 "still has occurrences and assignments" answer)
 *      leaves the page where it is and surfaces the API's own message as a toast.
 *      Building real occurrences needs a recurring event series and a schedule
 *      (admin.volunteer-v2.occurrence.spec.js does that); the 409 itself is the
 *      service's decision and is asserted at the API level in the setup-api spec,
 *      so here the response is intercepted and the UI's handling of it is what is
 *      under test.
 *
 * Order inside every hook is API setup → fresh login → cy.visit(), because
 * cy.request() rotates the PHP session cookie (cypress-testing.md). Fixtures are
 * removed in `before` as well as `after`.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const VOLUNTEER_URL = "/api/volunteer";
const MINISTRIES_URL = "/volunteer/ministries";
const DASHBOARD_URL = "/volunteer/dashboard";

const PREFIX = "UIMINDEL";
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
                adminApi("DELETE", `${VOLUNTEER_URL}/ministries/${ministry.id}`, null, [200, 404]);
            }
        }
    });
}

function cleanupFixtures() {
    cleanupScopes();
    cleanupMinistries();
}

/** One ministry, and tony.wade made its coordinator. */
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

describe("Volunteer v2 — Delete on the ministry page", () => {
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

    it("is offered to an administrator; confirming removes the ministry and lands on the Ministry Dashboard", () => {
        freshAdminLogin();
        cy.visit(ministryUrl());
        cy.get("#volunteer-ministry").should("exist");

        // The header, the button, the breadcrumb root and the sidebar entry, before.
        cy.get("#ministry-delete-btn").should("be.visible").and("contain", "Delete");
        cy.get(".breadcrumb").should("contain", "Ministries").and("not.contain", "Volunteer");
        cy.get(`a[href$="${ministryUrl()}"]`).should("exist");

        cy.get("#ministry-delete-btn").click();
        cy.get(".bootbox").should("be.visible").and("contain", MINISTRY_NAME);
        cy.get(".bootbox .btn-danger").click();

        cy.url().should("include", DASHBOARD_URL);
        cy.get("#volunteer-dashboard").should("exist");
        cy.contains("Ministry Dashboard").should("be.visible");
        cy.get(`a[href$="${ministryUrl()}"]`).should("not.exist");

        // And it really is gone — not just hidden.
        adminApi("GET", `${VOLUNTEER_URL}/ministries/${ministryId}`, null, 404);
        adminApi("GET", `${VOLUNTEER_URL}/scopes?personId=${COORDINATOR_PERSON}`, null, 200).then((resp) => {
            const stale = resp.body.scopes.filter((scope) => scope.scopeId === ministryId);
            expect(stale, "scope rows pointing at the deleted ministry").to.have.length(0);
        });
    });

    it("is not offered to a ministry coordinator, whose DELETE the API refuses anyway", () => {
        freshCoordinatorLogin();
        cy.visit(ministryUrl());
        cy.get("#volunteer-ministry").should("exist");
        cy.get("#ministry-delete-btn").should("not.exist");

        // Hiding is not security (D5): the same session's DELETE is a 403.
        cy.request({
            method: "DELETE",
            url: `${VOLUNTEER_URL}/ministries/${ministryId}`,
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status).to.eq(403);
        });
        adminApi("GET", `${VOLUNTEER_URL}/ministries/${ministryId}`, null, 200);
    });

    it("keeps the page and shows the API's reason when the delete is refused", () => {
        const refusal = "This ministry still has 4 scheduled occurrences and 2 assignments. Deactivate it instead of deleting it.";

        freshAdminLogin();
        cy.visit(ministryUrl());
        cy.get("#ministry-delete-btn").should("be.visible");

        cy.intercept("DELETE", `**${VOLUNTEER_URL}/ministries/${ministryId}`, {
            statusCode: 409,
            body: { message: refusal },
        }).as("refusedDelete");

        cy.get("#ministry-delete-btn").click();
        cy.get(".bootbox .btn-danger").click();
        cy.wait("@refusedDelete");

        cy.get(".notyf__toast--error, .notyf__toast--danger, .notyf__toast").should("contain", refusal);
        cy.url().should("include", ministryUrl());
        cy.get("#volunteer-ministry").should("exist");
        cy.get("#ministry-delete-btn").should("be.visible").and("not.be.disabled");
    });
});
