/// <reference types="cypress" />

/**
 * Member sign-in row 9 (#10463): the Members tab's lists, the audit and the
 * Statistics tab on Admin → Member Portal (docs/member-sign-in-design.md §11.2).
 *
 * Seed people under the rule used here (roles 1-3, classifications 1-2, age 13):
 * 2 Mathew (head, email removed here), 6 Constance and 7 Marion (made to share an
 * email), 14 Nathan (an unconfirmed moved address), 34 Alvin, 57 Jon and 92 Bruce
 * (heads with their own email). 95 Judith has a staff account and opens the portal from it.
 * The upgrade's family email moves, old audit rows and past sign-ins are support
 * fixtures; the people's emails and sign-in rows are set up and put back in SQL.
 */

import { addOldAuditRows, ageFamilyConfirmations, recordFamilyEmailMoves, recordPastSignIn } from "../../../../support/member-sign-in-fixtures";

const SETTINGS_URL = "/admin/api/member-portal/sign-in";
const RULE = { sMemberSignInFamilyRoles: [1, 2, 3], sMemberSignInClassifications: [1, 2], iMemberSignInMinAge: 13 };
const RULE_KEYS = Object.keys(RULE);
const MARK = "row09-lists";

const db = (sql, params = []) =>
    cy.dbQuery(sql, params).then((result) => {
        expect(result.error, sql).to.be.null;
        return result.rows;
    });

const list = (name, params = {}) => {
    const query = new URLSearchParams({ length: "100", ...params }).toString();
    return cy.makePrivateAdminAPICall("GET", `/admin/api/member-portal/lists/${name}?${query}`, null, 200).its("body");
};
const ids = (body) => body.rows.map((row) => row.personId);
const summary = () => cy.makePrivateAdminAPICall("GET", "/admin/api/member-portal/lists", null, 200).its("body");
const stats = () => cy.makePrivateAdminAPICall("GET", "/admin/api/member-portal/stats", null, 200).its("body");
const audit = (params = {}) =>
    cy.makePrivateAdminAPICall("GET", `/admin/api/member-portal/audit?${new URLSearchParams(params).toString()}`, null, 200).its("body");

