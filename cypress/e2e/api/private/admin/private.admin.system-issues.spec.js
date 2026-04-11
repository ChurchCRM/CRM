/// <reference types="cypress" />

describe("API System Issues", () => {
    it("Issues endpoint requires authentication", () => {
        // Unauthenticated request should be rejected
        cy.apiRequest({
            method: "POST",
            url: "/api/issues",
            headers: { "content-type": "application/json" },
            body: {
                pageName: "test",
                screenSize: { height: 1080, width: 1920 },
                windowSize: { height: 900, width: 1440 },
                pageSize: { height: 2000, width: 1440 },
            },
            failOnStatusCode: false,
        }).then((resp) => {
            // Should return 401 — requires authentication
            expect(resp.status).to.eq(401);
        });
    });

    it("Issues endpoint returns diagnostics for authenticated users", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/issues",
            {
                pageName: "TestPage",
                screenSize: { height: 1080, width: 1920 },
                windowSize: { height: 900, width: 1440 },
                pageSize: { height: 2000, width: 1440 },
            },
            200,
        ).then((resp) => {
            expect(resp.body).to.have.property("issueBody");
            expect(resp.body.issueBody).to.contain("TestPage");
            expect(resp.body.issueBody).to.contain("ChurchCRM Version");
        });
    });

    it("Issues endpoint works for standard (non-admin) users", () => {
        cy.makePrivateStandardAPICall(
            "POST",
            "/api/issues",
            {
                pageName: "StandardUserPage",
                screenSize: { height: 1080, width: 1920 },
                windowSize: { height: 900, width: 1440 },
                pageSize: { height: 2000, width: 1440 },
            },
            200,
        ).then((resp) => {
            expect(resp.body).to.have.property("issueBody");
            expect(resp.body.issueBody).to.contain("StandardUserPage");
        });
    });
});
