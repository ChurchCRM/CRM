/// <reference types="cypress" />

/**
 * Member sign-in (#10463, docs/member-sign-in-design.md §13): the person-keyed
 * tables every later row builds on, how the application reads rows it never
 * writes, and the new settings with their defaults.
 */

const TABLES = {
    person_signin_psi: [
        "psi_per_ID",
        "psi_SessionVersion",
        "psi_SignInDisabled",
        "psi_DisabledBy_usr_ID",
        "psi_DisabledAt",
        "psi_DisabledReason",
        "psi_ProvenEmail",
        "psi_ProvenAt",
        "psi_UnconfirmedEmail",
        "psi_UnconfirmedSource",
        "psi_UnconfirmedAt",
        "psi_FirstSignIn",
        "psi_LastSignIn",
        "psi_LastMethod",
        "psi_LastActivity",
        "psi_CalendarToken",
        "psi_CalendarSelection",
        "psi_Language",
    ],
    person_signin_request_psr: [
        "psr_ID",
        "psr_per_ID",
        "psr_Email",
        "psr_TokenHash",
        "psr_CodeHash",
        "psr_BrowserHash",
        "psr_Attempts",
        "psr_CreatedAt",
        "psr_ExpiresAt",
        "psr_UsedAt",
        "psr_CancelledAt",
    ],
    person_email_change_pec: [
        "pec_ID",
        "pec_per_ID",
        "pec_Kind",
        "pec_Email",
        "pec_TokenHash",
        "pec_CreatedAt",
        "pec_ExpiresAt",
        "pec_UsedAt",
        "pec_CancelledAt",
    ],
    person_device_pdv: [
        "pdv_ID",
        "pdv_per_ID",
        "pdv_TokenHash",
        "pdv_Kind",
        "pdv_Label",
        "pdv_CreatedAt",
        "pdv_LastUsedAt",
        "pdv_ExpiresAt",
        "pdv_RevokedAt",
        "pdv_PreviousTokenHash",
        "pdv_PreviousValidUntil",
    ],
    person_passkey_ppk: [
        "ppk_ID",
        "ppk_per_ID",
        "ppk_CredentialId",
        "ppk_CredentialHash",
        "ppk_PublicKey",
        "ppk_SignCount",
        "ppk_UserHandle",
        "ppk_Transports",
        "ppk_Label",
        "ppk_CreatedAt",
        "ppk_LastUsedAt",
    ],
    portal_grant_pgr: [
        "pgr_ID",
        "pgr_per_ID",
        "pgr_Capability",
        "pgr_ScopeType",
        "pgr_ScopeId",
        "pgr_EndsAt",
        "pgr_Reason",
        "pgr_GrantedBy_usr_ID",
        "pgr_GrantedAt",
    ],
    portal_signin_audit_psa: [
        "psa_ID",
        "psa_Time",
        "psa_per_ID",
        "psa_Event",
        "psa_Method",
        "psa_Reason",
        "psa_Actor_usr_ID",
        "psa_Email",
        "psa_IP",
        "psa_Detail",
    ],
};

const SETTINGS = {
    bMemberSignIn: "0",
    sMemberSignInFamilyRoles: "1,2",
    sMemberSignInClassifications: "",
    iMemberSignInMinAge: "13",
    iSignInLinkMinutes: "120",
    iPortalIdleMinutes: "30",
    iRememberDeviceDays: "30",
    bPortalPasskeys: "1",
    sMemberSignInNotificationRecipientIDs: "",
    bAllowLoginAsPerson: "0",
    bLoginAsPersonCanWrite: "0",
};

const HASH = (c) => c.repeat(64);

const db = (sql, params = []) =>
    cy.dbQuery(sql, params).then((result) => {
        expect(result.error, sql).to.be.null;
        return result.rows;
    });