/** A family confirms its details through the emailed link, with a message. */
const confirmFamilyByEmailLink = (familyId, message) =>
    cy.makePrivateAdminAPICall("GET", `/api/family/${familyId}/verify/url`, null, 200).then(({ body }) => {
        const path = new URL(body.url).pathname.replace(/^.*\/external\//, "/external/");
        cy.request({ method: "POST", url: path, form: true, body: { message } }).its("status").should("eq", 200);
    });

const resetFixtures = () => {
    db("UPDATE person_per SET per_Email = 'mathew.campbell@example.com' WHERE per_ID = 2");
    db("UPDATE person_per SET per_Email = 'constance.hart@example.com' WHERE per_ID = 6");
    db("UPDATE person_per SET per_Email = 'marion.sutton@example.com' WHERE per_ID = 7");
    db("DELETE FROM person_signin_psi WHERE psi_per_ID IN (14, 34, 57, 92, 95)");
    db("DELETE FROM family_email_move_fem WHERE fem_Email LIKE ?", [`${MARK}%`]);
    db("DELETE FROM note_nte WHERE nte_Text = ?", [MARK]);
    db("DELETE FROM portal_signin_audit_psa WHERE psa_Detail = ? OR psa_Email LIKE ?", [MARK, `${MARK}%`]);
};

describe("Admin → Member Portal lists, audit and statistics", () => {
    const saved = {};

    before(() => {
        cy.makePrivateAdminAPICall("GET", SETTINGS_URL, null, 200).then(({ body }) => {
            RULE_KEYS.forEach((key) => {
                saved[key] = body.values[key];
            });
        });
        cy.makePrivateAdminAPICall("POST", SETTINGS_URL, RULE, 200);
        resetFixtures();
        db("UPDATE person_per SET per_Email = NULL WHERE per_ID = 2");
        db("UPDATE person_per SET per_Email = ' Shared.Pair@example.com' WHERE per_ID = 6");
        db("UPDATE person_per SET per_Email = 'shared.pair@example.com ' WHERE per_ID = 7");
        db(
            `INSERT INTO person_signin_psi (psi_per_ID, psi_UnconfirmedEmail, psi_UnconfirmedSource, psi_UnconfirmedAt, psi_LastSignIn, psi_LastMethod)
             VALUES (14, 'NATHAN.LEWIS@example.com', 'family_email', NOW(), NOW() - INTERVAL 3 DAY, 'email_code'),
                    (34, NULL, NULL, NULL, NOW() - INTERVAL 40 DAY, 'email_link'),
                    (57, NULL, NULL, NULL, NOW() - INTERVAL 3 DAY, 'passkey')`,
        );
        db("UPDATE user_usr SET usr_LastPortalActivity = NULL WHERE usr_per_ID = 95");
    });

    after(() => {
        resetFixtures();
        cy.makePrivateAdminAPICall("POST", SETTINGS_URL, saved, 200);
    });

    it("lists who may sign in now: the rule and an email nobody else uses that is not unconfirmed", () => {
        list("may-sign-in").then((body) => {
            expect(body.total).to.eq(body.rows.length);
            expect(ids(body)).to.include.members([34, 57, 92]);
            expect(ids(body)).to.not.include.members([2, 6, 7, 14]);
            expect(ids(body)).to.not.include(4);
            const jon = body.rows.find((row) => row.personId === 57);
            expect(jon.familyName).to.eq("Olson");
            expect(jon.email).to.eq("jon.olson@example.com");
            expect(jon.lastMethodLabel).to.eq("Passkey");
        });
    });

    it("lists who signed in during the last 30 days and who may sign in but never has", () => {
        list("signed-in-recently").then((body) => {
            expect(ids(body)).to.include.members([14, 57]);
            expect(ids(body)).to.not.include(34);
        });
        list("never-signed-in").then((body) => {
            expect(ids(body)).to.include(92);
            expect(ids(body)).to.not.include.members([34, 57, 14, 2]);
        });
    });

    it("lists the emails that need attention, with the problem, who shares it and where it came from", () => {
        list("email-attention").then((body) => {
            const byId = Object.fromEntries(body.rows.map((row) => [row.personId, row]));
            expect(Object.keys(byId).map(Number)).to.include.members([2, 6, 7, 14]);
            expect(byId[2].problem).to.eq("no_email");
            expect(byId[6].problem).to.eq("shared_email");
            expect(byId[6].sharedWith.map((other) => other.personId)).to.deep.eq([7]);
            expect(byId[7].sharedWith.map((other) => other.personId)).to.deep.eq([6]);
            expect(byId[14].problem).to.eq("email_unconfirmed");
            expect(byId[14].unconfirmedSource).to.eq("family_email");
            expect(byId[14].unconfirmedSourceLabel).to.eq("Moved from the family email");
            expect(byId[14].problemLabel).to.eq("Their email is not confirmed yet");
            expect(byId[14].canConfirmEmail).to.eq(true);
            expect(byId[2].canConfirmEmail).to.eq(false);
        });
        list("email-attention", { problem: "shared_email" }).then((body) => {
            expect(ids(body).sort()).to.deep.eq([6, 7]);
        });
        list("email-attention", { search: "Lewis" }).then((body) => {
            expect(ids(body)).to.deep.eq([14]);
            expect(body.filtered).to.eq(1);
            expect(body.total).to.be.at.least(4);
        });
        summary().then((body) => {
            expect(body.emailProblems.no_email).to.be.at.least(1);
            expect(body.emailProblems.shared_email).to.be.at.least(2);
            expect(body["email-attention"]).to.eq(
                body.emailProblems.no_email + body.emailProblems.shared_email + body.emailProblems.email_unconfirmed,
            );
        });
    });

    it("agrees with each person's own sign-in status", () => {
        [
            [2, "no_email"],
            [6, "shared_email"],
            [14, "email_unconfirmed"],
            [92, null],
        ].forEach(([personId, reason]) => {
            cy.makePrivateAdminAPICall("GET", `/api/person/${personId}/sign-in`, null, 200).its("body.reason").should("eq", reason);
        });
    });

    it("pages and searches a list on the server", () => {
        list("may-sign-in", { length: "2", start: "0" }).then((first) => {
            expect(first.rows).to.have.length(2);
            list("may-sign-in", { length: "2", start: "2" }).then((second) => {
                expect(second.total).to.eq(first.total);
                expect(ids(second)).to.not.include.members(ids(first));
            });
        });
        list("may-sign-in", { search: "Bruce Kennedy" }).then((body) => {
            expect(ids(body)).to.deep.eq([92]);
        });
        cy.makePrivateAdminAPICall("GET", "/admin/api/member-portal/lists/no-such-list", null, 400);
        cy.makePrivateAdminAPICall("GET", "/admin/api/member-portal/lists/email-attention?problem=nope", null, 400);
    });

    it("lists the family emails the upgrade could not move, with the counts by outcome", () => {
        recordFamilyEmailMoves([
            { familyId: 1, email: `${MARK}-head@example.com`, outcome: "head", personId: 2, needsConfirm: true },
            { familyId: 2, email: `${MARK}-spouse@example.com`, outcome: "spouse", personId: 7, needsConfirm: true },
            { familyId: 3, email: `${MARK}-held@example.com`, outcome: "held", personId: 14 },
            { familyId: 7, email: `${MARK}-both@example.com`, outcome: "noted", reason: "both_have_email" },
            { familyId: 12, email: `${MARK}-other@example.com`, outcome: "noted", reason: "other_person", personId: 92 },
        ]);
        summary().then((body) => {
            expect(body["family-emails-not-moved"]).to.eq(2);
            expect(body.familyEmailMoves).to.include({ head: 1, spouse: 1, held: 1, noted: 2, total: 5 });
        });
        list("family-emails-not-moved").then((body) => {
            expect(body.total).to.eq(2);
            const byFamily = Object.fromEntries(body.rows.map((row) => [row.familyId, row]));
            expect(byFamily[7].reasonLabel).to.eq("The head of household and spouse both have their own email");
            expect(byFamily[7].familyName).to.eq("Stewart");
            expect(byFamily[12].reason).to.eq("other_person");
            expect(byFamily[12].holderId).to.eq(92);
            expect(byFamily[12].holderName).to.eq("Bruce Kennedy");
        });
    });

    it("lists the families confirmed in the last 30 days by the emailed link", () => {
        confirmFamilyByEmailLink(12, MARK);
        confirmFamilyByEmailLink(19, MARK);
        ageFamilyConfirmations(19, MARK, 45);
        list("families-confirmed").then((body) => {
            const families = body.rows.map((row) => row.familyId);
            expect(families).to.include(12);
            expect(families).to.not.include(19);
            const olson = body.rows.find((row) => row.familyId === 12);
            expect(olson.via).to.eq("email_link");
            expect(olson.personId).to.be.null;
            expect(olson.result).to.eq("change-needed");
        });
    });

    it("filters the audit by person, event and dates, newest first, with nothing secret in a row", () => {
        cy.makePrivateAdminAPICall("POST", "/admin/api/person/92/sign-in/disable", { reason: "Lists spec" }, 200);
        cy.makePrivateAdminAPICall("POST", "/admin/api/person/92/sign-in/enable", null, 200);
        addOldAuditRows([
            { daysAgo: 400, event: "refused", method: "email_code", reason: "no_match", email: `${MARK}@example.com`, ip: "10.0.0.9", detail: MARK },
        ]);

        audit({ personId: 92 }).then((body) => {
            expect(body.rows.map((row) => row.event).slice(0, 2)).to.deep.eq(["sign_in_enabled", "sign_in_disabled"]);
            expect(body.rows[0].eventLabel).to.eq("Sign-in enabled");
            expect(body.rows[0].actorId).to.eq(1);
            expect(body.rows[0].actorName).to.eq("Church Admin");
            expect(body.rows.every((row) => row.personId === 92)).to.eq(true);
            expect(JSON.stringify(body)).to.not.contain("Lists spec");
        });
        audit({ personId: 92, event: "sign_in_disabled" }).then((body) => {
            expect(body.rows).to.have.length.at.least(1);
            expect(body.rows.every((row) => row.event === "sign_in_disabled")).to.eq(true);
        });

        const day = (offset) => {
            const date = new Date(Date.now() + offset * 86400000);
            return date.toISOString().slice(0, 10);
        };
        audit({ event: "refused", to: day(-300) }).then((body) => {
            const row = body.rows.find((candidate) => candidate.email === `${MARK}@example.com`);
            expect(row, "the old refusal").to.exist;
            expect(row.personId).to.be.null;
            expect(row.reasonLabel).to.eq("No person has this email");
            expect(row.methodLabel).to.eq("Email code");
            expect(Object.keys(row).sort()).to.deep.eq(
                [
                    "actorId",
                    "actorName",
                    "detail",
                    "email",
                    "event",
                    "eventLabel",
                    "id",
                    "method",
                    "methodLabel",
                    "personId",
                    "personName",
                    "reason",
                    "reasonLabel",
                    "summary",
                    "time",
                    "timeDisplay",
                ].sort(),
            );
        });
        audit({ event: "refused", from: day(-30) }).then((body) => {
            expect(body.rows.map((row) => row.email)).to.not.include(`${MARK}@example.com`);
        });
        audit({ length: "1" }).then((body) => {
            expect(body.rows).to.have.length(1);
            expect(body.filtered).to.eq(body.total);
            expect(body.events.map((event) => event.value)).to.include.members(["refused", "grant_added", "family_confirmed"]);
            expect(JSON.stringify(body.rows)).to.not.match(/[0-9a-f]{64}/);
        });

        addOldAuditRows([
            { daysAgo: 401, event: "refused", method: "email_code", reason: "shared_email", email: `${MARK}-shared@example.com`, detail: "matched:6,7" },
            { daysAgo: 402, personId: 92, event: "email_requested", method: "email_code", email: `${MARK}-send@example.com`, detail: "send_failed" },
        ]);
        audit({ to: day(-300), length: "100" }).then((body) => {
            const byEmail = Object.fromEntries(body.rows.map((row) => [row.email, row]));
            expect(byEmail[`${MARK}-shared@example.com`].summary).to.eq("Matches: Constance Hart, Marion Hart");
            expect(byEmail[`${MARK}-send@example.com`].summary).to.eq("The email could not be sent.");
        });

        cy.makePrivateAdminAPICall("GET", "/admin/api/member-portal/audit?from=2026-02-30", null, 400);
        cy.makePrivateAdminAPICall("GET", "/admin/api/member-portal/audit?from=2026-05-02&to=2026-05-01", null, 400);
        cy.makePrivateAdminAPICall("GET", "/admin/api/member-portal/audit?event=nope", null, 400);
    });

    it("reads Statistics from the person sign-in rows, and stops stamping the account", () => {
        stats().then((before) => {
            recordPastSignIn(92, 20, "device");
            cy.request({ method: "POST", url: "/admin/", form: true, body: { User: "judith.matthews@example.com", Password: "noMoney$" } });
            cy.request("/portal/");

            stats().then((after) => {
                expect(after.activeNow - before.activeNow, "active now").to.eq(1);
                expect(after.signedIn24Hours - before.signedIn24Hours, "24 hours").to.eq(1);
                expect(after.signedIn7Days - before.signedIn7Days, "7 days").to.eq(1);
                expect(after.signedIn30Days - before.signedIn30Days, "30 days").to.eq(2);
                expect(after.recentSignIns[0].personId).to.eq(95);
                expect(after.recentSignIns[0].method).to.eq("staff");
                expect(after.recentSignIns[0].lastActivity).to.match(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/);
                list("may-sign-in", { length: "1" }).its("total").should("eq", after.maySignIn);
                list("never-signed-in", { length: "1" }).its("total").should("eq", after.neverSignedIn);
                expect(after.neverSignedIn - before.neverSignedIn, "92 has signed in now").to.eq(-1);
            });
            db("SELECT usr_LastPortalActivity AS stamp FROM user_usr WHERE usr_per_ID = 95").then((rows) => {
                expect(rows[0].stamp).to.be.null;
            });
        });
    });

    it("is for administrators only", () => {
        cy.makePrivateUserAPICall("GET", "/admin/api/member-portal/lists", null, 403);
        cy.makePrivateEditRecordsAPICall("GET", "/admin/api/member-portal/lists/may-sign-in", null, 403);
        cy.makePrivateEditRecordsAPICall("GET", "/admin/api/member-portal/audit", null, 403);
    });
});
