/// <reference types="cypress" />

// A path stored with a leading slash used to render as //people/..., which the
// browser treats as http://people/...

describe("Admin sidebar navigation", () => {
    const appHost = () => new URL(Cypress.config("baseUrl")).host;

    const sidebarHrefs = () => cy.get("#sidebar-menu a[href]").then(($anchors) => {
        const hrefs = [];
        $anchors.each((_, anchor) => {
            const href = anchor.getAttribute("href");
            if (!href || href.startsWith("#") || anchor.getAttribute("target") === "_blank") {
                return;
            }
            const url = new URL(href, Cypress.config("baseUrl"));
            expect(url.host, `sidebar href ${href}`).to.equal(appHost());
            if (!hrefs.includes(href)) {
                hrefs.push(href);
            }
        });

        return hrefs;
    });

    it("opens every sidebar page on this site", () => {
        cy.setupAdminSession();
        cy.visit("/people/dashboard");
        sidebarHrefs().then((hrefs) => {
            expect(hrefs.length).to.be.greaterThan(10);
            cy.wrap(hrefs, { log: false }).each((href) => {
                cy.visit("/people/dashboard");
                cy.get("#sidebar-menu a[href]").filter((_, el) => el.getAttribute("href") === href).first().click({ force: true });
                cy.location("host", { timeout: 20000 }).should("eq", appHost());
                const expected = new URL(href, Cypress.config("baseUrl"));
                cy.location("pathname").should("eq", expected.pathname);
                if (expected.pathname.endsWith("/people/properties/p")) {
                    cy.contains("#main-content, .container-fluid", "Person").should("be.visible");
                }
                if (expected.pathname.endsWith("/people/properties/f")) {
                    cy.contains("#main-content, .container-fluid", "Family").should("be.visible");
                }
                if (expected.pathname.endsWith("/people/properties/g")) {
                    cy.contains("#main-content, .container-fluid", "Group").should("be.visible");
                }
            });
        });
    });

    it("indents a nested menu under its toggle and keeps the current page in view", () => {
        cy.setupAdminSession();
        cy.viewport(1280, 600);
        cy.visit("/admin/people");
        cy.contains("#sidebar-menu .dropdown-item", "Self Registrations").then(($sibling) => {
            const siblingPad = parseFloat(getComputedStyle($sibling[0]).paddingInlineStart);
            cy.get("#sidebar-menu .dropdown-item.active").closest(".dropdown").children(".dropdown-toggle").then(($toggle) => {
                expect(parseFloat(getComputedStyle($toggle[0]).paddingInlineStart)).to.eq(siblingPad);
            });
            cy.get("#sidebar-menu .dropdown-item.active").should(($active) => {
                expect(parseFloat(getComputedStyle($active[0]).paddingInlineStart)).to.be.greaterThan(siblingPad);
            });
        });

        cy.visit("/admin/system/feature-toggles");
        cy.get("#sidebar-menu .dropdown-item.active").should(($active) => {
            expect($active[0].getBoundingClientRect().bottom).to.be.at.most(Cypress.config("viewportHeight"));
        });
    });
});
