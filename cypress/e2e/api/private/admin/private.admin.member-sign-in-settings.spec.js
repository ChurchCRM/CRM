/// <reference types="cypress" />

/**
 * Member sign-in row 4 (#10463): the Admin → Member Portal → Sign-in settings
 * (docs/member-sign-in-design.md §11.1) through POST /admin/api/member-portal/sign-in.
 */

const URL = "/admin/api/member-portal/sign-in";
const KEYS = [
    "bMemberSignIn",
    "sMemberSignInFamilyRoles",
    "sMemberSignInClassifications",
    "iMemberSignInMinAge",
    "iSignInLinkMinutes",
    "iPortalIdleMinutes",
    "iRememberDeviceDays",
    "bPortalPasskeys",
    "sMemberSignInNotificationRecipientIDs",
    "bAllowLoginAsUser",
    "bLoginAsUserCanWrite",
];

const settings = () => cy.makePrivateAdminAPICall("GET", URL, null, 200).its("body");
const save = (body, expectedStatus = 200) => cy.makePrivateAdminAPICall("POST", URL, body, expectedStatus);

describe("Member sign-in settings API", () => {
    const saved = {};
    let savedEmailEnabled;

    before(() => {
        settings().then((body) => {
            KEYS.forEach((key) => {
                saved[key] = body.values[key];
            });
        });
        cy.getSystemConfig("bEnabledEmail").then((value) => {
            savedEmailEnabled = value;
        });
    });

    afterEach(() => {
        cy.restoreSystemConfig("bEnabledEmail", savedEmailEnabled);
        save(saved);
    });

    it("lists every setting with the family roles and classifications to choose from", () => {
        settings().then((body) => {
            expect(Object.keys(body.values)).to.have.members(KEYS);
            expect(body.familyRoles.map((role) => role.name)).to.include.members(["Head of Household", "Spouse", "Child"]);
            expect(body.familyRoles.find((role) => role.id === 0)).to.exist;
            const member = body.classifications.find((choice) => choice.name === "Member");
            expect(member.activeCount).to.be.a("number").and.be.greaterThan(0);
            expect(body.classifications.find((choice) => choice.id === 0).name).to.eq("Unassigned");
            expect(body.maySignInCount).to.be.a("number");
            expect(body.emailProblem).to.be.null;
        });
    });

    it("saves every setting and returns what it stored", () => {
        save({
            sMemberSignInFamilyRoles: [3, 1, 2],
            sMemberSignInClassifications: "2, 1",
            iMemberSignInMinAge: 16,
            iSignInLinkMinutes: 60,
            iPortalIdleMinutes: 15,
            iRememberDeviceDays: 0,
            bPortalPasskeys: false,
            sMemberSignInNotificationRecipientIDs: [2, 6],
            bAllowLoginAsUser: true,
            bLoginAsUserCanWrite: true,
        }).then(({ body }) => {
            expect(body.values).to.include({
                sMemberSignInFamilyRoles: "1,2,3",
                sMemberSignInClassifications: "1,2",
                iMemberSignInMinAge: "16",
                iSignInLinkMinutes: "60",
                iPortalIdleMinutes: "15",
                iRememberDeviceDays: "0",
                bPortalPasskeys: "0",
                sMemberSignInNotificationRecipientIDs: "2,6",
                bAllowLoginAsUser: "1",
                bLoginAsUserCanWrite: "1",
            });
            expect(body.maySignInCount).to.be.a("number").and.be.greaterThan(0);
        });
        settings().then((body) => {
            expect(body.values.iSignInLinkMinutes).to.eq("60");
            expect(body.officeRecipients.map((person) => person.id)).to.deep.eq([2, 6]);
        });
    });

    it("checks each setting and saves none when one is wrong", () => {
        save({ iSignInLinkMinutes: 45 });
        save(
            {
                iSignInLinkMinutes: 90,
                iMemberSignInMinAge: 121,
                iPortalIdleMinutes: 0,
                iRememberDeviceDays: 366,
                sMemberSignInFamilyRoles: [99],
                sMemberSignInClassifications: ["guest"],
                sMemberSignInNotificationRecipientIDs: [1],
                bPortalPasskeys: "maybe",
            },
            400,
        ).then(({ body }) => {
            expect(Object.keys(body.errors)).to.have.members([
                "iMemberSignInMinAge",
                "iPortalIdleMinutes",
                "iRememberDeviceDays",
                "sMemberSignInFamilyRoles",
                "sMemberSignInClassifications",
                "sMemberSignInNotificationRecipientIDs",
                "bPortalPasskeys",
            ]);
            expect(body.message).to.be.a("string").and.not.be.empty;
        });
        settings().its("values.iSignInLinkMinutes").should("eq", "45");
    });

    it("keeps people without their own email, and the deceased, off the office list", () => {
        save({ sMemberSignInNotificationRecipientIDs: [19] }, 400)
            .its("body.errors")
            .should("have.property", "sMemberSignInNotificationRecipientIDs");
        cy.makePrivateAdminAPICall("GET", `${URL}/office-candidates/campbell`, null, 200).then(({ body }) => {
            expect(body.map((person) => person.objid)).to.include(2);
            body.forEach((person) => expect(person.text).to.match(/\(.+@.+\)$/));
        });
        cy.makePrivateAdminAPICall("GET", `${URL}/office-candidates/admin`, null, 200).then(({ body }) => {
            const ids = body.map((person) => person.objid);
            expect(ids).to.include(906);
            expect(ids).not.to.include(1);
        });
    });

    it("turns member sign-in on only while email can be sent", () => {
        cy.makePrivateAdminAPICall("POST", "/admin/api/system/config/bEnabledEmail", { value: "0" }, 200);
        settings().its("emailProblem").should("be.a", "string");
        save({ bMemberSignIn: true }, 400).its("body.errors").should("have.property", "bMemberSignIn");
        cy.makePrivateAdminAPICall("POST", "/admin/api/system/config/bMemberSignIn", { value: "1" }, 400);
        settings().its("values.bMemberSignIn").should("eq", "0");

        cy.makePrivateAdminAPICall("POST", "/admin/api/system/config/bEnabledEmail", { value: "1" }, 200);
        save({ bMemberSignIn: true }).its("body.values.bMemberSignIn").should("eq", "1");
        save({ bMemberSignIn: false });
    });

    it("is for administrators only", () => {
        cy.makePrivateEditRecordsAPICall("GET", URL, null, 403);
        cy.makePrivateEditRecordsAPICall("POST", URL, { iSignInLinkMinutes: 5 }, 403);
        cy.makePrivateEditRecordsAPICall("GET", `${URL}/office-candidates/campbell`, null, 403);
    });

    it("refuses a browser save without the page's CSRF token", () => {
        cy.clearCookies();
        cy.readEnv("admin.username").then((username) =>
            cy.readEnv("admin.password").then((password) =>
                cy.request({ method: "POST", url: "/session/begin", form: true, body: { User: username, Password: password } }),
            ),
        );
        cy.request({ method: "POST", url: URL, body: { iSignInLinkMinutes: 30 }, failOnStatusCode: false })
            .its("status")
            .should("eq", 403);
        cy.request("/admin/member-portal").then((page) => {
            const token = page.body.match(/"csrfToken":"([0-9a-f]{64})"/)[1];
            cy.request({ method: "POST", url: URL, headers: { "X-CSRF-Token": token }, body: { iSignInLinkMinutes: 30 } })
                .its("body.values.iSignInLinkMinutes")
                .should("eq", "30");
        });
        cy.clearCookies();
    });
});
