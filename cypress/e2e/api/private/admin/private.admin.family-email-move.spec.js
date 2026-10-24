/// <reference types="cypress" />

import { pdfText } from "../../../../support/pdf-text";

/**
 * Member sign-in row 11: the 7.9.0 upgrade moves each family email to a person
 * (design §10.1). The application no longer writes a family email, so the
 * fixtures are written in SQL; the test then runs the shipped block of
 * src/mysql/upgrade/7.9.0.sql (task db:upgradeBlock) and reads the result
 * back through the API wherever there is one.
 */
const MOVE = { script: "7.9.0.sql", block: "member sign-in row 11, family email moves to people" };
const tag = `fem${Date.now()}`;
const addr = (name) => `${name}.${tag}@example.com`;

const familyIds = [];
const fam = {};
const per = {};

const insertId = ({ rows, error }) => {
    expect(error).to.be.null;
    return rows.insertId;
};

const addFamily = (key, email) =>
    cy
        .dbQuery("INSERT INTO family_fam (fam_Name, fam_Email, fam_DateEntered, fam_EnteredBy) VALUES (?, ?, NOW(), 1)", [
            `${key} ${tag}`,
            email,
        ])
        .then(insertId)
        .then((id) => {
            fam[key] = id;
            familyIds.push(id);
        });

const addPerson = (key, familyKey, role, email, { year = null, deceased = null, inactive = null } = {}) =>
    cy
        .then(() =>
            cy.dbQuery(
                "INSERT INTO person_per (per_FirstName, per_LastName, per_fam_ID, per_fmr_ID, per_Email, per_BirthYear, " +
                    "per_DateDeceased, per_DateDeactivated, per_DateEntered, per_EnteredBy) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)",
                [key, tag, fam[familyKey], role, email, year, deceased, inactive],
            ),
        )
        .then(insertId)
        .then((id) => {
            per[key] = id;
        });

const runMove = () => cy.task("db:upgradeBlock", MOVE).its("error").should("be.null");

// Ids are read when the command runs, so a family added earlier in the same test is found.
const moveOf = (familyKey) =>
    cy
        .then(() =>
            cy.dbQuery(
                "SELECT fem_Email AS email, fem_Outcome AS outcome, fem_Reason AS reason, fem_per_ID AS personId, " +
                    "fem_NeedsConfirm AS needsConfirm FROM family_email_move_fem WHERE fem_fam_ID = ? ORDER BY fem_ID",
                [fam[familyKey]],
            ),
        )
        .its("rows");

const emailOf = (personKey) => cy.makePrivateAdminAPICall("GET", `/api/person/${per[personKey]}`).its("body.Email");

const movedNotes = (familyKey) =>
    cy
        .then(() => cy.makePrivateAdminAPICall("GET", `/api/timeline/family/${fam[familyKey]}`))
        .then((resp) => resp.body.timeline.filter((item) => item.type === "family-email"));

const expectNote = (familyKey, text) => {
    movedNotes(familyKey).then((notes) => {
        expect(notes).to.have.length(1);
        expect(notes[0].text).to.eq(text);
        expect(notes[0].header).to.include("System Upgrade");
    });
};

