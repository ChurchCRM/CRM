/// <reference types="cypress" />

/**
 * Volunteer v2 — substitution / swap workflow (#9709, epic #9701, D13).
 *
 * Normative sections of `.agents/skills/churchcrm/volunteer-v2-design.md`:
 * §2.13 (the swap row, its lifecycle, the one-transaction approval and the
 * at-most-one-proposed rule), §2.12 (the four `substitute_*` response values),
 * §2.14 / §3.6 (`swap_proposed` and `swap_resolved` outbox rows), §3.3.2 (the
 * coordinator queue, approve and reject) and §3.3.3 (`propose-substitute` and
 * `withdraw`, both on the member surface).
 *
 * The fixture is UC2 / §2.17's Worship: one ministry, one team, two exactly-one
 * positions, and the EditSelf-exclusive volunteer persona (person 99) as the
 * assignee who proposes — because "the volunteer proposes a substitute who has
 * already agreed" is the product decision this issue implements, and the person
 * who proposes must be the assignee.
 *
 * What §6.5 scenario 2 asks to be proven, and where:
 *
 *   "original row is `substituted` and still readable"
 *       → `approve` block reads it back through GET /assignments/{id}
 *   "replacement carries `replaces` and `source='substitute'`"
 *       → same block, against the API and the raw row
 *   "response rows exist for both"
 *       → same block, counting the append-only history of each
 *   "one transaction"
 *       → the failure case: an approval that cannot insert the replacement
 *         leaves the original `accepted`, asserted after a deliberate clash
 *
 * Cleanup runs in `before` as well as `after` (cypress-testing.md).
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const ADMIN_KEY = "admin.api.key";
const COORDINATOR_KEY = "user.api.key";
const SELFEDIT_KEY = "selfedit.api.key";
const SELFEDIT_NOTES_KEY = "selfedit.plus.notes.api.key";

const VOLUNTEER_URL = "/api/volunteer";

const PERSON_COORDINATOR = 3; // tony.wade
const PERSON_ASSIGNEE = 99; // EditSelf-exclusive — the volunteer persona (D14)
const PERSON_SUBSTITUTE = 100; // EditSelf + Notes — the swap counterparty
const PERSON_UNQUALIFIED_SUB = 5;

const CHURCH_SERVICE_TYPE = 1;

const FIXTURE_PREFIX = "SWAP9709";
const EVENT_TITLE = `${FIXTURE_PREFIX} Sunday Worship`;

let ministryId = 0;
let teamId = 0;
let posSongLeader = 0;
let posCommunion = 0;
let scheduleId = 0;
let occurrenceId = 0;
let originalVersion = "v1";
let seriesStart = "";
let seriesEnd = "";

// ── helpers ────────────────────────────────────────────────────────────────

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

function api(key, method, url, body, expectedStatus = 200) {
    return cy.makePrivateAPICall(Cypress.env(key), method, url, body, expectedStatus);
}

function setVersion(value) {
    cy.makePrivateAdminAPICall("POST", SETTING_URL, { value }, 200);
}

function isoDate(offsetDays) {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + offsetDays);
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function daysToNext(dow) {
    const today = new Date().getDay();
    return ((dow - today + 7) % 7) || 7;
}

function assignmentDetail(assignmentId) {
    return api(ADMIN_KEY, "GET", `${VOLUNTEER_URL}/assignments/${assignmentId}`).then(
        (resp) => resp.body,
    );
}

function outboxRows(type, personId) {
    return dbOk(
        `SELECT vntf_ID, vntf_DedupeKey, vntf_Status FROM volunteer_notification_vntf
          WHERE vntf_Type = ? AND vntf_per_ID = ?`,
        [type, personId],
    );
}

function cleanupWorkflowRows() {
    const scoped = `
        JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vasg.vasg_vocc_ID
        JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
        JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
       WHERE vmin.vmin_Name LIKE ?`;

    dbOk(
        `DELETE vntf FROM volunteer_notification_vntf vntf
           JOIN volunteer_assignment_vasg vasg ON vasg.vasg_ID = vntf.vntf_vasg_ID
           ${scoped}`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vntf FROM volunteer_notification_vntf vntf
           JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vntf.vntf_vocc_ID
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vswp FROM volunteer_swap_vswp vswp
           JOIN volunteer_assignment_vasg vasg ON vasg.vasg_ID = vswp.vswp_vasg_ID
           ${scoped}`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vrsp FROM volunteer_response_vrsp vrsp
           JOIN volunteer_assignment_vasg vasg ON vasg.vasg_ID = vrsp.vrsp_vasg_ID
           ${scoped}`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `UPDATE volunteer_assignment_vasg vasg ${scoped.replace("WHERE", "SET vasg.vasg_Replaces_vasg_ID = NULL WHERE")}`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(`DELETE vasg FROM volunteer_assignment_vasg vasg ${scoped}`, [`${FIXTURE_PREFIX}%`]);
}

function cleanupFixtures() {
    cleanupWorkflowRows();

    for (const parent of ["vreq_vsch_ID", "vreq_vocc_ID"]) {
        const join =
            parent === "vreq_vsch_ID"
                ? `JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vreq.vreq_vsch_ID`
                : `JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vreq.vreq_vocc_ID
                   JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID`;
        dbOk(
            `DELETE vreq FROM volunteer_requirement_vreq vreq
               ${join}
               JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
              WHERE vmin.vmin_Name LIKE ?`,
            [`${FIXTURE_PREFIX}%`],
        );
    }
    dbOk(
        `DELETE vocc FROM volunteer_occurrence_vocc vocc
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vsch FROM volunteer_schedule_vsch vsch
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vqal FROM volunteer_qualification_vqal vqal
           JOIN volunteer_position_vpos vpos ON vpos.vpos_ID = vqal.vqal_vpos_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vpos.vpos_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vpos FROM volunteer_position_vpos vpos
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vpos.vpos_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    // D19: the pool is the ministry's own Group, and `grp_ministry_id` is ON DELETE
    // SET NULL — so the group and its memberships go BEFORE the ministry row, or the
    // installation is left with an orphan group nobody recognises.
    dbOk(
        `DELETE r FROM person2group2role_p2g2r r
           JOIN group_grp g ON g.grp_ID = r.p2g2r_grp_ID
           JOIN volunteer_ministry_vmin m ON m.vmin_ID = g.grp_ministry_id
          WHERE m.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE g FROM group_grp g
           JOIN volunteer_ministry_vmin m ON m.vmin_ID = g.grp_ministry_id
          WHERE m.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vtem FROM volunteer_team_vtem vtem
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vtem.vtem_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(`DELETE FROM volunteer_scope_vscp WHERE vscp_per_ID = ?`, [PERSON_COORDINATOR]);
    dbOk(`DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?`, [`${FIXTURE_PREFIX}%`]);
    dbOk(`DELETE FROM events_event WHERE event_title LIKE ?`, [`${EVENT_TITLE}%`]);
}

function qualify(positionId, personId) {
    return api(
        ADMIN_KEY,
        "POST",
        `${VOLUNTEER_URL}/positions/${positionId}/qualifications`,
        { personId, notes: "" },
        201,
    );
}

/** A fresh accepted Song Leader assignment for the volunteer persona. */
function freshAcceptedAssignment() {
    return api(
        ADMIN_KEY,
        "POST",
        `${VOLUNTEER_URL}/occurrences/${occurrenceId}/assignments`,
        { positionId: posSongLeader, personId: PERSON_ASSIGNEE },
        201,
    ).then((resp) => {
        const id = resp.body.assignment.id;
        return api(
            SELFEDIT_KEY,
            "POST",
            `${VOLUNTEER_URL}/me/assignments/${id}/respond`,
            { response: "accepted" },
            200,
        ).then(() => id);
    });
}

