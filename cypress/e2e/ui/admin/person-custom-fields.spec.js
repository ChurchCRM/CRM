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

// ------------------------------------------------------------------ //
// RowOps up/down CSRF guard paths (PR #9345)
// FamilyCustomFieldsRowOps.php and PersonCustomFieldsRowOps.php now require
// POST + a valid CSRF token for all state-changing actions (up, down, delete)
// as part of the GHSA-v4x7-hgpq-29r6 security fix.  A bare GET request must
// be rejected with 405 Method Not Allowed — not redirect or 500.
// ------------------------------------------------------------------ //

describe("Family Custom Fields Row Operations — prepared-statement paths (PR #9351)", () => {
    beforeEach(() => cy.setupAdminSession());

    it("up action rejects GET with 405 (CSRF guard — GHSA-v4x7-hgpq-29r6)", () => {
        // GHSA-v4x7-hgpq-29r6: state-changing actions require POST + CSRF.
        // A bare GET to Action=up must return 405, not a redirect.
        cy.request({
            method: "GET",
            url: "FamilyCustomFieldsRowOps.php?Action=up&OrderID=2&Field=c1",
            failOnStatusCode: false,
            followRedirect: false,
        }).then((response) => {
            expect(response.status).to.equal(405);
        });
    });

    it("down action rejects GET with 405 (CSRF guard — GHSA-v4x7-hgpq-29r6)", () => {
        cy.request({
            method: "GET",
            url: "FamilyCustomFieldsRowOps.php?Action=down&OrderID=1&Field=c1",
            failOnStatusCode: false,
            followRedirect: false,
        }).then((response) => {
            expect(response.status).to.equal(405);
        });
    });

    it("delete action rejects GET with 405 (CSRF guard — GHSA-v4x7-hgpq-29r6)", () => {
        cy.request({
            method: "GET",
            url: "FamilyCustomFieldsRowOps.php?Action=delete&Field=c1",
            failOnStatusCode: false,
        }).then((response) => {
            expect(response.status).to.equal(405);
        });
    });
});

describe("Person Custom Fields Row Operations — prepared-statement paths (PR #9351)", () => {
    beforeEach(() => cy.setupAdminSession());

    it("up action rejects GET with 405 (CSRF guard — GHSA-v4x7-hgpq-29r6)", () => {
        // GHSA-v4x7-hgpq-29r6: state-changing actions require POST + CSRF.
        // A bare GET to Action=up must return 405, not a redirect.
        cy.request({
            method: "GET",
            url: "PersonCustomFieldsRowOps.php?Action=up&OrderID=2&Field=c1",
            failOnStatusCode: false,
            followRedirect: false,
        }).then((response) => {
            expect(response.status).to.equal(405);
        });
    });

    it("down action rejects GET with 405 (CSRF guard — GHSA-v4x7-hgpq-29r6)", () => {
        cy.request({
            method: "GET",
            url: "PersonCustomFieldsRowOps.php?Action=down&OrderID=1&Field=c1",
            failOnStatusCode: false,
            followRedirect: false,
        }).then((response) => {
            expect(response.status).to.equal(405);
        });
    });

    it("delete action rejects GET with 405 (CSRF guard — GHSA-v4x7-hgpq-29r6)", () => {
        cy.request({
            method: "GET",
            url: "PersonCustomFieldsRowOps.php?Action=delete&Field=c1",
            failOnStatusCode: false,
        }).then((response) => {
            expect(response.status).to.equal(405);
        });
    });
});
