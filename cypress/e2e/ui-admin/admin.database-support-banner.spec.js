/// <reference types="cypress" />

const banner = '.crm-system-notification[data-notification-id="database-out-of-support"]';

const belowSupportedFloor = (serverVersion) => {
    const isMariaDb = /mariadb/i.test(serverVersion);
    const [major, minor] = serverVersion.replace(/^5\.5\.5-/, "").match(/\d+\.\d+/)[0].split(".").map(Number);
    const [floorMajor, floorMinor] = isMariaDb ? [10, 11] : [8, 4];
    return major < floorMajor || (major === floorMajor && minor < floorMinor);
};

describe("Database out-of-support banner", () => {
    it("follows the running database version on the dashboard and the upgrader", () => {
        cy.setupAdminSession();
        cy.visit("/admin/system/debug");
        cy.contains("#env-db td", "Server Version")
            .next()
            .invoke("text")
            .then((serverVersion) => {
                for (const page of ["/people/dashboard", "/admin/system/upgrade"]) {
                    cy.visit(page);
                    if (belowSupportedFloor(serverVersion.trim())) {
                        cy.get(banner)
                            .should("be.visible")
                            .and("contain", "is a security risk")
                            .and("contain", "no longer ships security fixes")
                            .find(".js-dismiss-notification")
                            .should("not.exist");
                    } else {
                        cy.get(banner).should("not.exist");
                    }
                }
                cy.get("#upgrade-wizard-card").should("be.visible");
            });
    });

    it("is not shown to a standard user", () => {
        cy.setupStandardSession();
        cy.visit("/people/dashboard");
        cy.get(banner).should("not.exist");
    });
});
