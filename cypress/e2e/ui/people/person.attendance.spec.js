/// <reference types="cypress" />

/**
 * UI spec for the Attendance History tab on the Person view page.
 *
 * Seeding strategy: person 2 (Mathew Campbell) is checked into event 1 via
 * cy.makePrivateAdminAPICall() in the beforeEach() of contexts that need
 * attendance data. Per `.agents/skills/churchcrm/cypress-testing.md` →
 * "UI Tests Must Not Call APIs After Login": `makePrivateAdminAPICall` does
 * set `withCredentials: false` on its cy.request() call (see
 * cypress/support/api-commands.js), but CI has repeatedly confirmed that
 * alone is NOT sufficient to stop the browser's session cookie from being
 * shared with the request — so an API-key call made while a UI session
 * cookie is already present still clobbers that browser session's PHP
 * session data. `cy.setupAdminSession()` afterward is NOT a reliable fix
 * either — its cache validator only checks that a session cookie exists,
 * not that the underlying PHP session is still alive — so contexts that mix
 * an API call with a browser visit use the local cy.freshAdminFormLogin() helper
 * (real clear + form login) AFTER the API call instead, exactly as
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

// Direct form login. cy.request() before cy.visit() kills the PHP session, and
// cy.setupAdminSession() does not recover it. 
describe("Person Attendance History Tab", () => {
    const PERSON_WITH_ATTENDANCE = 2;
    const PERSON_WITHOUT_ATTENDANCE = 1;
    const EVENT_ID = 1;

    after(() => {
        // Cleanup: check person 2 out of event 1.
        // makePrivateAdminAPICall() authenticates via x-api-key alone — it needs no
        // browser session, so no login call here (see file header).
        cy.makePrivateAdminAPICall("POST", `/api/events/${EVENT_ID}/checkout`, { personId: PERSON_WITH_ATTENDANCE }, 200);
        // Checking out only stamps a checkout date — the event_attend row
        // stays, which used to leave one behind per run (#9769). This is what
        // actually removes it.
        cy.makePrivateAdminAPICall(
            "DELETE",
            `/api/events/${EVENT_ID}/attendance/${PERSON_WITH_ATTENDANCE}`,
            null,
            [200, 404],
        );
    });

    context("Lazy load on tab activation — person with attendance", () => {
        beforeEach(() => {
            // API setup FIRST, then cy.freshAdminFormLogin() — NOT cy.setupAdminSession(),
            // see file header. Accept 200 (fresh checkin) or 409 (already checked
            // in) — tolerate duplicates.
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/events/${EVENT_ID}/checkin`,
                { personId: PERSON_WITH_ATTENDANCE },
                [200, 409],
            );
            cy.freshAdminFormLogin({ sessionCookie: true });
            cy.visit(`/people/view/${PERSON_WITH_ATTENDANCE}`);
            // Use **/api/** glob so intercepts work under a subdirectory deployment
            cy.intercept("GET", `**/api/attendance/person/${PERSON_WITH_ATTENDANCE}`).as("attendanceApi");
            // Click the attendance tab to trigger lazy load
            cy.get("#nav-item-attendance").click();
        });

        it("loads the person's attendance the first time the tab opens", () => {
            cy.wait("@attendanceApi").its("response.statusCode").should("eq", 200);
            cy.get("#attendance-tab .attendance-loading").should("have.class", "d-none");
            cy.get("#attendance-tab .attendance-summary").should("be.visible");
            cy.get("#attendance-tab .attendance-filters").should("be.visible");
            // cy.wait() covers the network only, not the render in attendance-history.ts; retry the read.
            cy.get("#attendance-tab .attendance-stat-total").should(($el) => {
                expect(parseInt($el.text().trim(), 10)).to.be.at.least(1);
            });
            cy.get("#attendance-tab .attendance-tbody tr").should("have.length.at.least", 1);
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

        it("shows zero events and an empty table", () => {
            cy.wait("@emptyAttendance");
            cy.get("#attendance-tab .attendance-stat-total").should(($el) => {
                expect(parseInt($el.text().trim(), 10)).to.equal(0);
            });
            cy.get("#attendance-tab .attendance-tbody tr").should("have.length", 0);
        });
    });

    context("Filter controls", () => {
        beforeEach(() => {
            // API setup FIRST, then cy.freshAdminFormLogin() — NOT cy.setupAdminSession(),
            // see file header.
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/events/${EVENT_ID}/checkin`,
                { personId: PERSON_WITH_ATTENDANCE },
                [200, 409],
            );
            cy.freshAdminFormLogin({ sessionCookie: true });
            cy.visit(`/people/view/${PERSON_WITH_ATTENDANCE}`);
            cy.intercept("GET", `**/api/attendance/person/${PERSON_WITH_ATTENDANCE}`).as("attendanceApi");
            cy.get("#nav-item-attendance").click();
            cy.wait("@attendanceApi");
        });

        it("clear button resets filters and shows all records", () => {
            cy.get("#attendance-tab .attendance-filter-type option").should("have.length.at.least", 2);
            // Set a future date that excludes all records. attendance-history.ts
            // applies the filter on the "change" event, which native date inputs
            // only fire on blur — .type() alone doesn't blur the field, and the
            // very next command here is a read-only assertion (no click to move
            // focus elsewhere), so an explicit .blur() is required to trigger it.
            cy.get("#attendance-tab .attendance-filter-from").clear().type("2099-01-01").blur();
            cy.get("#attendance-tab .attendance-tbody tr").should("have.length", 0);

            // Clear the filter
            cy.get("#attendance-tab .attendance-filter-clear").click();
            cy.get("#attendance-tab .attendance-tbody tr").should("have.length.at.least", 1);
        });
    });
});
