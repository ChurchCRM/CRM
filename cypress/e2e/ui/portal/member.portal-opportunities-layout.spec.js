/// <reference types="cypress" />

/**
 * Member Portal — the layout of "Find something to do".
 *
 * `member.portal-volunteer.spec.js` (MP6, #9867) and `volunteer-v2.member.spec.js`
 * (#9712) own what this page DOES. This spec owns what it LOOKS LIKE, after the
 * product owner's restructure:
 *
 *  - the standing info box at the top ("These are the places you are trained for
 *    that still need someone", with its "Back to my schedule" button) is gone;
 *  - "Ministries looking for help" keeps its heading, its secondary sentence and
 *    its section break, exactly as it was;
 *  - below that break, ALWAYS, a second heading pair in the same shape:
 *    "Upcoming availability" over "These scheduled positions still need someone.";
 *  - the open positions are cards of the SAME shape as a help-wanted ministry
 *    card — one markup builder renders both — and so is the "nothing open" state,
 *    which is now a card in that list rather than a Tabler `.empty` block, with
 *    the "Back to my schedule" button inside it.
 *
 * Persona and fixture mechanics follow `member.portal-volunteer.spec.js`: person
 * 100, Lena Black (`usr_EditSelf=1`, no admin flag), whose seeded username is
 * longer than `user_usr.usr_UserName`'s VARCHAR(32) and so must be typed
 * truncated. Order inside every hook is API setup → login → `cy.visit()`, because
 * `cy.request()` rotates the PHP session cookie (cypress-testing.md).
 *
 * The two states are separate describes on purpose, in file order: the fixture
 * ministry starts with NO schedule at all, so the first describe sees the empty
 * state, and the second describe's own `before` is what generates the occurrence
 * that gives the member something to sign up for.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const VOLUNTEER_URL = "/api/volunteer";

const OPPORTUNITIES_URL = "/portal/volunteer/opportunities";
const SCHEDULE_URL = "/portal/volunteer/schedule";

const MEMBER_USERNAME = "lena.black.editself.notes@exampl";
const MEMBER_PASSWORD = "changeme";
const PERSON_MEMBER = 100;

const CHURCH_SERVICE_TYPE = 1;
const PREFIX = "MPOPPLAYOUT";
const EVENT_TITLE = `${PREFIX} Portal Service`;
const HELP_WANTED_TEXT = `${PREFIX} we would love more hands on a Sunday`;

/** The sentence the restructure deleted. It must not come back. */
const RETIRED_SENTENCE = "These are the places you are trained for";

let ministryId = 0;
let teamId = 0;
let positionId = 0;
let scheduleId = 0;
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

