// Member sign-in audit rows from weeks ago (#10463), for the retention spec. Only
// time makes them, and the audit has no HTTP surface until the admin pages, so
// they are written, read back and removed by id in SQL.

const FILE = "cypress/support/sign-in-audit-retention.js";

const db = (sql, params = []) =>
    cy.dbQuery(sql, params, { fixture: FILE }).then((result) => {
        expect(result.error, sql).to.be.null;
        return result.rows;
    });

/**
 * Adds audit rows `daysAgo` days old: `{ daysAgo, personId, event, email, ip }`.
 * Yields their ids, in order.
 */
export const addAgedAuditRows = (rows) => {
    const ids = [];
    rows.forEach(({ daysAgo, personId = null, event, email = null, ip = null }) => {
        db(
            `INSERT INTO portal_signin_audit_psa (psa_Time, psa_per_ID, psa_Event, psa_Method, psa_Email, psa_IP, psa_Detail)
             VALUES (NOW() - INTERVAL ? DAY, ?, ?, 'email_code', ?, ?, 'retention spec')`,
            [daysAgo, personId, event, email, ip],
        ).then((result) => ids.push(result.insertId));
    });
    return cy.wrap(ids, { log: false });
};

/** The rows with these ids that still exist, by id: `{ [id]: { email, ip } }`. */
export const auditRowsById = (ids) =>
    db(`SELECT psa_ID AS id, psa_Email AS email, psa_IP AS ip FROM portal_signin_audit_psa WHERE psa_ID IN (${ids.map(() => "?").join(",")})`, ids).then(
        (rows) => Object.fromEntries(rows.map((row) => [row.id, { email: row.email, ip: row.ip }])),
    );

/** Removes the rows with these ids. */
export const removeAuditRows = (ids) =>
    ids.length === 0 ? cy.wrap(null, { log: false }) : db(`DELETE FROM portal_signin_audit_psa WHERE psa_ID IN (${ids.map(() => "?").join(",")})`, ids);
