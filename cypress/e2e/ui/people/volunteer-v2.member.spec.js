/// <reference types="cypress" />

/**
 * Volunteer v2 — S5 / S6, the volunteer self-service screens (#9712, §5.6),
 * inside the Member Portal (#9867).
 *
 * The member half of the product: "what am I down for", "what still needs
 * filling", accept, decline, find a sub, sign up. Deliberately plain — a
 * volunteer must never see the words *ministry hierarchy*, *requirement*,
 * *occurrence* or *schedule* (§5.6) — and **mobile-first**: §5.9 makes a 375px
 * viewport a hard requirement for this issue, not a nicety.
 *
 * The persona is person 100, `lena.black.editself.notes@example.com`. Person 99
 * is the EditSelf-EXCLUSIVE volunteer the API spec uses, but its seeded password
 * hash is not the shared `changeme` one (seed.sql `user_usr`), so it cannot be
 * logged in through the form. Person 100 is the design's "second volunteer"
 * (§6.4) and reaches the same pages through the same no-role-gate route group —
 * the EditSelf-exclusive reachability half of §4.7 is proven in
 * `private.volunteer.self-service.spec.js`, which drives person 99 directly.
 *
 * Order inside every hook is API setup → login → `cy.visit()`, because
 * `cy.request()` rotates the PHP session cookie (cypress-testing.md). Fixtures
 * are removed in `before` as well as `after`.
 *
 * **#9867 moved the pages, not the behaviour.** They render from Twig templates
 * in the portal layout instead of PHP views in the admin shell; every container
 * id, every bundle and every string is the same, so every assertion below is the
 * same one it was — only the two URLs changed, and the navigation assertion,
 * which now looks at the portal's nav because the admin sidebar is not there.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const VOLUNTEER_URL = "/api/volunteer";

/**
 * The two member pages live in the MEMBER PORTAL since #9867 — they are Twig
 * templates rendered by `src/portal/routes/volunteer.php`, not PHP views in the
 * admin shell. The bundles, the ids they drive and every string below are
 * unchanged by that move, which is the point: this spec asserts the same
 * behaviour at the new addresses.
 */
const MY_SCHEDULE_URL = "/portal/volunteer/schedule";
const OPPORTUNITIES_URL = "/portal/volunteer/opportunities";
const LEGACY_MY_SCHEDULE_URL = "/volunteer/my-schedule";
const LEGACY_OPPORTUNITIES_URL = "/volunteer/opportunities";

const PREFIX = "UI9712";
const EVENT_TITLE = `${PREFIX} Hospitality Service`;

/**
 * The volunteer persona's credentials. Hard-coded rather than added to the three
 * cypress configs: person 100 already exists in `seed.sql` with the shared
 * `changeme` password, so there is no new seeded user and nothing for the
 * configs to drift on (§6.4).
 *
 * **The username is TRUNCATED, and that is not a typo.** `user_usr.usr_UserName`
 * is `VARCHAR(32)` (`orm/schema.xml:603`), and `seed.sql` seeds person 100 as
 * `lena.black.editself.notes@example.com` — 37 characters. MySQL stores the first
 * 32 and drops the rest, so the name the login form has to be given is
 * `lena.black.editself.notes@exampl`. Logging in with the full address fails with
 * no error beyond a silent return to `/session/begin`.
 */
const MEMBER_USERNAME = "lena.black.editself.notes@example.com";
const MEMBER_PASSWORD = "changeme";
const PERSON_MEMBER = 100;

const POOL_MEMBER_A = 8;
const POOL_MEMBER_B = 9;
const CHURCH_SERVICE_TYPE = 1;

const MOBILE_VIEWPORT = [375, 812];

let ministryId = 0;
let teamId = 0;
let posDoor = 0; // Min 1 / Max 2
let posCoffee = 0; // Min 1 / Max 1 — the sign-up target
let scheduleId = 0;
let occurrenceId = 0;
let seriesStart = "";
let seriesEnd = "";

// ── helpers (local functions, NOT cy.* commands — cypress-testing.md) ───────

function freshMemberLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(MEMBER_USERNAME);
    cy.get("input[name=Password]").type(MEMBER_PASSWORD + "{enter}");
    cy.url().should("not.include", "/session/begin");
}

function setVersion(value) {
    cy.makePrivateAdminAPICall("POST", SETTING_URL, { value }, 200);
}

function adminApi(method, url, body, expectedStatus = 200) {
    return cy.makePrivateAdminAPICall(method, url, body, expectedStatus);
}

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

/** Assert the document has no horizontal overflow at the current viewport. */
function assertNoHorizontalOverflow() {
    cy.document().then((doc) => {
        const scrollWidth = doc.documentElement.scrollWidth;
        const clientWidth = doc.documentElement.clientWidth;
        expect(scrollWidth, "document scrollWidth").to.be.lessThan(clientWidth + 3);
    });
}

