/// <reference types="cypress" />

/**
 * Regression tests: the event action menu must encode the event title for
 * ATTRIBUTE context when it writes it into `data-event_title="…"`.
 *
 * `window.CRM.escapeHtml()` encodes `&`, `<` and `>` but leaves `"` and `'`
 * untouched, so it is only correct for HTML *text* context. Attribute values
 * need `window.CRM.escapeAttribute()`, which additionally encodes both quote
 * characters. `window.CRM.renderPersonActionMenu()` already uses
 * `escapeAttribute()` for its `data-person_name` attribute; this spec pins the
 * same behaviour for `window.CRM.renderEventActionMenu()` so an ordinary
 * apostrophe-and-quote title such as `Choir "Spring" Night & Co's` round-trips
 * unchanged and does not add stray attributes to the Delete button.
 *
 * Scenarios covered:
 *   1. Renderer  - window.CRM.renderEventActionMenu() output parsed with DOMParser
 *   2. Events Dashboard (/event/dashboard) - PHP placeholder hydrated by the renderer
 *   3. V2 Dashboard (/v2/dashboard) - "Today's Events" DataTable column
 *
 * Fixtures are created and removed through the API with the cleanup-before
 * pattern (see .agents/skills/churchcrm/cypress-testing.md): `before` deletes
 * any leftovers from an earlier (possibly crashed) run, then creates the
 * fixture; `after` removes it again for tidiness.
 */

// Plain-English title exercising all three characters that matter here:
// a double quote, a single quote and an ampersand.
const EVENT_TITLE = 'Choir "Spring" Night & Co\'s';

// The Delete item must carry exactly these attributes — no more.
const EXPECTED_DELETE_ATTRS = ["type", "class", "data-event_id", "data-event_title"];

/**
 * Every event currently stored under the fixture title.
 * GET /api/events answers 404 when the events table is empty.
 *
 * @returns {Cypress.Chainable<number[]>} matching event ids
 */
function findFixtureEventIds() {
    return cy.makePrivateAdminAPICall("GET", "/api/events", null, [200, 404]).then((resp) => {
        if (resp.status !== 200) {
            return [];
        }
        const payload = resp.body.Events || resp.body;
        const events = Array.isArray(payload) ? payload : Object.values(payload);
        return events.filter((e) => e && e.Title === EVENT_TITLE).map((e) => e.Id);
    });
}

/**
 * Delete every event stored under the fixture title.
 * Accepts 404 so a fresh database is fine too.
 */
function deleteFixtureEvents() {
    findFixtureEventIds().then((ids) => {
        ids.forEach((id) => {
            cy.makePrivateAdminAPICall("DELETE", `/api/events/${id}`, {}, [200, 404]);
        });
    });
}

/**
 * Create the fixture event and yield its id.
 *
 * Start/End are sent as local (not UTC) datetime strings because the API
 * stores them verbatim. Midday-to-end-of-day keeps the row in the dashboard's
 * "current events" tbody rather than the collapsible "past events" one.
 *
 * @returns {Cypress.Chainable<number>} the new event id
 */
function createFixtureEvent() {
    const now = new Date();
    const day = [
        now.getFullYear(),
        String(now.getMonth() + 1).padStart(2, "0"),
        String(now.getDate()).padStart(2, "0"),
    ].join("-");

    return cy
        .makePrivateAdminAPICall(
            "POST",
            "/api/events",
            {
                Title: EVENT_TITLE,
                Type: 1,
                PinnedCalendars: [1],
                Start: `${day}T12:00:00`,
                End: `${day}T23:59:00`,
            },
            200,
        )
        .then(() => findFixtureEventIds())
        .then((ids) => {
            expect(ids, "fixture event should exist after creation").to.have.length(1);
            return ids[0];
        });
}

/**
 * Assert a rendered Delete button round-trips the literal title and carries
 * exactly the expected attribute set.
 *
 * @param {HTMLElement} btn     - the `.delete-event` button
 * @param {string}      context - label for assertion messages
 */
