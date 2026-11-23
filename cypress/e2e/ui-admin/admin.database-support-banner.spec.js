/// <reference types="cypress" />

const banner = '.crm-system-notification[data-notification-id="database-out-of-support"]';

const belowSupportedFloor = (serverVersion) => {
    const isMariaDb = /mariadb/i.test(serverVersion);
    const [major, minor] = serverVersion.replace(/^5\.5\.5-/, "").match(/\d+\.\d+/)[0].split(".").map(Number);
    const [floorMajor, floorMinor] = isMariaDb ? [10, 11] : [8, 4];
    return major < floorMajor || (major === floorMajor && minor < floorMinor);
};

describe("Database out-of-support banner", () => {
    it("follows the running database version for an admin", () => {
        cy.setupAdminSession();
        cy.visit("/admin/system/debug");
        cy.contains("#env-db td", "Server Version")
            .next()
            .invoke("text")
            .then((serverVersion) => {
                cy.visit("/people/dashboard");
                if (belowSupportedFloor(serverVersion.trim())) {
                    cy.get(banner)
                        .should("be.visible")
                        .and("contain", "is out of support and might be unsafe")
                        .find(".js-dismiss-notification")
                        .should("not.exist");
                } else {
                    cy.get(banner).should("not.exist");
                }
            });
    });

    it("is not shown to a standard user", () => {
        cy.setupStandardSession();
        cy.visit("/people/dashboard");
        cy.get(banner).should("not.exist");
    });
});
