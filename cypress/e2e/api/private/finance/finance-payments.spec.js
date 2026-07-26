/// <reference types="cypress" />

describe("API Finance Payments - Type Mismatch Fix", () => {
    const getPaymentPayload = (overrides = {}) => ({
        type: "Payment",
        iMethod: "CASH",
        Date: "2025-10-25",
        FamilyID: "1",
        FYID: 29,
        tScanString: "",
        FundSplit: JSON.stringify([
            {
                FundID: "1",
                Amount: 100.00,
                NonDeductible: 0,
                Comment: "",
            },
        ]),
        ...overrides,
    });

    describe("POST /api/payments/ - Type casting fix validation", () => {
        it("POST /api/payments/ - No type mismatch errors after normalizeFundSplit fix", () => {
            // normalizeFundSplit() decodes the JSON-string FundSplit before validateFund()
            // runs, eliminating the PHP 8 TypeError from count($string). The request may
            // now succeed (200) or fail with a data/DB error — any of these is acceptable
            // here. What matters is the absence of PHP type-error strings.
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/payments/",
                getPaymentPayload(),
                [200, 400, 422, 500]
            ).then((resp) => {
                const bodyStr = JSON.stringify(resp.body).toLowerCase();
                expect(bodyStr).to.not.include("call to a member function on array");
                expect(bodyStr).to.not.include("trying to get property");
                expect(bodyStr).to.not.include("typeerror");
            });
        });

        it("POST /api/payments/ with CHECK method - Null safety validation", () => {
            // After normalizeFundSplit fixes the FundSplit TypeError, validateChecks now
            // runs and throws "Must specify non-zero check number" when iCheckNo is absent.
            // The legacy route has no try/catch, so Slim's error middleware returns 500.
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/payments/",
                getPaymentPayload({ iMethod: "CHECK" }),
                [400, 422, 500]
            ).then((resp) => {
                const bodyStr = JSON.stringify(resp.body).toLowerCase();
                expect(bodyStr).to.not.include("call to a member function on array");
                expect(bodyStr).to.not.include("undefined property");
                expect(bodyStr).to.not.include("typeerror");
            });
        });
    });

    describe("POST /api/payments/pledges - New MVC endpoint validation", () => {
        it("POST /api/payments/pledges - Returns 400 when FundSplit is missing", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/payments/pledges",
                { type: "Payment", iMethod: "CASH", Date: "2025-10-25", FamilyID: "1", FYID: 29 },
                400
            ).then((resp) => {
                // renderErrorJSON returns { success: false, message: '...' }
                expect(resp.body).to.have.property("message");
                expect(resp.body.success).to.equal(false);
            });
        });

        it("POST /api/payments/pledges - Valid CASH payload returns payment with GroupKey", () => {
            // Verifies that the post-save redirect in the JS editor receives a GroupKey.
            // Seed data has FamilyID 1 and FundID 1, so this request should succeed.
            // Note: the /api/payments/pledges endpoint returns `payment` as a decoded JSON
            // object (not a double-encoded string), so resp.body.payment is already parsed.
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/payments/pledges",
                getPaymentPayload(),
                200
            ).then((resp) => {
                expect(resp.body).to.have.property("payment");
                const parsed = resp.body.payment;
                expect(parsed).to.have.property("GroupKey");
                expect(parsed.GroupKey).to.be.a("string").and.to.have.length.greaterThan(0);
                expect(parsed).to.have.property("total");
                expect(parsed.total).to.be.closeTo(100.00, 0.01);
            });
        });

        it("POST /api/payments/pledges - Multi-fund split saves all funds (smoke test)", () => {
            // Verifies the core fix: multi-fund FundSplit no longer stops after the first row.
            // Both rows use FundID 1 (the only fund in seed data); the pledge table has
            // no unique constraint on (famId, fundId, date), so two rows are valid.
            // Note: the /api/payments/pledges endpoint returns `payment` as a decoded JSON
            // object (not a double-encoded string), so resp.body.payment is already parsed.
            const multiSplit = JSON.stringify([
                { FundID: "1", Amount: 75.00, NonDeductible: 0, Comment: "Fund split 1" },
                { FundID: "1", Amount: 25.00, NonDeductible: 0, Comment: "Fund split 2" },
            ]);
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/payments/pledges",
                getPaymentPayload({ FundSplit: multiSplit }),
                200
            ).then((resp) => {
                expect(resp.body).to.have.property("payment");
                const parsed = resp.body.payment;
                expect(parsed).to.have.property("GroupKey");
                // Both rows persisted — funds array should have 2 entries
                expect(parsed.funds).to.be.an("array").with.length(2);
                // Total reflects both allocations
                expect(parsed.total).to.be.closeTo(100.00, 0.01);
            });
        });
    });

    describe("GET /api/payments/pledges/{groupKey} - Pledge group retrieval", () => {
        it("GET /api/payments/pledges/{groupKey} - Returns full group details for an existing groupKey", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/payments/pledges",
                getPaymentPayload(),
                200
            ).then((createResp) => {
                const groupKey = createResp.body.groupKey;
                expect(groupKey).to.be.a("string").and.to.have.length.greaterThan(0);

                cy.makePrivateAdminAPICall(
                    "GET",
                    "/api/payments/pledges/" + groupKey,
                    null,
                    200
                ).then((getResp) => {
                    expect(getResp.body.groupKey).to.equal(groupKey);
                    expect(getResp.body.pledgeOrPayment).to.equal("Payment");
                    expect(getResp.body.funds).to.be.an("array").with.length(1);
                    expect(getResp.body.total).to.be.closeTo(100.00, 0.01);
                });
            });
        });

        it("GET /api/payments/pledges/{groupKey} - Returns 404 for an unknown groupKey", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/payments/pledges/does-not-exist-groupkey",
                null,
                404
            );
        });
    });

    describe("DELETE /api/payments/{groupKey} - Multi-fund delete (deletePledgeGroup bug fix)", () => {
        it("DELETE /api/payments/{groupKey} - Removes every fund row sharing the GroupKey, not just the first", () => {
            // Regression test for the bug called out in #8482: the old
            // deletePayment() only removed the first matching row. deletePledgeGroup()
            // must delete all pledge_plg rows for a multi-fund GroupKey in one call.
            const multiSplit = JSON.stringify([
                { FundID: "1", Amount: 60.00, NonDeductible: 0, Comment: "Delete test fund 1" },
                { FundID: "1", Amount: 40.00, NonDeductible: 0, Comment: "Delete test fund 2" },
            ]);
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/payments/pledges",
                getPaymentPayload({ FundSplit: multiSplit }),
                200
            ).then((createResp) => {
                const groupKey = createResp.body.groupKey;
                expect(createResp.body.payment.funds).to.be.an("array").with.length(2);

                cy.makePrivateAdminAPICall(
                    "DELETE",
                    "/api/payments/" + groupKey,
                    null,
                    200
                ).then(() => {
                    // If any row had survived the delete, this would still return 200
                    // with leftover fund data instead of 404 — proving all rows were removed.
                    cy.makePrivateAdminAPICall(
                        "GET",
                        "/api/payments/pledges/" + groupKey,
                        null,
                        404
                    );
                });
            });
        });
    });

    describe("GET /api/fiscalyear - Fiscal year resolution", () => {
        it("GET /api/fiscalyear - Returns 200 with fyId and label", () => {
            cy.makePrivateAdminAPICall("GET", "/api/fiscalyear", null, 200).then((resp) => {
                expect(resp.body).to.have.property("fyId");
                expect(resp.body.fyId).to.be.a("number").and.to.be.at.least(1);
                expect(resp.body).to.have.property("label");
                expect(resp.body.label).to.match(/^\d{4}(\/\d{2})?$/);
            });
        });

        it("GET /api/fiscalyear?date=2025-01-15 - Returns correct fyId for a specific date", () => {
            cy.makePrivateAdminAPICall("GET", "/api/fiscalyear?date=2025-01-15", null, 200).then((resp) => {
                expect(resp.body).to.have.property("fyId");
                expect(resp.body.fyId).to.be.a("number").and.to.be.at.least(1);
                expect(resp.body).to.have.property("label");
            });
        });

        it("GET /api/fiscalyear - Unauthenticated returns 401", () => {
            cy.request({
                method: "GET",
                url: "/api/fiscalyear",
                failOnStatusCode: false,
            }).then((resp) => {
                expect(resp.status).to.equal(401);
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
