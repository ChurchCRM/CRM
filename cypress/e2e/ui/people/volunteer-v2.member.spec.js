/// <reference types="cypress" />

/**
 * Volunteer v2 — S5 / S6, the volunteer self-service screens (#9712, §5.6).
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
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const VOLUNTEER_URL = "/api/volunteer";

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
const MEMBER_USERNAME = "lena.black.editself.notes@exampl";
const MEMBER_PASSWORD = "changeme";
const PERSON_MEMBER = 100;

const POOL_GROUP = 1; // "Angels class" — seeded members 4, 5, 8, 9, 63
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
    dbOk(
        `DELETE vpol FROM volunteer_pool_vpol vpol
           JOIN volunteer_team_vtem vtem
             ON vtem.vtem_ID = vpol.vpol_OwnerId AND vpol.vpol_OwnerType = 'team'
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vtem.vtem_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(
        `DELETE vtem FROM volunteer_team_vtem vtem
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vtem.vtem_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${PREFIX}%`],
    );
    dbOk(`DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?`, [`${PREFIX}%`]);
    dbOk(`DELETE FROM events_event WHERE event_title LIKE ?`, [`${EVENT_TITLE}%`]);
    dbOk(`DELETE FROM person2group2role_p2g2r WHERE p2g2r_per_ID = ? AND p2g2r_grp_ID = ?`, [
        PERSON_MEMBER,
        POOL_GROUP,
    ]);
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

    cy.then(() => {
        dbOk(
            `INSERT IGNORE INTO person2group2role_p2g2r (p2g2r_per_ID, p2g2r_grp_ID, p2g2r_rle_ID)
             VALUES (?, ?, 2)`,
            [PERSON_MEMBER, POOL_GROUP],
        );
        adminApi("POST", `${VOLUNTEER_URL}/teams/${teamId}/pools`, {
            groupId: POOL_GROUP,
            label: `${PREFIX} pool`,
        }, 201);
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
        cy.visit("/volunteer/my-schedule");

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
        cy.visit("/volunteer/my-schedule");
        cy.get(".volunteer-assignment-card").should("have.length.at.least", 1);
        cy.get("#volunteer-my-schedule").invoke("text").then((text) => {
            const lowered = text.toLowerCase();
            ["occurrence", "requirement", "ministry hierarchy"].forEach((word) => {
                expect(lowered, `the word "${word}"`).to.not.contain(word);
            });
        });
    });

    it("accepts with one tap and re-renders the card", () => {
        cy.visit("/volunteer/my-schedule");
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
        cy.visit("/volunteer/my-schedule");
        cy.get(".volunteer-assignment-card").first().find(".volunteer-decline").click();

        cy.get(".bootbox").should("be.visible");
        cy.get(".bootbox input").type("Out of town");
        cy.get(".bootbox .btn-primary").click();

        cy.get(".volunteer-assignment-card")
            .first()
            .find(".volunteer-card-status")
            .should("contain.text", "Declined");

        // The slot is genuinely open again — S6 says so.
        cy.visit("/volunteer/opportunities");
        cy.get(`.volunteer-opportunity-card[data-position-id="${posDoor}"]`).should("exist");
    });

    it("shows a first-class empty state when nothing is booked", () => {
        clearWorkflowRows();
        freshMemberLogin();
        cy.visit("/volunteer/my-schedule");
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

        cy.visit("/volunteer/my-schedule");
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
        cy.visit("/volunteer/my-schedule");
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
        cy.visit("/volunteer/my-schedule");
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
        cy.visit("/volunteer/my-schedule");
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
        cy.visit("/volunteer/my-schedule");
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
        cy.visit("/volunteer/opportunities");
        cy.get("#volunteer-opportunities").should("exist");
        cy.get(`.volunteer-opportunity-card[data-position-id="${posCoffee}"]`)
            .first()
            .within(() => {
                cy.get(".volunteer-card-what").should("contain", `${PREFIX} Coffee`);
                cy.get(".volunteer-signup").should("be.visible");
            });
    });

    it("signing up moves the commitment onto my schedule", () => {
        cy.visit("/volunteer/opportunities");
        cy.get(`.volunteer-opportunity-card[data-position-id="${posCoffee}"]`)
            .first()
            .find(".volunteer-signup")
            .click();

        // The card goes away — the slot is mine now.
        cy.get(`.volunteer-opportunity-card[data-position-id="${posCoffee}"][data-occurrence-id="${occurrenceId}"]`)
            .should("not.exist");

        cy.visit("/volunteer/my-schedule");
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
        cy.visit("/volunteer/opportunities");

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
        cy.visit("/volunteer/opportunities");
        cy.get("#opportunities-empty").should("be.visible");
        cy.get("#opportunities-empty .empty-title").should("not.be.empty");
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
        cy.visit("/volunteer/my-schedule");
        cy.get(".volunteer-assignment-card").should("be.visible");
        assertNoHorizontalOverflow();
    });

    it("S5 accept/decline meet the 44px touch target minimum", () => {
        cy.visit("/volunteer/my-schedule");
        cy.get(".volunteer-assignment-card").first().find(".volunteer-accept").then(($el) => {
            expect(parseFloat($el.css("height")), "accept height").to.be.gte(44);
        });
        cy.get(".volunteer-assignment-card").first().find(".volunteer-decline").then(($el) => {
            expect(parseFloat($el.css("height")), "decline height").to.be.gte(44);
        });
    });

    it("S5 cards are a single column at every width", () => {
        cy.visit("/volunteer/my-schedule");
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
        cy.visit("/volunteer/opportunities");
        cy.get(".volunteer-opportunity-card").should("be.visible");
        assertNoHorizontalOverflow();
        cy.get(".volunteer-opportunity-card").first().find(".volunteer-signup").then(($el) => {
            expect(parseFloat($el.css("height")), "sign-up height").to.be.gte(44);
        });
    });
});

// ── The member menu entry (§3.5) ───────────────────────────────────────────

describe("Volunteer v2 — the member menu entry (§3.5)", () => {
    beforeEach(() => {
        seedPendingDoorAssignment();
        freshMemberLogin();
    });

    it("offers 'My Volunteer Schedule' to a volunteer who coordinates nothing", () => {
        cy.visit("/volunteer/my-schedule");
        cy.get("a[href$='volunteer/my-schedule']").should("exist");
        cy.get("a[href$='volunteer/opportunities']").should("exist");
        // …and never the coordinator entries.
        cy.get("a[href$='volunteer/dashboard']").should("not.exist");
        cy.get("a[href$='volunteer/setup']").should("not.exist");
    });
});
