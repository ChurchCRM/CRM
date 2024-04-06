context(
    "CSVImport",
    {
        retries: {
            runMode: 0,
            openMode: 0,
        },
    },
    () => {
        it("Verify CSV Import", () => {
            cy.loginAdmin("CSVImport.php");
            cy.get("#CSVFileChooser").selectFile(
                "cypress/data/test_import.csv",
            );
            cy.get("#UploadCSVBtn").click();
            cy.contains("Total number of rows in the CSV file:3");
            // It is not clear why, but it seems that force:true was needed to get the selections to work
            cy.get("#SelField0").select("Last Name", { force: true });
            cy.get("#SelField1").select("First Name", { force: true });
            cy.get("#SelField2").select("Address 1", { force: true });
            cy.get("#SelField3").select("City", { force: true });
            cy.get("#SelField4").select("State", { force: true });
            cy.get("#SelField5").select("Zip", { force: true });
            cy.get("#SelField6").select("Email", { force: true });
            cy.get("#SelField7").select("Birth Date", { force: true });
            cy.get("#SelField8").select("Home Phone", { force: true });
            // Now that we have mapped the right fields, do the import
            cy.get("#DoImportBtn").click();
            cy.contains("Data import successful.");
            // Now verify everyone was added to the family (expect 3 members)
            cy.request("GET", "/api/search/ImportTest").then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body[0].children.length).to.eq(3);
            });
        });
    },
);
