/// <reference types="cypress" />

/**
 * Volunteer v2 — the notification outbox and its drain (#9710, epic #9701).
 *
 * Normative sections of `.agents/skills/churchcrm/volunteer-v2-design.md`:
 * §2.14 (the outbox columns, the dedupe keys, the amended retry rule and what
 * `skipped` means), §3.4 (`VolunteerNotificationService`), §3.6 (the trigger
 * table, Reply-To resolution and the four-step drain), Appendix B
 * (`iVolunteerReminderLeadHours`), Appendix C (the seven `BaseEmail`
 * subclasses) and §6.6 ("duplicate notification enqueue", which is what
 * `GET /api/volunteer/assignments/{id}/notifications` exists for).
 *
 * #9709 built the ENQUEUE half: the real workflow already writes outbox rows at
 * every §3.6 trigger. This spec proves the other half — that those rows are
 * turned into mail, once, with the right content, and that a delivery failure
 * changes nothing about the assignment.
 *
 * What #9710 promises, and where each promise is proven:
 *
 *   "existing email infrastructure is reused"        → the delivery block: a real
 *        BaseEmail subclass through the real PHPMailer reaches Mailpit
 *   "duplicate notifications avoided on retry"       → the idempotency block
 *   "failed delivery does not corrupt assignment state" → the retry block: the
 *        assignment is re-read after five failures and is untouched
 *   "coordinator gap notifications are scoped"       → the trigger block
 *   "notification content is localized"              → every string is gettext();
 *        the body assertions read the rendered message, not a hard-coded one
 *
 * **The drain is driven through the real `POST /api/background/timerjobs`**, not
 * through a test-only endpoint — §7.2 says a coordinator drain endpoint is not
 * in the design, and driving the supported path is the only way to prove the
 * timer-job hook exists. #9724 rate limits that endpoint, so `drain()` clears
 * `sLastTimerJobsRunDateTime` first (the same bypass
 * `private.system.timerjobs.spec.js` uses) and asserts `ran: true`, so a
 * silently skipped run can never be mistaken for a drain that found nothing.
 *
 * **Mailpit is optional.** The `test` docker profile runs it; the `ci-root` and
 * `ci-subdir` profiles bring up no mail server at all. The delivery assertions
 * therefore skip themselves when `mail:available` says no — but every
 * outbox-state assertion (skipped / pending / failed / attempts) runs
 * everywhere, because those need no mail server to be meaningful.
 *
 * Cleanup runs in `before` as well as `after` (cypress-testing.md): an `after`
 * hook does not run when the runner crashes mid-spec.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const EMAIL_ENABLED_URL = "/admin/api/system/config/bEnabledEmail";
const SMTP_HOST_URL = "/admin/api/system/config/sSMTPHost";
const LEAD_HOURS_URL = "/admin/api/system/config/iVolunteerReminderLeadHours";
const DO_NOT_EMAIL_URL = "/admin/api/system/config/iDoNotEmailPropertyId";
/**
 * Read, never assumed (#9714). Every `BaseEmail` subject is prefixed with
 * `sChurchName`, and `private.admin.system.config.spec.js` sets that config to
 * "Example Church Name" to prove trimming and never restores it — so asserting
 * the seeded "Main St. Cathedral" passes in isolation and fails in a full-suite
 * run. The subject shape is what this spec is about; the church's name is not.
 */
const CHURCH_NAME_URL = "/admin/api/system/config/sChurchName";
const TIMERJOBS_LAST_RUN_URL =
    "/admin/api/system/config/sLastTimerJobsRunDateTime";

const ADMIN_KEY = "admin.api.key";
const COORDINATOR_KEY = "user.api.key";
const PLAINAUTH_KEY = "plainauth.api.key";
const SELFEDIT_KEY = "selfedit.api.key";

const VOLUNTEER_URL = "/api/volunteer";

const PERSON_COORDINATOR = 3; // tony.wade@example.com — coordinates ministry A
const PERSON_PLAIN = 900; // john.plainauth — no volunteer rights at all
const PERSON_VOLUNTEER = 99; // EditSelf-exclusive — THE volunteer persona (D14)

/** Seeded members of group 1 "Angels class". */
const POOL_GROUP = 1;
const POOL_MEMBER_A = 8; // herminia.bennett@example.com
const POOL_MEMBER_B = 9; // jean.williams@example.com
const POOL_MEMBER_C = 63; // julie.gregory@example.com — the "no email" case

const COORDINATOR_EMAIL = "tony.wade@example.com";
const MEMBER_A_EMAIL = "herminia.bennett@example.com";

/** A person property to borrow as the do-not-email marker (seed.sql, class 'p'). */
const DO_NOT_EMAIL_PROPERTY = 1;

const CHURCH_SERVICE_TYPE = 1; // seed.sql — weekly, Sunday, 10:30

const FIXTURE_PREFIX = "NTF9710";
const EVENT_TITLE = `${FIXTURE_PREFIX} Coffee Bar Service`;
const MINISTRY_NAME = `${FIXTURE_PREFIX} Coffee Bar`;
const ESPRESSO_NAME = `${FIXTURE_PREFIX} Espresso`;

