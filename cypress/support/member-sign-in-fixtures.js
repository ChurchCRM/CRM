// Member sign-in states that no HTTP route writes (#10463, #10377). A passkey is
// registered, and its signature counter moved, only by a WebAuthn ceremony in a browser, a remembered device takes
// its label from the browser that chose to stay signed in, the family email
// move rows come only from the 7.9.0 upgrade, a person waits for review only
// after a registration or a member's proposal, the give API refuses a grant an
// older release or a dropped plugin capability left behind, and the profile page
// stores only a language this installation ships. The rest is time passing: a
// given permission's last day, an expired link or sign-in request, old audit
// rows, past sign-ins and confirmations. Each spec removes what it adds.

const FILE = "cypress/support/member-sign-in-fixtures.js";

const db = (sql, params = []) =>
    cy.dbQuery(sql, params, { fixture: FILE }).then((result) => {
        expect(result.error, sql).to.be.null;
        return result.rows;
    });

const randomHash = () => Array.from({ length: 64 }, () => Math.floor(Math.random() * 16).toString(16)).join("");

/** Adds a passkey for the person; yields its id. */
export const addPasskey = (personId, { label = null } = {}) => {
    const hash = randomHash();
    return db(
        "INSERT INTO person_passkey_ppk (ppk_per_ID, ppk_CredentialId, ppk_CredentialHash, ppk_PublicKey, ppk_Label, ppk_CreatedAt) VALUES (?, ?, ?, 'cypress-key', ?, NOW())",
        [personId, `cypress-${hash.slice(0, 16)}`, hash, label],
    ).its("insertId");
};

/** Sets the signature counter stored for the person's passkeys, as their last sign-in left it. */
export const setPasskeySignCount = (personId, signCount) =>
    db("UPDATE person_passkey_ppk SET ppk_SignCount = ? WHERE ppk_per_ID = ?", [signCount, personId]);

/** Adds a remembered device for the person; yields its id. `signedOut` leaves it revoked, as Sign out does. */
export const addRememberedDevice = (personId, { label = null, expiresInDays = 30, signedOut = false } = {}) =>
    db(
        `INSERT INTO person_device_pdv (pdv_per_ID, pdv_TokenHash, pdv_Kind, pdv_Label, pdv_CreatedAt, pdv_LastUsedAt, pdv_ExpiresAt, pdv_RevokedAt)
         VALUES (?, ?, 'browser', ?, NOW(), NOW(), NOW() + INTERVAL ? DAY, ${signedOut ? "NOW()" : "NULL"})`,
        [personId, randomHash(), label, expiresInDays],
    ).its("insertId");

/** Removes remembered devices added with addRememberedDevice. */
export const removeRememberedDevices = (deviceIds) =>
    deviceIds.length === 0 ? cy.wrap(null, { log: false }) : db(`DELETE FROM person_device_pdv WHERE pdv_ID IN (${deviceIds.map(() => "?").join(",")})`, deviceIds);

/** Removes the people's sign-in rows, remembered devices and passkeys. */
export const resetSignIn = (personIds) => {
    const marks = personIds.map(() => "?").join(",");
    db(`DELETE FROM person_signin_psi WHERE psi_per_ID IN (${marks})`, personIds);
    db(`DELETE FROM person_device_pdv WHERE pdv_per_ID IN (${marks})`, personIds);
    db(`DELETE FROM person_passkey_ppk WHERE ppk_per_ID IN (${marks})`, personIds);
};

/**
 * Records what the 7.9.0 upgrade did with family emails, one row per move:
 * `{ familyId, email, outcome, reason, personId, needsConfirm }`.
 */
export const recordFamilyEmailMoves = (moves) => {
    moves.forEach(({ familyId, email, outcome, reason = null, personId = null, needsConfirm = false }) => {
        db(
            "INSERT INTO family_email_move_fem (fem_fam_ID, fem_Email, fem_Outcome, fem_Reason, fem_per_ID, fem_NeedsConfirm, fem_DateMoved) VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [familyId, email, outcome, reason, personId, needsConfirm ? 1 : 0],
        );
    });
};

/** Leaves the person awaiting review, as a registration or a member's proposal does. */
export const markAwaitingReview = (personId) => db("UPDATE person_per SET per_NeedsReview = 1 WHERE per_ID = ?", [personId]);

