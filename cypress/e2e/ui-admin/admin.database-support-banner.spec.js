/// <reference types="cypress" />

const item = '#notificationCenterMenuItem [data-notification-id="database-out-of-support"]';
const dismissKey = "notification.dismissed.database-out-of-support";

const belowSupportedFloor = (serverVersion) => {
    const isMariaDb = /mariadb/i.test(serverVersion);
    const [major, minor] = serverVersion.replace(/^5\.5\.5-/, "").match(/\d+\.\d+/)[0].split(".").map(Number);
    const [floorMajor, floorMinor] = isMariaDb ? [10, 11] : [8, 4];
    return major < floorMajor || (major === floorMajor && minor < floorMinor);
};

const readServerVersion = () => {
    cy.visit("/admin/system/debug");
    return cy
        .contains("#env-db td", "Server Version")
        .next()
        .invoke("text")
        .then((text) => text.trim());
};

// Dismissal is a per-user setting with no un-dismiss UI; undo it through the
// app's own API so a run never leaves the shared admin with the notice hidden.
const resetDismissal = () => {
    cy.window().then((win) =>
        win.CRM.APIRequest({
            path: `user/${win.CRM.userId}/setting/${encodeURIComponent(dismissKey)}`,
            method: "POST",
            data: JSON.stringify({ value: "false" }),
        }),
    );
};

// Signs in without cy.session, whose setup dismisses every notification.
const freshLogin = (usernameKey, passwordKey) =>
    cy.readEnv(usernameKey).then((username) =>
        cy.readEnv(passwordKey).then((password) => {
            cy.clearAllCookies();
            cy.visit("/session/begin");
            cy.get("input[name=User]").type(username);
            cy.get("input[name=Password]").type(`${password}{enter}`);
            cy.expectStaffSignedIn();
        }),
    );

const openCenter = () => cy.get("#notificationCenterMenu").click();

describe("Database out-of-support notice", () => {
    it("follows the running database version on the dashboard and the upgrader", () => {
        cy.setupAdminSession();
        cy.visit("/people/dashboard");
        resetDismissal();
        readServerVersion().then((serverVersion) => {
            for (const page of ["/people/dashboard", "/admin/system/upgrade"]) {
                cy.visit(page);
                if (belowSupportedFloor(serverVersion)) {
                    openCenter();
                    cy.get(item)
                        .should("be.visible")
                        .and("contain", "is a security risk")
                        .and("contain", "no longer ships security fixes")
                        .find(".js-dismiss-notification")
                        .should("exist");
                    cy.get(item).find(".js-notification-more").click();
                    cy.get("#notificationDetailPanel").should("be.visible").and("contain", "is a security risk");
                    cy.get("#notificationDetailPanel .btn-close").click();
                    cy.get("#notificationDetailPanel").should("not.be.visible");
                } else {
                    cy.get(item).should("not.exist");
                }
            }
            cy.get("#upgrade-wizard-card").should("be.visible");
        });
    });

    it("dismisses from the notification center and stays dismissed per user", () => {
        cy.setupAdminSession();
        cy.visit("/people/dashboard");
        resetDismissal();
        readServerVersion().then((serverVersion) => {
            cy.visit("/people/dashboard");
            if (!belowSupportedFloor(serverVersion)) {
                cy.get(item).should("not.exist");
                return;
            }
            openCenter();
            cy.get(item).should("be.visible");
            cy.intercept("POST", `**/api/user/*/setting/${dismissKey}`).as("dismissDbWarning");
            cy.get(item).find(".js-dismiss-notification").click();
            cy.wait("@dismissDbWarning");
            cy.get(item).should("not.exist");

            freshLogin("admin.username", "admin.password");
            cy.visit("/people/dashboard");
            cy.get(item).should("not.exist");

            freshLogin("locale.admin.username", "locale.admin.password");
            resetDismissal();
            cy.visit("/people/dashboard");
            openCenter();
            cy.get(item).should("be.visible");

            freshLogin("admin.username", "admin.password");
            resetDismissal();
            cy.visit("/people/dashboard");
            openCenter();
            cy.get(item).should("be.visible");
        });
    });

    it("is not shown to a standard user", () => {
        cy.setupStandardSession();
        cy.visit("/people/dashboard");
        cy.get(item).should("not.exist");
    });
});
