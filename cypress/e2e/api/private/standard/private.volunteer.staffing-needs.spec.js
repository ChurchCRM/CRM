/// <reference types="cypress" />

/**
 * Volunteer v2 — setting a whole staffing plan at once (§2.10, epic #9701).
 *
 * The defect this covers: a staffing requirement is a separate entity from a position,
 * the API to upsert ONE of them shipped with #9708, and no screen ever created any. A
 * schedule therefore had no requirements, every occurrence it generated needed nobody,
 * had no gaps, and reported itself "Fully staffed" with `0/0` filled. The fix gives the
 * schedule form and the occurrence page a plan editor, which needs an API that can say
 * "and NOT this position" — something a sequence of single-position upserts cannot
 * express.
 *
 * What is proven here:
 *
 *   - `POST /ministries/{id}/schedules` and `POST /schedules/{id}` accept a
 *     `requirements` array that the schedule's plan is made to match exactly:
 *     positions not listed are removed, an empty array clears the plan, an absent
 *     field leaves it alone.
 *   - The row and its plan are ONE transaction: a payload naming an unknown position
 *     creates no schedule at all.
 *   - Occurrences DERIVE their needs. A schedule that gains requirements after its
 *     occurrences were generated fixes them immediately, because nothing is copied at
 *     generation time — `getEffectiveRequirements()` merges on every read.
 *   - `POST /occurrences/{id}/requirements/replace` writes overrides for one week and
 *     `DELETE /occurrences/{id}/requirements` drops them again.
 *   - `requirementCount` on the occurrence wire shape is what tells an EMPTY plan
 *     apart from a satisfied one — `requiredCount` cannot, because a Min 0 / Max 1
 *     requirement is a plan with no required body.
 *   - Max ≥ Min and "no duplicate position" are refused with 400, and every route is
 *     scoped: a coordinator of another ministry gets 403.
 *
 * Fixtures follow `private.volunteer.schedule.spec.js`: rows in through `cy.dbQuery()`,
 * scopes through the #9706 API, cleanup in `before` as well as `after` because an
 * `after` hook does not run when the runner crashes mid-spec.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const ADMIN_KEY = "admin.api.key";
const COORDINATOR_KEY = "user.api.key";

const PERSON_COORDINATOR = 3; // tony.wade — every flag but Admin

const FIXTURE_PREFIX = "NEEDS9701";

let ministryA = 0;
let ministryB = 0;
let teamA1 = 0;
let posLead = 0;
let posHelper = 0;
let posSpare = 0;
/** A position of the OTHER ministry — the §2.10 "different ministry" rejection. */
let posForeign = 0;
let originalVersion = "v1";

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

