/// <reference types="cypress" />

import { markEmailUnconfirmed, restorePeople, setPersonEmails } from "../../../../support/sign-in-email-fixtures";
import { waitForMail } from "../../../../support/member-sign-in";

/**
 * Member sign-in row 9b (#10463): Fix shared emails and Confirm selected on
 * "Email needs attention" (docs/member-sign-in-design.md §4, §9, §11.2).
 * The member rule is opened to everyone, so every living, active person who
 * shares an address blocks it. judith.matthews (95) has Edit Records and is
 * not an administrator; Marion Hart (7) has a staff account.
 */

const API = "/api/persons/sign-in-email";
const SETTINGS_URL = "/admin/api/member-portal/sign-in";
const RULE_KEYS = ["sMemberSignInFamilyRoles", "sMemberSignInClassifications", "iMemberSignInMinAge"];

const SEED = {
    6: "constance.hart@example.com",
    7: "marion.sutton@example.com",
    26: "paul.robertson@example.com",
    28: "rafael.dixon@example.com",
    48: "kurt.hernandez@example.com",
    49: "lorraine.craig@example.com",
    50: "sherri.gordon@example.com",
    59: "franklin.beck@example.com",
    76: "leroy.larson@example.com",
    77: "natalie.lynch@example.com",
    79: "bradley.spencer@example.com",
    80: "judy.douglas@example.com",
    92: "bruce.kennedy@example.com",
    93: "katie.hoffman@example.com",
};

const asAdmin = (method, url, body, status = 200) => cy.makePrivateAdminAPICall(method, url, body, status);
const asEditRecords = (method, url, body, status = 200) => cy.makePrivateEditRecordsAPICall(method, url, body, status);

const planRow = (address, caller = asAdmin) =>
    caller("GET", `${API}/shared`).then(({ body }) => body.rows.find((row) => row.address === address) ?? null);

const fixRow = (row) => ({ email: row.address, keeperId: row.keeper.personId, loserIds: row.losers.map((person) => person.personId) });

const auditFor = (personId, event) =>
    asAdmin("GET", `/admin/api/member-portal/audit?personId=${personId}&event=${event}&length=5`).its("body.rows");

const statusOf = (personId) => asAdmin("GET", `/api/person/${personId}/sign-in`).its("body");

