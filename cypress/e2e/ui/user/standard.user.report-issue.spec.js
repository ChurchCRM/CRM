/// <reference types="cypress" />

describe("Report Issue", () => {
    beforeEach(() => cy.setupStandardSession());

    it("opens modal, enters description, and submits to GitHub", () => {
        const description = "Cypress test: menu disappears after clicking the nav toggle on mobile";

        cy.intercept("POST", "**/api/issues", {
            statusCode: 200,
            body: { issueBody: "## System Info\nVersion: test\n" },
        }).as("postIssue");

        cy.visit("v2/dashboard");

        // Stub window.open before triggering the flow
        cy.window().then((win) => {
            cy.stub(win, "open").as("windowOpen");
        });

        // Open support dropdown → click Report an issue
        cy.get("#supportMenu").click();
        cy.get("#reportIssue").click();

        // Modal should be visible with correct title
        cy.get("#IssueReportModal").should("be.visible");
        cy.get("#IssueReportModal .modal-title").should("contain.text", "Report an Issue");

        // Enter a description
        cy.get("#issueDescription").type(description);

        // Submit
        cy.get("#submitIssue").click();
        cy.wait("@postIssue");

        // GitHub should be opened with the description in the URL
        cy.get("@windowOpen").should("have.been.calledOnce");
        cy.get("@windowOpen").should(
            "have.been.calledWithMatch",
            /^https:\/\/github\.com\/ChurchCRM\/CRM\/issues\/new/
        );
        cy.get("@windowOpen").its("firstCall.args.0").should("include", encodeURIComponent(description));
    });
});
