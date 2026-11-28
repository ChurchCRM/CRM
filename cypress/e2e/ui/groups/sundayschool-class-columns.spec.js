/// <reference types="cypress" />

// Seed data: group 1 is "Angels class" (Sunday School).
const ANGELS_CLASS_GROUP_ID = 1;
const SETTING = "ui.sundayschool.class.hidden";
const CLASS_URL = `/groups/sundayschool/class/${ANGELS_CLASS_GROUP_ID}`;

const ALL_COLUMNS = ["Name", "Grade", "Age", "Mobile", "Email", "Father", "Mother", "Actions"];

const shouldShowHeaders = (expected) =>
    cy.get("#sundayschool thead th").should((headers) => {
        expect(Array.from(headers).map((h) => h.textContent.trim())).to.deep.equal(expected);
    });

describe("Sunday School class roster columns (#8529)", () => {
    let settingUrl;
    const resetColumns = () => cy.request("POST", settingUrl, { value: "" });

    beforeEach(() => {
        cy.setupStandardSession();
        cy.visit(CLASS_URL);
        cy.window()
            .its("CRM.userId")
            .then((userId) => {
                settingUrl = `/api/user/${userId}/setting/${SETTING}`;
                resetColumns();
            });
        cy.reload();
    });

    afterEach(() => resetColumns());

    it("hides the chosen columns and keeps the choice for the user", () => {
        cy.get("#sundayschool tbody tr", { timeout: 10000 }).should("have.length.greaterThan", 0);
        shouldShowHeaders(ALL_COLUMNS);

        cy.intercept("POST", `**${settingUrl}`).as("saveColumns");
        cy.get("#ss-column-picker [data-bs-toggle='dropdown']").click();
        cy.get("#ss-column-picker .ss-column-toggle").should("have.length", 6);
        cy.get(".ss-column-toggle[value='mobile']").uncheck();
        cy.wait("@saveColumns").its("request.body.value").should("eq", "mobile");
        cy.get(".ss-column-toggle[value='email']").uncheck();
        cy.wait("@saveColumns").its("response.statusCode").should("eq", 200);

        shouldShowHeaders(["Name", "Grade", "Age", "Father", "Mother", "Actions"]);
        cy.get("#sundayschool tbody tr").each(($row) => {
            expect($row.children("td").length).to.eq(6);
        });

        cy.reload();
        shouldShowHeaders(["Name", "Grade", "Age", "Father", "Mother", "Actions"]);
        cy.get(".ss-column-toggle[value='mobile']").should("not.be.checked");
        cy.get(".ss-column-toggle[value='email']").should("not.be.checked");
        cy.get(".ss-column-toggle[value='grade']").should("be.checked");

        cy.get("#ss-column-picker [data-bs-toggle='dropdown']").click();
        cy.get(".ss-column-toggle[value='mobile']").check();
        cy.wait("@saveColumns").its("request.body.value").should("eq", "email");
        shouldShowHeaders(["Name", "Grade", "Age", "Mobile", "Father", "Mother", "Actions"]);
    });

    it("refuses a value that is not a list of roster columns", () => {
        ["name", "actions", "mobile,", "mobile;email"].forEach((value) => {
            cy.request({ method: "POST", url: settingUrl, body: { value }, failOnStatusCode: false })
                .its("status")
                .should("eq", 400);
        });
    });
});
