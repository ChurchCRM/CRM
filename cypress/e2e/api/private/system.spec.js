/// <reference types="cypress" />

describe("Check Notification API", () => {
    it("Admin notification endpoint returns 200", () => {
        cy.makePrivateAdminAPICall(
            "GET",
            "/admin/api/notification",
            "",
            200,
        );
    });

    it("Admin notification endpoint returns a notifications array", () => {
        cy.makePrivateAdminAPICall(
            "GET",
            "/admin/api/notification",
            "",
            200,
        ).then((resp) => {
            expect(resp.body).to.have.property("notifications");
            expect(resp.body.notifications).to.be.an("array");
        });
    });
});
