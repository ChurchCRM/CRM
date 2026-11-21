/// <reference types="cypress" />

describe("Deposit editor: what a treasurer does", { defaultCommandTimeout: 30000 }, () => {
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

    beforeEach(() => {
        cy.setupAdminSession();
        cy.intercept("GET", "**/api/deposits/*/payments*").as("loadPayments");
    });

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

    it("retains payment defaults only for the deposit where the payment was saved", () => {
        createDeposit("Payment defaults first deposit").then((firstId) => {
            createDeposit("Payment defaults second deposit").then((secondId) => {
                cy.visit(`/finance/pledge/new?type=Payment&depositId=${secondId}`);
                cy.get("#Date").invoke("val").then((defaultDate) => {
                    cy.get("#Method").should("have.value", "CHECK");
                    cy.visit(`/finance/pledge/new?type=Payment&depositId=${firstId}`);
                    cy.get("#FamilyName-ts-control").should("exist");
                    cy.intercept("GET", "**/api/fiscalyear?date=2025-10-25").as("paymentFiscalYear");
                    cy.intercept("POST", "**/api/payments/pledges").as("savePayment");
                    cy.get("#Date").clear().type("2025-10-25").trigger("change");
                    cy.wait("@paymentFiscalYear").its("response.body.fyId").as("paymentFiscalYearId");
                    cy.get("#Method").select("CASH");
                    cy.get("#FamilyID").invoke("val", FAMILY_ID);
                    cy.get(".fund-select").first().select(1);
                    cy.get(".fund-amount").first().clear().type("25");
                    cy.get("#saveAndAddBtn").click();
                    cy.wait("@savePayment").its("response.statusCode").should("eq", 200);
                    cy.get(".fund-amount").first().should("have.value", "");
                    cy.get("#Date").should("have.value", "2025-10-25");
                    cy.get("@paymentFiscalYearId").then((fyId) => {
                        cy.get("#FYID").should("have.value", String(fyId));
                    });
                    cy.get("#Method").should("have.value", "CASH");

                    cy.visit(`/finance/pledge/new?type=Payment&depositId=${secondId}`);
                    cy.get("#Date").should("have.value", defaultDate);
                    cy.get("#Method").should("have.value", "CHECK");
                    cy.get("#checkNumberGroup").should("be.visible");

                    cy.visit(`/finance/pledge/new?type=Payment&depositId=${firstId}`);
                    cy.get("#Date").should("have.value", "2025-10-25");
                    cy.get("@paymentFiscalYearId").then((fyId) => {
                        cy.get("#FYID").should("have.value", String(fyId));
                    });
                    cy.get("#Method").should("have.value", "CASH");
                    cy.get("#Method").select("CHECK");
                    cy.get("#checkNumberGroup").should("be.visible");
                });
            });
        });
    });

    it("edits the comment, closes the deposit, and can no longer add or delete payments", () => {
        const comment = `Sunday offering ${Date.now()}`;
        createDeposit("Draft").then((id) => {
            addPayment(id, 1, 100);

            cy.visit(`/finance/deposit/${id}`);
            cy.wait("@loadPayments");
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
            cy.wait("@loadPayments");
            cy.get("#paymentsTable tbody .row-select").should("have.length", 2);
            cy.get("#payment-count").should("have.text", "2");

            cy.get("#paymentsTable tbody .row-select").first().check();
            cy.get("#deleteSelectedRows").should("not.be.disabled").click();
            cy.get(".bootbox .btn-danger").click();

            cy.wait("@loadPayments");
            cy.get("#paymentsTable tbody .row-select").should("have.length", 1);
            cy.get("#payment-count").should("have.text", "1");
            cy.request(`/api/deposits/${id}/payments`).its("body").should("have.length", 1);
        });
    });

    it("clicking a fund in the chart shows only that fund's payments, and Clear Filter shows all", () => {
        createDeposit(`Funds ${Date.now()}`).then((id) => {
            addPayment(id, 1, 10);
            addPayment(id, 2, 20);

            cy.visit(`/finance/deposit/${id}`);
            cy.wait("@loadPayments");
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
            cy.wait("@loadPayments");
            cy.get("#generateDepositReport").click();
            cy.contains("No payments on this deposit");

            addPayment(id, 1, 15);
            cy.intercept("HEAD", `**/finance/reports/print-deposit/download*`).as("pdf");
            cy.visit(`/finance/deposit/${id}`);
            cy.wait("@loadPayments");
            cy.get("#generateDepositReport").click();
            cy.wait("@pdf", { requestTimeout: 60000 }).its("response.statusCode").should("eq", 200);
        });
    });

    it("a payment added from the deposit returns to that deposit with the payment listed", () => {
        createDeposit(`Add payment ${Date.now()}`).then((id) => {
            cy.visit(`/finance/deposit/${id}`);
            cy.wait("@loadPayments");
            cy.contains("a.btn", "Add Payment").click();
            cy.url().should("contain", "/finance/pledge/new");
            cy.get("#FamilyName-ts-control").should("exist");
            cy.get(".fund-select").first().select(1);
            cy.get(".fund-amount").first().type("75");
            cy.get("#CheckNo").type(String(Date.now()).slice(-6));
            cy.get("#FamilyID").invoke("val", FAMILY_ID);
            cy.get("#savePledgeBtn").click();

            cy.location("pathname").should("match", new RegExp(`/finance/deposit/${id}$`));
            cy.wait("@loadPayments");
            cy.get("#paymentsTable tbody .row-select").should("have.length", 1);
        });
    });

    it("folds the funds chart away on a phone and shows it on desktop", () => {
        createDeposit("funds collapse").then((id) => {
            addPayment(id, 1, 10);
            cy.viewport(390, 800);
            cy.visit(`/finance/deposit/${id}`);
            cy.wait("@loadPayments");
            cy.get("#fundsBody").should("not.be.visible");
            cy.get("#fundsToggle").click();
            cy.get("#fundsBody").should("be.visible");
            cy.get("#fund-bar svg").invoke("width").should("be.gt", 200);
            cy.viewport(1280, 800);
            cy.get("#fundsToggle").should("not.be.visible");
            cy.get("#fundsBody").should("be.visible");
        });
    });
});

