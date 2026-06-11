/// <reference types="cypress" />

/**
 * UI spec for the Attendance History tab on the Person view page.
 *
 * Test data: person 2 (Mathew Campbell) is checked into event 1 in before()
 * and cleaned up in after(). Person 1 (Admin) has no attendance records.
 *
 * Note: check-in seeding happens in before() — before beforeEach() establishes
 * the cy.session — so cy.request never runs with an active session cookie
 * and cannot corrupt the server-side PHP session.
 */
describe("Person Attendance History Tab", () => {
    const PERSON_WITH_ATTENDANCE = 2;
    const PERSON_WITHOUT_ATTENDANCE = 1;
    const EVENT_ID = 1;

    before(() => {
        // Seed: check person 2 into event 1 so the attendance tab has data
        cy.makePrivateAdminAPICall("POST", `/api/events/${EVENT_ID}/checkin`, { personId: PERSON_WITH_ATTENDANCE }, 200);
    });

    after(() => {
        // Cleanup: check person 2 out of event 1
        cy.makePrivateAdminAPICall("POST", `/api/events/${EVENT_ID}/checkout`, { personId: PERSON_WITH_ATTENDANCE }, 200);
    });

    beforeEach(() => {
        cy.setupAdminSession();
    });

    context("Tab navigation", () => {
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
            cy.visit(`/people/view/${PERSON_WITH_ATTENDANCE}`);
            // Intercept the attendance API call before clicking the tab
            cy.intercept("GET", `/api/attendance/person/${PERSON_WITH_ATTENDANCE}`).as("attendanceApi");
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
            cy.intercept("GET", `/api/attendance/person/${PERSON_WITH_ATTENDANCE}`, () => {
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
            cy.visit(`/people/view/${PERSON_WITHOUT_ATTENDANCE}`);
            cy.intercept("GET", `/api/attendance/person/${PERSON_WITHOUT_ATTENDANCE}`).as("emptyAttendance");
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
            cy.visit(`/people/view/${PERSON_WITH_ATTENDANCE}`);
            cy.intercept("GET", `/api/attendance/person/${PERSON_WITH_ATTENDANCE}`).as("attendanceApi");
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
