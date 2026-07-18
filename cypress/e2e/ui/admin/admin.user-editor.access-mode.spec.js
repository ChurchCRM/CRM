/// <reference types="cypress" />

/**
 * UX for the user editor "Access level" selector (Administrator / Self-service /
 * Custom). Selecting a mode drives the hidden Admin/EditSelf flags and shows or
 * hides the Custom module-permission panel, so EditSelf can never coexist with
 * module or admin permissions.
 *
 * These tests only manipulate the DOM and NEVER save — switching the admin user
 * (PersonID 1) to a non-admin mode and saving would strip its admin rights and
 * break the rest of the suite. The Tabler form-selectgroup radios are visually
 * hidden, so they're toggled with { force: true }.
 */
describe("User Editor - Access level selector (Admin/Self-service/Custom)", () => {
    beforeEach(() => {
        cy.setupAdminSession();
        cy.visit("admin/system/users/1/edit");
        cy.contains("User Editor");
    });

    it("Admin user loads in Administrator mode with module permissions hidden", () => {
        cy.get('input[name="accessMode"][value="admin"]').should("be.checked");
        cy.get("#customPermissions").should("not.be.visible");
        cy.get("#Admin").should("be.checked");
    });

    it("Self-service mode flags EditSelf only and hides module permissions", () => {
        cy.get('input[name="accessMode"][value="self"]').check({ force: true });
        cy.get("#customPermissions").should("not.be.visible");
        cy.get("#EditSelf").should("be.checked");
        cy.get("#Admin").should("not.be.checked");
    });

    it("Custom mode reveals the module permission switches", () => {
        cy.get('input[name="accessMode"][value="custom"]').check({ force: true });
        cy.get("#customPermissions").should("be.visible");
        cy.get("#AddRecords").should("be.visible");
        cy.get("#Admin").should("not.be.checked");
        cy.get("#EditSelf").should("not.be.checked");
    });

    it("Switching back to Administrator hides modules and flags Admin", () => {
        cy.get('input[name="accessMode"][value="custom"]').check({ force: true });
        cy.get("#customPermissions").should("be.visible");
        cy.get('input[name="accessMode"][value="admin"]').check({ force: true });
        cy.get("#customPermissions").should("not.be.visible");
        cy.get("#Admin").should("be.checked");
        cy.get("#EditSelf").should("not.be.checked");
    });
});