let ministryA = 0;
let ministryB = 0;
let teamA = 0;
let posEspresso = 0;
let posMilk = 0;
let scheduleA = 0;
let occurrenceOne = 0;
let occurrenceTwo = 0;
let occurrenceOneStart = "";
let occurrenceTwoStart = "";
// `null` until the `before` hook has actually read them. A run whose fixture
// dies early must not let `after` "restore" an empty string over a real
// setting — SystemConfig::setValue() DELETES the row when the value equals the
// item's default, so a bogus restore of sSMTPHost silently disables email for
// every spec that runs afterwards.
let originalVersion = null;
let originalSmtpHost = null;
let originalLeadHours = null;
let originalDoNotEmail = null;
let churchName = "";
let seriesStart = "";
let seriesEnd = "";
let mailpitAvailable = false;

// ── helpers ────────────────────────────────────────────────────────────────

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

function api(key, method, url, body, expectedStatus = 200) {
    return cy.makePrivateAPICall(
        Cypress.env(key),
        method,
        url,
        body,
        expectedStatus,
    );
}

function setConfig(url, value) {
    return cy.makePrivateAdminAPICall("POST", url, { value }, 200);
}

function readConfig(url) {
    return cy
        .makePrivateAdminAPICall("GET", url, null, 200)
        .then((resp) => resp.body.value ?? "");
}

/**
 * Run the timer jobs for real.
 *
 * #9724 rate limits `POST /api/background/timerjobs` to one run per
 * `iTimerJobsMinIntervalMinutes`, so the recorded last run is cleared first —
 * the bypass `private.system.timerjobs.spec.js` already uses, and the reason
 * this spec does not touch that interval setting itself (leaving it at its
 * default keeps the rate-limit regression spec honest). `ran` is asserted
 * because a skipped run and a drain that found nothing look identical from the
 * outbox.
 */
function drain() {
    setConfig(TIMERJOBS_LAST_RUN_URL, "");

    return cy
        .makePrivateAdminAPICall("POST", "/api/background/timerjobs", {}, 200)
        .then((resp) => {
            expect(resp.body.ran, "the rate limit must not swallow the drain").to
                .be.true;
        });
}

/** `YYYY-MM-DD`, `offsetDays` from today. */
function isoDate(offsetDays) {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + offsetDays);
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/** Days from today to the next occurrence of `dow` (0 = Sunday). Never 0. */
function daysToNext(dow) {
    const today = new Date().getDay();
    return ((dow - today + 7) % 7) || 7;
}

/**
 * `start` minus `leadHours`, as the naive wall-clock string the outbox stores.
 *
 * Parsed and formatted by hand rather than through `new Date(...)` maths on a
 * space-separated string: the occurrence start is wall-clock in `sTimeZone`
 * (timezone-handling.md), and letting the browser's zone near it is exactly how
 * a one-hour drift gets asserted as correct.
 */
function minusHours(wallClock, leadHours) {
    const [datePart, timePart] = wallClock.split(" ");
    const [y, mo, d] = datePart.split("-").map(Number);
    const [h, mi, s] = timePart.split(":").map(Number);
    const asUtc = Date.UTC(y, mo - 1, d, h, mi, s) - leadHours * 3600 * 1000;
    const out = new Date(asUtc);
    const pad = (n) => String(n).padStart(2, "0");

    return (
        `${out.getUTCFullYear()}-${pad(out.getUTCMonth() + 1)}-${pad(out.getUTCDate())}` +
        ` ${pad(out.getUTCHours())}:${pad(out.getUTCMinutes())}:${pad(out.getUTCSeconds())}`
    );
}

function assign(key, occurrenceId, positionId, personId, extra = {}, status = 201) {
    return api(
        key,
        "POST",
        `${VOLUNTEER_URL}/occurrences/${occurrenceId}/assignments`,
        { positionId, personId, ...extra },
        status,
    ).then((resp) => resp.body.assignment);
}

/** The outbox through the read endpoint this issue adds (§6.6). */
function notifications(assignmentId, key = ADMIN_KEY, status = 200) {
    return api(
        key,
        "GET",
        `${VOLUNTEER_URL}/assignments/${assignmentId}/notifications`,
        null,
        status,
    );
}

/** The outbox row straight from the table — the columns the API does not expose. */
function outboxRow(dedupeKey) {
    return dbOk(
        `SELECT vntf_ID, vntf_Type, vntf_Channel, vntf_per_ID, vntf_Status,
                vntf_Attempts, vntf_LastError, vntf_SentDate, vntf_LastAttemptDate,
                vntf_ScheduledFor, vntf_vasg_ID, vntf_vocc_ID
           FROM volunteer_notification_vntf
          WHERE vntf_DedupeKey = ?`,
        [dedupeKey],
    ).then((rows) => {
        expect(rows, `outbox row ${dedupeKey}`).to.have.length(1);
        return rows[0];
    });
}

function outboxRowsOfType(type) {
    return dbOk(
        `SELECT vntf_ID, vntf_DedupeKey, vntf_Status, vntf_per_ID, vntf_ScheduledFor
           FROM volunteer_notification_vntf vntf
           LEFT JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vntf.vntf_vocc_ID
           LEFT JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           LEFT JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vntf.vntf_Type = ? AND vmin.vmin_Name LIKE ?`,
        [type, `${FIXTURE_PREFIX}%`],
    );
}

/** Force outbox columns the API deliberately cannot write (§2.14's retry counter). */
function forceOutbox(dedupeKey, assignments) {
    const sets = Object.keys(assignments)
        .map((column) => `${column} = ?`)
        .join(", ");

    return dbOk(
        `UPDATE volunteer_notification_vntf SET ${sets} WHERE vntf_DedupeKey = ?`,
        [...Object.values(assignments), dedupeKey],
    );
}

