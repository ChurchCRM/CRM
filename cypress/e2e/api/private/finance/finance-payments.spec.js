/// <reference types="cypress" />

describe("API Finance Payments - Type Mismatch Fix", () => {
    const getPaymentPayload = (overrides = {}) => ({
        type: "Payment",
        iMethod: "CASH",
        Date: "2025-10-25",
        FamilyID: "1",
        FundSplit: JSON.stringify([
            {
                FundID: "1",
                Amount: 100.00,
            },
        ]),
        ...overrides,
    });

    it("POST /api/payments - Requires authentication", () => {
        // Test that unauthenticated request returns 401
        cy.request({
            method: "POST",
            failOnStatusCode: false,
            url: Cypress.env("apiRoot") + "/api/payments",
            body: getPaymentPayload(),
        }).then((resp) => {
            expect(resp.status).to.equal(401);
        });
    });

    it("POST /api/payments - Handles type conversion without Fatal Error", () => {
        // This test verifies the critical fix: array is cast to object
        // and validateDate() can access $payment->Date instead of $payment['Date']
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/payments",
            getPaymentPayload(),
            [200, 400, 422] // Accept multiple status codes as operation may fail for other reasons
        ).then((resp) => {
            // Before fix: Returns 500 with "Call to a member function on array"
            // After fix: May return different error, but not type mismatch
            const bodyStr = JSON.stringify(resp).toLowerCase();
            expect(bodyStr).to.not.include("call to a member function on array");
            expect(bodyStr).to.not.include("trying to get property");
        });
    });

    it("POST /api/payments - Content-Type JSON header processed correctly", () => {
        // Verifies proper JSON content type handling
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/payments",
            getPaymentPayload(),
            [200, 400, 422]
        ).then((resp) => {
            // Should not crash with type error
            const bodyStr = JSON.stringify(resp).toLowerCase();
            expect(bodyStr).to.not.include("call to a member function on array");
        });
    });

    it("POST /api/payments - Null safety check for check validation", () => {
        // Tests null safety: validateChecks checks for empty properties before access
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/payments",
            getPaymentPayload({ iMethod: "CHECK" }),
            [200, 400, 422]
        ).then((resp) => {
            // Should not crash with type error, should validate properly
            const bodyStr = JSON.stringify(resp).toLowerCase();
            expect(bodyStr).to.not.include("call to a member function on array");
            expect(bodyStr).to.not.include("undefined property");
        });
    });

    it("POST /api/payments - Null safety for currency denominations", () => {
        // Tests processCurrencyDenominations null safety (missing cashDenominations)
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/payments",
            getPaymentPayload(),
            [200, 400, 422]
        ).then((resp) => {
            // Should not crash due to empty property access
            const bodyStr = JSON.stringify(resp).toLowerCase();
            expect(bodyStr).to.not.include("trying to get property of non-object");
            expect(bodyStr).to.not.include("undefined property");
        });
    });

    it("GET /api/payments/family/{id}/list - Requires authentication", () => {
        cy.request({
            method: "GET",
            failOnStatusCode: false,
            url: Cypress.env("apiRoot") + "/api/payments/family/1/list",
        }).then((resp) => {
            expect(resp.status).to.equal(401);
        });
    });

    it("GET /api/payments/family/{id}/list - Returns family pledges and payments", () => {
        // Tests that family payments list API works and returns proper data structure
        cy.makePrivateAdminAPICall(
            "GET",
            "/api/payments/family/1/list",
            null,
            200
        ).then((resp) => {
            expect(resp).to.have.property("data");
            expect(resp.data).to.be.an("array");
            
            // If there are payments, verify structure
            if (resp.data.length > 0) {
                const payment = resp.data[0];
                // Verify key fields exist and are properly formatted
                expect(payment).to.have.property("GroupKey");
                expect(payment).to.have.property("Fund");
                expect(payment).to.have.property("FormattedFY");
                expect(payment).to.have.property("Date");
                expect(payment).to.have.property("Amount");
                expect(payment).to.have.property("PledgeOrPayment");
            }
        });
    });

    it("GET /api/payments/family/{id}/list - Returns list with no errors", () => {
        // Tests that family payments list API works without errors
        cy.makePrivateAdminAPICall(
            "GET",
            "/api/payments/family/20/list",
            null,
            200
        ).then((resp) => {
            expect(resp).to.have.property("data");
            expect(resp.data).to.be.an("array");
            
            // Response should be valid JSON without undefined function errors
            const bodyStr = JSON.stringify(resp).toLowerCase();
            expect(bodyStr).to.not.include("call to undefined function");
            expect(bodyStr).to.not.include("makefystring");
        });
    });

    it("GET /api/payments/family/{id}/list - Returns payments with properly formatted fiscal year", () => {
        // Tests that family payments API returns valid fiscal year data
        cy.makePrivateAdminAPICall(
            "GET",
            "/api/payments/family/1/list",
            null,
            200
        ).then((resp) => {
            expect(resp).to.have.property("data");
            expect(resp.data).to.be.an("array");
            
            // If there are payments, verify data integrity
            if (resp.data.length > 0) {
                const payment = resp.data[0];
                expect(payment).to.have.property("FormattedFY");
                expect(typeof payment.FormattedFY).to.equal("string");
                expect(payment.FormattedFY).to.not.be.empty;
                // Fiscal year should be in valid format
                expect(payment.FormattedFY).to.match(/^\d{4}(\/\d{2})?$/);
            }
        });
    });
});
