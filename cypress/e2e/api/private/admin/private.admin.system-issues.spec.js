/// <reference types="cypress" />

describe("API System Issues", () => {
    it("Issues endpoint requires admin authentication", () => {
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
            // Should not be 200 — requires authentication
            expect(resp.status).to.not.eq(200);
        });
    });

    it("Issues endpoint returns diagnostics for admin users", () => {
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

    it("Issues endpoint denied for non-admin users", () => {
        cy.makePrivateStandardAPICall(
            "POST",
            "/api/issues",
            {
                pageName: "TestPage",
                screenSize: { height: 1080, width: 1920 },
                windowSize: { height: 900, width: 1440 },
                pageSize: { height: 2000, width: 1440 },
            },
            403,
        );
    });
});
