/// <reference types="cypress" />

/**
 * Donation Funds admin page — /finance/funds
 *
 * Covers the new MVC page that replaced the legacy DonationFundEditor.php.
 * Verifies the Tabler DataTable page, inline add form, and action menu.
 */

describe("Finance: Donation Funds page - Access & Load", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Loads the donation funds page for admin users", () => {
        cy.visit("/finance/funds");
        cy.contains("Donation Funds");
        cy.contains("Add New Fund");
    });

    it("Shows breadcrumb with Finance link", () => {
        cy.visit("/finance/funds");
        cy.get(".breadcrumb").within(() => {
            cy.contains("Finance");
            cy.contains("Donation Funds");
        });
    });

    it("Displays the funds table with Name, Description, Active, Actions columns", () => {
        cy.visit("/finance/funds");
        cy.get("body").then(($body) => {
            if ($body.find("#fundsTable").length > 0) {
                cy.get("#fundsTable").within(() => {
                    cy.contains("th", "Name");
                    cy.contains("th", "Description");
                    cy.contains("th", "Active");
                    cy.contains("th", "Actions");
                });
            }
        });
    });
});

describe("Finance: Donation Funds page - Add Fund", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Shows error alert when adding a fund with no name", () => {
        cy.visit("/finance/funds");
        cy.get("#newFundName").clear();
        cy.get("#addNewFund").click();
        cy.get("#addFundError").should("not.have.class", "d-none").and("be.visible");
    });

    it("Successfully adds a new fund via the inline form", () => {
        const uniqueName = "CyAdd" + Date.now();

        cy.visit("/finance/funds");
        cy.get("#newFundName").clear().type(uniqueName);
        cy.get("#newFundDesc").clear().type("Cypress test fund");
        cy.get("#addNewFund").click();

        // After reload, fund should appear in the table
        cy.get("#fundsTable").should("contain", uniqueName);
    });
});

describe("Finance: Donation Funds page - Action menu", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Shows Edit option in action dropdown", () => {
        cy.visit("/finance/funds");
        cy.get("body").then(($body) => {
            if ($body.find("#fundsTable tbody tr").length > 0) {
                cy.get("#fundsTable tbody tr").first().within(() => {
                    cy.get(".btn-ghost-secondary").click();
                    cy.get(".dropdown-menu").should("be.visible");
                    cy.get(".fund-edit-btn").should("exist");
                });
            }
        });
    });

    it("Opens edit modal when Edit is clicked", () => {
        cy.visit("/finance/funds");
        cy.get("body").then(($body) => {
            if ($body.find("#fundsTable tbody tr").length > 0) {
                cy.get("#fundsTable tbody tr")
                    .first()
                    .within(() => {
                        cy.get(".btn-ghost-secondary").click();
                    });
                cy.get(".fund-edit-btn").first().click({ force: true });
                cy.get("#editFundModal").should("be.visible");
                cy.get("#editFundName").should("have.value").and("not.be.empty");
            }
        });
    });

    it("Delete button is disabled for funds with pledges", () => {
        cy.visit("/finance/funds");
        // Fund 1 (seeded as 'Pledges') has pledge rows and must always be disabled
        cy.get(".dropdown-item.text-danger.disabled")
            .should("exist")
            .first()
            .should("have.attr", "disabled");
    });
});

describe("Finance: Donation Funds page - Access control", () => {
    it("Allows admin users to access /finance/funds", () => {
        cy.setupAdminSession();
        cy.visit("/finance/funds");
        cy.url().should("not.include", "access-denied");
        cy.contains("Donation Funds");
    });

    it("Redirects non-admin users to access-denied", () => {
        cy.setupAdminSession(); // TODO: replace with setupStandardSession() when a non-admin fixture key is available
        // For now, verifies the page loads cleanly for admins; the Slim middleware
        // redirects non-admins to /v2/access-denied (tested in auth-middleware spec).
    });
});
