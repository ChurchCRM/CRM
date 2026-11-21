/// <reference types="cypress" />

describe("Finance Dashboard access", () => {
    it("allows a finance user to open the dashboard and funds page", () => {
        cy.setupFinanceOnlySession();

        cy.visit("/finance/");
        cy.get("h2.page-title").should("contain", "Finance Dashboard");

        cy.visit("/finance/funds");
        cy.contains("Donation Funds");
    });

    it("allows a standard user with finance permission to open the dashboard", () => {
        cy.setupStandardSession();

        cy.visit("/finance/");
        cy.get("h2.page-title").should("contain", "Finance Dashboard");
    });
});

describe("Treasurer reviews payments by fund", () => {
    const funds = [];
    let depositId;
    const fundNames = [`Giving & 'A' ${Date.now()}`, `Missions ${Date.now()}`];

    before(() => {
        cy.setupAdminSession();
        fundNames.forEach((name) => {
            cy.request("POST", "/finance/api/funds", { name, active: true }).then(({ body }) => funds.push(body.fund.id));
        });
        cy.request("POST", "/api/deposits", {
            depositType: "Bank", depositComment: "Dashboard fund breakdown", depositDate: "2025-01-01",
        }).then(({ body }) => {
            depositId = body.Id;
            const add = (date, fyid, type, splits) => cy.request("POST", "/api/payments/pledges", {
                type, iMethod: "CASH", Date: date, FamilyID: "3", FYID: fyid, tScanString: "", DepositID: depositId,
                FundSplit: JSON.stringify(splits.map(([index, amount]) => ({
                    FundID: String(funds[index]), Amount: amount, NonDeductible: 0, Comment: "",
                }))),
            });
            add("2025-01-01", 29, "Payment", [[0, 12.5], [1, 25]]);
            add("2025-12-31", 29, "Payment", [[0, 7.5]]);
            add("2024-12-31", 28, "Payment", [[1, 40]]);
            add("2026-01-01", 30, "Payment", [[0, 60]]);
            add("2025-06-01", 29, "Pledge", [[0, 999]]);
            cy.request("PUT", `/finance/api/funds/${funds[1]}`, { name: fundNames[1], active: false });
        });
    });

    after(() => {
        cy.setupAdminSession();
        if (depositId) cy.request("DELETE", `/api/deposits/${depositId}`);
        funds.forEach((id) => cy.request("DELETE", `/finance/api/funds/${id}`));
    });

    it("compares fund totals across fiscal years and All Time, including inactive funds", () => {
        cy.setupFinanceOnlySession();
        cy.visit("/finance/?fyid=29");
        const expectFund = (index, amount) => {
            cy.window().then((win) => {
                cy.get(`#payments-by-fund tr[data-fund-id="${funds[index]}"]`)
                    .should("contain.text", fundNames[index])
                    .find("td").should("have.text", win.CRM.currency.format(amount));
                cy.get("#payments-by-fund-chart .apexcharts-bar-area").should("exist");
                cy.window().its("financeDashboardConfig.funds").then((rows) => {
                    expect(rows.find((row) => row.fundId === funds[index]).amount).to.eq(amount);
                });
            });
        };
        expectFund(0, 20);
        expectFund(1, 25);
        cy.get("#finance-range").select("fy:28");
        cy.location("search").should("include", "fyid=28");
        expectFund(1, 40);
        cy.get(`#payments-by-fund tr[data-fund-id="${funds[0]}"]`).should("not.exist");
        cy.get("#finance-range").select("all");
        expectFund(0, 80);
        expectFund(1, 65);
        cy.window().then((win) => {
            const total = win.financeDashboardConfig.funds.reduce((sum, fund) => sum + fund.amount, 0);
            cy.get("#payments-by-fund tfoot td").should("have.text", win.CRM.currency.format(total));
        });
    });

    it("shows a clear empty state for a period without payments", () => {
        cy.setupFinanceOnlySession();
        cy.visit("/finance/?fyid=1");
        cy.get("#payments-by-fund").should("contain.text", "No payments found for this period.");
        cy.get("#payments-by-fund-chart").should("not.exist");
    });
});

describe("Treasurer continues the open deposit from the dashboard", () => {
    let depositId;

    before(() => {
        cy.setupAdminSession();
        cy.request("POST", "/api/deposits", {
            depositType: "Bank", depositComment: "Dashboard work card", depositDate: new Date().toISOString().slice(0, 10),
        }).then(({ body }) => {
            depositId = body.Id;
        });
    });

    after(() => {
        cy.setupAdminSession();
        if (depositId) cy.request("DELETE", `/api/deposits/${depositId}`);
    });

    it("shows the open deposit with the next steps and the year-end status", () => {
        cy.setupAdminSession();
        cy.visit(`/finance/deposit/${depositId}`);
        cy.visit("/finance/");
        cy.get("#current-work").within(() => {
            cy.contains(`#${depositId}`);
            cy.contains("a", "Add Payment").should("have.attr", "href").and("include", `depositId=${depositId}`);
            cy.contains("a", "Review and Close Deposit").should("have.attr", "href").and("include", `/finance/deposit/${depositId}`);
            cy.contains("a", "Create Deposit");
            cy.contains("a", "Add Pledge");
        });
        cy.get("#year-end-entry").within(() => {
            cy.contains("a", "Prepare Giving Statements").should("have.attr", "href").and("include", "/finance/reports/year-end?year=");
            cy.contains("a", "Statement Settings").should("have.attr", "href").and("include", "/finance/statements/settings");
        });
    });

    it("lists the latest deposits whatever fiscal year is selected", () => {
        cy.setupAdminSession();
        cy.visit("/finance/?fyid=22");
        cy.contains(".card-title", "Recent Deposits").parents(".card").find(`a[href$='/finance/deposit/${depositId}']`).should("exist");
    });
});