// ── Mailpit ────────────────────────────────────────────────────────────────

function clearMail() {
    return cy.task("mail:clear");
}

/** Every stored message, newest first. `[]` when Mailpit is not running. */
function listMail() {
    return cy.task("mail:list", { limit: 100 }).then((result) => {
        if (!result.ok) {
            return [];
        }
        return result.body.messages || [];
    });
}

/**
 * The one delivered message addressed to `address`, in full (Text/HTML/ReplyTo).
 *
 * `subjectFragment` narrows it when a single drain legitimately sends the same
 * person more than one message — a volunteer's decline both alerts the
 * coordinator AND opens a gap on the occurrence, so that coordinator receives a
 * `decline_alert` and a `gap_alert` from the same run (§3.6). Matching on the
 * address alone would make that correct behaviour look like a duplicate.
 */
function mailTo(address, subjectFragment = null) {
    return listMail().then((messages) => {
        const matches = messages.filter(
            (message) =>
                (message.To || []).some(
                    (to) => (to.Address || "").toLowerCase() === address.toLowerCase(),
                ) &&
                (subjectFragment === null ||
                    (message.Subject || "")
                        .toLowerCase()
                        .includes(subjectFragment.toLowerCase())),
        );
        expect(
            matches,
            `one delivered message addressed to ${address}` +
                (subjectFragment === null ? "" : ` about "${subjectFragment}"`),
        ).to.have.length(1);

        return cy.task("mail:get", { id: matches[0].ID }).then((result) => {
            expect(result.ok, `reading message ${matches[0].ID}`).to.be.true;
            return result.body;
        });
    });
}

/**
 * Skip the calling test when the stack has no mail server.
 *
 * Delivery cannot be asserted without one, and refusing to run is honest where
 * pretending the absence is a pass is not. Everything that can be proven from
 * the outbox alone is asserted outside these guarded tests.
 */
function requireMailpit(ctx) {
    if (!mailpitAvailable) {
        ctx.skip();
    }
}

// ── cleanup ────────────────────────────────────────────────────────────────

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
    dbOk(`DELETE vasg FROM volunteer_assignment_vasg vasg ${scoped}`, [
        `${FIXTURE_PREFIX}%`,
    ]);
}

function cleanupFixtures() {
    cleanupWorkflowRows();

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
    dbOk(
        `DELETE vpol FROM volunteer_pool_vpol vpol
           JOIN volunteer_team_vtem vtem
             ON vtem.vtem_ID = vpol.vpol_OwnerId AND vpol.vpol_OwnerType = 'team'
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vtem.vtem_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(
        `DELETE vtem FROM volunteer_team_vtem vtem
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vtem.vtem_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        [`${FIXTURE_PREFIX}%`],
    );
    dbOk(`DELETE FROM volunteer_scope_vscp WHERE vscp_per_ID IN (?, ?)`, [
        PERSON_COORDINATOR,
        PERSON_PLAIN,
    ]);
    dbOk(`DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?`, [
        `${FIXTURE_PREFIX}%`,
    ]);
    dbOk(`DELETE FROM events_event WHERE event_title LIKE ?`, [`${EVENT_TITLE}%`]);
    dbOk(`DELETE FROM person2group2role_p2g2r WHERE p2g2r_per_ID = ? AND p2g2r_grp_ID = ?`, [
        PERSON_VOLUNTEER,
        POOL_GROUP,
    ]);
    // Anything this spec borrowed from a seeded person is put back by hand.
    dbOk(`DELETE FROM record2property_r2p WHERE r2p_pro_ID = ? AND r2p_record_ID IN (?, ?)`, [
        DO_NOT_EMAIL_PROPERTY,
        POOL_MEMBER_A,
        POOL_MEMBER_B,
    ]);
}

function createMinistry(name) {
    return api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries`, {
        name: `${FIXTURE_PREFIX} ${name}`,
        description: "volunteer v2 notification fixture",
    }, 201).then((resp) => resp.body.ministry.id);
}

function createTeam(ministryId, name) {
    return api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/teams`, {
        name: `${FIXTURE_PREFIX} ${name}`,
        description: "volunteer v2 notification fixture",
    }, 201).then((resp) => resp.body.team.id);
}

/**
 * The team a ministry was born with.
 *
 * Every ministry is created with one team already in it, named "{Ministry} Team",
 * so this fixture adopts that team instead of creating a second one with the same
 * name — which the API now answers 409 to, correctly.
 */
function defaultTeam(ministryId) {
    return api(ADMIN_KEY, "GET", `${VOLUNTEER_URL}/ministries/${ministryId}`, null, 200).then(
        (resp) => resp.body.teams[0].id,
    );
}

