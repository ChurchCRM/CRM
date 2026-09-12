/// <reference types="cypress" />

/**
 * Volunteer v2 — core domain schema (#9705, epic #9701)
 *
 * #9705 ships tables, not endpoints: the V2 API arrives with #9706/#9707, so
 * there is nothing to exercise over HTTP yet. What the issue actually promises
 * is a set of database guarantees — "duplicate assignments are prevented",
 * "open gaps can be derived without duplicating assignment truth", "the
 * migration does not modify V1 volunteer data" — and every one of those lives
 * in a UNIQUE key, a foreign key, an ON DELETE rule or an enum domain.
 *
 * So this spec asserts them where they live, through the node-side `db:query`
 * task (`cy.dbQuery`, cypress/configs/_shared.ts). #9707 extends this same file
 * with the setup endpoints once they exist.
 *
 * Fixture data is the §2.17 worked example from the design document
 * (.agents/skills/churchcrm/volunteer-v2-design.md): UC1 Coffee Bar and UC2
 * Sunday Worship, including the two cases the schema is deliberately shaped
 * around — one person holding several qualifications in one team, and one
 * person serving two different positions on a single occurrence (I7 / D16).
 *
 * Cleanup runs in `before` as well as `after`: an `after` hook does not run
 * when a test crashes the runner, so the next run must not inherit rows.
 */

// FK-safe delete order: children before the rows they reference.
const V2_TABLES_CHILD_FIRST = [
    "volunteer_notification_vntf",
    "volunteer_swap_vswp",
    "volunteer_response_vrsp",
    "volunteer_assignment_vasg",
    "volunteer_requirement_vreq",
    "volunteer_occurrence_vocc",
    "volunteer_schedule_vsch",
    "volunteer_qualification_vqal",
    "volunteer_position_vpos",
    "volunteer_pool_vpol",
    "volunteer_scope_vscp",
    "volunteer_team_vtem",
    "volunteer_ministry_vmin",
];

// Every V2 table and the exact column set it must expose (design §2.3–§2.15).
const EXPECTED_COLUMNS = {
    volunteer_ministry_vmin: [
        "vmin_ID",
        "vmin_Name",
        "vmin_Description",
        "vmin_Active",
        "vmin_CreatedDate",
        "vmin_CreatedBy_per_ID",
    ],
    volunteer_team_vtem: [
        "vtem_ID",
        "vtem_vmin_ID",
        "vtem_Name",
        "vtem_Description",
        "vtem_Active",
    ],
    volunteer_pool_vpol: [
        "vpol_ID",
        "vpol_OwnerType",
        "vpol_OwnerId",
        "vpol_grp_ID",
        "vpol_Label",
    ],
    volunteer_position_vpos: [
        "vpos_ID",
        "vpos_vmin_ID",
        "vpos_vtem_ID",
        "vpos_Name",
        "vpos_Description",
        "vpos_Active",
        "vpos_Order",
    ],
    volunteer_qualification_vqal: [
        "vqal_ID",
        "vqal_per_ID",
        "vqal_vpos_ID",
        "vqal_Active",
        "vqal_GrantedDate",
        "vqal_GrantedBy_per_ID",
        "vqal_Notes",
    ],
    volunteer_schedule_vsch: [
        "vsch_ID",
        "vsch_vmin_ID",
        "vsch_vtem_ID",
        "vsch_Name",
        "vsch_LinkMode",
        "vsch_event_type_id",
        "vsch_TitleFilter",
        "vsch_RecurType",
        "vsch_RecurDOW",
        "vsch_RecurDOM",
        "vsch_StartTime",
        "vsch_EndTime",
        "vsch_WindowStart",
        "vsch_WindowEnd",
        "vsch_GenerateAheadDays",
        "vsch_Active",
    ],
    volunteer_occurrence_vocc: [
        "vocc_ID",
        "vocc_vsch_ID",
        "vocc_event_id",
        "vocc_OccurrenceDate",
        "vocc_StartDateTime",
        "vocc_EndDateTime",
        "vocc_Status",
        "vocc_Notes",
        "vocc_GeneratedDate",
    ],
    volunteer_requirement_vreq: [
        "vreq_ID",
        "vreq_vsch_ID",
        "vreq_vocc_ID",
        "vreq_vpos_ID",
        "vreq_MinCount",
        "vreq_MaxCount",
        "vreq_Notes",
    ],
    volunteer_assignment_vasg: [
        "vasg_ID",
        "vasg_vocc_ID",
        "vasg_vpos_ID",
        "vasg_per_ID",
        "vasg_vreq_ID",
        "vasg_Status",
        "vasg_Source",
        "vasg_AssignedDate",
        "vasg_AssignedBy_per_ID",
        "vasg_RespondedDate",
        "vasg_Replaces_vasg_ID",
        "vasg_Notes",
    ],
    volunteer_response_vrsp: [
        "vrsp_ID",
        "vrsp_vasg_ID",
        "vrsp_per_ID",
        "vrsp_Response",
        "vrsp_ResponseDate",
        "vrsp_Channel",
        "vrsp_Comment",
    ],
    volunteer_swap_vswp: [
        "vswp_ID",
        "vswp_vasg_ID",
        "vswp_ProposedBy_per_ID",
        "vswp_Proposed_per_ID",
        "vswp_Status",
        "vswp_ProposedDate",
        "vswp_DecidedDate",
        "vswp_DecidedBy_per_ID",
        "vswp_Comment",
    ],
    volunteer_notification_vntf: [
        "vntf_ID",
        "vntf_Type",
        "vntf_Channel",
        "vntf_per_ID",
        "vntf_vasg_ID",
        "vntf_vocc_ID",
        "vntf_DedupeKey",
        "vntf_ScheduledFor",
        "vntf_Status",
        "vntf_Attempts",
        "vntf_LastAttemptDate",
        "vntf_SentDate",
        "vntf_LastError",
    ],
    volunteer_scope_vscp: [
        "vscp_ID",
        "vscp_per_ID",
        "vscp_ScopeType",
        "vscp_ScopeId",
        "vscp_GrantedDate",
        "vscp_GrantedBy_per_ID",
    ],
};

const V2_TABLES = Object.keys(EXPECTED_COLUMNS);