function cleanupFixtures() {
    dbOk(
        `DELETE vreq FROM volunteer_requirement_vreq vreq
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vreq.vreq_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vreq FROM volunteer_requirement_vreq vreq
           JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vreq.vreq_vocc_ID
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
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
        `DELETE vpos FROM volunteer_position_vpos vpos
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vpos.vpos_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vtem FROM volunteer_team_vtem vtem
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vtem.vtem_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(`DELETE FROM volunteer_scope_vscp WHERE vscp_per_ID = ?`, [PERSON_COORDINATOR]);
    // #9869: a ministry created through the API now comes with its own calendar,
    // named after the ministry. `calendars.ministry_id` is ON DELETE SET NULL, so
    // deleting the ministry row directly would leave the calendar behind as an
    // unowned church calendar. It goes first, matched on the same prefix.
    dbOk(`DELETE FROM calendars WHERE name LIKE ?`, [`${FIXTURE_PREFIX}%`]);
    dbOk(`DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?`, [`${FIXTURE_PREFIX}%`]);
}

function createMinistry(suffix) {
    return dbOk(
        `INSERT INTO volunteer_ministry_vmin (vmin_Name, vmin_Description, vmin_Active, vmin_CreatedDate)
         VALUES (?, 'volunteer v2 staffing-needs fixture', 1, NOW())`,
        [`${FIXTURE_PREFIX} ${suffix}`],
    ).then((rows) => rows.insertId);
}

function createTeam(ministryId, suffix) {
    return dbOk(
        `INSERT INTO volunteer_team_vtem (vtem_vmin_ID, vtem_Name, vtem_Description, vtem_Active)
         VALUES (?, ?, 'volunteer v2 staffing-needs fixture', 1)`,
        [ministryId, `${FIXTURE_PREFIX} ${suffix}`],
    ).then((rows) => rows.insertId);
}

function createPosition(ministryId, teamId, name, order) {
    return dbOk(
        `INSERT INTO volunteer_position_vpos (vpos_vmin_ID, vpos_vtem_ID, vpos_Name, vpos_Description, vpos_Active, vpos_Order)
         VALUES (?, ?, ?, 'volunteer v2 staffing-needs fixture', 1, ?)`,
        [ministryId, teamId, `${FIXTURE_PREFIX} ${name}`, order],
    ).then((rows) => rows.insertId);
}

/**
 * A standalone weekly schedule. The seeded calendar holds only 2016/2017 events, and
 * this spec is about the plan rather than about where the dates come from, so V2 owning
 * them keeps the fixture to one request.
 */
function standaloneBody(overrides = {}) {
    return {
        name: `${FIXTURE_PREFIX} Wednesday Night`,
        linkMode: "standalone",
        recurType: "weekly",
        recurDow: "Wednesday",
        startTime: "19:00:00",
        endTime: "20:30:00",
        windowStart: isoDate(0),
        ...overrides,
    };
}

function createSchedule(body, key = ADMIN_KEY, expectedStatus = 201) {
    return api(key, "POST", `/api/ministries/ministries/${ministryA}/schedules`, body, expectedStatus);
}

function requirementsOf(scheduleId) {
    return api(ADMIN_KEY, "GET", `/api/ministries/schedules/${scheduleId}/requirements`).then(
        (resp) => resp.body.requirements,
    );
}

/** Generate a fortnight of occurrences and hand back the first one's id. */
function generateAndFirstOccurrence(scheduleId) {
    return api(ADMIN_KEY, "POST", `/api/ministries/schedules/${scheduleId}/generate`, { through: isoDate(14) }, 200)
        .then(() =>
            api(
                ADMIN_KEY,
                "GET",
                `/api/ministries/occurrences?from=${isoDate(-1)}&to=${isoDate(14)}&scheduleId=${scheduleId}`,
            ),
        )
        .then((resp) => {
            expect(resp.body.occurrences.length, "the fixture generated occurrences").to.be.greaterThan(0);
            return resp.body.occurrences[0].id;
        });
}

function occurrence(occurrenceId) {
    return api(ADMIN_KEY, "GET", `/api/ministries/occurrences/${occurrenceId}`).then((resp) => resp.body.occurrence);
}

// ── suite ──────────────────────────────────────────────────────────────────

describe("Volunteer v2 — staffing needs as a whole plan (§2.10)", () => {
    before(() => {
        cy.makePrivateAdminAPICall("GET", SETTING_URL, null, 200).then((resp) => {
            originalVersion = resp.body.value ?? resp.body.data ?? "v1";
        });
        setVersion("v2");

        cleanupFixtures();

        createMinistry("Children's Ministry").then((id) => {
            ministryA = id;
            createTeam(ministryA, "Wednesday Night").then((teamId) => {
                teamA1 = teamId;
                createPosition(ministryA, teamId, "Lead Teacher", 1).then((p) => {
                    posLead = p;
                });
                createPosition(ministryA, teamId, "Helper", 2).then((p) => {
                    posHelper = p;
                });
                createPosition(ministryA, teamId, "Spare", 3).then((p) => {
                    posSpare = p;
                });
            });
        });
        createMinistry("Other Ministry").then((id) => {
            ministryB = id;
            // "Foreign" means "another ministry's", which is still exactly what this
            // position is — it just lives in a team of that ministry now, because
            // `vpos_vtem_ID` is NOT NULL. The ministry row is inserted with raw SQL,
            // which bypasses the service and so the team it would have created.
            createTeam(ministryB, "Other Team").then((teamId) => {
                createPosition(ministryB, teamId, "Foreign Position", 1).then((p) => {
                    posForeign = p;
                });
            });
        });

        cy.then(() => {
            api(
                ADMIN_KEY,
                "POST",
                "/api/ministries/scopes",
                { personId: PERSON_COORDINATOR, scopeType: "ministry", scopeId: ministryB },
                [200, 201],
            );
        });
    });

    after(() => {
        cleanupFixtures();
        setVersion(originalVersion);
    });

    // ── the schedule's plan ────────────────────────────────────────────────

    describe("a schedule is created with its whole plan", () => {
        it("writes one requirement per listed position", () => {
            createSchedule(
                standaloneBody({
                    teamId: teamA1,
                    requirements: [
                        { positionId: posLead, minCount: 1, maxCount: 1 },
                        { positionId: posHelper, minCount: 2, maxCount: 3 },
                    ],
                }),
            ).then((resp) => {
                const scheduleId = resp.body.schedule.id;

                requirementsOf(scheduleId).then((rows) => {
                    expect(rows).to.have.length(2);

                    const lead = rows.find((r) => r.positionId === posLead);
                    expect(lead.minCount).to.eq(1);
                    expect(lead.maxCount).to.eq(1);
                    expect(lead.scheduleId).to.eq(scheduleId);
                    expect(lead.occurrenceId).to.eq(null);
                    expect(lead.source).to.eq("schedule");

                    const helper = rows.find((r) => r.positionId === posHelper);
                    expect(helper.minCount).to.eq(2);
                    expect(helper.maxCount).to.eq(3);
                });
            });
        });

        it("accepts an empty array as a real answer — the plan is simply empty", () => {
            createSchedule(standaloneBody({ teamId: teamA1, requirements: [] })).then((resp) => {
                requirementsOf(resp.body.schedule.id).then((rows) => {
                    expect(rows).to.have.length(0);
                });
            });
        });

        it("creates no schedule at all when the plan names an unknown position", () => {
            api(ADMIN_KEY, "GET", `/api/ministries/ministries/${ministryA}/schedules`).then((before) => {
                const countBefore = before.body.schedules.length;

                createSchedule(
                    standaloneBody({
                        name: `${FIXTURE_PREFIX} Doomed`,
                        teamId: teamA1,
                        requirements: [{ positionId: 999999, minCount: 1, maxCount: 1 }],
                    }),
                    ADMIN_KEY,
                    400,
                ).then(() => {
                    // The row and its plan are one transaction: a rejected plan must not
                    // leave a half-made schedule behind for a coordinator to find later.
                    api(ADMIN_KEY, "GET", `/api/ministries/ministries/${ministryA}/schedules`).then((after) => {
                        expect(after.body.schedules.length).to.eq(countBefore);
                        expect(after.body.schedules.find((s) => s.name === `${FIXTURE_PREFIX} Doomed`)).to.eq(
                            undefined,
                        );
                    });
                });
            });
        });
    });

    describe("updating a schedule replaces its plan exactly", () => {
        let scheduleId = 0;

        beforeEach(() => {
            createSchedule(
                standaloneBody({
                    teamId: teamA1,
                    requirements: [
                        { positionId: posLead, minCount: 1, maxCount: 1 },
                        { positionId: posHelper, minCount: 2, maxCount: 2 },
                    ],
                }),
            ).then((resp) => {
                scheduleId = resp.body.schedule.id;
            });
        });

        it("creates, updates and DELETES to match the payload", () => {
            api(ADMIN_KEY, "POST", `/api/ministries/schedules/${scheduleId}`, {
                requirements: [
                    // Helper stays, with new counts; Spare is new; Lead is gone — and
                    // "gone" is the half a sequence of single upserts cannot express.
                    { positionId: posHelper, minCount: 1, maxCount: 4 },
                    { positionId: posSpare, minCount: 0, maxCount: 1 },
                ],
            }).then(() => {
                requirementsOf(scheduleId).then((rows) => {
                    expect(rows.map((r) => r.positionId).sort()).to.deep.eq([posHelper, posSpare].sort());

                    const helper = rows.find((r) => r.positionId === posHelper);
                    expect(helper.minCount).to.eq(1);
                    expect(helper.maxCount).to.eq(4);
                });
            });
        });

        it("leaves the plan alone when the field is absent", () => {
            api(ADMIN_KEY, "POST", `/api/ministries/schedules/${scheduleId}`, {
                name: `${FIXTURE_PREFIX} Renamed`,
            }).then(() => {
                requirementsOf(scheduleId).then((rows) => {
                    expect(rows).to.have.length(2);
                });
            });
        });

        it("clears the plan when the field is an empty array", () => {
            api(ADMIN_KEY, "POST", `/api/ministries/schedules/${scheduleId}`, { requirements: [] }).then(() => {
                requirementsOf(scheduleId).then((rows) => {
                    expect(rows).to.have.length(0);
                });
            });
        });

        describe("rejects a plan that breaks §2.10's count rules", () => {
            it("rejects a maxCount below minCount", () => {
                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/ministries/schedules/${scheduleId}`,
                    { requirements: [{ positionId: posLead, minCount: 3, maxCount: 2 }] },
                    400,
                );
            });

            it("rejects a negative minCount", () => {
                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/ministries/schedules/${scheduleId}`,
                    { requirements: [{ positionId: posLead, minCount: -1, maxCount: 1 }] },
                    400,
                );
            });

            it("rejects the same position twice", () => {
                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/ministries/schedules/${scheduleId}`,
                    {
                        requirements: [
                            { positionId: posLead, minCount: 1, maxCount: 1 },
                            { positionId: posLead, minCount: 2, maxCount: 2 },
                        ],
                    },
                    400,
                );
            });

            it("rejects a position from another ministry", () => {
                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/ministries/schedules/${scheduleId}`,
                    { requirements: [{ positionId: posForeign, minCount: 1, maxCount: 1 }] },
                    400,
                );
            });

            it("leaves the stored plan untouched after a rejection", () => {
                api(
                    ADMIN_KEY,
                    "POST",
                    `/api/ministries/schedules/${scheduleId}`,
                    { requirements: [{ positionId: posLead, minCount: 3, maxCount: 2 }] },
                    400,
                ).then(() => {
                    requirementsOf(scheduleId).then((rows) => {
                        expect(rows).to.have.length(2);
                    });
                });
            });
        });
    });

    // ── the derivation ─────────────────────────────────────────────────────

    describe("occurrences derive their needs from the schedule", () => {
        it("an occurrence generated BEFORE the plan existed picks it up immediately", () => {
            // This is the reported defect in miniature: schedule first, occurrences
            // second, plan third — and the occurrences must not be stuck at 0/0.
            createSchedule(standaloneBody({ teamId: teamA1 })).then((resp) => {
                const scheduleId = resp.body.schedule.id;

                generateAndFirstOccurrence(scheduleId).then((occurrenceId) => {
                    occurrence(occurrenceId).then((before) => {
                        expect(before.requirementCount, "no plan yet").to.eq(0);
                        expect(before.requiredCount).to.eq(0);
                    });

                    api(ADMIN_KEY, "POST", `/api/ministries/schedules/${scheduleId}`, {
                        requirements: [
                            { positionId: posLead, minCount: 1, maxCount: 1 },
                            { positionId: posHelper, minCount: 2, maxCount: 2 },
                        ],
                    }).then(() => {
                        occurrence(occurrenceId).then((after) => {
                            // Nothing was copied at generation time; the merge is
                            // derived on every read, so the already-generated row is
                            // fixed by the schedule edit alone.
                            expect(after.requirementCount).to.eq(2);
                            expect(after.requiredCount).to.eq(3);
                            expect(after.gapCount).to.eq(3);
                            expect(after.requirementsOverridden).to.eq(false);
                        });

                        api(ADMIN_KEY, "GET", `/api/ministries/occurrences/${occurrenceId}/staffing`).then((st) => {
                            expect(st.body.requirements.map((r) => r.positionId).sort()).to.deep.eq(
                                [posLead, posHelper].sort(),
                            );
                            for (const requirement of st.body.requirements) {
                                expect(requirement.source).to.eq("schedule");
                            }
                        });
                    });
                });
            });
        });

        it("reports requirementCount separately from requiredCount", () => {
            // A Min 0 / Max 1 requirement is a PLAN with no required body. Only
            // requirementCount can tell it apart from "nobody set any needs", which is
            // why a screen must not decide "fully staffed" from the counts alone.
            createSchedule(
                standaloneBody({
                    teamId: teamA1,
                    requirements: [{ positionId: posSpare, minCount: 0, maxCount: 1 }],
                }),
            ).then((resp) => {
                generateAndFirstOccurrence(resp.body.schedule.id).then((occurrenceId) => {
                    occurrence(occurrenceId).then((row) => {
                        expect(row.requiredCount, "nobody is REQUIRED").to.eq(0);
                        expect(row.requirementCount, "but a plan exists").to.eq(1);
                        expect(row.gapCount).to.eq(0);
                    });
                });
            });
        });

        it("names the short positions in the occurrence list", () => {
            createSchedule(
                standaloneBody({
                    teamId: teamA1,
                    requirements: [
                        { positionId: posLead, minCount: 1, maxCount: 1 },
                        { positionId: posHelper, minCount: 2, maxCount: 2 },
                    ],
                }),
            ).then((resp) => {
                const scheduleId = resp.body.schedule.id;

                generateAndFirstOccurrence(scheduleId).then(() => {
                    api(
                        ADMIN_KEY,
                        "GET",
                        `/api/ministries/occurrences?from=${isoDate(-1)}&to=${isoDate(14)}&scheduleId=${scheduleId}`,
                    ).then((list) => {
                        const row = list.body.occurrences[0];
                        // "1 Lead Teacher, 2 Helper" is what a coordinator can act on; a
                        // bare "3" is a click of guessing per row.
                        expect(row.gaps).to.have.length(2);
                        const lead = row.gaps.find((g) => g.positionId === posLead);
                        expect(lead.gapCount).to.eq(1);
                        expect(lead.positionName).to.contain("Lead Teacher");
                    });
                });
            });
        });
    });

    // ── per-occurrence overrides ───────────────────────────────────────────

    describe("one occurrence overrides its schedule, then goes back", () => {
        let scheduleId = 0;
        let occurrenceId = 0;

        beforeEach(() => {
            createSchedule(
                standaloneBody({
                    teamId: teamA1,
                    requirements: [
                        { positionId: posLead, minCount: 1, maxCount: 1 },
                        { positionId: posHelper, minCount: 2, maxCount: 2 },
                    ],
                }),
            ).then((resp) => {
                scheduleId = resp.body.schedule.id;
                generateAndFirstOccurrence(scheduleId).then((id) => {
                    occurrenceId = id;
                });
            });
        });

        it("serves the editor its rows and its candidate positions", () => {
            api(ADMIN_KEY, "GET", `/api/ministries/occurrences/${occurrenceId}/requirements`).then((resp) => {
                expect(resp.body.overridden).to.eq(false);
                expect(resp.body.requirements).to.have.length(2);
                // Every active position of the team is offered, including the one with
                // no requirement — that is the line the editor renders unchecked.
                expect(resp.body.positions.map((p) => p.id)).to.include.members([posLead, posHelper, posSpare]);
                // Never another ministry's positions.
                expect(resp.body.positions.map((p) => p.id)).to.not.include(posForeign);
            });
        });

        it("writes overrides that win over the schedule's plan, position by position", () => {
            api(ADMIN_KEY, "POST", `/api/ministries/occurrences/${occurrenceId}/requirements/replace`, {
                requirements: [{ positionId: posLead, minCount: 4, maxCount: 5 }],
            }).then((resp) => {
                expect(resp.body.overridden).to.eq(true);
                // The merge is a UNION (§2.10): Lead is outvoted, and Helper — which this
                // payload does not mention — still comes from the schedule. Leaving a
                // position out is not how an occurrence drops it; see the next test.
                expect(resp.body.requirements).to.have.length(2);

                const lead = resp.body.requirements.find((r) => r.positionId === posLead);
                expect(lead.minCount).to.eq(4);
                expect(lead.maxCount).to.eq(5);
                expect(lead.source).to.eq("occurrence");

                const helper = resp.body.requirements.find((r) => r.positionId === posHelper);
                expect(helper.source).to.eq("schedule");
                expect(helper.minCount).to.eq(2);
            });

            occurrence(occurrenceId).then((row) => {
                expect(row.requiredCount).to.eq(6);
                expect(row.requirementCount).to.eq(2);
                expect(row.requirementsOverridden).to.eq(true);
            });

            // The schedule's own plan is untouched — other occurrences still follow it.
            requirementsOf(scheduleId).then((rows) => {
                expect(rows).to.have.length(2);
                expect(rows.find((r) => r.positionId === posLead).minCount).to.eq(1);
            });
        });

        it("drops a position for one week with an explicit Min 0 / Max 0", () => {
            // "Not this week" has to be a row, because the union hands an omitted
            // position straight back from the schedule. This is what the occurrence
            // editor writes when a schedule-provided box is unchecked.
            api(ADMIN_KEY, "POST", `/api/ministries/occurrences/${occurrenceId}/requirements/replace`, {
                requirements: [
                    { positionId: posLead, minCount: 1, maxCount: 1 },
                    { positionId: posHelper, minCount: 0, maxCount: 0 },
                ],
            });

            occurrence(occurrenceId).then((row) => {
                expect(row.requiredCount, "only the Lead Teacher is required").to.eq(1);
                // A zero-capacity row is a suppression, not a slot, so it is not counted:
                // `requirementCount` answers "how many positions can take anyone".
                expect(row.requirementCount).to.eq(1);
                expect(row.gapCount).to.eq(1);
            });
        });

        it("reports an occurrence whose every position is suppressed as having no needs", () => {
            api(ADMIN_KEY, "POST", `/api/ministries/occurrences/${occurrenceId}/requirements/replace`, {
                requirements: [
                    { positionId: posLead, minCount: 0, maxCount: 0 },
                    { positionId: posHelper, minCount: 0, maxCount: 0 },
                ],
            });

            occurrence(occurrenceId).then((row) => {
                expect(row.requirementCount).to.eq(0);
                expect(row.gapCount).to.eq(0);
            });
        });

        it("tells the editor which positions the schedule provides", () => {
            api(ADMIN_KEY, "GET", `/api/ministries/occurrences/${occurrenceId}/requirements`).then((resp) => {
                expect(resp.body.schedulePositionIds.sort()).to.deep.eq([posLead, posHelper].sort());
            });
        });

        it("goes back to the schedule's plan when the overrides are deleted", () => {
            api(ADMIN_KEY, "POST", `/api/ministries/occurrences/${occurrenceId}/requirements/replace`, {
                requirements: [{ positionId: posLead, minCount: 4, maxCount: 5 }],
            });

            api(ADMIN_KEY, "DELETE", `/api/ministries/occurrences/${occurrenceId}/requirements`).then((resp) => {
                expect(resp.body.overridden).to.eq(false);
                expect(resp.body.requirements).to.have.length(2);
            });

            occurrence(occurrenceId).then((row) => {
                expect(row.requiredCount).to.eq(3);
                expect(row.requirementsOverridden).to.eq(false);
            });
        });

        it("stores exactly one parent per override row (vreq_one_parent_chk)", () => {
            api(ADMIN_KEY, "POST", `/api/ministries/occurrences/${occurrenceId}/requirements/replace`, {
                requirements: [{ positionId: posLead, minCount: 2, maxCount: 2 }],
            }).then(() => {
                dbOk(`SELECT vreq_vsch_ID, vreq_vocc_ID FROM volunteer_requirement_vreq WHERE vreq_vocc_ID = ?`, [
                    occurrenceId,
                ]).then((rows) => {
                    expect(rows).to.have.length(1);
                    expect(rows[0].vreq_vsch_ID).to.eq(null);
                    expect(rows[0].vreq_vocc_ID).to.eq(occurrenceId);
                });
            });
        });

        it("rejects a maxCount below minCount on an override", () => {
            api(
                ADMIN_KEY,
                "POST",
                `/api/ministries/occurrences/${occurrenceId}/requirements/replace`,
                { requirements: [{ positionId: posLead, minCount: 3, maxCount: 1 }] },
                400,
            );
        });

        it("rejects a body with no requirements field at all", () => {
            api(ADMIN_KEY, "POST", `/api/ministries/occurrences/${occurrenceId}/requirements/replace`, {}, 400);
        });
    });

    // ── scope ──────────────────────────────────────────────────────────────

    describe("scope (§4.4, §4.6)", () => {
        let scheduleId = 0;
        let occurrenceId = 0;

        before(() => {
            createSchedule(
                standaloneBody({
                    teamId: teamA1,
                    requirements: [{ positionId: posLead, minCount: 1, maxCount: 1 }],
                }),
            ).then((resp) => {
                scheduleId = resp.body.schedule.id;
                generateAndFirstOccurrence(scheduleId).then((id) => {
                    occurrenceId = id;
                });
            });
        });

        it("denies a coordinator of another ministry every new route", () => {
            // The persona is scoped to ministry B; every fixture here is in ministry A.
            api(COORDINATOR_KEY, "GET", `/api/ministries/occurrences/${occurrenceId}/requirements`, null, 403);
            api(
                COORDINATOR_KEY,
                "POST",
                `/api/ministries/occurrences/${occurrenceId}/requirements/replace`,
                { requirements: [] },
                403,
            );
            api(COORDINATOR_KEY, "DELETE", `/api/ministries/occurrences/${occurrenceId}/requirements`, null, 403);
            api(
                COORDINATOR_KEY,
                "POST",
                `/api/ministries/schedules/${scheduleId}`,
                { requirements: [] },
                403,
            );
        });

        it("left the plan intact after the denials", () => {
            requirementsOf(scheduleId).then((rows) => {
                expect(rows).to.have.length(1);
            });
        });
    });
});