describe("Member sign-in schema", () => {
    Object.entries(TABLES).forEach(([table, columns]) => {
        it(`${table} has its columns`, () => {
            db("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", [
                table,
            ]).then((rows) => {
                expect(rows.map((row) => row.COLUMN_NAME)).to.have.members(columns);
            });
        });
    });

    it("keys every table to person_per with ON DELETE CASCADE", () => {
        db(
            `SELECT rc.TABLE_NAME AS tbl, rc.REFERENCED_TABLE_NAME AS parent, rc.DELETE_RULE AS rule
               FROM information_schema.REFERENTIAL_CONSTRAINTS rc
              WHERE rc.CONSTRAINT_SCHEMA = DATABASE() AND rc.TABLE_NAME IN (${Object.keys(TABLES).map(() => "?").join(", ")})`,
            Object.keys(TABLES),
        ).then((rows) => {
            expect(rows.map((row) => row.tbl)).to.have.members(Object.keys(TABLES));
            rows.forEach((row) => {
                expect(row.parent, row.tbl).to.eq("person_per");
                expect(row.rule, row.tbl).to.eq("CASCADE");
            });
        });
    });

    it("removes a person's sign-in rows when the person is deleted", () => {
        db(
            "INSERT INTO person_per (per_FirstName, per_LastName, per_DateEntered) VALUES ('Cypress', 'Sign-in Cascade', NOW())",
        ).then((result) => {
            const personId = result.insertId;
            db("INSERT INTO person_signin_psi (psi_per_ID) VALUES (?)", [personId]);
            db(
                `INSERT INTO person_signin_request_psr
                    (psr_per_ID, psr_Email, psr_TokenHash, psr_CodeHash, psr_BrowserHash, psr_CreatedAt, psr_ExpiresAt)
                 VALUES (?, 'cascade@example.com', ?, ?, ?, NOW(), NOW())`,
                [personId, HASH("a"), HASH("b"), HASH("c")],
            );
            db(
                "INSERT INTO person_email_change_pec (pec_per_ID, pec_Kind, pec_Email, pec_TokenHash, pec_CreatedAt, pec_ExpiresAt) VALUES (?, 'change', 'cascade@example.com', ?, NOW(), NOW())",
                [personId, HASH("g")],
            );
            db(
                "INSERT INTO person_device_pdv (pdv_per_ID, pdv_TokenHash, pdv_CreatedAt, pdv_ExpiresAt) VALUES (?, ?, NOW(), NOW())",
                [personId, HASH("d")],
            );
            db(
                `INSERT INTO person_passkey_ppk (ppk_per_ID, ppk_CredentialId, ppk_CredentialHash, ppk_PublicKey, ppk_CreatedAt)
                 VALUES (?, 'Y3JlZA', ?, 'key', NOW())`,
                [personId, HASH("e")],
            );
            db(
                "INSERT INTO portal_grant_pgr (pgr_per_ID, pgr_Capability, pgr_GrantedAt) VALUES (?, 'portal.family.edit', NOW())",
                [personId],
            );
            db(
                "INSERT INTO portal_signin_audit_psa (psa_Time, psa_per_ID, psa_Event) VALUES (NOW(), ?, 'signed_in')",
                [personId],
            );

            db("DELETE FROM person_per WHERE per_ID = ?", [personId]);

            Object.keys(TABLES).forEach((table) => {
                const prefix = TABLES[table][0].split("_")[0];
                db(`SELECT COUNT(*) AS c FROM \`${table}\` WHERE ${prefix}_per_ID = ?`, [personId]).then((rows) => {
                    expect(Number(rows[0].c), table).to.eq(0);
                });
            });
        });
    });

    it("refuses a second row with the same token hash", () => {
        db(
            "INSERT INTO person_device_pdv (pdv_per_ID, pdv_TokenHash, pdv_CreatedAt, pdv_ExpiresAt) VALUES (1, ?, NOW(), NOW())",
            [HASH("f")],
        ).then((first) => {
            cy.dbQuery(
                "INSERT INTO person_device_pdv (pdv_per_ID, pdv_TokenHash, pdv_CreatedAt, pdv_ExpiresAt) VALUES (1, ?, NOW(), NOW())",
                [HASH("f")],
            ).then((second) => {
                expect(second.error.code).to.eq("ER_DUP_ENTRY");
            });
            db("DELETE FROM person_device_pdv WHERE pdv_ID = ?", [first.insertId]);
        });
        const link = "INSERT INTO person_email_change_pec (pec_per_ID, pec_Kind, pec_Email, pec_TokenHash, pec_CreatedAt, pec_ExpiresAt) VALUES (1, 'confirm', 'x@example.com', ?, NOW(), NOW())";
        db(link, [HASH("h")]).then((first) => {
            cy.dbQuery(link, [HASH("h")]).its("error.code").should("eq", "ER_DUP_ENTRY");
            db("DELETE FROM person_email_change_pec WHERE pec_ID = ?", [first.insertId]);
        });
    });

    it("indexes a device's previous token hash without making it unique", () => {
        db(
            "SELECT INDEX_NAME, NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'person_device_pdv' AND COLUMN_NAME = 'pdv_PreviousTokenHash'",
        ).then((rows) => {
            expect(rows.map((row) => row.INDEX_NAME)).to.deep.equal(["pdv_previous_token_idx"]);
            expect(Number(rows[0].NON_UNIQUE)).to.eq(1);
        });
    });

    it("declares the member sign-in settings with the design's defaults", () => {
        Object.entries(SETTINGS).forEach(([name, value]) => {
            cy.getSystemConfig(name).should("eq", value);
        });
    });

    it("ignores a stored grant whose scope type does not match its capability", () => {
        db(
            `INSERT INTO portal_grant_pgr (pgr_per_ID, pgr_Capability, pgr_ScopeType, pgr_ScopeId, pgr_Reason, pgr_GrantedBy_usr_ID, pgr_GrantedAt)
             VALUES (4, 'portal.family.contacts.view', 'team', 1, 'Schema spec', 1, NOW())`,
        ).then(({ insertId }) => {
            cy.signInAsMember(4);
            cy.request("/api/portal/family").its("body.canSeeContacts").should("eq", false);
            db("DELETE FROM portal_grant_pgr WHERE pgr_ID = ?", [insertId]);
        });
    });
});
