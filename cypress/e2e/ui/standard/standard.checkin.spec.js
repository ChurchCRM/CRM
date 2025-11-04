/// <reference types="cypress" />

describe("Event Checkin with Select2", () => {
    beforeEach(() => {
        // Create a test event before each test
        cy.loginAdmin("EventEditor.php");
        cy.get("#EventTitle").clear().type("Test Checkin Event");
        cy.get("#EventDesc").clear().type("Test event for Select2 checkin");
        cy.get('input[name="EventStartDate"]').clear().type("2025-12-01");
        cy.get('input[name="EventStartTime"]').clear().type("10:00 AM");
        cy.get('input[name="EventEndDate"]').clear().type("2025-12-01");
        cy.get('input[name="EventEndTime"]').clear().type("12:00 PM");
        cy.get("#event-editor-save-button").click();
        
        // Wait for event to be created
        cy.url().should("include", "ListEvents.php");
    });

    it("Load Checkin Page", () => {
        cy.loginStandard("Checkin.php");
        cy.contains("Select the event to which you would like to check people in for");
        cy.contains("Select Event");
        cy.contains("Add New Event");
    });

    it("Select Event and Show Checkin Form", () => {
        cy.loginStandard("Checkin.php");
        
        // Select the test event
        cy.get("#EventID").select("Test Checkin Event");
        
        // Verify checkin form appears
        cy.contains("Add Attendees for Event");
        cy.contains("Person's Name");
        cy.contains("Adult Name (Optional)");
        cy.contains("CheckIn");
    });

    it("Person Search Input Has Select2 Initialized", () => {
        cy.loginStandard("Checkin.php");
        cy.get("#EventID").select("Test Checkin Event");
        
        // Check that Select2 is initialized on the child input
        cy.get("#child").parent().should("have.class", "select2-hidden-accessible");
        cy.get(".select2-container").should("exist");
    });

    it("Select2 Search for Person", () => {
        cy.loginStandard("Checkin.php");
        cy.get("#EventID").select("Test Checkin Event");
        
        // Click on the Select2 container to open dropdown
        cy.get("#child").parent().find(".select2-selection").click();
        
        // Type search term in Select2 search input
        cy.get(".select2-search__field").type("Admin");
        
        // Wait for AJAX results
        cy.wait(500);
        
        // Verify dropdown shows results
        cy.get(".select2-results__options").should("be.visible");
        cy.get(".select2-results__option").should("have.length.greaterThan", 0);
    });

    it("Select Person from Select2 Dropdown", () => {
        cy.loginStandard("Checkin.php");
        cy.get("#EventID").select("Test Checkin Event");
        
        // Open Select2 dropdown
        cy.get("#child").parent().find(".select2-selection").click();
        
        // Search for person
        cy.get(".select2-search__field").type("Admin Church");
        cy.wait(500);
        
        // Select the first result
        cy.get(".select2-results__option").first().click();
        
        // Verify hidden ID field is populated
        cy.get("#child-id").should("not.have.value", "");
        
        // Verify person details are displayed
        cy.get("#childDetails").should("be.visible");
        cy.get("#childDetails").should("contain", "Admin Church");
        cy.get("#childDetails img").should("be.visible");
    });

    it("Adult Select2 Search Works", () => {
        cy.loginStandard("Checkin.php");
        cy.get("#EventID").select("Test Checkin Event");
        
        // Test adult input
        cy.get("#adult").parent().find(".select2-selection").click();
        cy.get(".select2-search__field").type("Joel");
        cy.wait(500);
        
        cy.get(".select2-results__option").should("have.length.greaterThan", 0);
        cy.get(".select2-results__option").first().click();
        
        // Verify hidden ID field is populated
        cy.get("#adult-id").should("not.have.value", "");
        
        // Verify adult details are displayed
        cy.get("#adultDetails").should("be.visible");
    });

    it("Clear Select2 Selection", () => {
        cy.loginStandard("Checkin.php");
        cy.get("#EventID").select("Test Checkin Event");
        
        // Select a person
        cy.get("#child").parent().find(".select2-selection").click();
        cy.get(".select2-search__field").type("Admin Church");
        cy.wait(500);
        cy.get(".select2-results__option").first().click();
        
        // Verify selection
        cy.get("#child-id").should("not.have.value", "");
        cy.get("#childDetails").should("be.visible");
        
        // Clear the selection
        cy.get("#child").parent().find(".select2-selection__clear").click();
        
        // Verify fields are cleared
        cy.get("#child-id").should("have.value", "");
        cy.get("#childDetails").should("have.class", "hidden");
    });

    it("Minimum Input Length Requirement", () => {
        cy.loginStandard("Checkin.php");
        cy.get("#EventID").select("Test Checkin Event");
        
        // Open Select2
        cy.get("#child").parent().find(".select2-selection").click();
        
        // Type only 1 character (less than minimum)
        cy.get(".select2-search__field").type("A");
        
        // Should show minimum input message
        cy.get(".select2-results__option").should("contain", "Please enter 2 or more characters");
    });

    it("Complete Checkin Flow with Select2", () => {
        cy.loginStandard("Checkin.php");
        cy.get("#EventID").select("Test Checkin Event");
        
        // Select person to check in
        cy.get("#child").parent().find(".select2-selection").click();
        cy.get(".select2-search__field").type("Admin Church");
        cy.wait(500);
        cy.get(".select2-results__option").first().click();
        
        // Verify person is selected
        cy.get("#childDetails").should("be.visible");
        
        // Select adult checking in (optional)
        cy.get("#adult").parent().find(".select2-selection").click();
        cy.get(".select2-search__field").type("Joel");
        cy.wait(500);
        cy.get(".select2-results__option").first().click();
        
        // Submit the form
        cy.get('input[name="CheckIn"]').click();
        
        // Verify the person appears in the checked-in table
        cy.get("#checkedinTable").should("be.visible");
        cy.get("#checkedinTable tbody tr").should("have.length.greaterThan", 0);
        cy.get("#checkedinTable").should("contain", "Admin Church");
    });

    it("API Endpoint Returns Valid Data", () => {
        cy.loginStandard("Checkin.php");
        
        // Test the API endpoint directly
        cy.request({
            url: "/api/persons/search/Admin",
            method: "GET",
            failOnStatusCode: true
        }).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.body).to.be.an("array");
            expect(response.body.length).to.be.greaterThan(0);
            
            // Verify response structure
            const firstPerson = response.body[0];
            expect(firstPerson).to.have.property("id");
            expect(firstPerson).to.have.property("objid");
            expect(firstPerson).to.have.property("text");
            expect(firstPerson).to.have.property("uri");
        });
    });
});