function createPosition(ministryId, teamId, name, order) {
    return api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/positions`, {
        name: `${FIXTURE_PREFIX} ${name}`,
        description: "volunteer v2 notification fixture",
        teamId,
        order,
    }, 201).then((resp) => resp.body.position.id);
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

function upsertRequirement(scheduleId, positionId, minCount, maxCount) {
    return api(
        ADMIN_KEY,
        "POST",
        `${VOLUNTEER_URL}/schedules/${scheduleId}/requirements`,
        { positionId, minCount, maxCount },
        [200, 201],
    );
}

function resetWorkflow() {
    cleanupWorkflowRows();
    clearMail();
}

// ── fixture ────────────────────────────────────────────────────────────────

before(() => {
    cy.task("mail:available").then((result) => {
        mailpitAvailable = result.available;
        cy.log(
            result.available
                ? `Mailpit reachable at ${result.url}`
                : `Mailpit NOT reachable at ${result.url} (${result.error}) — delivery assertions will skip`,
        );
    });

    readConfig(SETTING_URL).then((value) => {
        originalVersion = value || "v1";
    });
    readConfig(SMTP_HOST_URL).then((value) => {
        originalSmtpHost = value;
    });
    readConfig(LEAD_HOURS_URL).then((value) => {
        originalLeadHours = value;
    });
    readConfig(DO_NOT_EMAIL_URL).then((value) => {
        originalDoNotEmail = value;
    });
    readConfig(CHURCH_NAME_URL).then((value) => {
        churchName = value || "";
    });

    setConfig(SETTING_URL, "v2");
    setConfig(EMAIL_ENABLED_URL, "1");
    // Reminders are opt-in per test: a lead of 0 means "schedule none", so a
    // fixture occurrence that happens to fall inside the default 48 hours
    // cannot seed reminder rows into an unrelated assertion.
    setConfig(LEAD_HOURS_URL, "0");
    setConfig(DO_NOT_EMAIL_URL, String(DO_NOT_EMAIL_PROPERTY));

    cleanupFixtures();

    createMinistry("Coffee Bar").then((id) => {
        ministryA = id;
    });
    createMinistry("Sound Booth").then((id) => {
        ministryB = id;
    });

    cy.then(() => {
        defaultTeam(ministryA).then((id) => {
            teamA = id;
        });
    });

    cy.then(() => {
        createPosition(ministryA, teamA, "Espresso", 1).then((id) => {
            posEspresso = id;
        });
        createPosition(ministryA, teamA, "Milk Station", 2).then((id) => {
            posMilk = id;
        });
    });

    cy.then(() => {
        dbOk(
            `INSERT IGNORE INTO person2group2role_p2g2r (p2g2r_per_ID, p2g2r_grp_ID, p2g2r_rle_ID)
             VALUES (?, ?, 2)`,
            [PERSON_VOLUNTEER, POOL_GROUP],
        );
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/teams/${teamA}/pools`, {
            groupId: POOL_GROUP,
            label: `${FIXTURE_PREFIX} pool`,
        }, 201);
    });

    cy.then(() => {
        qualify(posEspresso, POOL_MEMBER_A);
        qualify(posEspresso, POOL_MEMBER_B);
        qualify(posEspresso, POOL_MEMBER_C);
        qualify(posEspresso, PERSON_VOLUNTEER);
        qualify(posMilk, POOL_MEMBER_A);
        qualify(posMilk, POOL_MEMBER_B);
    });

    cy.then(() => {
        seriesStart = isoDate(daysToNext(0));
        seriesEnd = isoDate(daysToNext(0) + 14);

        api(ADMIN_KEY, "POST", "/api/events/repeat", {
            Title: EVENT_TITLE,
            Type: CHURCH_SERVICE_TYPE,
            StartTime: "10:30:00",
            EndTime: "11:45:00",
            RecurType: "weekly",
            RecurDOW: "Sunday",
            RangeStart: seriesStart,
            RangeEnd: seriesEnd,
        }, 200).then((resp) => {
            expect(resp.body.eventIds.length).to.eq(3);
        });
    });

    cy.then(() => {
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/ministries/${ministryA}/schedules`, {
            name: `${FIXTURE_PREFIX} Coffee Bar — Sunday`,
            linkMode: "event_type",
            eventTypeId: CHURCH_SERVICE_TYPE,
            titleFilter: EVENT_TITLE,
            windowStart: seriesStart,
            teamId: teamA,
        }, 201).then((resp) => {
            scheduleA = resp.body.schedule.id;
        });
    });

    cy.then(() => {
        upsertRequirement(scheduleA, posEspresso, 1, 1);
        upsertRequirement(scheduleA, posMilk, 1, 1);
    });

    cy.then(() => {
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/schedules/${scheduleA}/generate`, {
            through: seriesEnd,
        }, 200);
    });

    cy.then(() => {
        api(
            ADMIN_KEY,
            "GET",
            `${VOLUNTEER_URL}/occurrences?from=${seriesStart}&to=${seriesEnd}&ministryId=${ministryA}`,
        ).then((resp) => {
            const rows = resp.body.occurrences;
            expect(rows.length).to.be.greaterThan(1);
            occurrenceOne = rows[0].id;
            occurrenceOneStart = rows[0].start;
            occurrenceTwo = rows[1].id;
            occurrenceTwoStart = rows[1].start;
        });
    });

    // Person 3 coordinates Coffee Bar and nothing else — this is also the person
    // §3.6's Reply-To resolution must land on, since the schedule's team has no
    // leader of its own.
    cy.then(() => {
        api(ADMIN_KEY, "POST", `${VOLUNTEER_URL}/scopes`, {
            personId: PERSON_COORDINATOR,
            scopeType: "ministry",
            scopeId: ministryA,
        }, [200, 201]);
    });
});

/** Put a setting back only if this run got far enough to learn what it was. */
function restoreConfig(url, captured) {
    if (captured === null) {
        return;
    }
    setConfig(url, captured);
}

after(() => {
    cleanupFixtures();
    setConfig(EMAIL_ENABLED_URL, "1");
    restoreConfig(SETTING_URL, originalVersion);
    restoreConfig(SMTP_HOST_URL, originalSmtpHost);
    restoreConfig(LEAD_HOURS_URL, originalLeadHours);
    restoreConfig(DO_NOT_EMAIL_URL, originalDoNotEmail);
    clearMail();
});

