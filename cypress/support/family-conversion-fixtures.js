const FIXTURE = "cypress/support/family-conversion-fixtures.js";

const query = (sql, params) => cy.dbQuery(sql, params, { fixture: FIXTURE }).then((result) => {
    expect(result.error, result.error?.message || sql).to.equal(null);
    return result.rows;
});

const FIELDS = ["per_Address1", "per_Address2", "per_City", "per_State", "per_Zip", "per_Country", "per_HomePhone", "per_DateLastEdited", "per_EditedBy"];

// Convert All takes every seed person without a family; these put the seed back afterwards,
// including the "Updated" notes saving each person adds.
export const snapshotUnassignedPeople = () =>
    query("SELECT COALESCE(MAX(nte_ID), 0) AS lastNoteId FROM note_nte", []).then(([{ lastNoteId }]) =>
        query(`SELECT per_ID, ${FIELDS.join(", ")} FROM person_per WHERE per_fam_ID = 0`, []).then((people) => ({
            lastNoteId: Number(lastNoteId),
            people,
        })),
    );

export const restoreUnassignedPeople = (snapshot, keepPersonId) => {
    const people = snapshot.people.filter((row) => Number(row.per_ID) !== keepPersonId);
    return cy
        .wrap(people, { log: false })
        .each((row) =>
            query(
                `UPDATE person_per SET per_fam_ID = 0, ${FIELDS.map((field) => `${field} = ?`).join(", ")} WHERE per_ID = ?`,
                [...FIELDS.map((field) => row[field]), row.per_ID],
            ).then(() => query("DELETE FROM note_nte WHERE nte_ID > ? AND nte_per_ID = ?", [snapshot.lastNoteId, row.per_ID])),
        );
};
