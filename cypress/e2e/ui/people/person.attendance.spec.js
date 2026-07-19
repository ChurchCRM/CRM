/// <reference types="cypress" />

/**
 * UI spec for the Attendance History tab on the Person view page.
 *
 * Seeding strategy: person 2 (Mathew Campbell) is checked into event 1 via
 * cy.makePrivateAdminAPICall() in the beforeEach() of contexts that need
 * attendance data. Per `.agents/skills/churchcrm/cypress-testing.md` →
 * "UI Tests Must Not Call APIs After Login": cy.request() (which
 * makePrivateAdminAPICall uses) sends and receives cookies for the app's
 * own origin regardless of `withCredentials`, so any API-key call made
 * while a UI session cookie is already present clobbers that browser
 * session's PHP session data. `cy.setupAdminSession()` afterward is NOT
 * a reliable fix — its cache validator only checks that a session cookie
 * exists, not that the underlying PHP session is still alive — so contexts
 * that mix an API call with a browser visit use the local freshAdminLogin()
 * helper (real clear + form login) AFTER the API call instead, exactly as
 * documented in the skill. The checkin call accepts both 200 (freshly
 * checked in) and 409 (already checked in from a previous test run) so
 * duplicate checkins are silently tolerated.
 *
 * The after() cleanup is a pure API call (checkout) and needs no browser
 * session at all — makePrivateAdminAPICall authenticates via x-api-key only.
 *
 * cy.intercept uses double-star ("**") glob patterns so intercepts work when
 * ChurchCRM is deployed under a subdirectory path.
 */

// Local helper — NOT a cy.* command (see cypress-testing.md). Clears cookies and
// does a direct form login, discarding any dead PHP session left by a prior
// cy.request() / makePrivateAdminAPICall() call. Required after any API call
// that precedes a cy.visit() — cy.setupAdminSession() is not reliable here.
function freshAdminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.env("admin.username"));
    cy.get("input[name=Password]").type(Cypress.env("admin.password") + "{enter}");
    cy.url().should("not.include", "/session/begin");
}

