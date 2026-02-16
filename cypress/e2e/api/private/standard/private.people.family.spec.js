/// <reference types="cypress" />

/**
 * API tests for Family endpoints
 * Tests validate that family phone field removal (fam_CellPhone, fam_WorkPhone)
 * does not break API functionality
 */
describe("API Private Family", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    describe("GET /api/families/latest - Latest Families", () => {
        it("Returns 200 with families data", () => {
            cy.makePrivateAdminAPICall("GET", "/api/families/latest", null, 200).then(
                (response) => {
                    // Response should contain families data
                    expect(response.body).to.exist;
                },
            );
        });
    });

    describe("GET /api/families/updated - Updated Families", () => {
        it("Returns 200 with families data", () => {
            cy.makePrivateAdminAPICall("GET", "/api/families/updated", null, 200).then(
                (response) => {
                    expect(response.body).to.exist;
                },
            );
        });
    });

    describe("GET /api/families/search/{query} - Search Families", () => {
        it("Returns 200 with matching families", () => {
            cy.makePrivateAdminAPICall("GET", "/api/families/search/Smith", null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("Families");
                    expect(response.body.Families).to.be.an("array");
                },
            );
        });

        it("Returns empty array for no matches", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/families/search/ZZZZNONEXISTENT",
                null,
                200,
            ).then((response) => {
                expect(response.body).to.have.property("Families");
                expect(response.body.Families).to.be.an("array");
                expect(response.body.Families).to.have.lengthOf(0);
            });
        });
    });

    describe("GET /api/families/email/without - Families Without Email", () => {
        it("Returns 200 with count and families array", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/families/email/without",
                null,
                200,
            ).then((response) => {
                expect(response.body).to.have.property("count");
                expect(response.body).to.have.property("families");
                expect(response.body.families).to.be.an("array");
                expect(response.body.count).to.be.a("number");
            });
        });
    });

    describe("GET /api/family/{id} - Get Family by ID", () => {
        it("Returns 200 with family data for valid ID", () => {
            cy.makePrivateAdminAPICall("GET", "/api/family/1", null, 200).then(
                (response) => {
                    expect(response.body).to.have.property("Id");
                    expect(response.body).to.have.property("Name");
                    expect(response.body).to.have.property("Address1");
                    expect(response.body).to.have.property("HomePhone");
                    expect(response.body).to.have.property("Email");
                    // Verify removed fields are NOT present
                    expect(response.body).to.not.have.property("CellPhone");
                    expect(response.body).to.not.have.property("WorkPhone");
                },
            );
        });

        it("Returns error for non-existent family", () => {
            // API returns 412 Precondition Failed for non-existent family
            cy.makePrivateAdminAPICall("GET", "/api/family/99999", null, 412);
        });
    });

    describe("GET /api/family/{id}/nav - Family Navigation", () => {
        it("Returns 200 with prev/next family IDs", () => {
            cy.makePrivateAdminAPICall("GET", "/api/family/2/nav", null, 200).then(
                (response) => {
                    // Response should have navigation info
                    expect(response.body).to.exist;
                },
            );
        });
    });

    describe("GET /api/families/anniversaries - Family Anniversaries", () => {
        it("Returns 200 with anniversary data", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/families/anniversaries",
                null,
                200,
            ).then((response) => {
                expect(response.body).to.exist;
            });
        });
    });

    describe("GET /api/families/self-register - Self-Registered Families", () => {
        it("Returns 200 with families array", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/families/self-register",
                null,
                200,
            ).then((response) => {
                expect(response.body).to.have.property("families");
                expect(response.body.families).to.be.an("array");
            });
        });
    });

    describe("GET /api/families/self-verify - Self-Verified Families", () => {
        it("Returns 200 with families array", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/families/self-verify",
                null,
                200,
            ).then((response) => {
                expect(response.body).to.have.property("families");
                expect(response.body.families).to.be.an("array");
            });
        });
    });

    describe("GET /api/families/pending-self-verify - Pending Verification", () => {
        it("Returns 200 with families array", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/families/pending-self-verify",
                null,
                200,
            ).then((response) => {
                expect(response.body).to.have.property("families");
                expect(response.body.families).to.be.an("array");
            });
        });
    });

    describe("POST /api/family/{id}/activate/{status} - Activate/Deactivate Family", () => {
        it("Activate endpoint responds without error", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/family/1/activate/true",
                null,
                200,
            ).then((response) => {
                expect(response.body).to.have.property("success");
                expect(response.body.success).to.be.true;
            });
        });
    });
});
