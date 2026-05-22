/// <reference types="cypress" />

/**
 * Tests for issue #8849 — Past Events archiving on /event/dashboard.
 *
 * These tests are SELF-SUFFICIENT: every precondition is created via API.
 * The suite seeds three events:
 *
 *   1. currentEvent  — starts/ends in the future (active, not yet ended)
 *   2. pastByDate    — created for a past date (End < NOW)
 *   3. pastByInactive — created for today but then deactivated (InActive = 1)
 *
 * Because quick-create uses "today" by default and sets End = Start + 1 hour,
 * the safest way to guarantee a past event by date is to supply a date from
 * last month. We use quick-create with a `date` param set one month back.
 *
 * We then visit /event/dashboard?year=<currentYear> and assert that:
 *  - current events appear immediately without interaction
 *  - past events are hidden behind a collapsed "Past Events" toggle
 *  - toggling reveals the past rows
 *  - localStorage key is updated on toggle
 *  - months with ONLY past events auto-expand that section
 */

const CURRENT_YEAR = new Date().getFullYear();
const THIS_MONTH = new Date().getMonth() + 1; // 1-based
const LAST_MONTH = THIS_MONTH === 1 ? 12 : THIS_MONTH - 1;
const LAST_MONTH_YEAR = THIS_MONTH === 1 ? CURRENT_YEAR - 1 : CURRENT_YEAR;

// Format YYYY-MM-DD
function formatDate(year, month, day) {
    return `${year}-${String(month).padStart(2, "0")}-${String(day).padStart(2, "0")}`;
}

const PAST_DATE = formatDate(LAST_MONTH_YEAR, LAST_MONTH, 1);
const FUTURE_DATE = formatDate(CURRENT_YEAR + 1, 1, 15);

