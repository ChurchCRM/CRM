/// <reference types="cypress" />

describe("Administrator and staff customize the calendar", () => {
    const keys = ["sCalendarDefaultView", "sCalendarMobileDefaultView", "sCalendarTheme", "sCalendarPrintView", "sCalendarPrintStyle"];
    const original = {};

    before(() => {
        keys.forEach((key) => cy.getSystemConfig(key).then((value) => { original[key] = value; }));
    });

    after(() => {
        cy.window().then(({ CRM }) => cy.request("POST", `/api/user/${CRM.userId}/setting/ui.calendar.view`, { value: "" }));
        keys.forEach((key) => cy.restoreSystemConfig(key, original[key]));
    });

    beforeEach(() => cy.setupAdminSession());

    it("saves system defaults, lets a user override them, and prints a chosen layout", () => {
        cy.setSystemConfig("sCalendarDefaultView", "timeGridWeek");
        cy.setSystemConfig("sCalendarMobileDefaultView", "listMonth");
        cy.setSystemConfig("sCalendarTheme", "pulse");
        cy.setSystemConfig("sCalendarPrintView", "listMonth");
        cy.setSystemConfig("sCalendarPrintStyle", "compact");
        cy.visit("/admin/settings#calendarSettings");
        cy.get("#calendarSettings [name='sCalendarDefaultView']").should("have.value", "timeGridWeek");
        cy.get("#calendarSettings [name='sCalendarTheme']").should("have.value", "pulse");

        cy.visit("/event/calendars");
        cy.window().its("CRM.fullcalendar.view.type").should("eq", "timeGridWeek");
        cy.window().then((win) => cy.stub(win, "print").as("printCalendar"));
        cy.get("#calendar").contains("button", "Print").click();
        cy.window().its("CRM.fullcalendar.view.type").should("eq", "listMonth");
        cy.get("body").should("have.class", "crm-calendar-print-compact");
        cy.get("@printCalendar").should("have.been.calledOnce");
        cy.window().then((win) => win.dispatchEvent(new Event("afterprint")));
        cy.window().its("CRM.fullcalendar.view.type").should("eq", "timeGridWeek");

        cy.visit("/account");
        cy.get("[href='#tab-calendar']").click();
        cy.intercept("POST", "**/setting/ui.calendar.view").as("saveCalendarPreference");
        cy.get("#setting-ui-calendar-view").select("timeGridDay");
        cy.wait("@saveCalendarPreference").its("response.statusCode").should("eq", 200);
        cy.visit("/event/calendars");
        cy.window().its("CRM.fullcalendar.view.type").should("eq", "timeGridDay");

        cy.visit("/account");
        cy.get("[href='#tab-calendar']").click();
        cy.get("#setting-ui-calendar-view").select("");
        cy.wait("@saveCalendarPreference").its("response.statusCode").should("eq", 200);
        [
            { name: "desktop", width: 1280, height: 900, view: "timeGridWeek" },
            { name: "tablet", width: 820, height: 1024, view: "timeGridWeek" },
            { name: "phone", width: 390, height: 844, view: "listMonth" },
        ].forEach(({ name, width, height, view }) => {
            ["light", "dark"].forEach((theme) => {
                cy.viewport(width, height);
                cy.visit("/event/calendars");
                cy.get("html").invoke("attr", "data-bs-theme", theme);
                cy.window().its("CRM.fullcalendar.view.type").should("eq", view);
                cy.get("#calendar").should("be.visible").contains("button", "Print").should("be.visible");
                cy.log(`${name} ${theme} calendar passed`);
            });
        });
    });
});
