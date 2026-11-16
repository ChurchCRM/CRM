/// <reference types="cypress" />

describe("Community Feedback", () => {
    beforeEach(() => cy.setupAdminSession());

    it("asks for a vote and links the church registration form", () => {
        cy.visit("/v2/community-feedback");
        cy.contains("Open an idea. Vote with");
        cy.contains("What this page is for").should("not.exist");
        cy.get('a[href="https://forms.gle/F1xgoBaWUD1Fy7Bn9"]').should("be.visible");
    });

    it("shows an icon in the top nav and a link in the Support menu", () => {
        cy.visit("/v2/community-feedback");
        cy.contains("Help shape ChurchCRM").should("not.exist");
        cy.get('a[title="Review what we are considering"]').should("be.visible");
        cy.get("#supportMenu").click();
        cy.get("#supportMenu")
            .parent()
            .find('a[href*="/v2/community-feedback"]')
            .should("be.visible");
    });

    it("loads topics from the feedback endpoint", () => {
        cy.intercept("GET", "**/api/community-feedback", {
            statusCode: 200,
            body: {
                count: 1,
                items: [
                    {
                        id: "sample",
                        title: "Sample topic",
                        summary: "A short summary",
                        category: "Feature Proposal",
                        discussionUrl: "https://github.com/ChurchCRM/CRM/discussions/1",
                        expiresAt: null,
                    },
                ],
            },
        }).as("feedback");
        cy.visit("/v2/community-feedback");
        cy.wait("@feedback");
        cy.get("#communityFeedbackCount").should("contain", "1");
        cy.contains("Sample topic");
        cy.get("#communityFeedbackList .card").should("have.length", 1);
        cy.get(".col-lg-4").contains("How to respond");
    });
});
