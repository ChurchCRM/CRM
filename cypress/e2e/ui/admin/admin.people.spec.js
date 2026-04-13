/// <reference types="cypress" />

describe("Admin People", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    describe("Person Classifications Editor", () => {
        it("loads the page with existing classifications", () => {
            cy.visit("admin/system/options?mode=classes");
            cy.contains("Person Classifications Editor");
            cy.get("#optionsTable tbody tr").should("have.length.greaterThan", 0);
        });

        it("shows the Inactive column for classifications", () => {
            cy.visit("admin/system/options?mode=classes");
            cy.get("#optionsTable thead").should("contain", "Inactive");
            cy.get(".inactive-toggle").should("have.length.greaterThan", 0);
        });

        it("displays existing classification names (Member)", () => {
            cy.visit("admin/system/options?mode=classes");
            // Names render as input values, not text content
            cy.get('#optionsTable tbody input.option-name-input[value="Member"]').should("exist");
        });

        it("can add a new classification", () => {
            const newName = "CypressTestClass_" + Date.now();
            cy.visit("admin/system/options?mode=classes");

            cy.get("#newOptionName").type(newName);
            cy.get("#addOptionBtn").click();

            // Page reloads with the new option (rendered as input value)
            cy.get(`#optionsTable tbody input.option-name-input[value="${newName}"]`, { timeout: 10000 }).should("exist");
        });

        it("rejects empty name on add", () => {
            cy.visit("admin/system/options?mode=classes");
            cy.get("#addOptionBtn").click();
            cy.get("#newOptionError").should("be.visible");
        });

        it("can rename a classification via Save Changes", () => {
            const originalName = "CypressRenameSource_" + Date.now();
            const renamedName = "CypressRenamed_" + Date.now();

            // Create a dedicated option so we don't mutate seeded data
            cy.visit("admin/system/options?mode=classes");
            cy.get("#newOptionName").type(originalName);
            cy.get("#addOptionBtn").click();
            cy.get(`#optionsTable tbody input.option-name-input[value="${originalName}"]`, { timeout: 10000 }).should("exist");

            cy.get(`#optionsTable tbody input.option-name-input[value="${originalName}"]`)
                .clear().type(renamedName);
            cy.get("#saveChangesBtn").click();
            cy.get(`#optionsTable tbody input.option-name-input[value="${renamedName}"]`, { timeout: 10000 }).should("exist");
        });
    });

    describe("Family Roles Editor", () => {
        it("loads the page with existing roles", () => {
            cy.visit("admin/system/options?mode=famroles");
            cy.contains("Family Roles Editor");
            cy.get("#optionsTable tbody tr").should("have.length.greaterThan", 0);
        });

        it("shows Head of Household and Spouse roles", () => {
            cy.visit("admin/system/options?mode=famroles");
            cy.get('#optionsTable tbody input.option-name-input[value="Head of Household"]').should("exist");
            cy.get('#optionsTable tbody input.option-name-input[value="Spouse"]').should("exist");
        });

        it("does NOT show the Inactive column", () => {
            cy.visit("admin/system/options?mode=famroles");
            cy.get("#optionsTable thead").should("not.contain", "Inactive");
        });

        it("can add a new family role", () => {
            const newRole = "CypressTestRole_" + Date.now();
            cy.visit("admin/system/options?mode=famroles");

            cy.get("#newOptionName").type(newRole);
            cy.get("#addOptionBtn").click();

            cy.get(`#optionsTable tbody input.option-name-input[value="${newRole}"]`, { timeout: 10000 }).should("exist");
        });
    });

    describe("OptionManager Admin API", () => {
        const listId = 1; // Classifications list

        it("GET returns options array with expected fields", () => {
            cy.makePrivateAdminAPICall("GET", `/admin/api/options/${listId}`, null, 200).then((resp) => {
                expect(resp.body).to.be.an("array");
                expect(resp.body.length).to.be.greaterThan(0);
                expect(resp.body[0]).to.have.property("optionId");
                expect(resp.body[0]).to.have.property("optionName");
                expect(resp.body[0]).to.have.property("optionSequence");
            });
        });

        it("POST creates a new option and returns it", () => {
            const name = "APITestOption_" + Date.now();
            cy.makePrivateAdminAPICall("POST", `/admin/api/options/${listId}`, { name }, 200).then((resp) => {
                expect(resp.body).to.have.property("optionId");
                expect(resp.body.optionName).to.eq(name);
                expect(resp.body.optionSequence).to.be.a("number");
            });
        });

        it("POST rejects duplicate names with 400", () => {
            cy.makePrivateAdminAPICall("POST", `/admin/api/options/${listId}`, { name: "Member" }, 400);
        });

        it("POST rejects empty name with 400", () => {
            cy.makePrivateAdminAPICall("POST", `/admin/api/options/${listId}`, { name: "" }, 400);
        });

        it("PATCH renames an option", () => {
            const name = "PatchMe_" + Date.now();
            cy.makePrivateAdminAPICall("POST", `/admin/api/options/${listId}`, { name }, 200).then((resp) => {
                const optionId = resp.body.optionId;
                const newName = "Patched_" + Date.now();
                cy.makePrivateAdminAPICall("PATCH", `/admin/api/options/${listId}/${optionId}`, { name: newName }, 200).then((patchResp) => {
                    expect(patchResp.body.optionName).to.eq(newName);
                });
            });
        });

        it("DELETE removes an option and resequences", () => {
            const name = "DeleteMe_" + Date.now();
            cy.makePrivateAdminAPICall("POST", `/admin/api/options/${listId}`, { name }, 200).then((resp) => {
                const optionId = resp.body.optionId;
                cy.makePrivateAdminAPICall("DELETE", `/admin/api/options/${listId}/${optionId}`, null, 200);

                // Verify it no longer exists
                cy.makePrivateAdminAPICall("GET", `/admin/api/options/${listId}`, null, 200).then((getResp) => {
                    const found = getResp.body.find((o) => o.optionId === optionId);
                    expect(found).to.be.undefined;
                });
            });
        });

        it("reorder moves an option up", () => {
            cy.makePrivateAdminAPICall("GET", `/admin/api/options/${listId}`, null, 200).then((resp) => {
                if (resp.body.length >= 2) {
                    const second = resp.body[1];
                    const originalSeq = second.optionSequence;
                    cy.makePrivateAdminAPICall("POST", `/admin/api/options/${listId}/${second.optionId}/reorder`, { direction: "up" }, 200);

                    // Verify the sequence changed
                    cy.makePrivateAdminAPICall("GET", `/admin/api/options/${listId}`, null, 200).then((afterResp) => {
                        const movedOption = afterResp.body.find((o) => o.optionId === second.optionId);
                        expect(movedOption.optionSequence).to.eq(originalSeq - 1);
                    });
                }
            });
        });
    });

    it("Custom Family Fields Editor", () => {
        cy.visit("FamilyCustomFieldsEditor.php");
        cy.contains("Custom Family Fields Editor");
    });

    it("Custom Person Fields Editor", () => {
        cy.visit("PersonCustomFieldsEditor.php");
        cy.contains("Custom Person Fields Editor");
    });

    it("Volunteer Opportunity Editor", () => {
        cy.visit("VolunteerOpportunityEditor.php");
        cy.contains("Volunteer Opportunity Editor");
    });

    it("Family Property List", () => {
        cy.visit("PropertyList.php?Type=f");
        cy.contains("Family Property List");
        cy.get('a[href*="PropertyEditor.php"]').first().click();
        cy.url().should("contain", "PropertyEditor.php");
        cy.get('select[name="Class"]').select("2");
        cy.get('input[name="Name"]').type("Test");
        cy.get('textarea[name="Description"]').type("Who");
        cy.get('input[name="Prompt"]').type("What do you want");
        cy.get('button[name="Submit"]').click();
        cy.url().should("contain", "PropertyList.php");
    });

    it("Person Property List", () => {
        cy.visit("PropertyList.php?Type=p");
        cy.contains("Person Property List");
        cy.get('a[href*="PropertyEditor.php"]').first().click();
        cy.url().should("contain", "PropertyEditor.php");
        cy.get('select[name="Class"]').select("1");
        cy.get('input[name="Name"]').type("Test");
        cy.get('textarea[name="Description"]').type("Who");
        cy.get('input[name="Prompt"]').type("What do you want");
        cy.get('button[name="Submit"]').click();
        cy.url().should("contain", "PropertyList.php");
    });
});
