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

describe("Deposit chart shows one line per fund (#11471)", () => {
    const funds = [];
    const depositIds = [];
    const fundNames = [`Chart Fund A ${Date.now()}`, `Chart Fund B ${Date.now()}`];
    const today = new Date().toISOString().slice(0, 10);
    const threeDaysAgo = new Date(Date.now() - 3 * 86400000).toISOString().slice(0, 10);
    // Seeded fiscal years are calendar years: 2025 is FY 29, 2026 is FY 30.
    const fyid = new Date().getFullYear() - 1996;

    before(() => {
        cy.setupAdminSession();
        fundNames.forEach((name) => {
            cy.request("POST", "/finance/api/funds", { name, active: true }).then(({ body }) => funds.push(body.fund.id));
        });
        // Two deposits on different days: today holds A $10 + B $20, three days ago holds A $5.
        const addDeposit = (date, splits) => cy.request("POST", "/api/deposits", {
            depositType: "Bank", depositComment: "Per-fund chart", depositDate: date,
        }).then(({ body }) => {
            depositIds.push(body.Id);
            cy.request("POST", "/api/payments/pledges", {
                type: "Payment", iMethod: "CASH", Date: date, FamilyID: "3", FYID: fyid, tScanString: "", DepositID: body.Id,
                FundSplit: JSON.stringify(splits.map(([index, amount]) => ({
                    FundID: String(funds[index]), Amount: amount, NonDeductible: 0, Comment: "",
                }))),
            });
        });
        addDeposit(today, [[0, 10], [1, 20]]);
        addDeposit(threeDaysAgo, [[0, 5]]);
    });

    after(() => {
        cy.setupAdminSession();
        depositIds.forEach((id) => cy.request("DELETE", `/api/deposits/${id}`));
        funds.forEach((id) => cy.request("DELETE", `/finance/api/funds/${id}`));
    });

    it("returns one daily series per fund and draws both lines", () => {
        cy.setupFinanceOnlySession();
        cy.intercept("GET", "**/api/deposits/dashboard-by-fund*").as("byFund");
        cy.visit("/finance/?days=90");
        cy.wait("@byFund").then(({ response }) => {
            expect(response.statusCode).to.eq(200);
            expect(response.body.dates).to.deep.eq([threeDaysAgo, today]);
            const fundA = response.body.funds.find((fund) => fund.name === fundNames[0]);
            const fundB = response.body.funds.find((fund) => fund.name === fundNames[1]);
            expect(fundA.data).to.deep.eq([5, 10]);
            expect(fundB.data).to.deep.eq([0, 20]);
        });
        cy.get("#deposit-lineGraph .apexcharts-canvas").should("exist");
        cy.contains("#deposit-lineGraph .apexcharts-legend-text", fundNames[0]).should("exist");
        cy.contains("#deposit-lineGraph .apexcharts-legend-text", fundNames[1]).should("exist");
    });
});
