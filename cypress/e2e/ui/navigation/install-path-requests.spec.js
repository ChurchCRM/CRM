/// <reference types="cypress" />

import { LEGACY_PAGES, MVC_PAGES } from "../../../support/mvc-pages";

// A subdirectory install serves the app under a path such as /churchcrm. A request that
// skips that path leaves the install, so every same-origin request a page makes stays inside it.
const base = () => new URL(Cypress.config("baseUrl"));
const installPath = () => base().pathname.replace(/\/$/, "");

describe("Page requests stay inside the install path", () => {
    let seen;

    const watchRequests = () => {
        seen = { outside: [], missingAssets: [] };
        cy.intercept({ url: /.*/ }, (req) => {
            const url = new URL(req.url);
            if (url.origin !== base().origin) {
                return;
            }
            if (url.pathname !== installPath() && !url.pathname.startsWith(`${installPath()}/`)) {
                seen.outside.push(`${req.method} ${url.pathname}`);
            }
            if (["script", "stylesheet"].includes(req.resourceType)) {
                req.on("response", (res) => {
                    if (res.statusCode === 404) {
                        seen.missingAssets.push(url.pathname);
                    }
                });
            }
        });
    };

    const expectNoStrayRequests = () => {
        // Late fetches start after the load event.
        cy.wait(1000);
        cy.then(() => {
            expect(seen.outside, "requests outside the install path").to.deep.equal([]);
            expect(seen.missingAssets, "scripts and styles that returned 404").to.deep.equal([]);
        });
    };

    context("signed in as an administrator", () => {
        beforeEach(() => {
            cy.setupAdminSession();
            watchRequests();
        });

        [...Object.values(MVC_PAGES).flat(), ...LEGACY_PAGES].forEach((page) => {
            it(`${page} requests only its own install`, () => {
                cy.visit(page, { failOnStatusCode: false });
                cy.get("body").should("exist");
                expectNoStrayRequests();
            });
        });
    });

    context("signed out", () => {
        beforeEach(() => {
            cy.clearAllCookies();
            watchRequests();
        });

        it("the sign-in page requests only its own install", () => {
            cy.visit("/session/begin", { failOnStatusCode: false });
            cy.get("body").should("exist");
            expectNoStrayRequests();
        });
    });
});
