/// <reference types="cypress" />

/**
 * #8672: a user's settings go away with the user.
 *
 * user_settings has a foreign key to user_usr with ON DELETE CASCADE, and the
 * 7.8.5 upgrade removes the rows earlier deletes left behind before it adds the
 * key. Foreign keys and the upgrade block have no HTTP surface, so this is a
 * schema spec (cypress/db-access.json): it reads the schema in SQL and runs the
 * shipped block of src/mysql/upgrade/7.8.5-user-settings-cascade.sql.
 */
const BLOCK = { script: "7.8.5-user-settings-cascade.sql", block: "user settings follow their user" };
const PERSON = 83; // Terry Cooper: no seeded login, used by no other spec
const NO_SUCH_USER = 999999;

const db = (sql, params = []) =>
    cy.dbQuery(sql, params).then((result) => {
        expect(result.error, sql).to.be.null;
        return result.rows;
    });

const runBlock = () => cy.task("db:upgradeBlock", BLOCK).its("error").should("be.null");
const settingsOf = (userId) => db("SELECT COUNT(*) AS n FROM user_settings WHERE user_id = ?", [userId]).then((rows) => Number(rows[0].n));

const cascadeRule = () =>
    db(
        "SELECT DELETE_RULE AS rule FROM information_schema.REFERENTIAL_CONSTRAINTS " +
            "WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'user_settings' AND CONSTRAINT_NAME = 'fk_user_settings_user'",
    ).then((rows) => rows[0]?.rule ?? null);

const userIdColumn = () =>
    db(
        "SELECT DATA_TYPE AS type, COLUMN_TYPE AS columnType FROM information_schema.COLUMNS " +
            "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_settings' AND COLUMN_NAME = 'user_id'",
    ).its(0);

describe("user_settings follow their user (#8672)", () => {
    after(() => {
        // Leave the schema as Install.sql makes it, whatever a failed test left behind.
        db("DELETE FROM user_settings WHERE user_id IN (?, ?)", [NO_SUCH_USER, PERSON]);
        runBlock();
        db("DELETE FROM user_usr WHERE usr_per_ID = ?", [PERSON]);
    });

    it("refuses a setting for a login that does not exist", () => {
        cy.dbQuery("INSERT INTO user_settings (user_id, setting_name, setting_value) VALUES (?, 'ui.style', 'auto')", [NO_SUCH_USER]).then((result) => {
            expect(result.error, "the insert is refused").to.not.be.null;
            expect(result.error.code).to.eq("ER_NO_REFERENCED_ROW_2");
        });
    });

    it("deletes a user's settings when the user is deleted", () => {
        db("DELETE FROM user_usr WHERE usr_per_ID = ?", [PERSON]);
        db("INSERT INTO user_usr (usr_per_ID, usr_Password, usr_UserName) VALUES (?, '', ?)", [PERSON, `cascade${Date.now()}`]);
        db("INSERT INTO user_settings (user_id, setting_name, setting_value) VALUES (?, 'ui.style', 'auto'), (?, 'ui.table.size', '25')", [PERSON, PERSON]);
        settingsOf(PERSON).should("eq", 2);

        cy.makePrivateAdminAPICall("DELETE", `/admin/api/user/${PERSON}/`, null, 200);
        settingsOf(PERSON).should("eq", 0);
    });

    describe("the 7.8.5 upgrade", () => {
        let adminSettings;

        beforeEach(() => {
            // The table as 7.8.4 and earlier made it: a signed int(11) user_id, no key,
            // and rows for logins that no longer exist.
            db("ALTER TABLE user_settings DROP FOREIGN KEY fk_user_settings_user");
            db("ALTER TABLE user_settings MODIFY user_id int(11) NOT NULL");
            db("INSERT INTO user_settings (user_id, setting_name, setting_value) VALUES (?, 'ui.style', 'auto'), (-5, 'ui.style', 'auto')", [NO_SUCH_USER]);
            settingsOf(1).then((n) => {
                adminSettings = n;
            });
        });

        it("removes the orphaned settings and adds the cascade", () => {
            runBlock();
            settingsOf(NO_SUCH_USER).should("eq", 0);
            settingsOf(-5).should("eq", 0);
            cy.then(() => settingsOf(1).should("eq", adminSettings));
            userIdColumn().then(({ type, columnType }) => {
                expect(type).to.eq("mediumint");
                expect(columnType).to.contain("unsigned");
            });
            cascadeRule().should("eq", "CASCADE");
        });

        it("is safe to run again", () => {
            runBlock();
            runBlock();
            settingsOf(NO_SUCH_USER).should("eq", 0);
            cy.then(() => settingsOf(1).should("eq", adminSettings));
            cascadeRule().should("eq", "CASCADE");
        });
    });
});
