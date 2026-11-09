/// <reference types="cypress" />
import { basePath } from "../../../support/urls";

describe("API Public - Standalone error pages", () => {
    const pages = [
        { url: "/errors/php-error.php", status: 503, title: "PHP Version Not Supported" },
        { url: "/errors/config-error.php?error=Config.php%20is%20not%20readable", status: 503, title: "Configuration Error" },
        { url: "/errors/not-found.php?path=old-page.php", status: 404, title: "Page Not Found" },
    ];

    pages.forEach(({ url, status, title }) => {
        it(`renders ${title} on the shared Tabler layout with local assets`, () => {
            cy.request({ url, failOnStatusCode: false }).then((response) => {
                expect(response.status).to.equal(status);
                expect(response.body).to.include(`<h1 class="h2 mb-0">${title}</h1>`);
                expect(response.body).to.include('data-theme-mode="auto"');
                expect(response.body).to.include(`href="${basePath()}/skin/v2/error.min.css"`);
                expect(response.body).to.include(`src="${basePath()}/skin/v2/theme.min.js"`);
                expect(response.body).to.include(`href="${basePath()}/"`);
                expect(response.body).to.not.include("cdn.jsdelivr.net");
            });
        });
    });

    it("follows a dark browser theme and fits a phone screen", () => {
        cy.viewport(390, 844);
        cy.visit("/errors/not-found.php?path=old-page.php", {
            failOnStatusCode: false,
            onBeforeLoad(win) {
                cy.stub(win, "matchMedia").callsFake((query) => ({
                    matches: query.includes("dark"),
                    media: query,
                    addEventListener() {},
                    removeEventListener() {},
                }));
            },
        });
        cy.get("html").should("have.attr", "data-bs-theme", "dark");
        cy.contains("h1", "Page Not Found").should("be.visible");
        cy.contains("code", "old-page.php").should("be.visible");
        cy.contains("a.btn", "Return Home").should("be.visible");
        cy.get("body").should("have.css", "background-color").and("not.eq", "rgb(255, 255, 255)");
        cy.window().then((win) => {
            expect(win.document.documentElement.scrollWidth).to.be.at.most(win.innerWidth);
        });
    });
});
