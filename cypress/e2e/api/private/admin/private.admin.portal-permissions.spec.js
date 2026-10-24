/// <reference types="cypress" />

/**
 * Member sign-in row 9 (#10463): the Permissions tab (docs/member-sign-in-design.md
 * §8.6), portal permissions an administrator gives one person (§8.3 source 3),
 * and removing a person's remembered devices and passkeys (§11.3).
 *
 * cypress/fixtures/plugins/portal-example adds a rule-based capability (a roster
 * of each group a person belongs to) and one only an administrator gives.
 */

const SETTINGS_URL = "/admin/api/member-portal/sign-in";
const RULE = { sMemberSignInFamilyRoles: [1, 2, 3], sMemberSignInClassifications: [1, 2], iMemberSignInMinAge: 13 };
const PLUGIN = "portal-example";
const PLUGIN_URL = `/plugins/api/plugins/${PLUGIN}`;
const NOTICES = `plugin.${PLUGIN}.notices`;
const ROSTER = `plugin.${PLUGIN}.roster.view`;
const CHILD = 16;
const OTHER = 57;
const REASON = "Permissions spec";

const db = (sql, params = []) =>
    cy.dbQuery(sql, params).then((result) => {
        expect(result.error, sql).to.be.null;
        return result.rows;
    });

const grants = (personId) => `/admin/api/person/${personId}/sign-in/grants`;
const give = (personId, body, status = 201) => cy.makePrivateAdminAPICall("POST", grants(personId), { reason: REASON, ...body }, status);
const catalog = () =>
    cy.makePrivateAdminAPICall("GET", "/admin/api/member-portal/permissions", null, 200).then(({ body }) =>
        Object.fromEntries(body.capabilities.map((capability) => [capability.key, capability])),
    );
const holders = (key, params = {}) =>
    cy
        .makePrivateAdminAPICall(
            "GET",
            `/admin/api/member-portal/permissions/${encodeURIComponent(key)}/holders?${new URLSearchParams({ length: "100", ...params })}`,
            null,
            200,
        )
        .its("body");
const holderCount = (key) => catalog().then((capabilities) => capabilities[key].holderCount);
const allIds = (name) =>
    cy.makePrivateAdminAPICall("GET", `/admin/api/member-portal/lists/${name}?length=100`, null, 200).then(({ body }) => {
        expect(body.total, `${name} fits one page`).to.be.at.most(100);
        return body.rows.map((row) => row.personId);
    });

const day = (offset) => new Date(Date.now() + offset * 86400000).toISOString().slice(0, 10);

const clean = () => {
    db("DELETE FROM portal_grant_pgr WHERE pgr_Reason = ?", [REASON]);
    db("DELETE FROM person_device_pdv WHERE pdv_Label LIKE 'row09 %'");
    db("DELETE FROM person_passkey_ppk WHERE ppk_Label LIKE 'row09 %'");
};

