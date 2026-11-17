/// <reference types="cypress" />

describe("Person Custom Fields", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should load Custom Person Fields Editor without errors", () => {
        cy.visit("PersonCustomFieldsEditor.php");
        cy.contains("Custom Person Fields Editor").should("exist");
        cy.get("body").should("not.contain", "Fatal error");
        cy.get("body").should("not.contain", "TypeError");
        cy.get("body").should("not.contain", "BadMethodCallException");
    });

    it("should save existing person custom fields without crashing", () => {
        cy.visit("PersonCustomFieldsEditor.php");

        // Only test save if fields exist (Save Changes button is present)
        cy.get("body").then(($body) => {
            if ($body.find('button[name="SaveChanges"]').length > 0) {
                cy.get('button[name="SaveChanges"]').click();
                // After save, page reloads — verify no fatal errors
                cy.get("body").should("not.contain", "Fatal error");
                cy.get("body").should("not.contain", "BadMethodCallException");
                cy.contains("Custom Person Fields Editor").should("exist");
            }
        });
    });
});

describe("Family Custom Fields", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should load Custom Family Fields Editor without errors", () => {
        cy.visit("FamilyCustomFieldsEditor.php");
        cy.contains("Custom Family Fields Editor").should("exist");
        cy.get("body").should("not.contain", "Fatal error");
        cy.get("body").should("not.contain", "TypeError");
        cy.get("body").should("not.contain", "BadMethodCallException");
    });

    it("should save existing family custom fields without crashing", () => {
        cy.visit("FamilyCustomFieldsEditor.php");

        // Only test save if fields exist (Save Changes button is present)
        cy.get("body").then(($body) => {
            if ($body.find('button[name="SaveChanges"]').length > 0) {
                cy.get('button[name="SaveChanges"]').click();
                // After save, page reloads — verify no fatal errors
                cy.get("body").should("not.contain", "Fatal error");
                cy.get("body").should("not.contain", "BadMethodCallException");
                cy.contains("Custom Family Fields Editor").should("exist");
            }
        });
    });
});

// ------------------------------------------------------------------ //
// Delete button CSP regression — gh #8520
// Verifies that the Delete button on custom field rows:
//   1. Uses data-* attributes (not inline onclick) for CSP compliance
//   2. Triggers the bootbox confirmation dialog via event delegation
//   3. Cancel leaves the field intact; Confirm deletes it
// ------------------------------------------------------------------ //

describe("Person Custom Fields — Delete button (CSP regression #8520)", () => {
    const fieldName = "Cypress Person Delete Test";

    before(() => {
        cy.setupAdminSession();
        // Create a test field via UI form so it exists for the tests below
        cy.visit("PersonCustomFieldsEditor.php");
        cy.get("select#newFieldType").select("1");
        cy.get("input#newFieldName").clear().type(fieldName);
        cy.get('button[name="AddField"]').click();
        cy.get(`input[value="${fieldName}"]`).should("exist");
    });

    beforeEach(() => {
        cy.setupAdminSession();
        cy.visit("PersonCustomFieldsEditor.php");
    });

    it("Delete button has data-field-name/data-field-id attrs and no onclick", () => {
        cy.get(`.js-delete-field[data-field-name="${fieldName}"]`).then(
            ($btn) => {
                expect($btn).to.have.attr("data-field-id");
                expect($btn).to.not.have.attr("onclick");
            },
        );
    });

    it("clicking Delete opens bootbox; Cancel leaves field intact", () => {
        // Open the dropdown
        cy.get(`.js-delete-field[data-field-name="${fieldName}"]`)
            .closest(".dropdown")
            .find("[data-bs-toggle='dropdown']")
            .click();

        cy.get(`.js-delete-field[data-field-name="${fieldName}"]`).click();

        cy.get(".bootbox").should("be.visible");
        cy.get(".bootbox .btn-secondary").click({ force: true });

        // Field still present after cancel
        cy.get(`input[value="${fieldName}"]`).should("exist");
    });

    it("confirming Delete removes the field and shows success notification", () => {
        cy.get(`.js-delete-field[data-field-name="${fieldName}"]`)
            .closest(".dropdown")
            .find("[data-bs-toggle='dropdown']")
            .click();

        cy.get(`.js-delete-field[data-field-name="${fieldName}"]`).click();

        cy.get(".bootbox-accept").should("be.visible").click();

        cy.url().should("include", "deleted=1");
        cy.contains("Field deleted successfully").should("be.visible");
        cy.get(`input[value="${fieldName}"]`).should("not.exist");
    });
});

