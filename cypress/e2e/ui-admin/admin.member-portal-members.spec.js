/// <reference types="cypress" />

/**
 * Member sign-in row 9 (#10463): the Members, Audit and Permissions tabs of
 * Admin → Member Portal (docs/member-sign-in-design.md §11.2, §8.6), and the
 * person page's devices, passkeys and portal permissions (§11.3, §8.3).
 *
 * Every x-api-key request replaces the browser session, so each UI step that
 * follows one signs in again. Darren Campbell (person 4) and Lena Black (person
 * 100, spouse in the Black family) sign in to the portal with their email.
 * An unconfirmed moved address and labelled remembered devices are support fixtures.
 */

import { addRememberedDevice } from "../../support/member-sign-in-fixtures";
import { signInStatus } from "../../support/sign-in-audit";
import { markEmailUnconfirmed } from "../../support/sign-in-email-fixtures";

const SETTINGS_URL = "/admin/api/member-portal/sign-in";
const RULE = { sMemberSignInFamilyRoles: [1, 2, 3], sMemberSignInClassifications: [1, 2], iMemberSignInMinAge: 13 };
const VERSION_URL = "/admin/api/system/config/sVolunteerVersion";
const MINISTRIES_URL = "/api/ministries/ministries";
const PREFIX = "ROW09UI";
const MARK = "row09-ui";
const PORTAL_PERSON = 4;

const db = (sql, params = []) =>
    cy.dbQuery(sql, params).then((result) => {
        expect(result.error, sql).to.be.null;
        return result.rows;
    });

const portalNavLabels = () =>
    cy.request("/portal/").then((response) => {
        const doc = new DOMParser().parseFromString(response.body, "text/html");
        return [...doc.querySelectorAll(".portal-nav-link")].map((link) => link.textContent.trim());
    });

// Bootstrap ignores a hide while the modal is still opening; bootbox focuses its button once it is open.
const confirmDialog = () => {
    cy.get(".bootbox .bootbox-accept").should("have.focus").click();
    cy.get(".bootbox").should("not.exist");
};

const noHorizontalScroll = () =>
    cy.document().then((doc) => {
        expect(doc.documentElement.scrollWidth, "page width").to.be.at.most(doc.documentElement.clientWidth + 1);
    });

const cleanupMinistries = () =>
    cy.makePrivateAdminAPICall("GET", MINISTRIES_URL, null, 200).then(({ body }) => {
        for (const ministry of body.ministries) {
            if (ministry.name.startsWith(PREFIX)) {
                cy.makePrivateAdminAPICall("POST", `${MINISTRIES_URL}/${ministry.id}`, { active: false }, [200, 404]);
                cy.makePrivateAdminAPICall("DELETE", `${MINISTRIES_URL}/${ministry.id}`, null, [200, 404]);
            }
        }
    });

const cleanupRows = () => {
    db("UPDATE person_per SET per_Email = 'constance.hart@example.com' WHERE per_ID = 6");
    db("UPDATE person_per SET per_Email = 'marion.sutton@example.com' WHERE per_ID = 7");
    db("DELETE FROM family_email_move_fem WHERE fem_Email LIKE ?", [`${MARK}%`]);
    db("DELETE FROM portal_grant_pgr WHERE pgr_per_ID = ?", [PORTAL_PERSON]);
    db("DELETE FROM person_device_pdv WHERE pdv_Label LIKE ?", [`${MARK}%`]);
    db("DELETE FROM person_signin_psi WHERE psi_per_ID = 14");
};

