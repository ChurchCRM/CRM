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

        it("fills the Grade column with each person's grade name (#10189)", () => {
            const cells = (line) => line.match(/("([^"]|"")*"|[^,]*)(,|$)/g).map((cell) => cell.replace(/,$/, "").replace(/^"|"$/g, ""));
            let personId;
            cy.setupAdminSession();
            cy.visit("/PersonEditor.php");
            cy.get("#FirstName").type("Gil");
            cy.get("#LastName").type("ChmGrade");
            cy.get("#Grade").select("9");
            cy.get("button[name='PersonSubmit']").click();
            cy.url()
                .should("match", /\/people\/view\/\d+/)
                .then((url) => {
                    personId = Number(url.match(/\/people\/view\/(\d+)/)[1]);
                });
            cy.makePrivateAdminAPICall("GET", "/admin/api/database/people/export/chmeetings", null, 200).then((resp) => {
                const lines = resp.body.trim().split(/\r?\n/);
                const header = cells(lines[0]);
                const row = lines.find((line) => line.includes("ChmGrade"));
                expect(row, "exported row for the graded person").to.exist;
                expect(cells(row)[header.indexOf("Grade")]).to.eq("4th");
            });
            cy.then(() => cy.cleanupPeople([personId]));
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