describe("Admin → Member Portal permissions and given grants", () => {
    const saved = {};

    before(() => {
        cy.makePrivateAdminAPICall("GET", SETTINGS_URL, null, 200).then(({ body }) => {
            Object.keys(RULE).forEach((key) => {
                saved[key] = body.values[key];
            });
        });
        cy.makePrivateAdminAPICall("POST", SETTINGS_URL, RULE, 200);
        clean();
        cy.makePrivateAdminAPICall("DELETE", PLUGIN_URL, null, [200, 400]);
        cy.task("pluginFixtures:write", { id: PLUGIN, version: "1.0.0", source: PLUGIN }).then(([entry]) => {
            cy.makePrivateAdminAPICall("POST", "/plugins/api/registry/refresh");
            cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/install", { downloadUrl: entry.downloadUrl });
        });
        cy.makePrivateAdminAPICall("POST", `${PLUGIN_URL}/enable`);
    });

    after(() => {
        clean();
        cy.makePrivateAdminAPICall("DELETE", PLUGIN_URL, null, [200, 400]);
        cy.task("pluginFixtures:clear");
        cy.makePrivateAdminAPICall("POST", "/plugins/api/registry/refresh");
        cy.makePrivateAdminAPICall("POST", SETTINGS_URL, saved, 200);
    });

    it("lists every capability, core and plugin, with its sources, status and holders", () => {
        catalog().then((capabilities) => {
            [
                "portal.profile.edit",
                "portal.family.view",
                "portal.family.contacts.view",
                "portal.family.edit",
                "portal.family.confirm",
                "portal.calendar.view",
                "portal.volunteer.use",
                "volunteer.team.manage",
                "volunteer.team.substitutes",
                ROSTER,
                NOTICES,
            ].forEach((key) => expect(capabilities, key).to.have.property(key));

            const edit = capabilities["portal.family.edit"];
            expect(edit.owner).to.eq("core");
            expect(edit.scopeType).to.eq("family");
            expect(edit.sources.map((source) => source.label)).to.deep.eq(["Adult of the family", "Given by an administrator"]);
            expect(capabilities["portal.profile.edit"].sources[0].label).to.eq("Everyone");

            const roster = capabilities[ROSTER];
            expect(roster.owner).to.eq(PLUGIN);
            expect(roster.ownerName).to.eq("Portal Example");
            expect(roster.status).to.eq("on");
            expect(roster.sources.map((source) => source.ruleKey)).to.include(`plugin.${PLUGIN}.group-members`);
            expect(capabilities[NOTICES].holderCount).to.eq(0);
        });
    });

    it("counts holders among everyone the rules let in and staff, from each rule's own data", () => {
        allIds("may-sign-in").then((maySignIn) => {
            allIds("email-attention").then((attention) => {
                db(
                    `SELECT u.usr_per_ID AS id FROM user_usr u
                       JOIN person_per p ON p.per_ID = u.usr_per_ID
                       LEFT JOIN family_fam f ON f.fam_ID = p.per_fam_ID
                       LEFT JOIN person_signin_psi s ON s.psi_per_ID = p.per_ID
                      WHERE p.per_DateDeceased IS NULL AND p.per_DateDeactivated IS NULL AND p.per_NeedsReview = 0
                        AND f.fam_DateDeactivated IS NULL AND (s.psi_SignInDisabled IS NULL OR s.psi_SignInDisabled = 0)`,
                ).then((staff) => {
                    const population = new Set([...maySignIn, ...attention, ...staff.map((row) => Number(row.id))]);
                    db("SELECT DISTINCT p2g2r_per_ID AS id FROM person2group2role_p2g2r").then((members) => {
                        const inGroups = members.map((row) => Number(row.id)).filter((id) => population.has(id));
                        catalog().then((capabilities) => {
                            expect(capabilities["portal.profile.edit"].holderCount, "everyone").to.eq(population.size);
                            expect(capabilities[ROSTER].holderCount, "group members").to.eq(inGroups.length);
                        });
                        holders(ROSTER).then((body) => {
                            expect(body.total).to.eq(inGroups.length);
                            const limited = body.rows.find((row) => row.personId === 4);
                            expect(limited.grants.map((grant) => grant.sourceLabel)).to.include("Member of Angels class");
                            expect(limited.grants[0].scope).to.include({ type: "group", id: 1, name: "Angels class" });
                        });
                    });
                });
            });
        });
        holders("portal.family.edit").then((body) => {
            const ids = body.rows.map((row) => row.personId);
            expect(ids).to.include(2);
            expect(ids).to.not.include(CHILD);
            const mathew = body.rows.find((row) => row.personId === 2);
            expect(mathew.grants[0]).to.include({ source: "rule", sourceLabel: "Adult of the Campbell family" });
        });
    });

    it("gives a permission with a scope, an end date and a reason, audits it and removes it", () => {
        holderCount("portal.family.edit").then((before) => {
            give(CHILD, { capability: "portal.family.edit", scopeId: 3, endsOn: day(30) }).then(({ body }) => {
                const grantId = body.grantId;
                holderCount("portal.family.edit").should("eq", before + 1);
                holders("portal.family.edit", { search: "Alicia" }).then((page) => {
                    expect(page.rows).to.have.length(1);
                    expect(page.rows[0].grants[0].source).to.eq("given");
                    expect(page.rows[0].grants[0].sourceLabel).to.match(/^Given by Church Admin until /);
                    expect(page.rows[0].grants[0].scope).to.include({ type: "family", id: 3, name: "Lewis" });
                });
                db("SELECT pgr_EndsAt AS endsAt, pgr_GrantedBy_usr_ID AS giver FROM portal_grant_pgr WHERE pgr_ID = ?", [grantId]).then((rows) => {
                    expect(String(rows[0].endsAt)).to.match(/23:59:59|T\d\d:59:59/);
                    expect(rows[0].giver).to.eq(1);
                });

                give(CHILD, { capability: "portal.family.edit", scopeId: 3 }, 409);

                cy.makePrivateAdminAPICall("POST", `${grants(CHILD)}/${grantId}/remove`, null, 200);
                cy.makePrivateAdminAPICall("POST", `${grants(OTHER)}/${grantId}/remove`, null, 404);
                holderCount("portal.family.edit").should("eq", before);

                cy.makePrivateAdminAPICall("GET", `/admin/api/member-portal/audit?personId=${CHILD}&length=2`, null, 200).then(({ body: page }) => {
                    expect(page.rows.map((row) => row.event)).to.deep.eq(["grant_removed", "grant_added"]);
                    page.rows.forEach((row) => {
                        expect(row.actorId).to.eq(1);
                        expect(row.summary).to.contain("Change family details").and.contain("Lewis");
                        expect(row.detail).to.contain(`grant=${grantId}`);
                        expect(row.detail).to.not.contain(REASON);
                    });
                });
            });
        });
    });

    it("gives a plugin's permission without a scope, and counts it", () => {
        give(OTHER, { capability: NOTICES }).then(({ body }) => {
            catalog().then((capabilities) => {
                expect(capabilities[NOTICES].holderCount).to.eq(1);
                expect(capabilities[NOTICES].givenCount).to.eq(1);
            });
            holders(NOTICES).its("rows.0.personId").should("eq", OTHER);
            cy.makePrivateAdminAPICall("POST", `${grants(OTHER)}/${body.grantId}/remove`, null, 200);
        });
    });

    it("refuses a grant it cannot give", () => {
        give(CHILD, { capability: "no.such.capability" }, 400);
        give(CHILD, { capability: "portal.family.edit" }, 400);
        give(CHILD, { capability: "portal.family.edit", scopeId: 987654 }, 400);
        give(CHILD, { capability: "portal.calendar.view", endsOn: day(-2) }, 400);
        give(CHILD, { capability: "portal.calendar.view", endsOn: "31/12/2026" }, 400);
        give(CHILD, { capability: "portal.calendar.view", reason: "  " }, 400);
        give(987654, { capability: "portal.calendar.view" }, 404);
        cy.makePrivateAdminAPICall("GET", "/admin/api/member-portal/permissions/no.such.capability/holders", null, 404);
        cy.makePrivateAdminAPICall("GET", "/admin/api/member-portal/grant-scopes/family?q=Lewis", null, 200)
            .its("body")
            .should("deep.include", { id: 3, text: "Lewis (Detroit)" });
        cy.makePrivateAdminAPICall("GET", "/admin/api/member-portal/grant-scopes/event", null, 400);
    });

    it("signs out one remembered device or removes a passkey of a person, and audits it", () => {
        db(
            `INSERT INTO person_device_pdv (pdv_per_ID, pdv_TokenHash, pdv_Kind, pdv_Label, pdv_CreatedAt, pdv_ExpiresAt)
             VALUES (?, SHA2('row09-device-a', 256), 'browser', 'row09 laptop', NOW(), NOW() + INTERVAL 20 DAY),
                    (?, SHA2('row09-device-b', 256), 'browser', 'row09 phone', NOW(), NOW() + INTERVAL 20 DAY)`,
            [OTHER, OTHER],
        );
        db(
            `INSERT INTO person_passkey_ppk (ppk_per_ID, ppk_CredentialId, ppk_CredentialHash, ppk_PublicKey, ppk_Label, ppk_CreatedAt)
             VALUES (?, 'row09-credential', SHA2('row09-credential', 256), 'public-key', 'row09 passkey', NOW())`,
            [OTHER],
        );
        db("SELECT pdv_ID AS id FROM person_device_pdv WHERE pdv_Label = 'row09 laptop'").then(([device]) => {
            cy.makePrivateAdminAPICall("POST", `/admin/api/person/${CHILD}/sign-in/devices/${device.id}/remove`, null, 404);
            cy.makePrivateAdminAPICall("POST", `/admin/api/person/${OTHER}/sign-in/devices/${device.id}/remove`, null, 200);
            cy.makePrivateAdminAPICall("POST", `/admin/api/person/${OTHER}/sign-in/devices/${device.id}/remove`, null, 404);
            db("SELECT pdv_Label AS label, pdv_RevokedAt AS revokedAt FROM person_device_pdv WHERE pdv_per_ID = ? AND pdv_Label LIKE 'row09 %' ORDER BY pdv_ID", [OTHER]).then((rows) => {
                expect(rows.map((row) => row.label)).to.deep.eq(["row09 laptop", "row09 phone"]);
                expect(rows[0].revokedAt, "signed out, kept for the member's list").to.not.be.null;
                expect(rows[1].revokedAt).to.be.null;
            });
        });
        db("SELECT ppk_ID AS id FROM person_passkey_ppk WHERE ppk_Label = 'row09 passkey'").then(([passkey]) => {
            cy.makePrivateAdminAPICall("POST", `/admin/api/person/${OTHER}/sign-in/passkeys/${passkey.id}/remove`, null, 200);
            cy.makePrivateAdminAPICall("POST", `/admin/api/person/${OTHER}/sign-in/passkeys/${passkey.id}/remove`, null, 404);
        });
        cy.makePrivateAdminAPICall("GET", `/admin/api/member-portal/audit?personId=${OTHER}&length=2`, null, 200).then(({ body }) => {
            expect(body.rows.map((row) => row.event)).to.deep.eq(["passkey_removed", "device_removed"]);
            expect(body.rows.every((row) => row.actorId === 1)).to.eq(true);
            expect(body.rows[1].reason).to.eq("signed_out");
            expect(body.rows[1].detail).to.eq("row09 laptop");
        });
    });

    it("is for administrators only", () => {
        cy.makePrivateEditRecordsAPICall("POST", grants(CHILD), { capability: "portal.calendar.view", reason: REASON }, 403);
        cy.makePrivateUserAPICall("GET", "/admin/api/member-portal/permissions", null, 403);
        cy.makePrivateEditRecordsAPICall("POST", `/admin/api/person/${OTHER}/sign-in/devices/1/remove`, null, 403);
    });
});
