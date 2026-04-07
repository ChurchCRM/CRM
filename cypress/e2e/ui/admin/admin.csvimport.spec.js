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
            // Verify at least the 3 members from this import exist (test DB may have prior runs)
            cy.request("GET", "/api/search/ImportTest").then((response) => {
                expect(response.status).to.eq(200);
                const personsGroup = response.body.find((g) => g.text.startsWith("Persons"));
                expect(personsGroup.children.length).to.be.at.least(3);
            });
        });

        it("Verify CSV Import sets Classification and FamilyRole", () => {
            cy.visit("admin/import/csv");
            // Attach the classification fixture (has Classification + FamilyRole columns)
            cy.get("#csvFile").selectFile("cypress/fixtures/test_classification_import.csv", { force: true });
            cy.get("#csv-import-form").submit();
            // Mapping step — Classification and FamilyRole are in CSV_FIELD_ALIASES and auto-map
            cy.get("#mapping-card").should("be.visible");
            cy.get("#execute-import").click();
            cy.get("#summary-card").should("be.visible");
            cy.get("#summary-imported").should("not.have.text", "0");

            // Find the most recently imported ClsRoleTest persons via search
            cy.request("GET", "/api/search/ClsRoleTest").then((response) => {
                expect(response.status).to.eq(200);
                const personsGroup = response.body.find((g) => g.text.startsWith("Persons"));
                expect(personsGroup).to.exist;
                expect(personsGroup.children).to.have.length.at.least(2);

                // Extract the last 2 person IDs (most recent import) from the uri field
                const recentChildren = personsGroup.children.slice(-2);
                recentChildren.forEach((child) => {
                    const match = child.uri.match(/PersonID=(\d+)/);
                    expect(match, `Expected PersonID in uri: ${child.uri}`).to.exist;
                    const personId = match[1];

                    cy.makePrivateAdminAPICall("GET", `/api/person/${personId}`, null, 200).then((personResp) => {
                        const person = personResp.body;
                        // Classification must be resolved (non-zero means "Member" was matched)
                        expect(person.ClsId, `ClsId for person ${personId}`).to.be.greaterThan(0);
                        // Family role must be resolved for family members (non-zero)
                        expect(person.FmrId, `FmrId for person ${personId}`).to.be.greaterThan(0);
                    });
                });
            });
        });
    },
);
