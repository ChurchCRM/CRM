/// <reference types="cypress" />

/**
 * Member sign-in row 13 (#10463): what an administrator sees after the
 * release-day cutover (docs/member-sign-in-design.md §12). The 7.9.0 upgrade
 * records how many self-service accounts it removed in
 * iMemberSignInFormerAccounts; the Admin Dashboard asks to turn member sign-in on
 * until it is on or the notice is dismissed. Admin → Users lists staff accounts
 * only.
 */
const CONFIG_URL = "/admin/api/system/config";
const NOTICE = "#former-portal-accounts-notice";

const setConfig = (name, value) => cy.makePrivateAdminAPICall("POST", `${CONFIG_URL}/${name}`, { value }, 200);
const config = (name) => cy.makePrivateAdminAPICall("GET", `${CONFIG_URL}/${name}`, null, 200).its("body.value");

describe("After the release-day cutover", () => {
    let smtpHost;

    before(() => {
        config("sSMTPHost").then((value) => {
            smtpHost = value;
        });
    });

    afterEach(() => {
        setConfig("sSMTPHost", smtpHost);
    });

    after(() => {
        setConfig("iMemberSignInFormerAccounts", "0");
        setConfig("bMemberSignIn", "0");
    });

    describe("the Admin Dashboard notice", () => {
        it("asks to turn member sign-in on, links to the Sign-in tab, and stays dismissed", () => {
            setConfig("iMemberSignInFormerAccounts", "3");
            setConfig("bMemberSignIn", "0");
            cy.setupAdminSession();
            cy.visit("/admin/");

            cy.get(NOTICE)
                .should("be.visible")
                .and("contain.text", "Member sign-in is off")
                .and("contain.text", "People who used to sign in to the Member Portal: 3");
            cy.get("#former-portal-accounts-email").should("contain.text", "email (SMTP) must work before you turn it on");
            cy.get("#former-portal-accounts-email-setup").should("not.exist");
            cy.get("#former-portal-accounts-turn-on").should("have.attr", "href", "/admin/member-portal#portal-signin");

            cy.intercept("POST", "**/admin/api/member-portal/former-accounts-notice/dismiss").as("dismiss");
            cy.get(`${NOTICE} .js-dismiss-former-portal-accounts`).click();
            cy.wait("@dismiss").its("response.statusCode").should("eq", 200);
            cy.get(NOTICE).should("not.exist");

            cy.reload();
            cy.get(".container-fluid").should("exist");
            cy.get(NOTICE).should("not.exist");
            config("iMemberSignInFormerAccounts").should("eq", "0");
        });

        it("sends the administrator to set up email while SMTP is not set", () => {
            setConfig("iMemberSignInFormerAccounts", "3");
            setConfig("bMemberSignIn", "0");
            setConfig("sSMTPHost", "");
            cy.setupAdminSession();
            cy.visit("/admin/");
            cy.get("#former-portal-accounts-email-setup").should("have.attr", "href", "/admin/email");
        });

        it("is not shown while member sign-in is on", () => {
            setConfig("iMemberSignInFormerAccounts", "2");
            setConfig("bMemberSignIn", "1");
            cy.setupAdminSession();
            cy.visit("/admin/");
            cy.get(".container-fluid").should("exist");
            cy.get(NOTICE).should("not.exist");
        });

        it("is not shown when no self-service account was removed", () => {
            setConfig("iMemberSignInFormerAccounts", "0");
            setConfig("bMemberSignIn", "0");
            cy.setupAdminSession();
            cy.visit("/admin/");
            cy.get(".container-fluid").should("exist");
            cy.get(NOTICE).should("not.exist");
        });

        it("goes away for good once member sign-in is turned on from the Sign-in tab", () => {
            setConfig("iMemberSignInFormerAccounts", "4");
            setConfig("bMemberSignIn", "0");
            cy.makePrivateAdminAPICall("POST", "/admin/api/member-portal/sign-in", { bMemberSignIn: "1" }, 200);
            config("iMemberSignInFormerAccounts").should("eq", "0");
        });

        it("refuses the dismissal to anyone but an administrator", () => {
            cy.makePrivateUserAPICall("POST", "/admin/api/member-portal/former-accounts-notice/dismiss", null, 403);
        });
    });

    describe("Admin → Users", () => {
        it("lists staff accounts only, with no self-service legend or icon", () => {
            cy.setupAdminSession();
            cy.visit("/admin/system/users");
            cy.get("#user-listing-table").should("contain.text", "tony.wade@example.com");
            cy.get("#user-listing-table").should("not.contain.text", "lena.black.editself.notes@example.com");
            cy.get("#user-listing-table").should("not.contain.text", "limited.user");
            cy.get("body").should("not.contain.text", "Self-service");
            cy.get("#user-listing-table .fa-user-check").should("not.exist");
        });
    });
});
