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

// Volunteer delivery attempts are transactional outbox records, not service history.
export const recordVolunteerNotification = (personId) =>
    query(
        "INSERT INTO volunteer_notification_vntf (vntf_Type, vntf_per_ID, vntf_DedupeKey, vntf_ScheduledFor, vntf_Status, vntf_Attempts, vntf_LastAttemptDate, vntf_SentDate) VALUES ('assignment', ?, ?, NOW(), 'sent', 1, NOW(), NOW())",
        [personId, `delete-history-${personId}`],
    );

export const countVolunteerNotifications = (personId) =>
    query("SELECT COUNT(*) AS n FROM volunteer_notification_vntf WHERE vntf_per_ID = ?", [personId])
        .then((rows) => Number(rows[0].n));

// The note KioskGuestService writes in the same transaction as a walk-in
// guest's check-in: a system 'event' entry, authored by self-registration
// (Person::SELF_REGISTER = -1). Mirrors the real row so the deletion guard
// is tested against what the kiosk actually leaves behind.
export const recordKioskGuestNote = (personId) =>
    query(
        "INSERT INTO note_nte (nte_per_ID, nte_fam_ID, nte_Private, nte_Text, nte_DateEntered, nte_EnteredBy, nte_Type) VALUES (?, 0, 0, ?, NOW(), -1, 'event')",
        [personId, "Registered as a walk-in guest at the kiosk during event: deletion history test"],
    );

export const recordWhyCame = (personId) =>
    query("INSERT INTO whycame_why (why_per_ID, why_join, why_come, why_suggest, why_hearOfUs) VALUES (?, 'joined', 'visited', 'recommended', 'friend')", [personId]);

export const countWhyCame = (personId) =>
    query("SELECT COUNT(*) AS n FROM whycame_why WHERE why_per_ID = ?", [personId])
        .then((rows) => Number(rows[0].n));

const FIXTURE_MINISTRY = "Deletion history fixture";

// The test stack seeds no volunteer schedule, so the fixture owns one ministry
// with a team, position and occurrence. Removing the ministry cascades to all of it.
const ensureVolunteerOccurrence = () =>
    query("SELECT vmin_ID FROM volunteer_ministry_vmin WHERE vmin_Name = ?", [FIXTURE_MINISTRY]).then((found) => {
        if (found.length === 0) {
            return query("INSERT INTO volunteer_ministry_vmin (vmin_Name, vmin_CreatedDate) VALUES (?, NOW())", [FIXTURE_MINISTRY])
                .then(() => query("SELECT vmin_ID FROM volunteer_ministry_vmin WHERE vmin_Name = ?", [FIXTURE_MINISTRY]))
                .then((rows) => Number(rows[0].vmin_ID))
                .then((ministryId) =>
                    query("INSERT INTO volunteer_team_vtem (vtem_vmin_ID, vtem_Name) VALUES (?, ?)", [ministryId, FIXTURE_MINISTRY])
                        .then(() => query("SELECT vtem_ID FROM volunteer_team_vtem WHERE vtem_vmin_ID = ?", [ministryId]))
                        .then((teams) => Number(teams[0].vtem_ID))
                        .then((teamId) =>
                            query("INSERT INTO volunteer_position_vpos (vpos_vmin_ID, vpos_vtem_ID, vpos_Name) VALUES (?, ?, ?)", [ministryId, teamId, FIXTURE_MINISTRY])
                                .then(() => query("INSERT INTO volunteer_schedule_vsch (vsch_vmin_ID, vsch_vtem_ID, vsch_Name, vsch_LinkMode, vsch_WindowStart) VALUES (?, ?, ?, 'ministry', CURDATE())", [ministryId, teamId, FIXTURE_MINISTRY])),
                        )
                        .then(() => query("SELECT vsch_ID FROM volunteer_schedule_vsch WHERE vsch_vmin_ID = ?", [ministryId]))
                        .then((schedules) => query("INSERT INTO volunteer_occurrence_vocc (vocc_vsch_ID, vocc_OccurrenceDate, vocc_GeneratedDate) VALUES (?, CURDATE(), NOW())", [schedules[0].vsch_ID])),
                );
        }
        return null;
    }).then(() =>
        query("SELECT o.vocc_ID, p.vpos_ID FROM volunteer_ministry_vmin m JOIN volunteer_position_vpos p ON p.vpos_vmin_ID = m.vmin_ID JOIN volunteer_schedule_vsch s ON s.vsch_vmin_ID = m.vmin_ID JOIN volunteer_occurrence_vocc o ON o.vocc_vsch_ID = s.vsch_ID WHERE m.vmin_Name = ? LIMIT 1", [FIXTURE_MINISTRY]),
    ).then((rows) => {
        expect(rows, "volunteer fixture occurrence").to.have.length(1);
        return rows[0];
    });

