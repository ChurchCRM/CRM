/// <reference types="cypress" />

describe("Admin Settings", () => {
    before(() => {
        cy.loginAdmin("SystemSettings.php");
    });

    it("View system settings", () => {
        cy.contains("Church Information");
        cy.contains("User Setup");
        cy.contains("Email Setup");
        cy.contains("People Setup");
        cy.contains("System Settings");
        cy.contains("Map Settings");
        cy.contains("Report Settings");
        cy.contains("Localization");
        cy.contains("Financial Settings");
        cy.contains("Integration");
        cy.contains("Backup");
    });

    /*  TODO For some reason this resets the user session

        it('Update Church Name', () => {
        const newValue = "New Church -  " + Cypress._.random(0, 1e6)
        cy.get("input[name='new_value[1003]']").clear().type(newValue);
        cy.get("form[name='SystemSettingsForm']").submit();
        cy.location('pathname').should('include', "/SystemSettings.php");
        cy.visit("v2/admin/debug");
        cy.location('pathname').should('include', "/admin/debug");
        cy.visit("SystemSettings.php");
        cy.location('pathname').should('include', "/SystemSettings.php");
        cy.get("input[name='new_value[1003]']").should('have.value', newValue);
    });*/

    it("Password fields should not contain plaintext values (security fix GHSA-p98h-5xcj-5c6x)", () => {
        // Get all password input fields
        cy.get("input[type='password']").each(($passwordField) => {
            // Verify password fields do not have plaintext values in HTML
            cy.wrap($passwordField)
                .should("not.have.attr", "value")
                .or("have.attr", "value", ""); // If value attr exists, it should be empty
            
            // Verify placeholder text is present
            cy.wrap($passwordField).should("have.attr", "placeholder");
        });
    });
});
