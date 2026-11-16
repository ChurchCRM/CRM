/// <reference types="cypress" />

const FOLDED = "navbar-folded-hover";
const FULL_WIDTH = 256;
const FOLDED_WIDTH = 64;

function toggleSidebar() {
    movePointer("#main-content");
    cy.get("#desktop-sidebar-toggle").click();
    cy.wait("@saveSidebar").its("response.statusCode").should("eq", 200);
}

function movePointer(selector) {
    cy.get(selector).then(($element) => {
        const win = $element[0].ownerDocument.defaultView;
        const bounds = $element[0].getBoundingClientRect();
        const frame = window.top.document.querySelector("iframe.aut-iframe").getBoundingClientRect();
        const scale = frame.width / win.innerWidth;
        return Cypress.automation("remote:debugger:protocol", {
            command: "Input.dispatchMouseEvent",
            params: {
                type: "mouseMoved",
                x: frame.left + (bounds.left + bounds.width / 2) * scale,
                y: frame.top + (bounds.top + bounds.height / 2) * scale,
            },
        });
    });
}

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

function expectOpenMenuBelowHeader() {
    cy.get("#sidebar-menu")
        .should("have.class", "show")
        .and("not.have.class", "collapsing");
    cy.scrollTo("top");
    cy.get("#sidebar").should(($sidebar) => {
        const header = $sidebar[0].ownerDocument.getElementById("topbar").getBoundingClientRect();
        expect(header.top).to.be.closeTo(0, 1);
        expect($sidebar[0].getBoundingClientRect().top).to.be.closeTo(header.bottom, 1);
    });
}


describe("Staff folded sidebar preference", () => {
    beforeEach(() => {
        cy.setupStandardSession();
        cy.viewport(1440, 900);
        cy.intercept("POST", "**/api/user/*/setting/ui.sidebar.folded").as("saveSidebar");
        cy.intercept("GET", "**/api/calendar/events-counters?*", {
            Birthdays: 1, Anniversaries: 0, Events: 2,
        }).as("menuCounts");
        cy.visit("/v2/dashboard");
        cy.wait("@menuCounts");
        cy.get("#sidebar").then(($sidebar) => {
            if ($sidebar.hasClass(FOLDED)) {
                toggleSidebar();
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

            toggleSidebar();
            cy.get("#sidebar").should("have.class", FOLDED).and("be.visible");
            expectLayout(FOLDED_WIDTH);
            cy.get("#desktop-sidebar-toggle")
                .should("have.attr", "aria-expanded", "false")
                .and("have.attr", "aria-label", "Expand sidebar");
            capture(`sidebar-desktop-1440x900-${theme}-folded`);

            toggleSidebar();
            cy.get("#sidebar").should("not.have.class", FOLDED);
            cy.get("#desktop-sidebar-toggle")
                .should("have.attr", "aria-expanded", "true")
                .and("have.attr", "aria-label", "Collapse sidebar");
            expectLayout(FULL_WIDTH);
        });
    }

    it("stores the preference on the user and restores it on reload and on other pages", () => {
        toggleSidebar();
        cy.reload();
        cy.get("#sidebar").should("have.class", FOLDED);
        expectLayout(FOLDED_WIDTH);
        cy.visit("/people/dashboard");
        cy.get("#sidebar").should("have.class", FOLDED);
        expectLayout(FOLDED_WIDTH);
        toggleSidebar();
        cy.reload();
        cy.get("#sidebar").should("not.have.class", FOLDED);
        expectLayout(FULL_WIDTH);
    });

    it("keeps navigation reachable from the folded rail by hover and keyboard", () => {
        toggleSidebar();
        movePointer("#main-content");
        cy.get("#sidebar .nav-link-title").first().should("not.be.visible");
        movePointer("#sidebar .nav-link-icon");
        cy.get("#sidebar .nav-link-title").first().should("be.visible");
        movePointer("#main-content");
        cy.then(() => Cypress.automation("remote:debugger:protocol", {
            command: "Input.dispatchKeyEvent", params: { type: "keyDown", key: "Tab", code: "Tab", windowsVirtualKeyCode: 9 },
        }));
        cy.then(() => Cypress.automation("remote:debugger:protocol", {
            command: "Input.dispatchKeyEvent", params: { type: "keyUp", key: "Tab", code: "Tab", windowsVirtualKeyCode: 9 },
        }));
        cy.get("#sidebar .nav-link").first().focus();
        cy.get("#sidebar .nav-link-title").first().should("be.visible");
    });

    for (const theme of ["light", "dark"]) {
        it(`shows one indicator for positive counts on the folded rail in ${theme} mode`, () => {
            cy.document().then((doc) => doc.documentElement.setAttribute("data-bs-theme", theme));
            toggleSidebar();
            movePointer("#main-content");
            cy.get("#BirthdateNumber").closest(".nav-link").should("have.class", "sidebar-has-count");
            cy.get("#BirthdateNumber").should("have.text", "1").and("have.css", "clip-path", "inset(50%)");
            cy.get("#EventsNumber").should("have.text", "2").and("have.css", "clip-path", "inset(50%)");
            movePointer("#sidebar .sidebar-has-count > .nav-link-icon");
            cy.get("#BirthdateNumber").should("have.css", "clip-path", "none").and("be.visible");
            movePointer("#main-content");
            capture(`sidebar-${theme}-count-indicator`);
            cy.intercept("GET", "**/api/calendar/events-counters?*", {
                Birthdays: 0, Anniversaries: 0, Events: 0,
            }).as("emptyCounts");
            cy.window().then((win) => win.CRM.dashboard.loadEventCounters());
            cy.wait("@emptyCounts");
            cy.get("#BirthdateNumber").closest(".nav-link").should("not.have.class", "sidebar-has-count");
            toggleSidebar();
            cy.get("#BirthdateNumber").should("be.visible").and("have.text", "0");
        });
    }

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
        toggleSidebar();
        expectMapCenter();
        toggleSidebar();
        expectMapCenter();
    });

    for (const theme of ["light", "dark"]) {
        for (const [name, width] of [["tablet", 820], ["phone", 390]]) {
            it(`opens the ${name} menu below the header in ${theme} mode`, () => {
                cy.document().then((doc) => doc.documentElement.setAttribute("data-bs-theme", theme));
                toggleSidebar();
                cy.viewport(width, 820);
                cy.get("#desktop-sidebar-toggle").should("not.be.visible");
                cy.get("#sidebar").should("be.visible");
                cy.get("#topbar").should(($header) => {
                    expect($header[0].getBoundingClientRect().top).to.be.closeTo(0, 1);
                });
                expectLayout(0);
                capture(`sidebar-${name}-${width}x820-${theme}-closed`);
                cy.scrollTo(0, 40);
                cy.get("#menu-toggle").should("be.visible").click();
                expectOpenMenuBelowHeader();
                expectLayout(0);
                capture(`sidebar-${name}-${width}x820-${theme}-open`);
                cy.get("#menu-toggle").click();
                cy.get("#sidebar-menu").should("not.be.visible");
                cy.viewport(1440, 900);
                expectLayout(FOLDED_WIDTH);
                cy.get("#topbar").should(($header) => {
                    expect($header[0].getBoundingClientRect().left).to.be.closeTo(FOLDED_WIDTH, 1);
                });
            });
        }
    }
});
