/// <reference types="cypress" />

/**
 * Authorization matrix for every route registered under /admin/api.
 *
 * The admin app applies AdminRoleAuthMiddleware to all of its routes, so a
 * non-admin API key must get 403 and an invalid key 401 before any handler
 * runs. Keep ROUTES in sync with the require lines in src/admin/index.php:
 * a new /admin/api route needs a row here.
 *
 * Backup/restore/download success paths are exercised by
 * cypress/e2e/new-system/03-backup-restore.spec.js.
 */
const ROUTES = [
    ["POST", "/admin/api/admin/birthday-emails/test"],
    ["GET", "/admin/api/database/people/export/chmeetings"],
    ["POST", "/admin/api/database/backup"],
    ["POST", "/admin/api/database/backupRemote"],
    ["POST", "/admin/api/database/restore"],
    ["GET", "/admin/api/database/download/none.tar.gz"],
    ["DELETE", "/admin/api/database/reset"],
    ["POST", "/admin/api/demo/load"],
    ["GET", "/admin/api/import/csv/families"],
    ["POST", "/admin/api/import/csv/upload"],
    ["POST", "/admin/api/import/csv/execute"],
    ["POST", "/admin/api/map/geocode-all"],
    ["GET", "/admin/api/options/1"],
    ["POST", "/admin/api/options/1"],
    ["PATCH", "/admin/api/options/1/1"],
    ["DELETE", "/admin/api/options/1/1"],
    ["POST", "/admin/api/options/1/1/reorder"],
    ["POST", "/admin/api/options/1/1/default"],
    ["POST", "/admin/api/options/1/1/inactive"],
    ["POST", "/admin/api/options/1/1/directory"],
    ["GET", "/admin/api/orphaned-files"],
    ["POST", "/admin/api/orphaned-files/delete-all"],
    ["GET", "/admin/api/system/church-logo"],
    ["POST", "/admin/api/system/church-logo"],
    ["DELETE", "/admin/api/system/church-logo"],
    ["GET", "/admin/api/system/config/sChurchName"],
    ["POST", "/admin/api/system/config/sChurchName"],
    ["GET", "/admin/api/system/custom-fields/person"],
    ["GET", "/admin/api/system/properties/person"],
    ["POST", "/admin/api/system/logs/loglevel"],
    ["DELETE", "/admin/api/system/logs"],
    ["GET", "/admin/api/system/logs/none.log"],
    ["GET", "/admin/api/system/logs/none.log/download"],
    ["DELETE", "/admin/api/system/logs/none.log"],
    ["POST", "/admin/api/system/telemetry-consent"],
    ["GET", "/admin/api/volunteer-opportunities"],
    ["POST", "/admin/api/volunteer-opportunities"],
    ["GET", "/admin/api/volunteer-opportunities/1"],
    ["PUT", "/admin/api/volunteer-opportunities/1"],
    ["DELETE", "/admin/api/volunteer-opportunities/1"],
    ["GET", "/admin/api/upgrade/preview"],
    ["GET", "/admin/api/upgrade/download-latest-release"],
    ["POST", "/admin/api/upgrade/do-upgrade"],
    ["POST", "/admin/api/upgrade/refresh-upgrade-info"],
    ["POST", "/admin/api/user/1/password/reset"],
    ["POST", "/admin/api/user/1/disableTwoFactor"],
    ["POST", "/admin/api/user/1/login/reset"],
    ["DELETE", "/admin/api/user/1/"],
    ["GET", "/admin/api/user/1/permissions"],
];

describe("Admin API authorization matrix", () => {
    // Several of these routes are irreversible (database reset, upgrade, demo
    // load). Stop before sending any of them if the app-wide guard is not
    // answering 403 to a non-admin key.
    before(() => {
        cy.makePrivateUserAPICall("GET", "/admin/api/system/properties/person", null, 403);
    });

    it("covers every admin API route", () => {
        expect(ROUTES).to.have.length(48);
    });

    describe("non-admin API key is refused (403)", () => {
        ROUTES.forEach(([method, url]) => {
            it(`${method} ${url}`, () => {
                cy.makePrivateUserAPICall(method, url, null, 403).then((response) => {
                    expect(response.body.success).to.eq(false);
                });
            });
        });
    });

    describe("invalid API key is rejected (401)", () => {
        ROUTES.forEach(([method, url]) => {
            it(`${method} ${url}`, () => {
                cy.makePrivateAPICall("not-a-real-api-key", method, url, null, 401).then(
                    (response) => {
                        expect(response.body.success).to.eq(false);
                        expect(response.body.message).to.eq("Invalid API key");
                    },
                );
            });
        });
    });
});
