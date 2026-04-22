describe(
    "CSVImport",
    {
        retries: {
            runMode: 0,
            openMode: 0,
        },
    },
    () => {
        // Every test ends with cy.request()/makePrivateAdminAPICall() to verify
        // imported state, which resets the PHP session on the server. The next
        // test's cached cy.session() cookie then points at a dead server session
        // and cy.visit() lands on the login page — "#csvFile never found". The
        // cy.session() cache (with or without forceLogin) does NOT fix this
        // because it only restores the client cookie, not the server-side PHP
        // session. Bypass cy.session() entirely with a direct form login:
        // clearCookies → POST /session/begin → verify we landed past login.
        const freshAdminLogin = () => {
            const username = Cypress.env("admin.username");
            const password = Cypress.env("admin.password");
            cy.clearCookies();
            // Match setupLoginSession's canonical path: /login resolves to the
            // session/begin form but goes through the front-controller, which
            // is the path the app's session cookie gets minted on.
            cy.visit("/login");
            cy.get("input[name=User]", { timeout: 10000 })
                .should("be.visible")
                .type(username);
            cy.get("input[name=Password]").type(`${password}{enter}`);
            cy.url().should("not.include", "/session/begin");
        };

        beforeEach(() => {
            freshAdminLogin();
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

            // Extension fields should appear as <optgroup>s in the mapping selects
            cy.get("#mapping-tbody select.mapping-select")
                .first()
                .find("optgroup")
                .then(($optgroups) => {
                    const labels = $optgroups.map((_, el) => el.getAttribute("label")).get();
                    expect(labels).to.include("Person Custom");
                    expect(labels).to.include("Person Property");
                    expect(labels).to.include("Family Property");
                });

            // autoMapHeader() must match custom/property column labels case-insensitively —
            // verify the dropdown row for each extension header has the right option pre-selected.
            cy.get('#mapping-tbody select.mapping-select[data-header="Highest Degree Received"]').should(
                ($sel) => expect($sel.val()).to.match(/^pcustom_c\d+$/),
            );
            cy.get('#mapping-tbody select.mapping-select[data-header="Disabled"]').should(
                ($sel) => expect($sel.val()).to.equal("pprop_1"),
            );
            cy.get('#mapping-tbody select.mapping-select[data-header="Single Parent"]').should(
                ($sel) => expect($sel.val()).to.equal("fprop_2"),
            );

            cy.get("#execute-import").click();
            cy.get("#summary-card", { timeout: 20000 }).should("be.visible");
            cy.get("#summary-imported").should("not.have.text", "0");

            cy.request("GET", "/api/search/ExtTest").then((response) => {
                expect(response.status).to.eq(200);
                const personsGroup = response.body.find((g) => g.text.startsWith("Persons"));
                expect(personsGroup, "ExtTest persons found").to.exist;
                expect(personsGroup.children).to.have.length.at.least(2);

                const byFirstName = {};
                personsGroup.children.forEach((child) => {
                    const match = child.uri.match(/PersonID=(\d+)/);
                    if (match) byFirstName[child.text.split(" ")[0]] = match[1];
                });

                const customAndPropId = byFirstName["customAndProp"];
                expect(customAndPropId, "customAndProp person imported").to.exist;

                // Person property "Disabled" (pro_ID 1) — /api/people/properties/person returns
                // objects shaped { id, name, value, allowEdit, allowDelete } (see getProperties()).
                cy.makePrivateAdminAPICall(
                    "GET",
                    `/api/people/properties/person/${customAndPropId}`,
                    null,
                    200,
                ).then((propResp) => {
                    const disabled = propResp.body.find((p) => Number(p.id) === 1);
                    expect(disabled, "Disabled property assigned to customAndProp").to.exist;
                });

                // Family property "Single Parent" (pro_ID 2) is attached to the shared FamilyID 200
                // row. Resolve family via /api/person → Person.FamId, then read family properties.
                cy.makePrivateAdminAPICall("GET", `/api/person/${customAndPropId}`, null, 200).then(
                    (personResp) => {
                        const familyId = personResp.body.FamId;
                        expect(familyId, "imported person attached to a family").to.be.greaterThan(0);
                        cy.makePrivateAdminAPICall(
                            "GET",
                            `/api/people/properties/family/${familyId}`,
                            null,
                            200,
                        ).then((famPropResp) => {
                            const singleParent = famPropResp.body.find((p) => Number(p.id) === 2);
                            expect(singleParent, "Single Parent family property assigned").to.exist;
                        });
                    },
                );
            });
        });

        it("Verify CSV Import auto-maps category-suffixed extension column headers", () => {
            // The /csv/families template writes extension columns as
            // "{name} ({category})" so custom-field/property sources are
            // visible in Excel and collisions across buckets can't happen.
            // The importer must still auto-map the suffixed form back to
            // the same key that the bare-name form resolves to.
            cy.visit("admin/import/csv");
            cy.get("#csvFile").selectFile("cypress/fixtures/test_extension_suffixed_import.csv", { force: true });
            cy.get("#csv-import-form").submit();
            cy.get("#mapping-card").should("be.visible");

            cy.get(
                '#mapping-tbody select.mapping-select[data-header="Highest Degree Received (Person Custom)"]',
            ).should(($sel) => expect($sel.val()).to.match(/^pcustom_c\d+$/));
            cy.get(
                '#mapping-tbody select.mapping-select[data-header="Disabled (Person Property)"]',
            ).should(($sel) => expect($sel.val()).to.equal("pprop_1"));
            cy.get(
                '#mapping-tbody select.mapping-select[data-header="Single Parent (Family Property)"]',
            ).should(($sel) => expect($sel.val()).to.equal("fprop_2"));
        });

        it("Verify CSV Import rejects duplicate column headers with a 400", () => {
            // A user-assembled CSV with two identically named columns would
            // otherwise produce a raw 500 from League\Csv\SyntaxError. The
            // route must surface it as a 400 with the server-provided message
            // — not Uppy's hardcoded "looks like a network error" fallback.
            // Uses a fixture file (not Cypress.Buffer.from inline) because
            // Uppy's allowedFileTypes check rejects in-memory Buffer uploads
            // before they reach the server.
            cy.visit("admin/import/csv");

            cy.get("#csvFile").selectFile("cypress/fixtures/test_duplicate_columns.csv", { force: true });
            cy.get("#csv-import-form").submit();

            cy.get("#statusError", { timeout: 10000 }).should("be.visible");
            cy.get("#errorMessage")
                .should("contain.text", "duplicate")
                .and("contain.text", "Rename");
            // Must NOT advance past the upload step on a rejected file.
            cy.get("#mapping-card").should("have.class", "d-none");
            cy.get("#summary-card").should("have.class", "d-none");
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
