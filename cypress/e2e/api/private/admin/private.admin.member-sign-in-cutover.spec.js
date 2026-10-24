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
            db("INSERT INTO person_signin_psi (psi_per_ID, psi_Language) VALUES (?, 'it_IT')", [per.blocked]);
            Object.entries({ dormantFlags: "xx_XX", unconfirmed: "es_MX", blocked: "de_DE", administrator: "de_DE" }).forEach(([key, locale]) => {
                db("INSERT INTO user_settings (user_id, setting_name, setting_value) VALUES (?, 'ui.locale', ?)", [per[key], locale]);
            });
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

    it("carries a self-service account's language to the sign-in row when 7.9.0 ships it", () => {
        signInRowOf("selfService").its("psi_Language").should("eq", "fr_FR");
        signInRowOf("unconfirmed").its("psi_Language").should("eq", "es_MX");
        signInRowOf("dormantFlags").its("psi_Language").should("be.null");
        signInRowOf("blocked").its("psi_Language").should("eq", "it_IT");
        signInRowOf("administrator").its("psi_Language").should("be.null");
        cy.then(() => db("SELECT setting_value AS locale FROM user_settings WHERE user_id = ? AND setting_name = 'ui.locale'", [per.administrator]))
            .its("0.locale")
            .should("eq", "de_DE");
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
        signInRowOf("selfService").its("psi_Language").should("eq", "fr_FR");
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
        cy.setSystemConfig("bMemberSignIn", "1");
        cy.setSystemConfig("sMemberSignInClassifications", "2");
        feedStatus(feeds.selfService).should("eq", 404);
        feedStatus(feeds.readOnly).should("eq", 200);
        cy.setSystemConfig("sMemberSignInClassifications", "1");
        feedStatus(feeds.selfService).should("eq", 200);
    });

    it("serves the member's subscription from the sign-in row in the portal", () => {
        cy.signInAsMember(per.selfService);
        cy.request("/api/portal/calendar/subscription").then(({ body }) => {
            expect(body.url).to.include(feedUrl(feeds.selfService));
            expect(body.choices.find((choice) => choice.id === "calendar:1").selected).to.eq(true);
        });
    });

    it("opens the portal in the language the removed account had chosen", () => {
        cy.signInAsMember(per.selfService);
        cy.request("/portal/").its("body").should("include", '<html lang="fr-FR"');
    });
});

