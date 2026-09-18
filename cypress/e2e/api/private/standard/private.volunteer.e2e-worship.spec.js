/// <reference types="cypress" />

/**
 * Volunteer v2 — #9714 scenario 2, "Sunday Worship", as ONE end-to-end run.
 *
 * Epic #9701, issue #9714, design `.agents/skills/churchcrm/volunteer-v2-design.md`
 * §0.4 UC2, §2.13 (substitution as a first-class, coordinator-approved
 * workflow), §2.11.1 (the `substituted` terminal state), §2.12 (the append-only
 * response log) and §6.5 row 2.
 *
 * UC2 is the case where every position needs exactly one person and the
 * coordinator is deliberately **not** an administrator:
 *
 *     5 positions on the seeded weekly Church Service type,
 *       each Min 1 / Max 1
 *       → a scoped coordinator (person 3) staffs all five
 *       → the volunteer ACCEPTS
 *       → the volunteer proposes a substitute who has already agreed
 *       → the coordinator APPROVES
 *       → the original row is `substituted` and still readable,
 *         the replacement carries `replacesAssignmentId` and `source=substitute`,
 *         the audit log holds accepted + substitute_proposed + substitute_approved,
 *         and BOTH parties have a `swap_resolved` row in the outbox.
 *
 * "Audit rows on both" is the assertion that matters most here: §2.13's whole
 * point is that a substitution never rewrites history. The original volunteer's
 * acceptance has to survive being replaced, and the replacement has to be
 * traceable back to the row it replaced.
 *
 * Person 99 (`selfedit.api.key`) is the volunteer; person 100
 * (`selfedit.plus.notes.api.key`) is the substitute — both EditSelf-exclusive
 * D14 accounts, reaching the member surface through §4.7's exemption.
 *
 * Cleanup runs in `before` as well as `after` (`cypress-testing.md`).
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const ADMIN_KEY = "admin.api.key";
const COORDINATOR_KEY = "user.api.key";
const SELFEDIT_KEY = "selfedit.api.key"; // person 99 — the assigned volunteer
const SELFEDIT_NOTES_KEY = "selfedit.plus.notes.api.key"; // person 100 — the substitute

const VOLUNTEER_URL = "/api/ministries";

const PERSON_COORDINATOR = 3;
const PERSON_VOLUNTEER = 99;
const PERSON_SUBSTITUTE = 100;

const CHURCH_SERVICE_TYPE = 1; // seed.sql — the weekly Sunday 10:30 service

const PREFIX = "E2E9714WS";
const MINISTRY_NAME = `${PREFIX} Sunday Worship`;
const TEAM_NAME = `${PREFIX} Worship Team`;
const EVENT_TITLE = `${PREFIX} Sunday Morning Worship`;

/** UC2's five roles. Every one of them is Min 1 / Max 1. */
const POSITION_NAMES = [
    "Song Leader",
    "Communion Leader",
    "Opening Prayer",
    "Closing Prayer",
    "Preacher",
];

/** One body per position, plus the two personas. */
const FILLERS = [4, 5, 6, 7];
const POOL_ALL = [...FILLERS, PERSON_VOLUNTEER, PERSON_SUBSTITUTE];

let ministryId = 0;
let teamId = 0;
let groupId = 0;
let scheduleId = 0;
let occurrenceId = 0;
let originalAssignmentId = 0;
let swapId = 0;
const positions = {};
let originalVersion = "v1";
let seriesStart = "";
let seriesEnd = "";
let seriesEventIds = [];

// ── helpers (local functions, NOT cy.* commands — cypress-testing.md) ───────

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
    return cy.makePrivateAPICall(
        Cypress.env(key),
        method,
        url,
        body,
        expectedStatus,
    );
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

/** The coordinator's read of one assignment, with its response history (§6.6). */
function assignment(assignmentId) {
    return api(
        COORDINATOR_KEY,
        "GET",
        `${VOLUNTEER_URL}/assignments/${assignmentId}`,
    ).then((resp) => resp.body);
}

function staffingFor(positionId) {
    return api(
        COORDINATOR_KEY,
        "GET",
        `${VOLUNTEER_URL}/occurrences/${occurrenceId}/staffing`,
    ).then((resp) =>
        resp.body.requirements.find((r) => r.positionId === positionId),
    );
}