describe("Deposit editor: closing a deposit with failed card payments", () => {
    // Payments created through the API are uncleared (AutCleared = 0), which is
    // exactly what a failed card charge looks like on a deposit slip.
    const FAMILY_ID = "3";

    const payment = (depositId, method, amount) => ({
        type: "Payment",
        iMethod: method,
        Date: "2025-10-25",
        FamilyID: FAMILY_ID,
        FYID: 29,
        tScanString: "",
        DepositID: depositId,
        FundSplit: JSON.stringify([{ FundID: "1", Amount: amount, NonDeductible: 0, Comment: "" }]),
    });

    const createdDeposits = [];

    const createDeposit = (type, comment) =>
        cy
            .request("POST", "/api/deposits", {
                depositType: type,
                depositComment: comment,
                depositDate: "2025-10-25",
            })
            .its("body.Id")
            .then((id) => {
                createdDeposits.push({ id, type });
                return id;
            });

    const closeFromEditor = (id) => {
        cy.visit(`/finance/deposit/${id}`);
        cy.get("#Closed").check();
        cy.get("#saveDeposit").click();
        cy.contains(".badge", "Closed", { timeout: 10000 });
        cy.get("#Closed").should("be.checked");
    };

    beforeEach(() => cy.setupAdminSession());

    // A closed deposit refuses to delete its payments, so each deposit is
    // reopened under its own type first, as in the spec above.
    after(() => {
        cy.setupAdminSession();
        createdDeposits.forEach(({ id, type }) => {
            cy.request("POST", `/api/deposits/${id}`, {
                depositType: type,
                depositComment: "cleanup",
                depositDate: "2025-10-25",
                depositClosed: false,
            });
            cy.request({ method: "DELETE", url: `/api/deposits/${id}`, failOnStatusCode: false })
                .its("status")
                .should("be.oneOf", [200, 404]);
        });
    });

    it("closing a card deposit removes its failed payment", () => {
        createDeposit("CreditCard", `Failed card ${Date.now()}`).then((id) => {
            cy.request("POST", "/api/payments/pledges", payment(id, "CREDITCARD", 60));
            cy.request(`/api/deposits/${id}/payments`).its("body").should("have.length", 1);

            closeFromEditor(id);

            cy.request(`/api/deposits/${id}`).its("body.Closed").should("be.oneOf", [true, 1, "1"]);
            cy.request(`/api/deposits/${id}/payments`).its("body").should("have.length", 0);
        });
    });

    it("closing a card deposit with no payments closes as before", () => {
        createDeposit("CreditCard", `Empty card ${Date.now()}`).then((id) => {
            closeFromEditor(id);

            cy.request(`/api/deposits/${id}`).its("body.Closed").should("be.oneOf", [true, 1, "1"]);
            cy.request(`/api/deposits/${id}/payments`).its("body").should("have.length", 0);
        });
    });

    it("closing a bank deposit keeps its payments", () => {
        createDeposit("Bank", `Bank keep ${Date.now()}`).then((id) => {
            cy.request("POST", "/api/payments/pledges", payment(id, "CASH", 45));

            closeFromEditor(id);

            cy.request(`/api/deposits/${id}`).its("body.Closed").should("be.oneOf", [true, 1, "1"]);
            cy.request(`/api/deposits/${id}/payments`).its("body").should("have.length", 1);
        });
    });
});

describe("Deposit search: deleting deposits", { defaultCommandTimeout: 30000 }, () => {
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
                // Once DataTables initialises, the table pages in ID order
                // and the two new deposits (the highest IDs, of 22) sit on
                // the last page. Filter the table by their unique comment,
                // as a treasurer finding them would.
                cy.get("#depositsTable_wrapper").should("exist");
                cy.get("#depositsTable_wrapper input[type='search']").type(tag);
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