// The account's id is its person's id, so the rows it wrote outlive it (design §12).
// They are written before the upgrade through the account's own sign-in where the
// application still allows it, and in SQL where only an older release wrote them.
describe("7.9.0 upgrade: what the removed self-service account leaves behind", () => {
    const VOLUNTEER = "/api/ministries";
    const prefix = `refs${Date.now()}`;
    const id = {};
    const seen = {};
    let authorSession = null;
    let formerCount = 0;
    let volunteerVersion = "v1";

    const admin = (method, url, body = null, status = 200) => {
        cy.clearCookies();
        return cy.makePrivateAdminAPICall(method, url, body, status);
    };
    const addPerson = (key) =>
        db(
            "INSERT INTO person_per (per_FirstName, per_LastName, per_Email, per_fmr_ID, per_cls_ID, per_fam_ID, per_DateEntered, per_EnteredBy) " +
                "VALUES (?, ?, ?, 1, 1, 0, NOW(), 1)",
            [key, prefix, `${key}.${prefix}@example.com`],
        ).then(({ insertId }) => {
            id[key] = insertId;
        });
    const isoDay = (offsetDays) => {
        const day = new Date();
        day.setDate(day.getDate() + offsetDays);
        return day.toISOString().slice(0, 10);
    };
    const timelineHeader = (personId, text) =>
        admin("GET", `/api/timeline/person/${personId}`).then(({ body }) => {
            const item = body.timeline.find((entry) => String(entry.text).startsWith(text));
            expect(item, `timeline note "${text}"`).to.not.eq(undefined);
            return item.header;
        });
    const auditNames = (personId, event) =>
        admin("GET", `/admin/api/member-portal/audit?personId=${personId}&event=${event}`).then(({ body }) => {
            expect(body.rows, `${event} audit row`).to.have.length(1);
            return { person: body.rows[0].personName, actor: body.rows[0].actorName };
        });
    const assignmentNames = () =>
        admin("GET", `${VOLUNTEER}/assignments/${id.assignment}`).then(({ body }) => ({
            assignee: body.assignment.displayName,
            responses: body.responses.map((row) => `${row.response}:${row.displayName}`),
        }));
    const teamLeaderName = () =>
        admin("GET", `${VOLUNTEER}/scopes?personId=${id.author}`).its("body.scopes").then((scopes) => {
            expect(scopes, "team leader scope").to.have.length(1);
            return scopes[0].personName;
        });
    const REFERENCES = [
        "note_nte WHERE nte_EnteredBy = ? OR nte_EditedBy = ?",
        "email_log_eml WHERE eml_usr_ID = ?",
        "portal_signin_audit_psa WHERE psa_per_ID = ? OR psa_Actor_usr_ID = ?",
        "login_as_person_session_lps WHERE lps_per_ID = ?",
        "volunteer_qualification_vqal WHERE vqal_per_ID = ?",
        "volunteer_scope_vscp WHERE vscp_per_ID = ?",
        "volunteer_assignment_vasg WHERE vasg_per_ID = ?",
        "volunteer_response_vrsp WHERE vrsp_per_ID = ?",
    ];
    const referenceCounts = () => {
        const counts = {};
        REFERENCES.forEach((from) => {
            db(`SELECT COUNT(*) AS n FROM ${from}`, from.match(/\?/g).map(() => id.author)).then(([row]) => {
                counts[from.split(" ")[0]] = Number(row.n);
            });
        });
        return cy.wrap(counts);
    };
    const historyCard = () =>
        cy.env(["admin.username", "admin.password"]).then((env) => {
            cy.clearCookies();
            cy.request({ method: "POST", url: "/admin/", form: true, body: { User: env["admin.username"], Password: env["admin.password"] } });
            return cy.request(`/people/view/${id.author}`).then(({ body }) => {
                const sessions = [...body.matchAll(/data-cy="login-as-person-session"[^>]*>\s*<div class="fw-medium">\s*([^<]+?)\s*<\/div>/g)];
                return sessions.map((match) => match[1]);
            });
        });

    before(() => {
        config("iMemberSignInFormerAccounts").then((value) => {
            formerCount = Number(value || 0);
        });
        config("sVolunteerVersion").then((value) => {
            volunteerVersion = value || "v1";
        });
        setConfig("sVolunteerVersion", "v2");
        addPerson("author");
        addPerson("subject");
        cy.then(() =>
            db(
                "INSERT INTO user_usr (usr_per_ID, usr_Password, usr_NeedPasswordChange, usr_UserName, usr_apiKey, usr_EditSelf, usr_Admin) " +
                    "VALUES (?, ?, 0, ?, ?, 1, 0)",
                [id.author, SHARED_CHANGEME_HASH, `author.${prefix}`, `${prefix}ApiKey`],
            ),
        );

        cy.then(() => {
            admin("POST", `${VOLUNTEER}/ministries`, { name: `${prefix} Hospitality`, description: "" }, 201).then(({ body }) => {
                id.ministry = body.ministry.id;
            });
        });
        cy.then(() => {
            admin("POST", `${VOLUNTEER}/ministries/${id.ministry}/teams`, { name: `${prefix} Greeters`, description: "" }, 201).then(({ body }) => {
                id.team = body.team.id;
            });
        });
        cy.then(() => {
            admin("POST", `${VOLUNTEER}/ministries/${id.ministry}/positions`, { name: `${prefix} Door`, description: "", teamId: id.team, order: 1 }, 201).then(
                ({ body }) => {
                    id.position = body.position.id;
                },
            );
            admin("POST", "/api/events", { Title: `${prefix} Service`, Type: 1, Start: `${isoDay(7)}T10:00:00`, End: `${isoDay(7)}T11:00:00`, PinnedCalendars: [] });
            admin("GET", "/api/events").then(({ body }) => {
                id.event = body.Events.find((event) => event.Title === `${prefix} Service`).Id;
            });
        });
        cy.then(() => {
            admin("POST", `${VOLUNTEER}/positions/${id.position}/qualifications`, { personId: id.author, notes: "" }, 201);
            admin("POST", `${VOLUNTEER}/scopes`, { personId: id.author, scopeType: "team", scopeId: id.team }, [200, 201]);
            admin(
                "POST",
                `${VOLUNTEER}/ministries/${id.ministry}/staffed-events`,
                { eventId: id.event, teamId: id.team, requirements: [{ positionId: id.position, minCount: 1, maxCount: 1 }] },
                201,
            ).then(({ body }) => {
                id.occurrence = body.occurrence.id;
            });
        });
        cy.then(() => {
            admin("POST", `${VOLUNTEER}/occurrences/${id.occurrence}/assignments`, { positionId: id.position, personId: id.author }, 201).then(({ body }) => {
                id.assignment = body.assignment.id;
            });
            admin("POST", `/admin/api/person/${id.author}/sign-in/disable`, { reason: "Cutover references spec" });
            admin("POST", `/admin/api/person/${id.author}/sign-in/enable`);
        });

        cy.then(() => {
            db(
                "INSERT INTO note_nte (nte_per_ID, nte_fam_ID, nte_Private, nte_Text, nte_DateEntered, nte_EnteredBy, nte_EditedBy, nte_Type) " +
                    "VALUES (?, 0, 0, 'Written by the account', '2026-02-01 10:00:00', ?, 0, 'note')",
                [id.subject, id.author],
            );
            db(
                "INSERT INTO note_nte (nte_per_ID, nte_fam_ID, nte_Private, nte_Text, nte_DateEntered, nte_DateLastEdited, nte_EnteredBy, nte_EditedBy, nte_Type) " +
                    "VALUES (?, 0, 0, 'Edited by the account', '2026-02-01 11:00:00', '2026-02-02 11:00:00', 1, ?, 'note')",
                [id.subject, id.author],
            );
            db(
                "INSERT INTO email_log_eml (eml_per_ID, eml_usr_ID, eml_Address, eml_Kind, eml_Subject, eml_Status, eml_DateSent) " +
                    "VALUES (?, ?, ?, 'composer', 'Sent by the account', 'sent', '2026-02-03 10:00:00')",
                [id.subject, id.author, `subject.${prefix}@example.com`],
            ).then(({ insertId }) => {
                id.email = insertId;
            });
            db(
                "INSERT INTO portal_signin_audit_psa (psa_Time, psa_per_ID, psa_Event, psa_Actor_usr_ID) VALUES ('2026-02-04 10:00:00', ?, 'email_changed', ?)",
                [id.subject, id.author],
            );
            db(
                "INSERT INTO login_as_person_session_lps (lps_Actor_usr_ID, lps_per_ID, lps_Started, lps_Ended, lps_EndReason) " +
                    "VALUES (1, ?, '2026-02-05 10:00:00', '2026-02-05 10:05:00', 'stop')",
                [id.author],
            ).then(({ insertId }) =>
                db("INSERT INTO login_as_person_action_lpa (lpa_lps_ID, lpa_Time, lpa_Method, lpa_Path, lpa_Status) VALUES (?, '2026-02-05 10:01:00', 'POST', '/api/portal/me', 200)", [
                    insertId,
                ]),
            );
        });

        cy.then(() => {
            cy.clearCookies();
            cy.request({ method: "POST", url: "/admin/", form: true, body: { User: `author.${prefix}`, Password: "changeme" } });
            cy.request({ method: "POST", url: `${VOLUNTEER}/me/assignments/${id.assignment}/respond`, body: { response: "accepted" } });
            cy.request("/portal/profile/edit").then(({ body }) => {
                const token = body.match(/name="csrf_token" value="([^"]+)"/)[1];
                cy.request({ method: "POST", url: "/api/portal/me", headers: { "X-CSRF-Token": token }, body: { cellPhone: "555-0142" } });
            });
            cy.getCookies().then((cookies) => {
                authorSession = cookies.find((cookie) => /^CRM-[0-9a-f]{32}$/.test(cookie.name));
                expect(authorSession, "the account's session cookie").to.not.eq(undefined);
            });
        });

        cy.then(() => {
            timelineHeader(id.subject, "Written by the account").then((header) => {
                seen.written = header;
            });
            timelineHeader(id.subject, "Edited by the account").then((header) => {
                seen.edited = header;
            });
            timelineHeader(id.author, "Edited via the Member Portal").then((header) => {
                seen.portalEdit = header;
            });
            admin("GET", `/api/email/log/${id.email}`).its("body.sentBy").then((sentBy) => {
                seen.sentBy = sentBy;
            });
            auditNames(id.author, "sign_in_disabled").then((names) => {
                seen.disabled = names;
            });
            auditNames(id.subject, "email_changed").then((names) => {
                seen.emailChanged = names;
            });
            assignmentNames().then((names) => {
                seen.assignment = names;
            });
            teamLeaderName().then((name) => {
                seen.teamLeader = name;
            });
            historyCard().then((sessions) => {
                seen.history = sessions;
            });
            referenceCounts().then((counts) => {
                seen.references = counts;
            });
        });

        runCutover();
    });

    after(() => {
        cy.clearCookies();
        if (id.ministry) {
            cy.makePrivateAdminAPICall("POST", `${VOLUNTEER}/ministries/${id.ministry}`, { active: false }, [200, 404]);
            cy.makePrivateAdminAPICall("DELETE", `${VOLUNTEER}/ministries/${id.ministry}`, null, [200, 404]);
        }
        if (id.event) {
            cy.cleanupEvents([id.event]);
        }
        if (id.email) {
            db("DELETE FROM email_log_eml WHERE eml_ID = ?", [id.email]);
        }
        const people = [id.author, id.subject].filter(Boolean);
        db(`DELETE FROM user_usr WHERE usr_per_ID IN (${people.map(() => "?").join(",")})`, people);
        cy.cleanupPeople(people);
        setConfig("iMemberSignInFormerAccounts", String(formerCount));
        setConfig("sVolunteerVersion", volunteerVersion);
    });

    it("removes the account and nothing that names it", () => {
        Object.entries(seen.references).forEach(([table, count]) => expect(count, table).to.be.above(0));
        db("SELECT COUNT(*) AS n FROM user_usr WHERE usr_per_ID = ?", [id.author]).its("0.n").should("eq", 0);
        referenceCounts().should("deep.eq", seen.references);
    });

    it("still names the account as the author of notes it wrote and edited", () => {
        expect(seen.written).to.include(prefix);
        expect(seen.edited).to.include(prefix);
        timelineHeader(id.subject, "Written by the account").should("eq", seen.written);
        timelineHeader(id.subject, "Edited by the account").should("eq", seen.edited);
    });

    it("still names the person who edited their details in the portal", () => {
        expect(seen.portalEdit).to.include(prefix);
        timelineHeader(id.author, "Edited via the Member Portal").should("eq", seen.portalEdit);
    });

    it("still names the account as the sender in the email history", () => {
        expect(seen.sentBy).to.include(prefix);
        admin("GET", `/api/email/log/${id.email}`).its("body.sentBy").should("eq", seen.sentBy);
        admin("GET", `/api/email/log?personId=${id.subject}`)
            .its("body.rows")
            .then((rows) => expect(rows.find((row) => row.id === id.email).sentBy).to.eq(seen.sentBy));
    });

    it("still names the person and the actor in the sign-in audit", () => {
        expect(seen.disabled.person).to.include(prefix);
        expect(seen.emailChanged.actor).to.include(prefix);
        auditNames(id.author, "sign_in_disabled").should("deep.eq", seen.disabled);
        auditNames(id.subject, "email_changed").should("deep.eq", seen.emailChanged);
    });

    it("keeps the volunteer assignment, its response and the team lead under the person's name", () => {
        expect(seen.assignment.assignee).to.include(prefix);
        expect(seen.assignment.responses).to.have.length(1);
        assignmentNames().should("deep.eq", seen.assignment);
        teamLeaderName().should("eq", seen.teamLeader);
        admin("GET", `${VOLUNTEER}/positions/${id.position}/qualifications`)
            .its("body.qualifications")
            .then((rows) => expect(rows.map((row) => row.personId)).to.include(id.author));
    });

    it("keeps the Login as Person history on the person's page", () => {
        expect(seen.history).to.have.length(1);
        expect(seen.history[0]).to.include(prefix);
        historyCard().should("deep.eq", seen.history);
    });

    it("signs the account's open session out on its next request", () => {
        cy.clearCookies();
        cy.setCookie(authorSession.name, authorSession.value, { httpOnly: true });
        cy.request({ url: "/portal/", followRedirect: false }).then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("/portal/login");
        });
    });

    it("lets the person carry on as a member with their assignment and their team", () => {
        cy.makeMemberAPICall(id.author, "GET", `${VOLUNTEER}/me/assignments`)
            .its("body.assignments")
            .then((rows) => expect(rows.find((row) => row.id === id.assignment).status).to.eq("accepted"));
        cy.makeMemberAPICall(id.author, "GET", `${VOLUNTEER}/teams/${id.team}/qualification-matrix`).its("body.teamId").should("eq", id.team);
    });
});
