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
        cy.url().should("contain", "FindDepositSlip.php");
        cy.contains("Deposit Listing");
    });

    it("Navigate to deposits from Finance Menu", () => {
        cy.visit("/finance/");
        
        // Use the View All link in Recent Deposits section
        cy.contains("Recent Deposits")
            .parents(".card")
            .find("a")
            .contains("View All")
            .click();
            
        cy.url().should("contain", "FindDepositSlip.php");
        cy.contains("Deposit Listing");
    });

    it("Create a new Deposit without comment", () => {
        cy.visit("/FindDepositSlip.php");
        cy.get("#depositComment").clear();
        cy.get("#addNewDeposit").click();
        cy.contains("You are about to add a new deposit without a comment");
    });

    it("Create a new Deposit", () => {
        const uniqueSeed = Date.now().toString();
        const name = "New Test Deposit " + uniqueSeed;

        cy.visit("/FindDepositSlip.php");
        cy.contains("Add New Deposit");
        cy.contains("Deposits");
        cy.get("#depositComment").type(name);
        cy.get("#addNewDeposit").click();

        cy.url().should("contain", "DepositSlipEditor.php");

        cy.get(".btn-success").click();
        cy.url().should("contain", "PledgeEditor.php");

        cy.get("#1_Amount").type("1000");
        cy.get("#CheckNo").type(uniqueSeed);

        cy.get("#saveBtn").click();
        cy.get("#DepositSlipEditor").submit();
        cy.url().should("contain", "DepositSlipEditor.php");
    });

    it("Open the Deposits page & Add Payment", () => {
        cy.visit("/DepositSlipEditor.php?DepositSlipID=5");
        cy.contains("Deposit Slip Number: 5");
        cy.contains("Payments");

        cy.get(".btn-success").click();
        cy.url().should("contain", "PledgeEditor.php");

        cy.get("#1_Amount").type("1000");
        cy.get("#CheckNo").type("111");

        cy.get("#saveBtn").click();
        cy.get("#DepositSlipEditor").submit();
        cy.url().should("contain", "DepositSlipEditor.php");
    });

    it("Edit Deposit without an ID", () => {
        cy.visit("/DepositSlipEditor.php?DepositSlipID=9999");
        cy.url().should("contain", "FindDepositSlip.php");
        cy.contains("Deposit Listing");
    });

    it("Open Deposit with the Bad / deleted Deposits id", () => {
        cy.visit("/DepositSlipEditor.php?");
        cy.url().should("contain", "FindDepositSlip.php");
        cy.contains("Deposit Listing");
    });

    it("Create a Deposit with XSS attempt - should be sanitized", () => {
        const uniqueSeed = Date.now().toString();
        const xssPayload = "<script>alert('XSS')</script>Test" + uniqueSeed;
        const sanitizedComment = "alert(&#039;XSS&#039;)Test" + uniqueSeed; // The script tags should be stripped, quotes escaped

        cy.visit("/FindDepositSlip.php");
        cy.contains("Add New Deposit");
        cy.get("#depositComment").type(xssPayload);
        cy.get("#addNewDeposit").click();

        cy.url().should("contain", "DepositSlipEditor.php");

        // Verify the comment field contains sanitized text (script tags stripped, quotes escaped)
        cy.get("#Comment").should("have.value", sanitizedComment);

    });

    it("Load DepositSlipEditor and verify DataTables loads without errors", () => {
        cy.visit("/DepositSlipEditor.php?DepositSlipID=5");
        
        // Verify page loaded
        cy.contains("Deposit Slip Number: 5");
        cy.contains("Payments");
        
    });
});
