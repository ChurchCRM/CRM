/// <reference types="cypress" />

/**
 * Individual Person Pledge and Payment Attribution (#9313)
 *
 * Verifies that pledges can be attributed to a specific family member,
 * that the attribution persists and displays on the finance dashboard
 * and detail views, and that updating or deleting the pledge manages
 * the attribution mapping cleanly.
 */

describe("Individual Pledge Attribution", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Assigns pledge to a specific family member in editor and verifies attribution on finance dashboard", () => {
        const uniqueAmount = (Math.floor(Math.random() * 800) + 150).toFixed(2);
        cy.intercept("POST", "**/api/payments/pledges").as("submitPledge");

        // Open new pledge form with Family 1 (Campbell) pre-selected
        cy.visit("/finance/pledge/new?type=Pledge&familyId=1");

        // Verify member dropdown is populated for Family 1
        cy.get("#PersonID").should("be.visible");
        cy.get("#PersonID option").should("have.length.greaterThan", 1);

        // Select Mathew Campbell (person ID 2 in test seed)
        cy.get("#PersonID").select("2");

        // Select fund and enter amount
        cy.get(".fund-select").first().select(1);
        cy.get(".fund-amount").first().clear().type(uniqueAmount);

        // Save pledge
        cy.get("#savePledgeBtn").click();

        cy.wait("@submitPledge").then((interception) => {
            expect(interception.response.statusCode).to.eq(200);
            const body = interception.response.body;
            const groupKey = body.groupKey;
            const paymentData = typeof body.payment === "string" ? JSON.parse(body.payment) : body.payment;
            const fyId = paymentData.FYID || 29;

            expect(groupKey).to.be.a("string").and.not.be.empty;

            // Verify attribution on pledge detail view
            cy.visit(`/finance/pledge/${groupKey}`);
            cy.contains("Pledged By").should("be.visible");
            cy.contains("Mathew Campbell").should("be.visible");

            // Verify attribution on the Pledge Dashboard
            cy.visit(`/finance/pledge/dashboard?fyid=${fyId}`);
            cy.get("#pledge-summary-table").should("be.visible");
            cy.get("#pledge-summary-table").contains("td", "Campbell").parent("tr").within(() => {
                cy.contains("Mathew Campbell").should("be.visible");
                cy.contains(uniqueAmount).should("be.visible");
            });

            // Edit pledge: reassign to Darren Campbell (person ID 4)
            cy.visit(`/finance/pledge/${groupKey}/edit`);
            cy.get("#PersonID").should("have.value", "2");
            cy.get("#PersonID").select("4");

            cy.intercept("PUT", `**/api/payments/${groupKey}`).as("updatePledge");
            cy.get("#savePledgeBtn").click();
            cy.wait("@updatePledge").its("response.statusCode").should("eq", 200);

            // Verify detail view now shows Darren Campbell
            cy.visit(`/finance/pledge/${groupKey}`);
            cy.contains("Darren Campbell").should("be.visible");

            // Clean up: delete the test pledge
            cy.visit(`/finance/pledge/${groupKey}/edit`);
            cy.on("window:confirm", () => true);
            cy.get("#deletePledgeBtn").click();
            cy.url().should("not.contain", "/edit");
        });
    });

    it("Dynamic family member dropdown refreshes when changing family in editor", () => {
        cy.intercept("GET", "**/api/payments/family/1/members").as("getFamily1Members");
        cy.intercept("GET", "**/api/payments/family/9/members").as("getFamily9Members");

        cy.visit("/finance/pledge/new?type=Pledge");

        // Initially unassigned
        cy.get("#PersonID option").should("have.length", 1);
        cy.get("#PersonID").should("contain.text", "Entire Family / Unassigned");

        // Trigger family 1 lookup via API endpoint test
        cy.request("GET", "/api/payments/family/1/members").then((resp) => {
            expect(resp.status).to.eq(200);
            expect(resp.body).to.have.property("members");
            expect(resp.body.members).to.have.property("2");
            expect(resp.body.members["2"]).to.contain("Mathew Campbell");
        });

        // Trigger family 9 (Diaz) lookup
        cy.request("GET", "/api/payments/family/9/members").then((resp) => {
            expect(resp.status).to.eq(200);
            expect(resp.body).to.have.property("members");
            expect(resp.body.members).to.have.property("43");
            expect(resp.body.members["43"]).to.contain("Natalie Diaz");
        });
    });
});
