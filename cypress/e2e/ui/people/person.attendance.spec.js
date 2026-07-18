/// <reference types="cypress" />

/**
 * UI spec for the Attendance History tab on the Person view page.
 *
 * Seeding strategy: person 2 (Mathew Campbell) is checked into event 1 in
 * the beforeEach() of contexts that need attendance data — AFTER
 * setupAdminSession() has established auth. The checkin call accepts both 200
 * (freshly checked in) and 409 (already checked in from a previous test run)
 * so duplicate checkins are silently tolerated.
 *
 * The after() cleanup runs as admin (cy.session is still alive after the last
 * beforeEach of the suite) to remove the attendance record.
 *
 * cy.intercept uses "**/api/..." glob patterns so intercepts work when
 * ChurchCRM is deployed under a subdirectory path.
 */
describe("Person Attendance History Tab", () => {
    const PERSON_WITH_ATTENDANCE = 2;
    const PERSON_WITHOUT_ATTENDANCE = 1;
    const EVENT_ID = 1;

    after(() => {
        // Cleanup: check person 2 out of event 1.
        // cy.session from the last beforeEach is still valid here.
        cy.setupAdminSession();
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
            // Auth FIRST, then seed — before() fires before any auth is set up
            cy.setupAdminSession();
            // Accept 200 (fresh checkin) or 409 (already checked in) — tolerate duplicates
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/events/${EVENT_ID}/checkin`,
                { personId: PERSON_WITH_ATTENDANCE },
                [200, 409],
            );
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
            cy.setupAdminSession();
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/events/${EVENT_ID}/checkin`,
                { personId: PERSON_WITH_ATTENDANCE },
                [200, 409],
            );
            cy.visit(`/people/view/${PERSON_WITH_ATTENDANCE}`);
            cy.intercept("GET", `**/api/attendance/person/${PERSON_WITH_ATTENDANCE}`).as("attendanceApi");
            cy.get("#nav-item-attendance").click();
            cy.wait("@attendanceApi");
        });

        it("type filter select is populated with at least one option beyond All", () => {
            cy.get("#attendance-tab .attendance-filter-type option").should("have.length.at.least", 2);
        });

        it("clear button resets filters and shows all records", () => {
            // Set a future date that excludes all records
            cy.get("#attendance-tab .attendance-filter-from").type("2099-01-01");
            cy.get("#attendance-tab .attendance-tbody tr").should("have.length", 0);

            // Clear the filter
            cy.get("#attendance-tab .attendance-filter-clear").click();
            cy.get("#attendance-tab .attendance-tbody tr").should("have.length.at.least", 1);
        });
    });
});
