/// <reference types="cypress" />

/**
 * Member sign-in row 13 (#10463): the release-day cutover in the 7.9.0 upgrade
 * (docs/member-sign-in-design.md §12, §17 "Cutover"). The application no longer
 * makes self-service accounts, so the pre-upgrade accounts are fixtures written
 * in SQL; the test then runs the shipped block of src/mysql/upgrade/7.9.0.sql
 * (task db:upgradeBlock) and reads the result back through the API wherever
 * there is one.
 */
const CUTOVER = { script: "7.9.0.sql", block: "member sign-in row 13, release-day cutover" };
const SHARED_CHANGEME_HASH = "$2y$12$e3o8rmvWUYdgzUNB/AAMK.pRvT9rwsIZx4wYB0brOmVPB1UL.HA5S";
const tag = `cut${Date.now()}`;
const token = (index) => `${index}${Date.now().toString(16)}`.padEnd(64, "0");
const feedUrl = (feedToken) => `/api/public/portal-calendar/${feedToken}/calendar.ics`;
const CONFIG_URL = "/admin/api/system/config";

// Each pre-upgrade account: its flags, and the feed it had.
const ACCOUNTS = {
    selfService: { editSelf: 1, admin: 0, notes: 0, addRecords: 0, removed: true },
    dormantFlags: { editSelf: 1, admin: 0, notes: 1, addRecords: 1, removed: true },
    unconfirmed: { editSelf: 1, admin: 0, notes: 0, addRecords: 0, removed: true },
    blocked: { editSelf: 1, admin: 0, notes: 0, addRecords: 0, removed: true },
    administrator: { editSelf: 1, admin: 1, notes: 0, addRecords: 0, removed: false },
    readOnly: { editSelf: 0, admin: 0, notes: 0, addRecords: 0, removed: false },
};
const REMOVED = Object.values(ACCOUNTS).filter((account) => account.removed).length;

const per = {};
const feeds = {};
let formerBefore = 0;

const db = (sql, params = []) =>
    cy.dbQuery(sql, params).then((result) => {
        expect(result.error, sql).to.be.null;
        return result.rows;
    });

const runCutover = () => cy.task("db:upgradeBlock", CUTOVER).its("error").should("be.null");
const config = (name) => cy.makePrivateAdminAPICall("GET", `${CONFIG_URL}/${name}`, null, 200).its("body.value");
const setConfig = (name, value) => cy.makePrivateAdminAPICall("POST", `${CONFIG_URL}/${name}`, { value }, 200);
const feedStatus = (feedToken) => cy.request({ url: feedUrl(feedToken), failOnStatusCode: false }).its("status");
const accountOf = (key) => db("SELECT usr_EditSelf AS editSelf, usr_Admin AS admin FROM user_usr WHERE usr_per_ID = ?", [per[key]]);
const signInRowOf = (key) => db("SELECT * FROM person_signin_psi WHERE psi_per_ID = ?", [per[key]]).its(0);

const addAccount = (key, { editSelf, admin, notes, addRecords }, index) => {
    feeds[key] = token(index);
    db(
        "INSERT INTO person_per (per_FirstName, per_LastName, per_Email, per_fmr_ID, per_cls_ID, per_fam_ID, per_DateEntered, per_EnteredBy) " +
            "VALUES (?, ?, ?, 1, 1, 0, NOW(), 1)",
        [key, tag, `${key}.${tag}@example.com`],
    ).then(({ insertId }) => {
        per[key] = insertId;
        db(
            "INSERT INTO user_usr (usr_per_ID, usr_Password, usr_NeedPasswordChange, usr_UserName, usr_apiKey, usr_EditSelf, usr_Admin, " +
                "usr_Notes, usr_AddRecords, usr_PortalCalendarToken, usr_PortalCalendarSelection, usr_LastPortalActivity) " +
                "VALUES (?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, '2026-03-04 05:06:07')",
            [insertId, SHARED_CHANGEME_HASH, `${key}.${tag}`, `${key}${tag}ApiKey`, editSelf, admin, notes, addRecords, feeds[key], '["calendar:1"]'],
        );
    });
};

