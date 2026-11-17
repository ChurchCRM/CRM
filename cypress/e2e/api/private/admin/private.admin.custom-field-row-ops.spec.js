/// <reference types="cypress" />

/**
 * API tests for reordering and deleting person and family custom fields
 *
 * Covers:
 *   PUT    /admin/api/system/custom-fields/{person|family}/{field}/order
 *   DELETE /admin/api/system/custom-fields/{person|family}/{field}
 *
 * Seeded fields c1 and c2 hold display order 1 and 2 for both record types.
 */
["person", "family"].forEach((recordType) => {
    const base = `/admin/api/system/custom-fields/${recordType}`;

    describe(`Admin API ${recordType} custom field row operations`, () => {
        it("moves a field up and back down, refusing moves past the top", () => {
            cy.makePrivateAdminAPICall("PUT", `${base}/c2/order`, { direction: "up" }, 200).then((resp) => {
                expect(resp.body.success).to.eq(true);
            });
            cy.makePrivateAdminAPICall("PUT", `${base}/c2/order`, { direction: "up" }, 400).then((resp) => {
                expect(resp.body.message).to.eq("Cannot move field in that direction");
            });
            cy.makePrivateAdminAPICall("PUT", `${base}/c2/order`, { direction: "down" }, 200);
            cy.makePrivateAdminAPICall("PUT", `${base}/c1/order`, { direction: "up" }, 400);
        });

        it("rejects a missing or unknown direction", () => {
            cy.makePrivateAdminAPICall("PUT", `${base}/c2/order`, {}, 400);
            cy.makePrivateAdminAPICall("PUT", `${base}/c2/order`, { direction: "sideways" }, 400);
        });

        it("returns 404 for a field that does not exist", () => {
            cy.makePrivateAdminAPICall("PUT", `${base}/c9999/order`, { direction: "up" }, 404);
            cy.makePrivateAdminAPICall("DELETE", `${base}/c9999`, null, 404);
        });

        it("returns 404 for a field name that is not a custom field column", () => {
            cy.makePrivateAdminAPICall("PUT", `${base}/per_ID/order`, { direction: "up" }, 404);
            cy.makePrivateAdminAPICall("DELETE", `${base}/per_ID`, null, 404);
        });

        it("refuses a user without the Admin role", () => {
            cy.makePrivateEditRecordsAPICall("PUT", `${base}/c2/order`, { direction: "up" }, 403);
            cy.makePrivateEditRecordsAPICall("DELETE", `${base}/c2`, null, 403);
            cy.makePrivateAdminAPICall("PUT", `${base}/c1/order`, { direction: "up" }, 400);
        });
    });
});

describe("Admin API custom field row operations: record type", () => {
    it("returns 404 for a record type other than person or family", () => {
        cy.makePrivateAdminAPICall("PUT", "/admin/api/system/custom-fields/group/c1/order", { direction: "up" }, 404);
        cy.makePrivateAdminAPICall("DELETE", "/admin/api/system/custom-fields/group/c1", null, 404);
    });
});
