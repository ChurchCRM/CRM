/// <reference types="cypress" />

const KEY = "churchcrm.desktopSidebarCollapsed";

function expectLayout(sidebarWidth = 0) {
    cy.get(".page-wrapper").should(($el) => {
        const wrapper = $el[0];
        const viewport = wrapper.ownerDocument.documentElement;
        const bounds = wrapper.getBoundingClientRect();
        expect(bounds.left).to.be.closeTo(sidebarWidth, 1);
        expect(bounds.right).to.be.closeTo(viewport.clientWidth, 1);
        expect(viewport.scrollWidth).to.be.at.most(viewport.clientWidth + 1);
        const content = wrapper.querySelector("#main-content > .container-xl").getBoundingClientRect();
        expect(content.left - bounds.left).to.be.closeTo(bounds.right - content.right, 1);
    });
}

function capture(name) {
    cy.get("#main-content").should("not.contain.text", "Loading");
    cy.scrollTo("top");
    cy.screenshot(name, { capture: "viewport", scale: true });
}

describe("Staff desktop sidebar preference", () => {
    beforeEach(() => {
        cy.setupStandardSession();
        cy.viewport(1440, 900);
        cy.visit("/v2/dashboard");
        cy.window().then((win) => win.localStorage.removeItem(KEY));
        cy.reload();
    });

    afterEach(() => {
        cy.window().then((win) => win.localStorage.removeItem(KEY));
    });

    for (const theme of ["light", "dark"]) {
        it(`collapses and expands the desktop sidebar in ${theme} mode`, () => {
            cy.document().then((doc) => doc.documentElement.setAttribute("data-bs-theme", theme));
            cy.get("#desktop-sidebar-toggle")
                .should("be.visible")
                .and("have.attr", "aria-expanded", "true");
            cy.get("#sidebar").should("be.visible");
            expectLayout(256);
            capture(`sidebar-desktop-1440x900-${theme}-expanded`);

            cy.get("#desktop-sidebar-toggle").click();
            cy.get("#sidebar").should("not.be.visible");
            expectLayout();
            cy.get("#desktop-sidebar-toggle")
                .should("have.attr", "aria-expanded", "false")
                .and("have.attr", "aria-label", "Expand sidebar");
            capture(`sidebar-desktop-1440x900-${theme}-collapsed`);

            cy.get("#desktop-sidebar-toggle").click();
            cy.get("#sidebar").should("be.visible");
            cy.get("#desktop-sidebar-toggle")
                .should("have.attr", "aria-expanded", "true")
                .and("have.attr", "aria-label", "Collapse sidebar");
            expectLayout(256);
        });
    }

    it("persists the preference across reload", () => {
        cy.get("#desktop-sidebar-toggle").click();
        cy.window().its("localStorage").invoke("getItem", KEY).should("eq", "true");
        cy.reload();
        cy.get("#sidebar").should("not.be.visible");
        expectLayout();
        cy.visit("/people/dashboard");
        cy.get("#sidebar").should("not.be.visible");
        expectLayout();
        cy.get("#desktop-sidebar-toggle").click();
        cy.reload();
        cy.get("#sidebar").should("be.visible");
        expectLayout(256);
    });

    it("still toggles when browser storage is unavailable", () => {
        cy.visit("/v2/dashboard", {
            onBeforeLoad(win) {
                cy.stub(win.Storage.prototype, "getItem").withArgs(KEY).throws(new Error("Storage blocked"));
                cy.stub(win.Storage.prototype, "setItem").withArgs(KEY, Cypress.sinon.match.any).throws(new Error("Storage blocked"));
            },
        });
        cy.get("#desktop-sidebar-toggle").click();
        cy.get("#sidebar").should("not.be.visible");
        expectLayout();
        cy.get("#desktop-sidebar-toggle").click();
        cy.get("#sidebar").should("be.visible");
        expectLayout(256);
    });

    it("keeps the congregation map centered when the available width changes", () => {
        cy.visit("/people/map");
        const expectMapCenter = () => {
            cy.get('#map img.leaflet-marker-icon[src$="/church.png"]').should(($marker) => {
                const marker = $marker[0].getBoundingClientRect();
                const map = $marker[0].closest("#map").getBoundingClientRect();
                expect(marker.left + marker.width / 2).to.be.closeTo(map.left + map.width / 2, 2);
            });
        };
        expectMapCenter();
        cy.get("#desktop-sidebar-toggle").click();
        expectMapCenter();
        cy.get("#desktop-sidebar-toggle").click();
        expectMapCenter();
    });

    for (const theme of ["light", "dark"]) {
        for (const [name, width] of [["tablet", 820], ["phone", 390]]) {
            it(`keeps the ${name} hamburger after a desktop collapse in ${theme} mode`, () => {
                cy.document().then((doc) => doc.documentElement.setAttribute("data-bs-theme", theme));
                cy.get("#desktop-sidebar-toggle").click();
                cy.viewport(width, 820);
                cy.get("#desktop-sidebar-toggle").should("not.be.visible");
                cy.get("#sidebar").should("be.visible");
                expectLayout();
                capture(`sidebar-${name}-${width}x820-${theme}-closed`);
                cy.get("#menu-toggle").should("be.visible").click();
                cy.get("#sidebar-menu").should("have.class", "show");
                expectLayout();
                capture(`sidebar-${name}-${width}x820-${theme}-open`);
                cy.get("#menu-toggle").click();
                cy.get("#sidebar-menu").should("not.be.visible");
                cy.viewport(1200, 900);
                cy.get("#sidebar").should("not.be.visible");
                expectLayout();
                cy.get("#desktop-sidebar-toggle").should("be.visible").click();
                cy.get("#sidebar").should("be.visible");
                expectLayout(256);
            });
        }
    }
});
