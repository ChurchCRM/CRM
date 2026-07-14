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
        // Wait up to 10 s for the form to render (page loads TomSelect for Donor dropdown)
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
    // CSRF protection (regression for "Invalid security token" on save)
    //
    // Root cause of the original bug: CSRFUtils rotated the token on every
    // render, so a second GET of the editor (e.g. from a relative <img> src
    // that re-hit the route, a prefetch, or a second tab) invalidated the
    // token already embedded in the on-screen form, and the subsequent POST
    // failed with "Invalid security token." The fix makes the token a stable,
    // session-wide synchronizer token and validates every POST via a single
    // group-level CSRFMiddleware instead of per-route inline checks.
    // -----------------------------------------------------------------------

    const extractCsrf = (html) => {
        const m = html.match(/name="csrf_token"\s+value="([a-f0-9]+)"/i);
        expect(m, "csrf_token hidden field present in editor HTML").to.not.be.null;
        return m[1];
    };

    it("reuses one stable CSRF token across repeated editor renders", () => {
        // Two separate GETs of the same editor must return the SAME token.
        // Before the fix each render rotated the token, invalidating the form.
        cy.request("/fundraiser/1/donated-items/editor").then((first) => {
            const tokenA = extractCsrf(first.body);
            cy.request("/fundraiser/1/donated-items/editor").then((second) => {
                const tokenB = extractCsrf(second.body);
                expect(tokenB, "token is reused, not rotated, between renders").to.eq(tokenA);
            });
        });
    });

    it("saves a donated item with a valid CSRF token (no 'Invalid security token')", () => {
        cy.request("/fundraiser/1/donated-items/editor").then((res) => {
            const token = extractCsrf(res.body);
            cy.request({
                method: "POST",
                url: "/fundraiser/1/donated-items/editor",
                form: true,
                followRedirect: false,
                body: {
                    csrf_token: token,
                    Item: "CSRF Regression Item",
                    Title: "Regression",
                    Description: "",
                    Donor: 0,
                    Buyer: 0,
                    Multibuy: 0,
                    SellPrice: 0,
                    EstPrice: 0,
                    MaterialValue: 0,
                    MinimumPrice: 0,
                    PictureURL: "",
                    DonatedItemSubmit: "Save",
                },
            }).then((post) => {
                // Success redirects back to the fundraiser editor; a CSRF failure
                // would instead be a 4xx with the "Invalid security token" body.
                expect(post.status).to.eq(302);
                expect(post.headers.location).to.include("/fundraiser/editor/1");
                expect(post.body).to.not.include("Invalid security token");
            });
        });
    });

    it("saves a donated item with empty price fields (no 500 on blank DECIMAL columns)", () => {
        // Regression: blank price inputs arrive as '' and were passed straight to
        // ->setSellprice('') etc., which the DECIMAL columns reject with a 500
        // (Incorrect decimal value: ''). The route now coerces blanks to 0.0.
        cy.request("/fundraiser/1/donated-items/editor").then((res) => {
            const token = extractCsrf(res.body);
            cy.request({
                method: "POST",
                url: "/fundraiser/1/donated-items/editor",
                form: true,
                followRedirect: false,
                failOnStatusCode: false,
                body: {
                    csrf_token: token,
                    Item: "Empty Price Item",
                    Title: "No Prices",
                    Description: "",
                    Donor: 0,
                    Buyer: 0,
                    Multibuy: 0,
                    SellPrice: "",
                    EstPrice: "",
                    MaterialValue: "",
                    MinimumPrice: "",
                    PictureURL: "",
                    DonatedItemSubmit: "Save",
                },
            }).then((post) => {
                expect(post.status, "blank prices must not 500").to.eq(302);
                expect(post.headers.location).to.include("/fundraiser/editor/1");
            });
        });
    });

    it("rejects a donated-item save with a missing CSRF token (403)", () => {
        cy.request({
            method: "POST",
            url: "/fundraiser/1/donated-items/editor",
            form: true,
            failOnStatusCode: false,
            body: { Item: "No token", DonatedItemSubmit: "Save" },
        }).then((post) => {
            expect(post.status).to.eq(403);
        });
    });

    // -----------------------------------------------------------------------
    // View page (/fundraiser/view/{id}) — PR #9188
    // -----------------------------------------------------------------------

    it("view page renders for fundraiser 1", () => {
        cy.visit("/fundraiser/view/1");
        // Page title / breadcrumb should include the fundraiser name
        cy.contains("2016 Car Wash");
        // At a Glance sidebar card should be present
        cy.contains("At a Glance");
        // Donated Items card (even when empty)
        cy.contains("Donated Items");
        // Edit button is visible to a user with ManageFundraisers role.
        // Scope to .btn-primary to avoid matching the hidden sidebar nav link
        // that also contains "Edit" (set via $_SESSION['iCurrentFundraiser']).
        cy.contains("a.btn-primary", "Edit").should("be.visible");
    });

    it("view page redirects to listing for unknown fundraiser", () => {
        cy.request({
            url: "/fundraiser/view/99999",
            followRedirect: false,
            failOnStatusCode: false,
        }).then((res) => {
            expect(res.status).to.eq(302);
            expect(res.headers.location).to.include("/fundraiser/");
        });
    });

    // -----------------------------------------------------------------------
    // Listing — filter bar smoke test (PR #9188)
    // -----------------------------------------------------------------------

    it("status filter narrows listing without error", () => {
        cy.visit("/fundraiser/?filterStatus=Active");
        cy.contains("Fundraiser Listing");
        // The page should not contain a PHP error trace
        cy.get("body").should("not.contain", "Fatal error");
        cy.get("body").should("not.contain", "Uncaught");
    });

    it("type filter narrows listing without error", () => {
        cy.visit("/fundraiser/?filterType=Auction");
        cy.contains("Fundraiser Listing");
        cy.get("body").should("not.contain", "Fatal error");
    });

    // -----------------------------------------------------------------------
    // Listing — archive collapse section (PR #9188)
    // -----------------------------------------------------------------------

    it("archive collapse section is present on the listing page", () => {
        cy.visit("/fundraiser/");
        // The archive collapse target must exist in the DOM
        cy.get("#archiveCollapse").should("exist");
    });


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
