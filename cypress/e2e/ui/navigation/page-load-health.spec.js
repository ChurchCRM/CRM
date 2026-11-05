/// <reference types="cypress" />

import { STAFF_HEALTH_PAGES } from "../../../support/page-health-pages";
import { expectPageHealth } from "../../../support/page-health";

describe("Page load health: staff pages", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    STAFF_HEALTH_PAGES.forEach((page) => {
        it(`${page} loads without JavaScript or server errors`, () => {
            expectPageHealth(page);
        });
    });
});