function outboxRows(type, personId) {
    return dbOk(
        `SELECT vntf_ID, vntf_Type, vntf_Status, vntf_per_ID, vntf_vasg_ID
           FROM volunteer_notification_vntf
          WHERE vntf_Type = ? AND vntf_per_ID = ?`,
        [type, personId],
    );
}

function cleanupFixtures() {
    const scoped = `
        JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vasg.vasg_vocc_ID
        JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
        JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
       WHERE vmin.vmin_Name LIKE ?`;
    const like = [`${PREFIX}%`];

    dbOk(
        `DELETE vntf FROM volunteer_notification_vntf vntf
           JOIN volunteer_assignment_vasg vasg ON vasg.vasg_ID = vntf.vntf_vasg_ID
           ${scoped}`,
        like,
    );
    dbOk(
        `DELETE vntf FROM volunteer_notification_vntf vntf
           JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vntf.vntf_vocc_ID
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vswp FROM volunteer_swap_vswp vswp
           JOIN volunteer_assignment_vasg vasg ON vasg.vasg_ID = vswp.vswp_vasg_ID
           ${scoped}`,
        like,
    );
    dbOk(
        `DELETE vrsp FROM volunteer_response_vrsp vrsp
           JOIN volunteer_assignment_vasg vasg ON vasg.vasg_ID = vrsp.vrsp_vasg_ID
           ${scoped}`,
        like,
    );
    dbOk(
        `UPDATE volunteer_assignment_vasg vasg ${scoped.replace("WHERE", "SET vasg.vasg_Replaces_vasg_ID = NULL WHERE")}`,
        like,
    );
    dbOk(`DELETE vasg FROM volunteer_assignment_vasg vasg ${scoped}`, like);
    dbOk(
        `DELETE vreq FROM volunteer_requirement_vreq vreq
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vreq.vreq_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vreq FROM volunteer_requirement_vreq vreq
           JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vreq.vreq_vocc_ID
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vocc FROM volunteer_occurrence_vocc vocc
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vsch FROM volunteer_schedule_vsch vsch
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vqal FROM volunteer_qualification_vqal vqal
           JOIN volunteer_position_vpos vpos ON vpos.vpos_ID = vqal.vqal_vpos_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vpos.vpos_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vpos FROM volunteer_position_vpos vpos
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vpos.vpos_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    // D19: the pool is the ministry's own Group and `grp_ministry_id` is ON DELETE
    // SET NULL, so the group goes before the ministry or it is left an orphan.
    dbOk(
        `DELETE r FROM person2group2role_p2g2r r
           JOIN group_grp g ON g.grp_ID = r.p2g2r_grp_ID
           JOIN volunteer_ministry_vmin m ON m.vmin_ID = g.grp_ministry_id
          WHERE m.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE g FROM group_grp g
           JOIN volunteer_ministry_vmin m ON m.vmin_ID = g.grp_ministry_id
          WHERE m.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vtem FROM volunteer_team_vtem vtem
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vtem.vtem_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(`DELETE FROM volunteer_scope_vscp WHERE vscp_per_ID = ?`, [
        PERSON_COORDINATOR,
    ]);
    // #9869: a ministry created through the API now comes with its own calendar,
    // named after the ministry. `calendars.ministry_id` is ON DELETE SET NULL, so
    // deleting the ministry row directly would leave the calendar behind as an
    // unowned church calendar. It goes first, matched on the same prefix.
    dbOk(`DELETE FROM calendars WHERE name LIKE ?`, like);
    dbOk(`DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?`, like);
    dbOk(`DELETE FROM events_event WHERE event_title LIKE ?`, like);
    dbOk(
        `DELETE p2g2r FROM person2group2role_p2g2r p2g2r
           JOIN group_grp grp ON grp.grp_ID = p2g2r.p2g2r_grp_ID
          WHERE grp.grp_Name LIKE ?`,
        like,
    );
    dbOk(`DELETE FROM group_grp WHERE grp_Name LIKE ?`, like);
}

// ── fixture ────────────────────────────────────────────────────────────────

before(() => {
    cy.makePrivateAdminAPICall("GET", SETTING_URL, null, 200).then((resp) => {
        originalVersion = resp.body.value ?? resp.body.Value ?? "v1";
    });
    setVersion("v2");
    cleanupFixtures();

    cy.then(() => {
        api(
            ADMIN_KEY,
            "POST",
            `${VOLUNTEER_URL}/ministries`,
            { name: MINISTRY_NAME, description: "#9714 scenario 2 — UC2" },
            201,
        ).then((resp) => {
            ministryId = resp.body.ministry.id;
        });
    });

    // UC2's coordinator is explicitly not an administrator.
    cy.then(() => {
        api(
            ADMIN_KEY,
            "POST",
            `${VOLUNTEER_URL}/scopes`,
            {
                personId: PERSON_COORDINATOR,
                scopeType: "ministry",
                scopeId: ministryId,
            },
            [200, 201],
        );
    });

    cy.then(() => {
        api(
            COORDINATOR_KEY,
            "POST",
            `${VOLUNTEER_URL}/ministries/${ministryId}/teams`,
            { name: TEAM_NAME, description: "" },
            201,
        ).then((resp) => {
            teamId = resp.body.team.id;
        });
    });

    cy.then(() => {
        POSITION_NAMES.forEach((name, index) => {
            api(
                COORDINATOR_KEY,
                "POST",
                `${VOLUNTEER_URL}/ministries/${ministryId}/positions`,
                { name: `${PREFIX} ${name}`, teamId, order: index + 1 },
                201,
            ).then((resp) => {
                positions[name] = resp.body.position.id;
            });
        });
    });

    // D19: the ministry came with its own pool Group, empty — so the coordinator
    // fills it rather than making a group and linking it.
    cy.then(() => {
        POOL_ALL.forEach((personId) => {
            api(
                COORDINATOR_KEY,
                "POST",
                `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${personId}`,
                null,
                [200, 201],
            );
        });
        api(
            COORDINATOR_KEY,
            "GET",
            `${VOLUNTEER_URL}/ministries/${ministryId}/pool`,
            null,
            200,
        ).then((resp) => {
            groupId = resp.body.groupId;
        });
    });

    // Everybody in the pool is qualified for everything: UC2's constraint is
    // one-per-position, not a narrow skill matrix, and the substitution has to
    // have a qualified counterparty (§2.13 re-checks I2 for the replacement).
    cy.then(() => {
        POSITION_NAMES.forEach((name) => {
            POOL_ALL.forEach((personId) => {
                api(
                    COORDINATOR_KEY,
                    "POST",
                    `${VOLUNTEER_URL}/positions/${positions[name]}/qualifications`,
                    { personId, notes: "" },
                    [200, 201],
                );
            });
        });
    });

    cy.then(() => {
        seriesStart = isoDate(daysToNext(0));
        seriesEnd = isoDate(daysToNext(0) + 7);

        api(
            ADMIN_KEY,
            "POST",
            "/api/events/repeat",
            {
                Title: EVENT_TITLE,
                Type: CHURCH_SERVICE_TYPE,
                StartTime: "10:30:00",
                EndTime: "11:45:00",
                RecurType: "weekly",
                RecurDOW: "Sunday",
                RangeStart: seriesStart,
                RangeEnd: seriesEnd,
            },
            200,
        ).then((resp) => {
            seriesEventIds = resp.body.eventIds;
        });
    });

    cy.then(() => {
        api(
            COORDINATOR_KEY,
            "POST",
            `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`,
            {
                name: `${PREFIX} Sunday Morning Worship`,
                linkMode: "event_type",
                eventTypeId: CHURCH_SERVICE_TYPE,
                titleFilter: EVENT_TITLE,
                windowStart: seriesStart,
                teamId,
            },
            201,
        ).then((resp) => {
            scheduleId = resp.body.schedule.id;
        });
    });

    // Five requirements, every one of them exactly one person (§0.4 UC2).
    cy.then(() => {
        POSITION_NAMES.forEach((name) => {
            api(
                COORDINATOR_KEY,
                "POST",
                `${VOLUNTEER_URL}/schedules/${scheduleId}/requirements`,
                { positionId: positions[name], minCount: 1, maxCount: 1 },
                [200, 201],
            );
        });
    });

    cy.then(() => {
        api(
            COORDINATOR_KEY,
            "POST",
            `${VOLUNTEER_URL}/schedules/${scheduleId}/generate`,
            { through: seriesEnd },
            200,
        );
    });

    cy.then(() => {
        api(
            COORDINATOR_KEY,
            "GET",
            `${VOLUNTEER_URL}/occurrences?from=${seriesStart}&to=${seriesEnd}&ministryId=${ministryId}`,
        ).then((resp) => {
            expect(resp.body.occurrences.length).to.be.greaterThan(0);
            occurrenceId = resp.body.occurrences[0].id;
        });
    });
});

after(() => {
    cleanupFixtures();
    setVersion(originalVersion);
});

// ── the run ────────────────────────────────────────────────────────────────

describe("Volunteer v2 e2e — #9714 scenario 2, Sunday Worship", () => {
    it("has five Min 1 / Max 1 requirements and therefore five gaps to start with", () => {
        api(
            COORDINATOR_KEY,
            "GET",
            `${VOLUNTEER_URL}/schedules/${scheduleId}/requirements`,
        ).then((resp) => {
            expect(resp.body.requirements).to.have.length(5);
            resp.body.requirements.forEach((r) => {
                expect(r.minCount).to.eq(1);
                expect(r.maxCount).to.eq(1);
            });
        });

        api(
            COORDINATOR_KEY,
            "GET",
            `${VOLUNTEER_URL}/occurrences/${occurrenceId}/staffing`,
        ).then((resp) => {
            const total = resp.body.requirements.reduce(
                (sum, r) => sum + r.gapCount,
                0,
            );
            expect(total).to.eq(5);
        });
    });

    it("lets a scoped, non-administrator coordinator fill all five positions", () => {
        // Song Leader goes to the volunteer persona; the rest to the fillers.
        api(
            COORDINATOR_KEY,
            "POST",
            `${VOLUNTEER_URL}/occurrences/${occurrenceId}/assignments`,
            {
                positionId: positions["Song Leader"],
                personId: PERSON_VOLUNTEER,
            },
            201,
        ).then((resp) => {
            originalAssignmentId = resp.body.assignment.id;
            expect(resp.body.assignment.source).to.eq("coordinator");
        });

        POSITION_NAMES.slice(1).forEach((name, index) => {
            api(
                COORDINATOR_KEY,
                "POST",
                `${VOLUNTEER_URL}/occurrences/${occurrenceId}/assignments`,
                { positionId: positions[name], personId: FILLERS[index] },
                201,
            );
        });

        cy.then(() => {
            api(
                COORDINATOR_KEY,
                "GET",
                `${VOLUNTEER_URL}/occurrences/${occurrenceId}/staffing`,
            ).then((resp) => {
                const total = resp.body.requirements.reduce(
                    (sum, r) => sum + r.gapCount,
                    0,
                );
                expect(total).to.eq(0);
            });
        });
    });

    it("records the volunteer's acceptance from the member surface", () => {
        api(
            SELFEDIT_KEY,
            "POST",
            `${VOLUNTEER_URL}/me/assignments/${originalAssignmentId}/respond`,
            { response: "accepted", comment: "Glad to" },
            200,
        ).then((resp) => {
            expect(resp.body.assignment.status).to.eq("accepted");
        });

        assignment(originalAssignmentId).then((body) => {
            expect(body.responses).to.have.length(1);
            expect(body.responses[0].response).to.eq("accepted");
            expect(body.responses[0].channel).to.eq("web");
            expect(body.responses[0].personId).to.eq(PERSON_VOLUNTEER);
        });
    });

    it("proposes a substitute who has already agreed, changing nothing yet", () => {
        api(
            SELFEDIT_KEY,
            "POST",
            `${VOLUNTEER_URL}/me/assignments/${originalAssignmentId}/propose-substitute`,
            { personId: PERSON_SUBSTITUTE, comment: "He has agreed" },
            201,
        ).then((resp) => {
            swapId = resp.body.swap.id;
            expect(resp.body.swap.status).to.eq("proposed");
            expect(resp.body.swap.proposedByPersonId).to.eq(PERSON_VOLUNTEER);
            expect(resp.body.swap.proposedPersonId).to.eq(PERSON_SUBSTITUTE);
        });

        // Proposing decides nothing — the volunteer is still the one serving.
        cy.then(() => {
            assignment(originalAssignmentId).then((body) => {
                expect(body.assignment.status).to.eq("accepted");
                expect(body.assignment.personId).to.eq(PERSON_VOLUNTEER);
            });
        });

        // …and the position is still counted as filled, so no gap opened.
        cy.then(() => {
            staffingFor(positions["Song Leader"]).then((row) => {
                expect(row.liveCount).to.eq(1);
                expect(row.gapCount).to.eq(0);
            });
        });
    });

    it("shows the proposal in the coordinator's queue", () => {
        api(
            COORDINATOR_KEY,
            "GET",
            `${VOLUNTEER_URL}/swaps?status=proposed&ministryId=${ministryId}`,
        ).then((resp) => {
            const mine = resp.body.swaps.filter((s) => s.id === swapId);
            expect(mine).to.have.length(1);
            expect(mine[0].occurrenceId).to.eq(occurrenceId);
        });
    });

    it("approves the substitution in one transaction, and keeps both audit trails", () => {
        api(
            COORDINATOR_KEY,
            "POST",
            `${VOLUNTEER_URL}/swaps/${swapId}/approve`,
            {},
            200,
        ).then((resp) => {
            expect(resp.body.swap.status).to.eq("approved");
        });

        // The original row is terminal, still names the original volunteer, and
        // is still readable. Nothing was deleted or rewritten (§2.13).
        cy.then(() => {
            assignment(originalAssignmentId).then((body) => {
                expect(body.assignment.status).to.eq("substituted");
                expect(body.assignment.personId).to.eq(PERSON_VOLUNTEER);

                const kinds = body.responses.map((r) => r.response);
                expect(kinds).to.include("accepted");
                expect(kinds).to.include("substitute_proposed");
                expect(kinds).to.include("substitute_approved");
            });
        });

        // The replacement is traceable back to the row it replaced.
        cy.then(() => {
            staffingFor(positions["Song Leader"]).then((row) => {
                const replacement = row.assignments.find(
                    (a) => a.personId === PERSON_SUBSTITUTE,
                );
                expect(replacement, "a replacement row exists").to.not.eq(
                    undefined,
                );
                expect(replacement.status).to.eq("accepted");
                expect(replacement.source).to.eq("substitute");
                expect(replacement.replacesAssignmentId).to.eq(
                    originalAssignmentId,
                );

                // Both rows are visible, and only one of them counts as live.
                expect(row.assignments.length).to.eq(2);
                expect(row.liveCount).to.eq(1);
                expect(row.gapCount).to.eq(0);
            });
        });

        // The replacement carries its own response history — "audit rows on
        // both" (§6.5 row 2), not one shared log.
        cy.then(() => {
            staffingFor(positions["Song Leader"]).then((row) => {
                const replacement = row.assignments.find(
                    (a) => a.personId === PERSON_SUBSTITUTE,
                );
                assignment(replacement.id).then((body) => {
                    const kinds = body.responses.map((r) => r.response);
                    expect(kinds).to.include("accepted");
                    body.responses.forEach((r) => {
                        expect(r.assignmentId).to.eq(replacement.id);
                    });
                });
            });
        });
    });

    it("enqueues a swap_resolved outbox row for BOTH parties", () => {
        outboxRows("swap_resolved", PERSON_VOLUNTEER).then((rows) => {
            expect(rows.length, "the original volunteer is told").to.be.at.least(
                1,
            );
        });
        outboxRows("swap_resolved", PERSON_SUBSTITUTE).then((rows) => {
            expect(rows.length, "the substitute is told").to.be.at.least(1);
        });
    });

    it("refuses to approve or reject the same swap twice", () => {
        api(
            COORDINATOR_KEY,
            "POST",
            `${VOLUNTEER_URL}/swaps/${swapId}/approve`,
            {},
            409,
        );
        api(
            COORDINATOR_KEY,
            "POST",
            `${VOLUNTEER_URL}/swaps/${swapId}/reject`,
            {},
            409,
        );
    });

    it("keeps the substituted volunteer's own view honest", () => {
        api(SELFEDIT_KEY, "GET", `${VOLUNTEER_URL}/me/assignments`).then(
            (resp) => {
                const here = resp.body.assignments.find(
                    (a) => a.id === originalAssignmentId,
                );
                // Either the row is gone from "what I am committed to", or it is
                // there and says it is no longer theirs to answer. Both are
                // honest; silently still showing "Going" would not be.
                if (here !== undefined) {
                    expect(here.status).to.eq("substituted");
                    expect(here.canRespond).to.eq(false);
                }
            },
        );

        api(
            SELFEDIT_NOTES_KEY,
            "GET",
            `${VOLUNTEER_URL}/me/assignments`,
        ).then((resp) => {
            const mine = resp.body.assignments.filter(
                (a) => a.occurrenceId === occurrenceId,
            );
            expect(mine.length, "the substitute now has the commitment").to.be.at.least(1);
        });
    });
});
