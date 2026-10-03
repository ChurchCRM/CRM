/// <reference types="cypress" />

/**
 * Member Portal — the calendar legend switches calendars on and off (Volunteer v2 D27).
 * Design: member-portal-design.md §5.3, §3.6; volunteer-v2-design.md §0.8 D27.
 *
 * Seed persona: Lena Black (user 100), a self-service member confined to the portal.
 * API setup runs before the member logs in: every x-api-key request replaces the session.
 */
const MEMBER_USER = "lena.black.editself.notes@example.com";
const MEMBER_PASSWORD = "changeme";
const PUBLIC_CALENDAR = 1;
const STORAGE_KEY = "churchcrm.portal.calendar.hidden";
const PREFIX = "PORTALLEG";

const DAY = `${new Date().getFullYear() + 1}-06-14`;
const WORSHIP_TITLE = `${PREFIX} Worship Hour`;
const CLASS_TITLE = `${PREFIX} Faith City`;

let classesCalendar = 0;

const adminRequest = (method, url, body) =>
    cy.request({
        method,
        url,
        headers: { "content-type": "application/json", "x-api-key": Cypress.env("admin.api.key") },
        body,
    });

const dbOk = (sql, params = []) =>
    cy.dbQuery(sql, params).then((result) => {
        if (result.error !== null) {
            throw new Error(`Unexpected SQL failure.\n  SQL: ${sql}\n  ${result.error.code}: ${result.error.message}`);
        }
        return result.rows;
    });

const loginAsMember = () => {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(MEMBER_USER);
    cy.get("input[name=Password]").type(`${MEMBER_PASSWORD}{enter}`);
    cy.url({ timeout: 10000 }).should("include", "/portal");
};

const gotoDay = () => {
    cy.window({ timeout: 15000 }).should((win) => {
        expect(win.CRM.fullcalendar, "the portal calendar is rendered").to.exist;
    });
    cy.window().then((win) => win.CRM.fullcalendar.changeView("listDay", DAY));
};

const classesToggle = () => cy.get(`button[data-calendar-key="calendar:${classesCalendar}"]`);

const cleanup = () => {
    dbOk(
        `DELETE ce FROM calendar_events ce
           JOIN events_event e ON e.event_id = ce.event_id
          WHERE e.event_title LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(`DELETE FROM events_event WHERE event_title LIKE ?`, [`${PREFIX}%`]);
    dbOk(`DELETE FROM calendars WHERE name LIKE ?`, [`${PREFIX}%`]);
};

describe("Member Portal calendar legend toggles", () => {
    before(() => {
        cleanup();
        adminRequest("POST", "/api/calendars", {
            Name: `${PREFIX} Bible Classes`,
            ForegroundColor: "#FFFFFF",
            BackgroundColor: "#6A1B9A",
        }).then((resp) => {
            classesCalendar = resp.body.Id;
            adminRequest("POST", "/admin/api/member-portal/calendars", {
                visible: [
                    { type: "calendar", id: PUBLIC_CALENDAR },
                    { type: "calendar", id: classesCalendar },
                ],
            });
            adminRequest("POST", "/api/events", {
                Title: WORSHIP_TITLE,
                Type: 1,
                PinnedCalendars: [PUBLIC_CALENDAR],
                Start: `${DAY}T10:30:00`,
                End: `${DAY}T11:45:00`,
            });
            adminRequest("POST", "/api/events", {
                Title: CLASS_TITLE,
                Type: 1,
                PinnedCalendars: [classesCalendar],
                Start: `${DAY}T09:30:00`,
                End: `${DAY}T10:15:00`,
            });
        });
    });

    after(() => {
        adminRequest("POST", "/admin/api/member-portal/calendars", { visible: [] });
        cleanup();
    });

    it("hides and shows one calendar's events and remembers the choice across a reload", () => {
        loginAsMember();
        cy.visit("/portal/calendar");
        gotoDay();
        cy.contains("#portal-calendar", CLASS_TITLE, { timeout: 15000 }).should("be.visible");

        classesToggle().should("have.attr", "type", "button").and("have.attr", "aria-pressed", "true").click();
        classesToggle().should("have.attr", "aria-pressed", "false");
        cy.get("#portal-calendar").should("not.contain", CLASS_TITLE);
        cy.contains("#portal-calendar", WORSHIP_TITLE).should("be.visible");
        cy.window().then((win) => {
            expect(JSON.parse(win.localStorage.getItem(STORAGE_KEY))).to.include(`calendar:${classesCalendar}`);
        });

        cy.reload();
        gotoDay();
        cy.contains("#portal-calendar", WORSHIP_TITLE, { timeout: 15000 }).should("be.visible");
        classesToggle().should("have.attr", "aria-pressed", "false");
        cy.get("#portal-calendar").should("not.contain", CLASS_TITLE);

        classesToggle().click();
        classesToggle().should("have.attr", "aria-pressed", "true");
        cy.contains("#portal-calendar", CLASS_TITLE).should("be.visible");

        cy.reload();
        gotoDay();
        cy.contains("#portal-calendar", CLASS_TITLE, { timeout: 15000 }).should("be.visible");
        classesToggle().should("have.attr", "aria-pressed", "true");
    });

    it("keeps the calendar grid when every calendar is switched off", () => {
        loginAsMember();
        cy.visit("/portal/calendar");
        gotoDay();
        cy.contains("#portal-calendar", WORSHIP_TITLE, { timeout: 15000 }).should("be.visible");

        cy.get("button[data-calendar-key]").each(($toggle) => {
            cy.wrap($toggle).click().should("have.attr", "aria-pressed", "false");
        });
        cy.get("#portal-calendar").should("be.visible").and("not.contain", WORSHIP_TITLE).and("not.contain", CLASS_TITLE);
        cy.get("#portal-calendar .portal-fc-toolbar").should("be.visible");
    });

    it("works with browser storage unavailable", () => {
        loginAsMember();
        cy.visit("/portal/calendar", {
            onBeforeLoad(win) {
                cy.stub(win.Storage.prototype, "getItem").throws(new Error("storage disabled"));
                cy.stub(win.Storage.prototype, "setItem").throws(new Error("storage disabled"));
            },
        });
        gotoDay();
        cy.contains("#portal-calendar", CLASS_TITLE, { timeout: 15000 }).should("be.visible");
        classesToggle().click().should("have.attr", "aria-pressed", "false");
        cy.get("#portal-calendar").should("not.contain", CLASS_TITLE);
    });

    it("shows every event when a theme's legend has no toggles, whatever was stored", () => {
        loginAsMember();
        cy.visit("/portal/calendar", {
            onBeforeLoad(win) {
                win.localStorage.setItem(STORAGE_KEY, JSON.stringify([`calendar:${classesCalendar}`]));
                win.document.addEventListener("DOMContentLoaded", () => {
                    for (const toggle of win.document.querySelectorAll("button[data-calendar-key]")) {
                        toggle.removeAttribute("data-calendar-key");
                    }
                });
            },
        });
        gotoDay();
        cy.contains("#portal-calendar", CLASS_TITLE, { timeout: 15000 }).should("be.visible");
        cy.contains("#portal-calendar", WORSHIP_TITLE).should("be.visible");
    });
});
