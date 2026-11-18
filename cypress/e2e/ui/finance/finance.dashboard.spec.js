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
        cy.get("#deposit-fyid").select("28");
        cy.location("search").should("include", "fyid=28");
        expectFund(1, 40);
        cy.get(`#payments-by-fund tr[data-fund-id="${funds[0]}"]`).should("not.exist");
        cy.get("#deposit-fyid").select("0");
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

describe("Deposit Tracking on Finance Dashboard", () => {
    describe("Finance-enabled user", () => {
        beforeEach(() => {
            cy.setupFinanceOnlySession();
            cy.request({
                method: "POST",
                url: "/api/deposits/1",
                body: {
                    depositType: "Bank",
                    depositComment: "Updated Test Deposit",
                    depositDate: new Date().toISOString().slice(0, 10),
                    depositClosed: false,
                },
                failOnStatusCode: false,
            });
        });

        it("renders the deposit chart and calls the deposits API with default 90 days", () => {
            cy.intercept("GET", "**/api/deposits/dashboard*").as("depositsApi");

            cy.visit("/finance/");

            cy.get("#depositChartRow").should("exist");
            cy.get("#deposit-lineGraph").should("exist");
            cy.contains("Deposit Tracking").should("be.visible");

            cy.wait("@depositsApi").its("response.statusCode").should("eq", 200);
            cy.get("@depositsApi").its("request.url").should("include", "days=90");

            cy.get("#deposit-lineGraph .apexcharts-canvas").should("exist");
            cy.get("#deposit-lineGraph svg.apexcharts-svg").should("exist");
        });

        it("updates time range when selecting 30 days and 12 months", () => {
            cy.intercept("GET", "**/api/deposits/dashboard?days=30").as("deposits30");
            cy.intercept("GET", "**/api/deposits/dashboard?days=365").as("deposits365");

            cy.visit("/finance/");

            cy.get("#deposit-days").select("30");
            cy.wait("@deposits30").its("response.statusCode").should("eq", 200);

            cy.get("#deposit-days").select("365");
            cy.wait("@deposits365").its("response.statusCode").should("eq", 200);
        });
    });

    describe("User without finance permission", () => {
        beforeEach(() => cy.setupNoFinanceSession());

        it("denies access to finance dashboard and deposit tracking", () => {
            cy.intercept("GET", "**/api/deposits/dashboard*").as("depositsApi");

            cy.visit("/finance/", { failOnStatusCode: false });

            cy.url().should("include", "/v2/access-denied");
            cy.get("#depositChartRow").should("not.exist");
            cy.get("#deposit-lineGraph").should("not.exist");
            cy.get("@depositsApi.all").should("have.length", 0);
        });
    });
});
