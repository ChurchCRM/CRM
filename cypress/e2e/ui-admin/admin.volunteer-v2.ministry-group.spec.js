/// <reference types="cypress" />

/**
 * Volunteer v2 — the Groups module's view of a ministry's pool Group (D19).
 *
 * The product decision, on the UI side: a managed group stays visible EVERYWHERE —
 * in the group list, on its own page, in the cart, in email export — and only its
 * identity moves. So:
 *
 *   - `/groups/view/{id}` carries an alert saying whose pool it is, with a link to
 *     that ministry;
 *   - Edit and Delete are RENDERED, disabled, with a title saying why, both on the
 *     page and in the group list's row action menu — rendered rather than hidden,
 *     because somebody looking for Edit should find out where it went rather than
 *     wonder whether the row is broken;
 *   - `/groups/editor/{id}` redirects back to the group, since the API answers 409
 *     to every write the editor could make;
 *   - and adding/removing MEMBERS is untouched, for anyone with Manage Groups.
 *
 * The API contract is pinned by `private.volunteer.ministry-group.spec.js`; this
 * file is about what the screen does with it.
 *
 * Order inside every hook is API setup → freshAdminLogin() → cy.visit(), because
 * cy.request() rotates the PHP session cookie (cypress-testing.md). Fixtures are
 * removed in `before` as well as `after`: an `after` hook does not run when the
 * runner crashes mid-spec. No cy.dbQuery() here — the `db:query` task is
 * registered only in cypress/configs/docker.config.ts, and ui-admin specs run
 * under docker-admin.config.ts.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const MINISTRIES_URL = "/api/ministries/ministries";

const PREFIX = "UIMINGRP";
const MINISTRY_NAME = `${PREFIX} Coffee Bar`;

/** An ordinary seed group — the "nothing changed here" control. */
const PLAIN_GROUP = 1;
const POOL_PERSON = 8;

let ministryId = 0;
let groupId = 0;

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

/** Ministries take their pool Group with them, so this is the whole cleanup. */
function cleanupFixtures() {
    cy.makePrivateAdminAPICall("GET", MINISTRIES_URL, null, 200).then((resp) => {
        for (const ministry of resp.body.ministries) {
            if (ministry.name.startsWith(PREFIX)) {
                // An active ministry cannot be deleted (409): deactivate first (2026-09-17 lifecycle rule).
                cy.makePrivateAdminAPICall("POST", `${MINISTRIES_URL}/${ministry.id}`, { active: false }, [200, 404]);
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `${MINISTRIES_URL}/${ministry.id}`,
                    null,
                    [200, 404],
                );
            }
        }
    });
}

describe("Volunteer v2 — a ministry's pool Group in the Groups module (D19)", () => {
    before(() => {
        setVersion("v2");
        cleanupFixtures();

        cy.makePrivateAdminAPICall(
            "POST",
            MINISTRIES_URL,
            { name: MINISTRY_NAME },
            201,
        ).then((resp) => {
            ministryId = resp.body.ministry.id;
            groupId = resp.body.poolGroupId;
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryId}/pool/${POOL_PERSON}`,
                null,
                [200, 201],
            );
        });
    });

    after(() => {
        cleanupFixtures();
        setVersion("v1");
    });

    beforeEach(() => {
        freshAdminLogin();
    });

    it("shows the managed note on the group page, linking to the ministry", () => {
        cy.visit(`/groups/view/${groupId}`);
        cy.get("#group-ministry-pool-note")
            .should("be.visible")
            .and("contain", "volunteer pool")
            .and("contain", MINISTRY_NAME)
            .and("contain", "managed from that ministry");
        cy.get(`#group-ministry-pool-note a[href*="/ministries/${ministryId}"]`)
            .should("be.visible");
    });

    it("renders Edit and Delete disabled on the group page, with a reason", () => {
        cy.visit(`/groups/view/${groupId}`);

        // Rendered, not hidden — and not a link, so there is nothing to follow.
        cy.get("#group-edit-disabled")
            .should("be.visible")
            .and("have.class", "disabled")
            .and("have.attr", "aria-disabled", "true")
            .and("have.attr", "title")
            .and("include", MINISTRY_NAME);
        cy.get(`a[href="${Cypress.config("baseUrl")}groups/editor/${groupId}"]`).should(
            "not.exist",
        );

        cy.get("#deleteGroupButton")
            .should("be.disabled")
            .and("have.attr", "aria-disabled", "true")
            .and("have.attr", "title")
            .and("include", MINISTRY_NAME);
    });

    it("leaves the member controls alone — the roster is still edited here", () => {
        // Explicitly asked for: only the group's IDENTITY moved to the ministry.
        // Adding and removing people stays exactly where it was, for anyone with
        // Manage Groups.
        cy.visit(`/groups/view/${groupId}`);
        cy.get("#addGroupMember").should("exist");
        cy.get("#membersTable").should("be.visible");
        cy.get("#memberCountBadge").should("contain", "1");
    });

    it("redirects the group editor back to the group", () => {
        cy.visit(`/groups/editor/${groupId}`);
        cy.url().should("include", `/groups/view/${groupId}`);
        cy.url().should("not.include", "/groups/editor/");
        cy.get("#group-ministry-pool-note").should("be.visible");
    });

    it("disables Edit and Delete in the group list's row action menu", () => {
        cy.visit("/groups/dashboard");
        cy.get("#groupsTable").should("be.visible");
        // DataTables paginates; search narrows to the one row this is about.
        // `.dt-search` is the v2+/v3 layout container — the legacy
        // `.dataTables_filter` no longer exists (datatables-v3-upgrade.spec.js).
        cy.get(".dt-search input").first().type(MINISTRY_NAME);
        cy.get("#groupsTable tbody tr")
            .should("have.length", 1)
            .find("[data-bs-toggle='dropdown']")
            .click();

        cy.get("#groupsTable tbody tr .dropdown-menu").within(() => {
            cy.contains("Edit")
                .should("have.class", "disabled")
                .and("have.attr", "aria-disabled", "true")
                .and("have.attr", "title")
                .and("include", MINISTRY_NAME);
            cy.contains("Delete")
                .should("be.disabled")
                .and("have.attr", "aria-disabled", "true");
            // View is untouched: a managed group stays fully visible.
            cy.contains("View").should("not.have.class", "disabled");
        });
    });

    it("leaves an ordinary group's page and row menu exactly as they were", () => {
        cy.visit(`/groups/view/${PLAIN_GROUP}`);
        cy.get("#group-ministry-pool-note").should("not.exist");
        cy.get("#group-edit-disabled").should("not.exist");
        cy.get(`a[href*="/groups/editor/${PLAIN_GROUP}"]`).should("exist");
        cy.get("#deleteGroupButton").should("not.be.disabled");
    });
});
