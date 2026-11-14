const FIXTURE = "cypress/support/person-deletion-fixtures.js";

const query = (sql, params) => cy.dbQuery(sql, params, { fixture: FIXTURE }).then((result) => {
    expect(result.error, result.error?.message || sql).to.equal(null);
    return result.rows;
});

// Model a legacy profile/login without the audit rows emitted by today's UI.
export const resetDeletionFixtureAudit = (personId, type) => {
    expect(type).to.be.oneOf(["photo", "user"]);
    return query("DELETE FROM note_nte WHERE nte_per_ID = ? AND nte_Type = ?", [personId, type]);
};

export const createDeletionProperty = (personId) =>
    query("INSERT INTO property_pro (pro_Class, pro_prt_ID, pro_Name, pro_Description) VALUES ('p', 1, ?, 'deletion history test')", [`Delete history ${personId}`])
        .then(() => query("SELECT pro_ID FROM property_pro WHERE pro_Name = ?", [`Delete history ${personId}`]))
        .then((rows) => Number(rows[0].pro_ID));

// These stored relationships have no complete HTTP read surface.
export const readDeletionRelationships = (personId) => {
    const snapshot = {};
    return query("SELECT * FROM person_custom WHERE per_ID = ?", [personId])
        .then((rows) => { snapshot.custom = rows; })
        .then(() => query("SELECT * FROM person2group2role_p2g2r WHERE p2g2r_per_ID = ?", [personId]))
        .then((rows) => { snapshot.groups = rows; })
        .then(() => query("SELECT * FROM person2volunteeropp_p2vo WHERE p2vo_per_ID = ?", [personId]))
        .then((rows) => { snapshot.opportunities = rows; })
        .then(() => query("SELECT * FROM record2property_r2p WHERE r2p_record_ID = ?", [personId]))
        .then((rows) => { snapshot.properties = rows; })
        .then(() => query("SELECT * FROM note_nte WHERE nte_per_ID = ?", [personId]))
        .then((rows) => { snapshot.notes = rows; return snapshot; });
};

export const prepareDeletionRelationships = (personId, propertyId) =>
    query("INSERT INTO person2group2role_p2g2r (p2g2r_per_ID, p2g2r_grp_ID, p2g2r_rle_ID) VALUES (?, 9, 1)", [personId])
        .then(() => query("INSERT INTO person2volunteeropp_p2vo (p2vo_per_ID, p2vo_vol_ID) VALUES (?, NULL)", [personId]))
        .then(() => query("INSERT INTO record2property_r2p (r2p_pro_ID, r2p_record_ID, r2p_Value) VALUES (?, ?, 'preserve')", [propertyId, personId]))
        .then(() => readDeletionRelationships(personId));

// A notification that was sent: protected history.
export const recordVolunteerNotification = (personId) =>
    query(
        "INSERT INTO volunteer_notification_vntf (vntf_Type, vntf_per_ID, vntf_DedupeKey, vntf_ScheduledFor, vntf_Status, vntf_Attempts, vntf_LastAttemptDate, vntf_SentDate) VALUES ('assignment', ?, ?, NOW(), 'sent', 1, NOW(), NOW())",
        [personId, `delete-history-${personId}`],
    );

// What deletion may clear because nothing was ever delivered: a gap alert that
// was never attempted, and a queued email no worker has touched. The far-off
// next attempt keeps the queue worker away from the row during the spec.
export const recordUnsentActivity = (personId) =>
    query(
        "INSERT INTO volunteer_notification_vntf (vntf_Type, vntf_per_ID, vntf_DedupeKey, vntf_ScheduledFor, vntf_Status) VALUES ('gap_alert', ?, ?, NOW(), 'skipped')",
        [personId, `delete-history-unsent-${personId}`],
    ).then(() =>
        query(
            "INSERT INTO email_log_eml (eml_per_ID, eml_Address, eml_Kind, eml_Subject, eml_Status, eml_DateSent, eml_Attempts, eml_NextAttempt) VALUES (?, ?, 'composer', 'Unsent', 'queued', NOW(), 0, '2099-01-01 00:00:00')",
            [personId, `unsent-${personId}@example.test`],
        ),
    );

export const countPersonMail = (personId) =>
    query("SELECT COUNT(*) AS n FROM email_log_eml WHERE eml_per_ID = ?", [personId]).then((rows) => Number(rows[0].n));

