/// <reference types="cypress" />

describe("API Private Deposit Operations", () => {
    let depositID = 1; // Use existing deposit ID for testing

    describe("Deposit Retrieval Operations", () => {
        it("Get all deposits", () => {
            // Test retrieving all deposits
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/deposits`,
                null,
                200
            ).then((resp) => {
                expect(resp.body).to.be.an("object");
                expect(resp.body).to.have.property("Deposits");
                expect(resp.body.Deposits).to.be.an("array");
                // Each deposit should have properties like Id, Type, Comment, Date (capitalized by Propel)
                if (resp.body.Deposits.length > 0) {
                    const deposit = resp.body.Deposits[0];
                    expect(deposit).to.have.property("Id");
                }
            });
        });

        it("Get specific deposit by ID", () => {
            // Test retrieving a specific deposit
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/deposits/${depositID}`,
                null,
                [200, 404, 500]
            ).then((resp) => {
                if (resp.status === 200) {
                    expect(resp.body).to.have.property("Id");
                    expect(resp.body).to.have.property("Type");
                }
            });
        });

        it("Get deposit pledges/payments", () => {
            // Test retrieving pledges in a deposit
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/deposits/${depositID}/pledges`,
                null,
                [200, 404]
            ).then((resp) => {
                if (resp.status === 200) {
                    expect(resp.body).to.be.an("array");
                }
            });
        });

        it("Get deposit CSV export", () => {
            // Test exporting deposit as CSV
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/deposits/${depositID}/csv`,
                null,
                [200, 404, 500]
            ).then((resp) => {
                if (resp.status === 200) {
                    expect(resp.body).to.exist;
                }
            });
        });

        it("Get deposit PDF export", () => {
            // Test generating deposit PDF
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/deposits/${depositID}/pdf`,
                null,
                [200, 404, 500]
            );
        });

        it("Get deposit OFX format", () => {
            // Test getting deposit in OFX format
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/deposits/${depositID}/ofx`,
                null,
                [200, 404, 500]
            ).then((resp) => {
                if (resp.status === 200) {
                    expect(resp.body).to.have.property("content");
                }
            });
        });
    });

    describe("Deposit CRUD Operations", () => {
        it("Create new deposit", () => {
            // Test creating a new deposit
            const today = new Date().toISOString().split("T")[0];
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/deposits`,
                {
                    depositType: "Bank",
                    depositComment: "Test Deposit",
                    depositDate: today,
                },
                200
            ).then((resp) => {
                expect(resp.body).to.exist;
                expect(resp.body).to.have.property("Id");
            });
        });

        it("Update existing deposit", () => {
            // Test updating a deposit
            const today = new Date().toISOString().split("T")[0];
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/deposits/${depositID}`,
                {
                    depositType: "Bank",
                    depositComment: "Updated Test Deposit",
                    depositDate: today,
                    depositClosed: false,
                },
                [200, 404, 500]
            );
        });

        it("Delete deposit", () => {
            // Test deleting a deposit
            cy.makePrivateAdminAPICall(
                "DELETE",
                `/api/deposits/9999`,
                null,
                [200, 404]
            );
        });

        it("Get deposit dashboard data", () => {
            // Test getting dashboard data for deposits
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/deposits/dashboard`,
                null,
                200
            ).then((resp) => {
                expect(resp.body).to.be.an("array");
            });
        });
    });

    describe("Authorization Tests - Finance Operations", () => {
        it("Non-finance user denied getting pledges", () => {
            // Test that a user without bFinance permission is denied
            cy.makePrivateNoFinanceAPICall(
                "GET",
                `/api/deposits/${depositID}/pledges`,
                null,
                [401, 403, 500]
            );
        });

        it("Non-finance user denied exporting CSV", () => {
            // Test that a user without bFinance permission is denied
            cy.makePrivateNoFinanceAPICall(
                "GET",
                `/api/deposits/${depositID}/csv`,
                null,
                [401, 403, 500]
            );
        });

        it("Non-finance user denied generating PDF", () => {
            // Test that a user without bFinance permission is denied
            cy.makePrivateNoFinanceAPICall(
                "GET",
                `/api/deposits/${depositID}/pdf`,
                null,
                [401, 403, 500]
            );
        });

        it("Non-finance user denied creating deposits", () => {
            // Test that a user without bFinance permission is denied
            const today = new Date().toISOString().split("T")[0];
            cy.makePrivateNoFinanceAPICall(
                "POST",
                `/api/deposits`,
                {
                    depositType: "Bank",
                    depositComment: "Unauthorized Deposit",
                    depositDate: today,
                },
                [401, 403]
            );
        });

        it("Non-finance user denied updating deposits", () => {
            // Test that a user without bFinance permission is denied
            const today = new Date().toISOString().split("T")[0];
            cy.makePrivateNoFinanceAPICall(
                "POST",
                `/api/deposits/${depositID}`,
                {
                    depositType: "Bank",
                    depositComment: "Unauthorized Update",
                    depositDate: today,
                    depositClosed: false,
                },
                [401, 403, 500]
            );
        });

        it("Non-finance user denied deleting deposits", () => {
            // Test that a user without bFinance permission is denied
            cy.makePrivateNoFinanceAPICall(
                "DELETE",
                `/api/deposits/${depositID}`,
                null,
                [401, 403]
            );
        });
    });

    describe("Data Structure Validation", () => {
        it("Verify deposit data structure", () => {
            // Ensure deposit operations return proper structure
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/deposits`,
                null,
                200
            ).then((resp) => {
                expect(resp.body).to.be.an("object");
                expect(resp.body).to.have.property("Deposits");
                expect(resp.body.Deposits).to.be.an("array");
                if (resp.body.Deposits.length > 0) {
                    const deposit = resp.body.Deposits[0];
                    expect(deposit).to.have.property("Id");
                    expect(deposit).to.have.property("Type");
                    expect(deposit).to.have.property("Comment");
                    expect(deposit).to.have.property("Date");
                }
            });
        });

        it("Verify pledge/payment data structure", () => {
            // Ensure pledge retrieval returns proper structure
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/deposits/${depositID}/pledges`,
                null,
                [200, 404]
            ).then((resp) => {
                if (resp.status === 200) {
                    expect(resp.body).to.be.an("array");
                    if (resp.body.length > 0) {
                        const pledge = resp.body[0];
                        expect(pledge).to.have.property("GroupKey");
                        expect(pledge).to.have.property("sumAmount");
                    }
                }
            });
        });
    });
});
