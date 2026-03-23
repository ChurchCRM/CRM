/// <reference types="cypress" />

describe("Access Denied Page", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    describe("Direct Access", () => {
        it("Should display access denied page with default elements", () => {
            cy.visit("/v2/access-denied");
            cy.contains("Permission Required").should("be.visible");
            cy.contains("You don't have access to this page").should("be.visible");
            cy.contains("The page you tried to visit requires special permissions").should("be.visible");
            cy.contains("If you need access to this feature, please contact your church administrator.").should("be.visible");
            cy.get("a").contains("Go to Dashboard").should("be.visible");
        });

        it("Should display access denied page without role callout when no role parameter", () => {
            cy.visit("/v2/access-denied");
            cy.contains("Permission Required").should("be.visible");
            // The callout should not be visible when no role is specified
            cy.get(".callout-warning").should("not.exist");
        });
    });

    describe("Role Parameter Display", () => {
        it("Should display Admin role description", () => {
            cy.visit("/v2/access-denied?role=Admin");
            cy.contains("Permission Required").should("be.visible");
            cy.get(".callout-warning").should("be.visible");
            cy.contains("Required Permission").should("be.visible");
            cy.contains("Administrator privileges").should("be.visible");
        });

        it("Should display Finance role description", () => {
            cy.visit("/v2/access-denied?role=Finance");
            cy.contains("Permission Required").should("be.visible");
            cy.get(".callout-warning").should("be.visible");
            cy.contains("Finance access").should("be.visible");
        });

        it("Should display ManageGroups role description", () => {
            cy.visit("/v2/access-denied?role=ManageGroups");
            cy.contains("Permission Required").should("be.visible");
            cy.get(".callout-warning").should("be.visible");
            cy.contains("Group management access").should("be.visible");
        });

        it("Should display EditRecords role description", () => {
            cy.visit("/v2/access-denied?role=EditRecords");
            cy.contains("Permission Required").should("be.visible");
            cy.get(".callout-warning").should("be.visible");
            cy.contains("Edit records permission").should("be.visible");
        });

        it("Should display DeleteRecords role description", () => {
            cy.visit("/v2/access-denied?role=DeleteRecords");
            cy.contains("Permission Required").should("be.visible");
            cy.get(".callout-warning").should("be.visible");
            cy.contains("Delete records permission").should("be.visible");
        });

        it("Should display AddRecords role description", () => {
            cy.visit("/v2/access-denied?role=AddRecords");
            cy.contains("Permission Required").should("be.visible");
            cy.get(".callout-warning").should("be.visible");
            cy.contains("Add records permission").should("be.visible");
        });

        it("Should display MenuOptions role description", () => {
            cy.visit("/v2/access-denied?role=MenuOptions");
            cy.contains("Permission Required").should("be.visible");
            cy.get(".callout-warning").should("be.visible");
            cy.contains("Menu options access").should("be.visible");
        });

        it("Should display Notes role description", () => {
            cy.visit("/v2/access-denied?role=Notes");
            cy.contains("Permission Required").should("be.visible");
            cy.get(".callout-warning").should("be.visible");
            cy.contains("Notes access").should("be.visible");
        });

        it("Should display CreateDirectory role description", () => {
            cy.visit("/v2/access-denied?role=CreateDirectory");
            cy.contains("Permission Required").should("be.visible");
            cy.get(".callout-warning").should("be.visible");
            cy.contains("Create directory permission").should("be.visible");
        });

        it("Should display AddEvent role description", () => {
            cy.visit("/v2/access-denied?role=AddEvent");
            cy.contains("Permission Required").should("be.visible");
            cy.get(".callout-warning").should("be.visible");
            cy.contains("Add event permission").should("be.visible");
        });

        it("Should display default description for unknown role", () => {
            cy.visit("/v2/access-denied?role=UnknownRole");
            cy.contains("Permission Required").should("be.visible");
            // Unknown roles should NOT display a callout (security: don't expose invalid role names)
            cy.get(".callout-warning").should("not.exist");
        });
    });

    describe("Dashboard Navigation", () => {
        it("Should redirect to dashboard when clicking Go to Dashboard button", () => {
            cy.visit("/v2/access-denied?role=Admin");
            cy.get("a").contains("Go to Dashboard").click();
            cy.url().should("include", "/v2/dashboard");
            cy.contains("Dashboard").should("be.visible");
        });
    });

    describe("Page Styling", () => {
        it("Should display danger card styling", () => {
            cy.visit("/v2/access-denied?role=Admin");
            // Tabler uses bordered cards instead of `card-danger`
            cy.get(".card.border-danger").should("exist");
            cy.get(".card.border-danger").contains("Permission Required").should("be.visible");
            // Icon classes may be FontAwesome or Tabler; check for either
            cy.get('.card.border-danger i.fa-lock, .card.border-danger i.fa-solid.fa-lock, .card.border-danger i.ti-lock').should("exist");
            cy.get('.card.border-danger i.fa-user-lock, .card.border-danger i.fa-solid.fa-user-lock, .card.border-danger i.ti-user-lock').should("exist");
        });

        it("Should display warning callout for role information", () => {
            cy.visit("/v2/access-denied?role=Finance");
            // Callout may use legacy `callout-warning` or Bootstrap `alert-warning`
            cy.get('.callout-warning, .callout.callout-warning, .alert-warning').should("exist");
            cy.get('.callout-warning i.fa-key, .callout-warning i.ti-key, .alert-warning i.fa-key, .alert-warning i.ti-key').should("exist");
        });
    });
});

describe("Access Denied Redirect for Standard User", () => {
    beforeEach(() => {
        cy.setupStandardSession();
    });

    it("Standard user accessing admin page should be redirected to access-denied", () => {
        // Standard user tries to access admin-only page
        cy.visit("/admin/system/debug", { failOnStatusCode: false });
        // Should be redirected to access-denied page with Admin role
        cy.url().should("include", "/v2/access-denied");
        cy.url().should("include", "role=Admin");
        cy.contains("Administrator privileges").should("be.visible");
    });
});