// ───────────────────────────────────────────────────────────────────────────

describe("Volunteer v2 — outbox enqueue is idempotent (§2.14, §6.6)", () => {
    beforeEach(resetWorkflow);

    it("notifying twice reuses the one row, and the read endpoint shows one", () => {
        assign(COORDINATOR_KEY, occurrenceOne, posEspresso, POOL_MEMBER_A).then(
            (assignment) => {
                const url = `${VOLUNTEER_URL}/assignments/${assignment.id}/notify`;

                api(COORDINATOR_KEY, "POST", url, {}).then((first) => {
                    // assign() already enqueued it, so even the first explicit
                    // notify finds the existing row — that IS the dedupe key
                    // doing its job (§2.14).
                    expect(first.body.created).to.be.false;

                    api(COORDINATOR_KEY, "POST", url, {}).then((second) => {
                        expect(second.body.created).to.be.false;
                        expect(second.body.notification.id).to.eq(
                            first.body.notification.id,
                        );
                    });
                });

                notifications(assignment.id).then((resp) => {
                    expect(resp.body.notifications).to.have.length(1);
                    const row = resp.body.notifications[0];
                    expect(row.type).to.eq("assignment");
                    expect(row.channel).to.eq("email");
                    expect(row.status).to.eq("pending");
                    expect(row.attempts).to.eq(0);
                    expect(row.personId).to.eq(POOL_MEMBER_A);
                    expect(row.dedupeKey).to.eq(
                        `assignment:${assignment.id}:${POOL_MEMBER_A}`,
                    );
                });
            },
        );
    });

    it("?force=1 re-arms the same row rather than adding a second", () => {
        assign(COORDINATOR_KEY, occurrenceOne, posEspresso, POOL_MEMBER_A).then(
            (assignment) => {
                const key = `assignment:${assignment.id}:${POOL_MEMBER_A}`;
                forceOutbox(key, { vntf_Status: "sent", vntf_Attempts: 2 });

                api(
                    COORDINATOR_KEY,
                    "POST",
                    `${VOLUNTEER_URL}/assignments/${assignment.id}/notify?force=1`,
                    {},
                );

                notifications(assignment.id).then((resp) => {
                    expect(resp.body.notifications).to.have.length(1);
                    expect(resp.body.notifications[0].status).to.eq("pending");
                    expect(resp.body.notifications[0].attempts).to.eq(0);
                });
            },
        );
    });

    it("a drained row is not re-sent by the next drain", function () {
        requireMailpit(this);

        assign(COORDINATOR_KEY, occurrenceOne, posEspresso, POOL_MEMBER_A).then(
            (assignment) => {
                const key = `assignment:${assignment.id}:${POOL_MEMBER_A}`;

                drain();
                outboxRow(key).then((row) => {
                    expect(row.vntf_Status).to.eq("sent");
                });
                listMail().then((messages) => {
                    expect(messages).to.have.length(1);
                });

                drain();
                listMail().then((messages) => {
                    expect(
                        messages,
                        "a sent row is never re-selected (§3.6 step 1)",
                    ).to.have.length(1);
                });
            },
        );
    });
});

