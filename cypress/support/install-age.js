// Install age is time passing: the installer writes the first version_ver row
// and no route moves it.

const FILE = "cypress/support/install-age.js";

const db = (sql, params = []) =>
    cy.dbQuery(sql, params, { fixture: FILE }).then((result) => {
        expect(result.error, sql).to.be.null;
        return result.rows;
    });

/** Yields the first install's start time as stored, for restoreInstallStart(). */
export const captureInstallStart = () =>
    db("SELECT DATE_FORMAT(ver_update_start, '%Y-%m-%d %H:%i:%s') AS start FROM version_ver ORDER BY ver_ID LIMIT 1").then(
        (rows) => rows[0].start,
    );

/** Makes the install `days` days old. */
export const setInstallAgeDays = (days) =>
    db("UPDATE version_ver SET ver_update_start = NOW() - INTERVAL ? DAY ORDER BY ver_ID LIMIT 1", [days]);

export const restoreInstallStart = (start) =>
    db("UPDATE version_ver SET ver_update_start = ? ORDER BY ver_ID LIMIT 1", [start]);
