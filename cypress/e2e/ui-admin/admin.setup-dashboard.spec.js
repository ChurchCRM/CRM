/// <reference types="cypress" />

import { captureInstallStart, restoreInstallStart, setInstallAgeDays } from "../../support/install-age";

const DAYS = "iSetupDashboardDays";
const FINISHED = "bSetupFinished";
const PROFILE = "sInstallDataProfile";
const CHURCH = "sChurchName";
const LANGUAGE = "sLanguage";
const EMAIL = "bEnabledEmail";
const MEMBER_SIGN_IN = "bMemberSignIn";
const SETUP = /\/admin\/setup$/;
const STANDARD = /\/people\/dashboard$/;
const saved = {};

const staffSignIn = () =>
    cy.readEnv("standard.username").then((username) =>
        cy.readEnv("standard.password").then((password) => {
            cy.clearCookies();
            cy.visit("/session/begin");
            cy.get("input[name=User]").type(username);
            cy.get("input[name=Password]").type(`${password}{enter}`, { log: false });
            cy.expectStaffSignedIn();
        }),
    );

describe("Admin of a new install signs in to the Setup Dashboard", () => {
    before(() => {
        captureInstallStart().then((start) => {
            saved.start = start;
        });
        [DAYS, FINISHED, PROFILE, CHURCH, LANGUAGE, EMAIL, MEMBER_SIGN_IN].forEach((key) => {
            cy.getSystemConfig(key).then((value) => {
                saved[key] = value;
            });
        });
    });

    beforeEach(() => {
        cy.setSystemConfig(DAYS, 30);
        cy.setSystemConfig(FINISHED, 0);
        cy.setSystemConfig(PROFILE, saved[PROFILE]);
        cy.setSystemConfig(CHURCH, saved[CHURCH]);
        cy.setSystemConfig(LANGUAGE, saved[LANGUAGE]);
        setInstallAgeDays(1);
    });

    after(() => {
        restoreInstallStart(saved.start);
        [DAYS, FINISHED, PROFILE, CHURCH, LANGUAGE, EMAIL, MEMBER_SIGN_IN].forEach((key) => cy.restoreSystemConfig(key, saved[key]));
    });

    it("keeps an admin with no church name on the three setup paths, until church information is saved", () => {
        cy.setSystemConfig(CHURCH, "");
        cy.freshAdminFormLogin();
        cy.location("pathname").should("match", SETUP);
        cy.get("#sidebar").should("not.exist");
        cy.get("#globalSearch").should("not.exist");
        cy.get("#finishSetup").should("not.exist");
        cy.get("#setupSteps").should("not.exist");

        cy.visit("/people/dashboard");
        cy.location("pathname").should("match", SETUP);
        cy.visit("/PersonEditor.php");
        cy.location("pathname").should("match", SETUP);
        cy.visit("/admin/system/restore");
        cy.location("pathname").should("match", /\/admin\/system\/restore$/);

        cy.visit("/admin/setup");
        cy.get("#importDemoDataSetup").click();
        cy.get("#demoImportModal").should("be.visible").and("contain.text", "PostHog");
        cy.get("#demoImportCancelBtn").should("be.focused").click();
        cy.get("#demoImportModal").should("not.be.visible");

        cy.get("#startChurchSetup").click();
        cy.location("pathname").should("match", /\/admin\/system\/church-info$/);
        cy.get("#sChurchName").clear().type(saved[CHURCH]);
        cy.get("#church-info-form button[type=submit]").click();

        cy.location("pathname").should("match", SETUP);
        cy.get("#setupSteps [data-step-done]").first().should("have.attr", "data-step-done", "true");
        cy.get("#globalSearch").should("exist");
    });

    it("starts in the browser's language, and the top bar picker changes it", () => {
        cy.setSystemConfig(CHURCH, "");
        cy.freshAdminFormLogin();
        cy.visit("/admin/setup", {
            onBeforeLoad(win) {
                win.localStorage.removeItem("churchcrm.setupLanguageDetected");
                Object.defineProperty(win.navigator, "languages", { value: ["es-MX", "es"] });
            },
        });
        cy.window().its("CRM.locale").should("match", /^es_/);

        cy.get("#setupLanguage").select("en_US");
        cy.window().its("CRM.locale").should("eq", "en_US");
    });

    it("lists the next steps once the church is set up, and finishes setup for good", () => {
        cy.freshAdminFormLogin();
        cy.location("pathname").should("match", SETUP);
        cy.get("#setupChoiceDemo #importDemoDataSetup").should("be.visible");
        cy.get("#setupChoiceRestore a").should("have.attr", "href").and("match", /\/admin\/system\/restore$/);
        cy.get("#setupSteps [data-step-done]").first().should("contain.text", "Church Information");

        cy.get("#finishSetup").click();
        cy.get(".bootbox .btn-primary").click();
        cy.location("pathname").should("match", STANDARD);

        cy.freshAdminFormLogin();
        cy.location("pathname").should("match", STANDARD);
        cy.getSystemConfig(FINISHED).should("eq", "1");
    });

    it("turns member sign-in on the first time a new install's email works", () => {
        cy.setSystemConfig(MEMBER_SIGN_IN, 0);
        cy.setSystemConfig(EMAIL, 0);
        cy.freshAdminFormLogin();
        cy.visit("/admin/email");
        cy.get("#bEnabledEmail").check();
        cy.get("#bEnabledEmail").closest("form").find("button[type=submit]").click();
        cy.contains("Member sign-in is now on").should("be.visible");
        cy.getSystemConfig(MEMBER_SIGN_IN).should("eq", "1");
    });

    it("leaves a staff user's sign-in on the standard dashboard", () => {
        staffSignIn();
        cy.location("pathname").should("match", STANDARD);
    });

    it("marks demo mode on every page and offers the admin Start Fresh", () => {
        cy.setSystemConfig(PROFILE, "demo");
        cy.freshAdminFormLogin();
        cy.location("pathname").should("match", SETUP);
        cy.get("#demoModeBanner .js-start-fresh").should("be.visible");
        cy.get("#setupChoiceDemo")
            .should("contain.text", "You're exploring demo data")
            .find(".js-start-fresh")
            .should("be.visible");
        cy.get("#startChurchSetup").should("not.exist");
        cy.get("#setupSteps").should("not.exist");

        cy.visit("/people/dashboard");
        cy.get("#demoModeBanner").should("be.visible");

        staffSignIn();
        cy.get("#demoModeBanner").should("be.visible").find(".js-start-fresh").should("not.exist");
    });
});
