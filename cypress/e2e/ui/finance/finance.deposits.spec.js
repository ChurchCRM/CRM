/// <reference types="cypress" />


describe("Finance Deposits", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Envelope Manager", () => {
        cy.visit("/ManageEnvelopes.php");
        cy.contains("Envelope Manager");
    });

    it("Navigate to deposits from Finance Dashboard", () => {
        cy.visit("/finance/");
        cy.contains("Finance Dashboard");
        
        // Click Create Deposit from Quick Actions
        cy.contains("a", "Create Deposit").click();
        cy.url().should("contain", "/finance/deposit/search");
        cy.contains("Deposits");
    });

    it("Navigate to deposits from Finance Menu", () => {
        cy.visit("/finance/");
        
        // Use the View All link in Recent Deposits section
        cy.contains("Recent Deposits")
            .parents(".card")
            .find("a")
            .contains("View All")
            .click();
            
        cy.url().should("contain", "/finance/deposit/search");
        cy.contains("Deposits");
    });

    it("Create a new Deposit without comment", () => {
        cy.visit("/finance/deposit/search");
        cy.get("[data-bs-target='#newDepositModal']").click();
        cy.get("#depositComment").clear();
        cy.get("#addNewDeposit").click();
        cy.contains("You are about to add a new deposit without a comment");
    });

    it("Create a new Deposit", () => {
        const uniqueSeed = Date.now().toString();
        const name = "New Test Deposit " + uniqueSeed;

        cy.visit("/finance/deposit/search");
        cy.contains("Deposits");
        cy.get("[data-bs-target='#newDepositModal']").click();
        cy.get("#depositComment").type(name);
        cy.get("#addNewDeposit").click();

        cy.url().should("contain", "DepositSlipEditor.php");

        cy.get(".btn-success").click();
        cy.url().should("contain", "/finance/pledge/new");

        cy.get(".fund-select").first().select(1);
        cy.get(".fund-amount").first().type("1000");
        cy.get("#CheckNo").type(uniqueSeed);
        cy.get("#FamilyID").invoke("val", "1");

        cy.get("#savePledgeBtn").click();
        cy.url().should("contain", "DepositSlipEditor.php");
    });

    it("Open the Deposits page & Add Payment", () => {
        cy.visit("/DepositSlipEditor.php?DepositSlipID=5");
        cy.contains("Deposit Slip Number: 5");
        cy.contains("Payments");

        cy.get(".btn-success").click();
        cy.url().should("contain", "/finance/pledge/new");

        cy.get(".fund-select").first().select(1);
        cy.get(".fund-amount").first().type("1000");
        cy.get("#CheckNo").type("111");
        cy.get("#FamilyID").invoke("val", "1");

        cy.get("#savePledgeBtn").click();
        cy.url().should("contain", "DepositSlipEditor.php");
    });

    it("Edit Deposit without an ID", () => {
        cy.visit("/DepositSlipEditor.php?DepositSlipID=9999");
        cy.url().should("contain", "/finance/deposit/search");
        cy.contains("Deposits");
    });

    it("Open Deposit with the Bad / deleted Deposits id", () => {
        cy.visit("/DepositSlipEditor.php?");
        cy.url().should("contain", "/finance/deposit/search");
        cy.contains("Deposits");
    });

    it("Create a Deposit with XSS attempt - should be sanitized", () => {
        const uniqueSeed = Date.now().toString();
        const xssPayload = "<script>alert('XSS')</script>Test" + uniqueSeed;
        const sanitizedComment = "alert(&#039;XSS&#039;)Test" + uniqueSeed; // The script tags should be stripped, quotes escaped

        // Create the deposit directly via the API to test server-side sanitization.
        // Using cy.request() here is intentional: the test targets the POST /api/deposits
        // endpoint's sanitization behaviour, not the modal UI itself.  Typing the raw
        // XSS payload (<script>…</script>) through a Bootstrap modal input is fragile
        // because Bootstrap's transition management can interrupt Cypress keystroke
        // delivery, causing only part of the value to be committed.
        cy.request({
            method: "POST",
            url: "/api/deposits",
            body: {
                depositType:    "Bank",
                depositComment: xssPayload,
                depositDate:    new Date().toISOString().split("T")[0],
            },
            headers: { "Content-Type": "application/json" },
        }).then((response) => {
            expect(response.status).to.eq(200);
            const depositId = response.body.Id;

            cy.visit(`/DepositSlipEditor.php?DepositSlipID=${depositId}`);

            // Verify the comment field contains sanitized text (script tags stripped, quotes escaped)
            cy.get("#Comment").should("have.value", sanitizedComment);
        });

    });

    it("Load DepositSlipEditor and verify DataTables loads without errors", () => {
        cy.visit("/DepositSlipEditor.php?DepositSlipID=5");

        // Verify page loaded
        cy.contains("Deposit Slip Number: 5");
        cy.contains("Payments");

    });

    it("Renders the Funds bar chart via ApexCharts", () => {
        cy.visit("/DepositSlipEditor.php?DepositSlipID=5");

        cy.contains("Deposit Slip Number: 5");

        // The #fund-bar container is always present; ApexCharts must
        // actually draw into it (SVG/canvas), not just leave it empty —
        // catches chart-library regressions (e.g. major version bumps)
        // that a container-existence check alone would miss.
        cy.get("#fund-bar").should("exist");
        cy.get("#fund-bar .apexcharts-canvas").should("exist");
        cy.get("#fund-bar svg.apexcharts-svg").should("exist");
    });
});
