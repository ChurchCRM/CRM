/// <reference types="cypress" />

/**
 * API tests for the CSV import upload and execute endpoints
 *
 * Covers:
 *   POST /admin/api/import/csv/upload
 *   POST /admin/api/import/csv/execute
 *
 * The full upload-map-import flow runs through the UI in
 * cypress/e2e/ui-admin/admin.csvimport.spec.js. These tests cover the requests
 * the endpoints reject, which change no data.
 */
describe("Admin API CSV Import Validation", () => {
    describe("POST /admin/api/import/csv/upload", () => {
        it("returns 400 when no file is sent", () => {
            cy.makePrivateAdminAPICall("POST", "/admin/api/import/csv/upload", {}, 400).then(
                (resp) => {
                    expect(resp.body.success).to.eq(false);
                    expect(resp.body.message).to.eq("No file uploaded");
                },
            );
        });
    });

    describe("POST /admin/api/import/csv/execute", () => {
        it("returns 400 when the request has no token or mapping", () => {
            cy.makePrivateAdminAPICall("POST", "/admin/api/import/csv/execute", {}, 400).then(
                (resp) => {
                    expect(resp.body.success).to.eq(false);
                    expect(resp.body.message).to.eq("Invalid import request");
                },
            );
        });

        it("returns 400 for an unknown upload token", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/admin/api/import/csv/execute",
                { token: "not-a-real-token", mapping: {} },
                400,
            ).then((resp) => {
                expect(resp.body.success).to.eq(false);
            });
        });
    });
});