describe("Family Custom Fields — Delete button (CSP regression #8520)", () => {
    const fieldName = "Cypress Family Delete Test";

    before(() => {
        cy.setupAdminSession();
        cy.visit("FamilyCustomFieldsEditor.php");
        cy.get("select#newFieldType").select("1");
        cy.get("input#newFieldName").clear().type(fieldName);
        cy.get('button[name="AddField"]').click();
        cy.get(`input[value="${fieldName}"]`).should("exist");
    });

    beforeEach(() => {
        cy.setupAdminSession();
        cy.visit("FamilyCustomFieldsEditor.php");
    });

    it("Delete button has data-field-name/data-field-id attrs and no onclick", () => {
        cy.get(`.js-delete-field[data-field-name="${fieldName}"]`).then(
            ($btn) => {
                expect($btn).to.have.attr("data-field-id");
                expect($btn).to.not.have.attr("onclick");
            },
        );
    });

    it("clicking Delete opens bootbox; Cancel leaves field intact", () => {
        cy.get(`.js-delete-field[data-field-name="${fieldName}"]`)
            .closest(".dropdown")
            .find("[data-bs-toggle='dropdown']")
            .click();

        cy.get(`.js-delete-field[data-field-name="${fieldName}"]`).click();

        cy.get(".bootbox").should("be.visible");
        cy.get(".bootbox .btn-secondary").click({ force: true });

        cy.get(`input[value="${fieldName}"]`).should("exist");
    });

    it("confirming Delete removes the field and shows success notification", () => {
        cy.get(`.js-delete-field[data-field-name="${fieldName}"]`)
            .closest(".dropdown")
            .find("[data-bs-toggle='dropdown']")
            .click();

        cy.get(`.js-delete-field[data-field-name="${fieldName}"]`).click();

        cy.get(".bootbox-accept").should("be.visible").click();

        cy.url().should("include", "deleted=1");
        cy.contains("Field deleted successfully").should("be.visible");
        cy.get(`input[value="${fieldName}"]`).should("not.exist");
    });
});

[
    { recordType: "person", page: "/PersonCustomFieldsEditor.php", fieldName: "Cypress Person Reorder Test" },
    { recordType: "family", page: "/FamilyCustomFieldsEditor.php", fieldName: "Cypress Family Reorder Test" },
].forEach(({ recordType, page, fieldName }) => {
    describe(`${recordType} custom fields — Move up / Move down`, () => {
        const fieldRow = () => cy.get(`input[value="${fieldName}"]`).closest("tr");

        const moveField = (direction) => {
            cy.intercept("PUT", `**/admin/api/system/custom-fields/${recordType}/*/order`).as("moveField");
            fieldRow().find("[data-bs-toggle='dropdown']").click();
            fieldRow().find(`.js-reorder-field[data-direction="${direction}"]`).click();
            cy.wait("@moveField").its("response.statusCode").should("eq", 200);
        };

        before(() => {
            cy.setupAdminSession();
            cy.visit(page);
            cy.get("select#newFieldType").select("1");
            cy.get("input#newFieldName").clear().type(fieldName);
            cy.get('button[name="AddField"]').click();
            cy.get(`input[value="${fieldName}"]`).should("exist");
        });

        beforeEach(() => {
            cy.setupAdminSession();
            cy.visit(page);
        });

        after(() => {
            cy.setupAdminSession();
            cy.visit(page);
            fieldRow()
                .find(".js-delete-field")
                .invoke("attr", "data-field-id")
                .then((fieldId) => {
                    cy.makePrivateAdminAPICall("DELETE", `/admin/api/system/custom-fields/${recordType}/${fieldId}`, null, 200);
                });
        });

        it("moves the field one row up, then back down", () => {
            cy.get(`input[value="${fieldName}"]`)
                .invoke("attr", "name")
                .then((inputName) => {
                    const row = Number.parseInt(inputName, 10);

                    moveField("up");
                    cy.get(`input[value="${fieldName}"]`).should("have.attr", "name", `${row - 1}name`);

                    moveField("down");
                    cy.get(`input[value="${fieldName}"]`).should("have.attr", "name", `${row}name`);
                });
        });
    });
});
