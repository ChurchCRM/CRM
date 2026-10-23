/// <reference types="cypress" />

describe("Deposit editor: what a treasurer does", () => {
    // Family 3 is used here on purpose: family 1's pledge list is asserted on by the
    // fiscal-year specs, and the payments this spec adds would push its seeded rows off page 1.
    const FAMILY_ID = "3";

    const payment = (depositId, fundId, amount, overrides = {}) => ({
        type: "Payment",
        iMethod: "CASH",
        Date: "2025-10-25",
        FamilyID: FAMILY_ID,
        FYID: 29,
        tScanString: "",
        DepositID: depositId,
        FundSplit: JSON.stringify([{ FundID: String(fundId), Amount: amount, NonDeductible: 0, Comment: "" }]),
        ...overrides,
    });

    const createdDeposits = [];

    const createDeposit = (comment) =>
        cy
            .request("POST", "/api/deposits", {
                depositType: "Bank",
                depositComment: comment,
                depositDate: "2025-10-25",
            })
            .its("body.Id")
            .then((id) => {
                createdDeposits.push(id);
                return id;
            });

    const addPayment = (depositId, fundId, amount) =>
        cy.request("POST", "/api/payments/pledges", payment(depositId, fundId, amount));

    beforeEach(() => cy.setupAdminSession());

    // Payments left on family 3 turn its delete page into a donations list, and a closed
    // deposit refuses to delete its payments, so each deposit is reopened first.
    after(() => {
        cy.setupAdminSession();
        createdDeposits.forEach((id) => {
            cy.request("POST", `/api/deposits/${id}`, {
                depositType: "Bank",
                depositComment: "cleanup",
                depositDate: "2025-10-25",
                depositClosed: false,
            });
            cy.request({ method: "DELETE", url: `/api/deposits/${id}`, failOnStatusCode: false })
                .its("status")
                .should("be.oneOf", [200, 404]);
        });
    });

    it("edits the comment, closes the deposit, and can no longer add or delete payments", () => {
        const comment = `Sunday offering ${Date.now()}`;
        createDeposit("Draft").then((id) => {
            addPayment(id, 1, 100);

            cy.visit(`/finance/deposit/${id}`);
            cy.get("#paymentsTable tbody .row-select").should("have.length", 1);
            cy.get("#Comment").clear().type(comment);
            cy.get("#Closed").check();
            cy.get("#saveDeposit").click();

            cy.get("#Comment", { timeout: 10000 }).should("have.value", comment);
            cy.get("#Closed").should("be.checked");
            cy.contains(".badge", "Closed");
            cy.contains("a.btn", "Add Payment").should("not.exist");
            cy.get("#paymentsTable tbody .row-select").should("not.exist");

            cy.request(`/api/deposits/${id}`).its("body.Closed").should("be.oneOf", [true, 1, "1"]);
        });
    });

    it("removes a mistakenly entered payment and keeps the others", () => {
        createDeposit(`Cleanup ${Date.now()}`).then((id) => {
            addPayment(id, 1, 25);
            addPayment(id, 1, 40);

            cy.visit(`/finance/deposit/${id}`);
            cy.get("#paymentsTable tbody .row-select").should("have.length", 2);
            cy.get("#payment-count").should("have.text", "2");

            cy.get("#paymentsTable tbody .row-select").first().check();
            cy.get("#deleteSelectedRows").should("not.be.disabled").click();
            cy.get(".bootbox .btn-danger").click();

            cy.get("#paymentsTable tbody .row-select", { timeout: 10000 }).should("have.length", 1);
            cy.get("#payment-count").should("have.text", "1");
            cy.request(`/api/deposits/${id}/payments`).its("body").should("have.length", 1);
        });
    });

    it("clicking a fund in the chart shows only that fund's payments, and Clear Filter shows all", () => {
        createDeposit(`Funds ${Date.now()}`).then((id) => {
            addPayment(id, 1, 10);
            addPayment(id, 2, 20);

            cy.visit(`/finance/deposit/${id}`);
            cy.get("#paymentsTable tbody .row-select").should("have.length", 2);

            cy.get("#fund-bar .apexcharts-bar-area").first().click({ force: true });
            cy.get("#paymentsTable tbody .row-select").should("have.length", 1);
            cy.get("#clearFundFilter").should("be.visible").click();
            cy.get("#paymentsTable tbody .row-select").should("have.length", 2);
        });
    });

    it("Generate Report warns on an empty deposit and requests the PDF when there are payments", () => {
        createDeposit(`Report ${Date.now()}`).then((id) => {
            cy.visit(`/finance/deposit/${id}`);
            cy.get("#generateDepositReport").click();
            cy.contains("No payments on this deposit");

            addPayment(id, 1, 15);
            cy.intercept("HEAD", `**/api/deposits/${id}/pdf*`).as("pdf");
            cy.visit(`/finance/deposit/${id}`);
            cy.get("#generateDepositReport").click();
            cy.wait("@pdf").its("response.statusCode").should("eq", 200);
        });
    });

    it("a payment added from the deposit returns to that deposit with the payment listed", () => {
        createDeposit(`Add payment ${Date.now()}`).then((id) => {
            cy.visit(`/finance/deposit/${id}`);
            cy.contains("a.btn", "Add Payment").click();
            cy.url().should("contain", "/finance/pledge/new");
            cy.get(".fund-select").first().select(1);
            cy.get(".fund-amount").first().type("75");
            cy.get("#CheckNo").type(String(Date.now()).slice(-6));
            cy.get("#FamilyID").invoke("val", FAMILY_ID);
            cy.get("#savePledgeBtn").click();

            cy.location("pathname").should("match", new RegExp(`/finance/deposit/${id}$`));
            cy.get("#paymentsTable tbody .row-select").should("have.length", 1);
        });
    });
});

describe("Deposit search: deleting deposits", () => {
    beforeEach(() => cy.setupAdminSession());

    it("a treasurer selects two empty deposits and deletes them", () => {
        const tag = `Delete me ${Date.now()}`;
        const create = (suffix) =>
            cy
                .request("POST", "/api/deposits", {
                    depositType: "Bank",
                    depositComment: `${tag} ${suffix}`,
                    depositDate: "2025-10-25",
                })
                .its("body.Id");

        create("a").then((a) => {
            create("b").then((b) => {
                cy.visit("/finance/deposit/search?fyid=0");
                cy.get(`#depositsTable .row-select[data-deposit-id="${a}"]`).check({ force: true });
                cy.get(`#depositsTable .row-select[data-deposit-id="${b}"]`).check({ force: true });
                cy.get("#btnDeleteSelected").should("not.be.disabled").click();
                cy.get(".bootbox .btn-danger").click();

                cy.get(`#depositsTable .row-select[data-deposit-id="${a}"]`).should("not.exist");
                cy.get(`#depositsTable .row-select[data-deposit-id="${b}"]`).should("not.exist");
                cy.request({ url: `/api/deposits/${a}`, failOnStatusCode: false }).its("status").should("not.eq", 200);
            });
        });
    });
});