describe("7.9.0 upgrade: the release-day cutover", () => {
    before(() => {
        cy.makePrivateAdminAPICall("POST", "/admin/api/member-portal/calendars", { visible: [{ type: "calendar", id: 1 }] }, 200);
        config("iMemberSignInFormerAccounts").then((value) => {
            formerBefore = Number(value || 0);
        });
        Object.entries(ACCOUNTS).forEach(([key, account], index) => addAccount(key, account, index));
        cy.then(() => {
            const id = per.selfService;
            db(
                "INSERT INTO userconfig_ucfg (ucfg_per_id, ucfg_id, ucfg_name, ucfg_value, ucfg_type, ucfg_tooltip, ucfg_permission, ucfg_cat) " +
                    "VALUES (?, 10, 'bAddEvent', '', 'boolean', '', 'FALSE', '')",
                [id],
            );
            db("INSERT INTO user_settings (user_id, setting_name, setting_value) VALUES (?, 'ui.locale', 'fr_FR')", [id]);
            db("INSERT INTO tokens (token, type, reference_id, valid_until_date, remainingUses) VALUES (?, 'password', ?, NOW() + INTERVAL 1 DAY, 1)", [
                `t${tag}`,
                id,
            ]);
            db(
                "INSERT INTO rate_limit_rlm (rlm_Bucket, rlm_KeyHash, rlm_Hits, rlm_ResetAt) VALUES ('signin.lockout', SHA2(?, 256), 1, UNIX_TIMESTAMP() + 3600)",
                [String(id)],
            );
            db(
                "INSERT INTO person_signin_psi (psi_per_ID, psi_UnconfirmedEmail, psi_UnconfirmedSource, psi_UnconfirmedAt) VALUES (?, ?, 'family_email', '2026-01-02 03:04:05')",
                [per.unconfirmed, `unconfirmed.${tag}@example.com`],
            );
        });
        runCutover();
    });

    after(() => {
        const ids = Object.values(per);
        db(`DELETE FROM user_usr WHERE usr_per_ID IN (${ids.map(() => "?").join(",")})`, ids);
        cy.cleanupPeople(ids);
        cy.makePrivateAdminAPICall("POST", "/admin/api/member-portal/calendars", { visible: [] }, 200);
        setConfig("iMemberSignInFormerAccounts", String(formerBefore));
        db("DELETE FROM config_cfg WHERE cfg_name IN ('bMemberSignIn', 'sMemberSignInClassifications')");
    });

    it("removes the self-service accounts, those with dormant staff flags too", () => {
        ["selfService", "dormantFlags", "unconfirmed", "blocked"].forEach((key) => accountOf(key).should("have.length", 0));
        cy.makePrivateAdminAPICall("GET", `/api/person/${per.selfService}`, null, 200).its("body.FirstName").should("eq", "selfService");
    });

    it("leaves administrators and read-only staff accounts as they were", () => {
        accountOf("administrator").its(0).should("deep.include", { editSelf: 1, admin: 1 });
        accountOf("readOnly").its(0).should("deep.include", { editSelf: 0, admin: 0 });
    });

    it("moves every account's feed, selection and last portal activity to the sign-in row", () => {
        Object.keys(ACCOUNTS).forEach((key) => {
            signInRowOf(key).then((row) => {
                expect(row.psi_CalendarToken, key).to.eq(feeds[key]);
                expect(row.psi_CalendarSelection, key).to.eq('["calendar:1"]');
                expect(row.psi_LastActivity, key).to.eq("2026-03-04 05:06:07");
            });
        });
    });

    it("keeps the unconfirmed email the family email move left on the sign-in row", () => {
        signInRowOf("unconfirmed").should("deep.include", {
            psi_UnconfirmedEmail: `unconfirmed.${tag}@example.com`,
            psi_UnconfirmedSource: "family_email",
            psi_UnconfirmedAt: "2026-01-02 03:04:05",
        });
    });

    it("deletes the removed account's settings, reset links and sign-in throttle", () => {
        const id = () => per.selfService;
        cy.then(() => db("SELECT COUNT(*) AS n FROM userconfig_ucfg WHERE ucfg_per_id = ?", [id()])).its("0.n").should("eq", 0);
        cy.then(() => db("SELECT COUNT(*) AS n FROM user_settings WHERE user_id = ?", [id()])).its("0.n").should("eq", 0);
        cy.then(() => db("SELECT COUNT(*) AS n FROM tokens WHERE reference_id = ? AND type = 'password'", [id()])).its("0.n").should("eq", 0);
        cy.then(() => db("SELECT COUNT(*) AS n FROM rate_limit_rlm WHERE rlm_KeyHash = SHA2(?, 256)", [String(id())]))
            .its("0.n")
            .should("eq", 0);
    });

    it("notes the removal on the person's timeline as the upgrade", () => {
        cy.makePrivateAdminAPICall("GET", `/api/timeline/person/${per.selfService}`, null, 200).then(({ body }) => {
            const notes = body.timeline.filter((item) => item.type === "user");
            expect(notes).to.have.length(1);
            expect(notes[0].text).to.eq("Member Portal account removed by the upgrade");
            expect(notes[0].header).to.include("System Upgrade");
        });
    });

    it("counts the removed accounts for the dashboard notice and leaves member sign-in off", () => {
        config("iMemberSignInFormerAccounts").then((value) => expect(Number(value)).to.eq(formerBefore + REMOVED));
        config("bMemberSignIn").should("be.oneOf", ["", "0"]);
    });

    it("keeps every copied calendar feed address working", () => {
        Object.keys(ACCOUNTS).forEach((key) => {
            cy.request(feedUrl(feeds[key])).then((response) => {
                expect(response.status, key).to.eq(200);
                expect(response.body, key).to.include("BEGIN:VCALENDAR");
            });
        });
    });

    it("is safe to run again", () => {
        runCutover();
        config("iMemberSignInFormerAccounts").then((value) => expect(Number(value)).to.eq(formerBefore + REMOVED));
        accountOf("administrator").should("have.length", 1);
        accountOf("readOnly").should("have.length", 1);
        signInRowOf("selfService").its("psi_CalendarToken").should("eq", feeds.selfService);
        signInRowOf("unconfirmed").its("psi_UnconfirmedEmail").should("eq", `unconfirmed.${tag}@example.com`);
        cy.makePrivateAdminAPICall("GET", `/api/timeline/person/${per.selfService}`, null, 200)
            .its("body.timeline")
            .then((timeline) => expect(timeline.filter((item) => item.type === "user")).to.have.length(1));
    });

    it("stops a feed once its person may no longer sign in", () => {
        cy.makePrivateAdminAPICall("POST", `/admin/api/person/${per.blocked}/sign-in/disable`, { reason: "Cutover spec" }, 200);
        feedStatus(feeds.blocked).should("eq", 404);
        cy.makePrivateAdminAPICall("POST", `/admin/api/person/${per.blocked}/sign-in/enable`, null, 200);
        feedStatus(feeds.blocked).should("eq", 200);
    });

    it("applies the member rule to a member's feed once member sign-in is on, but not to staff", () => {
        db(
            "INSERT INTO config_cfg (cfg_name, cfg_value) VALUES ('bMemberSignIn', '1'), ('sMemberSignInClassifications', '2') " +
                "ON DUPLICATE KEY UPDATE cfg_value = VALUES(cfg_value)",
        );
        feedStatus(feeds.selfService).should("eq", 404);
        feedStatus(feeds.readOnly).should("eq", 200);
        db("UPDATE config_cfg SET cfg_value = '1' WHERE cfg_name = 'sMemberSignInClassifications'");
        feedStatus(feeds.selfService).should("eq", 200);
    });

    it("serves the member's subscription from the sign-in row in the portal", () => {
        cy.signInAsMember(per.selfService);
        cy.request("/api/portal/calendar/subscription").then(({ body }) => {
            expect(body.url).to.include(feedUrl(feeds.selfService));
            expect(body.choices.find((choice) => choice.id === "calendar:1").selected).to.eq(true);
        });
    });
});
