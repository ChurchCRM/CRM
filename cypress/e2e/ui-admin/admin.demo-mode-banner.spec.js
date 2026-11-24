/// <reference types="cypress" />

import { captureInstallStart, restoreInstallStart, setInstallAgeDays } from "../../support/install-age";

const DAYS = "iSetupDashboardDays";
const FINISHED = "bSetupFinished";
const PROFILE = "sInstallDataProfile";
const item = '[data-notification-id="demo-mode"]';
const saved = {};

// Signs in without cy.session / freshAdminFormLogin, which dismiss notifications.
const freshLogin = (usernameKey, passwordKey) =>
    cy.readEnv(usernameKey).then((username) =>
        cy.readEnv(passwordKey).then((password) => {
            cy.clearAllCookies();
            cy.visit("/session/begin");
            cy.get("input[name=User]").type(username);
            cy.get("input[name=Password]").type(`${password}{enter}`, { log: false });
            cy.expectStaffSignedIn();
        }),
    );

describe("Demo mode notice", () => {
    before(() => {
        captureInstallStart().then((start) => {
            saved.start = start;
        });
        [DAYS, FINISHED, PROFILE].forEach((key) => {
            cy.getSystemConfig(key).then((value) => {
                saved[key] = value;
            });
        });
    });

    beforeEach(() => {
        cy.setSystemConfig(DAYS, 30);
        cy.setSystemConfig(FINISHED, 0);
        cy.setSystemConfig(PROFILE, "demo");
        setInstallAgeDays(1);
    });

    after(() => {
        restoreInstallStart(saved.start);
        [DAYS, FINISHED, PROFILE].forEach((key) => cy.restoreSystemConfig(key, saved[key]));
    });

    it("lives in the notification center with Start Fresh for the admin, not as a page banner", () => {
        freshLogin("admin.username", "admin.password");
        cy.visit("/people/dashboard");
        cy.get("#demoModeBanner").should("not.exist");
        cy.get("#notificationCenterMenu").click();
        cy.get(item).should("be.visible").and("contain", "demo data").find(".js-start-fresh").should("be.visible");
    });

    it("shows to a standard user without Start Fresh", () => {
        freshLogin("standard.username", "standard.password");
        cy.visit("/people/dashboard");
        cy.get("#notificationCenterMenu").click();
        cy.get(item).should("be.visible").find(".js-start-fresh").should("not.exist");
    });

    it("is gone when demo data is not loaded", () => {
        cy.setSystemConfig(PROFILE, "production");
        freshLogin("admin.username", "admin.password");
        cy.visit("/people/dashboard");
        cy.get(item).should("not.exist");
    });
});
