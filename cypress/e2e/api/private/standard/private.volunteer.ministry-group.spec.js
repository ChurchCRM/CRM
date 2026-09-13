/// <reference types="cypress" />

/**
 * Volunteer v2 — the ministry-owned pool Group, and "Help wanted" (D19, epic #9701).
 *
 * A sibling of `private.volunteer.pools-qualifications.spec.js`, which keeps the
 * membership and qualification surface. This file is about the three things D19
 * changed that nothing else can assert:
 *
 *   1. **A ministry owns a Group.** Creating one creates it (typed "Ministry",
 *      carrying `grp_ministry_id`), renaming the ministry renames it, deleting the
 *      ministry deletes it — and the Groups module answers `409` to an edit or a
 *      delete of it, because its identity now belongs to the ministry.
 *   2. **The hook exception.** A ministry coordinator who does NOT hold the global
 *      `bManageGroups` flag can add and remove pool members through the V2 routes,
 *      and is still refused on an ordinary group — which is the whole point of
 *      putting the exception on `grp_ministry_id` rather than on the user.
 *   3. **Help wanted.** The two ministry fields round-trip, the member surface lists
 *      the advertising ministries, and `POST /me/help-wanted/{id}` joins the pool and
 *      produces exactly one coordinator email per day, with the right wording for a
 *      new member and for somebody offering again.
 *
 * Tiers exercised:
 *   person 1   `admin.api.key`     administrator
 *   person 95  `editrecords.api.key`  Add+Edit records, **no** ManageGroups, no
 *                                     volunteer rights — the coordinator-without-
 *                                     ManageGroups case, which is the one D19 exists
 *                                     for. Verified against `user_usr` in `before`
 *                                     rather than assumed, because a seed change that
 *                                     gave them the flag would turn this spec green
 *                                     while proving nothing.
 *   person 99  `selfedit.api.key`  the EditSelf-exclusive volunteer persona (D14)
 *
 * **Mailpit is optional**, exactly as in `private.volunteer.notifications.spec.js`:
 * the `test` docker profile runs it, other profiles do not, so the delivery
 * assertions skip themselves when `mail:available` says no. Everything provable from
 * the outbox table alone runs everywhere.
 *
 * Cleanup runs in `before` AND `after` (cypress-testing.md: an `after` hook does not
 * run when the runner crashes mid-spec). The pool groups go before the ministries:
 * `grp_ministry_id` is `ON DELETE SET NULL`, so deleting a ministry in raw SQL would
 * leave its group behind as an orphan nobody can recognise.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const TIMERJOBS_LAST_RUN_URL =
    "/admin/api/system/config/sLastTimerJobsRunDateTime";
const VOLUNTEER_URL = "/api/volunteer";
const MINISTRIES_URL = `${VOLUNTEER_URL}/ministries`;
const SCOPES_URL = `${VOLUNTEER_URL}/scopes`;

/** Every fixture name starts with this so cleanup deletes exactly what this spec made. */
const PREFIX = "MINGRP19";

/** `list_lst` option id of the "Ministry" group type (F11, seed row `(3,1,1,'Ministry')`). */
const MINISTRY_GROUP_TYPE = 1;

/** An ordinary seed group — the "still refused on a plain group" half of the exception. */
const PLAIN_GROUP = 1; // "Angels class"

const PERSON_COORDINATOR_NO_GROUPS = 95; // judith.matthews — editrecords.api.key
const PERSON_VOLUNTEER = 99; // amanda.black — EditSelf-exclusive (D14)
const PERSON_ADMIN = 1;
const PERSON_SOMEBODY = 8; // herminia.bennett — a person with an email address

const VOLUNTEER_EMAIL = "amanda.black@example.com";
const COORDINATOR_EMAIL = "judith.matthews@example.com";

let ministryA = 0;
let ministryB = 0;
let groupA = 0;
let mailpitAvailable = false;

function coordinatorKey() {
    return Cypress.env("editrecords.api.key");
}

function volunteerKey() {
    return Cypress.env("selfedit.api.key");
}

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

function setVersion(value) {
    cy.makePrivateAdminAPICall("POST", SETTING_URL, { value }, 200);
}

