/**
 * Regression guard: admin users must always see all top-level module menus
 * (Calendar, Events, Finance, Sunday School, Fundraiser) regardless of whether
 * the underlying system-wide feature flag is enabled. This prevents the #8667
 * scenario where an admin disables a module and then can't find the menu to
 * re-enable it.
 *
 * These tests run under the admin session (which cy.setupAdminSession provides)
 * and verify the sidebar nav contains the expected links.
 */
describe("Admin menu visibility — module menus always visible for admin (#8667)", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Admin sidebar should contain Calendar link", () => {
        cy.visit("v2/dashboard");
        cy.get(".navbar-nav, .list-unstyled").contains("Calendar").should("exist");
    });

    it("Admin sidebar should contain Events link", () => {
        cy.visit("v2/dashboard");
        cy.get(".navbar-nav, .list-unstyled").contains("Events").should("exist");
    });

    it("Admin sidebar should contain Finance link", () => {
        cy.visit("v2/dashboard");
        cy.get(".navbar-nav, .list-unstyled").contains("Finance").should("exist");
    });

    it("Admin sidebar should contain Sunday School link", () => {
        cy.visit("v2/dashboard");
        cy.get(".navbar-nav, .list-unstyled").contains("Sunday School").should("exist");
    });

    it("Admin sidebar should contain Fundraiser link", () => {
        cy.visit("v2/dashboard");
        cy.get(".navbar-nav, .list-unstyled").contains("Fundraiser").should("exist");
    });
});

describe("User Editor - ORM Migration Tests", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Should edit user permissions and persist via ORM", () => {
        // Intercept the UserEditor form POST so we can wait on the real
        // save round-trip rather than on a hard-coded 500ms sleep.
        cy.intercept('POST', '**/UserEditor.php*').as('saveUser');

        // Edit existing admin user (PersonID 1 always exists)
        cy.visit('UserEditor.php?PersonID=1');
        cy.contains("User Editor");

        // Modify a permission
        cy.get('#Finance').check();
        cy.get('#SaveButton').click();
        cy.wait('@saveUser');

        // Reload page and verify ORM loaded the updated value
        cy.visit('UserEditor.php?PersonID=1');
        cy.get('#Finance').should('be.checked');

        // Uncheck to clean up
        cy.get('#Finance').uncheck();
        cy.get('#SaveButton').click();
        cy.wait('@saveUser');
    });

    it("Should handle ORM user update with multiple permission changes", () => {
        cy.intercept('POST', '**/UserEditor.php*').as('saveUser');

        cy.visit('UserEditor.php?PersonID=1');
        cy.contains("User Editor");

        // Update multiple fields at once (tests ORM object state management)
        cy.get('#AddRecords').check();
        cy.get('#EditRecords').check();
        cy.get('#Notes').check();
        cy.get('#SaveButton').click();
        cy.wait('@saveUser');

        // Reload and verify ORM persisted all changes
        cy.visit('UserEditor.php?PersonID=1');
        cy.get('#AddRecords').should('be.checked');
        cy.get('#EditRecords').should('be.checked');
        cy.get('#Notes').should('be.checked');

        // Clean up
        cy.get('#AddRecords').uncheck();
        cy.get('#EditRecords').uncheck();
        cy.get('#Notes').uncheck();
        cy.get('#SaveButton').click();
        cy.wait('@saveUser');
    });

    it("Should update username via ORM", () => {
        cy.intercept('POST', '**/UserEditor.php*').as('saveUser');

        cy.visit('UserEditor.php?PersonID=1');
        cy.contains("User Editor");

        // Get original username
        cy.get('#UserName').invoke('val').then((originalUsername) => {
            const newUsername = 'admin_orm_test';

            // Update username
            cy.get('#UserName').clear().type(newUsername);
            cy.get('#SaveButton').click();
            cy.wait('@saveUser');

            // Verify ORM persisted the change
            cy.visit('UserEditor.php?PersonID=1');
            cy.get('#UserName').should('have.value', newUsername);

            // Reset to original
            cy.get('#UserName').clear().type(originalUsername);
            cy.get('#SaveButton').click();
            cy.wait('@saveUser');
        });
    });

});
