/// <reference types="cypress" />

describe("Standard Family", () => {
    beforeEach(() => cy.setupStandardSession());

    it("View invalid Family", () => {
        cy.visit("v2/family/9999");
        cy.location("pathname").should("include", "family/not-found");
        cy.contains("Oops! FAMILY 9999 Not Found");
    });
    
    it("Entering a new Family", () => {
        cy.visit("FamilyEditor.php");

        cy.contains("Family Info");
        // Fill in Family Info section
        cy.get("#FamilyName").type("Troy" + Cypress._.random(0, 1e6));
        cy.get('input[name="Address1"').type("4222 Clinton Way");
        cy.get('input[name="City"]').clear().type("Los Angeles");
        cy.get('select[name="State"]').select("CA", { force: true });
        // Add clearing of Lat/Long to verify these can be null, instead of default 0
        cy.get('input[name="Latitude"]').clear();
        cy.get('input[name="Longitude"]').clear();

        // Fill in Contact Information section
        cy.get('input[name="Email"]').type("mike@example.com");

        // Fill in Wedding Date (now in Family Identity section)
        const weddingYear = "2024";
        const weddingMonth = "04";
        const weddingDay = "03";
        cy.get("#WeddingDate").type(
            `${weddingYear}-${weddingMonth}-${weddingDay}`,
        );

        // Fill in Family Members (default 4 rows, add 2 more via button)
        cy.get('input[name="FirstName1"]').type("Mike");
        cy.get('input[name="FirstName2"]').type("Carol");
        cy.get('input[name="FirstName3"]').type("Alice");
        cy.get('input[name="FirstName4"]').type("Greg");
        // Add more family members using the button
        cy.get('#addFamilyMemberRow').click();
        cy.get('input[name="FirstName5"]').type("Marcia");
        cy.get('#addFamilyMemberRow').click();
        cy.get('input[name="FirstName6"]').type("Peter");
        cy.get('select[name="Classification1"]').select("1", { force: true });
        cy.get('select[name="Classification2"]').select("1", { force: true });
        cy.get('select[name="Classification3"]').select("1", { force: true });
        cy.get('select[name="Classification4"]').select("2", { force: true });
        cy.get('select[name="Classification5"]').select("1", { force: true });
        cy.get('select[name="Classification6"]').select("2", { force: true });

        // Click FAB save button
        cy.get(".fab-save").click();

        cy.location("pathname").should("include", "/v2/family/");
        cy.contains("Mike Troy");
        cy.contains("Carol Troy");
        cy.contains("Alice Troy");
        cy.contains("Greg Troy");
        cy.contains("Marcia Troy");
        cy.contains("Peter Troy");
        cy.contains("4222 Clinton Way Los Angeles, CA");
        cy.contains("mike@example.com");
        cy.contains(`${weddingMonth}/${weddingDay}/${weddingYear}`);

        // Delete Email and WeddingDate - click FAB edit button on family view
        cy.get('.fab-edit').click();
        cy.get('input[name="Email"]').clear();
        cy.get("#WeddingDate").clear();

        // Click FAB save button
        cy.get(".fab-save").click();

        cy.should('not.have.text', 'mike@example.com');
        cy.should('not.have.text', `${weddingMonth}/${weddingDay}/${weddingYear}`);
    });
});
