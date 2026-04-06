describe("CSV Export Page", () => {
    beforeEach(() => {
        cy.setupAdminSession();
        cy.visit("/CSVExport.php");
    });

    it("should load the page with correct title", () => {
        cy.contains("CSV Export").should("be.visible");
    });

    it("should display Field Selection card with pill toggles", () => {
        cy.contains("Field Selection").should("be.visible");

        // Verify pills container exists
        cy.get(".form-selectgroup-pills").first().within(() => {
            // Verify all standard field pills are present
            cy.contains(".form-selectgroup-label", "Title").should("be.visible");
            cy.contains(".form-selectgroup-label", "First Name").should("be.visible");
            cy.contains(".form-selectgroup-label", "Last Name").should("not.exist"); // Last Name is required, not a pill
            cy.contains(".form-selectgroup-label", "Middle Name").should("be.visible");
            cy.contains(".form-selectgroup-label", "Suffix").should("be.visible");
            cy.contains(".form-selectgroup-label", "Address 1").should("be.visible");
            cy.contains(".form-selectgroup-label", "City").should("be.visible");
            cy.contains(".form-selectgroup-label", "State").should("be.visible");
            cy.contains(".form-selectgroup-label", "Zip").should("be.visible");
            cy.contains(".form-selectgroup-label", "Country").should("be.visible");
            cy.contains(".form-selectgroup-label", "Home Phone").should("be.visible");
            cy.contains(".form-selectgroup-label", "Email").should("be.visible");
            cy.contains(".form-selectgroup-label", "Gender").should("be.visible");
        });

        // Verify pre-checked fields
        cy.get('.form-selectgroup-input[name="FirstName"]').should("be.checked");
        cy.get('.form-selectgroup-input[name="Address1"]').should("be.checked");
        cy.get('.form-selectgroup-input[name="City"]').should("be.checked");
        cy.get('.form-selectgroup-input[name="State"]').should("be.checked");
        cy.get('.form-selectgroup-input[name="Zip"]').should("be.checked");
        cy.get('.form-selectgroup-input[name="Country"]').should("be.checked");

        // Verify unchecked fields
        cy.get('.form-selectgroup-input[name="Title"]').should("not.be.checked");
        cy.get('.form-selectgroup-input[name="Envelope"]').should("not.be.checked");
    });

    it("should allow toggling field pills on and off", () => {
        cy.contains(".form-selectgroup-label", "Title").click();
        cy.get('.form-selectgroup-input[name="Title"]').should("be.checked");

        cy.contains(".form-selectgroup-label", "Title").click();
        cy.get('.form-selectgroup-input[name="Title"]').should("not.be.checked");
    });

    it("should display Filters card with form controls", () => {
        cy.contains("Filters").should("be.visible");

        // Records to export dropdown
        cy.get('select[name="Source"]').should("be.visible");
        cy.get('select[name="Source"]').find("option").should("have.length", 2);

        // Classification multi-select
        cy.get('select[name="Classification[]"]').should("be.visible");
        cy.get('select[name="Classification[]"]').find("option").should("have.length.at.least", 1);

        // Family Role multi-select
        cy.get('select[name="FamilyRole[]"]').should("be.visible");
        cy.get('select[name="FamilyRole[]"]').find("option").should("have.length.at.least", 1);

        // Gender dropdown
        cy.get('select[name="Gender"]').should("be.visible");
        cy.get('select[name="Gender"]').find("option").should("have.length", 3);

        // Group Membership multi-select
        cy.get('select[name="GroupID[]"]').should("be.visible");

        // Date range inputs
        cy.get("#MembershipDate1").should("be.visible");
        cy.get("#MembershipDate2").should("be.visible");
        cy.get("#BirthdayDate1").should("be.visible");
        cy.get("#BirthdayDate2").should("be.visible");
    });

    it("should display Output Method card with format options", () => {
        cy.contains("Output Method").should("be.visible");

        cy.get('select[name="Format"]').should("be.visible");
        cy.get('select[name="Format"]').find("option").should("have.length", 3);
        cy.get('select[name="Format"] option[value="Default"]').should("exist");
        cy.get('select[name="Format"] option[value="Rollup"]').should("exist");
        cy.get('select[name="Format"] option[value="AddToCart"]').should("exist");

        // Skip incomplete address checkbox
        cy.get("#SkipIncompleteAddr").should("be.visible").and("not.be.checked");

        // Submit button
        cy.get('input[name="Submit"]').should("be.visible");
    });

    it("should not contain ChMeetings export section", () => {
        cy.contains("ChMeetings Export").should("not.exist");
    });
});
