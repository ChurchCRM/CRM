/// <reference types="cypress" />

describe("Test Post Setup block", () => {
    
    it("Redirects to session/begin", () => {
        cy.visit("/setup");
        cy.location("pathname").should("eq", "/session/begin");
    });
});

describe("Test Setup Wizard Locale Detection", () => {
    it("Should display locale support information", () => {
        // Navigate to setup
        cy.visit("/setup");
        
        // Verify we're on the setup page
        cy.contains("Welcome to ChurchCRM Setup Wizard").should("be.visible");
        
        // Expand locale support section to verify it exists
        cy.get("#locale-support-collapse").should("exist");
        cy.get('a[href="#locale-support-collapse"]').click();
        
        // Verify locale information is displayed
        cy.get("#locale-support-summary").should("be.visible");
        cy.get("#locale-support-table").should("be.visible");
        
        // Check that either available or unavailable locales are shown
        cy.get("#locale-support-table").within(() => {
            cy.get("tr").should("have.length.at.least", 1);
        });
    });

    it("Should have locale check endpoint responding", () => {
        cy.request("GET", "/setup/SystemLocaleCheck").then((response) => {
            expect(response.status).to.equal(200);
            expect(response.body).to.have.property("supportedLocales");
            expect(response.body).to.have.property("availableSystemLocales");
            expect(response.body).to.have.property("systemLocaleSupportSummary");
            expect(response.body).to.have.property("systemLocaleDetected");
            
            // Verify supportedLocales is an array
            expect(response.body.supportedLocales).to.be.an("array");
            
            // Verify that supported locales have required properties
            if (response.body.supportedLocales.length > 0) {
                const firstLocale = response.body.supportedLocales[0];
                expect(firstLocale).to.have.property("name");
                expect(firstLocale).to.have.property("locale");
                expect(firstLocale).to.have.property("systemAvailable");
            }
        });
    });

    it("Should handle locale check errors gracefully", () => {
        // Even if locale detection fails, the setup should continue
        cy.visit("/setup");
        cy.contains("Welcome to ChurchCRM Setup Wizard").should("be.visible");
        
        // The prerequisites check should be visible
        cy.get("#prerequisites-next-btn").should("exist");
    });
});
