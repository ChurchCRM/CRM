/// <reference types="cypress" />

describe("Finance Family", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("View a Family with Pledges and Payments section", () => {
        // Intercept the pledge API so we can wait for the initial load
        cy.intercept("GET", "**/api/payments/family/1/list*").as("pledgeLoad");
        cy.visit("people/family/1");
        // Page title is family name, subtitle has "Family Profile"
        cy.contains("Campbell");
        cy.contains("Family Profile");
        cy.contains("Darren Campbell");

        // Finance section should be visible with pill filters
        cy.contains("Pledges and Payments");
        cy.get(".pledge-type-pill").should("have.length", 3);
        // FY pills: at minimum All Time + current FY pill (may include historical years too)
        cy.get(".pledge-fy-pill").should("have.length.at.least", 2);

        // Table should load with data
        cy.get("#pledge-payment-v2-table").should("be.visible");
        cy.wait("@pledgeLoad");

        // Default FY filter (current FY) hides older data — "Music Ministry" should NOT be visible
        cy.contains("Music Ministry").should("not.exist");

        // Click "All Time" pill to reveal all records (server-side reload)
        cy.intercept("GET", "**/api/payments/family/1/list*").as("pledgeAllTime");
        cy.get(".pledge-fy-pill[data-fy='0']").click();
        cy.wait("@pledgeAllTime");
        cy.get(".pledge-fy-pill.active").should("contain", "All Time");
        cy.contains("Music Ministry").should("be.visible");

        // Test type filter pills (still client-side column search)
        cy.get(".pledge-type-pill[data-filter='Pledge']").click();
        cy.get(".pledge-type-pill.active").should("contain", "Pledges");

        cy.get(".pledge-type-pill[data-filter='']").click();
        cy.get(".pledge-type-pill.active").should("contain", "All");
    });

    it("View another Family with finance data", () => {
        cy.intercept("GET", "**/api/payments/family/20/list*").as("pledgeLoad");
        cy.visit("people/family/20");
        cy.contains("Black");
        cy.contains("Family Profile");

        // Wait for finance section and table to be ready
        cy.contains("Pledges and Payments");
        cy.get("#pledge-payment-v2-table").should("be.visible");
        cy.wait("@pledgeLoad");

        // Default FY filter (current FY) hides older data
        cy.contains("New Building Fund").should("not.exist");

        // Click "All Time" to reveal all records (server-side reload)
        cy.intercept("GET", "**/api/payments/family/20/list*").as("pledgeAllTime");
        cy.get(".pledge-fy-pill[data-fy='0']").click();
        cy.wait("@pledgeAllTime");
        cy.get(".pledge-fy-pill.active").should("contain", "All Time");
        cy.contains("New Building Fund").should("be.visible");
    });
});