function cleanupFixtures() {
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
    dbOk(`DELETE FROM volunteer_notification_vntf WHERE vntf_Type = 'help_offer'`);
    dbOk(`DELETE vasg FROM volunteer_assignment_vasg vasg ${scoped}`, [`${PREFIX}%`]);
    dbOk(
        `DELETE vreq FROM volunteer_requirement_vreq vreq
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vreq.vreq_vsch_ID
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
    dbOk(`DELETE FROM calendars WHERE name LIKE ?`, [`${PREFIX}%`]);
    dbOk(`DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?`, [`${PREFIX}%`]);
    dbOk(`DELETE FROM events_event WHERE event_title LIKE ?`, [`${EVENT_TITLE}%`]);
}

// ── fixture: an advertising ministry, one position, and NO schedule yet ─────

before(() => {
    adminApi("POST", SETTING_URL, { value: "v2" }, 200);
    cleanupFixtures();

    adminApi("POST", `${VOLUNTEER_URL}/ministries`, {
        name: `${PREFIX} Hospitality`,
        description: "opportunities layout fixture",
    }, 201).then((resp) => {
        ministryId = resp.body.ministry.id;
    });

    cy.then(() => {
        adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}`, {
            helpWanted: true,
            helpWantedText: HELP_WANTED_TEXT,
        }, 200);
    });

    cy.then(() => {
        adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}/teams`, {
            name: `${PREFIX} Greeters`,
            description: "opportunities layout fixture",
        }, 201).then((resp) => {
            teamId = resp.body.team.id;
        });
    });

    cy.then(() => {
        adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}/positions`, {
            name: `${PREFIX} Coffee`,
            teamId,
            order: 1,
        }, 201).then((resp) => {
            positionId = resp.body.position.id;
        });
    });

    cy.then(() => {
        adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${PERSON_MEMBER}`, null, [
            200, 201,
        ]);
    });

    cy.then(() => {
        adminApi("POST", `${VOLUNTEER_URL}/positions/${positionId}/qualifications`, {
            personId: PERSON_MEMBER,
            notes: "",
        }, 201);
    });
});

after(() => {
    cleanupFixtures();
    adminApi("POST", SETTING_URL, { value: "v1" }, 200);
});

// ── the page's own furniture, and the empty state ──────────────────────────

describe("Member Portal — 'Find something to do' layout", () => {
    beforeEach(() => {
        freshMemberLogin();
        cy.visit(OPPORTUNITIES_URL);
        // The help-wanted section arrives from its own request; waiting for it
        // means the page has finished rendering both lists.
        cy.get("#help-wanted-section", { timeout: 20000 }).should("be.visible");
    });

    it("no longer shows the standing info box above the lists", () => {
        cy.get("#volunteer-opportunities").should("not.contain", RETIRED_SENTENCE);
    });

    it("heads the position list with 'Upcoming availability', formatted like the ministries heading", () => {
        cy.get("#help-wanted-section h3")
            .should("contain", "Ministries looking for help")
            .invoke("attr", "class")
            .then((ministriesHeadingClass) => {
                cy.contains("#volunteer-opportunities h3", "Upcoming availability")
                    .should("be.visible")
                    .and("have.attr", "class", ministriesHeadingClass);
            });

        cy.get("#help-wanted-section > p")
            .invoke("attr", "class")
            .then((ministriesTextClass) => {
                cy.contains("#volunteer-opportunities > p", "These scheduled positions still need someone.")
                    .should("be.visible")
                    .and("have.attr", "class", ministriesTextClass);
            });
    });

    it("shows the heading pair even though nothing is open", () => {
        cy.get("#opportunities-empty").should("be.visible");
        cy.contains("#volunteer-opportunities h3", "Upcoming availability").should("be.visible");
    });

    it("puts the 'nothing open' message and its button inside one card", () => {
        cy.get("#opportunities-empty").should("be.visible");
        cy.get("#opportunities-empty .card")
            .should("have.length", 1)
            .and("contain", "Nothing open right now")
            .and("contain", "We will email you when something needs filling.");

        cy.get("#opportunities-empty .card")
            .find(`a[href$="${SCHEDULE_URL}"]`)
            .should("be.visible")
            .and("contain", "Back to my schedule");
    });

    it("leaves exactly one 'Back to my schedule' button on the page", () => {
        // Two of them before the restructure: one in the deleted info box, one in
        // the empty state. Only the empty state's survives.
        cy.get("#volunteer-opportunities a")
            .filter(':contains("Back to my schedule")')
            .should("have.length", 1);
        cy.get("#opportunities-empty")
            .find(`a[href$="${SCHEDULE_URL}"]`)
            .should("contain", "Back to my schedule");
    });

    it("gives the 'nothing open' card the same shape as a help-wanted card", () => {
        cy.get(".volunteer-help-wanted-card").should("have.length.at.least", 1);

        ["card", "mb-3"].forEach((cls) => {
            cy.get("#opportunities-empty > .card").should("have.class", cls);
        });
        [".card-body", ".volunteer-card-title", ".volunteer-card-actions"].forEach((selector) => {
            cy.get("#opportunities-empty > .card").find(selector).should("exist");
        });
    });
});

// ── the same page once a position is actually open ─────────────────────────

describe("Member Portal — an open position on 'Find something to do'", () => {
    before(() => {
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
                positionId,
                minCount: 1,
                maxCount: 1,
            }, [200, 201]);
        });

        cy.then(() => {
            adminApi("POST", `${VOLUNTEER_URL}/schedules/${scheduleId}/generate`, {
                through: seriesEnd,
            }, 200);
        });
    });

    beforeEach(() => {
        freshMemberLogin();
        cy.visit(OPPORTUNITIES_URL);
        cy.get(".volunteer-opportunity-card", { timeout: 20000 }).should(
            "have.length.at.least",
            1,
        );
    });

    it("renders the position card in the same shape as a help-wanted card", () => {
        cy.get("#help-wanted-section", { timeout: 20000 }).should("be.visible");
        cy.get(".volunteer-help-wanted-card").should("have.length.at.least", 1);

        ["card", "mb-3"].forEach((cls) => {
            cy.get(".volunteer-help-wanted-card").first().should("have.class", cls);
            cy.get(".volunteer-opportunity-card").first().should("have.class", cls);
        });

        [".card-body", ".volunteer-card-title", ".volunteer-card-actions"].forEach((selector) => {
            cy.get(".volunteer-help-wanted-card").first().find(selector).should("exist");
            cy.get(".volunteer-opportunity-card").first().find(selector).should("exist");
        });
    });

    it("keeps the position card's own button and data attributes", () => {
        cy.get(".volunteer-opportunity-card")
            .first()
            .should("have.attr", "data-position-id")
            .and("not.be.empty");
        cy.get(".volunteer-opportunity-card")
            .first()
            .should("have.attr", "data-occurrence-id")
            .and("not.be.empty");
        cy.get(".volunteer-opportunity-card")
            .first()
            .find(".volunteer-signup")
            .should("be.visible")
            .and("contain", "Sign up");
    });

    it("still shows the 'Upcoming availability' heading above the cards, and no info box", () => {
        cy.contains("#volunteer-opportunities h3", "Upcoming availability").should("be.visible");
        cy.get("#volunteer-opportunities").should("not.contain", RETIRED_SENTENCE);
        cy.get("#opportunities-empty").should("not.be.visible");
    });
});
