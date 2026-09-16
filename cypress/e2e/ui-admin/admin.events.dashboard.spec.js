/// <reference types="cypress" />

/**
 * Ids of the events this spec created through the API, so they can be removed
 * again when the spec finishes. Leftover events are not harmless: the events
 * dashboard groups rows by month and hides "past" rows inside a collapsed
 * <tbody>, so every stale row makes the next run's assertions less stable
 * (issue #9795).
 */
const createdEventIds = [];

/**
 * Local helper — NOT a cy.* command, and deliberately copied into this spec
 * rather than added to cypress/support (see
 * `.agents/skills/churchcrm/cypress-testing.md` → "UI Tests Must Not Call APIs
 * After Login").
 *
 * Every cy.request()-backed call — makePrivateAdminAPICall included — makes PHP
 * issue a new session, which invalidates the browser session a later cy.visit()
 * needs. `cy.setupAdminSession({ forceLogin: true })` is documented as not
 * sufficient to recover from that, so any API call that precedes a cy.visit()
 * in this spec is followed by a real clear-and-form-login instead.
 */
function freshAdminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.env("admin.username"));
    cy.get("input[name=Password]").type(Cypress.env("admin.password") + "{enter}");
    // Leaving /session/begin is not by itself proof of a successful login (an
    // error page would satisfy it too) — confirm a CRM session cookie exists,
    // the same check cy.session()'s validate() uses in support/ui-commands.js.
    cy.url().should("not.include", "/session/begin");
    cy.getCookies().should("satisfy", (cookies) =>
        cookies.some((cookie) => cookie.name.startsWith("CRM-")),
    );
}

/**
 * Helper — quick-create a fresh event using the seeded "Church Service"
 * event type (id 1) and return its id via a callback. Centralized so
 * every test in this file can guarantee the dashboard has at least one
 * row to assert against.
 *
 * Tests in this file are SELF-SUFFICIENT — they never depend on seed
 * data being populated, only on the seeded event types being present
 * (which the other passing event specs also rely on).
 *
 * Only events this call actually created are tracked for cleanup —
 * /api/events/quick-create returns an already existing event (created:false)
 * when one of the same type exists for the day, and that one is not ours to
 * delete.
 */
function createTestEvent(callback) {
    cy.makePrivateAdminAPICall(
        "POST",
        "/api/events/quick-create",
        { eventTypeId: 1 },
        200,
    ).then((createResp) => {
        expect(createResp.body).to.have.property("eventId");
        if (createResp.body.created) {
            createdEventIds.push(createResp.body.eventId);
        }
        callback(createResp.body.eventId);
    });
}

/**
 * Helper — create an event with a caller-supplied unique title and return its
 * id. POST /api/events answers with `{success:true}` only, so the event is
 * looked up again by its title marker.
 *
 * The event is dated 31 December of the current year so it always lands in the
 * dashboard's default view (current year, all months) and is still in the
 * future, which puts its row in the always-visible "current events" tbody.
 */
function createUniqueEvent(title, callback) {
    const year = new Date().getFullYear();

    cy.makePrivateAdminAPICall(
        "POST",
        "/api/events",
        {
            Title: title,
            Type: 1,
            Desc: "",
            Text: "",
            Start: `${year}-12-31 23:00:00`,
            End: `${year}-12-31 23:59:00`,
            PinnedCalendars: [],
        },
        200,
    );

    cy.makePrivateAdminAPICall("GET", "/api/events", null, 200).then((resp) => {
        const matches = resp.body.Events.filter((evt) => evt.Title === title);
        expect(matches, `exactly one event titled "${title}"`).to.have.length(1);
        createdEventIds.push(matches[0].Id);
        callback(matches[0].Id);
    });
}

/**
 * Helper — open the action dropdown of one specific event row.
 *
 * Rows for events that already ended, or that were deactivated, live in a
 * collapsed `tbody.past-events-body`. Expand that group explicitly before
 * interacting with the row, otherwise the menu item is unreachable as soon as
 * the database holds other events in the same month (issue #9795).
 */
function openEventActionMenu(eventId) {
    const rowSelector = `.event-action-menu-placeholder[data-event-id="${eventId}"]`;

    cy.get(rowSelector, { timeout: 10000 }).then(($placeholder) => {
        const $collapsed = $placeholder.closest("tbody.past-events-body:not(.expanded)");
        if ($collapsed.length > 0) {
            cy.get(`[data-past-toggle="${$collapsed.attr("id")}"]`).click();
        }
    });

    cy.get(rowSelector).should("be.visible");
    // The menu markup is injected by JS once the locales are ready.
    cy.get(rowSelector)
        .find(".dropdown button[data-bs-toggle='dropdown']", { timeout: 10000 })
        .click();
}

