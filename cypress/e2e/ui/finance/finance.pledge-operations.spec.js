/// <reference types="cypress" />

describe("Pledge Operations", () => {
    const getPaymentPayload = (overrides = {}) => ({
        type: "Payment",
        iMethod: "CASH",
        Date: "2025-10-25",
        FamilyID: "1",
        FYID: 29,
        tScanString: "",
        FundSplit: JSON.stringify([
            { FundID: "1", Amount: 100.00, NonDeductible: 0, Comment: "" },
        ]),
        ...overrides,
    });

    beforeEach(() => cy.setupAdminSession());

    it("Pledge editor shows a Delete button in edit mode", () => {
        cy.request("POST", "/api/payments/pledges", getPaymentPayload()).then((resp) => {
            const groupKey = resp.body.groupKey;
            cy.visit("/finance/pledge/" + groupKey + "/edit");
            cy.get("#deletePledgeBtn").should("be.visible");
        });
    });

    it("Delete button removes the payment and redirects away", () => {
        cy.request("POST", "/api/payments/pledges", getPaymentPayload()).then((resp) => {
            const groupKey = resp.body.groupKey;
            cy.visit("/finance/pledge/" + groupKey + "/edit");

            cy.on("window:confirm", () => true);
            cy.get("#deletePledgeBtn").click();

            cy.url().should("contain", "/finance/");

            cy.request({
                method: "GET",
                url: "/api/payments/pledges/" + groupKey,
                failOnStatusCode: false,
            }).its("status").should("eq", 404);
        });
    });

    it("Cancelling the delete confirmation keeps the payment", () => {
        cy.request("POST", "/api/payments/pledges", getPaymentPayload()).then((resp) => {
            const groupKey = resp.body.groupKey;
            cy.visit("/finance/pledge/" + groupKey + "/edit");

            cy.on("window:confirm", () => false);
            cy.get("#deletePledgeBtn").click();

            cy.request("/api/payments/pledges/" + groupKey)
                .its("status").should("eq", 200);
        });
    });

    it("DELETE /api/payments/{groupKey} returns 404 for a nonexistent group", () => {
        cy.request({
            method: "DELETE",
            url: "/api/payments/nonexistent-group-key",
            failOnStatusCode: false,
        }).its("status").should("eq", 404);
    });

    it("PUT /api/payments/{groupKey} returns 404 for a nonexistent group", () => {
        cy.request({
            method: "PUT",
            url: "/api/payments/nonexistent-group-key",
            failOnStatusCode: false,
            body: {
                FamilyID: "1",
                Date: "2025-10-25",
                type: "Payment",
                iMethod: "CASH",
                FYID: 29,
                FundSplit: JSON.stringify([
                    { FundID: "1", Amount: 100.00, NonDeductible: 0, Comment: "" },
                ]),
            },
        }).its("status").should("eq", 404);
    });
});
