/// <reference types="cypress" />

import { LEGACY_PAGES, MVC_PAGES } from "../../support/mvc-pages";
import { expectPageHealth } from "../../support/page-health";

describe("Page load health: admin pages", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    [...MVC_PAGES.admin, ...LEGACY_PAGES].forEach((page) => {
        it(`${page} loads without JavaScript or server errors`, () => {
            expectPageHealth(page);
        });
    });
});