// The ON DELETE rules the design specifies, keyed by "table.column".
const EXPECTED_DELETE_RULES = {
    "volunteer_ministry_vmin.vmin_CreatedBy_per_ID": "SET NULL",
    "volunteer_team_vtem.vtem_vmin_ID": "CASCADE",
    "volunteer_pool_vpol.vpol_grp_ID": "CASCADE",
    "volunteer_position_vpos.vpos_vmin_ID": "CASCADE",
    "volunteer_position_vpos.vpos_vtem_ID": "SET NULL",
    "volunteer_qualification_vqal.vqal_per_ID": "CASCADE",
    "volunteer_qualification_vqal.vqal_vpos_ID": "CASCADE",
    "volunteer_qualification_vqal.vqal_GrantedBy_per_ID": "SET NULL",
    "volunteer_schedule_vsch.vsch_vmin_ID": "CASCADE",
    "volunteer_schedule_vsch.vsch_vtem_ID": "SET NULL",
    "volunteer_schedule_vsch.vsch_event_type_id": "SET NULL",
    "volunteer_occurrence_vocc.vocc_vsch_ID": "CASCADE",
    "volunteer_occurrence_vocc.vocc_event_id": "SET NULL",
    "volunteer_requirement_vreq.vreq_vsch_ID": "CASCADE",
    "volunteer_requirement_vreq.vreq_vocc_ID": "CASCADE",
    "volunteer_requirement_vreq.vreq_vpos_ID": "CASCADE",
    "volunteer_assignment_vasg.vasg_vocc_ID": "CASCADE",
    "volunteer_assignment_vasg.vasg_vpos_ID": "RESTRICT",
    "volunteer_assignment_vasg.vasg_per_ID": "CASCADE",
    "volunteer_assignment_vasg.vasg_vreq_ID": "SET NULL",
    "volunteer_assignment_vasg.vasg_AssignedBy_per_ID": "SET NULL",
    "volunteer_assignment_vasg.vasg_Replaces_vasg_ID": "SET NULL",
    "volunteer_response_vrsp.vrsp_vasg_ID": "CASCADE",
    "volunteer_response_vrsp.vrsp_per_ID": "CASCADE",
    "volunteer_swap_vswp.vswp_vasg_ID": "CASCADE",
    "volunteer_swap_vswp.vswp_ProposedBy_per_ID": "CASCADE",
    "volunteer_swap_vswp.vswp_Proposed_per_ID": "CASCADE",
    "volunteer_swap_vswp.vswp_DecidedBy_per_ID": "SET NULL",
    "volunteer_notification_vntf.vntf_per_ID": "CASCADE",
    "volunteer_notification_vntf.vntf_vasg_ID": "CASCADE",
    "volunteer_notification_vntf.vntf_vocc_ID": "CASCADE",
    "volunteer_scope_vscp.vscp_per_ID": "CASCADE",
    "volunteer_scope_vscp.vscp_GrantedBy_per_ID": "SET NULL",
};

// Seeded fixtures reused rather than recreated (design §6.4).
const PERSON_ADMIN = 1;
const PERSON_TONY = 3;
const PERSON_AMANDA = 99;
const PERSON_LENA = 100;
const GROUP_ANGELS = 1;
const GROUP_WORSHIP = 10;
const EVENT_TYPE_CHURCH_SERVICE = 1;
const EVENT_SUNDAY_SCHOOL = 1;

const DUP_ENTRY = "ER_DUP_ENTRY";
const NO_REFERENCED_ROW = ["ER_NO_REFERENCED_ROW", "ER_NO_REFERENCED_ROW_2"];
const ROW_IS_REFERENCED = ["ER_ROW_IS_REFERENCED", "ER_ROW_IS_REFERENCED_2"];

/** Run SQL and fail the test if the database rejected it. */
function dbOk(sql, params = []) {
    return cy.dbQuery(sql, params).then((result) => {
        if (result.error !== null) {
            throw new Error(
                `Unexpected SQL failure.\n  SQL: ${sql}\n  ${result.error.code}: ${result.error.message}`,
            );
        }
        return result.rows;
    });
}

/** Run SQL that must be rejected, and hand the driver error to the caller. */
function dbRejects(sql, params = []) {
    return cy.dbQuery(sql, params).then((result) => {
        expect(
            result.error,
            `the database was expected to reject: ${sql}`,
        ).to.not.equal(null);
        return result.error;
    });
}

/** INSERT and return the generated id. */
function insertReturningId(sql, params = []) {
    return dbOk(sql, params).then((rows) => rows.insertId);
}

/** Delete every V2 row, children first. Tolerant: used before the schema exists. */
function cleanupV2Rows() {
    V2_TABLES_CHILD_FIRST.forEach((table) => {
        cy.dbQuery(`DELETE FROM \`${table}\``);
    });
}

function countRows(table) {
    return cy
        .dbQuery(`SELECT COUNT(*) AS c FROM \`${table}\``)
        .then((result) => (result.error === null ? result.rows[0].c : -1));
}

