/// <reference types="cypress" />

const KEY = "churchcrm.desktopSidebarCollapsed";

describe("Staff desktop sidebar preference", () => {
    beforeEach(() => {
        cy.setupStandardSession();
        cy.viewport(1440, 900);
        cy.visit("/v2/dashboard");
        cy.window().then((win) => win.localStorage.removeItem(KEY));
        cy.reload();
    });

    afterEach(() => {
        cy.window().then((win) => {
            win.localStorage.removeItem(KEY);
            win.document.body.classList.remove("desktop-sidebar-collapsed");
        });
    });

    for (const theme of ["light", "dark"]) {
        it(`collapses and expands the desktop sidebar in ${theme} mode`, () => {
            cy.document().then((doc) => doc.documentElement.setAttribute("data-bs-theme", theme));
            cy.get("#desktop-sidebar-toggle")
                .should("be.visible")
                .and("have.attr", "aria-controls", "sidebar")
                .and("have.attr", "aria-expanded", "true");
            cy.get("#sidebar").should("be.visible");

            cy.get("#desktop-sidebar-toggle").click();
            cy.get("#sidebar").should("not.be.visible");
            cy.get("#desktop-sidebar-toggle")
                .should("have.attr", "aria-expanded", "false")
                .and("have.attr", "aria-label", "Expand sidebar");

            cy.get("#desktop-sidebar-toggle").click();
            cy.get("#sidebar").should("be.visible");
            cy.get("#desktop-sidebar-toggle")
                .should("have.attr", "aria-expanded", "true")
                .and("have.attr", "aria-label", "Collapse sidebar");
        });
    }

    it("persists the preference across reload and restores it when reopened", () => {
        cy.get("#desktop-sidebar-toggle").click();
        cy.window().its("localStorage").invoke("getItem", KEY).should("eq", "true");
        cy.reload();

        cy.get("#desktop-sidebar-toggle").should("have.attr", "aria-expanded", "false");
        cy.get("#sidebar").should("not.be.visible");

        cy.get("#desktop-sidebar-toggle").click();
        cy.window().its("localStorage").invoke("getItem", KEY).should("eq", "false");
        cy.get("#sidebar").should("be.visible");
    });

    for (const [name, width] of [["tablet", 820], ["phone", 390]]) {
        it(`keeps the existing ${name} hamburger behavior after a desktop collapse`, () => {
            cy.get("#desktop-sidebar-toggle").click();
            cy.viewport(width, 820);
            cy.get("#desktop-sidebar-toggle").should("not.be.visible");
            cy.get("#sidebar").should("be.visible");
            cy.get('#sidebar .navbar-toggler[data-bs-target="#sidebar-menu"]')
                .should("be.visible")
                .click();
            cy.get("#sidebar-menu").should("have.class", "show");
        });
    }
});
