/// <reference types="cypress" />

import { captureInstallStart, restoreInstallStart, setInstallAgeDays } from "../../support/install-age";

const DAYS = "iSetupDashboardDays";
const FINISHED = "bSetupFinished";
const PROFILE = "sInstallDataProfile";
const banner = "#demoModeBanner";
const navIndicator = "#demoModeMenuItem";
const dismissKey = "notification.dismissed.demo-mode";
const saved = {};

// Dismissal is a per-user setting with no un-dismiss UI; undo it through the
// app's own API so a run never leaves the shared users with the banner hidden.
const resetDismissal = () => {
    cy.window().then((win) =>
        win.CRM.APIRequest({
            path: `user/${win.CRM.userId}/setting/${encodeURIComponent(dismissKey)}`,
            method: "POST",
            data: JSON.stringify({ value: "false" }),
        }),
    );
};

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

describe("Demo mode banner", () => {
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

    it("shows with Start Fresh for the admin, and no nav indicator yet", () => {
        freshLogin("admin.username", "admin.password");
        resetDismissal();
        cy.visit("/people/dashboard");
        cy.get(banner).should("be.visible").and("contain", "demo data").find(".js-start-fresh").should("be.visible");
        cy.get(navIndicator).should("not.exist");
    });

    it("collapses into the top nav when dismissed and reopens from there", () => {
        freshLogin("admin.username", "admin.password");
        resetDismissal();
        cy.visit("/people/dashboard");
        cy.get(banner).should("be.visible");
        cy.intercept("POST", `**/api/user/*/setting/${dismissKey}`).as("dismissDemoBanner");
        cy.get(banner).find(".js-dismiss-notification").click();
        cy.wait("@dismissDemoBanner");
        cy.get(banner).should("not.exist");

        freshLogin("admin.username", "admin.password");
        cy.visit("/people/dashboard");
        cy.get(banner).should("not.exist");
        cy.get(navIndicator).should("be.visible");
        cy.get("#demoModeMenu").click();
        cy.get(navIndicator)
            .find(".dropdown-menu")
            .should("be.visible")
            .and("contain", "demo data")
            .and("contain", "Start fresh")
            .find(".js-start-fresh")
            .should("be.visible");
        resetDismissal();
    });

    it("shows to a standard user without Start Fresh", () => {
        freshLogin("standard.username", "standard.password");
        cy.visit("/people/dashboard");
        cy.get(banner).should("be.visible").find(".js-start-fresh").should("not.exist");
        cy.get(navIndicator).should("not.exist");
    });
});
