/// <reference types="cypress" />

describe("Dashboard tabs and main dashboard", () => {
    const resetLanding = () =>
        cy.window().then((win) =>
            cy.request({ method: "POST", url: `/api/user/${win.CRM.userId}/setting/ui.landing`, body: { value: "people" } }),
        );

    it("shows an administrator every dashboard", () => {
        cy.setupAdminSession();
        cy.visit("people/dashboard");
        ["People", "Groups", "Sunday School", "Events", "Finance", "Deposits"].forEach((tab) => cy.get("#dashboardSwitch").should("contain", tab));
        cy.get("#dashboardSwitch .nav-link.active").invoke("text").then((text) => expect(text.trim()).to.eq("People"));
    });

    it("shows finance-only staff People and Finance, and no group dashboards", () => {
        cy.setupFinanceOnlySession();
        cy.visit("people/dashboard");
        cy.get("#dashboardSwitch").should("contain", "Finance").and("contain", "Deposits").and("not.contain", "Groups").and("not.contain", "Sunday School");
    });

    it("opens on the People dashboard when no main dashboard is set", () => {
        cy.setupAdminSession();
        cy.visit("people/dashboard");
        resetLanding();
        cy.visit("/");
        cy.location("pathname").should("match", /\/people\/dashboard$/);
        cy.get("#setLandingPage").should("be.disabled").and("contain", "Your main dashboard");
    });

    it("lets a user choose another dashboard as the one they open on", () => {
        cy.setupFinanceOnlySession();
        cy.visit("finance/");
        cy.get("#setLandingPage").should("not.be.disabled").click();
        cy.get("#setLandingPage").should("be.disabled").and("contain", "Your main dashboard");

        cy.visit("/");
        cy.location("pathname").should("match", /\/finance\/?$/);
        cy.contains(".sidebar-menu, #sidebar-menu, .navbar-nav a", "Dashboard").should("have.attr", "href").and("match", /\/finance\/?$/);

        cy.visit("people/dashboard");
        cy.get("#setLandingPage").click();
        cy.get("#setLandingPage").should("be.disabled");
        cy.visit("/");
        cy.location("pathname").should("match", /\/people\/dashboard$/);
    });

    it("lets finance staff open Deposits from the tabs and make it their main dashboard", () => {
        cy.setupFinanceOnlySession();
        cy.visit("finance/deposit/search");
        cy.get("#dashboardSwitch .nav-link.active").invoke("text").then((text) => expect(text.trim()).to.eq("Deposits"));
        cy.get("#setLandingPage").click();
        cy.get("#setLandingPage").should("be.disabled");
        cy.visit("/");
        cy.location("pathname").should("match", /\/finance\/deposit\/search$/);
        cy.visit("people/dashboard");
        cy.get("#setLandingPage").click();
        cy.get("#setLandingPage").should("be.disabled");
    });

    it("sends the Dashboard link to the new main dashboard on the very next click", () => {
        cy.setupFinanceOnlySession();
        cy.visit("finance/");
        cy.get("#setLandingPage").click();
        cy.get("#setLandingPage").should("be.disabled");
        cy.contains("#sidebar .nav-link", /^\s*Dashboard\s*$/).click();
        cy.location("pathname").should("match", /\/finance\/?$/);
        cy.visit("people/dashboard");
        cy.get("#setLandingPage").click();
        cy.get("#setLandingPage").should("be.disabled");
        cy.contains("#sidebar .nav-link", /^\s*Dashboard\s*$/).click();
        cy.location("pathname").should("match", /\/people\/dashboard$/);
    });

    it("lets a user set their dashboard settings from /account/ and applies them", () => {
        const choose = (id, setting, value) => {
            cy.intercept("POST", `**/setting/${setting}`).as(setting);
            cy.get(id).select(value);
            cy.wait(`@${setting}`);
            cy.visit("account/#tab-appearance");
            cy.get(id).should("have.value", value);
        };
        cy.setupFinanceOnlySession();
        cy.visit("account/#tab-appearance");
        cy.get("#settingLanding option").should("have.length.gte", 3);
        choose("#settingLanding", "ui.landing", "finance");
        choose("#settingFinanceRange", "ui.dashboard.finance.range", "days:90");
        cy.visit("/");
        cy.location("pathname").should("match", /\/finance\/?$/);
        cy.get("#finance-range").should("have.value", "days:90");
        cy.visit("account/#tab-appearance");
        choose("#settingLanding", "ui.landing", "people");
        choose("#settingFinanceRange", "ui.dashboard.finance.range", "fy");
    });

    it("falls back to People when the saved dashboard is no longer allowed", () => {
        cy.setupFinanceOnlySession();
        cy.visit("people/dashboard");
        cy.window().then((win) =>
            cy.request({ method: "POST", url: `/api/user/${win.CRM.userId}/setting/ui.landing`, body: { value: "groups" } }),
        );
        cy.visit("/");
        cy.location("pathname").should("match", /\/people\/dashboard$/);
        resetLanding();
    });
});
