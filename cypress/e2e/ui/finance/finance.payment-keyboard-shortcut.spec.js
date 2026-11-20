/// <reference types="cypress" />

describe("Payment editor Ctrl+Enter shortcut (#8942)", { defaultCommandTimeout: 30000 }, () => {
    beforeEach(() => {
        cy.setupAdminSession();
        cy.intercept("POST", "**/api/payments/pledges").as("submitPayment");
        cy.intercept("POST", "**/api/deposits").as("createDeposit");

        cy.visit("/finance/deposit/search");
        cy.get("[data-bs-target='#newDepositModal']").click();
        cy.get("#depositComment").type(`Shortcut deposit ${Date.now()}`);
        cy.get("#addNewDeposit").click();
        cy.wait("@createDeposit").its("response.statusCode").should("eq", 200);
        cy.location("pathname").should("match", /\/finance\/deposit\/\d+$/);
        cy.location("pathname").then((pathname) => {
            cy.wrap(pathname.split("/").pop()).as("depositId");
        });

        cy.get(".btn-success").click();
        cy.location("pathname").should("include", "/finance/pledge/new");
        cy.get("#FamilyName-ts-control").should("exist");
        cy.get(".fund-amount").first().should("be.visible");
    });

    afterEach(() => {
        cy.get("@depositId").then((depositId) => {
            cy.request({ method: "DELETE", url: `/api/deposits/${depositId}`, failOnStatusCode: false })
                .its("status").should("be.oneOf", [200, 404]);
        });
    });

    function fillCashPayment(amount) {
        cy.get("#Method").select("CASH");
        cy.get("#FamilyID").invoke("val", "1");
        cy.get(".fund-select").first().select(1);
        cy.get(".fund-amount").first().clear().type(amount);
    }

    it("focuses the family field on a new payment", () => {
        cy.get("#FamilyName-ts-control").should("have.focus");
    });

    it("Ctrl+Enter saves and opens a fresh form on the same deposit", () => {
        const paymentDate = "2025-02-12";
        cy.intercept("GET", "**/api/fiscalyear?date=" + paymentDate).as("resolveFiscalYear");
        fillCashPayment("123");
        cy.get("#Date").clear().type(paymentDate).trigger("change");
        cy.wait("@resolveFiscalYear").its("response.statusCode").should("eq", 200);
        cy.get(".fund-amount").first().type("{ctrl}{enter}");

        cy.wait("@submitPayment").its("response.statusCode").should("eq", 200);
        cy.get("@depositId").then((depositId) => {
            cy.location("search").should("include", `depositId=${depositId}`);
        });
        cy.location("pathname").should("include", "/finance/pledge/new");
        cy.get(".fund-amount").first().should("have.value", "");
        cy.wait("@resolveFiscalYear").its("response.body.fyId").then((fyId) => {
            cy.get("#FYID").should("have.value", String(fyId));
        });
        cy.get("#Date").should("have.value", paymentDate);
        cy.get("#Method").should("have.value", "CASH");
        cy.get("#checkNumberGroup").should("not.be.visible");
        cy.get("#FamilyName-ts-control").should("have.focus");
    });

    it("Cmd+Enter pressed twice posts the payment once", () => {
        fillCashPayment("45");
        cy.get(".fund-amount").first().type("{meta}{enter}{enter}");

        cy.wait("@submitPayment");
        cy.get("#saveAndAddBtn").should("be.disabled");
        cy.get("@submitPayment.all").should("have.length", 1);
    });

    it("Ctrl+Enter waits for the restored date's fiscal year before saving", () => {
        let originalDate;
        let originalFiscalYear;
        cy.get("#Date").invoke("val").then((value) => { originalDate = value; });
        cy.get("#FYID").invoke("val").then((value) => { originalFiscalYear = value; });
        let releaseLookup;
        const lookupReady = new Promise((resolve) => { releaseLookup = resolve; });
        cy.intercept("GET", "**/api/fiscalyear?date=2025-02-12", (req) =>
            lookupReady.then(() => req.reply({ body: { fyId: 29 } })),
        ).as("restoredFiscalYear");
        cy.get("@depositId").then((depositId) => {
            cy.visit(`/finance/pledge/new?type=Payment&depositId=${depositId}`, {
                onBeforeLoad(win) {
                    win.sessionStorage.setItem(`churchcrm_deposit_payment_defaults_${depositId}`,
                        JSON.stringify({ date: "2025-02-12", method: "CASH" }));
                },
            });
        });
        cy.get("#FamilyName-ts-control").should("exist");
        cy.get("#Date").should(($date) => { expect($date.val()).to.eq(originalDate); });
        cy.get("#FYID").should(($fy) => { expect($fy.val()).to.eq(originalFiscalYear); });
        fillCashPayment("23");
        cy.get(".fund-amount").first().type("{ctrl}{enter}");
        cy.get("#saveAndAddBtn").should("be.disabled");
        cy.get("@submitPayment.all").should("have.length", 0);
        cy.then(() => releaseLookup());
        cy.wait("@submitPayment").then(({ request, response }) => {
            expect(response.statusCode).to.eq(200);
            expect(request.body.Date).to.eq("2025-02-12");
            expect(request.body.FYID).to.eq(29);
        });
        cy.location("pathname").should("include", "/finance/pledge/new");
        cy.get(".fund-amount").first().should("have.value", "");
    });

    it("Save preserves the server date and fiscal year when restoration fails", () => {
        let originalDate;
        let originalFiscalYear;
        cy.get("#Date").invoke("val").then((value) => { originalDate = value; });
        cy.get("#FYID").invoke("val").then((value) => { originalFiscalYear = value; });
        cy.intercept("GET", "**/api/fiscalyear?date=2025-02-12", { statusCode: 503 }).as("failedFiscalYear");
        cy.get("@depositId").then((depositId) => {
            cy.visit(`/finance/pledge/new?type=Payment&depositId=${depositId}`, {
                onBeforeLoad(win) {
                    win.sessionStorage.setItem(`churchcrm_deposit_payment_defaults_${depositId}`,
                        JSON.stringify({ date: "2025-02-12", method: "CASH" }));
                },
            });
        });
        cy.get("#FamilyName-ts-control").should("exist");
        cy.wait("@failedFiscalYear");
        cy.get("#Date").should(($date) => { expect($date.val()).to.eq(originalDate); });
        cy.get("#FYID").should(($fy) => { expect($fy.val()).to.eq(originalFiscalYear); });
        cy.get("@submitPayment.all").should("have.length", 0);
        fillCashPayment("24");
        cy.get("#savePledgeBtn").click();
        cy.wait("@submitPayment").then(({ request, response }) => {
            expect(response.statusCode).to.eq(200);
            expect(request.body.Date).to.eq(originalDate);
            expect(request.body.FYID).to.eq(Number(originalFiscalYear));
        });
        cy.location("pathname").should("match", /\/finance\/pledge\/[^/]+$/);
    });

    it("does nothing on plain Enter", () => {
        fillCashPayment("67");
        cy.get(".fund-amount").first().type("{enter}");

        cy.get("#saveAndAddBtn").should("not.be.disabled");
        cy.get("@submitPayment.all").should("have.length", 0);
    });
});