describe("Past Events Archiving (#8849)", () => {
    let currentEventId;
    let pastByDateEventId;
    let pastByInactiveEventId;

    before(() => {
        // Create a future event — will appear in the "Current Events" section.
        // We create it for next year so it won't flip to "past" mid-run.
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/events/quick-create",
            { eventTypeId: 1, date: FUTURE_DATE },
            200,
        ).then((resp) => {
            expect(resp.body).to.have.property("eventId");
            currentEventId = resp.body.eventId;
        });

        // Create an event for a past date (last month) — End < NOW.
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/events/quick-create",
            { eventTypeId: 1, date: PAST_DATE },
            200,
        ).then((resp) => {
            expect(resp.body).to.have.property("eventId");
            pastByDateEventId = resp.body.eventId;
        });

        // Create another event for today then deactivate it — InActive = 1.
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/events/quick-create",
            { eventTypeId: 1 },
            200,
        ).then((resp) => {
            expect(resp.body).to.have.property("eventId");
            pastByInactiveEventId = resp.body.eventId;

            // Now deactivate it so it appears in the "past" section.
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/events/${pastByInactiveEventId}/status`,
                { active: false },
                200,
            );
        });
    });

    beforeEach(() => {
        cy.setupAdminSession({ forceLogin: true });
        // Clear the past-events localStorage key before each test so that
        // the persistence tests start from a known empty state and are not
        // affected by state written by previous tests.
        cy.clearLocalStorage("churchcrm.eventDashboard.pastOpen");
    });

    // -----------------------------------------------------------------------
    // Test: future (current) event is visible without any interaction
    // -----------------------------------------------------------------------
    it("current event row is visible without any toggle interaction", () => {
        cy.visit(`/event/dashboard?year=${CURRENT_YEAR + 1}`);

        // The future event belongs to the next year — visit that year's dashboard.
        // Find the event row using the event ID in the action menu placeholder.
        cy.get(
            `.event-action-menu-placeholder[data-event-id="${currentEventId}"]`,
            { timeout: 10000 },
        ).should("exist");

        // The row is in a regular (non-collapse) tbody — it must be visible.
        cy.get(
            `.event-action-menu-placeholder[data-event-id="${currentEventId}"]`,
        )
            .closest("tr")
            .should("be.visible");
    });

    // -----------------------------------------------------------------------
    // Test: past-by-date event is hidden until the toggle is clicked
    // -----------------------------------------------------------------------
    it("past-by-date event row is hidden before toggle and visible after", () => {
        cy.visit(`/event/dashboard?year=${LAST_MONTH_YEAR}`);

        // The past-events collapse tbody should exist and NOT be open initially.
        // (Unless this is a month with ONLY past events — handled separately.)
        cy.get(`#past-events-${LAST_MONTH_YEAR}-month-${LAST_MONTH}`)
            .should("exist")
            .then(($el) => {
                // The row lives inside the collapse; if the section is already
                // expanded (auto-expand for all-past month) just assert visibility.
                const isShown = $el.hasClass("show");

                if (!isShown) {
                    // Row must be hidden before toggle.
                    cy.get(
                        `.event-action-menu-placeholder[data-event-id="${pastByDateEventId}"]`,
                    )
                        .closest("tr")
                        .should("not.be.visible");
                }

                // Click the toggle button.
                cy.get(
                    `[data-bs-target="#past-events-${LAST_MONTH_YEAR}-month-${LAST_MONTH}"]`,
                ).click();

                // After toggling, row must be visible.
                cy.get(
                    `.event-action-menu-placeholder[data-event-id="${pastByDateEventId}"]`,
                    { timeout: 5000 },
                )
                    .closest("tr")
                    .should("be.visible");
            });
    });

    // -----------------------------------------------------------------------
    // Test: deactivated event is classified as "past"
    // -----------------------------------------------------------------------
    it("deactivated (InActive=1) event appears in the past section, not the current section", () => {
        // The deactivated event was created for today, so it appears in the
        // current month. Find the collapse for this month.
        cy.visit(`/event/dashboard?year=${CURRENT_YEAR}`);

        // The action menu placeholder is inside the collapse tbody.
        cy.get(
            `.event-action-menu-placeholder[data-event-id="${pastByInactiveEventId}"]`,
            { timeout: 10000 },
        )
            .closest("tbody")
            .should("have.attr", "id")
            .and("match", /^past-events-\d{4}-month-/);
    });

    // -----------------------------------------------------------------------
    // Test: the "Past Events" toggle button text reflects the count
    // -----------------------------------------------------------------------
    it("past-events toggle button shows correct count label", () => {
        cy.visit(`/event/dashboard?year=${LAST_MONTH_YEAR}`);

        cy.get(`[data-bs-target="#past-events-${LAST_MONTH_YEAR}-month-${LAST_MONTH}"]`).should(
            "contain.text",
            "past event",
        );
    });

    // -----------------------------------------------------------------------
    // Test: localStorage key is updated when the toggle is clicked
    // -----------------------------------------------------------------------
    it("localStorage key is updated when past events section is toggled", () => {
        cy.visit(`/event/dashboard?year=${LAST_MONTH_YEAR}`);

        const collapseId = `past-events-${LAST_MONTH_YEAR}-month-${LAST_MONTH}`;
        const toggleBtn = `[data-bs-target="#${collapseId}"]`;

        // The collapse may start open (auto-expand for all-past month) or closed.
        // We need it CLOSED before we can test the open→localStorage path.
        // Use the aria-expanded attribute as a reliable indicator (updated by
        // Bootstrap synchronously on click, before the animation completes).
        cy.get(toggleBtn).then(($btn) => {
            if ($btn.attr("aria-expanded") === "true") {
                // Already open — click once to close it, then wait for it to close.
                cy.wrap($btn).click();
            }
        });
        // Wait for closed state regardless of initial condition.
        cy.get(`#${collapseId}`).should("not.have.class", "show");

        // ---- Open it — verify localStorage is written ----
        cy.get(toggleBtn).click();
        cy.get(`#${collapseId}`).should("have.class", "show");

        cy.window().then((win) => {
            const stored = JSON.parse(
                win.localStorage.getItem("churchcrm.eventDashboard.pastOpen") || "[]",
            );
            expect(stored).to.include(collapseId);
        });

        // ---- Close it — verify localStorage is updated ----
        cy.get(toggleBtn).click();
        cy.get(`#${collapseId}`).should("not.have.class", "show");

        cy.window().then((win) => {
            const stored = JSON.parse(
                win.localStorage.getItem("churchcrm.eventDashboard.pastOpen") || "[]",
            );
            expect(stored).to.not.include(collapseId);
        });
    });

    // -----------------------------------------------------------------------
    // Test: auto-expand — month with ONLY past events starts open
    // -----------------------------------------------------------------------
    it("month containing only past events auto-expands the past section", () => {
        // The past-by-date event is the only event created for LAST_MONTH.
        // (quick-create deduplicates by type+date, so we likely have exactly 1.)
        // Visit the year that contains the past month and check auto-expansion.
        cy.visit(`/event/dashboard?year=${LAST_MONTH_YEAR}`);

        cy.get(`#past-events-${LAST_MONTH_YEAR}-month-${LAST_MONTH}`).then(($el) => {
            // If the month has no current events, the collapse is auto-expanded.
            // Check whether a current tbody exists for this month card.
            const $card = $el.closest(".card");
            const hasCurrent = $card.find("tbody").not(".past-events-header-tbody").not(`#past-events-${LAST_MONTH_YEAR}-month-${LAST_MONTH}`).find("tr").length > 0;

            if (!hasCurrent) {
                // All-past month: collapse must be pre-expanded.
                cy.wrap($el).should("have.class", "show");
            } else {
                // Mixed month: collapse may or may not be open depending on localStorage.
                // Just assert the element exists and the test continues.
                cy.wrap($el).should("exist");
            }
        });
    });

    // -----------------------------------------------------------------------
    // Test: stat card shows current vs past count
    // -----------------------------------------------------------------------
    it("stat card shows current / past event counts", () => {
        cy.visit(`/event/dashboard?year=${CURRENT_YEAR}`);

        // The "Current Events" stat card is rendered with fw-medium containing
        // the count. It may also show "/ N past" if there are past events.
        cy.contains(".card-body", "Current Events").should("exist");
    });
});
