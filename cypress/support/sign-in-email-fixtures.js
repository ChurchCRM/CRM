// Fixtures for Fix shared emails and Confirm selected (#10463, design §4, §9).
// An address moved from the family email is written only by the 7.8.3
// upgrade, and a replacement address only after an email sign-in; a personal
// email is set only by the legacy person editor form, which also audits and
// mails. So they are set here, and each spec's people are put back.

const db = (sql, params = []) =>
    cy.dbQuery(sql, params, { fixture: "cypress/support/sign-in-email-fixtures.js" }).then((result) => {
        expect(result.error, sql).to.be.null;
        return result.rows;
    });

/** Sets personal emails: `{ personId: email }`. */
export const setPersonEmails = (emails) => {
    Object.entries(emails).forEach(([personId, email]) => {
        db("UPDATE person_per SET per_Email = ? WHERE per_ID = ?", [email, Number(personId)]);
    });
};

/** Leaves the person's current email unconfirmed, as the family email move (`family_email`) or a staff change after a sign-in (`staff`) does. */
export const markEmailUnconfirmed = (personId, source = "family_email") =>
    db(
        `INSERT INTO person_signin_psi (psi_per_ID, psi_UnconfirmedEmail, psi_UnconfirmedSource, psi_UnconfirmedAt)
         SELECT per_ID, per_Email, ?, NOW() FROM person_per WHERE per_ID = ?
         ON DUPLICATE KEY UPDATE psi_UnconfirmedEmail = VALUES(psi_UnconfirmedEmail),
             psi_UnconfirmedSource = VALUES(psi_UnconfirmedSource), psi_UnconfirmedAt = VALUES(psi_UnconfirmedAt)`,
        [source, personId],
    );

/** Leaves the person's current email unconfirmed as a staff change does after the person signed in with `provenEmail`. */
export const markReplacedAfterSignIn = (personId, provenEmail) =>
    db(
        `INSERT INTO person_signin_psi (psi_per_ID, psi_ProvenEmail, psi_ProvenAt, psi_UnconfirmedEmail, psi_UnconfirmedSource, psi_UnconfirmedAt)
         SELECT per_ID, ?, NOW() - INTERVAL 30 DAY, per_Email, 'staff', NOW() FROM person_per WHERE per_ID = ?
         ON DUPLICATE KEY UPDATE psi_ProvenEmail = VALUES(psi_ProvenEmail), psi_ProvenAt = VALUES(psi_ProvenAt),
             psi_UnconfirmedEmail = VALUES(psi_UnconfirmedEmail), psi_UnconfirmedSource = VALUES(psi_UnconfirmedSource),
             psi_UnconfirmedAt = VALUES(psi_UnconfirmedAt)`,
        [provenEmail, personId],
    );

/** Puts the seed emails back (`{ personId: email }`) and removes those people's sign-in rows, remembered devices and given grants. */
export const restorePeople = (emails) => {
    setPersonEmails(emails);
    const ids = Object.keys(emails).map(Number);
    const marks = ids.map(() => "?").join(",");
    db(`DELETE FROM person_signin_psi WHERE psi_per_ID IN (${marks})`, ids);
    db(`DELETE FROM person_device_pdv WHERE pdv_per_ID IN (${marks})`, ids);
    db(`DELETE FROM portal_grant_pgr WHERE pgr_per_ID IN (${marks})`, ids);
};
