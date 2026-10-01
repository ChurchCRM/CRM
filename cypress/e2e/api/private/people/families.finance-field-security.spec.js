/// <reference types="cypress" />

/**
 * Security test: Finance-sensitive family fields are hidden from non-Finance users.
 * Issue #10199: ScanCheck, ScanCredit, and Envelope should only appear in API
 * responses when the authenticated user has Finance permission.
 */

describe("Family Finance Field Security (issue #10199)", () => {
    let testFamilyId = null;

    before(() => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/families",
            {
                Name: "CypressTest FinanceFieldSecurity",
                Address1: "1 Security Test Lane",
                City: "Testville",
                State: "TS",
                Country: "US",
                Zip: "00000",
            },
            201,
        ).then((response) => {
            testFamilyId = response.body.Id;
        });
    });

    after(() => {
        if (testFamilyId) {
            cy.makePrivateAdminAPICall(
                "DELETE",
                `/api/family/${testFamilyId}?deleteMembers=true`,
                "",
                200,
            );
        }
    });

    describe("Non-Finance User", () => {
        it("GET /api/family/{id} does not expose finance fields", () => {
            cy.makePrivateAPICall(
                "GET",
                `/api/family/${testFamilyId}`,
                "",
                200,
            ).then((response) => {
                expect(response.body).not.to.have.property("ScanCheck");
                expect(response.body).not.to.have.property("ScanCredit");
                expect(response.body).not.to.have.property("Envelope");
            });
        });

        it("GET /api/families does not expose finance fields", () => {
            cy.makePrivateAPICall(
                "GET",
                "/api/families",
                "",
                200,
            ).then((response) => {
                const testFamily = response.body.find((f) => f.Id === testFamilyId);
                if (testFamily) {
                    expect(testFamily).not.to.have.property("ScanCheck");
                    expect(testFamily).not.to.have.property("ScanCredit");
                    expect(testFamily).not.to.have.property("Envelope");
                }
            });
        });
    });

    describe("Finance User", () => {
        it("GET /api/family/{id} exposes finance fields", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/family/${testFamilyId}`,
                "",
                200,
            ).then((response) => {
                expect(response.body).to.have.property("ScanCheck");
                expect(response.body).to.have.property("ScanCredit");
                expect(response.body).to.have.property("Envelope");
            });
        });

        it("GET /api/families exposes finance fields", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/families",
                "",
                200,
            ).then((response) => {
                const testFamily = response.body.find((f) => f.Id === testFamilyId);
                expect(testFamily).to.have.property("ScanCheck");
                expect(testFamily).to.have.property("ScanCredit");
                expect(testFamily).to.have.property("Envelope");
            });
        });
    });
});