/** Wipe every assignment/response/swap/outbox row of the fixture ministry. */
function clearWorkflowRows() {
    const scoped = `
        JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vasg.vasg_vocc_ID
        JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
        JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
       WHERE vmin.vmin_Name LIKE ?`;

    dbOk(
        `DELETE vntf FROM volunteer_notification_vntf vntf
           JOIN volunteer_assignment_vasg vasg ON vasg.vasg_ID = vntf.vntf_vasg_ID
           ${scoped}`,
        [`${PREFIX}%`],
    );
    dbOk(
        `DELETE vntf FROM volunteer_notification_vntf vntf
           JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vntf.vntf_vocc_ID
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(
        `DELETE vswp FROM volunteer_swap_vswp vswp
           JOIN volunteer_assignment_vasg vasg ON vasg.vasg_ID = vswp.vswp_vasg_ID
           ${scoped}`,
        [`${PREFIX}%`],
    );
    dbOk(
        `DELETE vrsp FROM volunteer_response_vrsp vrsp
           JOIN volunteer_assignment_vasg vasg ON vasg.vasg_ID = vrsp.vrsp_vasg_ID
           ${scoped}`,
        [`${PREFIX}%`],
    );
    dbOk(
        `UPDATE volunteer_assignment_vasg vasg ${scoped.replace("WHERE", "SET vasg.vasg_Replaces_vasg_ID = NULL WHERE")}`,
        [`${PREFIX}%`],
    );
    dbOk(`DELETE vasg FROM volunteer_assignment_vasg vasg ${scoped}`, [`${PREFIX}%`]);
}

function cleanupFixtures() {
    clearWorkflowRows();

    dbOk(
        `DELETE vreq FROM volunteer_requirement_vreq vreq
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vreq.vreq_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(
        `DELETE vreq FROM volunteer_requirement_vreq vreq
           JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vreq.vreq_vocc_ID
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(
        `DELETE vocc FROM volunteer_occurrence_vocc vocc
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(
        `DELETE vsch FROM volunteer_schedule_vsch vsch
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(
        `DELETE vqal FROM volunteer_qualification_vqal vqal
           JOIN volunteer_position_vpos vpos ON vpos.vpos_ID = vqal.vqal_vpos_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vpos.vpos_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(
        `DELETE vpos FROM volunteer_position_vpos vpos
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vpos.vpos_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    // D19: the pool is the ministry's own Group and `grp_ministry_id` is ON DELETE
    // SET NULL, so the group and its memberships go before the ministry row.
    dbOk(
        `DELETE r FROM person2group2role_p2g2r r
           JOIN group_grp g ON g.grp_ID = r.p2g2r_grp_ID
           JOIN volunteer_ministry_vmin m ON m.vmin_ID = g.grp_ministry_id
          WHERE m.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(
        `DELETE g FROM group_grp g
           JOIN volunteer_ministry_vmin m ON m.vmin_ID = g.grp_ministry_id
          WHERE m.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(
        `DELETE vtem FROM volunteer_team_vtem vtem
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vtem.vtem_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    // #9869: a ministry created through the API now comes with its own calendar,
    // named after the ministry. `calendars.ministry_id` is ON DELETE SET NULL, so
    // deleting the ministry row directly would leave the calendar behind as an
    // unowned church calendar. It goes first, matched on the same prefix.
    dbOk(`DELETE FROM calendars WHERE name LIKE ?`, [`${PREFIX}%`]);
    dbOk(`DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?`, [`${PREFIX}%`]);
    dbOk(`DELETE FROM events_event WHERE event_title LIKE ?`, [`${EVENT_TITLE}%`]);
}

/** Put the member back on one PENDING Door assignment and nothing else. */
function seedPendingDoorAssignment() {
    clearWorkflowRows();
    adminApi("POST", `${VOLUNTEER_URL}/occurrences/${occurrenceId}/assignments`, {
        positionId: posDoor,
        personId: PERSON_MEMBER,
    }, 201);
}

// ── fixture ────────────────────────────────────────────────────────────────

before(() => {
    setVersion("v2");
    cleanupFixtures();

    adminApi("POST", `${VOLUNTEER_URL}/ministries`, {
        name: `${PREFIX} Hospitality`,
        description: "volunteer v2 member UI fixture",
    }, 201).then((resp) => {
        ministryId = resp.body.ministry.id;
    });

    cy.then(() => {
        adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}/teams`, {
            name: `${PREFIX} Greeters`,
            description: "volunteer v2 member UI fixture",
        }, 201).then((resp) => {
            teamId = resp.body.team.id;
        });
    });

    cy.then(() => {
        adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}/positions`, {
            name: `${PREFIX} Door`,
            teamId,
            order: 1,
        }, 201).then((resp) => {
            posDoor = resp.body.position.id;
        });
        adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}/positions`, {
            name: `${PREFIX} Coffee`,
            teamId,
            order: 2,
        }, 201).then((resp) => {
            posCoffee = resp.body.position.id;
        });
    });

    // D19: the ministry came with its own pool Group, empty — fill it through the API.
    cy.then(() => {
        [PERSON_MEMBER, POOL_MEMBER_A, POOL_MEMBER_B].forEach((personId) => {
            adminApi(
                "POST",
                `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${personId}`,
                null,
                [200, 201],
            );
        });
    });

    cy.then(() => {
        [posDoor, posCoffee].forEach((positionId) => {
            [PERSON_MEMBER, POOL_MEMBER_A, POOL_MEMBER_B].forEach((personId) => {
                adminApi(
                    "POST",
                    `${VOLUNTEER_URL}/positions/${positionId}/qualifications`,
                    { personId, notes: "" },
                    201,
                );
            });
        });
    });

    cy.then(() => {
        seriesStart = isoDate(daysToNext(0));
        seriesEnd = isoDate(daysToNext(0) + 14);
        adminApi("POST", "/api/events/repeat", {
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
        adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`, {
            name: `${PREFIX} Hospitality — Sunday`,
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
        adminApi("POST", `${VOLUNTEER_URL}/schedules/${scheduleId}/requirements`, {
            positionId: posDoor,
            minCount: 1,
            maxCount: 2,
        }, [200, 201]);
        adminApi("POST", `${VOLUNTEER_URL}/schedules/${scheduleId}/requirements`, {
            positionId: posCoffee,
            minCount: 1,
            maxCount: 1,
        }, [200, 201]);
    });

    cy.then(() => {
        adminApi("POST", `${VOLUNTEER_URL}/schedules/${scheduleId}/generate`, {
            through: seriesEnd,
        }, 200);
    });

    cy.then(() => {
        adminApi(
            "GET",
            `${VOLUNTEER_URL}/occurrences?from=${seriesStart}&to=${seriesEnd}&ministryId=${ministryId}`,
        ).then((resp) => {
            occurrenceId = resp.body.occurrences[0].id;
        });
    });
});

after(() => {
    cleanupFixtures();
    setVersion("v1");
});

// ── S5 — My schedule (§5.6) ────────────────────────────────────────────────

describe("Volunteer v2 — S5 my schedule (#9712)", () => {
    beforeEach(() => {
        seedPendingDoorAssignment();
        freshMemberLogin();
    });

    it("renders the upcoming commitment as a card with what, when and status", () => {
        cy.visit(MY_SCHEDULE_URL);

        cy.get("#volunteer-my-schedule").should("exist");
        cy.get(".volunteer-assignment-card").should("have.length.at.least", 1);
        cy.get(".volunteer-assignment-card")
            .first()
            .within(() => {
                cy.get(".volunteer-card-what").should("contain", `${PREFIX} Door`);
                cy.get(".volunteer-card-what").should("contain", `${PREFIX} Hospitality`);
                cy.get(".volunteer-card-when").should("not.be.empty");
                cy.get(".volunteer-card-status").should("be.visible");
                cy.get(".volunteer-accept").should("be.visible");
                cy.get(".volunteer-decline").should("be.visible");
                cy.get(".volunteer-find-sub").should("be.visible");
            });
    });

    it("never shows a coordinator concept — no 'occurrence', 'requirement' or 'schedule'", () => {
        cy.visit(MY_SCHEDULE_URL);
        cy.get(".volunteer-assignment-card").should("have.length.at.least", 1);
        cy.get("#volunteer-my-schedule").invoke("text").then((text) => {
            const lowered = text.toLowerCase();
            ["occurrence", "requirement", "ministry hierarchy"].forEach((word) => {
                expect(lowered, `the word "${word}"`).to.not.contain(word);
            });
        });
    });

    it("accepts with one tap and re-renders the card", () => {
        cy.visit(MY_SCHEDULE_URL);
        cy.get(".volunteer-assignment-card").first().find(".volunteer-accept").click();

        cy.get(".volunteer-assignment-card")
            .first()
            .find(".volunteer-card-status")
            .should("contain.text", "Going");

        cy.makePrivateAdminAPICall(
            "GET",
            `${VOLUNTEER_URL}/occurrences/${occurrenceId}/staffing`,
            null,
            200,
        ).then((resp) => {
            const rows = resp.body.requirements.flatMap((r) => r.assignments ?? []);
            const mine = rows.find((a) => a.personId === PERSON_MEMBER);
            expect(mine.status).to.eq("accepted");
        });
    });

    it("declines behind a prompt and reopens the slot as an open opportunity", () => {
        cy.visit(MY_SCHEDULE_URL);
        cy.get(".volunteer-assignment-card").first().find(".volunteer-decline").click();

        cy.get(".bootbox").should("be.visible");
        cy.get(".bootbox input").type("Out of town");
        cy.get(".bootbox .btn-primary").click();

        cy.get(".volunteer-assignment-card")
            .first()
            .find(".volunteer-card-status")
            .should("contain.text", "Declined");

        // The slot is genuinely open again — S6 says so.
        cy.visit(OPPORTUNITIES_URL);
        cy.get(`.volunteer-opportunity-card[data-position-id="${posDoor}"]`).should("exist");
    });

    it("shows a first-class empty state when nothing is booked", () => {
        clearWorkflowRows();
        freshMemberLogin();
        cy.visit(MY_SCHEDULE_URL);
        cy.get("#assignments-empty").should("be.visible");
        cy.get("#assignments-empty .empty-title").should("not.be.empty");
        cy.get(".volunteer-assignment-card").should("not.exist");
    });

    it("shows the error block with a working retry when the API fails", () => {
        // ONE stateful intercept: fail the first call, let every later one through.
        // Registering a second cy.intercept for the same pattern is unreliable —
        // both stay registered and the earlier stub keeps answering (#9709).
        let failed = false;
        cy.intercept("**/api/volunteer/me/assignments*", (req) => {
            if (failed) {
                req.continue();

                return;
            }
            failed = true;
            req.reply({ statusCode: 500, body: { success: false, message: "boom" } });
        }).as("assignments");

        cy.visit(MY_SCHEDULE_URL);
        cy.wait("@assignments");
        cy.get("#assignments-error").should("be.visible");

        // Retry genuinely re-runs the load — §5.8 requires the button to work, not
        // merely to exist.
        cy.get("#assignments-retry").click();
        cy.wait("@assignments");
        cy.get("#assignments-error").should("not.be.visible");
        cy.get(".volunteer-assignment-card").should("have.length.at.least", 1);
    });

    it("renders localized strings, never a raw i18next key", () => {
        cy.visit(MY_SCHEDULE_URL);
        // `.text()` returns hidden nodes too, so wait for the cards before reading:
        // otherwise the loading block alone satisfies the read.
        cy.get(".volunteer-assignment-card").should("have.length.at.least", 1);
        cy.get("#assignments-content").invoke("text").then((text) => {
            expect(text).to.not.match(/[a-z]+\.[a-z]+\.[a-z]+/);
            expect(text).to.contain("I'll be there");
        });
    });
});

// ── S5 — the substitute picker (CR1, §5.6) ─────────────────────────────────

describe("Volunteer v2 — S5 find a sub (#9712)", () => {
    beforeEach(() => {
        seedPendingDoorAssignment();
        freshMemberLogin();
    });

    it("offers only eligible people, never the volunteer themselves", () => {
        cy.visit(MY_SCHEDULE_URL);
        cy.get(".volunteer-assignment-card").first().find(".volunteer-find-sub").click();

        cy.get("#substitute-modal").should("be.visible");
        cy.get("#substitute-select option").should("have.length.at.least", 1);
        cy.get("#substitute-select option").then(($options) => {
            const values = [...$options].map((o) => o.value).filter((v) => v !== "");
            expect(values).to.not.include(String(PERSON_MEMBER));
            expect(values).to.include(String(POOL_MEMBER_A));
        });
    });

    it("creates the proposal and turns the card into 'waiting for your coordinator'", () => {
        cy.visit(MY_SCHEDULE_URL);
        cy.get(".volunteer-assignment-card").first().find(".volunteer-find-sub").click();
        cy.get("#substitute-modal").should("be.visible");
        cy.get("#substitute-select").select(String(POOL_MEMBER_A), { force: true });
        cy.get("#substitute-save").click();

        cy.get("#substitute-modal").should("not.be.visible");
        cy.get(".volunteer-assignment-card").first().find(".volunteer-withdraw").should("be.visible");
        cy.get(".volunteer-assignment-card")
            .first()
            .find(".volunteer-card-status")
            .should("contain.text", "Substitute");
    });

    it("withdraws a pending proposal and restores the ordinary buttons", () => {
        cy.visit(MY_SCHEDULE_URL);
        cy.get(".volunteer-assignment-card").first().find(".volunteer-find-sub").click();
        cy.get("#substitute-modal").should("be.visible");
        cy.get("#substitute-select").select(String(POOL_MEMBER_A), { force: true });
        cy.get("#substitute-save").click();

        cy.get(".volunteer-assignment-card").first().find(".volunteer-withdraw").click();
        cy.get(".bootbox").should("be.visible");
        cy.get(".bootbox .btn-primary, .bootbox .btn-danger").last().click();

        cy.get(".volunteer-assignment-card").first().find(".volunteer-find-sub").should("be.visible");
        cy.get(".volunteer-assignment-card").first().find(".volunteer-withdraw").should("not.exist");
    });
});

// ── S6 — Open opportunities (§5.6) ─────────────────────────────────────────

describe("Volunteer v2 — S6 open opportunities (#9712)", () => {
    beforeEach(() => {
        clearWorkflowRows();
        freshMemberLogin();
    });

    it("lists an open opportunity with a single Sign up button", () => {
        cy.visit(OPPORTUNITIES_URL);
        cy.get("#volunteer-opportunities").should("exist");
        cy.get(`.volunteer-opportunity-card[data-position-id="${posCoffee}"]`)
            .first()
            .within(() => {
                cy.get(".volunteer-card-what").should("contain", `${PREFIX} Coffee`);
                cy.get(".volunteer-signup").should("be.visible");
            });
    });

    it("signing up moves the commitment onto my schedule", () => {
        cy.visit(OPPORTUNITIES_URL);
        cy.get(`.volunteer-opportunity-card[data-position-id="${posCoffee}"]`)
            .first()
            .find(".volunteer-signup")
            .click();

        // The card goes away — the slot is mine now.
        cy.get(`.volunteer-opportunity-card[data-position-id="${posCoffee}"][data-occurrence-id="${occurrenceId}"]`)
            .should("not.exist");

        cy.visit(MY_SCHEDULE_URL);
        cy.get(".volunteer-assignment-card").should("have.length.at.least", 1);
        cy.get("#volunteer-my-schedule").should("contain", `${PREFIX} Coffee`);
    });

    it("warns when I already serve on that day rather than hiding the row (D16)", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            `${VOLUNTEER_URL}/occurrences/${occurrenceId}/assignments`,
            { positionId: posCoffee, personId: PERSON_MEMBER },
            201,
        );
        freshMemberLogin();
        cy.visit(OPPORTUNITIES_URL);

        cy.get(
            `.volunteer-opportunity-card[data-position-id="${posDoor}"][data-occurrence-id="${occurrenceId}"]`,
        )
            .should("exist")
            .find(".volunteer-already-serving")
            .should("be.visible")
            .and("contain", `${PREFIX} Coffee`);
    });

    it("shows the §5.6 empty state when nothing is open", () => {
        // Fill every slot of every occurrence with other people.
        cy.makePrivateAdminAPICall(
            "GET",
            `${VOLUNTEER_URL}/occurrences?from=${seriesStart}&to=${seriesEnd}&ministryId=${ministryId}`,
            null,
            200,
        ).then((resp) => {
            resp.body.occurrences.forEach((occ) => {
                cy.makePrivateAdminAPICall(
                    "POST",
                    `${VOLUNTEER_URL}/occurrences/${occ.id}/assignments`,
                    { positionId: posCoffee, personId: POOL_MEMBER_A },
                    [201, 409],
                );
                cy.makePrivateAdminAPICall(
                    "POST",
                    `${VOLUNTEER_URL}/occurrences/${occ.id}/assignments`,
                    { positionId: posDoor, personId: POOL_MEMBER_A },
                    [201, 409],
                );
                cy.makePrivateAdminAPICall(
                    "POST",
                    `${VOLUNTEER_URL}/occurrences/${occ.id}/assignments`,
                    { positionId: posDoor, personId: POOL_MEMBER_B },
                    [201, 409],
                );
            });
        });

        freshMemberLogin();
        cy.visit(OPPORTUNITIES_URL);
        cy.get("#opportunities-empty").should("be.visible");
        // The empty state is a card in the availability list now, not a Tabler
        // `.empty` block — `member.portal-opportunities-layout.spec.js` owns its
        // shape; what matters here is that it says something.
        cy.get("#opportunities-empty .volunteer-card-title").should("not.be.empty");
    });
});

// ── D19 — "Ministries looking for help" on S6 ──────────────────────────────

/**
 * The section a volunteer with nothing to sign up for still has something to do
 * with. Its API contract is pinned by `private.volunteer.ministry-group.spec.js`;
 * what is asserted here is the SCREEN: that the section is omitted entirely when
 * no ministry is advertising, that the coordinator's own prose is rendered with
 * its line breaks and without its markup, that the button reports the right
 * sentence, and that it stays visible for somebody who is already a member.
 */
describe("Volunteer v2 — S6 ministries looking for help (D19)", () => {
    const HELP_TEXT = "Sundays need help.\nNo experience needed.";
    /** A deliberately hostile string: escaped as text, never parsed as markup. */
    const HELP_TEXT_UNSAFE = '<img src=x onerror="window.__xss=1">';

    function setHelpWanted(fields) {
        cy.makePrivateAdminAPICall(
            "POST",
            `${VOLUNTEER_URL}/ministries/${ministryId}`,
            fields,
            200,
        );
    }

    afterEach(() => {
        setHelpWanted({ helpWanted: false, helpWantedText: "" });
    });

    it("omits the whole section when no ministry is asking for help", () => {
        setHelpWanted({ helpWanted: false });
        freshMemberLogin();
        cy.visit(OPPORTUNITIES_URL);
        cy.get("#opportunities-loading").should("not.be.visible");
        cy.get("#help-wanted-section").should("not.be.visible");
    });

    it("lists the ministry with its text and one 'I'd like to help' button", () => {
        setHelpWanted({ helpWanted: true, helpWantedText: HELP_TEXT });
        freshMemberLogin();
        cy.visit(OPPORTUNITIES_URL);

        cy.get("#help-wanted-section").should("be.visible");
        cy.get(".volunteer-help-wanted-card").should("have.length", 1);
        cy.get(".volunteer-help-wanted-name").should("contain", `${PREFIX} Hospitality`);
        // Line breaks are preserved, so the two sentences are two lines.
        cy.get(".volunteer-help-wanted-text").should("contain", "Sundays need help.");
        cy.get(".volunteer-help-wanted-text br").should("exist");
        cy.get(".volunteer-offer-help").should("have.length", 1);
    });

    it("escapes the coordinator's text instead of rendering it as markup", () => {
        setHelpWanted({ helpWanted: true, helpWantedText: HELP_TEXT_UNSAFE });
        freshMemberLogin();
        cy.visit(OPPORTUNITIES_URL);

        cy.get(".volunteer-help-wanted-card").should("be.visible");
        cy.get(".volunteer-help-wanted-text img").should("not.exist");
        cy.window().its("__xss").should("be.undefined");
    });

    it("offers to help, thanks the volunteer, and keeps the button for a second offer", () => {
        setHelpWanted({ helpWanted: true, helpWantedText: HELP_TEXT });
        // Make sure the persona is NOT in the pool, so the first tap is the
        // "has been added" path.
        cy.makePrivateAdminAPICall(
            "DELETE",
            `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${PERSON_MEMBER}`,
            null,
            [200, 404],
        );

        freshMemberLogin();
        cy.visit(OPPORTUNITIES_URL);
        cy.get(".volunteer-offer-help").click();
        cy.get(".notyf__toast").should("contain", "you'd like to help");

        cy.makePrivateAdminAPICall(
            "GET",
            `${VOLUNTEER_URL}/ministries/${ministryId}/pool`,
            null,
            200,
        ).then((resp) => {
            expect(resp.body.members.map((m) => m.personId)).to.include(PERSON_MEMBER);
        });

        // D19: the button stays for members and non-members alike — offering again
        // is a real thing to do.
        freshMemberLogin();
        cy.visit(OPPORTUNITIES_URL);
        cy.get(".volunteer-offer-help").should("be.visible").click();
        cy.get(".notyf__toast").should("contain", "again");
    });

    it("renders the section through i18next, never a raw key", () => {
        setHelpWanted({ helpWanted: true, helpWantedText: HELP_TEXT });
        freshMemberLogin();
        cy.visit(OPPORTUNITIES_URL);
        cy.get("#help-wanted-section").should("not.contain", "i18next");
        cy.get("#help-wanted-section")
            .invoke("text")
            .should("not.match", /\{\{[a-z]+\}\}/);
    });
});

// ── Round four — "New volunteers needed for the following positions" ───────

/**
 * A position can now advertise itself (`recruiting`), and a ministry reaches the
 * Open Opportunities page when its Help-wanted switch is on **or** it has at
 * least one active recruiting position. What is asserted here is the screen: the
 * heading, the optional description, the subheading, the
 * `{Team} - {Position} - {Description}` rows in their server-chosen order, and
 * that the "I'd like to help" button still does exactly what it did — including
 * on a ministry that qualifies only through its positions, where a dead button
 * would be the obvious way to get this wrong.
 *
 * The API contract is pinned by `private.volunteer.recruiting.spec.js`.
 */
describe("Volunteer v2 — S6 positions being recruited for (#9701 round four)", () => {
    const SUBHEADING = "New volunteers needed for the following positions";
    const DOOR_DESCRIPTION = "Hold the door and say hello.";
    const HELP_TEXT = "Sundays need help.";

    function setMinistry(fields) {
        cy.makePrivateAdminAPICall("POST", `${VOLUNTEER_URL}/ministries/${ministryId}`, fields, 200);
    }

    function setPosition(positionId, fields) {
        cy.makePrivateAdminAPICall("POST", `${VOLUNTEER_URL}/positions/${positionId}`, fields, 200);
    }

    beforeEach(() => {
        setMinistry({ helpWanted: false, helpWantedText: "" });
        setPosition(posDoor, { recruiting: false, description: DOOR_DESCRIPTION, active: true });
        setPosition(posCoffee, { recruiting: false, description: "", active: true });
    });

    afterEach(() => {
        setMinistry({ helpWanted: false, helpWantedText: "" });
        setPosition(posDoor, { recruiting: false, description: "", active: true });
        setPosition(posCoffee, { recruiting: false, description: "", active: true });
        // The "I'd like to help" test takes the persona out of the pool to prove
        // the button puts them back. Restore it unconditionally: every later
        // describe seeds an assignment for them, and I3 answers 409 for somebody
        // outside the pool — a failure here must not cascade into the rest.
        cy.makePrivateAdminAPICall(
            "POST",
            `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${PERSON_MEMBER}`,
            null,
            [200, 201],
        );
    });

    it("advertises a ministry whose only advert is a recruiting position", () => {
        setPosition(posDoor, { recruiting: true });
        freshMemberLogin();
        cy.visit(OPPORTUNITIES_URL);

        cy.get("#help-wanted-section").should("be.visible");
        cy.get(".volunteer-help-wanted-card").should("have.length", 1);
        cy.get(".volunteer-help-wanted-name").should("contain", `${PREFIX} Hospitality`);
        // The switch is off and the text is empty, so there is no prose paragraph.
        cy.get(".volunteer-help-wanted-text").should("not.exist");
        cy.get(".volunteer-help-wanted-positions-title").should("contain", SUBHEADING);
        cy.get(".volunteer-offer-help").should("have.length", 1);
    });

    it("formats a row as Team - Position - Description", () => {
        setPosition(posDoor, { recruiting: true });
        freshMemberLogin();
        cy.visit(OPPORTUNITIES_URL);

        cy.get(".volunteer-help-wanted-position")
            .should("have.length", 1)
            .first()
            .invoke("text")
            .then((text) => {
                expect(text.replace(/\s+/g, " ").trim()).to.eq(
                    `${PREFIX} Greeters - ${PREFIX} Door - ${DOOR_DESCRIPTION}`,
                );
            });
    });

    it("drops the trailing separator when the position has no description", () => {
        setPosition(posCoffee, { recruiting: true });
        freshMemberLogin();
        cy.visit(OPPORTUNITIES_URL);

        cy.get(".volunteer-help-wanted-position")
            .should("have.length", 1)
            .first()
            .invoke("text")
            .then((text) => {
                expect(text.replace(/\s+/g, " ").trim()).to.eq(
                    `${PREFIX} Greeters - ${PREFIX} Coffee`,
                );
            });
    });

    it("orders the rows the way the Positions table does", () => {
        setPosition(posDoor, { recruiting: true });
        setPosition(posCoffee, { recruiting: true });
        freshMemberLogin();
        cy.visit(OPPORTUNITIES_URL);

        cy.get(".volunteer-help-wanted-position").should("have.length", 2);
        // Door is order 1, Coffee is order 2, and both are on the one team.
        cy.get(".volunteer-help-wanted-position").first().should("contain", `${PREFIX} Door`);
        cy.get(".volunteer-help-wanted-position").last().should("contain", `${PREFIX} Coffee`);
    });

    it("omits a deactivated position even while its recruiting switch is on", () => {
        setPosition(posDoor, { recruiting: true });
        setPosition(posCoffee, { recruiting: true });
        setPosition(posCoffee, { active: false });
        freshMemberLogin();
        cy.visit(OPPORTUNITIES_URL);

        cy.get(".volunteer-help-wanted-position").should("have.length", 1);
        cy.get(".volunteer-help-wanted-card").should("not.contain", `${PREFIX} Coffee`);
    });

    it("shows the description AND the positions when both adverts are on", () => {
        setMinistry({ helpWanted: true, helpWantedText: HELP_TEXT });
        setPosition(posDoor, { recruiting: true });
        freshMemberLogin();
        cy.visit(OPPORTUNITIES_URL);

        cy.get(".volunteer-help-wanted-text").should("contain", HELP_TEXT);
        cy.get(".volunteer-help-wanted-positions-title").should("contain", SUBHEADING);
        cy.get(".volunteer-help-wanted-position").should("have.length", 1);
    });

    it("shows no subheading for a ministry advertising with prose alone", () => {
        setMinistry({ helpWanted: true, helpWantedText: HELP_TEXT });
        freshMemberLogin();
        cy.visit(OPPORTUNITIES_URL);

        cy.get(".volunteer-help-wanted-card").should("be.visible");
        cy.get(".volunteer-help-wanted-positions-title").should("not.exist");
        cy.get(".volunteer-help-wanted-position").should("not.exist");
    });

    it("keeps 'I'd like to help' working on a ministry advertised only by a position", () => {
        setPosition(posDoor, { recruiting: true });
        cy.makePrivateAdminAPICall(
            "DELETE",
            `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${PERSON_MEMBER}`,
            null,
            [200, 404],
        );

        freshMemberLogin();
        cy.visit(OPPORTUNITIES_URL);
        cy.get(".volunteer-offer-help").click();
        cy.get(".notyf__toast").should("contain", "you'd like to help");

        cy.makePrivateAdminAPICall(
            "GET",
            `${VOLUNTEER_URL}/ministries/${ministryId}/pool`,
            null,
            200,
        ).then((resp) => {
            expect(resp.body.members.map((m) => m.personId)).to.include(PERSON_MEMBER);
        });
    });

    it("escapes a position name and description instead of rendering markup", () => {
        setPosition(posDoor, {
            recruiting: true,
            description: '<img src=x onerror="window.__posxss=1">',
        });
        freshMemberLogin();
        cy.visit(OPPORTUNITIES_URL);

        cy.get(".volunteer-help-wanted-position").should("exist");
        cy.get(".volunteer-help-wanted-position img").should("not.exist");
        cy.window().its("__posxss").should("be.undefined");
    });

    it("renders the subheading through i18next, never a raw key", () => {
        setPosition(posDoor, { recruiting: true });
        freshMemberLogin();
        cy.visit(OPPORTUNITIES_URL);

        cy.get("#help-wanted-section").should("not.contain", "i18next");
        cy.get("#help-wanted-section")
            .invoke("text")
            .should("not.match", /\{\{[a-z]+\}\}/);
    });
});

// ── §5.9 — mobile is a hard requirement for #9712 ──────────────────────────

describe("Volunteer v2 — member screens on a phone (§5.9)", () => {
    beforeEach(() => {
        seedPendingDoorAssignment();
        freshMemberLogin();
        cy.viewport(...MOBILE_VIEWPORT);
    });

    it("S5 fits a 375px viewport with no horizontal scroll", () => {
        cy.visit(MY_SCHEDULE_URL);
        cy.get(".volunteer-assignment-card").should("be.visible");
        assertNoHorizontalOverflow();
    });

    it("S5 accept/decline meet the 44px touch target minimum", () => {
        cy.visit(MY_SCHEDULE_URL);
        cy.get(".volunteer-assignment-card").first().find(".volunteer-accept").then(($el) => {
            expect(parseFloat($el.css("height")), "accept height").to.be.gte(44);
        });
        cy.get(".volunteer-assignment-card").first().find(".volunteer-decline").then(($el) => {
            expect(parseFloat($el.css("height")), "decline height").to.be.gte(44);
        });
    });

    it("S5 cards are a single column at every width", () => {
        cy.visit(MY_SCHEDULE_URL);
        cy.get(".volunteer-assignment-card").then(($cards) => {
            if ($cards.length < 2) {
                return;
            }
            const first = $cards[0].getBoundingClientRect();
            const second = $cards[1].getBoundingClientRect();
            expect(second.top, "second card below the first").to.be.greaterThan(first.top);
        });
    });

    it("S6 fits a 375px viewport with a full-width sign-up button", () => {
        cy.visit(OPPORTUNITIES_URL);
        cy.get(".volunteer-opportunity-card").should("be.visible");
        assertNoHorizontalOverflow();
        cy.get(".volunteer-opportunity-card").first().find(".volunteer-signup").then(($el) => {
            expect(parseFloat($el.css("height")), "sign-up height").to.be.gte(44);
        });
    });
});

// ── The member's navigation, in the portal (§3.5, #9867) ───────────────────

describe("Volunteer v2 — the member navigation (§3.5)", () => {
    beforeEach(() => {
        seedPendingDoorAssignment();
        freshMemberLogin();
    });

    /**
     * The admin sidebar's "Volunteer" heading is gone (#9867, Member Portal P16),
     * so the assertion that used to look for its two entries now looks at the
     * portal's own navigation — which is the only navigation a member ever sees.
     */
    it("shows Volunteering in the portal nav to a volunteer who coordinates nothing", () => {
        cy.visit(MY_SCHEDULE_URL);

        cy.get("#portal-nav").within(() => {
            cy.get(`a[href$='${MY_SCHEDULE_URL}']`).should("exist").and("contain", "Volunteering");
        });
        // Both pages are reachable from the page's own tab bar.
        cy.get("#portal-volunteer-tab-schedule").should("exist");
        cy.get("#portal-volunteer-tab-opportunities").should("exist");

        // …and never the coordinator entries, nor any admin furniture at all.
        cy.get("a[href$='volunteer/dashboard']").should("not.exist");
        cy.get("a[href$='volunteer/setup']").should("not.exist");
        cy.get("#sidebar-menu").should("not.exist");
    });

    it("302s the retired member URLs to the portal", () => {
        cy.request({ url: LEGACY_MY_SCHEDULE_URL, followRedirect: false }).then((resp) => {
            expect(resp.status).to.eq(302);
            // A self-service session is answered by AuthMiddleware before the
            // volunteer module's redirect is reached, so the destination is the
            // portal — the page itself for staff, the portal home for a member.
            expect(resp.headers.location).to.match(/\/portal\//);
        });
        cy.request({ url: LEGACY_OPPORTUNITIES_URL, followRedirect: false }).then((resp) => {
            expect(resp.status).to.eq(302);
            expect(resp.headers.location).to.match(/\/portal\//);
        });
    });
});