describe("Email needs attention: Fix shared emails and Confirm selected", () => {
    const saved = {};

    before(() => {
        asAdmin("GET", SETTINGS_URL).then(({ body }) => {
            RULE_KEYS.forEach((key) => {
                saved[key] = body.values[key];
            });
            asAdmin("POST", SETTINGS_URL, {
                sMemberSignInFamilyRoles: [0, ...body.familyRoles.map((role) => role.id)],
                sMemberSignInClassifications: [0, ...body.classifications.map((classification) => classification.id)],
                iMemberSignInMinAge: 0,
            });
        });
    });

    beforeEach(() => restorePeople(SEED));

    after(() => {
        restorePeople(SEED);
        asEditRecords("POST", "/api/person/93/role/2", null);
        asAdmin("POST", SETTINGS_URL, saved);
    });

    it("proposes the head of household, else the spouse, the oldest first", () => {
        setPersonEmails({ 76: "larson.home@example.com", 77: "larson.home@example.com" });
        setPersonEmails({ 79: "cooper.home@example.com", 80: "cooper.home@example.com" });
        setPersonEmails({ 92: "kennedy.home@example.com", 93: "kennedy.home@example.com" });
        asEditRecords("POST", "/api/person/93/role/1", null);

        planRow("larson.home@example.com").then((row) => {
            expect(row.status).to.eq("fix");
            expect(row.familyName).to.eq("Larson");
            expect(row.keeper.personId, "the head, though the spouse is older").to.eq(76);
            expect(row.losers.map((person) => person.personId)).to.deep.eq([77]);
            expect(row.canApply).to.eq(true);
        });
        planRow("cooper.home@example.com").then((row) => {
            expect(row.keeper.personId, "the spouse when no head shares it").to.eq(79);
            expect(row.losers.map((person) => person.personId)).to.deep.eq([80]);
        });
        planRow("kennedy.home@example.com").then((row) => {
            expect(row.keeper.personId, "the older of two heads").to.eq(93);
            expect(row.losers.map((person) => person.personId)).to.deep.eq([92]);
        });
        asEditRecords("POST", "/api/person/93/role/2", null);
    });

    it("lists cross-family and no-adult addresses to fix by hand and never changes them", () => {
        setPersonEmails({ 26: "two.families@example.com", 50: "two.families@example.com" });
        setPersonEmails({ 48: "diaz.children@example.com", 49: "diaz.children@example.com" });

        planRow("two.families@example.com").then((row) => {
            expect(row.status).to.eq("by_hand");
            expect(row.reason).to.eq("families");
            expect(row.keeper).to.be.null;
            expect(row.people.map((person) => person.personId).sort()).to.deep.eq([26, 50]);
        });
        planRow("diaz.children@example.com").then((row) => {
            expect(row.status).to.eq("by_hand");
            expect(row.reason).to.eq("no_adult");
            expect(row.canApply).to.eq(false);
        });

        asAdmin("POST", `${API}/shared/fix`, {
            rows: [
                { email: "two.families@example.com", keeperId: 26, loserIds: [50] },
                { email: "diaz.children@example.com", keeperId: 48, loserIds: [49] },
            ],
        }).then(({ body }) => {
            expect(body.changed).to.have.length(0);
            expect(body.refused.map((row) => row.email)).to.deep.eq(["two.families@example.com", "diaz.children@example.com"]);
            expect(body.peopleChanged).to.eq(0);
        });
        planRow("two.families@example.com").its("people").should("have.length", 2);
        planRow("diaz.children@example.com").its("people").should("have.length", 2);
    });

    it("leaves a row with a staff account or given permissions to an administrator, on the server", () => {
        setPersonEmails({ 6: "hart.home@example.com", 7: "hart.home@example.com" });
        setPersonEmails({ 76: "larson.home@example.com", 77: "larson.home@example.com" });
        asAdmin("POST", "/admin/api/person/77/sign-in/grants", { capability: "portal.family.contacts.view", scopeId: 3, reason: "Row 9b spec" }, 201);

        planRow("hart.home@example.com", asEditRecords).then((hart) => {
            expect(hart.keeper.personId).to.eq(6);
            expect(hart.needsAdministrator, "Marion has a staff account").to.eq(true);
            expect(hart.canApply).to.eq(false);
            expect(hart.refusal).to.contain("Only an administrator");
            planRow("larson.home@example.com", asEditRecords).then((larson) => {
                expect(larson.canApply, "the loser has a given permission").to.eq(false);
                asEditRecords("POST", `${API}/shared/fix`, { rows: [fixRow(hart), fixRow(larson)] }).then(({ body }) => {
                    expect(body.changed).to.have.length(0);
                    expect(body.refused).to.have.length(2);
                    expect(body.refused[0].reason).to.contain("Only an administrator");
                });
                planRow("hart.home@example.com").its("losers").should("have.length", 1);

                asAdmin("POST", `${API}/shared/fix`, { rows: [fixRow(hart), fixRow(larson)] }).then(({ body }) => {
                    expect(body.changed.map((row) => row.email)).to.deep.eq(["hart.home@example.com", "larson.home@example.com"]);
                    expect(body.peopleChanged).to.eq(2);
                });
            });
        });
        planRow("hart.home@example.com").should("be.null");
        statusOf(7).its("reason").should("eq", "no_email");
    });

    it("clears the address of everyone but the keeper, audits it, and keeps the keeper's confirmation", () => {
        setPersonEmails({ 79: "cooper.home@example.com", 80: "cooper.home@example.com" });
        markEmailUnconfirmed(79);

        planRow("cooper.home@example.com", asEditRecords).then((row) => {
            asEditRecords("POST", `${API}/shared/fix`, { rows: [{ ...fixRow(row), keeperId: 80 }] }).then(({ body }) => {
                expect(body.skipped, "a keeper that is not the preview's").to.have.length(1);
                expect(body.skipped[0].reason).to.contain("have changed");
            });
            asEditRecords("POST", `${API}/shared/fix`, { rows: [{ ...fixRow(row), email: "Cooper.Home@example.com" }] }).then(({ body }) => {
                expect(body.changed).to.have.length(1);
                expect(body.changed[0].losers[0].personId).to.eq(80);
                expect(body.peopleChanged).to.eq(1);
            });
        });

        statusOf(80).its("reason").should("eq", "no_email");
        statusOf(79).then((status) => {
            expect(status.reason, "the kept address still needs confirming").to.eq("email_unconfirmed");
            expect(status.unconfirmedEmail).to.eq("cooper.home@example.com");
        });
        auditFor(80, "email_changed").then((rows) => {
            expect(rows[0].actorId).to.eq(95);
            expect(rows[0].detail).to.contain("from=cooper.home@example.com").and.contain("via=shared_email_fix");
        });
        asEditRecords("POST", `${API}/shared/fix`, { rows: [{ email: "cooper.home@example.com", keeperId: 79, loserIds: [80] }] })
            .its("body.skipped.0.reason")
            .should("contain", "no longer shared");
    });

    it("signs out a person who loses an address they signed in with, and tells the address", () => {
        cy.signInAsMember(77);
        cy.makeMemberAPICall(77, "GET", "/api/portal/me", null, 200);
        cy.clearCookies();
        setPersonEmails({ 76: SEED[77] });

        waitForMail(SEED[77]).then((before) => {
            planRow(SEED[77]).then((row) => {
                expect(row.losers[0]).to.include({ personId: 77, signedIn: true });
                asAdmin("POST", `${API}/shared/fix`, { rows: [fixRow(row)] }).its("body.peopleChanged").should("eq", 1);
            });
            waitForMail(SEED[77], before.length + 1).then((messages) => {
                expect(messages.some((message) => (message.Subject || "").includes("Your email address was changed"))).to.eq(true);
            });
        });
        cy.makeMemberAPICall(77, "GET", "/api/portal/me", null, 401);
        cy.forgetMemberSessions();
        auditFor(77, "email_changed").its("0.detail").should("contain", "via=shared_email_fix").and("contain", "devices=");
    });

    it("confirms in bulk what each caller may confirm, and audits it as a bulk confirmation", () => {
        [76, 59, 28].forEach((personId) => markEmailUnconfirmed(personId));
        markEmailUnconfirmed(77, "staff");
        asAdmin("POST", "/admin/api/person/28/sign-in/grants", { capability: "portal.family.contacts.view", scopeId: 3, reason: "Row 9b spec" }, 201);
        const rows = [76, 59, 28, 77].map((personId) => ({ personId, email: SEED[personId] }));

        asEditRecords("GET", `${API}/attention?problem=email_unconfirmed&kind=family_email&length=100`).then(({ body }) => {
            const ids = body.rows.map((row) => row.personId);
            expect(ids).to.include.members([76, 59, 28]).and.not.include(77);
            expect(body.rows.find((row) => row.personId === 28).canConfirmEmail).to.eq(false);
        });
        asEditRecords("GET", `${API}/attention?problem=email_unconfirmed&kind=replacement&length=100`)
            .its("body.rows")
            .then((list) => expect(list.map((row) => row.personId)).to.include(77).and.not.include(76));
        asEditRecords("GET", `${API}/attention/counts`).its("body.unconfirmedKinds.replacement").should("be.at.least", 1);

        asEditRecords("POST", `${API}/confirm`, { rows: [...rows, { personId: 80, email: SEED[80] }] }).then(({ body }) => {
            expect(body.confirmed.map((row) => row.personId)).to.deep.eq([76, 59]);
            expect(body.refused.map((row) => row.personId)).to.deep.eq([28, 77]);
            expect(body.refused[1].reason).to.contain("replaced one the person signed in with");
            expect(body.skipped.map((row) => row.personId), "nothing to confirm").to.deep.eq([80]);
        });
        auditFor(76, "email_confirmed").then((audit) => {
            expect(audit[0].actorId).to.eq(95);
            expect(audit[0].detail).to.contain("via=bulk_confirm").and.contain("source=family_email");
        });

        asAdmin("POST", `${API}/confirm`, { rows: [{ personId: 28, email: "someone.else@example.com" }] })
            .its("body.skipped.0.reason")
            .should("contain", "has changed");
        asAdmin("POST", `${API}/confirm`, { rows: rows.slice(2) }).its("body.confirmed").should("have.length", 2);
        statusOf(77).its("unconfirmedEmail").should("be.null");
    });

    it("refuses malformed requests and staff without Edit Records", () => {
        asAdmin("POST", `${API}/confirm`, { rows: [] }, 400);
        asAdmin("POST", `${API}/confirm`, { rows: [{ personId: "76", email: SEED[76] }] }, 400);
        asAdmin("POST", `${API}/confirm`, { rows: Array.from({ length: 501 }, (_, i) => ({ personId: i + 1, email: "x@example.com" })) }, 400);
        asAdmin("POST", `${API}/shared/fix`, { rows: [{ email: "a@example.com", keeperId: 1 }] }, 400);
        asAdmin("GET", `${API}/attention?problem=email_unconfirmed&kind=other`, null, 400);
        cy.makePrivateNoPermAPICall("GET", `${API}/shared`, null, 403);
        cy.makePrivateNoPermAPICall("POST", `${API}/confirm`, { rows: [{ personId: 76, email: SEED[76] }] }, 403);
    });
});
