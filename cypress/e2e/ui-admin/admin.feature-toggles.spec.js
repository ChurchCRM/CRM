/// <reference types="cypress" />

describe("Admin Feature Toggles page", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("renders once with a single page title and toggles", () => {
        cy.visit("admin/system/feature-toggles");
        cy.get(".page-title").should("have.length", 1).and("contain", "Feature Toggles");
        cy.get(".feature-toggle").should("have.length.greaterThan", 0);
        cy.get(".feature-toggle").each(($el) => {
            expect($el.attr("aria-label")).to.be.a("string").and.not.be.empty;
        });
    });

    it("shows a Feature Toggles card on the dashboard linking to the full page", () => {
        cy.visit("admin/");
        cy.get(".dashboard-feature-toggle").should("have.length.greaterThan", 0);
        cy.contains("a", "Manage All Features").click();
        cy.url().should("include", "/admin/system/feature-toggles");
    });

    it("saves a toggle from the dashboard card", () => {
        cy.visit("admin/");
        cy.get('.dashboard-feature-toggle[data-setting="bEnabledEvents"]').then(($cb) => {
            const wasChecked = $cb.prop("checked");
            cy.wrap($cb).click();
            cy.get('.dashboard-feature-toggle[data-setting="bEnabledEvents"]').should(
                wasChecked ? "not.be.checked" : "be.checked",
            );
            cy.get('.dashboard-feature-toggle[data-setting="bEnabledEvents"]').click();
            cy.get('.dashboard-feature-toggle[data-setting="bEnabledEvents"]').should(
                wasChecked ? "be.checked" : "not.be.checked",
            );
        });
    });

    it("saves a toggle and persists it after reload", () => {
        cy.visit("admin/system/feature-toggles");
        cy.get('.feature-toggle[data-setting="bEnabledEvents"]').then(($cb) => {
            const wasChecked = $cb.prop("checked");
            cy.wrap($cb).click();
            cy.contains(".status-badge", "Saved").should("be.visible");
            cy.url().should("include", "/admin/system/feature-toggles");
            cy.get('.feature-toggle[data-setting="bEnabledEvents"]').should(
                wasChecked ? "not.be.checked" : "be.checked",
            );
            // restore
            cy.get('.feature-toggle[data-setting="bEnabledEvents"]').click();
            cy.contains(".status-badge", "Saved").should("be.visible");
        });
    });
});