function cleanupFixtures() {
    dbOk(
        `DELETE r FROM person2group2role_p2g2r r
           JOIN group_grp g ON g.grp_ID = r.p2g2r_grp_ID
           JOIN volunteer_ministry_vmin m ON m.vmin_ID = g.grp_ministry_id
          WHERE m.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(
        `DELETE n FROM volunteer_notification_vntf n
          WHERE n.vntf_Type = 'help_offer'`,
    );
    dbOk(
        `DELETE g FROM group_grp g
           JOIN volunteer_ministry_vmin m ON m.vmin_ID = g.grp_ministry_id
          WHERE m.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(`DELETE FROM volunteer_scope_vscp WHERE vscp_per_ID = ?`, [
        PERSON_COORDINATOR_NO_GROUPS,
    ]);
    dbOk(`DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?`, [
        `${PREFIX}%`,
    ]);
}

function createMinistry(name) {
    return cy
        .makePrivateAdminAPICall(
            "POST",
            MINISTRIES_URL,
            { name: `${PREFIX} ${name}` },
            201,
        )
        .then((resp) => resp.body);
}

function poolGroupIdOf(ministryId) {
    return dbOk(`SELECT grp_ID FROM group_grp WHERE grp_ministry_id = ?`, [
        ministryId,
    ]).then((rows) => (rows.length === 0 ? 0 : Number(rows[0].grp_ID)));
}

/** Run the timer jobs for real, clearing #9724's rate limit first. */
function drain() {
    cy.makePrivateAdminAPICall("POST", TIMERJOBS_LAST_RUN_URL, { value: "" }, 200);

    return cy
        .makePrivateAdminAPICall("POST", "/api/background/timerjobs", {}, 200)
        .then((resp) => {
            expect(resp.body.ran, "the rate limit must not swallow the drain").to.be
                .true;
        });
}

function listMail() {
    return cy.task("mail:list", { limit: 100 }).then((result) => {
        if (!result.ok) {
            return [];
        }
        return result.body.messages || [];
    });
}

function mailToCount(address, subjectFragment) {
    return listMail().then(
        (messages) =>
            messages.filter(
                (message) =>
                    (message.To || []).some(
                        (to) =>
                            (to.Address || "").toLowerCase() ===
                            address.toLowerCase(),
                    ) &&
                    (message.Subject || "")
                        .toLowerCase()
                        .includes(subjectFragment.toLowerCase()),
            ).length,
    );
}

function mailBody(address, subjectFragment) {
    return listMail().then((messages) => {
        const matches = messages.filter(
            (message) =>
                (message.To || []).some(
                    (to) => (to.Address || "").toLowerCase() === address.toLowerCase(),
                ) &&
                (message.Subject || "")
                    .toLowerCase()
                    .includes(subjectFragment.toLowerCase()),
        );
        expect(matches, `one message to ${address} about "${subjectFragment}"`).to
            .have.length(1);

        return cy.task("mail:get", { id: matches[0].ID }).then((result) => {
            expect(result.ok).to.be.true;
            return result.body;
        });
    });
}

function requireMailpit(ctx) {
    if (!mailpitAvailable) {
        ctx.skip();
    }
}

describe("Volunteer v2 ministry-owned pool Group and Help wanted (D19)", () => {
    before(() => {
        cy.task("mail:available").then((result) => {
            mailpitAvailable = result.available;
        });

        setVersion("v2");
        cleanupFixtures();

        // The premise of every "coordinator without Manage Groups" test below. If a
        // seed change ever grants the flag, these tests would pass for the wrong
        // reason — so the premise is asserted rather than assumed.
        dbOk(`SELECT usr_ManageGroups, usr_Admin FROM user_usr WHERE usr_per_ID = ?`, [
            PERSON_COORDINATOR_NO_GROUPS,
        ]).then((rows) => {
            expect(rows, "the fixture user must exist").to.have.length(1);
            expect(
                Number(rows[0].usr_ManageGroups),
                "person 95 must NOT hold bManageGroups, or this spec proves nothing",
            ).to.eq(0);
            expect(Number(rows[0].usr_Admin)).to.eq(0);
        });

        createMinistry("Coffee Bar").then((body) => {
            ministryA = body.ministry.id;
            poolGroupIdOf(ministryA).then((id) => {
                groupA = id;
            });
        });
        createMinistry("Sound Booth").then((body) => {
            ministryB = body.ministry.id;
        });
    });

    after(() => {
        cleanupFixtures();
        setVersion("v1");
    });

    // -----------------------------------------------------------------
    // D19 §A — the Group a ministry is born with
    // -----------------------------------------------------------------
    describe("The Group a ministry owns", () => {
        it("creates it with the ministry, typed Ministry and carrying the ministry id", () => {
            dbOk(
                `SELECT grp_ID, grp_Name, grp_Type, grp_ministry_id
                   FROM group_grp WHERE grp_ministry_id = ?`,
                [ministryA],
            ).then((rows) => {
                expect(rows, "exactly one group per ministry").to.have.length(1);
                expect(rows[0].grp_Name).to.eq(`${PREFIX} Coffee Bar`);
                expect(Number(rows[0].grp_Type)).to.eq(MINISTRY_GROUP_TYPE);
                expect(Number(rows[0].grp_ministry_id)).to.eq(ministryA);
            });
        });

        it("reports the group on the create response and on the ministry detail", () => {
            createMinistry("Nursery").then((body) => {
                expect(body.poolGroupId).to.be.a("number").and.to.be.greaterThan(0);

                cy.makePrivateAdminAPICall(
                    "GET",
                    `${MINISTRIES_URL}/${body.ministry.id}`,
                    null,
                    200,
                ).then((detail) => {
                    expect(detail.body.poolGroupId).to.eq(body.poolGroupId);
                    expect(detail.body.poolGroupName).to.eq(`${PREFIX} Nursery`);
                    expect(detail.body.pool).to.be.an("array").that.is.empty;
                });

                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `${MINISTRIES_URL}/${body.ministry.id}`,
                    null,
                    200,
                );
            });
        });

        it("renames the group when the ministry is renamed", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryB}`,
                { name: `${PREFIX} Sound & Lights` },
                200,
            );
            dbOk(`SELECT grp_Name FROM group_grp WHERE grp_ministry_id = ?`, [
                ministryB,
            ]).then((rows) => {
                expect(rows[0].grp_Name).to.eq(`${PREFIX} Sound & Lights`);
            });
        });

        it("deletes the group, and its memberships, when the ministry is deleted", () => {
            createMinistry("Throwaway").then((body) => {
                const ministryId = body.ministry.id;
                const groupId = body.poolGroupId;

                cy.makePrivateAdminAPICall(
                    "POST",
                    `${MINISTRIES_URL}/${ministryId}/pool/${PERSON_SOMEBODY}`,
                    null,
                    201,
                );
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `${MINISTRIES_URL}/${ministryId}`,
                    null,
                    200,
                );

                dbOk(`SELECT COUNT(*) AS c FROM group_grp WHERE grp_ID = ?`, [
                    groupId,
                ]).then((rows) => {
                    expect(Number(rows[0].c), "the pool group goes with it").to.eq(0);
                });
                dbOk(
                    `SELECT COUNT(*) AS c FROM person2group2role_p2g2r WHERE p2g2r_grp_ID = ?`,
                    [groupId],
                ).then((rows) => {
                    expect(Number(rows[0].c)).to.eq(0);
                });
            });
        });

        it("keeps the group visible in the Groups module, flagged with its ministry", () => {
            cy.makePrivateAdminAPICall("GET", "/api/groups/", null, 200).then(
                (resp) => {
                    const row = resp.body.find((g) => g.Id === groupA);
                    expect(row, "a managed group is still listed").to.exist;
                    expect(row.ministryId).to.eq(ministryA);
                    expect(row.ministryName).to.eq(`${PREFIX} Coffee Bar`);

                    const plain = resp.body.find((g) => g.Id === PLAIN_GROUP);
                    expect(plain.ministryId, "an ordinary group is unchanged").to.eq(
                        null,
                    );
                    expect(plain.ministryName).to.eq(null);
                },
            );
        });
    });

    // -----------------------------------------------------------------
    // D19 §A.4 — the Groups module refuses to rename or delete it
    // -----------------------------------------------------------------
    describe("The Groups module answers 409 for a managed group", () => {
        it("refuses POST /api/groups/{id} with a message naming the ministry", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/groups/${groupA}`,
                { groupName: "Renamed from Groups", groupType: 0, description: "" },
                409,
            ).then((resp) => {
                expect(resp.body.success).to.eq(false);
                expect(resp.body.message).to.include(`${PREFIX} Coffee Bar`);
                expect(resp.body.ministryId).to.eq(ministryA);
            });

            // And the refusal is real: the name is untouched.
            dbOk(`SELECT grp_Name FROM group_grp WHERE grp_ID = ?`, [groupA]).then(
                (rows) => {
                    expect(rows[0].grp_Name).to.eq(`${PREFIX} Coffee Bar`);
                },
            );
        });

        it("refuses DELETE /api/groups/{id}", () => {
            cy.makePrivateAdminAPICall("DELETE", `/api/groups/${groupA}`, null, 409);
            dbOk(`SELECT COUNT(*) AS c FROM group_grp WHERE grp_ID = ?`, [
                groupA,
            ]).then((rows) => {
                expect(Number(rows[0].c)).to.eq(1);
            });
        });

        it("still lets anyone with Manage Groups add and remove members there", () => {
            // Explicitly asked for: the Groups module stays the second door to the
            // same roster. Only the group's own identity moved to the ministry.
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/groups/${groupA}/addperson/${PERSON_SOMEBODY}`,
                {},
                200,
            );
            cy.makePrivateAdminAPICall(
                "GET",
                `${MINISTRIES_URL}/${ministryA}/pool`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.members.map((m) => m.personId)).to.include(
                    PERSON_SOMEBODY,
                );
            });
            cy.makePrivateAdminAPICall(
                "DELETE",
                `/api/groups/${groupA}/removeperson/${PERSON_SOMEBODY}`,
                null,
                200,
            );
        });

        it("leaves an ordinary group fully editable", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/groups/${PLAIN_GROUP}/members`,
                null,
                200,
            );
            // Renaming an ordinary group to its own name is a no-op that still has
            // to be ACCEPTED — the 409 must be about the ministry, not about groups.
            cy.makePrivateAdminAPICall(
                "POST",
                `/api/groups/${PLAIN_GROUP}`,
                { groupName: "Angels class", groupType: 4, description: "" },
                200,
            );
        });
    });

    // -----------------------------------------------------------------
    // D19 §A.3 — the hook exception, which is the whole point
    // -----------------------------------------------------------------
    describe("A coordinator without Manage Groups", () => {
        before(() => {
            cy.makePrivateAdminAPICall(
                "POST",
                SCOPES_URL,
                {
                    personId: PERSON_COORDINATOR_NO_GROUPS,
                    scopeType: "ministry",
                    scopeId: ministryA,
                },
                [200, 201],
            );
        });

        it("can add and remove pool members through the V2 routes", () => {
            cy.makePrivateAPICall(
                coordinatorKey(),
                "POST",
                `${MINISTRIES_URL}/${ministryA}/pool/${PERSON_SOMEBODY}`,
                null,
                [200, 201],
            );
            cy.makePrivateAPICall(
                coordinatorKey(),
                "GET",
                `${MINISTRIES_URL}/${ministryA}/pool`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.members.map((m) => m.personId)).to.include(
                    PERSON_SOMEBODY,
                );
            });
            cy.makePrivateAPICall(
                coordinatorKey(),
                "DELETE",
                `${MINISTRIES_URL}/${ministryA}/pool/${PERSON_SOMEBODY}`,
                null,
                200,
            );
        });

        it("is still refused on an ordinary group, by the unchanged V1 rule", () => {
            // The exception is on `grp_ministry_id`, never on the user — so the
            // same caller who may write the pool group may write nothing else.
            cy.makePrivateAPICall(
                coordinatorKey(),
                "POST",
                `/api/groups/${PLAIN_GROUP}/addperson/${PERSON_SOMEBODY}`,
                {},
                403,
            );
            cy.makePrivateAPICall(
                coordinatorKey(),
                "DELETE",
                `/api/groups/${PLAIN_GROUP}`,
                null,
                403,
            );
        });

        it("is still refused on ANOTHER ministry's pool group", () => {
            cy.makePrivateAPICall(
                coordinatorKey(),
                "POST",
                `${MINISTRIES_URL}/${ministryB}/pool/${PERSON_SOMEBODY}`,
                null,
                403,
            );
        });
    });

    // -----------------------------------------------------------------
    // D19 §C — Help wanted
    // -----------------------------------------------------------------
    describe("Help wanted", () => {
        const TEXT = "We would love more help on Sunday mornings.";

        it("round-trips both fields, defaulting to off", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `${MINISTRIES_URL}/${ministryA}`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.ministry.helpWanted).to.eq(false);
                expect(resp.body.ministry.helpWantedText).to.eq(null);
            });

            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}`,
                { helpWanted: true, helpWantedText: TEXT },
                200,
            ).then((resp) => {
                expect(resp.body.ministry.helpWanted).to.eq(true);
                expect(resp.body.ministry.helpWantedText).to.eq(TEXT);
            });

            cy.makePrivateAdminAPICall(
                "GET",
                `${MINISTRIES_URL}/${ministryA}`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.ministry.helpWanted).to.eq(true);
                expect(resp.body.ministry.helpWantedText).to.eq(TEXT);
            });
        });

        it("lists only the advertising ministries on the member surface", () => {
            cy.makePrivateAPICall(
                volunteerKey(),
                "GET",
                `${VOLUNTEER_URL}/me/help-wanted`,
                null,
                200,
            ).then((resp) => {
                const ids = resp.body.ministries.map((m) => m.ministryId);
                expect(ids).to.include(ministryA);
                expect(ids).to.not.include(ministryB);

                const row = resp.body.ministries.find(
                    (m) => m.ministryId === ministryA,
                );
                expect(row.ministryName).to.eq(`${PREFIX} Coffee Bar`);
                expect(row.helpWantedText).to.eq(TEXT);
                expect(row.inPool).to.eq(false);
            });
        });

        it("refuses an offer to a ministry that is not asking, and 404s an unknown one", () => {
            cy.makePrivateAPICall(
                volunteerKey(),
                "POST",
                `${VOLUNTEER_URL}/me/help-wanted/${ministryB}`,
                null,
                403,
            );
            cy.makePrivateAPICall(
                volunteerKey(),
                "POST",
                `${VOLUNTEER_URL}/me/help-wanted/99999999`,
                null,
                404,
            );
        });

        it("adds the volunteer to the pool and enqueues one help_offer", () => {
            cy.makePrivateAPICall(
                volunteerKey(),
                "POST",
                `${VOLUNTEER_URL}/me/help-wanted/${ministryA}`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.joinedPool, "this tap is what put them in").to.eq(
                    true,
                );
                expect(resp.body.notified).to.be.greaterThan(0);
            });

            cy.makePrivateAdminAPICall(
                "GET",
                `${MINISTRIES_URL}/${ministryA}/pool`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.members.map((m) => m.personId)).to.include(
                    PERSON_VOLUNTEER,
                );
            });

            dbOk(
                `SELECT vntf_per_ID, vntf_DedupeKey, vntf_Context
                   FROM volunteer_notification_vntf WHERE vntf_Type = 'help_offer'`,
            ).then((rows) => {
                expect(rows, "one row per coordinator").to.have.length(1);
                expect(Number(rows[0].vntf_per_ID)).to.eq(
                    PERSON_COORDINATOR_NO_GROUPS,
                );
                expect(rows[0].vntf_DedupeKey).to.include(
                    `help_offer:${ministryA}:${PERSON_VOLUNTEER}:`,
                );
                expect(JSON.parse(rows[0].vntf_Context).joinedPool).to.eq(true);
            });
        });

        it("sends nothing extra on a second tap the same day", () => {
            cy.makePrivateAPICall(
                volunteerKey(),
                "POST",
                `${VOLUNTEER_URL}/me/help-wanted/${ministryA}`,
                null,
                200,
            ).then((resp) => {
                // They are in the pool now, so this is the "again" path.
                expect(resp.body.joinedPool).to.eq(false);
            });

            dbOk(
                `SELECT COUNT(*) AS c FROM volunteer_notification_vntf
                  WHERE vntf_Type = 'help_offer'`,
            ).then((rows) => {
                expect(
                    Number(rows[0].c),
                    "the dedupe key carries the date — one per coordinator per day",
                ).to.eq(1);
            });
        });

        it("reports inPool on the member surface once they have joined", () => {
            cy.makePrivateAPICall(
                volunteerKey(),
                "GET",
                `${VOLUNTEER_URL}/me/help-wanted`,
                null,
                200,
            ).then((resp) => {
                const row = resp.body.ministries.find(
                    (m) => m.ministryId === ministryA,
                );
                expect(row.inPool).to.eq(true);
            });
        });

        it("delivers one coordinator email saying they were added", function () {
            requireMailpit(this);
            cy.task("mail:clear");
            drain();

            mailToCount(COORDINATOR_EMAIL, "wants to help with").then((count) => {
                expect(count, "exactly one coordinator email").to.eq(1);
            });

            mailBody(COORDINATOR_EMAIL, "wants to help with").then((message) => {
                const text = message.Text || "";
                expect(message.Subject).to.include(`${PREFIX} Coffee Bar`);
                expect(text).to.include("has been added to its volunteer pool");
                expect(text).to.include("Qualify them for a position");
                // Reply-To points at the VOLUNTEER, so a coordinator can just reply.
                const replyTo = (message.ReplyTo || []).map((r) =>
                    (r.Address || "").toLowerCase(),
                );
                expect(replyTo).to.include(VOLUNTEER_EMAIL);
            });
        });

        it("uses the 'again' wording for somebody already in the pool", function () {
            requireMailpit(this);
            cy.task("mail:clear");

            // A new day: the dedupe key is per (ministry, person, day, recipient), so
            // moving yesterday's row out of the way is what makes the next tap send.
            dbOk(`DELETE FROM volunteer_notification_vntf WHERE vntf_Type = 'help_offer'`);

            cy.makePrivateAPICall(
                volunteerKey(),
                "POST",
                `${VOLUNTEER_URL}/me/help-wanted/${ministryA}`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.joinedPool).to.eq(false);
            });

            drain();

            mailBody(COORDINATOR_EMAIL, "wants to help with").then((message) => {
                const text = message.Text || "";
                expect(text).to.include("is already in the");
                expect(text).to.include("wants to help again");
                expect(text).to.include("Qualify them for a position");
            });
        });

        it("switching Help wanted off takes the ministry off the member surface", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}`,
                { helpWanted: false },
                200,
            );
            cy.makePrivateAPICall(
                volunteerKey(),
                "GET",
                `${VOLUNTEER_URL}/me/help-wanted`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.ministries.map((m) => m.ministryId)).to.not.include(
                    ministryA,
                );
            });
            cy.makePrivateAPICall(
                volunteerKey(),
                "POST",
                `${VOLUNTEER_URL}/me/help-wanted/${ministryA}`,
                null,
                403,
            );
            // Put it back for anything that runs after this describe.
            cy.makePrivateAdminAPICall(
                "POST",
                `${MINISTRIES_URL}/${ministryA}`,
                { helpWanted: true },
                200,
            );
        });

        it("keeps the advert behind the rollout flag", () => {
            setVersion("v1");
            cy.makePrivateAPICall(
                volunteerKey(),
                "GET",
                `${VOLUNTEER_URL}/me/help-wanted`,
                null,
                403,
            );
            setVersion("v2");
        });

        it("refuses an unauthenticated offer", () => {
            cy.request({
                method: "POST",
                url: `${VOLUNTEER_URL}/me/help-wanted/${ministryA}`,
                failOnStatusCode: false,
                withCredentials: false,
            }).then((resp) => {
                expect(resp.status).to.eq(401);
            });
        });

        it("never takes a person id from the caller", () => {
            // The §3.3.3 rule, structurally: there is no personId anywhere on this
            // route, so a body carrying one changes nothing about who is added.
            cy.makePrivateAPICall(
                volunteerKey(),
                "POST",
                `${VOLUNTEER_URL}/me/help-wanted/${ministryA}`,
                { personId: PERSON_ADMIN },
                200,
            );
            cy.makePrivateAdminAPICall(
                "GET",
                `${MINISTRIES_URL}/${ministryA}/pool`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.members.map((m) => m.personId)).to.not.include(
                    PERSON_ADMIN,
                );
            });
        });
    });
});
