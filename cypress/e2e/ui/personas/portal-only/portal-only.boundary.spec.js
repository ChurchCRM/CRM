/// <reference types="cypress" />

import { LEGACY_PAGES, MVC_PAGES } from "../../../../support/mvc-pages";

// A member session opening a staff page is sent to the staff sign-in.
const expectStaffSignIn = (page) => {
    cy.visit(page, { failOnStatusCode: false });
    cy.location("pathname").should("eq", "/admin/");
    cy.get("#UserBox").should("be.visible");
};

describe("portal-only: every staff app sends a member to the staff sign-in", () => {
    beforeEach(() => cy.setupPersonaSession("portal-only"));

    Object.entries(MVC_PAGES).forEach(([app, pages]) => {
        context(app, () => {
            pages.forEach((page) => {
                it(`${page} goes to the staff sign-in`, () => expectStaffSignIn(page));
            });
        });
    });

    context("legacy pages", () => {
        LEGACY_PAGES.forEach((page) => {
            it(`${page} goes to the staff sign-in`, () => expectStaffSignIn(page));
        });
    });

    it("the portal home shows no admin shell", () => {
        cy.visit("/portal/");
        cy.get(".portal-home").should("exist");
        cy.get("#sidebar").should("not.exist");
    });
});
