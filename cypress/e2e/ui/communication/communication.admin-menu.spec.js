/// <reference types="cypress" />

describe("Communication admin menu", () => {
    describe("administrator", () => {
        beforeEach(() => cy.setupAdminSession());

        it("lists Email Settings once, under Communication > Admin", () => {
            cy.visit("v2/dashboard");
            cy.get("#sidebar-menu a[href$='admin/email']").should("have.length", 1);
        });

        it("opens the Email Settings page", () => {
            cy.visit("admin/email");
            cy.contains("Email Settings").should("be.visible");
            cy.get("#emailSettings").should("exist");
        });
    });

    describe("standard user", () => {
        beforeEach(() => cy.setupStandardSession());

        it("does not list Email Settings", () => {
            cy.visit("v2/dashboard");
            cy.get("#sidebar-menu a[href$='admin/email']").should("not.exist");
        });
    });
});
