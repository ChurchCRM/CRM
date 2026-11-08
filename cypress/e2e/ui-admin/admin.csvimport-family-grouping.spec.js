describe(
    "CSVImport family grouping",
    {
        retries: { runMode: 0, openMode: 0 },
    },
    () => {
        // Same PHP-session hazard as admin.csvimport.spec.js: bypass cy.session()
        // with a direct form login.
        beforeEach(() => {
            cy.freshAdminFormLogin({ path: "/login", visible: true, timeout: 10000 });
        });

        // Distinctive last name so after() can find and remove everything this
        // spec imported (families take their members with them; the unaffiliated
        // row's person is deleted separately).
        const LAST_NAME = "GroupTest";

        after(() => {
            const personIds = [];
            cy.makePrivateAdminAPICall("GET", `/api/persons/search/${LAST_NAME}`, null, 200).then(
                (resp) => {
                    (resp.body || []).forEach((hit) => {
                        if (hit.objid) personIds.push(hit.objid);
                    });
                },
            );
            cy.then(() => {
                const familyIds = [];
                personIds.forEach((personId) => {
                    cy.makePrivateAdminAPICall("GET", `/api/person/${personId}`, null, [200, 404]).then(
                        (resp) => {
                            if (resp.body?.FamId) familyIds.push(resp.body.FamId);
                        },
                    );
                });
                cy.then(() => {
                    [...new Set(familyIds)].forEach((familyId) => {
                        cy.makePrivateAdminAPICall("DELETE", `/api/family/${familyId}`, null, [
                            200, 404,
                        ]);
                    });
                    personIds.forEach((personId) => {
                        cy.makePrivateAdminAPICall("DELETE", `/api/person/${personId}`, null, [
                            200, 404,
                        ]);
                    });
                });
            });
        });

        function importFixture() {
            cy.visit("admin/import/csv");
            cy.get("#csvFile").selectFile("cypress/fixtures/family_grouping_import.csv", {
                force: true,
            });
            cy.get("#csv-import-form").submit();
            cy.get("#mapping-card").should("be.visible");
            cy.get("#execute-import").click();
            cy.get("#summary-card").should("be.visible");
            cy.get("#summary-imported").should("not.have.text", "0");
        }

        function personByName(first) {
            return cy
                .request("GET", `/api/search/${LAST_NAME}`)
                .then((response) => {
                    const personsGroup = response.body.find((g) =>
                        g.text.startsWith("Persons"),
                    );
                    const hit = personsGroup.children.find((child) =>
                        child.text.startsWith(`${first} `),
                    );
                    const personId = hit.uri.match(/\/people\/view\/(\d+)/)[1];
                    return cy
                        .makePrivateAdminAPICall("GET", `/api/person/${personId}`, null, 200)
                        .then((resp) => resp.body);
                });
        }

        it("groups rows that share a FamilyID into one family, from the rows' name and address", () => {
            importFixture();
            personByName("Alice").then((alice) => {
                personByName("Bob").then((bob) => {
                    // Shared FamilyID 9001 → one family.
                    expect(alice.FamId).to.eq(bob.FamId);
                    cy.makePrivateAdminAPICall(
                        "GET",
                        `/api/family/${alice.FamId}`,
                        null,
                        200,
                    ).then((resp) => {
                        // The family's name and address come from its rows.
                        expect(resp.body.Name).to.contain(LAST_NAME);
                        expect(resp.body.Address1).to.eq("12 Group St");
                    });
                });
            });
        });

        it("keeps different FamilyIDs in separate families", () => {
            importFixture();
            personByName("Alice").then((alice) => {
                personByName("Carol").then((carol) => {
                    // FamilyID 9001 vs 9002 → two families.
                    expect(alice.FamId).to.not.eq(carol.FamId);
                });
            });
        });

        it("gives a row with no FamilyID its own family", () => {
            importFixture();
            personByName("Eve").then((eve) => {
                personByName("Alice").then((alice) => {
                    // No FamilyID → own family, not folded into 9001.
                    expect(eve.FamId).to.not.eq(alice.FamId);
                    expect(eve.FamId).to.not.be.null;
                });
            });
        });
    },
);
