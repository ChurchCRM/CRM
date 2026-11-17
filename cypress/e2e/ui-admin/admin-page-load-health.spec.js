/// <reference types="cypress" />

import { ADMIN_HEALTH_PAGES } from "../../support/page-health-pages";
import { expectPageHealth } from "../../support/page-health";

describe("Page load health: admin pages", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    ADMIN_HEALTH_PAGES.forEach((page) => {
        it(`${page} loads without JavaScript or server errors`, () => {
            expectPageHealth(page);
        });
    });
});