describe("Volunteer v2 — the drain delivers (§3.6, Appendix C)", () => {
    beforeEach(resetWorkflow);

    it("sends the assignment mail with the position, the ministry and the coordinator Reply-To", function () {
        requireMailpit(this);

        assign(COORDINATOR_KEY, occurrenceOne, posEspresso, POOL_MEMBER_A).then(
            (assignment) => {
                drain();

                outboxRow(`assignment:${assignment.id}:${POOL_MEMBER_A}`).then(
                    (row) => {
                        expect(row.vntf_Status).to.eq("sent");
                        expect(row.vntf_SentDate).to.not.be.null;
                        expect(row.vntf_LastError).to.be.null;
                    },
                );

                mailTo(MEMBER_A_EMAIL).then((message) => {
                    // The church name prefix is the shape every BaseEmail
                    // subclass in the tree already uses.
                    expect(message.Subject).to.contain(churchName);
                    expect(message.Subject.toLowerCase()).to.contain("serve");

                    const body = `${message.Text || ""}\n${message.HTML || ""}`;
                    expect(body, "the position name").to.contain(ESPRESSO_NAME);
                    expect(body, "the ministry name").to.contain(MINISTRY_NAME);
                    expect(body, "the church wall-clock time").to.contain("10:30 AM");
                    expect(body, "the CTA into my schedule").to.contain(
                        "/volunteer/my-schedule",
                    );

                    // N10 / CR6: replies reach the coordinator, not the office.
                    const replyTo = (message.ReplyTo || []).map((r) => r.Address);
                    expect(replyTo).to.deep.eq([COORDINATOR_EMAIL]);
                    // The From stays the church address (BaseEmail.php).
                    expect(message.From.Address).to.eq("demo@churchcrm.io");
                });
            },
        );
    });

    it("alerts every coordinator when a VOLUNTEER declines, and nobody when the coordinator records it", function () {
        requireMailpit(this);

        assign(COORDINATOR_KEY, occurrenceOne, posEspresso, PERSON_VOLUNTEER).then(
            (assignment) => {
                api(
                    SELFEDIT_KEY,
                    "POST",
                    `${VOLUNTEER_URL}/me/assignments/${assignment.id}/respond`,
                    { response: "declined" },
                );

                drain();

                outboxRow(
                    `decline_alert:${assignment.id}:${PERSON_COORDINATOR}`,
                ).then((row) => {
                    expect(row.vntf_Status).to.eq("sent");
                });

                mailTo(COORDINATOR_EMAIL, "declined").then((message) => {
                    const body = `${message.Text || ""}\n${message.HTML || ""}`;
                    expect(body, "who declined").to.contain("Amanda");
                    expect(body, "the position still short").to.contain(
                        ESPRESSO_NAME,
                    );
                    expect(body, "the gap link").to.contain("/volunteer/occurrences/");
                    expect(body, "how many are still needed").to.contain("1");
                    // §3.6: a coordinator-facing decline alert replies to the
                    // volunteer who declined.
                    const replyTo = (message.ReplyTo || []).map((r) => r.Address);
                    expect(replyTo).to.deep.eq(["amanda.black@example.com"]);
                });

                // The same decline also opened a gap, so the coordinator gets the
                // occurrence-scoped alert too — and THAT one carries no Reply-To,
                // because it names no single volunteer (§3.6).
                outboxRowsOfType("gap_alert").then((rows) => {
                    expect(rows).to.have.length(1);
                    expect(rows[0].vntf_Status).to.eq("sent");
                    expect(Number(rows[0].vntf_per_ID)).to.eq(PERSON_COORDINATOR);
                });

                mailTo(COORDINATOR_EMAIL, "still need to be filled").then(
                    (message) => {
                        expect(message.ReplyTo || []).to.have.length(0);
                        const body = `${message.Text || ""}\n${message.HTML || ""}`;
                        expect(body, "which positions are short").to.contain(
                            ESPRESSO_NAME,
                        );
                    },
                );
            },
        );
    });

    it("a coordinator-recorded decline enqueues and sends no decline alert", () => {
        assign(COORDINATOR_KEY, occurrenceOne, posEspresso, POOL_MEMBER_A).then(
            (assignment) => {
                api(
                    COORDINATOR_KEY,
                    "POST",
                    `${VOLUNTEER_URL}/assignments/${assignment.id}/status`,
                    { status: "declined" },
                );

                drain();

                outboxRowsOfType("decline_alert").then((rows) => {
                    expect(rows).to.have.length(0);
                });
            },
        );
    });

    it("a decline removes the volunteer's own pending row before it can be sent", () => {
        assign(COORDINATOR_KEY, occurrenceOne, posEspresso, PERSON_VOLUNTEER).then(
            (assignment) => {
                outboxRow(`assignment:${assignment.id}:${PERSON_VOLUNTEER}`);

                api(
                    SELFEDIT_KEY,
                    "POST",
                    `${VOLUNTEER_URL}/me/assignments/${assignment.id}/respond`,
                    { response: "declined" },
                );

                // cancelPendingFor() deleted it (§3.6) — so nothing is owed to
                // somebody who has already said no.
                dbOk(
                    `SELECT vntf_ID FROM volunteer_notification_vntf WHERE vntf_DedupeKey = ?`,
                    [`assignment:${assignment.id}:${PERSON_VOLUNTEER}`],
                ).then((rows) => {
                    expect(rows).to.have.length(0);
                });
            },
        );
    });
});

