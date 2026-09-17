/// <reference types="cypress" />

/**
 * Volunteer v2 — the two sidebar headings (product-owner navigation decision,
 * epic #9701).
 *
 * The administration surface moved out of the "Volunteer" heading and into a
 * heading of its own:
 *
 *   - **Ministries** — the administration surface. Shown to a volunteer
 *     coordinator-or-above (`User::isVolunteerCoordinatorEnabled()`, the same
 *     predicate `VolunteerCoordinatorRoleAuthMiddleware` asks). It holds
 *     *Dashboard* and then one entry per ministry the viewer may administer,
 *     by name — every active ministry for an administrator or a global
 *     volunteer manager, exactly the ministry scopes for anybody else, and
 *     nothing at all for a pure team leader.
 *   - ~~**Volunteer**~~ — **gone** (#9867, Member Portal P16). The member
 *     surface lives only in the Member Portal now, so there is no "Volunteer"
 *     heading in the admin sidebar in ANY rollout state and for ANY user.
 *     Everything below that used to assert its two entries asserts its absence
 *     instead; "Ministries" is unchanged in every case.
 *
 * The `/volunteer/ministries` list page is gone with it: the sidebar lists the
 * ministries now, so the route 302s to the dashboard and the dashboard keeps
 * the "New ministry" quick action.
 *
 * Order inside every hook is API setup → freshLogin() → cy.visit(), because
 * cy.request() rotates the PHP session cookie (cypress-testing.md). Fixture
 * rows are removed in `before` as well as `after`: an `after` hook does not run
 * when the runner crashes mid-spec.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const VOLUNTEER_URL = "/api/volunteer";
const DASHBOARD_URL = "/volunteer/dashboard";
const MINISTRIES_URL = "/volunteer/ministries";

const PREFIX = "UIMENU";
const MINISTRY_A = `${PREFIX} Hospitality`;
const MINISTRY_B = `${PREFIX} Worship`;
const TEAM_A = `${PREFIX} Greeters`;
// Short on purpose: MenuItem::getName() truncates a label longer than 25
// characters to 22 plus " ...", exactly as it does for a long Group name.
const CREATE_NAME = `${PREFIX} New`;

/** tony.wade — every flag but Admin, and NOT a volunteer manager. */
const COORDINATOR_PERSON = 3;

let ministryA = 0;
let ministryB = 0;
let teamA = 0;

// Local helpers — NOT cy.* commands (cypress-testing.md).
function freshAdminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.env("admin.username"));
    cy.get("input[name=Password]").type(Cypress.env("admin.password") + "{enter}");
    cy.url().should("not.include", "/session/begin");
}

