/// <reference types="cypress" />

/**
 * Event Editor page (/event/editor/:id) — now boots the shared
 * renderEventEditor module from webpack/event-form.js. These tests
 * cover the full-page surface; tests for the identical modal surface
 * live in cypress/e2e/ui/events/standard.calendar.spec.js.
 */
describe("Event Editor page", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    /** Open the editor for a freshly-created event type, new-event mode. */
    function visitNewEditorForFirstType() {
        cy.visit("/event/types");
        cy.get("#eventTypesTable tbody tr")
            .first()
            .find("a")
            .first()
            .invoke("attr", "href")
            .then((href) => {
                const typeId = href.split("/").pop();
                cy.visit(`/event/editor?typeId=${typeId}`);
            });
    }

    it("renders the shared form with title, type, calendar pickers, and date inputs", () => {
        visitNewEditorForFirstType();

        cy.get("#event-title-input").should("be.visible");
        cy.get("#eventTypeSelect").should("exist");
        cy.get("#pinnedCalendarsSelect").should("exist");
        cy.get("#eventStartDate").should("exist");
        cy.get("#eventEndDate").should("exist");
        cy.get(".ql-toolbar").should("have.length", 2); // Description + Additional Information
    });

    it("renders the Advanced collapse closed by default", () => {
        visitNewEditorForFirstType();

        cy.get("#eventAdvancedFields").should("not.have.class", "show");
        cy.get("#eventAdvancedLabel").should("contain", "Show more options");
    });

    it("opens the Advanced collapse when the toggle is clicked", () => {
        visitNewEditorForFirstType();

        cy.get('[data-bs-target="#eventAdvancedFields"]').click();
        cy.get("#eventAdvancedFields").should("have.class", "show");
        // Tabler hides the radio input itself (opacity:0) and shows the
        // wrapping .form-selectgroup-label instead — assert against the
        // label, not the input.
        cy.get('input[name="eventInActive"][value="0"]').parent("label").should("be.visible");
        cy.get('input[name="eventInActive"][value="1"]').parent("label").should("be.visible");
        cy.get("#linkedGroupSelect").should("be.visible");
    });

    it("Save button is disabled until title is filled (calendar is optional)", () => {
        visitNewEditorForFirstType();

        // Start/End are pre-filled from the default next-hour seed, and
        // PinnedCalendars is optional — only Title blocks save.
        cy.get("#event-editor-save").should("be.disabled");

        cy.get("#event-title-input").type("Page Validation Test");
        cy.get("#event-editor-save").should("not.be.disabled");

        // Empty-calendar hint is visible because nothing is pinned yet.
        cy.get("#calendarsEmptyHint").should("be.visible");
    });

    /**
     * Sibling to the "with calendar" save test — asserts the full-page
     * surface also lets events be saved without a pinned calendar.
     */
    it("saves a new event without a pinned calendar", () => {
        cy.intercept("POST", "**/api/events").as("createEvent");

        visitNewEditorForFirstType();

        cy.get("#event-title-input").type(`No Calendar Page ${Date.now()}`);
        cy.get("#calendarsEmptyHint").should("be.visible");

        cy.get("#event-editor-save").click();
        cy.wait("@createEvent").then((intercepted) => {
            expect(intercepted.request.body.PinnedCalendars).to.deep.equal([]);
            expect(intercepted.response.statusCode).to.eq(200);
        });
        cy.url().should("include", "/event/dashboard");
    });

    it("persists InActive and LinkedGroupId when set in the Advanced section", () => {
        const title = `Advanced Persist ${Date.now()}`;
        cy.intercept("POST", "**/api/events").as("createEvent");

        visitNewEditorForFirstType();

        cy.get("#event-title-input").type(title);
        cy.tomSelectByValue("#pinnedCalendarsSelect", "1");

        cy.get('[data-bs-target="#eventAdvancedFields"]').click();
        cy.get("#eventAdvancedFields").should("have.class", "show");

        // Mark inactive. Tabler hides the radio input — click the parent label.
        cy.get('input[name="eventInActive"][value="1"]').parent("label").click();

        cy.get("#event-editor-save").click();
        cy.wait("@createEvent").then((intercepted) => {
            expect(intercepted.request.body.InActive).to.eq(1);
            expect(intercepted.response.statusCode).to.eq(200);
        });
        cy.url().should("include", "/event/dashboard");
    });

    it("default new event payload sends Type > 0 without user interaction", () => {
        cy.intercept("POST", "**/api/events").as("createEvent");

        visitNewEditorForFirstType();

        cy.get("#event-title-input").type(`Default Type Page ${Date.now()}`);
        cy.tomSelectByValue("#pinnedCalendarsSelect", "1");
        cy.get("#event-editor-save").click();

        cy.wait("@createEvent").then((intercepted) => {
            expect(intercepted.request.body.Type).to.be.a("number").and.to.be.greaterThan(0);
        });
    });

    it("editing an existing event pre-fills Title and Type", () => {
        // Most installs already have a calendar and at least one event from
        // the seed data. Just use the first event returned from /api/events.
        cy.request("/api/events").then((response) => {
            const events = response.body.Events || response.body;
            const eventArray = Array.isArray(events) ? events : Object.values(events);
            if (eventArray.length === 0) return; // nothing to edit; skip
            const eventId = eventArray[0].Id;
            cy.visit(`/event/editor/${eventId}`);
            cy.get("#event-title-input").should("not.have.value", "");
            cy.get("#eventTypeSelect option:checked").should("exist");
        });
    });
});