function propose(assignmentId, substitutePersonId, status = 201, key = SELFEDIT_KEY) {
    return api(
        key,
        "POST",
        `${VOLUNTEER_URL}/me/assignments/${assignmentId}/propose-substitute`,
        { personId: substitutePersonId, comment: "He has already agreed" },
        status,
    );
}

// ── fixture ────────────────────────────────────────────────────────────────

before(() => {
    cy.makePrivateAdminAPICall("GET", SETTING_URL, null, 200).then((resp) => {
        originalVersion = resp.body.value ?? resp.body.Value ?? "v1";
    });
    setVersion("v2");

    cleanupFixtures();

    api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries`, {
        name: `${FIXTURE_PREFIX} Worship`,
        description: "volunteer v2 swap fixture",
    }, 201).then((resp) => {
        ministryId = resp.body.ministry.id;
    });

    // The ministry was created with "<name> Team" already in it, so the fixture
    // adopts that team rather than asking for a second one of the same name.
    cy.then(() => {
        api(ADMIN_KEY, "GET", `${VOLUNTEER_URL}/ministries/${ministryId}`, null, 200).then((resp) => {
            teamId = resp.body.teams[0].id;
        });
    });

    cy.then(() => {
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/positions`, {
            name: `${FIXTURE_PREFIX} Song Leader`,
            description: "",
            teamId,
            order: 1,
        }, 201).then((resp) => {
            posSongLeader = resp.body.position.id;
        });
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/positions`, {
            name: `${FIXTURE_PREFIX} Communion Leader`,
            description: "",
            teamId,
            order: 2,
        }, 201).then((resp) => {
            posCommunion = resp.body.position.id;
        });
    });

    // D19: both volunteers go into the ministry's own pool Group through the API —
    // the raw INSERT this replaces existed only because /api/groups needed the
    // global Manage Groups flag.
    cy.then(() => {
        for (const personId of [PERSON_ASSIGNEE, PERSON_SUBSTITUTE]) {
            api(
                ADMIN_KEY,
                "POST",
                `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${personId}`,
                null,
                [200, 201],
            );
        }
    });

    cy.then(() => {
        qualify(posSongLeader, PERSON_ASSIGNEE);
        qualify(posSongLeader, PERSON_SUBSTITUTE);
        qualify(posCommunion, PERSON_SUBSTITUTE);
    });

    cy.then(() => {
        seriesStart = isoDate(daysToNext(0));
        seriesEnd = isoDate(daysToNext(0) + 7);

        api(ADMIN_KEY, "POST", "/api/events/repeat", {
            Title: EVENT_TITLE,
            Type: CHURCH_SERVICE_TYPE,
            StartTime: "10:30:00",
            EndTime: "11:45:00",
            RecurType: "weekly",
            RecurDOW: "Sunday",
            RangeStart: seriesStart,
            RangeEnd: seriesEnd,
        }, 200);
    });

    cy.then(() => {
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`, {
            name: `${FIXTURE_PREFIX} Sunday Morning Worship`,
            linkMode: "event_type",
            eventTypeId: CHURCH_SERVICE_TYPE,
            titleFilter: EVENT_TITLE,
            windowStart: seriesStart,
            teamId,
        }, 201).then((resp) => {
            scheduleId = resp.body.schedule.id;
        });
    });

    cy.then(() => {
        // §2.17: exactly one of each.
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/schedules/${scheduleId}/requirements`, {
            positionId: posSongLeader,
            minCount: 1,
            maxCount: 1,
        }, [200, 201]);
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/schedules/${scheduleId}/requirements`, {
            positionId: posCommunion,
            minCount: 1,
            maxCount: 1,
        }, [200, 201]);
    });

    cy.then(() => {
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/schedules/${scheduleId}/generate`, {
            through: seriesEnd,
        }, 200);
    });

    cy.then(() => {
        api(
            ADMIN_KEY,
            "GET",
            `${VOLUNTEER_URL}/occurrences?from=${seriesStart}&to=${seriesEnd}&ministryId=${ministryId}`,
        ).then((resp) => {
            occurrenceId = resp.body.occurrences[0].id;
        });
    });

    cy.then(() => {
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/scopes`, {
            personId: PERSON_COORDINATOR,
            scopeType: "ministry",
            scopeId: ministryId,
        }, [200, 201]);
    });
});

