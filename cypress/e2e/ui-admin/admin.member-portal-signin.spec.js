/// <reference types="cypress" />

/**
 * Member sign-in row 4 (#10463): Admin → Member Portal → Sign-in
 * (docs/member-sign-in-design.md §11.1) and the person page's Portal sign-in
 * card (§11.3). Every control saves on its own change. Every x-api-key request
 * replaces the browser session, so the UI steps that follow one log in again.
 */

const SETTINGS_URL = "/admin/api/member-portal/sign-in";
const PERSON_ID = 2;

const EVERYONE_CAPABILITY = "portal.profile.edit";

const NO_CLASSIFICATION = "Nobody can sign in yet: tick at least one classification under Who may sign in.";
const NO_FAMILY_ROLE = "Nobody can sign in yet: tick at least one family role under Who may sign in.";
const NO_MATCH = "No one matches the sign-in rule yet.";
const EMPTY_CLASSIFICATION_ID = 4;

const settings = () => cy.makePrivateAdminAPICall("GET", SETTINGS_URL, null, 200).its("body");

const openSignInTab = () => {
    cy.freshAdminFormLogin();
    cy.intercept("POST", "**/admin/api/member-portal/sign-in").as("saveSignIn");
    cy.visit("/admin/member-portal#portal-signin");
    cy.get("#portal-signin").should("have.class", "active");
    cy.get("#sMemberSignInNotificationRecipientIDs").should("have.class", "tomselected");
};

// Bootstrap ignores a hide while the modal is still opening; bootbox focuses its button once it is open.
const answerConfirm = (button) => {
    cy.get(".bootbox .bootbox-accept").should("have.focus");
    cy.get(`.bootbox .bootbox-${button}`).click();
    cy.get(".bootbox").should("not.exist");
};

/** The next save sent exactly one setting, with this value, and the server kept it. */
const expectSave = (key, value) =>
    cy.wait("@saveSignIn").then(({ request, response }) => {
        expect(Object.keys(request.body), "settings in one save").to.deep.eq([key]);
        if (Array.isArray(value)) {
            expect(request.body[key]).to.have.members(value);
        } else {
            expect(request.body[key]).to.eq(value);
        }
        expect(response.statusCode).to.eq(200);
    });

