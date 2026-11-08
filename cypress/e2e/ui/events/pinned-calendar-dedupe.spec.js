/// <reference types="cypress" />

/**
 * An event pinned to two visible calendars is one chip, with a mark for each calendar.
 * https://github.com/ChurchCRM/CRM/issues/4816
 */
const PREFIX = "PINDEDUPE";
const DAY = "2031-04-16";
const TITLE = `${PREFIX} Combined Service`;

const visibleCopies = (win) =>
    win.CRM.fullcalendar.getEvents().filter((event) => event.title === TITLE && event.display !== "none");

describe("Events pinned to more than one calendar", () => {
    const calendarIds = [];
    let eventId = 0;

    before(() => {
        cy.rememberTestEnv(["admin.api.key"]);
        cy.makePrivateAdminAPICall("POST", "/api/calendars", {
            Name: `${PREFIX} Worship`,
            ForegroundColor: "#FFFFFF",
            BackgroundColor: "#C0392B",
        }).then((resp) => {
            calendarIds.push(resp.body.Id);
        });
        cy.makePrivateAdminAPICall("POST", "/api/calendars", {
            Name: `${PREFIX} Youth`,
            ForegroundColor: "#FFFFFF",
            BackgroundColor: "#1A7F37",
        }).then((resp) => {
            calendarIds.push(resp.body.Id);
        });
        cy.then(() => {
            cy.makePrivateAdminAPICall("POST", "/api/events", {
                Title: TITLE,
                Type: 1,
                Start: `${DAY}T10:00:00`,
                End: `${DAY}T11:00:00`,
                PinnedCalendars: calendarIds,
            }).then((resp) => {
                eventId = resp.body.eventId;
            });
        });
    });

    after(() => {
        cy.then(() => {
            if (eventId) {
                cy.cleanupEvents([eventId]);
            }
            calendarIds.forEach((id) => {
                cy.makePrivateAdminAPICall("DELETE", `/api/calendars/${id}`, null, [200, 404]);
            });
        });
    });

    beforeEach(() => cy.setupStandardSession());

    const showDay = () => {
        cy.window({ timeout: 20000 }).should((win) => {
            expect(win.CRM.fullcalendar, "calendar").to.exist;
        });
        cy.window().then((win) => {
            win.CRM.fullcalendar.changeView("dayGridMonth", DAY);
        });
    };

    it("renders the event once and names both calendars", () => {
        cy.visit("event/calendars");
        showDay();

        cy.window({ timeout: 20000 }).should((win) => {
            const shown = visibleCopies(win);
            expect(shown, "one chip").to.have.length(1);
            const names = (shown[0].extendedProps.pinnedCalendars || []).map((pin) => pin.name).sort();
            expect(names).to.deep.eq([`${PREFIX} Worship`, `${PREFIX} Youth`]);
        });

        cy.get("#calendar .crm-cal-pin-mark").should("have.length", 2);
    });

    it("keeps the event when one of its calendars is hidden", () => {
        cy.visit("event/calendars");
        showDay();
        cy.window({ timeout: 20000 }).should((win) => {
            expect(visibleCopies(win)).to.have.length(1);
        });

        cy.get(`#display-user-${calendarIds[0]}`).click({ force: true });

        cy.window({ timeout: 20000 }).should((win) => {
            const shown = visibleCopies(win);
            expect(shown, "still one chip").to.have.length(1);
            const names = (shown[0].extendedProps.pinnedCalendars || []).map((pin) => pin.name);
            expect(names).to.deep.eq([`${PREFIX} Youth`]);
        });
        cy.get("#calendar .crm-cal-pin-mark").should("have.length", 0);
    });
});