describe("Volunteer v2 — skipped is not failed (§2.14, N2/N3)", () => {
    beforeEach(resetWorkflow);

    afterEach(() => {
        setConfig(EMAIL_ENABLED_URL, "1");
        dbOk(`UPDATE person_per SET per_Email = ? WHERE per_ID = ?`, [
            "julie.gregory@example.com",
            POOL_MEMBER_C,
        ]);
        dbOk(`DELETE FROM record2property_r2p WHERE r2p_pro_ID = ? AND r2p_record_ID = ?`, [
            DO_NOT_EMAIL_PROPERTY,
            POOL_MEMBER_A,
        ]);
    });

    it("skips every row while email is switched off, without an attempt", () => {
        assign(COORDINATOR_KEY, occurrenceOne, posEspresso, POOL_MEMBER_A).then(
            (assignment) => {
                setConfig(EMAIL_ENABLED_URL, "0");
                drain();

                outboxRow(`assignment:${assignment.id}:${POOL_MEMBER_A}`).then(
                    (row) => {
                        expect(row.vntf_Status).to.eq("skipped");
                        expect(
                            row.vntf_Attempts,
                            "isEmailEnabled() is checked BEFORE sending (N2)",
                        ).to.eq(0);
                        expect(row.vntf_SentDate).to.be.null;
                    },
                );

                listMail().then((messages) => {
                    expect(messages).to.have.length(0);
                });
            },
        );
    });

    it("skips a recipient who has no email address", () => {
        dbOk(`UPDATE person_per SET per_Email = NULL WHERE per_ID = ?`, [
            POOL_MEMBER_C,
        ]);

        assign(COORDINATOR_KEY, occurrenceOne, posEspresso, POOL_MEMBER_C).then(
            (assignment) => {
                drain();

                outboxRow(`assignment:${assignment.id}:${POOL_MEMBER_C}`).then(
                    (row) => {
                        expect(row.vntf_Status).to.eq("skipped");
                        expect(row.vntf_Attempts).to.eq(0);
                    },
                );
            },
        );
    });

    it("honours the do-not-email opt-out (N3)", () => {
        dbOk(
            `INSERT INTO record2property_r2p (r2p_pro_ID, r2p_record_ID, r2p_Value)
             VALUES (?, ?, '')`,
            [DO_NOT_EMAIL_PROPERTY, POOL_MEMBER_A],
        );

        assign(COORDINATOR_KEY, occurrenceOne, posEspresso, POOL_MEMBER_A).then(
            (assignment) => {
                drain();

                outboxRow(`assignment:${assignment.id}:${POOL_MEMBER_A}`).then(
                    (row) => {
                        expect(row.vntf_Status).to.eq("skipped");
                        expect(row.vntf_Attempts).to.eq(0);
                    },
                );

                listMail().then((messages) => {
                    const addressed = messages.filter((m) =>
                        (m.To || []).some((t) => t.Address === MEMBER_A_EMAIL),
                    );
                    expect(addressed).to.have.length(0);
                });
            },
        );
    });

    it("skips a reminder whose occurrence has already ended", () => {
        // The occurrence is detached from its event so its OWN start/end decide
        // the window (VolunteerScheduleService::resolveOccurrenceWindow), which
        // is the only way to put a generated occurrence in the past without
        // rewriting a shared church event.
        let savedEventId = null;

        dbOk(`SELECT vocc_event_id FROM volunteer_occurrence_vocc WHERE vocc_ID = ?`, [
            occurrenceTwo,
        ]).then((rows) => {
            savedEventId = rows[0].vocc_event_id;
        });

        assign(COORDINATOR_KEY, occurrenceTwo, posMilk, POOL_MEMBER_B).then(
            (assignment) => {
                const key = `reminder:${assignment.id}:${POOL_MEMBER_B}`;

                dbOk(
                    `UPDATE volunteer_occurrence_vocc
                        SET vocc_event_id = NULL,
                            vocc_StartDateTime = DATE_SUB(NOW(), INTERVAL 3 DAY),
                            vocc_EndDateTime = DATE_SUB(NOW(), INTERVAL 3 DAY)
                      WHERE vocc_ID = ?`,
                    [occurrenceTwo],
                );

                dbOk(
                    `INSERT INTO volunteer_notification_vntf
                        (vntf_Type, vntf_Channel, vntf_per_ID, vntf_vasg_ID, vntf_vocc_ID,
                         vntf_DedupeKey, vntf_ScheduledFor, vntf_Status, vntf_Attempts)
                     VALUES ('reminder', 'email', ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL 1 DAY), 'pending', 0)`,
                    [POOL_MEMBER_B, assignment.id, occurrenceTwo, key],
                );

                drain();

                outboxRow(key).then((row) => {
                    expect(row.vntf_Status).to.eq("skipped");
                    expect(row.vntf_Attempts).to.eq(0);
                });

                cy.then(() => {
                    dbOk(
                        `UPDATE volunteer_occurrence_vocc
                            SET vocc_event_id = ?, vocc_StartDateTime = NULL, vocc_EndDateTime = NULL
                          WHERE vocc_ID = ?`,
                        [savedEventId, occurrenceTwo],
                    );
                });
            },
        );
    });
});

describe("Volunteer v2 — the retry rule (§2.14, amended)", () => {
    beforeEach(() => {
        resetWorkflow();
        // Nothing is listening on port 2, so PHPMailer fails to connect and
        // send() returns false with an ErrorInfo — a genuine delivery failure,
        // while isEmailEnabled() stays true so the row cannot be `skipped`.
        setConfig(SMTP_HOST_URL, "127.0.0.1:2");
    });

    afterEach(() => {
        restoreConfig(SMTP_HOST_URL, originalSmtpHost);
    });

    it("leaves a failed row pending with Attempts 1 and the error recorded", () => {
        assign(COORDINATOR_KEY, occurrenceOne, posEspresso, POOL_MEMBER_A).then(
            (assignment) => {
                const key = `assignment:${assignment.id}:${POOL_MEMBER_A}`;

                drain();

                outboxRow(key).then((row) => {
                    expect(
                        row.vntf_Status,
                        "a failure below the cap stays selectable (§2.14)",
                    ).to.eq("pending");
                    expect(row.vntf_Attempts).to.eq(1);
                    expect(row.vntf_LastError).to.be.a("string").and.not.be.empty;
                    expect(row.vntf_LastAttemptDate).to.not.be.null;
                    expect(row.vntf_SentDate).to.be.null;
                });

                // The state change committed and stays committed: a delivery
                // failure never rolls the assignment back.
                api(
                    ADMIN_KEY,
                    "GET",
                    `${VOLUNTEER_URL}/assignments/${assignment.id}`,
                ).then((resp) => {
                    expect(resp.body.assignment.status).to.eq("pending");
                });
            },
        );
    });

    it("retries on the next drain, counting up", () => {
        assign(COORDINATOR_KEY, occurrenceOne, posEspresso, POOL_MEMBER_A).then(
            (assignment) => {
                const key = `assignment:${assignment.id}:${POOL_MEMBER_A}`;

                drain();
                drain();

                outboxRow(key).then((row) => {
                    expect(row.vntf_Status).to.eq("pending");
                    expect(row.vntf_Attempts).to.eq(2);
                });
            },
        );
    });

    it("gives up at the fifth attempt, and failed is terminal", () => {
        assign(COORDINATOR_KEY, occurrenceOne, posEspresso, POOL_MEMBER_A).then(
            (assignment) => {
                const key = `assignment:${assignment.id}:${POOL_MEMBER_A}`;

                // Four failures already recorded; the next one is the cap.
                forceOutbox(key, { vntf_Attempts: 4 });
                drain();

                outboxRow(key).then((row) => {
                    expect(row.vntf_Status).to.eq("failed");
                    expect(row.vntf_Attempts).to.eq(5);
                });

                // Terminal: the drain never selects it again, so the counter
                // cannot keep climbing.
                drain();
                outboxRow(key).then((row) => {
                    expect(row.vntf_Status).to.eq("failed");
                    expect(row.vntf_Attempts).to.eq(5);
                });

                notifications(assignment.id).then((resp) => {
                    expect(resp.body.notifications[0].status).to.eq("failed");
                    expect(resp.body.notifications[0].lastError).to.be.a("string");
                });
            },
        );
    });
});

