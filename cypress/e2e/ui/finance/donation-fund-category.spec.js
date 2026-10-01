/// <reference types="cypress" />

const stamp = Date.now();
const category = `UI Category ${stamp}`;
const renamed = `UI Renamed ${stamp}`;
const fundName = `UI Cat Fund ${stamp}`;
const API = "/finance/api/funds";

// Row-menu handlers bind after the locale bundle loads; DataTables init runs in the same step.
const visitFunds = () => {
    cy.visit("/finance/funds");
    cy.get("#fundsTable_wrapper").should("exist");
};

describe("Donation Fund Categories - funds page", () => {
    let fundId;

    before(() => {
        cy.setupAdminSession();
        cy.makePrivateAdminAPICall("POST", API, { name: fundName, category }, 201).then((resp) => {
            fundId = resp.body.fund.id;
        });
    });

    after(() => {
        cy.setupAdminSession();
        cy.makePrivateAdminAPICall("DELETE", `${API}/${fundId}`, null, [200, 404]);
    });

    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("shows the category column and category inputs", () => {
        visitFunds();
        cy.contains("th", "Category").should("exist");
        cy.get("#newFundCategory").should("be.visible");
        cy.contains("#fundsTable tr", fundName).should("contain", category);
    });

    it("opens Edit with the Active switch matching the fund's status", () => {
        visitFunds();
        cy.contains("#fundsTable tr", fundName).find(".fund-edit-btn").click({ force: true });
        cy.get("#editFundActive").should("be.checked");
    });

    it("pre-fills the category when editing a fund and saves a new one", () => {
        visitFunds();
        cy.contains("#fundsTable tr", fundName).find(".fund-edit-btn").click({ force: true });
        cy.focused().should("have.id", "editFundModal");
        cy.get("#editFundCategory").should("have.value", category).clear().type(`${category} edited`);
        cy.get("#saveFundEdit").click();
        cy.contains("#fundsTable tr", fundName).should("contain", `${category} edited`);
    });

    it("groups the fund under its category on the dashboard and report filter", () => {
        cy.visit("/finance/");
        cy.contains("h3.card-title", "Donation Funds").closest(".card").should("contain", fundName);
        cy.visit("/FinancialReports.php");
        cy.get("#FinancialReportTypes").select("Giving Report");
        cy.get("#FinancialReports").submit();
        cy.get("#fundsList optgroup").should("have.length.at.least", 1);
        cy.contains("#fundsList option", fundName).should("exist");
    });

    it("renames a category from the manage card", () => {
        cy.makePrivateAdminAPICall("PUT", `${API}/${fundId}`, { category }, 200);
        visitFunds();
        cy.get(`.category-rename-btn[data-category="${category}"]`).click();
        cy.get(".bootbox-input").clear().type(renamed);
        cy.get(".bootbox .btn-primary").click();
        cy.contains("#fundsTable tr", fundName).should("contain", renamed);
    });

    it("deleting a category keeps the fund but clears its category", () => {
        visitFunds();
        cy.get(`.category-delete-btn[data-category="${renamed}"]`).click();
        cy.get(".bootbox .btn-danger").click();
        cy.contains("#fundsTable tr", fundName).should("exist").and("not.contain", renamed);
    });
});

describe("Donation Funds - activate / deactivate from the row menu", () => {
    const toggleName = `UI Toggle Fund ${Date.now()}`;
    let toggleId;

    before(() => {
        cy.setupAdminSession();
        cy.makePrivateAdminAPICall("POST", API, { name: toggleName }, 201).then((resp) => {
            toggleId = resp.body.fund.id;
        });
    });

    after(() => {
        cy.setupAdminSession();
        cy.makePrivateAdminAPICall("DELETE", `${API}/${toggleId}`, null, [200, 404]);
    });

    it("deactivates and reactivates a fund without opening Edit", () => {
        cy.setupAdminSession();
        visitFunds();
        cy.contains("#fundsTable tr", toggleName).find(".fund-toggle-active-btn").click({ force: true });
        cy.contains("#fundsTable tr", toggleName).should("contain", "Inactive");
        cy.get("#fundsTable_wrapper").should("exist");

        cy.contains("#fundsTable tr", toggleName).find(".fund-toggle-active-btn").click({ force: true });
        cy.contains("#fundsTable tr", toggleName).should("contain", "Active").and("not.contain", "Inactive");
    });
});
