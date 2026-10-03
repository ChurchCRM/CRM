/// <reference types="cypress" />

describe("Admin People", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    describe("Options editor breadcrumbs", () => {
        it("marks the page title active on the Group Types editor", () => {
            cy.visit("admin/system/options?mode=grptypes");
            cy.get(".breadcrumb .breadcrumb-item.active").should("contain", "Group Types Editor");
        });
    });

    describe("Person Classifications Editor", () => {
        it("loads the page with existing classifications", () => {
            cy.visit("admin/system/options?mode=classes");
            cy.contains("Person Classifications Editor");
            cy.get("#optionsTable tbody tr").should("have.length.greaterThan", 0);
        });

        it("breadcrumbs back through People Settings", () => {
            cy.visit("admin/system/options?mode=classes");
            cy.get(".breadcrumb a[href$='/admin/people']").should("contain", "People Settings");
        });

        it("shows the Inactive column for classifications", () => {
            cy.visit("admin/system/options?mode=classes");
            cy.get("#optionsTable thead").should("contain", "Inactive");
            cy.get(".inactive-toggle").should("have.length.greaterThan", 0);
        });

        it("shows the In Directory column and saves a toggle", () => {
            cy.intercept("POST", "**/admin/api/options/1/*/directory").as("toggleDirectory");
            cy.visit("admin/system/options?mode=classes");
            cy.get("#optionsTable thead").should("contain", "In Directory");
            cy.get(".directory-toggle").should("have.length.greaterThan", 0);

            cy.get(".directory-toggle").first().click();
            cy.wait("@toggleDirectory").its("response.statusCode").should("eq", 200);
            // put it back
            cy.get(".directory-toggle").first().click();
            cy.wait("@toggleDirectory").its("response.statusCode").should("eq", 200);
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
        it("shows the page subtitle once", () => {
            cy.visit("admin/system/options?mode=famroles");
            cy.get("body")
                .invoke("text")
                .then((text) => {
                    expect(text.split("Manage Family Role options").length - 1).to.eq(1);
                });
        });

        it("links back to People Settings from the header", () => {
            cy.visit("admin/system/options?mode=famroles");
            cy.get(".page-header .btn-list a[href$='/admin/people']").should("contain", "People Settings");
        });

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
