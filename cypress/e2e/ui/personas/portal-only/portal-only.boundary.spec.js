/// <reference types="cypress" />

import { LEGACY_PAGES, MVC_PAGES } from "../../../../support/mvc-pages";

const expectPortal = (page) => {
    cy.visit(page, { failOnStatusCode: false });
    cy.url().should("include", "/portal");
};

describe("portal-only: every MVC app returns to the portal", () => {
    beforeEach(() => cy.setupPersonaSession("portal-only"));

    Object.entries(MVC_PAGES).forEach(([app, pages]) => {
        context(app, () => {
            pages.forEach((page) => {
                it(`${page} redirects to /portal`, () => expectPortal(page));
            });
        });
    });

    context("legacy pages", () => {
        LEGACY_PAGES.forEach((page) => {
            it(`${page} redirects to /portal`, () => expectPortal(page));
        });
    });

    it("the portal home shows no admin shell", () => {
        cy.visit("/portal/");
        cy.get(".portal-home").should("exist");
        cy.get("#sidebar").should("not.exist");
    });

    it("the password-change page of its sign-in flow stays reachable", () => {
        cy.visit("/v2/user/current/changepassword");
        cy.url().should("include", "/v2/user/current/changepassword");
    });
});

describe("portal-only-dormant-flags: a leftover staff flag does not open any MVC app", () => {
    beforeEach(() => cy.setupPersonaSession("portal-only-dormant-flags"));

    Object.entries(MVC_PAGES).forEach(([app, pages]) => {
        it(`${app} home (${pages[0]}) redirects to /portal`, () => expectPortal(pages[0]));
    });
});