function assertDeleteButtonAttributes(btn, context) {
    expect(btn.getAttribute("data-event_title"), `[${context}] data-event_title`).to.equal(EVENT_TITLE);

    const names = Array.from(btn.attributes)
        .map((a) => a.name)
        .sort();
    expect(names, `[${context}] attribute set`).to.deep.equal([...EXPECTED_DELETE_ATTRS].sort());
}

describe("Event action menu — title in data-event_title", () => {
    let eventId;

    before(() => {
        // 1. Remove anything an earlier (possibly failed) run left behind.
        deleteFixtureEvents();
        // 2. Create the fixture this spec needs.
        createFixtureEvent().then((id) => {
            eventId = id;
        });
    });

    after(() => {
        deleteFixtureEvents();
    });

    // Session setup runs last in each test's setup chain — cy.request() resets
    // the PHP session cookie, so it must come after all API calls.
    beforeEach(() => cy.setupAdminSession());

    // ── Scenario 1: the renderer itself ──────────────────────────────────────
    it("renderEventActionMenu() writes the literal title into data-event_title", () => {
        cy.visit("/v2/dashboard");
        cy.window().should("have.nested.property", "CRM.renderEventActionMenu");

        cy.window().then((win) => {
            const html = win.CRM.renderEventActionMenu(1, EVENT_TITLE);
            const doc = new DOMParser().parseFromString(html, "text/html");
            // querySelectorAll (not querySelector + .to.exist): Cypress overrides
            // the `exist` assertion with jQuery semantics, which a raw Element
            // does not satisfy.
            const buttons = doc.querySelectorAll(".delete-event");

            expect(buttons.length, "renderer emits exactly one .delete-event button").to.equal(1);
            assertDeleteButtonAttributes(buttons[0], "renderEventActionMenu");
            expect(buttons[0].getAttribute("data-event_id"), "[renderEventActionMenu] data-event_id").to.equal("1");
        });
    });

    // ── Scenario 2: Events Dashboard (PHP placeholder + JS hydration) ────────
    it("[/event/dashboard] the row's Delete item round-trips the title", () => {
        cy.visit("/event/dashboard");

        // The placeholder is hydrated by list-events.php once locales are ready.
        // Assert on existence, not visibility: past-event tbodies start collapsed.
        cy.get(`.event-action-menu-placeholder[data-event-id="${eventId}"] .delete-event`, { timeout: 15000 })
            .should("exist")
            .then(($btn) => {
                assertDeleteButtonAttributes($btn[0], "event dashboard");
                expect($btn.attr("data-event_title")).to.equal(EVENT_TITLE);
                expect($btn.attr("data-event_id")).to.equal(String(eventId));
            });
    });

    // ── Scenario 3: V2 Dashboard "Today's Events" DataTable ──────────────────
    // GET /events/today filters on the server's calendar day. When the server
    // clock has rolled past midnight relative to the browser's, the fixture is
    // not "today" for the widget; skip rather than assert on an absent row.
    it("[/v2/dashboard] the Today's Events Delete item round-trips the title", function () {
        cy.makePrivateAdminAPICall("GET", "/api/events/today", null, [200, 404])
            .then((resp) => {
                // GET /events/today answers { "events": [ { id, title, … } ] }.
                const events = (resp.status === 200 && resp.body.events) || [];
                return events.some((e) => e && Number(e.id) === Number(eventId));
            })
            .then((isToday) => {
                if (!isToday) {
                    cy.log("fixture event is not in the server's today window — skipping");
                    this.skip();
                    return;
                }

                // cy.request() above reset the PHP session cookie.
                cy.setupAdminSession();
                cy.visit("/v2/dashboard");

                cy.get(`#todayEventsDashboardItem .delete-event[data-event_id="${eventId}"]`, { timeout: 15000 })
                    .should("exist")
                    .then(($btn) => {
                        assertDeleteButtonAttributes($btn[0], "v2 dashboard");
                        expect($btn.attr("data-event_title")).to.equal(EVENT_TITLE);
                    });
            });
    });
});