describe("Volunteer v2 — reminders (D11, Appendix B, §3.6)", () => {
    beforeEach(resetWorkflow);

    afterEach(() => {
        setConfig(LEAD_HOURS_URL, "0");
    });

    it("schedules no reminder for an occurrence outside the lead window", () => {
        assign(COORDINATOR_KEY, occurrenceTwo, posEspresso, POOL_MEMBER_A).then(
            () => {
                setConfig(LEAD_HOURS_URL, "1");
                drain();

                outboxRowsOfType("reminder").then((rows) => {
                    expect(rows).to.have.length(0);
                });
            },
        );
    });

    it("schedules one reminder per live assignment inside the lead window, at start minus the lead", () => {
        // 21 days covers every occurrence the fixture generates, so the window
        // question is decided by the setting rather than by which weekday the
        // suite happens to run on.
        const lead = 21 * 24;

        assign(COORDINATOR_KEY, occurrenceTwo, posEspresso, POOL_MEMBER_A).then(
            (assignment) => {
                setConfig(LEAD_HOURS_URL, String(lead));
                drain();

                outboxRow(`reminder:${assignment.id}:${POOL_MEMBER_A}`).then(
                    (row) => {
                        expect(row.vntf_Type).to.eq("reminder");
                        expect(row.vntf_ScheduledFor).to.eq(
                            minusHours(occurrenceTwoStart, lead),
                        );
                        expect(Number(row.vntf_vasg_ID)).to.eq(assignment.id);
                    },
                );

                // Idempotent on the dedupe key: a second timer-job run adds none.
                drain();
                outboxRowsOfType("reminder").then((rows) => {
                    expect(rows).to.have.length(1);
                });
            },
        );
    });

    it("schedules no reminder for an assignment that is no longer live", () => {
        assign(COORDINATOR_KEY, occurrenceTwo, posEspresso, POOL_MEMBER_A).then(
            (assignment) => {
                api(
                    COORDINATOR_KEY,
                    "POST",
                    `${VOLUNTEER_URL}/assignments/${assignment.id}/status`,
                    { status: "cancelled" },
                );

                setConfig(LEAD_HOURS_URL, String(21 * 24));
                drain();

                outboxRowsOfType("reminder").then((rows) => {
                    expect(rows).to.have.length(0);
                });
            },
        );
    });

    it("delivers the reminder with the occurrence details", function () {
        requireMailpit(this);

        assign(COORDINATOR_KEY, occurrenceTwo, posEspresso, POOL_MEMBER_A).then(
            (assignment) => {
                // Drain the assignment mail first, so the only message left to
                // find is the reminder.
                drain();
                clearMail();

                setConfig(LEAD_HOURS_URL, String(21 * 24));
                drain();

                outboxRow(`reminder:${assignment.id}:${POOL_MEMBER_A}`).then(
                    (row) => {
                        expect(row.vntf_Status).to.eq("sent");
                    },
                );

                mailTo(MEMBER_A_EMAIL).then((message) => {
                    expect(message.Subject.toLowerCase()).to.contain("reminder");
                    const body = `${message.Text || ""}\n${message.HTML || ""}`;
                    expect(body).to.contain(ESPRESSO_NAME);
                    expect(body).to.contain(MINISTRY_NAME);
                    const replyTo = (message.ReplyTo || []).map((r) => r.Address);
                    expect(replyTo).to.deep.eq([COORDINATOR_EMAIL]);
                });
            },
        );
    });
});

describe("Volunteer v2 — GET /assignments/{id}/notifications is scoped (§4.8)", () => {
    let assignmentId = 0;

    before(() => {
        cleanupWorkflowRows();
        assign(COORDINATOR_KEY, occurrenceOne, posEspresso, POOL_MEMBER_A).then(
            (assignment) => {
                assignmentId = assignment.id;
            },
        );
    });

    after(cleanupWorkflowRows);

    it("is refused without authentication", () => {
        cy.request({
            method: "GET",
            url: `${VOLUNTEER_URL}/assignments/${assignmentId}/notifications`,
            failOnStatusCode: false,
        }).then((resp) => {
            expect(resp.status).to.eq(401);
        });
    });

    it("is refused to a caller with no volunteer rights", () => {
        notifications(assignmentId, PLAINAUTH_KEY, 403);
    });

    it("is refused to the volunteer whose assignment it is", () => {
        // The member surface is /api/volunteer/me; the coordinator read is not
        // a self-service endpoint (§3.3.3).
        notifications(assignmentId, SELFEDIT_KEY, 403);
    });

    it("is allowed to the coordinator of that ministry", () => {
        notifications(assignmentId, COORDINATOR_KEY).then((resp) => {
            expect(resp.body.notifications).to.have.length(1);
            expect(resp.body.notifications[0].assignmentId).to.eq(assignmentId);
        });
    });

    it("404s for an assignment that does not exist", () => {
        notifications(99999999, ADMIN_KEY, 404);
    });
});
