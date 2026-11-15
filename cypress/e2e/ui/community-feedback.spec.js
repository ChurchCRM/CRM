/// <reference types="cypress" />

describe("Community Feedback", () => {
    beforeEach(() => cy.setupAdminSession());

    it("renders the why and how sections", () => {
        cy.visit("community/feedback");
        cy.contains("We Need Your Help");
        cy.contains("Why your feedback matters");
        cy.contains("ChurchCRM is built by a small team");
        cy.contains("How to take part");
    });

    it("shows the top-nav help link pointing at the feedback page", () => {
        cy.visit("community/feedback");
        cy.get('a[href*="/community/feedback"]')
            .first()
            .should("have.attr", "href")
            .and("include", "/community/feedback");
    });

    it("links to all topics on GitHub Discussions in the fallback state", () => {
        // The remote feedback.json does not exist yet, so the page renders
        // its fallback state with a link to every open Discussion.
        cy.visit("community/feedback");
        cy.get('a[href="https://github.com/ChurchCRM/CRM/discussions"]').should(
            "exist",
        );
    });
});
