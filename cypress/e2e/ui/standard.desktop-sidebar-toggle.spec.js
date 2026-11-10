/// <reference types="cypress" />

const FOLDED = "navbar-folded-hover";
const FULL_WIDTH = 256;
const FOLDED_WIDTH = 64;

function expectLayout(sidebarWidth) {
    cy.get(".page-wrapper").should(($el) => {
        const wrapper = $el[0];
        const viewport = wrapper.ownerDocument.documentElement;
        const bounds = wrapper.getBoundingClientRect();
        expect(bounds.left).to.be.closeTo(sidebarWidth, 1);
        expect(bounds.right).to.be.closeTo(viewport.clientWidth, 1);
        expect(viewport.scrollWidth).to.be.at.most(viewport.clientWidth + 1);
    });
}

function capture(name) {
    cy.get("#main-content").should("not.contain.text", "Loading");
    cy.scrollTo("top");
    cy.screenshot(name, { capture: "viewport", scale: true });
}

describe("Staff folded sidebar preference", () => {
    beforeEach(() => {
        cy.setupStandardSession();
        cy.viewport(1440, 900);
        cy.visit("/v2/dashboard");
        cy.get("#sidebar").then(($sidebar) => {
            if ($sidebar.hasClass(FOLDED)) {
                cy.get("#desktop-sidebar-toggle").click();
                cy.reload();
            }
        });
    });

    for (const theme of ["light", "dark"]) {
        it(`folds and unfolds the desktop sidebar in ${theme} mode`, () => {
            cy.document().then((doc) => doc.documentElement.setAttribute("data-bs-theme", theme));
            cy.get("#desktop-sidebar-toggle").should("be.visible").and("have.attr", "aria-expanded", "true");
            cy.get("#sidebar").should("not.have.class", FOLDED);
            expectLayout(FULL_WIDTH);
            capture(`sidebar-desktop-1440x900-${theme}-expanded`);

            cy.get("#desktop-sidebar-toggle").click();
            cy.get("#sidebar").should("have.class", FOLDED).and("be.visible");
            expectLayout(FOLDED_WIDTH);
            cy.get("#desktop-sidebar-toggle")
                .should("have.attr", "aria-expanded", "false")
                .and("have.attr", "aria-label", "Expand sidebar");
            capture(`sidebar-desktop-1440x900-${theme}-folded`);

            cy.get("#desktop-sidebar-toggle").click();
            cy.get("#sidebar").should("not.have.class", FOLDED);
            cy.get("#desktop-sidebar-toggle")
                .should("have.attr", "aria-expanded", "true")
                .and("have.attr", "aria-label", "Collapse sidebar");
            expectLayout(FULL_WIDTH);
        });
    }

    it("stores the preference on the user and restores it on reload and on other pages", () => {
        cy.get("#desktop-sidebar-toggle").click();
        cy.reload();
        cy.get("#sidebar").should("have.class", FOLDED);
        expectLayout(FOLDED_WIDTH);
        cy.visit("/people/dashboard");
        cy.get("#sidebar").should("have.class", FOLDED);
        expectLayout(FOLDED_WIDTH);
        cy.get("#desktop-sidebar-toggle").click();
        cy.reload();
        cy.get("#sidebar").should("not.have.class", FOLDED);
        expectLayout(FULL_WIDTH);
    });

    it("keeps navigation reachable from the folded rail by hover and keyboard", () => {
        cy.get("#desktop-sidebar-toggle").click();
        cy.get("#sidebar .nav-link-title").first().should("not.be.visible");
        cy.get("#sidebar").trigger("mouseover");
        cy.get("#sidebar .nav-link-title").first().should("be.visible");
        cy.get("#sidebar").trigger("mouseout");
        cy.get("#sidebar .nav-link").first().focus();
        cy.get("#sidebar .nav-link-title").first().should("be.visible");
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
            it(`leaves the ${name} hamburger unchanged after a desktop fold in ${theme} mode`, () => {
                cy.document().then((doc) => doc.documentElement.setAttribute("data-bs-theme", theme));
                cy.get("#desktop-sidebar-toggle").click();
                cy.viewport(width, 820);
                cy.get("#desktop-sidebar-toggle").should("not.be.visible");
                cy.get("#sidebar").should("be.visible");
                expectLayout(0);
                capture(`sidebar-${name}-${width}x820-${theme}-closed`);
                cy.get("#menu-toggle").should("be.visible").click();
                cy.get("#sidebar-menu").should("have.class", "show");
                expectLayout(0);
                capture(`sidebar-${name}-${width}x820-${theme}-open`);
                cy.get("#menu-toggle").click();
                cy.get("#sidebar-menu").should("not.be.visible");
            });
        }
    }
});