describe("7.9.0 upgrade: family email moves to people", () => {
    before(() => {
        addFamily("Head", addr("head"));
        addPerson("headOlder", "Head", 1, null, { year: 1950 });
        addPerson("headYounger", "Head", 1, "", { year: 1980 });
        addPerson("headSpouse", "Head", 2, null, { year: 1940 });

        addFamily("Spouse", addr("spouse"));
        addPerson("spouseHead", "Spouse", 1, addr("spouse-head"), { year: 1960 });
        addPerson("spouseSpouse", "Spouse", 2, null, { year: 1962 });

        addFamily("Both", addr("both"));
        addPerson("bothHead", "Both", 1, addr("both-head"));
        addPerson("bothSpouse", "Both", 2, addr("both-spouse"));

        addFamily("NoAdult", addr("noadult"));
        addPerson("noAdultDeceasedHead", "NoAdult", 1, null, { deceased: "2020-01-01" });
        addPerson("noAdultChild", "NoAdult", 3, null, { year: 2012 });

        addFamily("Held", `  ${addr("held").toUpperCase()} `);
        addPerson("heldHead", "Held", 1, null);
        addPerson("heldChild", "Held", 3, addr("held"));

        addFamily("Elsewhere", addr("elsewhere"));
        addPerson("elsewhereHead", "Elsewhere", 1, null);
        addFamily("Holder", null);
        addPerson("holder", "Holder", 1, addr("elsewhere"));

        addFamily("InactiveHead", addr("inactive"));
        addPerson("inactiveHead", "InactiveHead", 1, null, { year: 1940, inactive: "2024-01-01" });
        addPerson("inactiveSpouse", "InactiveHead", 2, null, { year: 1945 });

        addFamily("Solo", addr("solo"));
        addPerson("solo", "Solo", 1, null);
        addPerson("soloDeceasedChild", "Solo", 3, null, { deceased: "2021-01-01" });

        addFamily("SharedFirst", addr("shared"));
        addPerson("sharedFirstHead", "SharedFirst", 1, null);
        addFamily("SharedSecond", addr("shared").toUpperCase());
        addPerson("sharedSecondHead", "SharedSecond", 1, null);

        runMove();
    });

    after(() => {
        cy.dbQuery(`DELETE FROM family_email_move_fem WHERE fem_fam_ID IN (${familyIds.map(Number).join(",")})`);
        cy.cleanupFamilies(familyIds);
    });

    it("copies the address to the head of household, the oldest first", () => {
        emailOf("headOlder").should("eq", addr("head"));
        emailOf("headYounger").should("eq", "");
        emailOf("headSpouse").should("eq", null);
        moveOf("Head").then(([move]) => {
            expect(move).to.include({ outcome: "head", personId: per.headOlder, needsConfirm: 1 });
        });
        movedNotes("Head").should("have.length", 0);
    });

    it("copies the address to the spouse when the head has their own email", () => {
        emailOf("spouseSpouse").should("eq", addr("spouse"));
        emailOf("spouseHead").should("eq", addr("spouse-head"));
        moveOf("Spouse").its("0").should("include", { outcome: "spouse", personId: per.spouseSpouse });
    });

    it("skips a head who is not active", () => {
        emailOf("inactiveHead").should("eq", null);
        emailOf("inactiveSpouse").should("eq", addr("inactive"));
        moveOf("InactiveHead").its("0").should("include", { outcome: "spouse", personId: per.inactiveSpouse });
    });

    it("writes a timeline note when the head and spouse both have an email", () => {
        emailOf("bothHead").should("eq", addr("both-head"));
        emailOf("bothSpouse").should("eq", addr("both-spouse"));
        moveOf("Both").its("0").should("include", { outcome: "noted", reason: "both_have_email", personId: null });
        expectNote(
            "Both",
            `The family email ${addr("both")} was not moved to a person: the head of household and spouse both have their own email.`,
        );
    });

    it("writes a timeline note when the family has no living, active adult", () => {
        emailOf("noAdultDeceasedHead").should("eq", null);
        emailOf("noAdultChild").should("eq", null);
        moveOf("NoAdult").its("0").should("include", { outcome: "noted", reason: "no_adult" });
        expectNote(
            "NoAdult",
            `The family email ${addr("noadult")} was not moved to a person: the family has no living, active head of household or spouse.`,
        );
    });

    it("writes a timeline note when a person in another family has the address", () => {
        emailOf("elsewhereHead").should("eq", null);
        moveOf("Elsewhere").its("0").should("include", { outcome: "noted", reason: "other_person", personId: per.holder });
        expectNote(
            "Elsewhere",
            `The family email ${addr("elsewhere")} was not moved to a person: another person (holder ${tag}) already uses this address.`,
        );
    });

    it("does nothing at all when a family member already has the address, whatever its case and spaces", () => {
        emailOf("heldHead").should("eq", null);
        emailOf("heldChild").should("eq", addr("held"));
        moveOf("Held").its("0").should("include", { outcome: "held", personId: per.heldChild, needsConfirm: 0 });
        movedNotes("Held").should("have.length", 0);
    });

    it("gives an address two families share to the first family only", () => {
        emailOf("sharedFirstHead").should("eq", addr("shared"));
        emailOf("sharedSecondHead").should("eq", null);
        moveOf("SharedSecond").its("0").should("include", { outcome: "noted", reason: "other_person", personId: per.sharedFirstHead });
        expectNote(
            "SharedSecond",
            `The family email ${addr("shared").toUpperCase()} was not moved to a person: another person (sharedFirstHead ${tag}) already uses this address.`,
        );
    });

    it("leaves an address moved into a family of two or more unconfirmed, and one moved to the only living member confirmed", () => {
        moveOf("Head").its("0.needsConfirm").should("eq", 1);
        moveOf("Spouse").its("0.needsConfirm").should("eq", 1);
        moveOf("Solo").its("0").should("include", { outcome: "head", personId: per.solo, needsConfirm: 0 });
        moveOf("SharedFirst").its("0.needsConfirm").should("eq", 0);
        cy.dbQuery(
            "SELECT psi_per_ID AS personId, psi_UnconfirmedEmail AS email, psi_UnconfirmedSource AS source " +
                "FROM person_signin_psi WHERE psi_per_ID IN (?) ORDER BY psi_per_ID",
            [[per.headOlder, per.spouseSpouse, per.inactiveSpouse, per.solo, per.sharedFirstHead, per.heldChild]],
        )
            .its("rows")
            .should("deep.equal", [
                { personId: per.headOlder, email: addr("head"), source: "family_email" },
                { personId: per.spouseSpouse, email: addr("spouse"), source: "family_email" },
            ]);
        moveOf("InactiveHead").its("0.needsConfirm").should("eq", 0);
    });

    it("clears every family email, and the family API's deprecated Email is the head's moved address", () => {
        cy.dbQuery(`SELECT COUNT(*) AS n FROM family_fam WHERE fam_Email IS NOT NULL`).its("rows.0.n").should("eq", 0);
        cy.makePrivateAdminAPICall("GET", `/api/family/${fam.Head}`).then((resp) => {
            expect(resp.body.Email).to.eq(addr("head"));
            expect(resp.headers).to.have.property("deprecation");
            expect(resp.body.Name).to.eq(`Head ${tag}`);
        });
    });

    it("moves nothing more when it runs again", () => {
        runMove();
        cy.dbQuery(`SELECT COUNT(*) AS n FROM family_email_move_fem WHERE fem_fam_ID IN (${familyIds.map(Number).join(",")})`)
            .its("rows.0.n")
            .should("eq", 10);
        movedNotes("Both").should("have.length", 1);
    });

    describe("a family added after the upgrade ran", () => {
        before(() => {
            addFamily("Twice", addr("twice"));
            addPerson("twiceHead", "Twice", 1, addr("twice-head"));
            addPerson("twiceSpouse", "Twice", 2, addr("twice-spouse"));
        });

        it("handles each family once when it runs twice in the same second", () => {
            const fixtureMoves = () =>
                cy
                    .then(() =>
                        cy.dbQuery(`SELECT COUNT(*) AS n FROM family_email_move_fem WHERE fem_fam_ID IN (${familyIds.map(Number).join(",")})`),
                    )
                    .its("rows.0.n");

            fixtureMoves().then((before) => {
                runMove();
                runMove();
                fixtureMoves().should("eq", before + 1);
            });
            moveOf("Twice").should("have.length", 1).its("0").should("include", { outcome: "noted", reason: "both_have_email" });
            movedNotes("Twice").should("have.length", 1);
            movedNotes("Both").should("have.length", 1);
        });
    });

    describe("a family email written later by an older client", () => {
        const stray = addr("stray");

        before(() => {
            cy.dbQuery("UPDATE family_fam SET fam_Email = ? WHERE fam_ID = ?", [stray, fam.NoAdult]);
        });

        it("is never used as a member's email", () => {
            cy.makePrivateAdminAPICall("GET", `/api/person/${per.noAdultChild}`).its("body.Email").should("eq", null);
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/email/preview",
                { personIds: [per.noAdultChild], subject: "s", body: "b" },
                400,
            );
        });

        it("is never the family's mail address", () => {
            cy.makePrivateAdminAPICall("POST", "/api/email/send", { familyIds: [fam.NoAdult], subject: `s ${tag}`, body: "b" }).then(
                (resp) => {
                    expect(resp.body.counts).to.deep.equal({ queued: 0, sent: 0, skipped: 1, failed: 0 });
                    expect(resp.body.skipped[0]).to.include({ familyId: fam.NoAdult, reason: "no-adult-email" });
                },
            );
        });

        describe("on a family whose head and spouse have their own email", () => {
            const strayBoth = addr("stray-both");

            before(() => {
                cy.dbQuery("UPDATE family_fam SET fam_Email = ? WHERE fam_ID = ?", [strayBoth, fam.Both]);
            });

            after(() => {
                cy.dbQuery("UPDATE family_fam SET fam_Email = NULL WHERE fam_ID = ?", [fam.Both]);
            });

            it("is neither shown on the family page nor printed in the directory", () => {
                const cart = (method, body) =>
                    cy.request({ method, url: "/api/cart/", headers: { "Content-Type": "application/json" }, body: JSON.stringify(body) });

                cy.setupAdminSession();
                cy.visit(`/people/family/${fam.Both}`);
                cy.get("#family-email-card").should("contain.text", addr("both-head")).and("not.contain.text", strayBoth);

                cart("DELETE", {});
                cart("POST", { Family: fam.Both });
                cy.request({
                    method: "POST",
                    url: "Reports/DirectoryReport.php",
                    form: true,
                    encoding: "binary",
                    body: {
                        cartdir: "M",
                        "sDirRoleHead[]": "1",
                        "sDirRoleSpouse[]": "2",
                        "sDirRoleChild[]": "3",
                        bDirAddress: "1",
                        bDirFamilyEmail: "1",
                        bDirPersonalEmail: "1",
                        sDirLayout: "pages",
                        NumCols: "1",
                        PageSize: "letter",
                        FSize: "10",
                        Submit: "Create Directory",
                    },
                })
                    .then((response) => pdfText(response.body))
                    .then((lines) => {
                        const text = lines.join("\n");
                        expect(text).to.include(addr("both-head"));
                        expect(text).to.include(addr("both-spouse"));
                        expect(text).to.not.include(strayBoth);
                    });
                cart("DELETE", {});
            });
        });

        it("is moved by the same rule when the upgrade block runs again", () => {
            runMove();
            moveOf("NoAdult").then((moves) => {
                expect(moves).to.have.length(2);
                expect(moves[1]).to.include({ email: stray, outcome: "noted", reason: "no_adult" });
            });
            cy.dbQuery("SELECT fam_Email AS email FROM family_fam WHERE fam_ID = ?", [fam.NoAdult]).its("rows.0.email").should("eq", null);
        });
    });
});
