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

describe("CSV Export Authorization (GHSA-4vj2-gm78-3q63)", () => {
    it("should deny non-admin users access to CSVExport.php", () => {
        // Create a low-privileged user (only DeleteRecords permission)
        cy.createUser({
            user_name: "csvlowpriv",
            user_password: "test1234",
            user_role: 0,
            user_AddRecords: 0,
            user_EditRecords: 0,
            user_DeleteRecords: 1,
            user_Admin: 0
        });

        // Login as low-privileged user
        cy.logout();
        cy.login("csvlowpriv", "test1234");

        // Attempt to access CSVExport.php directly
        cy.visit("/CSVExport.php", { failOnStatusCode: false });

        // Should be redirected to access denied page
        cy.contains("You do not have permission").should("be.visible");
    });

    it("should deny non-admin users access to CSVCreateFile.php form submission", () => {
        // Create a low-privileged user
        cy.createUser({
            user_name: "csvlowpriv2",
            user_password: "test1234",
            user_role: 0,
            user_AddRecords: 1,
            user_EditRecords: 0,
            user_DeleteRecords: 0,
            user_Admin: 0
        });

        // Login as low-privileged user
        cy.logout();
        cy.login("csvlowpriv2", "test1234");

        // Attempt to POST to CSVCreateFile.php (form submission)
        cy.request({
            method: "POST",
            url: "/CSVCreateFile.php",
            body: {
                Title: 1,
                FirstName: 1,
                Address1: 1,
                City: 1,
                State: 1,
                Zip: 1,
                Country: 1,
                Email: 1,
                Source: "all",
                Gender: 0,
                Format: "Default",
                Submit: "Create File"
            },
            failOnStatusCode: false
        }).then((response) => {
            // Should get 403 Forbidden or be redirected
            expect([403, 302]).to.include(response.status);
        });
    });

    it("should allow admin users to access CSVExport.php and submit form", () => {
        cy.setupAdminSession();

        // Should be able to visit the form
        cy.visit("/CSVExport.php");
        cy.contains("CSV Export").should("be.visible");

        // Should be able to submit the form (POST to CSVCreateFile.php)
        cy.request({
            method: "POST",
            url: "/CSVCreateFile.php",
            body: {
                Title: 1,
                FirstName: 1,
                Address1: 1,
                City: 1,
                State: 1,
                Zip: 1,
                Country: 1,
                Email: 1,
                Source: "all",
                Gender: 0,
                Format: "Default",
                Submit: "Create File"
            }
        }).then((response) => {
            // Should succeed with 200 and CSV content-type
            expect(response.status).to.eq(200);
            expect(response.headers["content-type"]).to.include("text/csv");
        });
    });
});