// Model a legacy family photo without its audit note.
export const resetFamilyDeletionFixtureAudit = (familyId, type) => {
    expect(type).to.be.oneOf(["photo"]);
    return query("DELETE FROM note_nte WHERE nte_fam_ID = ? AND nte_Type = ?", [familyId, type]);
};

export const recordOnboardingParticipation = (personId) =>
    query("INSERT INTO whycame_why (why_per_ID, why_join, why_come, why_suggest, why_hearOfUs) VALUES (?, 'joined', 'visited', 'recommended', 'friend')", [personId])
        .then(() => query("INSERT INTO multibuy_mb (mb_per_ID, mb_item_ID, mb_count) VALUES (?, 1, 2)", [personId]))
        .then(() => query("INSERT INTO paddlenum_pn (pn_Num, pn_per_ID) VALUES (12345, ?)", [personId]));

// Clear only history owned by this new fixture, then the spec deletes people/families through HTTP.
export const cleanupDeletionFixture = (personId, propertyId) =>
    query("DELETE FROM event_attend WHERE person_id = ?", [personId])
        .then(() => query("DELETE FROM note_nte WHERE nte_per_ID = ?", [personId]))
        .then(() => query("DELETE FROM email_log_eml WHERE eml_per_ID = ?", [personId]))
        .then(() => query("DELETE FROM whycame_why WHERE why_per_ID = ?", [personId]))
        .then(() => query("DELETE FROM multibuy_mb WHERE mb_per_ID = ?", [personId]))
        .then(() => query("DELETE FROM paddlenum_pn WHERE pn_per_ID = ?", [personId]))
        .then(() => query("DELETE FROM volunteer_notification_vntf WHERE vntf_per_ID = ?", [personId]))
        .then(() => query("DELETE FROM person2group2role_p2g2r WHERE p2g2r_per_ID = ?", [personId]))
        .then(() => query("DELETE FROM person2volunteeropp_p2vo WHERE p2vo_per_ID = ?", [personId]))
        .then(() => query("DELETE FROM record2property_r2p WHERE r2p_pro_ID = ? AND r2p_record_ID = ?", [propertyId, personId]))
        .then(() => query("DELETE FROM property_pro WHERE pro_ID = ?", [propertyId]));

// Remove the history rows that block deleting test people, so a spec that leaves
// photos, grades, kiosk check-ins or logins behind can still clean up through HTTP.
export const clearPersonHistory = (personIds, familyIds = []) => {
    const people = [...personIds].map(Number).filter(Number.isFinite);
    const families = [...familyIds].map(Number).filter(Number.isFinite);
    const marks = (ids) => ids.map(() => "?").join(",");
    // Build each query inside the callback that runs it: a chain created up
    // front is queued at once, so returning it later yields no rows.
    const familyNotes = families.length === 0
        ? cy.wrap(null, { log: false })
        : query(`DELETE FROM note_nte WHERE nte_fam_ID IN (${marks(families)})`, families);
    return familyNotes
        .then(() =>
            families.length === 0
                ? []
                : query(`SELECT per_ID FROM person_per WHERE per_fam_ID IN (${marks(families)})`, families).then((rows) =>
                      rows.map((row) => Number(row.per_ID)),
                  ),
        )
        .then((members) => {
        const ids = [...new Set([...people, ...members])];
        if (ids.length === 0) {
            return null;
        }
        const m = marks(ids);
        return query(`DELETE FROM event_attend WHERE person_id IN (${m})`, ids)
            .then(() => query(`DELETE FROM note_nte WHERE nte_per_ID IN (${m}) OR nte_EnteredBy IN (${m}) OR nte_EditedBy IN (${m})`, [...ids, ...ids, ...ids]))
            .then(() => query(`DELETE FROM email_log_eml WHERE eml_per_ID IN (${m}) OR eml_usr_ID IN (${m})`, [...ids, ...ids]))
            .then(() => query(`DELETE FROM whycame_why WHERE why_per_ID IN (${m})`, ids))
            .then(() => query(`DELETE FROM multibuy_mb WHERE mb_per_ID IN (${m})`, ids))
            .then(() => query(`DELETE FROM paddlenum_pn WHERE pn_per_ID IN (${m})`, ids))
            .then(() => query(`DELETE FROM volunteer_notification_vntf WHERE vntf_per_ID IN (${m})`, ids));
    });
};
