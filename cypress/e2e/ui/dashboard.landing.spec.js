/// <reference types="cypress" />

describe("Dashboard tabs and main dashboard", () => {
    const resetLanding = () =>
        cy.window().then((win) =>
            cy.request({ method: "POST", url: `/api/user/${win.CRM.userId}/setting/ui.landing`, body: { value: "people" } }),
        );

    it("shows an administrator every dashboard", () => {
        cy.setupAdminSession();
        cy.visit("people/dashboard");
        ["People", "Groups", "Sunday School", "Events", "Finance"].forEach((tab) => cy.get("#dashboardSwitch").should("contain", tab));
        cy.get("#dashboardSwitch .nav-link.active").invoke("text").then((text) => expect(text.trim()).to.eq("People"));
    });

    it("shows finance-only staff People and Finance, and no group dashboards", () => {
        cy.setupFinanceOnlySession();
        cy.visit("people/dashboard");
        cy.get("#dashboardSwitch").should("contain", "Finance").and("not.contain", "Groups").and("not.contain", "Sunday School");
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