describe("Admin → Member Portal members, audit, permissions and the person card", () => {
    const saved = {};
    let savedVersion = "v1";
    let ministryId = 0;
    let teamId = 0;
    let lastNoteId = 0;

    before(() => {
        cy.makePrivateAdminAPICall("GET", SETTINGS_URL, null, 200).then(({ body }) => {
            Object.keys(RULE).forEach((key) => {
                saved[key] = body.values[key];
            });
        });
        cy.makePrivateAdminAPICall("GET", VERSION_URL, null, 200).then(({ body }) => {
            savedVersion = body.value ?? "v1";
        });
        cy.makePrivateAdminAPICall("POST", SETTINGS_URL, RULE, 200);
        cy.makePrivateAdminAPICall("POST", VERSION_URL, { value: "v2" }, 200);
        cleanupRows();
        cleanupMinistries();
        db("UPDATE person_per SET per_Email = 'pair.row09@example.com' WHERE per_ID IN (6, 7)");
        db(
            `INSERT INTO family_email_move_fem (fem_fam_ID, fem_Email, fem_Outcome, fem_Reason, fem_per_ID, fem_NeedsConfirm, fem_DateMoved) VALUES
             (1, '${MARK}-head@example.com', 'head', NULL, 2, 1, NOW()),
             (7, '${MARK}-both@example.com', 'noted', 'both_have_email', NULL, 0, NOW())`,
        );
        db("SELECT COALESCE(MAX(nte_ID), 0) AS id FROM note_nte").then(([row]) => {
            lastNoteId = Number(row.id);
        });
        cy.makePrivateAdminAPICall("POST", MINISTRIES_URL, { name: `${PREFIX} Hospitality`, description: MARK }, 201).then(({ body }) => {
            ministryId = body.ministry.id;
            cy.makePrivateAdminAPICall("GET", `${MINISTRIES_URL}/${body.ministry.id}/teams`, null, 200).then(({ body: teams }) => {
                teamId = teams.teams[0].id;
            });
        });
    });

    after(() => {
        cleanupRows();
        cleanupMinistries();
        cy.then(() => db("DELETE FROM note_nte WHERE nte_ID > ? AND nte_Type = 'verify' AND nte_EnteredBy = -2", [lastNoteId]));
        cy.makePrivateAdminAPICall("POST", VERSION_URL, { value: savedVersion }, 200);
        cy.makePrivateAdminAPICall("POST", SETTINGS_URL, saved, 200);
    });

    it("lists who may sign in and whose email needs attention, a page at a time", () => {
        cy.freshAdminFormLogin();
        cy.visit("/admin/member-portal#portal-members");
        cy.get("#portal-members").should("have.class", "active");
        cy.get('[data-count-for="may-sign-in"]').invoke("text").should("match", /^\d+$/);
        cy.get("#memberListTable tbody tr").should("have.length.at.least", 2);
        cy.get("#memberListCard .dt-search input").type("Jon Olson");
        cy.get("#memberListTable tbody tr").should("have.length", 1).and("contain.text", "Jon Olson");

        cy.get("#memberList-email-attention").click();
        cy.get("#memberListTitle").should("have.text", "Email needs attention");
        cy.get("#memberListProblemBar").should("be.visible");
        cy.get("#memberListTable").should("contain.text", "Constance Hart").and("contain.text", "Marion Hart");
        cy.get('#memberListProblems [data-problem="no_email"]').click();
        cy.get("#memberListTable").should("not.contain.text", "Constance Hart");
        cy.get('#memberListProblems [data-problem="shared_email"]').click();
        cy.get("#memberListTable tbody tr").should("have.length", 2);
        cy.get("#memberListTable [data-cy=attention-fix]").first().should("have.attr", "data-problem", "shared_email");

        cy.get("#memberList-family-emails-not-moved").click();
        cy.get("#memberListMoveCounts").should("be.visible");
        cy.get('[data-move-count="head"]').should("have.text", "1");
        cy.get('[data-move-count="noted"]').should("have.text", "1");
        cy.get("#memberListTable").should("contain.text", "Stewart").and("contain.text", "both have their own email");
        cy.get("#memberListTable a[href$='/people/family/7']").should("exist");
    });

    it("confirms an unconfirmed moved address from the Email needs attention list", () => {
        markEmailUnconfirmed(14, "family_email");
        cy.freshAdminFormLogin();
        cy.visit("/admin/member-portal#portal-members");
        cy.get("#memberList-email-attention").click();
        cy.get('#memberListProblems [data-problem="email_unconfirmed"]').click();
        cy.get("#memberListTable tbody tr").should("have.length", 1).and("contain.text", "Nathan Lewis").and("contain.text", "Moved from the family email");
        cy.get("#memberListTable .member-list-confirm-email").click();
        confirmDialog();
        cy.get("#memberListTable").should("not.contain.text", "Nathan Lewis");
        cy.get('[data-problem-count="email_unconfirmed"]').should("have.text", "0");

        cy.visit("/admin/member-portal#portal-audit");
        cy.get("#portalAuditEvent").select("email_confirmed");
        cy.get("#portalAuditTable tbody tr").first().should("contain.text", "Nathan Lewis").and("contain.text", "Church Admin");
        signInStatus(14).its("unconfirmedEmail").should("be.null");
    });

    it("lists a family a member confirmed in the portal, with who confirmed, and audits it", () => {
        cy.signInAsMember(100);
        cy.visit("/portal/family/confirm");
        cy.get("#portal-confirm-submit").click();
        cy.get(".portal-flash-success", { timeout: 10000 }).should("be.visible");

        cy.freshAdminFormLogin();
        cy.visit("/admin/member-portal#portal-members");
        cy.get("#memberList-families-confirmed").click();
        cy.get("#memberListTable tbody tr").first().should("contain.text", "Black").and("contain.text", "Lena Black").and("contain.text", "No changes");

        cy.visit("/admin/member-portal#portal-audit");
        cy.get("#portalAuditEvent").select("family_confirmed");
        cy.get("#portalAuditTable tbody tr")
            .first()
            .should("contain.text", "Family details confirmed")
            .and("contain.text", "Lena Black")
            .and("contain.text", "Black · No changes");
    });

    it("filters the audit by event and person", () => {
        cy.makePrivateAdminAPICall("POST", "/admin/api/person/92/sign-in/disable", { reason: "UI audit spec" }, 200);
        cy.makePrivateAdminAPICall("POST", "/admin/api/person/92/sign-in/enable", null, 200);
        cy.freshAdminFormLogin();
        cy.visit("/admin/member-portal#portal-audit");
        cy.get("#portalAuditEvent").select("sign_in_disabled");
        cy.get("#portalAuditTable tbody tr").first().should("contain.text", "Sign-in disabled").and("contain.text", "Bruce Kennedy");
        cy.get("#portalAuditTable").should("not.contain.text", "Sign-in enabled");

        cy.get("#portalAuditFilters").find("button[type=reset]").click();
        cy.get("#portalAuditPerson").parent().find(".ts-control input").type("Bruce");
        cy.get(".ts-dropdown .option").contains("Bruce Kennedy").click();
        cy.get("#portalAuditTable tbody tr").first().should("contain.text", "Sign-in enabled");
        cy.get("#portalAuditTable tbody tr").each(($row) => {
            expect($row.text()).to.contain("Bruce Kennedy");
        });
        cy.get("#portalAuditTable").should("not.contain.text", "UI audit spec");
    });

    it("lists the permissions with their holders, and the Statistics tab reads the sign-in rows", () => {
        cy.freshAdminFormLogin();
        cy.visit("/admin/member-portal#portal-permissions");
        cy.get("#portalPermissionsLead").should("contain.text", '"Admin overrides" then shows here');
        cy.get("#portalPermissionsTable thead th").then(($headers) => {
            expect([...$headers].map((header) => header.textContent.trim())).to.deep.eq([
                "Permission",
                "Applies to",
                "Given to",
                "Status",
                "People",
            ]);
        });
        cy.get('#portalPermissionsTable tr[data-capability="portal.family.edit"]')
            .should("contain.text", "Change family details")
            .and("contain.text", "Adult of the family")
            .and("not.contain.text", "Admin overrides");
        cy.get('#portalPermissionsTable tr[data-capability="volunteer.team.manage"]').should("contain.text", "Team leader");
        // The catalog keeps "Everyone"; only the person card says "Given to Everyone".
        cy.get('#portalPermissionsTable tr[data-capability="portal.profile.edit"]').should("contain.text", "Everyone").and("not.contain.text", "Given to");
        cy.get('#portalPermissionsTable tr[data-capability="portal.family.edit"] .portal-holders-open').click();
        cy.get("#portalHoldersModal").should("be.visible");
        cy.get("#portalHoldersTitle").should("have.text", "Change family details");
        cy.get("#portalHoldersTable").should("contain.text", "Mathew Campbell").and("contain.text", "Adult of the Campbell family");
        cy.get("#portalHoldersTable thead th").then(($headers) => {
            expect([...$headers].map((header) => header.textContent.trim())).to.include("Why").and.not.include("From");
        });
        cy.get("#portalHoldersModal .btn-close").click();

        cy.get("#portal-statistics-tab").click();
        cy.get("#portalStat-maySignIn").invoke("text").should("match", /^\d+$/);
        cy.get("#portalRecentSignInsTable").should("contain.text", "Lena Black").and("contain.text", "Email code");
    });

    it("gives a team permission on the person page, which opens My Teams in the portal, and removes it", () => {
        cy.signInAsMember(PORTAL_PERSON);
        portalNavLabels().should("not.include", "My Teams");

        cy.freshAdminFormLogin();
        cy.visit(`/people/view/${PORTAL_PERSON}`);
        cy.get("#portal-signin-permissions-section").should("not.be.visible");
        cy.get("#portal-signin-disable").should("be.visible");
        cy.get("#portal-signin-sign-out").should("be.visible");
        cy.get("#portal-signin-permissions-toggle")
            .should("have.attr", "aria-expanded", "false")
            .and("contain.text", "View Permissions")
            .click();
        cy.get("#portal-signin-permissions-section").should("be.visible");
        cy.get("#portal-signin-permissions-toggle").should("have.attr", "aria-expanded", "true").and("contain.text", "Hide Permissions");
        cy.get('#portal-signin-permissions [data-source="everyone"] .fw-medium').should("have.text", "Given to Everyone");
        cy.get("#portal-signin-permissions-count").invoke("text").as("countBefore", { type: "static" });
        cy.get("#portal-grant-add").click();
        cy.get("#portal-grant-modal").should("be.visible");
        cy.get("#portal-grant-capability").select("volunteer.team.manage");
        cy.get("#portal-grant-scope-label").should("have.text", "Team");
        cy.get("#portal-grant-scope-group .ts-control input").type(PREFIX);
        cy.get(".ts-dropdown .option").contains(`${PREFIX} Hospitality`).click();
        const lastDay = new Date(Date.now() + 60 * 86400000).toISOString().slice(0, 10);
        cy.get("#portal-grant-ends").should("have.class", "is-empty").type(lastDay);
        cy.get("#portal-grant-ends").should("not.have.class", "is-empty").clear();
        cy.get("#portal-grant-ends").should("have.class", "is-empty").type(lastDay);
        cy.get("#portal-grant-save").click();
        cy.get("#portal-grant-error").should("contain.text", "say why");
        cy.get("#portal-grant-reason").type("Leads the greeters this autumn");
        cy.get("#portal-grant-save").click();

        cy.get('#portal-signin-permissions [data-source="given"]', { timeout: 20000 })
            .should("be.visible")
            .and("contain.text", "Given by Church Admin until")
            .and("contain.text", "Manage a team")
            .and("contain.text", `${PREFIX} Hospitality`)
            .and("contain.text", "Leads the greeters this autumn");
        cy.get("@countBefore").then((countBefore) => {
            cy.get("#portal-signin-permissions-count").should("have.text", `(${Number(countBefore.replace(/\D/g, "")) + 1})`);
        });
        cy.get("#portal-signin-permissions-toggle").should("contain.text", "Hide Permissions").click();
        cy.get("#portal-signin-permissions-section").should("not.be.visible");
        cy.get("#portal-signin-permissions-toggle").should("contain.text", "View Permissions");
        cy.visit("/admin/member-portal#portal-permissions");
        cy.get('#portalPermissionsTable tr[data-capability="volunteer.team.manage"]').should("contain.text", "Admin overrides: 1");

        cy.signInAsMember(PORTAL_PERSON);
        portalNavLabels().should("include", "My Teams");
        cy.then(() => cy.request(`/portal/teams/${teamId}`).its("status").should("eq", 200));
        cy.visit("/portal/teams");
        cy.get(".portal-team-card").should("contain.text", `${PREFIX} Hospitality`);

        cy.freshAdminFormLogin();
        cy.visit(`/people/view/${PORTAL_PERSON}`);
        cy.get("#portal-signin-permissions-section").should("not.be.visible");
        cy.get("#portal-signin-permissions-toggle").click();
        cy.get('#portal-signin-permissions [data-source="given"] .portal-signin-remove-grant').click();
        confirmDialog();
        cy.get('#portal-signin-permissions [data-source="given"]', { timeout: 20000 }).should("not.exist");
        cy.get("#portal-signin-permissions-section").should("be.visible");
        cy.get("#portal-signin-permissions-toggle").should("have.attr", "aria-expanded", "true").and("contain.text", "Hide Permissions");

        cy.signInAsMember(PORTAL_PERSON);
        portalNavLabels().should("not.include", "My Teams");
    });

    it("offers only the permissions and scopes the person does not hold yet", () => {
        const given = `/admin/api/person/${PORTAL_PERSON}/sign-in/grants`;
        let givenId = 0;
        let leaderScopeId = 0;
        cy.makePrivateAdminAPICall("POST", given, { capability: "portal.directory.view", reason: "Given already" }, 201).then(({ body }) => {
            givenId = body.grantId;
        });
        cy.makePrivateAdminAPICall("POST", `${MINISTRIES_URL}/${ministryId}/teams`, { name: `${PREFIX} Ushers` }, 201);
        cy.makePrivateAdminAPICall("POST", "/api/ministries/scopes", { personId: PORTAL_PERSON, scopeType: "team", scopeId: teamId }, 201).then(({ body }) => {
            leaderScopeId = body.scope.id;
        });

        cy.freshAdminFormLogin();
        cy.visit(`/people/view/${PORTAL_PERSON}`);
        cy.get("#portal-signin-permissions-toggle").click();
        cy.get('#portal-signin-permissions [data-source="rule"]').should("contain.text", "Manage a team");
        cy.get("#portal-grant-none-left").should("not.exist");
        cy.get("#portal-grant-add").should("be.enabled").click();
        cy.get("#portal-grant-modal").should("be.visible");
        // Everyone's own, and the one given above, are not offered again.
        ["portal.profile.edit", "portal.calendar.view", "portal.directory.view"].forEach((key) => {
            cy.get(`#portal-grant-capability option[value="${key}"]`).should("not.exist");
        });
        cy.get('#portal-grant-capability option[value="portal.family.contacts.view"]').should("exist");
        cy.get('#portal-grant-capability option[value="volunteer.team.manage"]').should("have.attr", "data-held-scopes", String(teamId));
        cy.intercept("GET", "/admin/api/member-portal/grant-scopes/team*").as("teams");
        cy.get("#portal-grant-capability").select("volunteer.team.manage");
        cy.get("#portal-grant-scope-group .ts-control input").type(PREFIX);
        cy.wait("@teams").its("request.url").should("contain", `exclude=${teamId}`);
        cy.get(".ts-dropdown .option")
            .should("contain.text", `${PREFIX} Ushers (${PREFIX} Hospitality)`)
            .and("not.contain.text", `${PREFIX} Hospitality (${PREFIX} Hospitality)`);

        // Mathew Campbell is an adult of the Campbell family (1) and a Member, which the directory rule lets in.
        cy.visit("/people/view/2");
        cy.get("#portal-signin-permissions-toggle").click();
        cy.get('#portal-signin-permissions [data-source="rule"]').should("contain.text", "Change family details");
        cy.get("#portal-grant-add").click();
        cy.get("#portal-grant-modal").should("be.visible");
        cy.get('#portal-grant-capability option[value="portal.directory.view"]').should("not.exist");
        cy.get('#portal-grant-capability option[value="portal.family.edit"]').should("have.attr", "data-held-scopes", "1");
        cy.intercept("GET", "/admin/api/member-portal/grant-scopes/family*").as("families");
        cy.get("#portal-grant-capability").select("portal.family.edit");
        cy.get("#portal-grant-scope-group .ts-control input").type("Campbell");
        cy.wait("@families").then(({ request, response }) => {
            expect(request.url).to.contain("exclude=1");
            expect(response.body.map((choice) => choice.id)).to.not.include(1);
        });
        cy.get(".ts-dropdown").should("not.contain.text", "Campbell (Denton)");

        cy.then(() => {
            cy.makePrivateAdminAPICall("POST", `${given}/${givenId}/remove`, null, 200);
            cy.makePrivateAdminAPICall("DELETE", `/api/ministries/scopes/${leaderScopeId}`, null, 200);
        });
    });

    it("lists a person's remembered devices and lets an administrator sign one out", () => {
        addRememberedDevice(PORTAL_PERSON, { label: `${MARK} laptop`, expiresInDays: 20 });
        addRememberedDevice(PORTAL_PERSON, { label: `${MARK} signed out`, expiresInDays: 20, signedOut: true });
        cy.freshAdminFormLogin();
        cy.visit(`/people/view/${PORTAL_PERSON}`);
        cy.get("#portal-signin-device-list [data-cy=portal-device]").should("have.length", 1).and("be.visible").and("contain.text", `${MARK} laptop`);
        cy.get("#portal-signin-device-list").should("not.contain.text", `${MARK} signed out`);
        cy.get("#portal-signin-device-list .portal-signin-remove-device").click();
        confirmDialog();
        cy.get("#portal-signin-device-list", { timeout: 20000 }).should("not.exist");
        cy.get("#portal-signin-devices").should("contain.text", "0");
    });

    it("shows Edit Records staff only the sign-in status and the counts", () => {
        cy.makePrivateAdminAPICall("POST", `/admin/api/person/${PORTAL_PERSON}/sign-in/grants`, {
            capability: "portal.family.contacts.view",
            scopeId: 3,
            reason: "Hidden from Edit Records",
        }, 201);
        addRememberedDevice(PORTAL_PERSON, { label: `${MARK} tablet`, expiresInDays: 20 });
        cy.setupStandardSession({ forceLogin: true });
        cy.visit(`/people/view/${PORTAL_PERSON}`);
        cy.get("#portal-signin-status").should("exist");
        cy.get("#portal-signin-devices").should("contain.text", "1");
        cy.get("#portal-signin-passkeys").should("contain.text", "0");
        cy.get("#portal-signin-access").should("not.exist");
        cy.get("#portal-signin-permissions-toggle").should("not.exist");
        cy.get("#portal-signin-card").should("not.contain.text", `${MARK} tablet`).and("not.contain.text", "Hidden from Edit Records");
        cy.get("#portal-grant-modal").should("not.exist");
    });

    it("fits phone and tablet widths", () => {
        cy.freshAdminFormLogin();
        [
            [390, 844],
            [820, 1180],
        ].forEach(([width, height]) => {
            cy.viewport(width, height);
            cy.visit("/admin/member-portal#portal-members");
            cy.get("#memberListTable tbody tr").should("have.length.at.least", 1);
            noHorizontalScroll();
            cy.visit("/admin/member-portal#portal-permissions");
            cy.get('#portalPermissionsTable tr[data-capability="portal.profile.edit"]').should("be.visible");
            noHorizontalScroll();
            cy.visit("/admin/member-portal#portal-audit");
            // Each row unrolls into a card, so the event is shown rather than folded away.
            cy.get("#portalAuditTable tbody tr:first-child > td").eq(2).should("be.visible").and("have.attr", "data-label", "Event");
            noHorizontalScroll();
            cy.visit(`/people/view/${PORTAL_PERSON}`);
            cy.get("#portal-signin-permissions-toggle").click();
            cy.get("#portal-signin-permissions").should("be.visible");
            noHorizontalScroll();
        });
    });
});
