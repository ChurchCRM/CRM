/// <reference types="cypress" />

/**
 * E2E tests for the /fundraiser/* Slim MVC module.
 *
 * Seed data (cypress/data/seed.sql):
 *   - fundraiser_fr ID 1: "2016 Car Wash" (Youth Car Wash)
 *   - donateditem_di:  empty (tests create items as needed)
 *   - paddlenum_pn:    empty (tests create paddles as needed)
 *
 * All routes require ManageFundraisers. tony.wade (standard session) has
 * ManageFundraisers=1 and is used throughout.
 *
 * PDF report tests use cy.request (shares the browser session cookie) instead
 * of cy.visit because PDFs cannot be rendered in Cypress.
 */

describe("Fund Raiser", () => {
    beforeEach(() => cy.setupStandardSession());

    // -----------------------------------------------------------------------
    // Listing
    // -----------------------------------------------------------------------

    it("View All", () => {
        cy.visit("/fundraiser/");
        cy.contains("Fundraiser Listing");
    });

    it("View By Filter Date ", () => {
        cy.visit("/fundraiser/?dateStart=2015-01-01&dateEnd=2017-01-01");
        cy.contains("Fundraiser Listing");
        cy.contains("2016 Car Wash");
    });

    // -----------------------------------------------------------------------
    // Create / Edit fundraiser
    // -----------------------------------------------------------------------

    it("Create form renders at /fundraiser/editor", () => {
        cy.visit("/fundraiser/editor");
        cy.contains("Create New Fund Raiser");
    });

    it("Create new FundRaiser", () => {
        cy.visit("/fundraiser/editor");
        cy.contains("Create New Fund Raiser");

        cy.get('#Title').type('Summer Car Wash');
        cy.get('#Description').type('This is the youth carwash');
        // Click the form submit input (Save)
        cy.get('input[name="FundRaiserSubmit"]').click();

        // After a successful save the editor reloads at /fundraiser/editor/{id}
        cy.url().should('include', '/fundraiser/editor/');
        // Click the Add Donated Item link
        cy.contains('a', 'Add Donated Item').click();

        cy.url().should('include', '/donated-items/editor');
        // Wait for the donated-item editor form to fully load (page initialises
        // TomSelect for the Donor dropdown which can delay rendering)
        cy.get('form[name="DonatedItemEditor"]').should('exist');
        cy.get('#Item', { timeout: 10000 }).should('be.visible').type('Soap for the Car wash');
        cy.get('#Title').type('Soap');
        cy.get('#EstPrice').type('20');
        cy.get('input[name="DonatedItemSubmit"]').click();
        cy.url().should('include', '/fundraiser/editor/');
        cy.contains("Soap for the Car wash");
    });

    it("Edit form renders at /fundraiser/editor/1", () => {
        cy.visit("/fundraiser/editor/1");
        // Page title includes the fundraiser name
        cy.contains("2016 Car Wash");
    });

    // -----------------------------------------------------------------------
    // Paddle numbers
    // -----------------------------------------------------------------------

    it("Paddle list renders at /fundraiser/1/paddle-numbers", () => {
        cy.visit("/fundraiser/1/paddle-numbers");
        cy.contains("Buyers for this fundraiser");
    });

    it("New paddle form renders at /fundraiser/1/paddle-numbers/editor", () => {
        cy.visit("/fundraiser/1/paddle-numbers/editor");
        cy.contains("Buyer Number Editor");
        cy.get('#Num').should('exist');
    });

    // -----------------------------------------------------------------------
    // Donated items
    // -----------------------------------------------------------------------

    it("New donated-item form renders at /fundraiser/1/donated-items/editor", () => {
        cy.visit("/fundraiser/1/donated-items/editor");
        cy.contains("Donated Item Editor");
        cy.get('#Item', { timeout: 10000 }).should('exist');
    });

    // -----------------------------------------------------------------------
    // Donors
    // -----------------------------------------------------------------------

    it("Donors page renders at /fundraiser/1/donors", () => {
        cy.visit("/fundraiser/1/donors");
        cy.contains("Add Donors to Buyer List");
    });

    // -----------------------------------------------------------------------
    // Batch winner
    // -----------------------------------------------------------------------

    it("Batch winner form renders at /fundraiser/1/batch-winner", () => {
        cy.visit("/fundraiser/1/batch-winner");
        cy.contains("Batch Winner Entry");
    });

    // -----------------------------------------------------------------------
    // PDF report smoke tests
    // cy.request shares the session cookie set by setupStandardSession in
    // beforeEach and is faster than cy.visit for binary responses.
    // -----------------------------------------------------------------------

    it("bid-sheets returns application/pdf", () => {
        cy.request("/fundraiser/1/reports/bid-sheets")
            .its("headers.content-type")
            .should("include", "application/pdf");
    });

    it("certificates returns application/pdf", () => {
        cy.request("/fundraiser/1/reports/certificates")
            .its("headers.content-type")
            .should("include", "application/pdf");
    });

    it("catalog returns application/pdf", () => {
        cy.request("/fundraiser/1/reports/catalog")
            .its("headers.content-type")
            .should("include", "application/pdf");
    });

    it("statement (GET) returns application/pdf", () => {
        cy.request("/fundraiser/1/reports/statement")
            .its("headers.content-type")
            .should("include", "application/pdf");
    });
});
