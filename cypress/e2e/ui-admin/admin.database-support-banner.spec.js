/// <reference types="cypress" />

const banner = '.crm-system-notification[data-notification-id="database-out-of-support"]';
const navIndicator = "#databaseSupportMenuItem";
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
// app's own API so a run never leaves the shared admin with the banner hidden.
const resetDismissal = () => {
    cy.window().then((win) =>
        win.CRM.APIRequest({
            path: `user/${win.CRM.userId}/setting/${encodeURIComponent(dismissKey)}`,
            method: "POST",
            data: JSON.stringify({ value: "false" }),
        }),
    );
};

describe("Database out-of-support banner", () => {
    it("follows the running database version on the dashboard and the upgrader", () => {
        cy.setupAdminSession();
        cy.visit("/people/dashboard");
        resetDismissal();
        readServerVersion().then((serverVersion) => {
            for (const page of ["/people/dashboard", "/admin/system/upgrade"]) {
                cy.visit(page);
                if (belowSupportedFloor(serverVersion)) {
                    cy.get(banner)
                        .should("be.visible")
                        .and("contain", "is a security risk")
                        .and("contain", "no longer ships security fixes")
                        .find(".js-dismiss-notification")
                        .should("exist");
                    cy.get(navIndicator).should("not.exist");
                } else {
                    cy.get(banner).should("not.exist");
                    cy.get(navIndicator).should("not.exist");
                }
            }
            cy.get("#upgrade-wizard-card").should("be.visible");
        });
    });

    it("collapses into the top nav when dismissed and reopens from there", () => {
        cy.setupAdminSession();
        cy.visit("/people/dashboard");
        resetDismissal();
        readServerVersion().then((serverVersion) => {
            cy.visit("/people/dashboard");
            if (!belowSupportedFloor(serverVersion)) {
                cy.get(banner).should("not.exist");
                cy.get(navIndicator).should("not.exist");
                return;
            }
            cy.get(banner).should("be.visible");
            cy.intercept("POST", `**/api/user/*/setting/${dismissKey}`).as("dismissDbWarning");
            cy.get(banner).find(".js-dismiss-notification").click();
            cy.wait("@dismissDbWarning");
            cy.get(banner).should("not.exist");

            // The dismissal is per-user and persists: after a fresh page load
            // the banner stays gone and the warning lives in the top nav.
            cy.visit("/people/dashboard");
            cy.get(banner).should("not.exist");
            cy.get(navIndicator).should("be.visible");
            cy.get("#databaseSupportMenu").click();
            cy.get(navIndicator)
                .find(".dropdown-menu")
                .should("be.visible")
                .and("contain", "Database out of support")
                .and("contain", "is a security risk");

            // Restore the banner for the next run.
            resetDismissal();
            cy.visit("/people/dashboard");
            cy.get(banner).should("be.visible");
            cy.get(navIndicator).should("not.exist");
        });
    });

    it("is not shown to a standard user", () => {
        cy.setupStandardSession();
        cy.visit("/people/dashboard");
        cy.get(banner).should("not.exist");
        cy.get(navIndicator).should("not.exist");
    });
});
