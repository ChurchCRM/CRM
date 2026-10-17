/// <reference types="cypress" />

/**
 * Admin → Member Portal → Calendars (MP5, #9866).
 *
 * Design: .agents/skills/churchcrm/member-portal-design.md §5.3.
 *   - the tab MP3 left hidden is now on the page
 *   - every church, ministry and system calendar is listed, each named by its
 *     kind, each with a "Show in Member Portal" switch
 *   - switching a calendar on, and off again, survives a reload
 *   - an entry naming no calendar is refused rather than stored
 *
 * Seed facts this relies on: calendar 1 is "Public Calendar" (the calendar a
 * new install shares) and the system calendars always include "Birthdays".
 *
 * The ministry calendar is built here rather than seeded. #9869 gave
 * `calendars.ministry_id` a real foreign key to `volunteer_ministry_vmin`, so a
 * seeded calendar naming a ministry the seed does not contain is no longer a
 * harmless fiction. The spec creates a ministry through the API, which gives it
 * its calendar, and deletes it the same way.
 *
 * NOTE: every x-api-key request below replaces the browser session cookie with
 * an API-token session, so a UI step that follows one has to re-establish the
 * browser session first (cy.setupAdminSession()).
 */
const CHURCH_CALENDAR_ID = 1;
const CHURCH_CALENDAR_NAME = "Public Calendar";
const MINISTRY_CALENDAR_NAME = "PORTALCAL Youth Ministry";
const VOLUNTEER_VERSION = "sVolunteerVersion";
let originalVolunteerVersion;

const adminKey = () => Cypress.testEnv("admin.api.key");

const setVisibleCalendars = (visible) =>
    cy.request({
        method: "POST",
        url: "/admin/api/member-portal/calendars",
        headers: { "content-type": "application/json", "x-api-key": adminKey() },
        body: { visible },
        failOnStatusCode: false,
    });

const openCalendarsTab = () => {
    cy.visit("/admin/member-portal");
    cy.get("#portal-calendars-tab").should("be.visible").click();
    cy.get("#portal-calendars").should("be.visible");
};

/** The row for one calendar, found by the name in its first cell. */
const calendarRow = (name) => cy.get("#portalCalendarsTable tbody tr").contains("td", name).parent();

// The ministries API answers only while Volunteer v2 is on; the page is tested as the seed has it.
const withVolunteerV2 = (work) => {
    cy.getSystemConfig(VOLUNTEER_VERSION).then((value) => {
        originalVolunteerVersion = value;
    });
    cy.makePrivateAdminAPICall("POST", `/admin/api/system/config/${VOLUNTEER_VERSION}`, { value: "v2" }, 200);
    work();
    cy.then(() => cy.restoreSystemConfig(VOLUNTEER_VERSION, originalVolunteerVersion));
};

/** Deleting the ministry deletes its calendar too. */
const removeMinistryCalendar = () =>
    cy.makePrivateAdminAPICall("GET", "/api/ministries/ministries", null, 200).then((resp) => {
        for (const ministry of resp.body.ministries.filter((m) => m.name === MINISTRY_CALENDAR_NAME)) {
            cy.makePrivateAdminAPICall("POST", `/api/ministries/ministries/${ministry.id}`, { active: false }, 200);
            cy.makePrivateAdminAPICall("DELETE", `/api/ministries/ministries/${ministry.id}`, null, 200);
        }
    });

before(() => {
    cy.rememberTestEnv(["admin.api.key"]);
});

describe("Admin → Member Portal → Calendars", () => {
    before(() => {
        withVolunteerV2(() => {
            removeMinistryCalendar();
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/ministries/ministries",
                { name: MINISTRY_CALENDAR_NAME, description: "member portal calendars fixture" },
                201,
            );
        });
    });

    after(() => {
        // Leave the installation as the seed had it: nothing shared.
        setVisibleCalendars([]);
        withVolunteerV2(removeMinistryCalendar);
    });

    beforeEach(() => {
        setVisibleCalendars([]);
        cy.setupAdminSession();
    });

    it("The Calendars tab is on the page and carries the lead text", () => {
        openCalendarsTab();
        cy.get("#portal-calendars-tab-item").should("not.have.class", "d-none");
        cy.get("#portalCalendarsLead").should(
            "contain",
            "Members see only the calendars switched on here."
        );
        cy.get("#portalCalendarsLead").should(
            "contain",
            "Birthdays and anniversaries show first names and last initials only."
        );
    });

    it("Church, ministry and system calendars are each listed under their own kind", () => {
        openCalendarsTab();

        calendarRow(CHURCH_CALENDAR_NAME).should("contain", "Church calendar");
        calendarRow(MINISTRY_CALENDAR_NAME).should("contain", "Ministry calendar");
        calendarRow("Birthdays").should("contain", "System calendar");
        calendarRow("Anniversaries").should("contain", "System calendar");
    });

    it("The table fits a phone without clipping its last column header (#10422)", () => {
        cy.viewport(390, 844);
        openCalendarsTab();
        cy.get("#portalCalendarsTable").should(($table) => {
            const scroller = $table.parent()[0];
            expect($table.outerWidth(), "table width").to.be.at.most(scroller.clientWidth);
        });
    });

    it("Switching the church calendar on persists across a reload", () => {
        openCalendarsTab();

        calendarRow(CHURCH_CALENDAR_NAME).find(".portal-calendar-switch").should("not.be.checked").check();
        cy.get("#portalCalendarsSaveButton").click();
        cy.get("#portalCalendarsStatus").should("contain", "Saved.");

        openCalendarsTab();
        calendarRow(CHURCH_CALENDAR_NAME).find(".portal-calendar-switch").should("be.checked");
    });

    it("Switching it off again persists across a reload", () => {
        setVisibleCalendars([{ type: "calendar", id: CHURCH_CALENDAR_ID }]);
        cy.setupAdminSession();
        openCalendarsTab();

        calendarRow(CHURCH_CALENDAR_NAME).find(".portal-calendar-switch").should("be.checked").uncheck();
        cy.get("#portalCalendarsSaveButton").click();
        cy.get("#portalCalendarsStatus").should("contain", "No calendar is shared with members.");

        openCalendarsTab();
        calendarRow(CHURCH_CALENDAR_NAME).find(".portal-calendar-switch").should("not.be.checked");
    });

    it("A system calendar can be switched on too", () => {
        openCalendarsTab();

        calendarRow("Birthdays").find(".portal-calendar-switch").check();
        cy.get("#portalCalendarsSaveButton").click();
        cy.get("#portalCalendarsStatus").should("contain", "Saved.");

        openCalendarsTab();
        calendarRow("Birthdays").find(".portal-calendar-switch").should("be.checked");
    });

    it("An entry that names no calendar is refused, and nothing is stored", () => {
        setVisibleCalendars([{ type: "calendar", id: 999999 }]).then((response) => {
            expect(response.status).to.eq(400);
        });

        cy.request({
            method: "GET",
            url: "/admin/api/member-portal/calendars",
            headers: { "x-api-key": adminKey() },
        }).then((response) => {
            const chosen = response.body.calendars.filter((calendar) => calendar.visible);
            expect(chosen, "nothing was stored").to.have.length(0);
        });
    });

    it("An entry of an unknown kind is refused", () => {
        setVisibleCalendars([{ type: "ministry", id: 1 }]).then((response) => {
            expect(response.status).to.eq(400);
        });
    });
});
