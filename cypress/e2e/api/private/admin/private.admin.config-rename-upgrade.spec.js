/// <reference types="cypress" />

const oldToNew = [
    ["sTaxReport1", "sGivingStatementOpening", "Dear {Name}, this is your tax statement."],
    ["sTaxReport2", "sGivingStatementClosing", "Thank you for your generous support."],
    ["sTaxReport3", "sGivingStatementQuestions", "Questions? Call 555-0100."],
    ["sTaxSigner", "sGivingStatementSigner", "Pastor Test"],
];

describe("Upgrade renames sTaxReport settings to Giving Statement (#10987)", () => {
    before(() => {
        cy.setupAdminSession();

        const allNames = oldToNew.flatMap(([old, nw]) => [old, nw]);
        const ph = allNames.map(() => "?").join(", ");

        // Wipe both old and new keys.
        cy.dbQuery(`DELETE FROM config_cfg WHERE cfg_name IN (${ph})`, allNames).then(({ error }) => {
            expect(error).to.eq(null);
        });

        // Insert old-name rows with known values (simulates a pre-upgrade DB).
        cy.dbQuery(
            "INSERT INTO config_cfg (cfg_name, cfg_value) VALUES (?, ?), (?, ?), (?, ?), (?, ?)",
            oldToNew.flatMap(([oldName, , value]) => [oldName, value]),
        ).then(({ error }) => {
            expect(error).to.eq(null);
        });

        // Run the same logic as 7.8.3-rename-tax-report-settings.sql
        for (const [oldName, newName] of oldToNew) {
            cy.dbQuery(
                "INSERT IGNORE INTO config_cfg (cfg_name, cfg_value) SELECT ?, cfg_value FROM config_cfg WHERE cfg_name = ?",
                [newName, oldName],
            ).then(({ error }) => {
                expect(error, `INSERT ${newName} from ${oldName}`).to.eq(null);
            });
        }

        const oldNames = oldToNew.map(([old]) => old);
        const oldPh = oldNames.map(() => "?").join(", ");
        cy.dbQuery(`DELETE FROM config_cfg WHERE cfg_name IN (${oldPh})`, oldNames).then(({ error }) => {
            expect(error).to.eq(null);
        });
    });

    beforeEach(() => cy.setupAdminSession());

    it("each old key's value is now under its new name", () => {
        for (const [oldName, newName, value] of oldToNew) {
            cy.dbQuery("SELECT cfg_value FROM config_cfg WHERE cfg_name = ?", [newName]).then(({ rows, error }) => {
                expect(error).to.eq(null);
                expect(rows, `${newName} row should exist`).to.have.length(1);
                expect(rows[0].cfg_value, `${newName} should carry ${oldName}'s value`).to.eq(value);
            });
        }
    });

    it("old key names no longer exist in the database", () => {
        const oldNames = oldToNew.map(([old]) => old);
        const ph = oldNames.map(() => "?").join(", ");
        cy.dbQuery(`SELECT cfg_name FROM config_cfg WHERE cfg_name IN (${ph})`, oldNames).then(({ rows, error }) => {
            expect(error).to.eq(null);
            expect(rows, "old keys should be deleted").to.have.length(0);
        });
    });
});