describe("Person Attendance History Tab", () => {
    const PERSON_WITH_ATTENDANCE = 2;
    const PERSON_WITHOUT_ATTENDANCE = 1;
    const EVENT_ID = 1;

    after(() => {
        // Cleanup: check person 2 out of event 1.
        // makePrivateAdminAPICall() authenticates via x-api-key alone — it needs no
        // browser session, so no login call here (see file header).
        cy.makePrivateAdminAPICall("POST", `/api/events/${EVENT_ID}/checkout`, { personId: PERSON_WITH_ATTENDANCE }, 200);
    });

    context("Tab navigation", () => {
        beforeEach(() => {
            cy.setupAdminSession();
        });

        it("shows the Attendance tab nav item on the person view", () => {
            cy.visit(`/people/view/${PERSON_WITH_ATTENDANCE}`);
            cy.get("#nav-item-attendance").should("exist").and("be.visible");
        });

        it("Attendance tab pane is present in the DOM", () => {
            cy.visit(`/people/view/${PERSON_WITH_ATTENDANCE}`);
            cy.get("#attendance").should("exist");
        });
    });

    context("Lazy load on tab activation — person with attendance", () => {
        beforeEach(() => {
            // API setup FIRST, then freshAdminLogin() — NOT cy.setupAdminSession(),
            // see file header. Accept 200 (fresh checkin) or 409 (already checked
            // in) — tolerate duplicates.
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/events/${EVENT_ID}/checkin`,
                { personId: PERSON_WITH_ATTENDANCE },
                [200, 409],
            );
            freshAdminLogin();
            cy.visit(`/people/view/${PERSON_WITH_ATTENDANCE}`);
            // Use **/api/** glob so intercepts work under a subdirectory deployment
            cy.intercept("GET", `**/api/attendance/person/${PERSON_WITH_ATTENDANCE}`).as("attendanceApi");
            // Click the attendance tab to trigger lazy load
            cy.get("#nav-item-attendance").click();
        });

        it("fires an API request when tab is first activated", () => {
            cy.wait("@attendanceApi").its("response.statusCode").should("eq", 200);
        });

        it("hides the loading spinner after data loads", () => {
            cy.wait("@attendanceApi");
            cy.get("#attendance-tab .attendance-loading").should("have.class", "d-none");
        });

        it("shows the summary stats section", () => {
            cy.wait("@attendanceApi");
            cy.get("#attendance-tab .attendance-summary").should("be.visible");
        });

        it("shows a non-zero total events stat", () => {
            cy.wait("@attendanceApi");
            cy.get("#attendance-tab .attendance-stat-total")
                .invoke("text")
                .then((text) => {
                    expect(parseInt(text.trim(), 10)).to.be.at.least(1);
                });
        });

        it("shows the filter controls", () => {
            cy.wait("@attendanceApi");
            cy.get("#attendance-tab .attendance-filters").should("be.visible");
        });

        it("shows the attendance table with at least one row", () => {
            cy.wait("@attendanceApi");
            cy.get("#attendance-tab .attendance-tbody tr").should("have.length.at.least", 1);
        });

        it("event link uses the correct /event/view/:id path", () => {
            cy.wait("@attendanceApi");
            cy.get("#attendance-tab .attendance-tbody tr:first-child a")
                .should("have.attr", "href")
                .and("match", /\/event\/view\/\d+/);
        });

        it("does NOT fire a second API request when tab is re-activated (cache hit)", () => {
            cy.wait("@attendanceApi");

            // Navigate to another tab then come back
            cy.get("#nav-item-timeline").click();

            let secondCallCount = 0;
            cy.intercept("GET", `**/api/attendance/person/${PERSON_WITH_ATTENDANCE}`, () => {
                secondCallCount++;
            }).as("secondAttendanceApi");

            cy.get("#nav-item-attendance").click();

            // Give a moment to ensure no second request fires
            // eslint-disable-next-line cypress/no-unnecessary-waiting
            cy.wait(300).then(() => {
                expect(secondCallCount).to.equal(0);
            });
        });
    });

    context("Person with no attendance records", () => {
        beforeEach(() => {
            cy.setupAdminSession();
            cy.visit(`/people/view/${PERSON_WITHOUT_ATTENDANCE}`);
            cy.intercept("GET", `**/api/attendance/person/${PERSON_WITHOUT_ATTENDANCE}`).as("emptyAttendance");
            cy.get("#nav-item-attendance").click();
        });

        it("shows zero total events", () => {
            cy.wait("@emptyAttendance");
            cy.get("#attendance-tab .attendance-stat-total")
                .invoke("text")
                .then((text) => {
                    expect(parseInt(text.trim(), 10)).to.equal(0);
                });
        });

        it("shows an empty table with no rows", () => {
            cy.wait("@emptyAttendance");
            cy.get("#attendance-tab .attendance-tbody tr").should("have.length", 0);
        });
    });

    context("Filter controls", () => {
        beforeEach(() => {
            // API setup FIRST, then freshAdminLogin() — NOT cy.setupAdminSession(),
            // see file header.
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/events/${EVENT_ID}/checkin`,
                { personId: PERSON_WITH_ATTENDANCE },
                [200, 409],
            );
            freshAdminLogin();
            cy.visit(`/people/view/${PERSON_WITH_ATTENDANCE}`);
            cy.intercept("GET", `**/api/attendance/person/${PERSON_WITH_ATTENDANCE}`).as("attendanceApi");
            cy.get("#nav-item-attendance").click();
            cy.wait("@attendanceApi");
        });

        it("type filter select is populated with at least one option beyond All", () => {
            cy.get("#attendance-tab .attendance-filter-type option").should("have.length.at.least", 2);
        });

        it("clear button resets filters and shows all records", () => {
            // Set a future date that excludes all records. .clear() first — matches
            // the working pattern used elsewhere for native date inputs (e.g.
            // tax-report-pdf.spec.js) and ensures the "change" event that
            // attendance-history.ts listens on actually fires.
            cy.get("#attendance-tab .attendance-filter-from").clear().type("2099-01-01");
            cy.get("#attendance-tab .attendance-tbody tr").should("have.length", 0);

            // Clear the filter
            cy.get("#attendance-tab .attendance-filter-clear").click();
            cy.get("#attendance-tab .attendance-tbody tr").should("have.length.at.least", 1);
        });
    });
});
