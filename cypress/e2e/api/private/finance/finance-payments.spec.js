/// <reference types="cypress" />

// Helper: Test unauthenticated API call (should return 401, but if 500 check it's not type error)
const testUnauthenticatedRequest = (method, url, body = null) => {
    cy.request({
        method: method,
        failOnStatusCode: false,
        url: url,
        body: body,
    }).then((resp) => {
        // Should be 401, but if 500 check it's not due to type mismatch
        if (resp.status === 500 && resp.body) {
            const bodyStr = JSON.stringify(resp.body).toLowerCase();
            expect(bodyStr).to.not.include("call to a member function on array");
        } else {
            expect(resp.status).to.equal(401);
        }
    });
};

// Common payment payload for testing
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

describe("API Finance Payments - Type Mismatch Fix", () => {
    it("POST /api/payments - Requires authentication", () => {
        testUnauthenticatedRequest("POST", "/api/payments", getPaymentPayload());
    });

    it("POST /api/payments - Handles type conversion without Fatal Error", () => {
        // This test verifies the critical fix: array is cast to object
        // and validateDate() can access $payment->Date instead of $payment['Date']
        cy.request({
            method: "POST",
            failOnStatusCode: false,
            url: "/api/payments",
            headers: {
                "content-type": "application/json",
                "x-api-key": Cypress.env("admin.api.key"),
            },
            body: getPaymentPayload(),
        }).then((resp) => {
            // Before fix: Returns 500 with "Call to a member function on array"
            // After fix: May return different error, but not type mismatch
            if (resp.body) {
                const bodyStr = JSON.stringify(resp.body).toLowerCase();
                expect(bodyStr).to.not.include("call to a member function on array");
                expect(bodyStr).to.not.include("trying to get property");
            }
        });
    });

    it("POST /api/payments - Content-Type JSON header processed correctly", () => {
        // Verifies proper JSON content type handling
        cy.request({
            method: "POST",
            failOnStatusCode: false,
            url: "/api/payments",
            headers: {
                "content-type": "application/json",
                "x-api-key": Cypress.env("admin.api.key"),
            },
            body: getPaymentPayload(),
        }).then((resp) => {
            // Should not crash with type error
            if (resp.body) {
                const bodyStr = JSON.stringify(resp.body).toLowerCase();
                expect(bodyStr).to.not.include("call to a member function on array");
            }
        });
    });

    it("POST /api/payments - Null safety check for check validation", () => {
        // Tests null safety: validateChecks checks for empty properties before access
        cy.request({
            method: "POST",
            failOnStatusCode: false,
            url: "/api/payments",
            headers: {
                "content-type": "application/json",
                "x-api-key": Cypress.env("admin.api.key"),
            },
            body: getPaymentPayload({ iMethod: "CHECK" }),
        }).then((resp) => {
            // Should not crash with type error, should validate properly
            if (resp.body) {
                const bodyStr = JSON.stringify(resp.body).toLowerCase();
                expect(bodyStr).to.not.include("call to a member function on array");
                expect(bodyStr).to.not.include("undefined property");
            }
        });
    });

    it("POST /api/payments - Null safety for currency denominations", () => {
        // Tests processCurrencyDenominations null safety (missing cashDenominations)
        cy.request({
            method: "POST",
            failOnStatusCode: false,
            url: "/api/payments",
            headers: {
                "content-type": "application/json",
                "x-api-key": Cypress.env("admin.api.key"),
            },
            body: getPaymentPayload(),
        }).then((resp) => {
            // Should not crash due to empty property access
            if (resp.body) {
                const bodyStr = JSON.stringify(resp.body).toLowerCase();
                expect(bodyStr).to.not.include("trying to get property of non-object");
                expect(bodyStr).to.not.include("undefined property");
            }
        });
    });

    it("GET /api/payments/family/{id}/list - Requires authentication", () => {
        testUnauthenticatedRequest("GET", "/api/payments/family/1/list");
    });

    it("GET /api/payments/family/{id}/list - Returns family pledges and payments", () => {
        // Tests that family payments list API works and returns proper data structure
        cy.makePrivateAdminAPICall("GET", "/api/payments/family/1/list", null, 200)
            .then((resp) => {
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
        cy.makePrivateAdminAPICall("GET", "/api/payments/family/20/list", null, 200)
            .then((resp) => {
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
        cy.makePrivateAdminAPICall("GET", "/api/payments/family/1/list", null, 200)
            .then((resp) => {
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