/** Takes the people out of review; the Approve action is only for self-registrations. */
export const clearAwaitingReview = (personIds) =>
    db(`UPDATE person_per SET per_NeedsReview = 0 WHERE per_ID IN (${personIds.map(() => "?").join(",")})`, personIds);

/**
 * Stores a grant the give API refuses, as an older release or a plugin that has
 * since dropped the capability leaves it behind; yields its id.
 */
export const addStoredGrant = (personId, capability, { scopeType = "none", scopeId = 0, reason = "Cypress" } = {}) =>
    db(
        `INSERT INTO portal_grant_pgr (pgr_per_ID, pgr_Capability, pgr_ScopeType, pgr_ScopeId, pgr_Reason, pgr_GrantedBy_usr_ID, pgr_GrantedAt)
         VALUES (?, ?, ?, ?, ?, 1, NOW())`,
        [personId, capability, scopeType, scopeId, reason],
    ).its("insertId");

/** Removes a grant stored with addStoredGrant. */
export const removeStoredGrant = (grantId) => db("DELETE FROM portal_grant_pgr WHERE pgr_ID = ?", [grantId]);

/** Stores the member's portal language as an older release could have, shipped or not. */
export const storeLanguage = (personId, locale) =>
    db("INSERT INTO person_signin_psi (psi_per_ID, psi_Language) VALUES (?, ?) ON DUPLICATE KEY UPDATE psi_Language = VALUES(psi_Language)", [
        personId,
        locale,
    ]);

/** Puts the members back on the church's language. */
export const clearLanguages = (personIds) =>
    db(`UPDATE person_signin_psi SET psi_Language = NULL WHERE psi_per_ID IN (${personIds.map(() => "?").join(",")})`, personIds);

/** Moves a given permission's last day to yesterday. */
export const endPortalGrant = (grantId) =>
    db("UPDATE portal_grant_pgr SET pgr_EndsAt = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE pgr_ID = ?", [grantId]);

/** Lets the person's open email change links expire. */
export const expireEmailChangeLinks = (personId) =>
    db(
        "UPDATE person_email_change_pec SET pec_ExpiresAt = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE pec_per_ID = ? AND pec_UsedAt IS NULL AND pec_CancelledAt IS NULL",
        [personId],
    );

/** Moves the person's sign-in requests `hours` into the past. */
export const ageSignInRequests = (personId, hours) =>
    db(
        "UPDATE person_signin_request_psr SET psr_CreatedAt = DATE_SUB(psr_CreatedAt, INTERVAL ? HOUR), psr_ExpiresAt = DATE_SUB(psr_ExpiresAt, INTERVAL ? HOUR) WHERE psr_per_ID = ?",
        [hours, hours, personId],
    );

/** Records the person's last sign-in `daysAgo` days ago, by `method`. */
export const recordPastSignIn = (personId, daysAgo, method) =>
    db(
        `INSERT INTO person_signin_psi (psi_per_ID, psi_LastSignIn, psi_LastMethod) VALUES (?, NOW() - INTERVAL ? DAY, ?)
         ON DUPLICATE KEY UPDATE psi_LastSignIn = VALUES(psi_LastSignIn), psi_LastMethod = VALUES(psi_LastMethod)`,
        [personId, daysAgo, method],
    );

/**
 * Adds audit rows from long ago: `{ daysAgo, personId, event, method, reason, email, ip, detail }`.
 * Only time makes them; the application writes every row as it happens.
 */
export const addOldAuditRows = (rows) => {
    rows.forEach(({ daysAgo, personId = null, event, method = null, reason = null, email = null, ip = null, detail = null }) => {
        db(
            `INSERT INTO portal_signin_audit_psa (psa_Time, psa_per_ID, psa_Event, psa_Method, psa_Reason, psa_Email, psa_IP, psa_Detail)
             VALUES (NOW() - INTERVAL ? DAY, ?, ?, ?, ?, ?, ?, ?)`,
            [daysAgo, personId, event, method, reason, email, ip, detail],
        );
    });
};

/** Moves the family's emailed confirmations with this text `days` into the past. */
export const ageFamilyConfirmations = (familyId, text, days) =>
    db("UPDATE note_nte SET nte_DateEntered = nte_DateEntered - INTERVAL ? DAY WHERE nte_fam_ID = ? AND nte_Type = 'verify' AND nte_Text = ?", [
        days,
        familyId,
        text,
    ]);
