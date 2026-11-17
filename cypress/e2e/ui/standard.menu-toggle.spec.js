/// <reference types="cypress" />

const VIEWPORTS = [
    ["tablet", 820, 1180],
    ["phone", 390, 844],
];

describe("Menu button in the top bar", () => {
    beforeEach(() => cy.setupStandardSession());

    VIEWPORTS.forEach(([name, width, height]) => {
        describe(name, () => {
            beforeEach(() => {
                cy.viewport(width, height);
                cy.visit("/v2/dashboard");
            });

            it("sits on the search row without the logo or a floating menu button", () => {
                cy.get("#menu-toggle").should("be.visible");
                cy.get("#sidebar .navbar-brand").should("not.be.visible");
                cy.get("#fab-menu-toggle").should("not.exist");
                cy.get('[data-bs-target="#navbar-menu"]:visible, #globalSearch:visible').first().then(($search) => {
                    cy.get("#menu-toggle").then(($btn) => {
                        const b = $btn[0].getBoundingClientRect();
                        const s = $search[0].getBoundingClientRect();
                        expect(Math.abs(b.top + b.height / 2 - (s.top + s.height / 2))).to.be.lessThan(8);
                        expect(b.right).to.be.at.most(s.left + 1);
                    });
                });
            });

            it("opens the sidebar menu", () => {
                cy.get("#sidebar-menu").should("not.be.visible");
                cy.get("#menu-toggle").click();
                cy.get("#sidebar-menu").should("be.visible");
            });
        });
    });
});
