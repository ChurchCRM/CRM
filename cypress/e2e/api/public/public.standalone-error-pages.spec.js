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
        cy.window().then((win) => {
            const doc = win.document;
            const sheet = [...doc.styleSheets].find((s) => s.href?.endsWith("/skin/v2/error.min.css"));
            expect(sheet, "error.min.css").to.exist;
            expect(sheet.cssRules.length).to.be.greaterThan(0);

            const darkSurface = doc.createElement("div");
            darkSurface.style.background = "var(--tblr-gray-900)";
            doc.body.appendChild(darkSurface);
            expect(win.getComputedStyle(doc.documentElement).colorScheme).to.equal("dark");
            expect(win.getComputedStyle(doc.body).backgroundColor).to.equal(win.getComputedStyle(darkSurface).backgroundColor);
            darkSurface.remove();

            expect(doc.documentElement.scrollWidth).to.be.at.most(win.innerWidth);
        });
    });
});