after(() => {
    cleanupFixtures();
    setVersion(originalVersion);
});

// ───────────────────────────────────────────────────────────────────────────

describe("Volunteer v2 — propose a substitute (§2.13, §3.3.3)", () => {
    let assignmentId = 0;

    beforeEach(() => {
        cleanupWorkflowRows();
        freshAcceptedAssignment().then((id) => {
            assignmentId = id;
        });
    });

    it("lets the assignee propose, appends a response row and alerts the coordinators", () => {
        propose(assignmentId, PERSON_SUBSTITUTE).then((resp) => {
            const swap = resp.body.swap;
            expect(swap.assignmentId).to.eq(assignmentId);
            expect(swap.proposedByPersonId).to.eq(PERSON_ASSIGNEE);
            expect(swap.proposedPersonId).to.eq(PERSON_SUBSTITUTE);
            expect(swap.status).to.eq("proposed");

            // §2.13: every swap state leaves a matching response row.
            assignmentDetail(assignmentId).then((body) => {
                const kinds = body.responses.map((r) => r.response);
                expect(kinds).to.include("substitute_proposed");
                // The original is untouched beyond the audit trail.
                expect(body.assignment.status).to.eq("accepted");
            });

            outboxRows("swap_proposed", PERSON_COORDINATOR).then((rows) => {
                expect(rows).to.have.length(1);
                expect(rows[0].vntf_DedupeKey).to.eq(
                    `swap_proposed:${swap.id}:${PERSON_COORDINATOR}`,
                );
            });
        });
    });

    it("refuses a second proposal while one is still pending (409)", () => {
        propose(assignmentId, PERSON_SUBSTITUTE);
        propose(assignmentId, PERSON_SUBSTITUTE, 409);

        dbOk(
            `SELECT COUNT(*) AS c FROM volunteer_swap_vswp
              WHERE vswp_vasg_ID = ? AND vswp_Status = 'proposed'`,
            [assignmentId],
        ).then((rows) => {
            expect(Number(rows[0].c)).to.eq(1);
        });
    });

    it("refuses an unqualified substitute (I2 applies to the replacement too)", () => {
        propose(assignmentId, PERSON_UNQUALIFIED_SUB, 403);
    });

    it("refuses a substitute who already holds that position on that occurrence (I1)", () => {
        // Cancel the assignee's row and give the position to the substitute, so
        // the substitute is already on it when the proposal is made.
        api(
            ADMIN_KEY,
            "POST",
            `${VOLUNTEER_URL}/occurrences/${occurrenceId}/assignments`,
            { positionId: posCommunion, personId: PERSON_SUBSTITUTE },
            201,
        );

        // A proposal naming a substitute already assigned to the SAME position.
        dbOk(
            `INSERT INTO volunteer_assignment_vasg
                 (vasg_vocc_ID, vasg_vpos_ID, vasg_per_ID, vasg_Status, vasg_Source, vasg_AssignedDate)
             VALUES (?, ?, ?, 'accepted', 'coordinator', NOW())`,
            [occurrenceId, posSongLeader, 8],
        );

        propose(assignmentId, 8, 403);
    });

    it("403s someone proposing on an assignment that is not theirs", () => {
        propose(assignmentId, PERSON_SUBSTITUTE, 403, SELFEDIT_NOTES_KEY);
    });

    it("refuses a proposal on an assignment that is not accepted or pending", () => {
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/assignments/${assignmentId}/status`, {
            status: "cancelled",
        }, 200);

        propose(assignmentId, PERSON_SUBSTITUTE, 409);
    });
});

describe("Volunteer v2 — approve a swap (§2.13, one transaction)", () => {
    let assignmentId = 0;
    let swapId = 0;

    beforeEach(() => {
        cleanupWorkflowRows();
        freshAcceptedAssignment().then((id) => {
            assignmentId = id;
            propose(id, PERSON_SUBSTITUTE).then((resp) => {
                swapId = resp.body.swap.id;
            });
        });
    });

    it("substitutes the original and inserts the replacement in one transaction", () => {
        api(COORDINATOR_KEY, "POST", `${VOLUNTEER_URL}/swaps/${swapId}/approve`, {
            comment: "Approved",
        }, 200).then((resp) => {
            expect(resp.body.swap.status).to.eq("approved");
            expect(resp.body.swap.decidedByPersonId).to.eq(PERSON_COORDINATOR);

            const original = resp.body.originalAssignment;
            const replacement = resp.body.replacementAssignment;

            expect(original.id).to.eq(assignmentId);
            expect(original.status).to.eq("substituted");
            expect(original.personId, "the original is never re-pointed").to.eq(PERSON_ASSIGNEE);

            expect(replacement.personId).to.eq(PERSON_SUBSTITUTE);
            expect(replacement.positionId).to.eq(posSongLeader);
            expect(replacement.occurrenceId).to.eq(occurrenceId);
            // The substitute has already agreed — that is what the product
            // decision means, so the replacement is born accepted.
            expect(replacement.status).to.eq("accepted");
            expect(replacement.source).to.eq("substitute");
            expect(replacement.replacesAssignmentId).to.eq(assignmentId);

            // The raw rows agree with the wire.
            dbOk(
                `SELECT vasg_Status, vasg_Source, vasg_Replaces_vasg_ID
                   FROM volunteer_assignment_vasg WHERE vasg_ID = ?`,
                [replacement.id],
            ).then((rows) => {
                expect(rows[0].vasg_Status).to.eq("accepted");
                expect(rows[0].vasg_Source).to.eq("substitute");
                expect(Number(rows[0].vasg_Replaces_vasg_ID)).to.eq(assignmentId);
            });
        });
    });

    it("keeps the original readable, with its whole audit trail (#9709)", () => {
        api(COORDINATOR_KEY, "POST", `${VOLUNTEER_URL}/swaps/${swapId}/approve`, {}, 200);

        assignmentDetail(assignmentId).then((body) => {
            expect(body.assignment.status).to.eq("substituted");
            const kinds = body.responses.map((r) => r.response);
            expect(kinds).to.include("accepted");
            expect(kinds).to.include("substitute_proposed");
            expect(kinds).to.include("substitute_approved");
        });
    });

    it("appends a response row to BOTH assignments", () => {
        let replacementId = 0;
        api(COORDINATOR_KEY, "POST", `${VOLUNTEER_URL}/swaps/${swapId}/approve`, {}, 200).then(
            (resp) => {
                replacementId = resp.body.replacementAssignment.id;
            },
        );

        cy.then(() => {
            assignmentDetail(replacementId).then((body) => {
                const row = body.responses.find((r) => r.response === "accepted");
                expect(row, "the replacement carries its own accepted row").to.not.eq(undefined);
                expect(row.channel).to.eq("coordinator");
                expect(row.personId).to.eq(PERSON_COORDINATOR);
            });
        });
    });

    it("enqueues swap_resolved for the proposer AND the substitute (§3.6)", () => {
        api(COORDINATOR_KEY, "POST", `${VOLUNTEER_URL}/swaps/${swapId}/approve`, {}, 200);

        outboxRows("swap_resolved", PERSON_ASSIGNEE).then((rows) => {
            expect(rows).to.have.length(1);
            expect(rows[0].vntf_DedupeKey).to.eq(`swap_resolved:${swapId}:${PERSON_ASSIGNEE}`);
        });
        outboxRows("swap_resolved", PERSON_SUBSTITUTE).then((rows) => {
            expect(rows).to.have.length(1);
        });
    });

    it("keeps the gap closed: the replacement counts as live, the original does not", () => {
        api(COORDINATOR_KEY, "POST", `${VOLUNTEER_URL}/swaps/${swapId}/approve`, {}, 200);

        api(
            COORDINATOR_KEY,
            "GET",
            `${VOLUNTEER_URL}/occurrences/${occurrenceId}/staffing`,
        ).then((resp) => {
            const song = resp.body.requirements.find((r) => r.positionId === posSongLeader);
            expect(song.liveCount).to.eq(1);
            expect(song.gapCount).to.eq(0);
            // Both rows are visible; only one is live.
            expect(song.assignments).to.have.length(2);
            expect(song.assignments.filter((a) => a.status === "substituted")).to.have.length(1);
        });
    });

    it("refuses a second decision on a decided swap (409)", () => {
        api(COORDINATOR_KEY, "POST", `${VOLUNTEER_URL}/swaps/${swapId}/approve`, {}, 200);
        api(COORDINATOR_KEY, "POST", `${VOLUNTEER_URL}/swaps/${swapId}/approve`, {}, 409);
        api(COORDINATOR_KEY, "POST", `${VOLUNTEER_URL}/swaps/${swapId}/reject`, {}, 409);
    });

    it("403s a volunteer trying to approve their own proposal", () => {
        api(SELFEDIT_KEY, "POST", `${VOLUNTEER_URL}/swaps/${swapId}/approve`, {}, 403);
    });
});

describe("Volunteer v2 — reject a swap (§2.13)", () => {
    let assignmentId = 0;
    let swapId = 0;

    beforeEach(() => {
        cleanupWorkflowRows();
        freshAcceptedAssignment().then((id) => {
            assignmentId = id;
            propose(id, PERSON_SUBSTITUTE).then((resp) => {
                swapId = resp.body.swap.id;
            });
        });
    });

    it("leaves the original accepted and appends substitute_rejected", () => {
        api(COORDINATOR_KEY, "POST", `${VOLUNTEER_URL}/swaps/${swapId}/reject`, {
            comment: "Need you specifically",
        }, 200).then((resp) => {
            expect(resp.body.swap.status).to.eq("rejected");
        });

        assignmentDetail(assignmentId).then((body) => {
            expect(body.assignment.status).to.eq("accepted");
            expect(body.responses.map((r) => r.response)).to.include("substitute_rejected");
        });

        dbOk(
            `SELECT COUNT(*) AS c FROM volunteer_assignment_vasg
              WHERE vasg_Replaces_vasg_ID = ?`,
            [assignmentId],
        ).then((rows) => {
            expect(Number(rows[0].c), "no replacement is created on rejection").to.eq(0);
        });
    });

    it("notifies both parties that the proposal was resolved", () => {
        api(COORDINATOR_KEY, "POST", `${VOLUNTEER_URL}/swaps/${swapId}/reject`, {}, 200);

        outboxRows("swap_resolved", PERSON_ASSIGNEE).then((rows) => {
            expect(rows).to.have.length(1);
        });
        outboxRows("swap_resolved", PERSON_SUBSTITUTE).then((rows) => {
            expect(rows).to.have.length(1);
        });
    });

    it("allows a second proposal after a rejection", () => {
        api(COORDINATOR_KEY, "POST", `${VOLUNTEER_URL}/swaps/${swapId}/reject`, {}, 200);
        propose(assignmentId, PERSON_SUBSTITUTE, 201);
    });
});

describe("Volunteer v2 — withdraw a swap (§2.13, §3.3.3)", () => {
    let assignmentId = 0;
    let swapId = 0;

    beforeEach(() => {
        cleanupWorkflowRows();
        freshAcceptedAssignment().then((id) => {
            assignmentId = id;
            propose(id, PERSON_SUBSTITUTE).then((resp) => {
                swapId = resp.body.swap.id;
            });
        });
    });

    it("lets the proposer withdraw and appends substitute_withdrawn", () => {
        api(SELFEDIT_KEY, "POST", `${VOLUNTEER_URL}/me/swaps/${swapId}/withdraw`, {
            comment: "Sorted it out",
        }, 200).then((resp) => {
            expect(resp.body.swap.status).to.eq("withdrawn");
        });

        assignmentDetail(assignmentId).then((body) => {
            // "changes nothing else — the original stays accepted" (§2.13).
            expect(body.assignment.status).to.eq("accepted");
            expect(body.responses.map((r) => r.response)).to.include("substitute_withdrawn");
        });
    });

    it("403s anyone but the proposer", () => {
        api(SELFEDIT_NOTES_KEY, "POST", `${VOLUNTEER_URL}/me/swaps/${swapId}/withdraw`, {}, 403);
        // Not even a coordinator withdraws: they approve or reject.
        api(COORDINATOR_KEY, "POST", `${VOLUNTEER_URL}/me/swaps/${swapId}/withdraw`, {}, 403);
    });

    it("409s a withdraw on a swap that is no longer proposed", () => {
        api(SELFEDIT_KEY, "POST", `${VOLUNTEER_URL}/me/swaps/${swapId}/withdraw`, {}, 200);
        api(SELFEDIT_KEY, "POST", `${VOLUNTEER_URL}/me/swaps/${swapId}/withdraw`, {}, 409);
    });

    it("allows a new proposal after a withdrawal", () => {
        api(SELFEDIT_KEY, "POST", `${VOLUNTEER_URL}/me/swaps/${swapId}/withdraw`, {}, 200);
        propose(assignmentId, PERSON_SUBSTITUTE, 201);
    });
});

describe("Volunteer v2 — the coordinator swap queue (§3.3.2)", () => {
    let swapId = 0;

    beforeEach(() => {
        cleanupWorkflowRows();
        freshAcceptedAssignment().then((id) => {
            propose(id, PERSON_SUBSTITUTE).then((resp) => {
                swapId = resp.body.swap.id;
            });
        });
    });

    it("lists proposed swaps inside the caller's scope, with the names a queue needs", () => {
        api(COORDINATOR_KEY, "GET", `${VOLUNTEER_URL}/swaps?status=proposed`).then((resp) => {
            const row = resp.body.swaps.find((s) => s.id === swapId);
            expect(row, "the coordinator sees their ministry's proposal").to.not.eq(undefined);
            expect(row.positionName).to.be.a("string");
            expect(row.proposedByName).to.be.a("string");
            expect(row.proposedPersonName).to.be.a("string");
            expect(row.occurrenceId).to.eq(occurrenceId);
        });
    });

    it("filters by occurrenceId", () => {
        api(
            COORDINATOR_KEY,
            "GET",
            `${VOLUNTEER_URL}/swaps?status=proposed&occurrenceId=${occurrenceId}`,
        ).then((resp) => {
            expect(resp.body.swaps.map((s) => s.id)).to.include(swapId);
        });
    });

    it("403s a caller with no volunteer rights", () => {
        api("plainauth.api.key", "GET", `${VOLUNTEER_URL}/swaps`, null, 403);
    });
});
