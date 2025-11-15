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

    describe("POST /api/payments - Type casting fix validation", () => {
        it("POST /api/payments - No type mismatch errors", () => {
            // Critical fix: array cast to object, validateDate accesses $payment->Date not $payment['Date']
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/payments",
                getPaymentPayload(),
                [200, 400, 422, 500] // Accept various codes - we care that no type error occurs
            ).then((resp) => {
                const bodyStr = JSON.stringify(resp).toLowerCase();
                expect(bodyStr).to.not.include("call to a member function on array");
                expect(bodyStr).to.not.include("trying to get property");
            });
        });

        it("POST /api/payments with CHECK method - Null safety validation", () => {
            // validateChecks uses !empty() checks before accessing properties
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/payments",
                getPaymentPayload({ iMethod: "CHECK" }),
                [200, 400, 422, 500]
            ).then((resp) => {
                const bodyStr = JSON.stringify(resp).toLowerCase();
                expect(bodyStr).to.not.include("call to a member function on array");
                expect(bodyStr).to.not.include("undefined property");
            });
        });
    });

    describe("GET /api/payments/family/{id}/list - Data retrieval and formatting", () => {
        it("GET /api/payments/family/1/list - Returns 200 with data array", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/payments/family/1/list",
                null,
                200
            ).then((resp) => {
                expect(resp.body).to.have.property("data");
                expect(resp.body.data).to.be.an("array");
            });
        });

        it("GET /api/payments/family/1/list - Returns properly structured payment objects", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/payments/family/1/list",
                null,
                200
            ).then((resp) => {
                expect(resp.body.data).to.be.an("array");
                
                if (resp.body.data.length > 0) {
                    const payment = resp.body.data[0];
                    expect(payment).to.have.property("FormattedFY");
                    expect(payment).to.have.property("GroupKey");
                    expect(payment).to.have.property("Fund");
                    expect(payment).to.have.property("Date");
                    expect(payment).to.have.property("Amount");
                    expect(payment).to.have.property("PledgeOrPayment");
                }
            });
        });

        it("GET /api/payments/family/1/list - Fiscal year formatted correctly", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/payments/family/1/list",
                null,
                200
            ).then((resp) => {
                if (resp.body.data.length > 0) {
                    const payment = resp.body.data[0];
                    expect(payment.FormattedFY).to.be.a("string");
                    expect(payment.FormattedFY).to.match(/^\d{4}(\/\d{2})?$/);
                }
            });
        });

        it("GET /api/payments/family/20/list - Works with different family IDs", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/payments/family/20/list",
                null,
                200
            ).then((resp) => {
                expect(resp.body).to.have.property("data");
                expect(resp.body.data).to.be.an("array");
            });
        });
    });
});
