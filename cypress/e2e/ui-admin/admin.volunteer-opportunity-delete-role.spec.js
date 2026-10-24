/// <reference types="cypress" />

// #10688: the legacy editor is Admin-only, while DeleteRecords is specifically
// for deleting people and families. Use an owned fixture, never seeded data.
describe("Admin manages a legacy volunteer opportunity", () => {
    let opportunityId;
    const opportunityName = `Volunteer Delete ${Date.now()}`;

    before(() => {
        cy.setupAdminSession();
        cy.request({
            method: "POST",
            url: "/admin/api/volunteer-opportunities",
            body: { name: opportunityName, description: "RBAC delete regression", active: true },
        }).then(({ status, body }) => {
            expect(status).to.eq(201);
            opportunityId = body.volunteerOpportunity.id;
        });
    });

    after(() => {
        if (!opportunityId) return;
        cy.setupAdminSession();
        cy.request({
            method: "DELETE",
            url: `/admin/api/volunteer-opportunities/${opportunityId}`,
            failOnStatusCode: false,
        }).its("status").should("be.oneOf", [200, 404]);
    });

    it("refuses a non-admin attempting the delete confirmation", () => {
        cy.setupStandardSession();
        cy.request({
            url: `/VolunteerOpportunityEditor.php?act=delete&Opp=${opportunityId}`,
            followRedirect: false,
            failOnStatusCode: false,
        }).then(({ status, headers }) => {
            expect(status).to.eq(302);
            expect(headers.location).to.not.include("VolunteerOpportunityEditor.php");
        });
    });

    it("lets an admin confirm and delete an opportunity through the editor", () => {
        cy.setupAdminSession();
        cy.visit(`/VolunteerOpportunityEditor.php?act=delete&Opp=${opportunityId}`);
        cy.contains("Volunteer Opportunity Delete Confirmation");
        cy.contains(opportunityName);
        cy.get('form input[name="act"][value="ConfDelete"]')
            .closest("form")
            .within(() => {
                cy.get('input[name="Opp"]').should("have.value", String(opportunityId));
                cy.get('input[name="csrf_token"]').should("exist");
            });
        cy.get('form input[name="act"][value="ConfDelete"]').closest("form").submit();
        cy.request({
            url: `/admin/api/volunteer-opportunities/${opportunityId}`,
            failOnStatusCode: false,
        }).its("status").should("eq", 404);
    });
});
