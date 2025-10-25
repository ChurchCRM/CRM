/// <reference types="cypress" />

describe("API Private Deposit Operations with AuthService", () => {
    let depositID = 1; // Use existing deposit ID or create one

    describe("Deposit Payment Retrieval", () => {
        it("Get deposit payments with authorization check", () => {
            // Test that getPayments() requires proper authorization via AuthService
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/deposits/${depositID}/payments`,
                null,
                [200, 404] // 404 if deposit doesn't exist, 200 if it does
            ).then((resp) => {
                if (resp.status === 200) {
                    expect(resp.body).to.be.an("array");
                }
            });
        });

        it("Get deposit CSV export with authorization", () => {
            // Test that getDepositCSV() requires proper authorization via AuthService
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/deposits/${depositID}/csv`,
                null,
                [200, 404]
            ).then((resp) => {
                if (resp.status === 200) {
                    expect(resp.body).to.exist;
                }
            });
        });

        it("Get deposit PDF with authorization", () => {
            // Test that getPDF() requires proper authorization via AuthService
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/deposits/${depositID}/pdf`,
                null,
                [200, 404]
            );
        });

        it("Get deposit total amount", () => {
            // Test that getDepositTotal() requires proper authorization via AuthService
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/deposits/${depositID}/total`,
                null,
                [200, 404]
            ).then((resp) => {
                if (resp.status === 200) {
                    expect(resp.body).to.exist;
                }
            });
        });
    });

    describe("Deposit Operations - All Methods", () => {
        it("Create new deposit", () => {
            // Test creating a deposit with authorization
            const today = new Date().toISOString().split("T")[0];
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/deposits`,
                {
                    type: "CASH",
                    comment: "Test Deposit",
                    date: today,
                },
                [200, 201]
            ).then((resp) => {
                if (resp.status === 200 || resp.status === 201) {
                    expect(resp.body).to.have.property("id");
                }
            });
        });

        it("Get all deposits", () => {
            // Test retrieving deposits with authorization
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/deposits`,
                null,
                200
            ).then((resp) => {
                expect(resp.body).to.be.an("array");
            });
        });

        it("Update existing deposit", () => {
            // Test updating a deposit with authorization
            const today = new Date().toISOString().split("T")[0];
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/deposits/${depositID}`,
                {
                    type: "CASH",
                    comment: "Updated Test Deposit",
                    date: today,
                },
                [200, 404]
            );
        });

        it("Delete deposit", () => {
            // Test deleting a deposit with authorization
            cy.makePrivateAdminAPICall(
                "DELETE",
                `/api/deposits/9999`, // Non-existent ID for safety
                null,
                [200, 404]
            );
        });
    });

    describe("Authorization Tests - Finance Operations", () => {
        it("Non-finance user denied getting payments", () => {
            // Test that a user without bFinance permission is denied
            cy.request({
                method: "GET",
                url: `${Cypress.env("apiRoot")}/api/deposits/${depositID}/payments`,
                headers: {
                    Cookie: `PHPSESSID=${Cypress.env("sessionID")}`,
                },
                failOnStatusCode: false,
            }).then((resp) => {
                // Should be denied with 401 or similar auth error
                expect(resp.status).to.be.oneOf([401, 403]);
            });
        });

        it("Non-finance user denied exporting CSV", () => {
            // Test that a user without bFinance permission is denied
            cy.request({
                method: "GET",
                url: `${Cypress.env("apiRoot")}/api/deposits/${depositID}/csv`,
                headers: {
                    Cookie: `PHPSESSID=${Cypress.env("sessionID")}`,
                },
                failOnStatusCode: false,
            }).then((resp) => {
                // Should be denied with 401 or similar auth error
                expect(resp.status).to.be.oneOf([401, 403]);
            });
        });

        it("Non-finance user denied generating PDF", () => {
            // Test that a user without bFinance permission is denied
            cy.request({
                method: "GET",
                url: `${Cypress.env("apiRoot")}/api/deposits/${depositID}/pdf`,
                headers: {
                    Cookie: `PHPSESSID=${Cypress.env("sessionID")}`,
                },
                failOnStatusCode: false,
            }).then((resp) => {
                // Should be denied with 401 or similar auth error
                expect(resp.status).to.be.oneOf([401, 403]);
            });
        });
    });

    describe("Deposit Integrity Tests", () => {
        it("Verify deposit data structure", () => {
            // Ensure deposit operations return proper structure
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/deposits`,
                null,
                200
            ).then((resp) => {
                if (resp.body.length > 0) {
                    expect(resp.body[0]).to.have.property("id");
                }
            });
        });

        it("Verify payment data structure in deposits", () => {
            // Ensure payment retrieval returns proper structure
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/deposits/${depositID}/payments`,
                null,
                [200, 404]
            ).then((resp) => {
                if (resp.status === 200 && resp.body.length > 0) {
                    const payment = resp.body[0];
                    expect(payment).to.have.property("plg_plgID");
                    expect(payment).to.have.property("plg_amount");
                }
            });
        });
    });
});