describe("Admin → Member Portal → Sign-in", () => {
    let savedValues;
    let savedEmailEnabled;

    before(() => {
        settings().then((body) => {
            savedValues = body.values;
        });
        cy.getSystemConfig("bEnabledEmail").then((value) => {
            savedEmailEnabled = value;
        });
    });

    afterEach(() => {
        cy.restoreSystemConfig("bEnabledEmail", savedEmailEnabled);
        cy.makePrivateAdminAPICall("POST", SETTINGS_URL, savedValues, 200);
        cy.makePrivateAdminAPICall("POST", `/admin/api/person/${PERSON_ID}/sign-in/enable`, null, [200, 409]);
    });

    it("saves each control as it changes, and keeps it after a reload", () => {
        openSignInTab();
        cy.get("#portal-signin").should("contain.text", "Changes save as you make them.");
        cy.get("#portal-signin button").should("not.exist");
        cy.get("#memberSignInClassifications [data-cy=classification-count]").first().invoke("text").should("match", /^\d+$/);

        cy.get("#memberSignInClassification-3").check();
        expectSave("sMemberSignInClassifications", [3]);
        cy.get("#memberSignInRole-3").check();
        expectSave("sMemberSignInFamilyRoles", [1, 2, 3]);
        cy.get("#iMemberSignInMinAge").clear().type("16").blur();
        expectSave("iMemberSignInMinAge", "16");
        cy.get("#iSignInLinkMinutes").clear().type("60").blur();
        expectSave("iSignInLinkMinutes", "60");
        cy.get("#iMemberSignInRequestsPerIP").clear().type("500").blur();
        expectSave("iMemberSignInRequestsPerIP", "500");
        cy.get("#iPortalIdleMinutes").clear().type("15").blur();
        expectSave("iPortalIdleMinutes", "15");
        cy.get("#iRememberDeviceDays").clear().type("0").blur();
        expectSave("iRememberDeviceDays", "0");
        cy.get("#bPortalPasskeys").uncheck();
        expectSave("bPortalPasskeys", false);
        cy.get("#bAllowLoginAsPerson").check();
        expectSave("bAllowLoginAsPerson", true);
        cy.get("#bLoginAsPersonCanWrite").check();
        expectSave("bLoginAsPersonCanWrite", true);
        cy.contains("Settings saved successfully").should("be.visible");
        cy.get("@saveSignIn.all").should("have.length", 10);

        cy.reload();
        cy.get("#memberSignInClassification-3").should("be.checked");
        cy.get("#memberSignInRole-3").should("be.checked");
        cy.get("#iMemberSignInMinAge").should("have.value", "16");
        cy.get("#iSignInLinkMinutes").should("have.value", "60");
        cy.get("#iMemberSignInRequestsPerIP").should("have.value", "500");
        cy.get("#iPortalIdleMinutes").should("have.value", "15");
        cy.get("#iRememberDeviceDays").should("have.value", "0");
        cy.get("#bPortalPasskeys").should("not.be.checked");
        cy.get("#bAllowLoginAsPerson").should("be.checked");
        cy.get("#bLoginAsPersonCanWrite").should("be.checked");
    });

    it("refreshes the counts, lists and permissions that follow the rule", () => {
        openSignInTab();
        cy.get("#portal-members-tab").click();
        cy.get('[data-count-for="may-sign-in"]').should("have.text", "0");
        cy.get("#portal-permissions-tab").click();
        cy.get(`#portalPermissionsTable tr[data-capability="${EVERYONE_CAPABILITY}"] [data-cy=holder-count]`)
            .invoke("text")
            .as("holdersBefore", { type: "static" });
        cy.get("#portal-signin-tab").click();
        cy.get("#memberSignInMayCount").should("have.text", "0");

        cy.get("#memberSignInClassification-1").check();
        cy.wait("@saveSignIn").its("response.body").then((saved) => {
            expect(saved.maySignInCount).to.be.greaterThan(0);
            cy.get("#memberSignInMayCount").should("have.text", String(saved.maySignInCount));
            cy.get("[data-shared-address-count]").should("have.text", String(saved.sharedAddresses));
            cy.get("#memberSignInSharedEmails").should(saved.sharedAddresses > 0 ? "be.visible" : "not.be.visible");
        });
        cy.request("/admin/api/member-portal/stats").its("body.maySignIn").then((count) => {
            expect(count).to.be.greaterThan(0);
            cy.get("#portalStat-maySignIn").should("have.text", String(count));
            cy.get('[data-count-for="may-sign-in"]').should("have.text", String(count));
        });
        cy.request("/admin/api/member-portal/permissions").then(({ body }) => {
            const everyone = body.capabilities.find((capability) => capability.key === EVERYONE_CAPABILITY);
            cy.get("@holdersBefore").then((before) => expect(everyone.holderCount).to.be.greaterThan(Number(before)));
            cy.get(`#portalPermissionsTable tr[data-capability="${EVERYONE_CAPABILITY}"] [data-cy=holder-count]`).should(
                "have.text",
                String(everyone.holderCount)
            );
        });
    });

    it("adds and removes people on the office list as they are picked", () => {
        openSignInTab();
        cy.get("#sMemberSignInNotificationRecipientIDs").parent().find(".ts-control input").type("Mathew");
        cy.get(".ts-dropdown .option").contains("mathew.campbell@example.com").click();
        expectSave("sMemberSignInNotificationRecipientIDs", [PERSON_ID]);
        cy.request(SETTINGS_URL).its("body.values.sMemberSignInNotificationRecipientIDs").should("eq", String(PERSON_ID));

        cy.get("#sMemberSignInNotificationRecipientIDs").parent().find(".ts-control .item .remove").click();
        expectSave("sMemberSignInNotificationRecipientIDs", []);
        cy.reload();
        cy.get("#sMemberSignInNotificationRecipientIDs").parent().find(".ts-control .item").should("not.exist");
    });

    it("shows the server's message for a refused value and puts the saved value back", () => {
        openSignInTab();
        cy.get("#iMemberSignInMinAge").should("have.value", "13").clear().type("500").blur();
        cy.wait("@saveSignIn").its("response.statusCode").should("eq", 400);
        cy.contains("Enter a whole number from 0 to 120.").should("be.visible");
        cy.get("#iMemberSignInMinAge").should("have.value", "13");
        cy.reload();
        cy.get("#iMemberSignInMinAge").should("have.value", "13");
    });

    it("asks before turning member sign-in on, naming how many may sign in", () => {
        openSignInTab();
        cy.get("#memberSignInClassification-1").check();
        cy.wait("@saveSignIn");
        cy.request("/admin/api/member-portal/stats").its("body.maySignIn").then((count) => {
            cy.get("#bMemberSignIn").should("not.be.checked").click();
            cy.get(".bootbox").should("be.visible").and("contain.text", `People who may sign in: ${count}`);
        });
        cy.get("#memberSignInConfirmWarning").should("not.exist");
        answerConfirm("cancel");
        cy.get("#bMemberSignIn").should("not.be.checked");
        cy.get("@saveSignIn.all").should("have.length", 1);
        cy.request(SETTINGS_URL).its("body.values.bMemberSignIn").should("eq", "0");

        cy.get("#bMemberSignIn").click();
        answerConfirm("accept");
        expectSave("bMemberSignIn", true);
        cy.get("#bMemberSignIn").should("be.checked");
        cy.reload();
        cy.get("#bMemberSignIn").should("be.checked");
    });

    it("says why nobody may sign in when asked to turn member sign-in on, and still turns it on", () => {
        openSignInTab();
        cy.get("#memberSignInNobodyWarning").should("not.be.visible");
        cy.get("#bMemberSignIn").should("not.be.checked").click();
        cy.get(".bootbox").should("be.visible").and("contain.text", "People who may sign in: 0");
        cy.get("#memberSignInConfirmWarning").should("be.visible").and("contain.text", NO_CLASSIFICATION);
        answerConfirm("accept");
        expectSave("bMemberSignIn", true);
        cy.get("#memberSignInNobodyWarning").should("be.visible").and("contain.text", NO_CLASSIFICATION);
    });

    it("warns while member sign-in is on and the rule lets nobody in, until it lets someone in", () => {
        cy.makePrivateAdminAPICall("POST", SETTINGS_URL, { bMemberSignIn: true }, 200);
        openSignInTab();
        cy.get("#memberSignInNobodyWarning").should("be.visible").and("contain.text", NO_CLASSIFICATION);
        cy.get("#memberSignInRuleWarning").should("be.visible").and("contain.text", NO_CLASSIFICATION);

        cy.get(`#memberSignInClassification-${EMPTY_CLASSIFICATION_ID}`).check();
        expectSave("sMemberSignInClassifications", [EMPTY_CLASSIFICATION_ID]);
        cy.get("#memberSignInMayCount").should("have.text", "0");
        cy.get("#memberSignInNobodyWarning").should("be.visible").and("contain.text", NO_MATCH);

        cy.get("#memberSignInClassification-1").check();
        expectSave("sMemberSignInClassifications", [1, EMPTY_CLASSIFICATION_ID]);
        cy.get("#memberSignInNobodyWarning").should("not.be.visible");
        cy.get("#memberSignInRuleWarning").should("not.be.visible");

        cy.get("#memberSignInRole-1").uncheck();
        expectSave("sMemberSignInFamilyRoles", [2]);
        cy.get("#memberSignInRole-2").uncheck();
        expectSave("sMemberSignInFamilyRoles", []);
        cy.get("#memberSignInNobodyWarning").should("be.visible").and("contain.text", NO_FAMILY_ROLE);
        cy.get("#memberSignInRuleWarning").should("be.visible").and("contain.text", NO_FAMILY_ROLE);
        cy.reload();
        cy.get("#memberSignInNobodyWarning").should("be.visible").and("contain.text", NO_FAMILY_ROLE);
    });

    it("asks before turning member sign-in off, saying members are signed out", () => {
        cy.makePrivateAdminAPICall("POST", SETTINGS_URL, { bMemberSignIn: true }, 200);
        openSignInTab();
        cy.get("#bMemberSignIn").should("be.checked").click();
        cy.get(".bootbox").should("be.visible").and("contain.text", "Members who are signed in now are signed out");
        answerConfirm("cancel");
        cy.get("#bMemberSignIn").should("be.checked");
        cy.get("@saveSignIn.all").should("have.length", 0);

        cy.get("#bMemberSignIn").click();
        answerConfirm("accept");
        expectSave("bMemberSignIn", false);
        cy.get("#bMemberSignIn").should("not.be.checked");
        cy.reload();
        cy.get("#bMemberSignIn").should("not.be.checked");
    });

    it("switches member sign-in back off when the server refuses it", () => {
        openSignInTab();
        cy.request({ method: "POST", url: "/admin/api/system/config/bEnabledEmail", body: { value: "0" } });
        cy.get("#bMemberSignIn").should("not.be.disabled").click();
        answerConfirm("accept");
        cy.wait("@saveSignIn").its("response.statusCode").should("eq", 400);
        cy.contains("so it can only be turned on while ChurchCRM can send email").should("be.visible");
        cy.get("#bMemberSignIn").should("not.be.checked");
        cy.request(SETTINGS_URL).its("body.values.bMemberSignIn").should("eq", "0");
    });

    it("can't turn member sign-in on while email is off, and says why", () => {
        cy.makePrivateAdminAPICall("POST", "/admin/api/system/config/bEnabledEmail", { value: "0" }, 200);
        cy.freshAdminFormLogin();
        cy.visit("/admin/member-portal#portal-signin");
        cy.get("#bMemberSignIn").should("be.disabled").and("not.be.checked");
        cy.get("#memberSignInEmailProblem").should("be.visible").find("a").should("have.attr", "href").and("include", "/admin/email");
    });

    it("lets an administrator disable and enable a person's sign-in from the person page", () => {
        cy.freshAdminFormLogin();
        cy.visit(`/people/view/${PERSON_ID}`);
        cy.get("#portal-signin-card").should("be.visible");
        cy.get("#portal-signin-disabled").should("not.exist");

        cy.get("#portal-signin-disable").click();
        cy.focused().should("have.id", "portal-signin-disable-reason");
        cy.get("#portal-signin-disable-confirm").click();
        cy.get("#portal-signin-disable-error").should("not.be.empty");
        cy.get("#portal-signin-disable-reason").type("Asked to close their access");
        cy.get("#portal-signin-disable-confirm").click();

        cy.get("#portal-signin-disabled", { timeout: 10000 }).should("contain.text", "Sign-in is disabled");
        cy.get("#portal-signin-disabled-reason").should("contain.text", "Asked to close their access");
        cy.get("#portal-signin-status").should("have.attr", "data-reason", "sign_in_disabled");

        cy.get("#portal-signin-enable").click();
        cy.get("#portal-signin-disable", { timeout: 10000 }).should("be.visible");
        cy.get("#portal-signin-disabled").should("not.exist");
    });

    it("shows staff with Edit Records the card without its controls", () => {
        cy.makePrivateAdminAPICall("POST", `/admin/api/person/${PERSON_ID}/sign-in/disable`, { reason: "Edit Records view" }, 200);
        cy.setupNoFinanceSession();
        cy.visit(`/people/view/${PERSON_ID}`);
        cy.get("#portal-signin-disabled").should("contain.text", "Sign-in is disabled");
        cy.get("#portal-signin-disabled-reason").should("not.exist");
        cy.get("#portal-signin-disable").should("not.exist");
        cy.get("#portal-signin-enable").should("not.exist");
        cy.get("#portal-signin-sign-out").should("not.exist");
    });
});
