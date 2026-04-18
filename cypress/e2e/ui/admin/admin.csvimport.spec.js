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
            // Verify at least the 5 members from this import exist (test DB may have prior runs)
            cy.request("GET", "/api/search/ImportTest").then((response) => {
                expect(response.status).to.eq(200);
                const personsGroup = response.body.find((g) => g.text.startsWith("Persons"));
                expect(personsGroup.children.length).to.be.at.least(5);
            });
        });

        it("Verify CSV Import sets BirthDate correctly for mixed date formats", () => {
            cy.visit("admin/import/csv");
            cy.get("#csvFile").selectFile("cypress/fixtures/test_import.csv", { force: true });
            cy.get("#csv-import-form").submit();
            cy.get("#mapping-card").should("be.visible");
            cy.get("#execute-import").click();
            cy.get("#summary-card").should("be.visible");

            cy.request("GET", "/api/search/ImportTest").then((response) => {
                expect(response.status).to.eq(200);
                const personsGroup = response.body.find((g) =>
                    g.text.startsWith("Persons"),
                );
                expect(personsGroup).to.exist;

                // Build a map of first-name → personId from the search results
                const nameToId = {};
                personsGroup.children.forEach((child) => {
                    const match = child.uri.match(/PersonID=(\d+)/);
                    if (match) {
                        // child.text is "FirstName LastName"
                        const firstName = child.text.split(" ")[0];
                        nameToId[firstName] = match[1];
                    }
                });

                // hasBday: YYYY-MM-DD format → year=2001, month=7, day=4
                const hasBdayId = nameToId["hasBday"];
                expect(hasBdayId, "Expected imported person hasBday to exist").to.exist;
                cy.makePrivateAdminAPICall("GET", `/api/person/${hasBdayId}`, null, 200).then((resp) => {
                    expect(resp.body.BirthMonth).to.eq(7);
                    expect(resp.body.BirthDay).to.eq(4);
                    expect(resp.body.BirthYear).to.eq(2001);
                });

                // noYrBday: 0000-MM-DD format → month=7, day=4, year should be 0 or null (no year stored)
                const noYrBdayId = nameToId["noYrBday"];
                expect(noYrBdayId, "Expected imported person noYrBday to exist").to.exist;
                cy.makePrivateAdminAPICall("GET", `/api/person/${noYrBdayId}`, null, 200).then((resp) => {
                    expect(resp.body.BirthMonth).to.eq(7);
                    expect(resp.body.BirthDay).to.eq(4);
                    expect(resp.body.BirthYear).to.be.oneOf([0, null]);
                });

                // slashBday: M/D/YYYY format → year=2001, month=7, day=4
                const slashBdayId = nameToId["slashBday"];
                expect(slashBdayId, "Expected imported person slashBday to exist").to.exist;
                cy.makePrivateAdminAPICall("GET", `/api/person/${slashBdayId}`, null, 200).then((resp) => {
                    expect(resp.body.BirthMonth).to.eq(7);
                    expect(resp.body.BirthDay).to.eq(4);
                    expect(resp.body.BirthYear).to.eq(2001);
                });

                // slashNoYrBday: M/D format (no year) → month=7, day=4, year should be 0 or null (no year stored)
                const slashNoYrBdayId = nameToId["slashNoYrBday"];
                expect(slashNoYrBdayId, "Expected imported person slashNoYrBday to exist").to.exist;
                cy.makePrivateAdminAPICall("GET", `/api/person/${slashNoYrBdayId}`, null, 200).then((resp) => {
                    expect(resp.body.BirthMonth).to.eq(7);
                    expect(resp.body.BirthDay).to.eq(4);
                    expect(resp.body.BirthYear).to.be.oneOf([0, null]);
                });
            });
        });

        it("Verify CSV Import sets custom fields and properties", () => {
            // Fixture headers: "Highest Degree Received" (person custom c3, seed.sql),
            // "Disabled" (person property pro_ID 1), "Single Parent" (family property pro_ID 2)
            cy.visit("admin/import/csv");
            cy.get("#csvFile").selectFile("cypress/fixtures/test_extension_import.csv", { force: true });
            cy.get("#csv-import-form").submit();
            cy.get("#mapping-card").should("be.visible");

            // Mapping dropdowns must render <optgroup> entries for each extension category
            cy.get("#mapping-tbody").within(() => {
                cy.get("select.mapping-select").first().then(($sel) => {
                    const groups = Array.from($sel[0].querySelectorAll("optgroup")).map((g) => g.label);
                    expect(groups).to.include.members([
                        "Person Custom",
                        "Person Property",
                        "Family Property",
                    ]);
                });
            });

            cy.get("#execute-import").click();
            cy.get("#summary-card").should("be.visible");
            cy.get("#summary-imported").should("not.have.text", "0");

            cy.request("GET", "/api/search/ExtTest").then((response) => {
                expect(response.status).to.eq(200);
                const personsGroup = response.body.find((g) => g.text.startsWith("Persons"));
                expect(personsGroup).to.exist;
                expect(personsGroup.children).to.have.length.at.least(2);

                const byFirstName = {};
                personsGroup.children.forEach((child) => {
                    const match = child.uri.match(/PersonID=(\d+)/);
                    if (match) byFirstName[child.text.split(" ")[0]] = match[1];
                });

                const customAndPropId = byFirstName["customAndProp"];
                expect(customAndPropId, "customAndProp person imported").to.exist;

                // Person property "Disabled" (pro_ID 1) should be assigned to both imported rows
                cy.makePrivateAdminAPICall(
                    "GET",
                    `/api/people/properties/person/${customAndPropId}`,
                    null,
                    200,
                ).then((propResp) => {
                    const props = propResp.body;
                    const disabled = props.find((p) => Number(p.PropertyId ?? p.ProId) === 1);
                    expect(disabled, "Disabled property assigned to customAndProp").to.exist;
                });

                // Custom field "Highest Degree Received" (c3) is rendered on the PersonView page.
                // No dedicated read API exists for person_custom values, so assert via the DOM.
                cy.visit(`PersonView.php?PersonID=${customAndPropId}`);
                cy.contains("PhD in Theology").should("exist");
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