export const recordVolunteerAssignment = (personId, status) => {
    expect(status).to.be.oneOf(["pending", "accepted", "declined", "cancelled", "substituted", "completed"]);
    return ensureVolunteerOccurrence().then(({ vocc_ID, vpos_ID }) =>
        query(
            "INSERT INTO volunteer_assignment_vasg (vasg_vocc_ID, vasg_vpos_ID, vasg_per_ID, vasg_Status, vasg_AssignedDate) VALUES (?, ?, ?, ?, NOW())",
            [vocc_ID, vpos_ID, personId, status],
        ),
    );
};

export const recordVolunteerResponse = (personId, status = "declined", responderId = personId) =>
    recordVolunteerAssignment(personId, status).then(() =>
        query("SELECT vasg_ID FROM volunteer_assignment_vasg WHERE vasg_per_ID = ? ORDER BY vasg_ID DESC LIMIT 1", [personId]),
    ).then((rows) =>
        query("INSERT INTO volunteer_response_vrsp (vrsp_vasg_ID, vrsp_per_ID, vrsp_Response, vrsp_ResponseDate) VALUES (?, ?, 'declined', NOW())", [rows[0].vasg_ID, responderId]),
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

export const countFamilyMail = (familyId) =>
    query("SELECT COUNT(*) AS n FROM email_log_eml WHERE eml_fam_ID = ?", [familyId]).then((rows) => Number(rows[0].n));

// A delivered email to a family and one of its members: both recipient
// references must survive rejected deletion attempts.
export const recordSentFamilyMail = (familyId, personId) =>
    query(
        "INSERT INTO email_log_eml (eml_per_ID, eml_fam_ID, eml_Address, eml_Kind, eml_Subject, eml_Status, eml_DateSent, eml_Attempts) VALUES (?, ?, ?, 'composer', 'Sent to the family', 'sent', NOW(), 1)",
        [personId, familyId, `family-${familyId}@example.test`],
    );

// Model a legacy family photo without its audit note.
export const resetFamilyDeletionFixtureAudit = (familyId, type) => {
    expect(type).to.be.oneOf(["photo"]);
    return query("DELETE FROM note_nte WHERE nte_fam_ID = ? AND nte_Type = ?", [familyId, type]);
};

export const recordOnboardingParticipation = (personId) =>
    query("INSERT INTO multibuy_mb (mb_per_ID, mb_item_ID, mb_count) VALUES (?, 1, 2)", [personId])
        .then(() => query("INSERT INTO paddlenum_pn (pn_Num, pn_per_ID) VALUES (12345, ?)", [personId]));

// Clear only history owned by this new fixture, then the spec deletes people/families through HTTP.
export const cleanupDeletionFixture = (personId, propertyId) =>
    query("DELETE FROM event_attend WHERE person_id = ?", [personId])
        .then(() => query("DELETE FROM note_nte WHERE nte_per_ID = ?", [personId]))
        .then(() => query("DELETE FROM email_log_eml WHERE eml_per_ID = ?", [personId]))
        .then(() => query("DELETE FROM whycame_why WHERE why_per_ID = ?", [personId]))
        .then(() => query("DELETE FROM multibuy_mb WHERE mb_per_ID = ?", [personId]))
        .then(() => query("DELETE FROM paddlenum_pn WHERE pn_per_ID = ?", [personId]))
         .then(() => query("DELETE FROM volunteer_response_vrsp WHERE vrsp_per_ID = ?", [personId]))
        .then(() => query("DELETE FROM volunteer_assignment_vasg WHERE vasg_per_ID = ?", [personId]))
        .then(() => query("DELETE FROM volunteer_notification_vntf WHERE vntf_per_ID = ?", [personId]))
        .then(() => query("DELETE FROM person2group2role_p2g2r WHERE p2g2r_per_ID = ?", [personId]))
        .then(() => query("DELETE FROM person2volunteeropp_p2vo WHERE p2vo_per_ID = ?", [personId]))
        .then(() => query("DELETE FROM record2property_r2p WHERE r2p_pro_ID = ? AND r2p_record_ID = ?", [propertyId, personId]))
        .then(() => query("DELETE FROM property_pro WHERE pro_ID = ?", [propertyId]))
        .then(() => query("DELETE FROM volunteer_ministry_vmin WHERE vmin_Name = ?", [FIXTURE_MINISTRY]));

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
