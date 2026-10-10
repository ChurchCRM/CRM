/// <reference types="cypress" />

/**
 * DELETE /api/family/{id} is blocked when pledge_plg rows exist.
 * Admin has both DeleteRecords and Finance, so a 403 here is the finance
 * guard (DeleteRecordRoleAuthMiddleware already passed). grace.financeonly
 * lacks DeleteRecords and would 403 before that guard.
 *
 * A family with no finance rows still deletes. There is no family-create
 * JSON endpoint, so this spec does not delete a seeded family. That 200
 * path is cypress/e2e/ui/people/standard.members.del.spec.js (TempDelFamily).
 */
describe("API Private Family delete blocked by finance", () => {
    const pledgePayload = (overrides = {}) => ({
        type: "Pledge",
        iMethod: "CASH",
        Date: "2025-10-25",
        FamilyID: "2",
        FYID: 29,
        tScanString: "",
        FundSplit: JSON.stringify([
            {
                FundID: "1",
                Amount: 25.0,
                NonDeductible: 0,
                Comment: "",
            },
        ]),
        ...overrides,
    });

    it("DELETE /api/family/1 returns 403 when the family already has payments", () => {
        // Seed family 1 has pledge rows. Do not delete those payments.
        cy.makePrivateAdminAPICall("DELETE", "/api/family/1", null, 403);
        cy.makePrivateAdminAPICall("GET", "/api/family/1", null, 200);
    });

    it("DELETE /api/family/2 returns 403 after a pledge is added, then the pledge is removed", () => {
        cy.makePrivateAdminAPICall("POST", "/api/payments/pledges", pledgePayload(), 200).then((createResp) => {
            const groupKey = createResp.body.payment.GroupKey;
            expect(groupKey).to.be.a("string").and.to.have.length.greaterThan(0);

            cy.makePrivateAdminAPICall("DELETE", "/api/family/2", null, 403);
            cy.makePrivateAdminAPICall("GET", "/api/family/2", null, 200);
            cy.makePrivateAdminAPICall("DELETE", `/api/payments/${groupKey}`, null, 200);
        });
    });
});
