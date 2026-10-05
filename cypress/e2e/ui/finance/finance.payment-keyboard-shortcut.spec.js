/// <reference types="cypress" />

describe("Payment editor Ctrl+Enter shortcut (#8942)", () => {
    beforeEach(() => {
        cy.setupAdminSession();
        cy.intercept("POST", "**/api/payments/pledges").as("submitPayment");

        cy.visit("/finance/deposit/search");
        cy.get("[data-bs-target='#newDepositModal']").click();
        cy.get("#depositComment").type(`Shortcut deposit ${Date.now()}`);
        cy.get("#addNewDeposit").click();
        cy.location("pathname").should("include", "DepositSlipEditor.php");
        cy.location("search").then((search) => {
            cy.wrap(new URLSearchParams(search).get("DepositSlipID")).as("depositId");
        });

        cy.get(".btn-success").click();
        cy.location("pathname").should("include", "/finance/pledge/new");
        cy.get(".fund-amount").first().should("be.visible");
    });

    afterEach(() => {
        cy.get("@depositId").then((depositId) => {
            cy.makePrivateAdminAPICall("DELETE", `/api/deposits/${depositId}`, {}, [200, 404]);
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

    it("shows the shortcut hint on a desktop browser", () => {
        cy.get("#saveShortcutHint").should("be.visible").and("contain", "Enter");
    });

    it("Ctrl+Enter saves and opens a fresh form on the same deposit", () => {
        fillCashPayment("123");
        cy.get(".fund-amount").first().type("{ctrl}{enter}");

        cy.wait("@submitPayment").its("response.statusCode").should("eq", 200);
        cy.get("@depositId").then((depositId) => {
            cy.location("search").should("include", `depositId=${depositId}`);
        });
        cy.location("pathname").should("include", "/finance/pledge/new");
        cy.get(".fund-amount").first().should("have.value", "");
        cy.get("#FamilyName-ts-control").should("have.focus");
    });

    it("Cmd+Enter pressed twice posts the payment once", () => {
        fillCashPayment("45");
        cy.get(".fund-amount").first().type("{meta}{enter}{enter}");

        cy.wait("@submitPayment");
        cy.get("#saveAndAddBtn").should("be.disabled");
        cy.get("@submitPayment.all").should("have.length", 1);
    });

    it("does nothing on plain Enter", () => {
        fillCashPayment("67");
        cy.get(".fund-amount").first().type("{enter}");

        cy.get("#saveAndAddBtn").should("not.be.disabled");
        cy.get("@submitPayment.all").should("have.length", 0);
    });
});