/** Log in as the standard user (person 3) — the person this spec scopes. */
function freshStandardLogin() {
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

function grantScope(personId, scopeType, scopeId) {
    return adminApi("POST", `${VOLUNTEER_URL}/scopes`, { personId, scopeType, scopeId }, [200, 201]);
}

function cleanupScopes() {
    adminApi("GET", `${VOLUNTEER_URL}/scopes?personId=${COORDINATOR_PERSON}`, null, 200).then(
        (resp) => {
            for (const scope of resp.body.scopes) {
                adminApi("DELETE", `${VOLUNTEER_URL}/scopes/${scope.id}`, null, [200, 404]);
            }
        },
    );
}

/** Teams cascade from the ministry row, so one DELETE per ministry is enough. */
function cleanupMinistries() {
    adminApi("GET", `${VOLUNTEER_URL}/ministries`, null, 200).then((resp) => {
        for (const ministry of resp.body.ministries) {
            if (ministry.name.startsWith(PREFIX)) {
                // An active ministry cannot be deleted (409): deactivate first (2026-09-17 lifecycle rule).
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

/**
 * The collapse container of a top-level sidebar heading, found by its own
 * label. `MenuRenderer::renderSubMenuItem()` gives the heading an anchor with
 * `data-bs-toggle="collapse"` whose href is the `#menu-N` id of the container,
 * and nothing else on the page carries an id — so the href IS the selector.
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

function menuSectionShouldNotExist(title) {
    cy.get("a[data-bs-toggle='collapse'] .nav-link-title")
        .filter((_i, el) => el.textContent.trim() === title)
        .should("have.length", 0);
}

describe("Volunteer v2 — the Ministries sidebar heading", () => {
    before(() => {
        setVersion("v2");
        cleanupFixtures();

        adminApi("POST", `${VOLUNTEER_URL}/ministries`, { name: MINISTRY_A }, 201).then((resp) => {
            ministryA = resp.body.ministry.id;

            adminApi(
                "POST",
                `${VOLUNTEER_URL}/ministries/${ministryA}/teams`,
                { name: TEAM_A, description: "Front door" },
                201,
            ).then((teamResp) => {
                teamA = teamResp.body.team.id;
            });
        });

        adminApi("POST", `${VOLUNTEER_URL}/ministries`, { name: MINISTRY_B }, 201).then((resp) => {
            ministryB = resp.body.ministry.id;
        });
    });

    after(() => {
        cleanupFixtures();
        setVersion("v1");
    });

    describe("an administrator", () => {
        beforeEach(() => {
            freshAdminLogin();
            cy.visit(DASHBOARD_URL);
        });

        it("gets a Ministries heading holding Dashboard and every active ministry", () => {
            menuSection("Ministries").within(() => {
                cy.get(`a[href$="${DASHBOARD_URL}"]`).should("exist");
                cy.get(`a[href$="${MINISTRIES_URL}/${ministryA}"]`)
                    .should("exist")
                    .and("contain", MINISTRY_A);
                cy.get(`a[href$="${MINISTRIES_URL}/${ministryB}"]`)
                    .should("exist")
                    .and("contain", MINISTRY_B);
            });
        });

        it("has no Volunteer heading at all, and no member links anywhere", () => {
            menuSectionShouldNotExist("Volunteer");
            // The member pages are in the portal; the sidebar must not link to
            // either of their retired URLs.
            cy.get('a[href$="/volunteer/my-schedule"]').should("not.exist");
            cy.get('a[href$="/volunteer/opportunities"]').should("not.exist");
        });

        it("leaves Ministries holding only the administration surface", () => {
            menuSection("Ministries").within(() => {
                cy.get(`a[href$="${DASHBOARD_URL}"]`).should("exist");
                // Every entry under this heading is either the dashboard or a
                // ministry page — never a member page. The count itself is not
                // asserted: an administrator sees every ministry, and another
                // spec's fixture may still be around. The nested Deactivated
                // Ministries group's own toggle is a collapse anchor, not a link.
                cy.get("a.nav-link:not([data-bs-toggle])").each(($link) => {
                    expect($link.attr("href")).to.match(
                        /\/volunteer\/(dashboard|ministries\/\d+)$/,
                    );
                });
            });
        });

        it("highlights the open ministry's entry and opens the Ministries heading", () => {
            cy.visit(`${MINISTRIES_URL}/${ministryA}`);
            menuSection("Ministries")
                .should("have.class", "show")
                .within(() => {
                    cy.get(`a[href$="${MINISTRIES_URL}/${ministryA}"]`).should(
                        "have.class",
                        "active",
                    );
                    cy.get(`a[href$="${MINISTRIES_URL}/${ministryB}"]`).should(
                        "not.have.class",
                        "active",
                    );
                });
        });

        it("302s the retired ministries list page to the dashboard", () => {
            cy.request({ url: MINISTRIES_URL, followRedirect: false }).then((resp) => {
                expect(resp.status).to.eq(302);
                expect(resp.headers.location).to.match(/\/volunteer\/dashboard$/);
            });

            cy.visit(MINISTRIES_URL);
            cy.url().should("include", DASHBOARD_URL);
            cy.get("#volunteer-dashboard").should("exist");
        });

        it("still creates a ministry from the dashboard's New ministry action", () => {
            cy.get("#volunteer-quick-actions #ministry-new-btn")
                .should("be.visible")
                .and("contain", "New ministry")
                .click();

            cy.get("#ministryCreateModal").should("be.visible");
            cy.get("#ministry-create-name").should("be.focused").type(CREATE_NAME);
            cy.get("#ministry-create-save").click();

            cy.url().should("match", /\/volunteer\/ministries\/\d+$/);
            cy.get("#volunteer-ministry .card-title").should("contain", CREATE_NAME);

            // And the new ministry is in the sidebar on the page it landed on.
            menuSection("Ministries").should("contain", CREATE_NAME);
        });
    });

    describe("a ministry coordinator (person 3, one ministry scope)", () => {
        before(() => {
            cleanupScopes();
            grantScope(COORDINATOR_PERSON, "ministry", ministryA);
        });

        after(() => {
            cleanupScopes();
        });

        beforeEach(() => {
            freshStandardLogin();
            cy.visit(DASHBOARD_URL);
        });

        it("sees Dashboard and their own ministry, and no other ministry", () => {
            menuSection("Ministries").within(() => {
                cy.get(`a[href$="${DASHBOARD_URL}"]`).should("exist");
                cy.get(`a[href$="${MINISTRIES_URL}/${ministryA}"]`)
                    .should("exist")
                    .and("contain", MINISTRY_A);
                cy.get(`a[href$="${MINISTRIES_URL}/${ministryB}"]`).should("not.exist");
            });
            // Scoped to the heading, not to the whole sidebar: a ministry is
            // created with a pool Group of the same name (D19), and the Groups
            // heading lists it — that is intended, and it is not this menu.
            menuSection("Ministries").should("not.contain", MINISTRY_B);
        });

        it("gets no Volunteer heading either", () => {
            menuSectionShouldNotExist("Volunteer");
            cy.get('a[href$="/volunteer/my-schedule"]').should("not.exist");
            cy.get('a[href$="/volunteer/opportunities"]').should("not.exist");
        });
    });

    describe("a pure team leader (person 3, one team scope)", () => {
        before(() => {
            cleanupScopes();
            grantScope(COORDINATOR_PERSON, "team", teamA);
        });

        after(() => {
            cleanupScopes();
        });

        beforeEach(() => {
            freshStandardLogin();
            cy.visit(DASHBOARD_URL);
        });

        it("sees the Ministries heading with Dashboard alone", () => {
            menuSection("Ministries").within(() => {
                cy.get(`a[href$="${DASHBOARD_URL}"]`).should("exist");
                // Leading a team is not administering the ministry above it
                // (design §4.6), so no ministry entry is offered.
                cy.get("a.nav-link").should("have.length", 1);
            });
            menuSection("Ministries").should("not.contain", MINISTRY_A);
        });
    });

    describe("a volunteer who coordinates nothing", () => {
        before(() => {
            cleanupScopes();
        });

        beforeEach(() => {
            freshStandardLogin();
            cy.visit("/people/view/1");
        });

        it("gets neither heading — and reaches volunteering through the portal", () => {
            menuSectionShouldNotExist("Ministries");
            menuSectionShouldNotExist("Volunteer");

            // This user is staff (person 3 has module permissions), so they land
            // in the admin shell; their own schedule is in the portal.
            cy.visit("/portal/volunteer/schedule");
            cy.get("#volunteer-my-schedule").should("exist");
            cy.get("#portal-nav").find('a[href$="/portal/volunteer/schedule"]').should("exist");
        });
    });

    describe("with the rollout flag off (v1)", () => {
        before(() => {
            setVersion("v1");
        });

        after(() => {
            setVersion("v2");
        });

        it("shows neither heading", () => {
            freshAdminLogin();
            cy.visit("/people/view/1");
            menuSectionShouldNotExist("Ministries");
            // "Volunteer" is absent in every state now (#9867), not just this one.
            menuSectionShouldNotExist("Volunteer");
        });
    });
});