describe("API Private Volunteer v2 core schema", () => {
    // D7: the V1 tables must be untouched by anything in this issue.
    const v1CountsBefore = {};
    let sqlMode = "";

    before(() => {
        cy.dbQuery("SELECT @@sql_mode AS mode").then((result) => {
            sqlMode = result.error === null ? result.rows[0].mode : "";
        });
        countRows("volunteeropportunity_vol").then((c) => {
            v1CountsBefore.volunteeropportunity_vol = c;
        });
        countRows("person2volunteeropp_p2vo").then((c) => {
            v1CountsBefore.person2volunteeropp_p2vo = c;
        });
        cleanupV2Rows();
    });

    after(() => {
        cleanupV2Rows();
    });

    describe("Schema shape", () => {
        it("creates all 13 volunteer_* tables as InnoDB / utf8mb4", () => {
            const placeholders = V2_TABLES.map(() => "?").join(", ");
            dbOk(
                `SELECT TABLE_NAME, ENGINE, TABLE_COLLATION
                   FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME IN (${placeholders})`,
                V2_TABLES,
            ).then((rows) => {
                const found = new Map(rows.map((r) => [r.TABLE_NAME, r]));
                V2_TABLES.forEach((table) => {
                    expect(found.has(table), `${table} exists`).to.equal(true);
                    expect(found.get(table).ENGINE, `${table} engine`).to.equal(
                        "InnoDB",
                    );
                    expect(
                        found.get(table).TABLE_COLLATION,
                        `${table} collation`,
                    ).to.match(/^utf8mb4_/);
                });
                expect(rows).to.have.length(13);
            });
        });

        it("declares exactly the documented columns on every table", () => {
            V2_TABLES.forEach((table) => {
                dbOk(
                    `SELECT COLUMN_NAME
                       FROM information_schema.COLUMNS
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?`,
                    [table],
                ).then((rows) => {
                    const actual = rows.map((r) => r.COLUMN_NAME).sort();
                    const expected = [...EXPECTED_COLUMNS[table]].sort();
                    expect(actual, `columns of ${table}`).to.deep.equal(
                        expected,
                    );
                });
            });
        });

        it("names every primary key column <prefix>_ID and adds no redundant UNIQUE on it", () => {
            const placeholders = V2_TABLES.map(() => "?").join(", ");
            dbOk(
                `SELECT TABLE_NAME, COLUMN_NAME
                   FROM information_schema.STATISTICS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND INDEX_NAME = 'PRIMARY'
                    AND TABLE_NAME IN (${placeholders})`,
                V2_TABLES,
            ).then((rows) => {
                expect(rows, "one single-column PK per table").to.have.length(
                    13,
                );
                rows.forEach((row) => {
                    expect(row.COLUMN_NAME, `PK of ${row.TABLE_NAME}`).to.match(
                        /^v[a-z]{3}_ID$/,
                    );
                });
            });

            // §2.0: "Redundant UNIQUE(pk) — never." Any other unique index that
            // covers exactly the primary key column is that anti-pattern.
            dbOk(
                `SELECT TABLE_NAME, INDEX_NAME, COUNT(*) AS cols,
                        MIN(COLUMN_NAME) AS col
                   FROM information_schema.STATISTICS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND NON_UNIQUE = 0
                    AND INDEX_NAME <> 'PRIMARY'
                    AND TABLE_NAME IN (${placeholders})
                  GROUP BY TABLE_NAME, INDEX_NAME
                 HAVING cols = 1 AND col LIKE '%\\_ID'`,
                V2_TABLES,
            ).then((rows) => {
                const offenders = rows
                    .filter((r) => /^v[a-z]{3}_ID$/.test(r.col))
                    .map((r) => `${r.TABLE_NAME}.${r.INDEX_NAME}`);
                expect(offenders, "redundant UNIQUE on a PK").to.deep.equal([]);
            });
        });

        it("names indexes with the _idx / _uidx convention", () => {
            const placeholders = V2_TABLES.map(() => "?").join(", ");
            dbOk(
                `SELECT DISTINCT TABLE_NAME, INDEX_NAME, NON_UNIQUE
                   FROM information_schema.STATISTICS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND INDEX_NAME <> 'PRIMARY'
                    AND TABLE_NAME IN (${placeholders})`,
                V2_TABLES,
            ).then((rows) => {
                rows.forEach((row) => {
                    const suffix =
                        Number(row.NON_UNIQUE) === 0 ? "_uidx" : "_idx";
                    expect(
                        row.INDEX_NAME.endsWith(suffix),
                        `${row.TABLE_NAME}.${row.INDEX_NAME} should end in ${suffix}`,
                    ).to.equal(true);
                });
            });
        });

        it("declares the documented ON DELETE rule on every foreign key", () => {
            const placeholders = V2_TABLES.map(() => "?").join(", ");
            dbOk(
                `SELECT k.TABLE_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME,
                        k.REFERENCED_COLUMN_NAME, r.DELETE_RULE
                   FROM information_schema.KEY_COLUMN_USAGE k
                   JOIN information_schema.REFERENTIAL_CONSTRAINTS r
                     ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
                    AND r.CONSTRAINT_NAME   = k.CONSTRAINT_NAME
                  WHERE k.TABLE_SCHEMA = DATABASE()
                    AND k.REFERENCED_TABLE_NAME IS NOT NULL
                    AND k.TABLE_NAME IN (${placeholders})`,
                V2_TABLES,
            ).then((rows) => {
                const actual = {};
                rows.forEach((row) => {
                    actual[`${row.TABLE_NAME}.${row.COLUMN_NAME}`] =
                        row.DELETE_RULE;
                });
                Object.entries(EXPECTED_DELETE_RULES).forEach(
                    ([key, rule]) => {
                        expect(actual[key], `ON DELETE of ${key}`).to.equal(
                            rule,
                        );
                    },
                );
                expect(
                    Object.keys(actual).sort(),
                    "the full foreign-key set",
                ).to.deep.equal(Object.keys(EXPECTED_DELETE_RULES).sort());
            });
        });

        it("leaves the two polymorphic columns without a foreign key, by design", () => {
            // §2.0: vscp_ScopeId and vpol_OwnerId point at either a ministry or
            // a team, so no single FK target exists — the same limitation
            // record2property_r2p lives with. Integrity is a service concern.
            dbOk(
                `SELECT COUNT(*) AS c
                   FROM information_schema.KEY_COLUMN_USAGE
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                    AND (
                          (TABLE_NAME = 'volunteer_scope_vscp' AND COLUMN_NAME = 'vscp_ScopeId')
                       OR (TABLE_NAME = 'volunteer_pool_vpol'  AND COLUMN_NAME = 'vpol_OwnerId')
                    )`,
            ).then((rows) => {
                expect(Number(rows[0].c)).to.equal(0);
            });
        });

        it("matches foreign key column types to the parent column", () => {
            // F6/§2.0: existing tables disagree with their parents
            // (person2group2role_p2g2r is mediumint(8) against a mediumint(9)
            // parent). V2 matches the parent instead.
            dbOk(
                `SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE
                   FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND (
                        (TABLE_NAME = 'person_per' AND COLUMN_NAME = 'per_ID')
                     OR (TABLE_NAME = 'group_grp'  AND COLUMN_NAME = 'grp_ID')
                     OR (TABLE_NAME = 'volunteer_assignment_vasg' AND COLUMN_NAME = 'vasg_per_ID')
                     OR (TABLE_NAME = 'volunteer_qualification_vqal' AND COLUMN_NAME = 'vqal_per_ID')
                     OR (TABLE_NAME = 'volunteer_scope_vscp' AND COLUMN_NAME = 'vscp_per_ID')
                     OR (TABLE_NAME = 'volunteer_pool_vpol' AND COLUMN_NAME = 'vpol_grp_ID')
                    )`,
            ).then((rows) => {
                const byKey = {};
                rows.forEach((r) => {
                    byKey[`${r.TABLE_NAME}.${r.COLUMN_NAME}`] = r.COLUMN_TYPE;
                });
                const personType = byKey["person_per.per_ID"];
                const groupType = byKey["group_grp.grp_ID"];
                [
                    "volunteer_assignment_vasg.vasg_per_ID",
                    "volunteer_qualification_vqal.vqal_per_ID",
                    "volunteer_scope_vscp.vscp_per_ID",
                ].forEach((key) => {
                    expect(byKey[key], `${key} matches person_per.per_ID`).to.equal(
                        personType,
                    );
                });
                expect(
                    byKey["volunteer_pool_vpol.vpol_grp_ID"],
                    "vpol_grp_ID matches group_grp.grp_ID",
                ).to.equal(groupType);
            });
        });
    });

    describe("Constraints", () => {
        // Ids of the §2.17 fixture rows, filled in by the before hook.
        const f = {};

        before(() => {
            cleanupV2Rows();

            // --- UC1 Coffee Bar -------------------------------------------
            insertReturningId(
                `INSERT INTO volunteer_ministry_vmin
                    (vmin_Name, vmin_Description, vmin_Active, vmin_CreatedDate, vmin_CreatedBy_per_ID)
                 VALUES ('Cypress Coffee Bar', 'UC1', 1, NOW(), ?)`,
                [PERSON_ADMIN],
            ).then((id) => {
                f.ministryCoffee = id;
            });

            cy.then(() =>
                insertReturningId(
                    `INSERT INTO volunteer_team_vtem
                        (vtem_vmin_ID, vtem_Name, vtem_Active)
                     VALUES (?, 'Coffee Bar Team', 1)`,
                    [f.ministryCoffee],
                ).then((id) => {
                    f.teamCoffee = id;
                }),
            );

            cy.then(() =>
                insertReturningId(
                    `INSERT INTO volunteer_pool_vpol
                        (vpol_OwnerType, vpol_OwnerId, vpol_grp_ID, vpol_Label)
                     VALUES ('team', ?, ?, 'Cypress pool')`,
                    [f.teamCoffee, GROUP_ANGELS],
                ).then((id) => {
                    f.poolCoffee = id;
                }),
            );

            cy.then(() =>
                insertReturningId(
                    `INSERT INTO volunteer_position_vpos
                        (vpos_vmin_ID, vpos_vtem_ID, vpos_Name, vpos_Active, vpos_Order)
                     VALUES (?, ?, 'Espresso', 1, 3)`,
                    [f.ministryCoffee, f.teamCoffee],
                ).then((id) => {
                    f.posEspresso = id;
                }),
            );

            cy.then(() =>
                insertReturningId(
                    `INSERT INTO volunteer_position_vpos
                        (vpos_vmin_ID, vpos_vtem_ID, vpos_Name, vpos_Active, vpos_Order)
                     VALUES (?, ?, 'Milk Station', 1, 4)`,
                    [f.ministryCoffee, f.teamCoffee],
                ).then((id) => {
                    f.posMilk = id;
                }),
            );

            // Tony holds two qualifications in the same team (§2.7, D16).
            cy.then(() =>
                insertReturningId(
                    `INSERT INTO volunteer_qualification_vqal
                        (vqal_per_ID, vqal_vpos_ID, vqal_Active, vqal_GrantedDate, vqal_GrantedBy_per_ID)
                     VALUES (?, ?, 1, NOW(), ?)`,
                    [PERSON_TONY, f.posEspresso, PERSON_ADMIN],
                ).then((id) => {
                    f.qualTonyEspresso = id;
                }),
            );
            cy.then(() =>
                dbOk(
                    `INSERT INTO volunteer_qualification_vqal
                        (vqal_per_ID, vqal_vpos_ID, vqal_Active, vqal_GrantedDate)
                     VALUES (?, ?, 1, NOW())`,
                    [PERSON_TONY, f.posMilk],
                ),
            );

            cy.then(() =>
                insertReturningId(
                    `INSERT INTO volunteer_schedule_vsch
                        (vsch_vmin_ID, vsch_vtem_ID, vsch_Name, vsch_LinkMode, vsch_event_type_id,
                         vsch_RecurType, vsch_WindowStart, vsch_GenerateAheadDays, vsch_Active)
                     VALUES (?, ?, 'Coffee Bar — Sunday', 'event_type', ?, 'none', '2026-09-13', 56, 1)`,
                    [f.ministryCoffee, f.teamCoffee, EVENT_TYPE_CHURCH_SERVICE],
                ).then((id) => {
                    f.schedCoffee = id;
                }),
            );

            cy.then(() =>
                insertReturningId(
                    `INSERT INTO volunteer_requirement_vreq
                        (vreq_vsch_ID, vreq_vpos_ID, vreq_MinCount, vreq_MaxCount)
                     VALUES (?, ?, 1, 1)`,
                    [f.schedCoffee, f.posEspresso],
                ).then((id) => {
                    f.reqEspresso = id;
                }),
            );

            cy.then(() =>
                insertReturningId(
                    `INSERT INTO volunteer_occurrence_vocc
                        (vocc_vsch_ID, vocc_event_id, vocc_OccurrenceDate, vocc_Status, vocc_GeneratedDate)
                     VALUES (?, ?, '2026-09-13', 'scheduled', NOW())`,
                    [f.schedCoffee, EVENT_SUNDAY_SCHOOL],
                ).then((id) => {
                    f.occCoffee = id;
                }),
            );

            cy.then(() =>
                insertReturningId(
                    `INSERT INTO volunteer_assignment_vasg
                        (vasg_vocc_ID, vasg_vpos_ID, vasg_per_ID, vasg_vreq_ID,
                         vasg_Status, vasg_Source, vasg_AssignedDate, vasg_AssignedBy_per_ID)
                     VALUES (?, ?, ?, ?, 'pending', 'coordinator', NOW(), ?)`,
                    [
                        f.occCoffee,
                        f.posEspresso,
                        PERSON_TONY,
                        f.reqEspresso,
                        PERSON_ADMIN,
                    ],
                ).then((id) => {
                    f.asgTonyEspresso = id;
                }),
            );

            // --- UC2 Sunday Worship ---------------------------------------
            cy.then(() =>
                insertReturningId(
                    `INSERT INTO volunteer_ministry_vmin
                        (vmin_Name, vmin_Active, vmin_CreatedDate)
                     VALUES ('Cypress Worship', 1, NOW())`,
                ).then((id) => {
                    f.ministryWorship = id;
                }),
            );
            cy.then(() =>
                insertReturningId(
                    `INSERT INTO volunteer_team_vtem (vtem_vmin_ID, vtem_Name, vtem_Active)
                     VALUES (?, 'Worship Team', 1)`,
                    [f.ministryWorship],
                ).then((id) => {
                    f.teamWorship = id;
                }),
            );
            cy.then(() =>
                dbOk(
                    `INSERT INTO volunteer_pool_vpol (vpol_OwnerType, vpol_OwnerId, vpol_grp_ID)
                     VALUES ('team', ?, ?)`,
                    [f.teamWorship, GROUP_WORSHIP],
                ),
            );
            cy.then(() =>
                insertReturningId(
                    `INSERT INTO volunteer_position_vpos
                        (vpos_vmin_ID, vpos_vtem_ID, vpos_Name, vpos_Active, vpos_Order)
                     VALUES (?, ?, 'Communion Leader', 1, 2)`,
                    [f.ministryWorship, f.teamWorship],
                ).then((id) => {
                    f.posCommunion = id;
                }),
            );
            cy.then(() =>
                insertReturningId(
                    `INSERT INTO volunteer_position_vpos
                        (vpos_vmin_ID, vpos_vtem_ID, vpos_Name, vpos_Active, vpos_Order)
                     VALUES (?, ?, 'Closing Prayer', 1, 4)`,
                    [f.ministryWorship, f.teamWorship],
                ).then((id) => {
                    f.posClosing = id;
                }),
            );
            cy.then(() =>
                dbOk(
                    `INSERT INTO volunteer_qualification_vqal
                        (vqal_per_ID, vqal_vpos_ID, vqal_Active, vqal_GrantedDate)
                     VALUES (?, ?, 1, NOW()), (?, ?, 1, NOW())`,
                    [
                        PERSON_AMANDA,
                        f.posCommunion,
                        PERSON_AMANDA,
                        f.posClosing,
                    ],
                ),
            );
            cy.then(() =>
                insertReturningId(
                    `INSERT INTO volunteer_schedule_vsch
                        (vsch_vmin_ID, vsch_vtem_ID, vsch_Name, vsch_LinkMode, vsch_event_type_id,
                         vsch_RecurType, vsch_WindowStart, vsch_Active)
                     VALUES (?, ?, 'Sunday Morning Worship', 'event_type', ?, 'none', '2026-09-13', 1)`,
                    [
                        f.ministryWorship,
                        f.teamWorship,
                        EVENT_TYPE_CHURCH_SERVICE,
                    ],
                ).then((id) => {
                    f.schedWorship = id;
                }),
            );
            // UC3: a second schedule's occurrence points at the SAME event row.
            cy.then(() =>
                insertReturningId(
                    `INSERT INTO volunteer_occurrence_vocc
                        (vocc_vsch_ID, vocc_event_id, vocc_OccurrenceDate, vocc_Status, vocc_GeneratedDate)
                     VALUES (?, ?, '2026-09-13', 'scheduled', NOW())`,
                    [f.schedWorship, EVENT_SUNDAY_SCHOOL],
                ).then((id) => {
                    f.occWorship = id;
                }),
            );
            // I7 / D16: Amanda serves two different positions on one occurrence.
            cy.then(() =>
                insertReturningId(
                    `INSERT INTO volunteer_assignment_vasg
                        (vasg_vocc_ID, vasg_vpos_ID, vasg_per_ID, vasg_Status, vasg_Source, vasg_AssignedDate)
                     VALUES (?, ?, ?, 'accepted', 'coordinator', NOW())`,
                    [f.occWorship, f.posCommunion, PERSON_AMANDA],
                ).then((id) => {
                    f.asgAmandaCommunion = id;
                }),
            );
            cy.then(() =>
                insertReturningId(
                    `INSERT INTO volunteer_assignment_vasg
                        (vasg_vocc_ID, vasg_vpos_ID, vasg_per_ID, vasg_Status, vasg_Source, vasg_AssignedDate)
                     VALUES (?, ?, ?, 'accepted', 'coordinator', NOW())`,
                    [f.occWorship, f.posClosing, PERSON_AMANDA],
                ).then((id) => {
                    f.asgAmandaClosing = id;
                }),
            );
            cy.then(() =>
                dbOk(
                    `INSERT INTO volunteer_response_vrsp
                        (vrsp_vasg_ID, vrsp_per_ID, vrsp_Response, vrsp_ResponseDate, vrsp_Channel)
                     VALUES (?, ?, 'accepted', NOW(), 'web')`,
                    [f.asgAmandaCommunion, PERSON_AMANDA],
                ),
            );
            cy.then(() =>
                dbOk(
                    `INSERT INTO volunteer_swap_vswp
                        (vswp_vasg_ID, vswp_ProposedBy_per_ID, vswp_Proposed_per_ID,
                         vswp_Status, vswp_ProposedDate)
                     VALUES (?, ?, ?, 'proposed', NOW())`,
                    [f.asgAmandaCommunion, PERSON_AMANDA, PERSON_LENA],
                ),
            );
            cy.then(() =>
                dbOk(
                    `INSERT INTO volunteer_notification_vntf
                        (vntf_Type, vntf_Channel, vntf_per_ID, vntf_vasg_ID,
                         vntf_DedupeKey, vntf_ScheduledFor, vntf_Status)
                     VALUES ('assignment', 'email', ?, ?, ?, NOW(), 'pending')`,
                    [
                        PERSON_AMANDA,
                        f.asgAmandaCommunion,
                        `assignment:${f.asgAmandaCommunion}:${PERSON_AMANDA}`,
                    ],
                ),
            );
            cy.then(() =>
                dbOk(
                    `INSERT INTO volunteer_scope_vscp
                        (vscp_per_ID, vscp_ScopeType, vscp_ScopeId, vscp_GrantedDate, vscp_GrantedBy_per_ID)
                     VALUES (?, 'ministry', ?, NOW(), ?)`,
                    [PERSON_TONY, f.ministryCoffee, PERSON_ADMIN],
                ),
            );
        });

        describe("Unique keys", () => {
            it("rejects a duplicate ministry name (vmin_name_uidx)", () => {
                dbRejects(
                    `INSERT INTO volunteer_ministry_vmin (vmin_Name, vmin_Active, vmin_CreatedDate)
                     VALUES ('Cypress Coffee Bar', 1, NOW())`,
                ).then((err) => {
                    expect(err.code).to.equal(DUP_ENTRY);
                });
            });

            it("rejects a duplicate team name inside one ministry (vtem_ministry_name_uidx)", () => {
                dbRejects(
                    `INSERT INTO volunteer_team_vtem (vtem_vmin_ID, vtem_Name, vtem_Active)
                     VALUES (?, 'Coffee Bar Team', 1)`,
                    [f.ministryCoffee],
                ).then((err) => {
                    expect(err.code).to.equal(DUP_ENTRY);
                });

                // The same team name under a different ministry is fine.
                dbOk(
                    `INSERT INTO volunteer_team_vtem (vtem_vmin_ID, vtem_Name, vtem_Active)
                     VALUES (?, 'Coffee Bar Team', 1)`,
                    [f.ministryWorship],
                ).then((rows) => {
                    cy.dbQuery(`DELETE FROM volunteer_team_vtem WHERE vtem_ID = ?`, [
                        rows.insertId,
                    ]);
                });
            });

            it("rejects the same group linked twice to one owner (vpol_owner_group_uidx)", () => {
                dbRejects(
                    `INSERT INTO volunteer_pool_vpol (vpol_OwnerType, vpol_OwnerId, vpol_grp_ID)
                     VALUES ('team', ?, ?)`,
                    [f.teamCoffee, GROUP_ANGELS],
                ).then((err) => {
                    expect(err.code).to.equal(DUP_ENTRY);
                });
            });

            it("rejects a duplicate position name in one ministry+team (vpos_ministry_team_name_uidx)", () => {
                dbRejects(
                    `INSERT INTO volunteer_position_vpos
                        (vpos_vmin_ID, vpos_vtem_ID, vpos_Name, vpos_Active, vpos_Order)
                     VALUES (?, ?, 'Espresso', 1, 9)`,
                    [f.ministryCoffee, f.teamCoffee],
                ).then((err) => {
                    expect(err.code).to.equal(DUP_ENTRY);
                });
            });

            it("does NOT catch two ministry-wide positions of the same name — the documented NULL trap", () => {
                // §2.6: MySQL treats NULLs as distinct inside a UNIQUE index, so
                // two vpos_vtem_ID IS NULL rows with the same name are accepted.
                // VolunteerSetupService::createPosition() is what returns 409.
                // This test pins the behaviour so nobody "fixes" it with a
                // NOT NULL DEFAULT 0 sentinel, which would break the FK.
                const ids = [];
                dbOk(
                    `INSERT INTO volunteer_position_vpos
                        (vpos_vmin_ID, vpos_vtem_ID, vpos_Name, vpos_Active, vpos_Order)
                     VALUES (?, NULL, 'Ministry Wide', 1, 0)`,
                    [f.ministryCoffee],
                ).then((rows) => ids.push(rows.insertId));
                dbOk(
                    `INSERT INTO volunteer_position_vpos
                        (vpos_vmin_ID, vpos_vtem_ID, vpos_Name, vpos_Active, vpos_Order)
                     VALUES (?, NULL, 'Ministry Wide', 1, 0)`,
                    [f.ministryCoffee],
                ).then((rows) => {
                    ids.push(rows.insertId);
                    expect(ids, "both inserts accepted").to.have.length(2);
                    cy.dbQuery(
                        `DELETE FROM volunteer_position_vpos WHERE vpos_ID IN (?, ?)`,
                        ids,
                    );
                });
            });

            it("rejects a duplicate person+position qualification (vqal_person_position_uidx)", () => {
                dbRejects(
                    `INSERT INTO volunteer_qualification_vqal
                        (vqal_per_ID, vqal_vpos_ID, vqal_Active, vqal_GrantedDate)
                     VALUES (?, ?, 1, NOW())`,
                    [PERSON_TONY, f.posEspresso],
                ).then((err) => {
                    expect(err.code).to.equal(DUP_ENTRY);
                });
            });

            it("allows one person to hold several qualifications (§2.7, D16)", () => {
                dbOk(
                    `SELECT COUNT(*) AS c FROM volunteer_qualification_vqal WHERE vqal_per_ID = ?`,
                    [PERSON_TONY],
                ).then((rows) => {
                    expect(Number(rows[0].c)).to.equal(2);
                });
            });

            it("rejects a second occurrence for the same schedule+event (vocc_schedule_event_uidx)", () => {
                dbRejects(
                    `INSERT INTO volunteer_occurrence_vocc
                        (vocc_vsch_ID, vocc_event_id, vocc_OccurrenceDate, vocc_Status, vocc_GeneratedDate)
                     VALUES (?, ?, '2026-09-13', 'scheduled', NOW())`,
                    [f.schedCoffee, EVENT_SUNDAY_SCHOOL],
                ).then((err) => {
                    expect(err.code).to.equal(DUP_ENTRY);
                });
            });

            it("allows two schedules to share one event row (UC3)", () => {
                dbOk(
                    `SELECT COUNT(*) AS c FROM volunteer_occurrence_vocc WHERE vocc_event_id = ?`,
                    [EVENT_SUNDAY_SCHOOL],
                ).then((rows) => {
                    expect(Number(rows[0].c)).to.equal(2);
                });
            });

            // CHECK constraints (MariaDB 10.2.1+ / MySQL 8.0.16+ enforce them; the
            // CI databases are MariaDB 10.11 and mysql:latest). Error codes differ:
            // MariaDB 4025 ER_CONSTRAINT_FAILED, MySQL 3819 ER_CHECK_CONSTRAINT_VIOLATED.
            const CHECK_ERRNOS = [4025, 3819];

            it("accepts a formerly linked occurrence left with no event and no start time (fk_vocc_event SET NULL)", () => {
                // Why there is deliberately no CHECK on vocc_StartDateTime: see the
                // comment in the migration. The standalone dedupe key still applies
                // to rows the generator creates with a start time.
                dbOk(
                    `INSERT INTO volunteer_occurrence_vocc
                        (vocc_vsch_ID, vocc_event_id, vocc_OccurrenceDate, vocc_StartDateTime, vocc_Status, vocc_GeneratedDate)
                     VALUES (?, NULL, '2026-09-20', NULL, 'scheduled', NOW())`,
                    [f.schedCoffee],
                ).then((res) => {
                    expect(res.insertId).to.be.greaterThan(0);
                    return dbOk(`DELETE FROM volunteer_occurrence_vocc WHERE vocc_ID = ?`, [res.insertId]);
                });
            });

            it("rejects a requirement with neither a schedule nor an occurrence (vreq_one_parent_chk)", () => {
                dbRejects(
                    `INSERT INTO volunteer_requirement_vreq
                        (vreq_vsch_ID, vreq_vocc_ID, vreq_vpos_ID, vreq_MinCount)
                     VALUES (NULL, NULL, ?, 1)`,
                    [f.posEspresso],
                ).then((err) => {
                    expect(err.errno).to.be.oneOf(CHECK_ERRNOS);
                });
            });

            it("rejects a requirement that names both a schedule and an occurrence (vreq_one_parent_chk)", () => {
                dbOk(`SELECT vocc_ID FROM volunteer_occurrence_vocc WHERE vocc_vsch_ID = ? LIMIT 1`, [f.schedCoffee]).then((rows) => {
                    expect(rows.length).to.equal(1);
                    dbRejects(
                        `INSERT INTO volunteer_requirement_vreq
                            (vreq_vsch_ID, vreq_vocc_ID, vreq_vpos_ID, vreq_MinCount)
                         VALUES (?, ?, ?, 1)`,
                        [f.schedCoffee, rows[0].vocc_ID, f.posEspresso],
                    ).then((err) => {
                        expect(err.errno).to.be.oneOf(CHECK_ERRNOS);
                    });
                });
            });

            it("rejects a duplicate requirement for one schedule+position (vreq_schedule_position_uidx)", () => {
                dbRejects(
                    `INSERT INTO volunteer_requirement_vreq
                        (vreq_vsch_ID, vreq_vpos_ID, vreq_MinCount)
                     VALUES (?, ?, 2)`,
                    [f.schedCoffee, f.posEspresso],
                ).then((err) => {
                    expect(err.code).to.equal(DUP_ENTRY);
                });
            });

            it("rejects the same person twice on one position of an occurrence (I1, vasg_occ_pos_per_uidx)", () => {
                dbRejects(
                    `INSERT INTO volunteer_assignment_vasg
                        (vasg_vocc_ID, vasg_vpos_ID, vasg_per_ID, vasg_Status, vasg_Source, vasg_AssignedDate)
                     VALUES (?, ?, ?, 'pending', 'coordinator', NOW())`,
                    [f.occWorship, f.posCommunion, PERSON_AMANDA],
                ).then((err) => {
                    expect(err.code).to.equal(DUP_ENTRY);
                });
            });

            it("allows the same person on TWO positions of one occurrence (I7 / D16)", () => {
                // The key is (occurrence, position, person) — deliberately not
                // (occurrence, person). Do not tighten it.
                dbOk(
                    `SELECT COUNT(*) AS c FROM volunteer_assignment_vasg
                      WHERE vasg_vocc_ID = ? AND vasg_per_ID = ?`,
                    [f.occWorship, PERSON_AMANDA],
                ).then((rows) => {
                    expect(Number(rows[0].c)).to.equal(2);
                });
            });

            it("rejects a duplicate notification dedupe key (vntf_dedupe_uidx)", () => {
                dbRejects(
                    `INSERT INTO volunteer_notification_vntf
                        (vntf_Type, vntf_Channel, vntf_per_ID, vntf_vasg_ID,
                         vntf_DedupeKey, vntf_ScheduledFor, vntf_Status)
                     VALUES ('assignment', 'email', ?, ?, ?, NOW(), 'pending')`,
                    [
                        PERSON_AMANDA,
                        f.asgAmandaCommunion,
                        `assignment:${f.asgAmandaCommunion}:${PERSON_AMANDA}`,
                    ],
                ).then((err) => {
                    expect(err.code).to.equal(DUP_ENTRY);
                });
            });

            it("rejects a duplicate scope grant (vscp_person_scope_uidx)", () => {
                dbRejects(
                    `INSERT INTO volunteer_scope_vscp
                        (vscp_per_ID, vscp_ScopeType, vscp_ScopeId, vscp_GrantedDate)
                     VALUES (?, 'ministry', ?, NOW())`,
                    [PERSON_TONY, f.ministryCoffee],
                ).then((err) => {
                    expect(err.code).to.equal(DUP_ENTRY);
                });
            });
        });

        describe("Foreign keys", () => {
            it("rejects a team whose ministry does not exist", () => {
                dbRejects(
                    `INSERT INTO volunteer_team_vtem (vtem_vmin_ID, vtem_Name, vtem_Active)
                     VALUES (999999, 'Orphan Team', 1)`,
                ).then((err) => {
                    expect(NO_REFERENCED_ROW).to.include(err.code);
                });
            });

            it("rejects a qualification for a person who does not exist", () => {
                dbRejects(
                    `INSERT INTO volunteer_qualification_vqal
                        (vqal_per_ID, vqal_vpos_ID, vqal_Active, vqal_GrantedDate)
                     VALUES (999999, ?, 1, NOW())`,
                    [f.posEspresso],
                ).then((err) => {
                    expect(NO_REFERENCED_ROW).to.include(err.code);
                });
            });

            it("rejects an assignment on an occurrence that does not exist", () => {
                dbRejects(
                    `INSERT INTO volunteer_assignment_vasg
                        (vasg_vocc_ID, vasg_vpos_ID, vasg_per_ID, vasg_Status, vasg_Source, vasg_AssignedDate)
                     VALUES (999999, ?, ?, 'pending', 'coordinator', NOW())`,
                    [f.posEspresso, PERSON_TONY],
                ).then((err) => {
                    expect(NO_REFERENCED_ROW).to.include(err.code);
                });
            });

            it("rejects a pool linked to a group that does not exist", () => {
                dbRejects(
                    `INSERT INTO volunteer_pool_vpol (vpol_OwnerType, vpol_OwnerId, vpol_grp_ID)
                     VALUES ('ministry', ?, 9999)`,
                    [f.ministryCoffee],
                ).then((err) => {
                    expect(NO_REFERENCED_ROW).to.include(err.code);
                });
            });

            it("accepts a polymorphic owner id that points at nothing — service-enforced, by design", () => {
                dbOk(
                    `INSERT INTO volunteer_pool_vpol (vpol_OwnerType, vpol_OwnerId, vpol_grp_ID)
                     VALUES ('ministry', 999999, ?)`,
                    [GROUP_ANGELS],
                ).then((rows) => {
                    cy.dbQuery(
                        `DELETE FROM volunteer_pool_vpol WHERE vpol_ID = ?`,
                        [rows.insertId],
                    );
                });
            });
        });

        describe("ON DELETE behaviour", () => {
            it("cascades assignments when an occurrence is deleted", () => {
                let occId;
                let asgId;
                insertReturningId(
                    `INSERT INTO volunteer_occurrence_vocc
                        (vocc_vsch_ID, vocc_OccurrenceDate, vocc_StartDateTime, vocc_Status, vocc_GeneratedDate)
                     VALUES (?, '2026-10-04', '2026-10-04 10:30:00', 'scheduled', NOW())`,
                    [f.schedCoffee],
                )
                    .then((id) => {
                        occId = id;
                        return insertReturningId(
                            `INSERT INTO volunteer_assignment_vasg
                                (vasg_vocc_ID, vasg_vpos_ID, vasg_per_ID, vasg_Status, vasg_Source, vasg_AssignedDate)
                             VALUES (?, ?, ?, 'pending', 'coordinator', NOW())`,
                            [occId, f.posMilk, PERSON_TONY],
                        );
                    })
                    .then((id) => {
                        asgId = id;
                        return dbOk(
                            `DELETE FROM volunteer_occurrence_vocc WHERE vocc_ID = ?`,
                            [occId],
                        );
                    })
                    .then(() =>
                        dbOk(
                            `SELECT COUNT(*) AS c FROM volunteer_assignment_vasg WHERE vasg_ID = ?`,
                            [asgId],
                        ),
                    )
                    .then((rows) => {
                        expect(Number(rows[0].c), "assignment cascaded").to.equal(0);
                    });
            });

            it("refuses to delete a position that still has assignments (RESTRICT, §2.6)", () => {
                dbRejects(
                    `DELETE FROM volunteer_position_vpos WHERE vpos_ID = ?`,
                    [f.posEspresso],
                ).then((err) => {
                    expect(ROW_IS_REFERENCED).to.include(err.code);
                });
            });

            it("nulls vasg_Replaces_vasg_ID when the replaced assignment is deleted", () => {
                let originalId;
                let substituteId;
                insertReturningId(
                    `INSERT INTO volunteer_assignment_vasg
                        (vasg_vocc_ID, vasg_vpos_ID, vasg_per_ID, vasg_Status, vasg_Source, vasg_AssignedDate)
                     VALUES (?, ?, ?, 'substituted', 'coordinator', NOW())`,
                    [f.occCoffee, f.posMilk, PERSON_TONY],
                )
                    .then((id) => {
                        originalId = id;
                        return insertReturningId(
                            `INSERT INTO volunteer_assignment_vasg
                                (vasg_vocc_ID, vasg_vpos_ID, vasg_per_ID, vasg_Status, vasg_Source,
                                 vasg_AssignedDate, vasg_Replaces_vasg_ID)
                             VALUES (?, ?, ?, 'accepted', 'substitute', NOW(), ?)`,
                            [f.occCoffee, f.posMilk, PERSON_LENA, originalId],
                        );
                    })
                    .then((id) => {
                        substituteId = id;
                        return dbOk(
                            `DELETE FROM volunteer_assignment_vasg WHERE vasg_ID = ?`,
                            [originalId],
                        );
                    })
                    .then(() =>
                        dbOk(
                            `SELECT vasg_Replaces_vasg_ID AS replaces
                               FROM volunteer_assignment_vasg WHERE vasg_ID = ?`,
                            [substituteId],
                        ),
                    )
                    .then((rows) => {
                        expect(rows[0].replaces, "self-FK set to NULL").to.equal(
                            null,
                        );
                        cy.dbQuery(
                            `DELETE FROM volunteer_assignment_vasg WHERE vasg_ID = ?`,
                            [substituteId],
                        );
                    });
            });

            it("cascades qualifications, assignments and scopes when a person is deleted", () => {
                let personId;
                insertReturningId(
                    `INSERT INTO person_per (per_FirstName, per_LastName, per_DateEntered)
                     VALUES ('Cypress', 'V2 Cascade', NOW())`,
                )
                    .then((id) => {
                        personId = id;
                        return dbOk(
                            `INSERT INTO volunteer_qualification_vqal
                                (vqal_per_ID, vqal_vpos_ID, vqal_Active, vqal_GrantedDate)
                             VALUES (?, ?, 1, NOW())`,
                            [personId, f.posMilk],
                        );
                    })
                    .then(() =>
                        dbOk(
                            `INSERT INTO volunteer_assignment_vasg
                                (vasg_vocc_ID, vasg_vpos_ID, vasg_per_ID, vasg_Status, vasg_Source, vasg_AssignedDate)
                             VALUES (?, ?, ?, 'pending', 'coordinator', NOW())`,
                            [f.occCoffee, f.posMilk, personId],
                        ),
                    )
                    .then(() =>
                        dbOk(
                            `INSERT INTO volunteer_scope_vscp
                                (vscp_per_ID, vscp_ScopeType, vscp_ScopeId, vscp_GrantedDate)
                             VALUES (?, 'team', ?, NOW())`,
                            [personId, f.teamCoffee],
                        ),
                    )
                    .then(() =>
                        dbOk(`DELETE FROM person_per WHERE per_ID = ?`, [
                            personId,
                        ]),
                    )
                    .then(() =>
                        dbOk(
                            `SELECT
                                (SELECT COUNT(*) FROM volunteer_qualification_vqal WHERE vqal_per_ID = ?) AS quals,
                                (SELECT COUNT(*) FROM volunteer_assignment_vasg  WHERE vasg_per_ID = ?) AS asgs,
                                (SELECT COUNT(*) FROM volunteer_scope_vscp       WHERE vscp_per_ID = ?) AS scopes`,
                            [personId, personId, personId],
                        ),
                    )
                    .then((rows) => {
                        expect(Number(rows[0].quals), "qualifications").to.equal(0);
                        expect(Number(rows[0].asgs), "assignments").to.equal(0);
                        expect(Number(rows[0].scopes), "scopes").to.equal(0);
                    });
            });

            it("nulls the granter instead of deleting the grant (SET NULL)", () => {
                let personId;
                let qualId;
                insertReturningId(
                    `INSERT INTO person_per (per_FirstName, per_LastName, per_DateEntered)
                     VALUES ('Cypress', 'V2 Granter', NOW())`,
                )
                    .then((id) => {
                        personId = id;
                        return insertReturningId(
                            `INSERT INTO volunteer_qualification_vqal
                                (vqal_per_ID, vqal_vpos_ID, vqal_Active, vqal_GrantedDate, vqal_GrantedBy_per_ID)
                             VALUES (?, ?, 1, NOW(), ?)`,
                            [PERSON_LENA, f.posMilk, personId],
                        );
                    })
                    .then((id) => {
                        qualId = id;
                        return dbOk(`DELETE FROM person_per WHERE per_ID = ?`, [
                            personId,
                        ]);
                    })
                    .then(() =>
                        dbOk(
                            `SELECT vqal_GrantedBy_per_ID AS granter
                               FROM volunteer_qualification_vqal WHERE vqal_ID = ?`,
                            [qualId],
                        ),
                    )
                    .then((rows) => {
                        expect(rows, "the grant survives").to.have.length(1);
                        expect(rows[0].granter).to.equal(null);
                        cy.dbQuery(
                            `DELETE FROM volunteer_qualification_vqal WHERE vqal_ID = ?`,
                            [qualId],
                        );
                    });
            });
        });

        describe("Enum domains", () => {
            const enumCases = [
                [
                    "volunteer_assignment_vasg.vasg_Status",
                    `INSERT INTO volunteer_assignment_vasg
                        (vasg_vocc_ID, vasg_vpos_ID, vasg_per_ID, vasg_Status, vasg_Source, vasg_AssignedDate)
                     VALUES (?, ?, ?, 'not_a_status', 'coordinator', NOW())`,
                    () => [f.occCoffee, f.posMilk, PERSON_LENA],
                ],
                [
                    "volunteer_response_vrsp.vrsp_Response",
                    `INSERT INTO volunteer_response_vrsp
                        (vrsp_vasg_ID, vrsp_per_ID, vrsp_Response, vrsp_ResponseDate, vrsp_Channel)
                     VALUES (?, ?, 'not_a_response', NOW(), 'web')`,
                    () => [f.asgAmandaCommunion, PERSON_AMANDA],
                ],
                [
                    "volunteer_scope_vscp.vscp_ScopeType",
                    `INSERT INTO volunteer_scope_vscp
                        (vscp_per_ID, vscp_ScopeType, vscp_ScopeId, vscp_GrantedDate)
                     VALUES (?, 'diocese', ?, NOW())`,
                    () => [PERSON_LENA, f.ministryCoffee],
                ],
                [
                    "volunteer_notification_vntf.vntf_Type",
                    `INSERT INTO volunteer_notification_vntf
                        (vntf_Type, vntf_Channel, vntf_per_ID, vntf_DedupeKey, vntf_ScheduledFor, vntf_Status)
                     VALUES ('carrier_pigeon', 'email', ?, 'cypress:enum:probe', NOW(), 'pending')`,
                    () => [PERSON_LENA],
                ],
            ];

            enumCases.forEach(([label, sql, params]) => {
                it(`rejects an unknown value for ${label}`, () => {
                    // The test database runs MariaDB with STRICT_TRANS_TABLES,
                    // so an out-of-domain enum value is an error (ER_*_DATA_*).
                    // Without strict mode MySQL would silently store '' instead,
                    // so assert whichever this server actually does.
                    const strict = /STRICT_(TRANS|ALL)_TABLES/.test(sqlMode);
                    if (strict) {
                        dbRejects(sql, params()).then((err) => {
                            expect(
                                err.message,
                                `${label} rejected in strict mode`,
                            ).to.match(/truncated|incorrect|invalid/i);
                        });
                    } else {
                        dbOk(sql, params()).then((rows) => {
                            cy.log(
                                `sql_mode is not strict (${sqlMode}) — value was truncated, not rejected`,
                            );
                            expect(rows.affectedRows).to.equal(1);
                        });
                    }
                });
            });

            it("accepts substitute_withdrawn as a response value (§2.12, §2.13)", () => {
                dbOk(
                    `INSERT INTO volunteer_response_vrsp
                        (vrsp_vasg_ID, vrsp_per_ID, vrsp_Response, vrsp_ResponseDate, vrsp_Channel)
                     VALUES (?, ?, 'substitute_withdrawn', NOW(), 'web')`,
                    [f.asgAmandaCommunion, PERSON_AMANDA],
                ).then((rows) => {
                    cy.dbQuery(
                        `DELETE FROM volunteer_response_vrsp WHERE vrsp_ID = ?`,
                        [rows.insertId],
                    );
                });
            });
        });
    });

    describe("V1 volunteer data (D7)", () => {
        it("leaves volunteeropportunity_vol and person2volunteeropp_p2vo untouched", () => {
            countRows("volunteeropportunity_vol").then((c) => {
                expect(c, "volunteeropportunity_vol row count").to.equal(
                    v1CountsBefore.volunteeropportunity_vol,
                );
            });
            countRows("person2volunteeropp_p2vo").then((c) => {
                expect(c, "person2volunteeropp_p2vo row count").to.equal(
                    v1CountsBefore.person2volunteeropp_p2vo,
                );
            });
        });
    });
});
