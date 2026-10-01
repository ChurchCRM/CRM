/// <reference types="cypress" />

/**
 * API tests for the admin database maintenance endpoints that are not
 * backup/restore (those run in cypress/e2e/new-system/03-backup-restore.spec.js)
 *
 * Covers:
 *   GET  /admin/api/database/people/export/chmeetings
 *   POST /admin/api/database/backupRemote
 */
describe("Admin API Database Endpoints", () => {
    describe("GET /admin/api/database/people/export/chmeetings", () => {
        it("downloads a ChMeetings-format CSV", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/admin/api/database/people/export/chmeetings",
                null,
                200,
            ).then((resp) => {
                expect(resp.headers["content-type"]).to.include("text/csv");
                expect(resp.headers["content-disposition"]).to.match(
                    /attachment; filename="ChMeetings-.*\.csv"/,
                );
                expect(resp.body).to.be.a("string");
                expect(resp.body.split("\n")[0]).to.include('"First Name"');
            });
        });
    });

    describe("POST /admin/api/database/backupRemote", () => {
        // The seed leaves the external-backup plugin disabled
        it("returns 400 while the External Backup plugin is not enabled", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/admin/api/database/backupRemote",
                { BackupType: 3 },
                400,
            ).then((resp) => {
                expect(resp.body.success).to.eq(false);
                expect(resp.body.message).to.include("External Backup plugin is not enabled");
            });
        });
    });
});
