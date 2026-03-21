describe(
    "CSVImport",
    {
        retries: {
            runMode: 0,
            openMode: 0,
        },
    },
    () => {
        beforeEach(() => {
            cy.setupAdminSession();
        });

        it("Verify CSV Import", () => {
            cy.visit("admin/import/csv");
            // Attach file to the hidden file input (force needed since it's d-none)
            cy.get("#csvFile").selectFile("cypress/fixtures/test_import.csv", { force: true });
            // Submit the upload form
            cy.get("#csv-import-form").submit();
            // Mapping step should appear — all columns in fixture are auto-mapped
            cy.get("#mapping-card").should("be.visible");
            // Execute the import
            cy.get("#execute-import").click();
            // Summary card should show successful results
            cy.get("#summary-card").should("be.visible");
            cy.get("#summary-imported").should("not.have.text", "0");
            // Verify everyone was added to the same family (expect 3 members)
            cy.request("GET", "/api/search/ImportTest").then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body[0].children.length).to.eq(3);
            });
        });
    },
);
