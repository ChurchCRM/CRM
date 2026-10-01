/// <reference types="cypress" />

/**
 * API tests for deleting orphaned files
 *
 * Covers:
 *   POST /admin/api/orphaned-files/delete-all
 *
 * The endpoint removes every orphaned file it finds, so the test only calls it
 * when the listing is empty and there is nothing to lose.
 */
describe("Admin API Orphaned Files delete-all", () => {
    it("reports an empty result when there are no orphaned files", function () {
        cy.makePrivateAdminAPICall("GET", "/admin/api/orphaned-files", null, 200).then((list) => {
            if (list.body.count !== 0) {
                this.skip();
            }
            cy.makePrivateAdminAPICall(
                "POST",
                "/admin/api/orphaned-files/delete-all",
                {},
                200,
            ).then((resp) => {
                expect(resp.body.success).to.eq(true);
                expect(resp.body.deleted).to.deep.eq([]);
                expect(resp.body.failed).to.deep.eq([]);
                expect(resp.body.errors).to.deep.eq([]);
                expect(resp.body.message).to.eq("Deleted 0 files, 0 failed");
            });
        });
    });
});