describe("Events Dashboard (MVC)", () => {
    beforeEach(() => cy.setupAdminSession());

    it("should display the events dashboard with stat cards", () => {
        cy.visit("event/dashboard");
        cy.contains("Events Dashboard").should("exist");
        cy.contains("Events This Year").should("exist");
        cy.contains("Total Check-ins").should("exist");
        cy.contains("Current Events").should("exist");
        cy.contains("Event Types").should("exist");
    });

    it("should have quick action buttons", () => {
        cy.visit("event/dashboard");
        cy.contains("Add Event").should("exist");
        cy.contains("Add Recurring Event").should("exist");
        cy.contains("Check-in").should("exist");
        cy.contains("Calendar").should("exist");
    });

    /**
     * Issue #8658 comment: users asked for a button to create a Recurring
     * Event alongside the "Add Event" button — previously only surfaced in
     * the empty-state message, so volunteers with any existing events
     * couldn't find it. Asserts the button exists and points at the
     * repeat-editor route.
     */
    it("Add Recurring Event button links to /event/repeat-editor", () => {
        cy.visit("event/dashboard");
        cy.contains("a", "Add Recurring Event")
            .should("have.attr", "href")
            .and("match", /\/event\/repeat-editor$/);
    });

    it("should have event type, month, and year filters", () => {
        cy.visit("event/dashboard");
        cy.get("#type").should("exist");
        cy.get("#month").should("exist");
        cy.get("#year").should("exist");
        cy.get("#type option").should("have.length.at.least", 1);
        // Month dropdown has 13 options: 1 for "All Months" + 12 calendar months
        cy.get("#month option").should("have.length", 13);
    });

    it("should filter dashboard by URL params", () => {
        cy.visit("event/dashboard?year=2024");
        cy.contains("Events Dashboard").should("exist");
        cy.url().should("include", "year=2024");
    });

    it("should filter dashboard by month URL param", () => {
        cy.visit("event/dashboard?month=1");
        cy.contains("Events Dashboard").should("exist");
        cy.url().should("include", "month=1");
        // Month select should reflect the param
        cy.get("#month").should("have.value", "1");
    });

    it("should have Manage Event Types button in header", () => {
        cy.visit("event/dashboard");
        cy.contains("Manage Event Types").should("exist");
    });

    it("Manage Event Types navigates to /event/types", () => {
        cy.visit("event/dashboard");
        cy.contains("Manage Event Types").click();
        cy.url().should("include", "/event/types");
    });

    describe("Stat cards data accuracy", () => {
        it("Event Types card shows total types, not types-with-events-this-year", () => {
            // Fetch the actual count via the API and assert the dashboard matches.
            // /api/events/types returns { EventTypes: [...] } — read the
            // wrapped array, NOT the raw response body.
            cy.makePrivateAdminAPICall("GET", "/api/events/types", null, 200).then((apiResp) => {
                expect(apiResp.body).to.have.property("EventTypes");
                const apiCount = apiResp.body.EventTypes.length;
                freshAdminLogin();
                cy.visit("event/dashboard");

                // The view renders the count as a plain <div class="fw-medium">
                // inside the Event Types card-body — not as <h2>/<h3>. Match the
                // stat label text and read the digit from its sibling .fw-medium.
                cy.contains(".card-body", "Event Types").within(() => {
                    cy.get(".fw-medium").first().invoke("text").then((txt) => {
                        const shown = parseInt(txt.replace(/\D/g, ""), 10);
                        expect(shown).to.equal(apiCount);
                    });
                });
            });
        });

        it("event title row does not render Quill empty placeholder (<p><br /></p>)", () => {
            // Ensure at least one row exists so the assertion is meaningful.
            createTestEvent(() => {
                freshAdminLogin();
                cy.visit("event/dashboard");
                // The literal markup must NEVER appear as text under any event row
                cy.get("table tbody").should("not.contain.text", "<p>");
                cy.get("table tbody").should("not.contain.text", "<br />");
            });
        });
    });

    describe("Inactive event guards", () => {
        it("shows a warning banner on /event/checkin/{id} for an inactive event", () => {
            // Create our own event, deactivate it, verify the banner.
            createTestEvent((eventId) => {
                cy.makePrivateAdminAPICall(
                    "POST",
                    `/api/events/${eventId}/status`,
                    { active: false },
                    200,
                );
                freshAdminLogin();

                cy.visit(`event/checkin/${eventId}`);

                // The walk-in form should NOT be present
                cy.get("#checkinBtn").should("not.exist");
                // The inactive warning banner should be visible
                cy.contains("This event is inactive").should("be.visible");
            });
        });

        it("API rejects check-in to inactive event with 409", () => {
            createTestEvent((eventId) => {
                cy.makePrivateAdminAPICall(
                    "POST",
                    `/api/events/${eventId}/status`,
                    { active: false },
                    200,
                );

                cy.makePrivateAdminAPICall(
                    "POST",
                    `/api/events/${eventId}/checkin`,
                    { personId: 1 },
                    409,
                );
            });
        });
    });

    describe("Event action menu", () => {
        // This suite owns exactly one event: created once through the API with
        // a unique title marker, acted on by id, and deleted again in after().
        // Targeting our own row — instead of the first row in the table — keeps
        // the assertions stable no matter how many other events the database
        // already holds (issue #9795).
        const eventTitle = `Events Dashboard Action Menu ${Date.now()}`;
        let testEventId;

        before(() => {
            // API-only setup: this leaves a dead PHP session behind, which is
            // why the beforeEach below logs in for real before any cy.visit().
            createUniqueEvent(eventTitle, (id) => {
                testEventId = id;
            });
        });

        beforeEach(() => {
            // The Deactivate test flips the event's status — make sure every
            // test starts from an active event.
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/events/${testEventId}/status`,
                { active: true },
                200,
            );
            // Both this call and the before() hook above leave a dead PHP
            // session behind, so every test in this suite starts from a real
            // login rather than a restored one.
            freshAdminLogin();
        });

        it("renders the standard action dropdown for each event row", () => {
            cy.visit("event/dashboard");
            // Wait for the action menu to be hydrated by JS
            cy.get(".event-action-menu-placeholder .dropdown", { timeout: 10000 })
                .should("have.length.at.least", 1);
        });

        it("event title link navigates to the read-only event view page", () => {
            cy.visit("event/dashboard");
            cy.contains("table tbody tr td:first-child a", eventTitle, { timeout: 10000 })
                .should("have.attr", "href")
                .and("include", `/event/view/${testEventId}`);
        });

        it("dropdown menu has View, Edit, Check-in, Deactivate, Delete items", () => {
            cy.visit("event/dashboard");
            openEventActionMenu(testEventId);

            cy.get(`.event-action-menu-placeholder[data-event-id="${testEventId}"]`)
                .find(".dropdown-menu.show")
                .within(() => {
                    cy.contains("View").should("exist");
                    cy.contains("Edit").should("exist");
                    cy.contains("Check-in").should("exist");
                    // For an active event the toggle says Deactivate
                    cy.contains(/Deactivate|Activate/).should("exist");
                    cy.contains("Delete").should("exist");
                });
        });

        it("Deactivate POSTs /api/events/{id}/status with active=false", () => {
            cy.intercept("POST", "**/api/events/*/status").as("status");
            cy.visit("event/dashboard");

            // Find the event we created for this suite and deactivate it via the menu
            openEventActionMenu(testEventId);

            cy.get(`.event-action-menu-placeholder[data-event-id="${testEventId}"]`)
                .find(".dropdown-menu.show")
                .contains("Deactivate")
                .click();

            cy.wait("@status").then(({ request, response }) => {
                expect(response.statusCode).to.eq(200);
                expect(request.body).to.deep.equal({ active: false });
            });
        });

        it("Activate POSTs /api/events/{id}/status with active=true", () => {
            // First deactivate the test event so the action menu shows "Activate"
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/events/${testEventId}/status`,
                { active: false },
                200,
            );
            freshAdminLogin();

            cy.intercept("POST", "**/api/events/*/status").as("status");
            cy.visit("event/dashboard");

            // A deactivated event is rendered as a "past" row, so its month
            // group has to be expanded before the menu can be used.
            openEventActionMenu(testEventId);

            cy.get(`.event-action-menu-placeholder[data-event-id="${testEventId}"]`)
                .find(".dropdown-menu.show")
                .contains("Activate")
                .click();

            cy.wait("@status").then(({ request, response }) => {
                expect(response.statusCode).to.eq(200);
                expect(request.body).to.deep.equal({ active: true });
            });
        });
    });

    after(() => {
        // Delete everything this spec created so the next run starts from the
        // same state — stale rows are exactly what made these tests flaky
        // (issue #9795).
        const ids = createdEventIds.splice(0);
        ids.forEach((id) => {
            cy.makePrivateAdminAPICall("DELETE", `/api/events/${id}`, null, [200, 404]);
        });

        // Nothing of ours may be left behind.
        cy.makePrivateAdminAPICall("GET", "/api/events", null, [200, 404]).then((resp) => {
            const remaining = (resp.body.Events || []).filter((evt) => ids.includes(evt.Id));
            expect(remaining, "events created by this spec were all deleted").to.have.length(0);
        });
    });
});
